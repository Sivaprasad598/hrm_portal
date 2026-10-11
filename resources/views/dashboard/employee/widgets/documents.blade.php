@if (in_array('documents', $activeWidgets) && in_array('employees', user_modules()))
    @isset($upcomingDocumentExpiries)
        <div class="col-md-6 mb-3">
            <div class="bg-white p-20 rounded b-shadow-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-15 f-w-500 mb-0 text-darkest-grey">
                        <i class="fa fa-file-alt text-primary mr-2"></i>@lang('modules.dashboard.documents')
                    </h5>
                    <span class="badge badge-primary f-10">{{ $upcomingDocumentExpiries->count() }} @lang('app.documents')</span>
                </div>

                <div class="document-list" style="max-height: 250px; overflow-y: auto;">
                    @forelse ($upcomingDocumentExpiries as $document)
                        @php
                            $expiryDate = \Carbon\Carbon::parse($document->expiry_date);
                            $daysUntilExpiry = now()->diffInDays($expiryDate, false);
                            $isExpired = $expiryDate->isPast();
                            $isExpiringSoon = $daysUntilExpiry <= 30 && !$isExpired;
                            $isExpiringVerySoon = $daysUntilExpiry <= 7 && !$isExpired;
                        @endphp
                        <div class="document-item p-3 mb-2 rounded border-left-4 {{ $isExpired ? 'border-danger bg-light-danger' : ($isExpiringVerySoon ? 'border-warning bg-light-warning' : ($isExpiringSoon ? 'border-warning bg-light-warning' : 'border-success bg-light-success')) }}">
                            <div class="d-flex align-items-center">
                                <div class="mr-3">
                                    @if($isExpired)
                                        <i class="fa fa-exclamation-triangle text-danger f-16"></i>
                                    @elseif($isExpiringVerySoon)
                                        <i class="fa fa-exclamation-circle text-warning f-16"></i>
                                    @elseif($isExpiringSoon)
                                        <i class="fa fa-clock text-warning f-16"></i>
                                    @else
                                        <i class="fa fa-calendar-check text-success f-16"></i>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 f-13 font-weight-bold text-darkest-grey">
                                            <a href="{{ route('employees.show', $document->user_id) }}?tab=documents" class="text-darkest-grey">{{ $document->document_name }}</a>
                                        </h6>
                                        @if($document->document_number)
                                            <small class="text-muted f-10">#{{ $document->document_number }}</small>
                                        @endif
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="f-11 {{ $isExpired ? 'text-danger' : ($isExpiringVerySoon ? 'text-warning' : ($isExpiringSoon ? 'text-warning' : 'text-success')) }}">
                                                @if($isExpired)
                                                    <i class="fa fa-times-circle mr-1"></i>@lang('app.expired') {{ $expiryDate->diffForHumans() }}
                                                @elseif($isExpiringVerySoon)
                                                    <i class="fa fa-exclamation-triangle mr-1"></i>@lang('app.expires') {{ $expiryDate->diffForHumans() }}
                                                @elseif($isExpiringSoon)
                                                    <i class="fa fa-clock mr-1"></i>@lang('app.expires') {{ $expiryDate->diffForHumans() }}
                                                @else
                                                    <i class="fa fa-calendar mr-1"></i>@lang('app.expires') {{ $expiryDate->diffForHumans() }}
                                                @endif
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <small class="text-muted f-10 mr-2">{{ $expiryDate->format('M d, Y') }}</small>
                                            @if($isExpired)
                                                <span class="badge badge-danger f-9">@lang('app.expired')</span>
                                            @elseif($isExpiringVerySoon)
                                                <span class="badge badge-warning f-9">@lang('app.urgent')</span>
                                            @elseif($isExpiringSoon)
                                                <span class="badge badge-warning f-9">@lang('app.soon')</span>
                                            @else
                                                <span class="badge badge-success f-9">@lang('app.valid')</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="fa fa-file-alt text-lightest f-24 mb-2"></i>
                            <p class="mb-0 f-12 text-lightest">@lang('messages.noDocumentExpiries')</p>
                        </div>
                    @endforelse
                    
                    {{-- @if($upcomingDocumentExpiries->count() > 5)
                        <div class="text-center mt-2">
                            <small class="text-muted f-11">
                                <i class="fa fa-ellipsis-h mr-1"></i>+{{ $upcomingDocumentExpiries->count() - 5 }} more documents
                            </small>
                        </div>
                    @endif --}}
                </div>
            </div>
        </div>
    @endisset
@endif