{{-- Pages of employees for the attendance grids: a month for every employee in one
     response took 20 s and 5 MB, which a shared server times out on. --}}
@if (!empty($pager) && $pager['pages'] > 1)
    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 border-top-grey">
        <span class="f-13 text-dark-grey">Showing {{ $pager['from'] }}–{{ $pager['to'] }} of {{ $pager['total'] }} employees</span>
        <div class="btn-group btn-group-sm">
            <a href="javascript:;" class="btn btn-outline-secondary attendance-page {{ $pager['page'] <= 1 ? 'disabled' : '' }}" data-page="{{ $pager['page'] - 1 }}">&lsaquo; Previous</a>
            <span class="btn btn-outline-secondary disabled">Page {{ $pager['page'] }} of {{ $pager['pages'] }}</span>
            <a href="javascript:;" class="btn btn-outline-secondary attendance-page {{ $pager['page'] >= $pager['pages'] ? 'disabled' : '' }}" data-page="{{ $pager['page'] + 1 }}">Next &rsaquo;</a>
        </div>
    </div>
@endif
