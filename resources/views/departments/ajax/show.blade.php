@php
$editDepartmentPermission = user()->permission('edit_department');
$deleteDepartmentPermission = user()->permission('delete_department');
@endphp

<div id="department-section">
    <div class="row">
        <div class="col-sm-12">
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-header bg-white  border-bottom-grey  justify-content-between p-20">
                    <div class="row">
                        <div class="col-12">
                            <h3 class="heading-h1">{{ $department->parent_id ? 'Sub Division' : 'Division' }}</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body dvs">
                    {{-- who it is and where it sits --}}
                    <div class="dvs-head">
                        <span class="dvs-avatar">{{ collect(preg_split('/\s+/', trim($department->team_name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</span>
                        <div class="dvs-title">
                            <h4>{{ $department->team_name }}</h4>
                            <div class="dvs-path">
                                @forelse ($path as $step)
                                    <a href="{{ route('departments.show', $step->id) }}" class="openRightModal">{{ $step->team_name }}</a> <span>›</span>
                                @empty
                                    <span>Top-level division</span>
                                @endforelse
                                @if ($path->isNotEmpty()) <b>{{ $department->team_name }}</b> @endif
                            </div>
                        </div>
                    </div>

                    <div class="dvs-stats">
                        <div><b>{{ $members->count() }}</b><span>employees in total</span></div>
                        <div><b>{{ $subDivisions->count() }}</b><span>sub divisions</span></div>
                        <div><b>{{ $ownCount }}</b><span>directly in {{ $department->team_name }}</span></div>
                    </div>

                    {{-- sub divisions, every level, with their people --}}
                    @if ($subDivisions->isNotEmpty())
                        <h5 class="dvs-h">Sub divisions</h5>
                        <div class="dvs-subs">
                            @foreach ($subDivisions as $sub)
                                <a href="{{ route('departments.show', $sub->id) }}" class="dvs-chip openRightModal" title="{{ $sub->path }}">
                                    {{ $sub->depth ? $sub->path : $sub->name }} <span class="dvs-chip__n">{{ $sub->people }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    {{-- the people --}}
                    <div class="dvs-h dvs-h--row">
                        <h5 class="mb-0">Employees</h5>
                        @if ($members->count() > 8)
                            <input type="search" class="dvs-search" placeholder="Search name or ID" oninput="
                                var q = this.value.toLowerCase();
                                document.querySelectorAll('.dvs-person').forEach(function (r) { r.style.display = r.dataset.q.indexOf(q) > -1 ? '' : 'none'; });">
                        @endif
                    </div>
                    @if ($members->isEmpty())
                        <p class="dvs-none">No active employees here yet.</p>
                    @else
                        <div class="dvs-people">
                            @foreach ($members as $m)
                                <a class="dvs-person" href="{{ route('employees.show', $m->id) }}"
                                   data-q="{{ strtolower($m->name . ' ' . $m->employee_id) }}">
                                    <img src="{{ $m->image ? asset_url_local_s3('avatar/' . $m->image) : asset('img/gravatar.png') }}" alt="" onerror="this.src='{{ asset('img/gravatar.png') }}'">
                                    <span class="dvs-person__main">
                                        <b>{{ $m->name }}</b>
                                        <small>{{ $m->employee_id }}{{ $m->designation ? ' · ' . $m->designation : '' }}</small>
                                    </span>
                                    @if ($m->department_id != $department->id)
                                        <span class="dvs-person__sub">{{ $deptNames[$m->department_id] ?? '' }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <style>
                    .dvs { padding: 20px 22px !important; }
                    .dvs-head { display: flex; align-items: center; gap: 14px; }
                    .dvs-avatar { width: 48px; height: 48px; flex: none; border-radius: 12px; display: flex; align-items: center; justify-content: center;
                        background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); color: var(--epic-blue, #cb6b4f); font-weight: 700; font-size: 16px; }
                    .dvs-title h4 { margin: 0; font-size: 18px; font-weight: 600; color: #101828; }
                    .dvs-path { font-size: 13px; color: #667085; margin-top: 2px; }
                    .dvs-path a { color: var(--epic-blue, #cb6b4f); }
                    .dvs-path b { color: #344054; font-weight: 600; }
                    .dvs-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 18px 0 6px; }
                    .dvs-stats > div { border: 1px solid #eaecf0; border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; }
                    .dvs-stats b { font-size: 22px; color: #101828; line-height: 1.2; }
                    .dvs-stats span { font-size: 12.5px; color: #667085; }
                    .dvs-h { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085; margin: 20px 0 10px; }
                    .dvs-h--row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
                    .dvs-h--row h5 { font-size: 12px; font-weight: 600; color: #667085; }
                    .dvs-subs { display: flex; flex-wrap: wrap; gap: 8px; }
                    .dvs-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 6px 5px 12px; border-radius: 999px; background: #f9fafb;
                        border: 1px solid #eaecf0; font-size: 13px; color: #344054 !important; }
                    .dvs-chip:hover { border-color: var(--epic-blue, #cb6b4f); color: var(--epic-blue, #cb6b4f) !important; }
                    .dvs-chip__n { min-width: 22px; height: 20px; padding: 0 6px; border-radius: 999px; background: #fff; border: 1px solid #eaecf0;
                        display: inline-flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 600; color: #475467; }
                    .dvs-search { height: 34px; width: 220px; padding: 0 12px; border: 1px solid #e8e9e9; border-radius: 8px; font-size: 13px; text-transform: none; letter-spacing: 0; }
                    .dvs-search:focus { outline: 0; border-color: var(--epic-blue, #cb6b4f); }
                    .dvs-people { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px; max-height: 420px; overflow-y: auto; }
                    .dvs-person { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #f2f4f7; border-radius: 10px; color: inherit !important; min-width: 0; }
                    .dvs-person:hover { border-color: #eaecf0; background: #fcfcfd; }
                    .dvs-person img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex: none; }
                    .dvs-person__main { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
                    .dvs-person__main b { font-size: 13.5px; font-weight: 600; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                    .dvs-person__main small { font-size: 12px; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                    .dvs-person__sub { flex: none; font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #f2f4f7; color: #475467; max-width: 110px;
                        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                    .dvs-none { color: #98a2b3; font-size: 13px; }
                    @media (max-width: 576px) { .dvs-stats { grid-template-columns: 1fr; } }
                </style>
            </div>
        </div>
    </div>
</div>

<script>
    $('body').on('click', '.delete-department', function() {
        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.recoverRecord')",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('messages.confirmDelete')",
            cancelButtonText: "@lang('app.cancel')",
            customClass: {
                confirmButton: 'btn btn-primary mr-3',
                cancelButton: 'btn btn-secondary'
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{ route('departments.destroy', $department->id) }}";

                var token = "{{ csrf_token() }}";

                $.easyAjax({
                    type: 'POST',
                    url: url,
                    data: {
                        '_token': token,
                        '_method': 'DELETE'
                    },
                    success: function(response) {
                        if (response.status == "success") {
                            window.location.href = response.redirectUrl;
                        }
                    }
                });
            }
        });
    });
</script>
