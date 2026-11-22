# Page rules: AJAX page flow and the standard page UI

Every new page, and every page that is changed, follows these rules. A page that
follows them opens and moves in place: no white flash, the sidebar stays put,
and the switch is quicker. A page that breaks them still works, but drops back
to a full page load.

Reference implementation: the Recruitment module (`resources/views/recruitment`).

---

## 1. How a page loads

| What the person does | What happens |
|---|---|
| Clicks the sidebar, icon strip, page tabs or Settings menu | **Content swap**: only the page content is fetched (`X-Page-Swap: 1`) and put in place |
| Clicks a link inside content marked `data-swap-links` | Content swap |
| Changes a filter (GET form) | Content swap through `PageSwap.submit(form)` |
| Saves in a side panel / pop-up | AJAX post, then `PageSwap.reload()`: the panel closes and the page under it (board, list, record) shows the change - the person stays where they were |
| Opens a side panel or pop-up | Panel loaded by AJAX (`openRightModal`, `data-toggle`); the page stays |
| Downloads, exports, prints, logs out, opens a new tab | Normal browser behaviour |

Anything the swap cannot use (an error page, the login page, a different layout,
a script that fails) is opened as a normal page load, and that page is not swapped
again for 15 minutes. Nothing is lost if a page is not swap-ready; it is just slower.

Switches: `PAGE_SWAP_ENABLED=false` in `.env` (all), the `NEVER` list in
`resources/views/sections/page-swap.blade.php` (one page), `class="no-swap"` (one link).

The machinery: `sections/page-swap-track.blade.php` (keeps track of what a page attaches,
plus the `PageSwap.open / reload / submit` helpers), `sections/page-swap.blade.php` (the swap),
`layouts/swap.blade.php` + `layouts/page-body.blade.php` (the server's answer),
`App\Support\PageScripts` (makes a page's scripts safe to run again).

---

## 2. Server side

1. **A page is a view that extends `layouts.app`.** Its markup goes in `@section('content')`
   (and `@section('filter-section')` if it has one), its CSS in `@push('styles')`, its
   scripts inline in the content or in `@push('scripts')`. Nothing is printed outside these.
2. **The same view serves the full page and the swap.** Never check `X-Page-Swap` in a
   controller; the layout does it.
3. **Side panels and pop-ups**: when `request()->ajax()`, answer
   `Reply::dataOnly(['status' => 'success', 'html' => ..., 'title' => ...])`; otherwise
   show the same view as a page (see `RecruitBaseController::panel()`), so a panel's
   link also works when it is opened on its own.
4. **Saves answer JSON**: `Reply::success()` / `Reply::successWithData(..., ['redirectUrl' => ...])`,
   or `Reply::error()` (status `fail`). Never a redirect response to an AJAX post.
5. **Downloads, exports, PDFs, prints** have their own URL containing `export`, `download`,
   `pdf` or `print`, so the swap never takes them.
6. **Filters are GET parameters**, so the page's address is always shareable and Back works.

## 3. Page scripts

1. **Start code** with `$(function () { ... })` or `document.addEventListener('DOMContentLoaded', ...)`.
   Both run on a swap too, after the content is in.
2. **Top-level `const` / `let` are fine** (they are turned into `var` so the page can run
   twice), but never count on a page variable keeping its value between visits.
3. **Handlers**: on elements inside the content, anything goes. On `body` / `document` /
   `window`, attach them in the page's inline script (they are taken off when the page is
   left). Never attach page handlers from a shared script file.
4. **Timers**: `setInterval` is cleared when the page is left; a `setTimeout` chain is not,
   so use `setInterval` for anything repeating.
5. **`<body>` classes are reset on every page.** A page that adds one (e.g. `rc-filters-open`)
   sets it again from its own start code. (The `swal2-*` classes of a message on screen are kept.)
6. **No `document.write`**; no markup appended to `<body>` except through the standard
   widgets (datepicker, select picker, tooltips), which the swap cleans up.
7. **Moving to another page from code**: `PageSwap.open(url)`. After a save that stays on
   the page: `PageSwap.reload()` (same scroll, no extra Back step). Never
   `window.location.href = ...` or `location.reload()`; the only exception is jumping to a
   `#section` of the page.
   Both close an open side panel first and work on `PageSwap.shownUrl` - the page whose
   content is on screen - because while a panel is open the address bar shows the panel's
   address. Never read `location.href` for "this page" while a panel can be open.
   Back / Forward are handled by the swap: Back with a panel open closes the panel; otherwise
   the page at that address is shown in place.
8. **Applying a filter form from code**: `PageSwap.submit(form)`, never `form.submit()`.
   The person can carry on typing in the search box after the page updates.

## 4. Links

1. Wrap the page's content in `<div class="content-wrapper" data-swap-links>`; its links
   then move in place.
2. A link that must load in full: `class="no-swap"`. A download: the `download` attribute.
   A new tab: `target="_blank"`.
3. A link that opens a side panel: `class="openRightModal"`; a pop-up: `data-toggle="modal"`.
   These never swap.
4. A clickable card or row that opens a panel calls `e.preventDefault()` in its handler,
   so the swap leaves the click alone.

## 5. Standard page UI

1. **Top-right bar**: the page's own buttons (Add ...) and a **Filters** button
   (`recruitment.partials.standard-bar`).
2. **Filter card hidden** until Filters is pressed; open on load when a filter is in use.
3. **No Filter / Clear buttons**: a change applies at once; typing in a search box applies
   after a short pause (0.8 s) or on Enter.
4. **Add / edit / move happen in a side panel**, not on a separate page; the page
   behind updates when the panel saves.
5. **Lists**: paged, the list and its foot in one white card, the foot as the app's tables (Employees):
   "Show [10] entries" on the left, "Showing 1 to 10 of 15 entries" and Previous / pages / Next on the right
   (`recruitment.partials.list-footer`, as on Resumes). A card page (Positions) shows no foot, only the pages when there is more than one.
6. **Words**: Division / Sub Division, never Department (code and stored names unchanged).
7. **Phone width**: the filter fields wrap below 992 px; tables sit in `.table-responsive`;
   boards scroll inside their own area; nothing scrolls the whole page sideways.

## 6. Before a page is done

- [ ] Open it from the sidebar, then click its in-page links and filters: the sidebar
      does not flicker and the browser console shows no errors.
- [ ] Open it, go elsewhere, come back: everything works again, and a click acts once (not twice).
- [ ] Save something in its side panel: the page updates in place.
- [ ] Back / Forward and a refresh (F5) still land on the right page.
- [ ] At phone width the page is usable with no sideways scroll.
