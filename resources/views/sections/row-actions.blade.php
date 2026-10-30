{{-- Row actions as buttons on the row, everywhere: a table row's "⋮" menu
     (View / Edit / Delete / Approve ...) is turned into the same actions shown
     side by side, so nothing has to be opened to find them.

     The menu's own links are moved, not copied: their classes, data-attributes
     and click handlers stay as they were, so every action behaves as before.
     Runs on page load, after every DataTables draw and on content loaded later
     (tabs, modals). Only menus inside a table cell are touched; drop-downs such
     as a select picker or the page's bulk "Select action" are left alone. --}}
<style>
    /* Buttons on the row (also used by Divisions / Designations directly) */
    .row-actions { display: inline-flex; align-items: center; justify-content: flex-end; flex-wrap: nowrap; gap: 4px; white-space: nowrap; }
    .row-actions.task_view { border: 0; border-radius: 0; }
    .row-actions .row-action,
    .row-actions .row-action.dropdown-item,
    .row-actions .row-action.taskView {
        display: inline-flex; align-items: center; gap: 4px; width: auto; height: 26px; padding: 0 8px; margin: 0;
        border: 1px solid #d0d5dd; border-radius: 6px; background: #fff; color: #344054;
        font-size: 12px; font-weight: 500; line-height: 1; white-space: nowrap; clear: none; cursor: pointer;
    }
    .row-actions .row-action:hover,
    .row-actions .row-action:focus { border-color: #1d82f5; color: #1d82f5; background: #fff; text-decoration: none; }
    .row-actions .row-action i { margin: 0 !important; font-size: 11px; }
    /* Keep the action cell as wide as its buttons, on one line */
    table td:has(> .row-actions), table td:has(.row-actions) { white-space: nowrap; width: 1%; }
    .row-actions .row-action-danger { color: #b42318; }
    .row-actions .row-action-danger:hover,
    .row-actions .row-action-danger:focus { border-color: #f04438; color: #f04438; }
    .row-actions .row-action-success { color: #067647; }
    .row-actions .row-action-success:hover { border-color: #12b76a; color: #12b76a; }
</style>

<script>
    (function () {
        if (!window.jQuery) {
            return;
        }

        var $ = window.jQuery;

        // Which way an action leans, from its classes, icon or words.
        function tone($item) {
            var text = (($item.attr('class') || '') + ' ' + $item.text() + ' ' + ($item.find('i').attr('class') || '')).toLowerCase();

            if (/delete|remove|trash|reject|revoke|cancel|terminate|fa-times/.test(text)) {
                return 'row-action-danger';
            }

            if (/approve|accept|fa-check/.test(text)) {
                return 'row-action-success';
            }

            return '';
        }

        function inline(root) {
            $(root).find('table td .dropdown, table td .btn-group').each(function () {
                var $menuBox = $(this);

                // A select picker is a drop-down too: not a menu of actions.
                if ($menuBox.data('row-inlined') || $menuBox.hasClass('bootstrap-select') || $menuBox.closest('.bootstrap-select').length
                    || $menuBox.find('select').length) {
                    return;
                }

                var $toggle = $menuBox.find('[data-toggle="dropdown"]').first();
                var $menu = $menuBox.find('.dropdown-menu').first();
                var $items = $menu.find('a, button').filter(function () {
                    return !$(this).is('[data-toggle="dropdown"]') && ($.trim($(this).text()) !== '' || $(this).find('i, svg').length);
                });

                if (!$toggle.length || !$menu.length) {
                    return;
                }

                $menuBox.data('row-inlined', true);

                // Inside the usual "View | ⋮" pair the buttons join the View link;
                // otherwise they take the menu's place.
                var $holder = $menuBox.closest('.task_view');

                if (!$holder.length) {
                    $holder = $('<div class="row-actions"></div>');
                    $menuBox.before($holder);
                }

                $holder.addClass('row-actions');
                $holder.children('.taskView').addClass('row-action');

                $items.each(function () {
                    var $item = $(this);
                    $item.addClass('row-action ' + tone($item));
                    $holder.append($item);
                });

                $menuBox.remove();
            });

            // A lone "View" link with no menu beside it: the same look.
            $(root).find('table td .task_view').each(function () {
                $(this).addClass('row-actions').children('.taskView').addClass('row-action');
            });
        }

        var queued = false;

        function later() {
            if (!queued) {
                queued = true;
                setTimeout(function () { queued = false; inline(document.body); }, 0);
            }
        }

        $(function () {
            inline(document.body);
            $(document).on('draw.dt init.dt', later);

            if (window.MutationObserver) {
                new MutationObserver(function (changes) {
                    for (var i = 0; i < changes.length; i++) {
                        if (changes[i].addedNodes.length) {
                            later();
                            return;
                        }
                    }
                }).observe(document.body, { childList: true, subtree: true });
            }
        });
    })();
</script>
