<!-- PAGE TITLE START -->
<div {{ $attributes->merge(['class' => 'page-title']) }}>
    @php
        // Back button on detail / create / edit pages: to where the person came from,
        // or one level up when the page was opened directly (a link, a notification)
        $routeName = (string) Route::currentRouteName();
        $segments = Request::segments();
        $isDetail = preg_match('/\.(show|edit|create)$|client_details$/', $routeName)
            || collect($segments)->contains(fn ($s) => ctype_digit($s) || in_array($s, ['create', 'edit'], true));
        $parentSegments = $segments;
        array_pop($parentSegments);
        if (end($parentSegments) !== false && ctype_digit((string) end($parentSegments))) {
            array_pop($parentSegments); // .../{id}/edit -> the list
        }
        $backUrl = count($parentSegments) > 1 ? url(implode('/', $parentSegments)) : route('dashboard');

        // Somebody who is only an employee gets a plain title: no breadcrumb trail
        // (it pointed at lists they cannot open), and no back arrow on their own
        // profile pages (My Assets, My Overtime), which sit under the staff list.
        $plainEmployee = \App\Services\Access\AssignedModules::plainEmployee();
        if ($plainEmployee && $routeName === 'employees.show') {
            $isDetail = false;
        }
    @endphp
    @if ($isDetail)
        <a href="{{ $backUrl }}" class="epic-back mr-3" title="Back" aria-label="Back"
           onclick="if (document.referrer.indexOf(location.origin) === 0 && history.length > 1) { history.back(); return false; }">
            <i class="bi bi-arrow-left"></i>
        </a>
    @endif
    <div class="page-heading">
        <h2 class="mb-0 pr-3 text-dark f-18 font-weight-bold d-flex align-items-center">
            <span class="d-inline-block text-truncate mw-300">{{ $pageTitle }}</span>

            {{-- No breadcrumb trail ("Home • Attendances • ..."), for anyone: the page title alone --}}
        </h2>
    </div>
</div>
<!-- PAGE TITLE END -->
