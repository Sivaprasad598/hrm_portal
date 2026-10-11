<div class="row">
    <div class="col-sm-12">
        <div class="add-client bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey d-flex align-items-center justify-content-between">
                <span>Offboarding Checklist</span>
                <span class="f-14 font-weight-400 text-muted">{{ $exit->employee->name }}</span>
            </h4>

            <div class="p-20">
                {{-- Overall Progress --}}
                @php
                    $total = $exit->checklists->count();
                    $done  = $exit->checklists->whereIn('status', ['completed', 'not_applicable'])->count();
                    $pct   = $total ? (int)round($done/$total*100) : 0;
                @endphp
                <div class="d-flex align-items-center mb-4">
                    <div class="progress flex-grow-1 mr-3" style="height:12px;border-radius:6px;">
                        <div class="progress-bar bg-success" style="width:{{ $pct }}%;transition:width 0.4s ease;"></div>
                    </div>
                    <span class="f-14 font-weight-700">{{ $pct }}% Complete</span>
                </div>

                <form id="checklistForm">
                    @csrf
                    @php
                        $categoryLabels = [
                            'hr_clearance'         => ['label' => 'HR Clearance',         'icon' => 'fa-users',       'color' => '#007bff'],
                            'it_clearance'         => ['label' => 'IT Clearance',         'icon' => 'fa-laptop',      'color' => '#6f42c1'],
                            'finance_clearance'    => ['label' => 'Finance Clearance',    'icon' => 'fa-money-bill',  'color' => '#28a745'],
                            'department_clearance' => ['label' => 'Department Clearance', 'icon' => 'fa-building',    'color' => '#fd7e14'],
                            'management_clearance' => ['label' => 'Management Clearance', 'icon' => 'fa-user-tie',    'color' => '#dc3545'],
                        ];
                    @endphp

                    @foreach($categoryLabels as $catKey => $catInfo)
                        @php $items = $checklistsByCategory[$catKey] ?? collect(); @endphp
                        @if($items->isEmpty()) @continue @endif

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header d-flex align-items-center py-2 px-3"
                                 style="background:{{ $catInfo['color'] }}15;border-left:4px solid {{ $catInfo['color'] }};">
                                <i class="fa {{ $catInfo['icon'] }} mr-2" style="color:{{ $catInfo['color'] }};"></i>
                                <strong class="f-14" style="color:{{ $catInfo['color'] }};">{{ $catInfo['label'] }}</strong>
                                @php
                                    $catDone  = $items->whereIn('status', ['completed', 'not_applicable'])->count();
                                    $catTotal = $items->count();
                                @endphp
                                <span class="ml-auto f-12 text-muted">{{ $catDone }}/{{ $catTotal }}</span>
                            </div>
                            <div class="card-body p-0">
                                @foreach($items as $item)
                                <div class="d-flex align-items-center px-3 py-2 border-bottom" style="min-height:48px;">
                                    <div class="flex-grow-1 f-13">
                                        {{ $item->item_label }}
                                        @if($item->completed_date && $item->status === 'completed')
                                            <br><span class="f-11 text-muted">Completed on {{ $item->completed_date->format(company()->date_format) }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <select name="items[{{ $item->id }}][status]" class="form-control form-control-sm mr-2 checklist-status" style="width:130px;">
                                            <option value="pending"         {{ $item->status === 'pending'         ? 'selected' : '' }}>Pending</option>
                                            <option value="completed"       {{ $item->status === 'completed'       ? 'selected' : '' }}>Completed</option>
                                            <option value="not_applicable"  {{ $item->status === 'not_applicable'  ? 'selected' : '' }}>N/A</option>
                                        </select>
                                        <input type="text" name="items[{{ $item->id }}][remarks]"
                                               class="form-control form-control-sm" style="width:140px;"
                                               placeholder="Remarks..."
                                               value="{{ $item->remarks ?? '' }}">
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex mt-3">
                        <button type="button" id="saveChecklistBtn" class="btn btn-primary mr-3">
                            <i class="fa fa-save mr-1"></i> Save Checklist
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="$(RIGHT_MODAL).modal('hide')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        $('#saveChecklistBtn').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '{{ route('employee-exit.update-checklist', $exit->id) }}',
                method: 'POST',
                data: $('#checklistForm').serialize() + '&_method=PATCH',
                success: function (res) {
                    if (res.status === 'success') {
                        toastr.success(res.message);
                        if (typeof window['employee-exit-table'] !== 'undefined') {
                            window['employee-exit-table'].draw();
                        }
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function () {
                    toastr.error('Error saving checklist.');
                },
                complete: function () {
                    btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Save Checklist');
                }
            });
        });
    });
</script>
