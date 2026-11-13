@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
    <style>
        .dv-search { position: relative; width: 300px; max-width: 100%; margin-left: auto; }
        .dv-search i, .dv-search svg { position: absolute; z-index: 1; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; font-size: 13px; }
        .dv-search input { width: 100%; height: 38px; padding: 0 12px 0 34px; border: 1px solid var(--epic-line, #e8e9e9); border-radius: 8px;
            font-size: 14px; background: #fff; outline: 0; }
        .dv-search input:focus { border-color: var(--epic-blue, #cb6b4f); box-shadow: 0 0 0 3px color-mix(in srgb, var(--epic-blue, #cb6b4f) 15%, transparent); }
        .dv-summary { display: flex; gap: 18px; flex-wrap: wrap; margin: 16px 0 14px; font-size: 13px; color: #667085; }
        .dv-summary b { color: #101828; font-size: 15px; margin-right: 3px; }
        .dv-table-wrap { background: #fff; border: 1px solid var(--epic-line, #e8e9e9); border-radius: 12px; box-shadow: 0 1px 3px rgba(16,24,40,.06); overflow-x: auto; }
        .dv-table { width: 100%; border-collapse: collapse; }
        .dv-table th { padding: 14px 18px; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085;
            border-bottom: 1px solid var(--epic-line, #e8e9e9); background: #fcfcfd; white-space: nowrap; }
        .dv-table td { padding: 14px 18px; border-bottom: 1px solid #f2f4f7; vertical-align: middle; }
        .dv-table tbody tr:last-child td { border-bottom: 0; }
        .dv-table tbody tr:hover td { background: #fcfcfd; }
        .dv-div { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .dv-num { font-size: 15px; color: #101828; }
        .dv-table .dv-card__body { padding: 0; }
        .dv-grid-unused { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
        .dv-card { background: #fff; border: 1px solid var(--epic-line, #e8e9e9); border-radius: 12px; box-shadow: 0 1px 3px rgba(16,24,40,.06);
            display: flex; flex-direction: column; }
        .dv-card__head { display: flex; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #f2f4f7; }
        .dv-card__avatar { width: 40px; height: 40px; flex: none; border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); }
        .dv-card__title { flex: 1 1 auto; min-width: 0; }
        .dv-card__title a { display: block; font-size: 15px; font-weight: 600; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dv-card__title a:hover { color: var(--epic-blue, #cb6b4f); }
        .dv-card__title small { font-size: 12.5px; color: #667085; }
        .dv-card__actions { display: flex; gap: 6px; }
        .dv-card__actions a { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 8px;
            border: 1px solid var(--epic-line, #e8e9e9); color: #475467; font-size: 12px; }
        .dv-card__actions a:hover { border-color: var(--epic-blue, #cb6b4f); color: var(--epic-blue, #cb6b4f); }
        .dv-card__actions a.dv-danger:hover { border-color: #f04438; color: #f04438; }
        .dv-card__body { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 18px 16px; }
        .dv-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 6px 5px 12px; border-radius: 999px; background: #f9fafb;
            border: 1px solid #eaecf0; font-size: 13px; }
        .dv-chip a { color: #344054; }
        .dv-chip a:hover { color: var(--epic-blue, #cb6b4f); }
        .dv-chip__n { min-width: 22px; height: 20px; padding: 0 6px; border-radius: 999px; background: #fff; border: 1px solid #eaecf0;
            font-size: 11.5px; font-weight: 600; color: #475467; display: inline-flex; align-items: center; justify-content: center; }
        .dv-chip__act { display: none; color: #98a2b3 !important; font-size: 11px; padding: 0 2px; }
        .dv-chip:hover .dv-chip__act { display: inline; }
        .dv-chip__act.dv-danger:hover { color: #f04438 !important; }
        .dv-chip.is-hit { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); border-color: var(--epic-blue, #cb6b4f); }
        .dv-none { font-size: 13px; color: #98a2b3; }
        .dv-empty { margin: 24px 0; text-align: center; color: #98a2b3; font-size: 14px; }
        /* a division, then its sub divisions indented under it */
        #departments-table .dv-division { font-weight: 600; color: #101828; }
        #departments-table .dv-count { display: inline-block; font-weight: 500; font-size: 12px; color: #667085; margin-left: 6px; }
        #departments-table .dv-sub { display: flex; align-items: center; gap: 8px; padding-left: 18px; color: #344054; }
        #departments-table .dv-sub::before { content: ''; width: 10px; height: 12px; border-left: 1.5px solid #d0d5dd; border-bottom: 1.5px solid #d0d5dd;
            border-bottom-left-radius: 4px; margin-top: -8px; flex: none; }
        #departments-table .dv-parent { font-size: 12px; color: #98a2b3; margin-left: 4px; }
        .filter-box {
            z-index: 2;
        }
    </style>
@endpush

@php
    $addDepartmentPermission = user()->permission('add_department');
@endphp

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">

        <!-- Add Task Export Buttons Start -->
        <div class="d-grid d-lg-flex d-md-flex action-bar">
            <div id="table-actions" class="flex-grow-1 align-items-center">
                @if ($addDepartmentPermission == 'all')
                    <x-forms.link-primary :link="route('departments.create')" class="mr-3 openRightModal float-left"
                                          icon="plus">
                        Add Division
                    </x-forms.link-primary>
                @endif
            </div>

            {{-- Search, at the top right of the table (no filter bar) --}}
            <div class="dv-search">
                <i class="fa fa-search"></i>
                <input type="text" id="search-text-field" placeholder="Search division or sub division" autocomplete="off">
            </div>


            {{-- Table View / Division Hierarchy switch removed: the table is the one view --}}
        </div>


        @php
            $canEditDiv = user()->permission('edit_department') == 'all';
            $canDeleteDiv = user()->permission('delete_department') == 'all';
            $initials = fn ($n) => strtoupper(collect(preg_split('/\s+/', trim($n)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
        @endphp

        {{-- One card per division, its sub divisions inside it --}}
        <div class="dv-summary">
            <span><b>{{ $divisionCards->count() }}</b> divisions</span>
            <span><b>{{ $divisionCards->sum(fn ($d) => $d['subs']->count()) }}</b> sub divisions</span>
            <span><b>{{ $divisionCards->sum('people') }}</b> employees</span>
        </div>

        <div class="dv-table-wrap">
            <table class="dv-table" id="dv-grid">
                <thead>
                    <tr>
                        <th style="width:28%">Division</th>
                        <th>Sub divisions</th>
                        <th class="text-right" style="width:110px">Employees</th>
                        <th class="text-right" style="width:100px">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($divisionCards as $d)
                    <tr class="dv-row" data-find="{{ strtolower($d['name'] . ' ' . $d['subs']->pluck('name')->implode(' ')) }}">
                        <td>
                            <div class="dv-div">
                                <span class="dv-card__avatar">{{ $initials($d['name']) }}</span>
                                <div class="dv-card__title">
                                    <a href="{{ route('departments.show', $d['id']) }}" class="openRightModal">{{ $d['name'] }}</a>
                                    <small>{{ $d['subs']->count() }} sub division{{ $d['subs']->count() == 1 ? '' : 's' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="dv-card__body">
                                @forelse ($d['subs'] as $sub)
                                    <span class="dv-chip" data-find="{{ strtolower($sub['name']) }}">
                                        <a href="{{ route('departments.show', $sub['id']) }}" class="openRightModal">{{ $sub['name'] }}</a>
                                        <span class="dv-chip__n" title="Employees">{{ $sub['people'] }}</span>
                                        @if ($canEditDiv)
                                            <a href="{{ route('departments.edit', $sub['id']) }}" class="openRightModal dv-chip__act" title="Edit"><i class="fa fa-pen"></i></a>
                                        @endif
                                        @if ($canDeleteDiv)
                                            <a href="javascript:;" class="delete-table-row dv-chip__act dv-danger" data-department-id="{{ $sub['id'] }}" title="Delete"><i class="fa fa-times"></i></a>
                                        @endif
                                    </span>
                                @empty
                                    <span class="dv-none">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="text-right">
                            <b class="dv-num">{{ $d['people'] }}</b>
                            @if ($d['own'] && $d['subs']->isNotEmpty())<div class="dv-none">{{ $d['own'] }} direct</div>@endif
                        </td>
                        <td class="text-right">
                            <div class="dv-card__actions justify-content-end">
                                <a href="{{ route('departments.show', $d['id']) }}" class="openRightModal" title="View" data-toggle="tooltip"><i class="fa fa-eye"></i></a>
                                @if ($canEditDiv)
                                    <a href="{{ route('departments.edit', $d['id']) }}" class="openRightModal" title="Edit" data-toggle="tooltip"><i class="fa fa-pen"></i></a>
                                @endif
                                @if ($canDeleteDiv)
                                    <a href="javascript:;" class="delete-table-row dv-danger" data-department-id="{{ $d['id'] }}" title="Delete" data-toggle="tooltip"><i class="fa fa-trash"></i></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="dv-empty d-none" id="dv-empty">No division or sub division matches.</p>

        <!-- The table is kept (hidden) for its Excel export -->
        <div class="d-none">

            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}

        </div>
        <!-- leave table End -->

    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')
    @include('sections.datatable_js')

    <script>
        $('#departments-table').on('preXhr.dt', function (e, settings, data) {
            const dateRangePicker = $('#datatableRange').data('daterangepicker');

            const parentId = $('#parent_id').val();
            const childId = $('#child').val();
            const searchText = $('#search-text-field').val();

            data['searchText'] = searchText;
            data['parentId'] = parentId;
            data['childId'] = childId;
        });

        // The cards are built on the server: after a change, reload them.
        const showTable = () => { window.location.reload(); }

        $('#parent_id, #child').on('change keyup',
            function () {
                if ($('#parent_id').val() != "all") {
                    $('#reset-filters').removeClass('d-none');

                } else if ($('#child').val() != "all") {
                    $('#reset-filters').removeClass('d-none');
                } else {
                    $('#reset-filters').addClass('d-none');
                }

                showTable();
            });

        // Search the cards: a division matches by its name or any sub division's;
        // inside a matching card, the matching sub divisions are highlighted.
        $('#search-text-field').on('input', function () {
            var q = this.value.trim().toLowerCase(), shown = 0;
            $('#dv-grid .dv-row').each(function () {
                var hit = !q || this.getAttribute('data-find').indexOf(q) !== -1;
                $(this).toggle(hit);
                shown += hit ? 1 : 0;
                $(this).find('.dv-chip').each(function () {
                    $(this).toggleClass('is-hit', !!q && this.getAttribute('data-find').indexOf(q) !== -1);
                });
            });
            $('#dv-empty').toggleClass('d-none', shown > 0);
        });

        $('#reset-filters').click(function () {
            $('#filter-form')[0].reset();
            $('.filter-box #status').val('not finished');
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('#reset-filters-2').click(function () {
            $('#filter-form')[0].reset();

            $('.filter-box #parent_id').val('all');
            $('.filter-box #child').val('all');
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('body').on('click', '.delete-table-row', function () {
            var id = $(this).data('department-id');
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
                    var url = "{{ route('departments.destroy', ':id') }}";
                    url = url.replace(':id', id);

                    var token = "{{ csrf_token() }}";

                    $.easyAjax({
                        type: 'POST',
                        url: url,
                        blockUI: true,
                        data: {
                            '_token': token,
                            '_method': 'DELETE'
                        },
                        success: function (response) {
                            if (response.status == "success") {
                                showTable();
                            }
                        }
                    });
                }
            });
        });

        $('#quick-action-type').change(function () {
            const actionValue = $(this).val();

            if (actionValue != '') {
                $('#quick-action-apply').removeAttr('disabled');
            } else {
                $('#quick-action-apply').attr('disabled', true);
                $('.quick-action-field').addClass('d-none');
            }
        });

        $('#quick-action-apply').click(function () {
            const actionValue = $('#quick-action-type').val();
            if (actionValue === 'delete') {
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
                        applyQuickAction();
                    }
                });

            } else {
                applyQuickAction();
            }
        });

        const applyQuickAction = () => {
            const rowdIds = $("#departments-table input:checkbox:checked").map(function () {
                return $(this).val();
            }).get();


            const url = "{{ route('departments.apply_quick_action') }}?row_ids=" + rowdIds;

            $.easyAjax({
                url: url,
                container: '#quick-action-form',
                type: "POST",
                disableButton: true,
                buttonSelector: "#quick-action-apply",
                data: $('#quick-action-form').serialize(),
                success: function (response) {
                    if (response.status === 'success') {
                        showTable();
                        resetActionButtons();
                        deSelectAll();
                        $('#quick-action-form').hide();
                    }
                }
            })
        };

    </script>
@endpush
