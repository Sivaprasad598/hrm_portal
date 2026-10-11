<div class="row">
    <div class="col-sm-12">
        <div class="add-client bg-white rounded">
            <h4 class="mb-0 p-20 f-21 font-weight-700 text-capitalize border-bottom-grey">
                Exit History — {{ $employee->name }}
            </h4>

            <div class="p-20">
                @if($histories->isEmpty())
                    <div class="text-center py-5">
                        <i class="fa fa-history f-40 text-muted mb-3 d-block"></i>
                        <p class="text-muted f-14">No exit history found for this employee.</p>
                    </div>
                @else
                    <div class="timeline-container" style="position:relative;">
                        <div style="position:absolute;left:20px;top:0;bottom:0;width:2px;background:#e9ecef;"></div>

                        @foreach($histories as $history)
                            <div class="d-flex mb-4" style="padding-left:50px;position:relative;">
                                <div style="position:absolute;left:12px;top:4px;width:18px;height:18px;border-radius:50%;background:{{ $history->new_status === 'off_boarded' ? '#dc3545' : ($history->new_status === 'notice_period' ? '#17a2b8' : ($history->new_status === 'confirmed' ? '#28a745' : '#6c757d')) }};border:2px solid white;box-shadow:0 0 0 2px {{ $history->new_status === 'off_boarded' ? '#dc3545' : ($history->new_status === 'notice_period' ? '#17a2b8' : ($history->new_status === 'confirmed' ? '#28a745' : '#6c757d')) }};"></div>

                                <div class="card border-0 shadow-sm flex-grow-1">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <strong class="f-14">{{ $history->action }}</strong>
                                            <span class="f-12 text-muted ml-2 text-nowrap">
                                                {{ $history->created_at->format(company()->date_format . ' H:i') }}
                                            </span>
                                        </div>

                                        <div class="d-flex align-items-center mb-1">
                                            @if($history->previous_status)
                                                <span class="badge badge-secondary f-11 mr-1">
                                                    {{ ucfirst(str_replace('_', ' ', $history->previous_status)) }}
                                                </span>
                                                <i class="fa fa-arrow-right text-muted mx-1 f-11"></i>
                                            @endif
                                            @if($history->new_status)
                                                <span class="badge badge-{{ $history->new_status === 'off_boarded' ? 'danger' : ($history->new_status === 'notice_period' ? 'info' : ($history->new_status === 'confirmed' ? 'success' : 'warning')) }} f-11">
                                                    {{ ucfirst(str_replace('_', ' ', $history->new_status)) }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($history->notes)
                                            <p class="f-13 text-muted mb-1">{{ $history->notes }}</p>
                                        @endif

                                        <p class="f-12 text-muted mb-0">
                                            <i class="fa fa-user mr-1"></i>
                                            By {{ $history->changedByUser?->name ?? 'System' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
