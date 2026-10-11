@php
$addDocumentPermission = user()->permission('add_documents');
$viewDocumentPermission = user()->permission('view_documents');
$deleteDocumentPermission = user()->permission('delete_documents');
$editDocumentPermission = user()->permission('edit_documents');
@endphp

<div class="d-flex flex-wrap mt-3" id="document-expiry-list">
    @forelse($documents as $document)
        @if ($viewDocumentPermission == 'all'
        || ($viewDocumentPermission == 'added' && $document->added_by == user()->id)
        || ($viewDocumentPermission == 'owned' && ($document->user_id == user()->id && $document->added_by != user()->id))
        || ($viewDocumentPermission == 'both' && ($document->added_by == user()->id || $document->user_id == user()->id)))
            
            @php
                $cardClass = 'bg-white';
                $borderClass = 'border-1';
                if ($document->is_expired) {
                    $cardClass = 'bg-warning-light';
                    $borderClass = 'border-danger border-2';
                } elseif ($document->is_expiring_soon) {
                    $cardClass = 'bg-warning-light';
                }
            @endphp

            <div class="col-md-4 mb-3">
                <div class="card {{ $cardClass }} {{ $borderClass }} shadow-sm document-expiry-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0 font-weight-bold">{{ $document->document_name }}</h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    @if ($viewDocumentPermission == 'all'
                                    || ($viewDocumentPermission == 'added' && $document->added_by == user()->id)
                                    || ($viewDocumentPermission == 'owned' && ($document->user_id == user()->id && $document->added_by != user()->id))
                                    || ($viewDocumentPermission == 'both' && ($document->added_by == user()->id || $document->user_id == user()->id)))
                                        @if($document->hashname)
                                            <a class="dropdown-item" target="_blank" href="{{ $document->doc_url }}">
                                                <i class="fa fa-eye mr-2"></i>@lang('app.view')
                                            </a>
                                            <a class="dropdown-item" href="{{ route('employee-document-expiries.download', md5($document->id)) }}">
                                                <i class="fa fa-download mr-2"></i>@lang('app.download')
                                            </a>
                                        @endif
                                    @endif

                                    @if ($editDocumentPermission == 'all'
                                    || ($editDocumentPermission == 'added' && $document->added_by == user()->id)
                                    || ($editDocumentPermission == 'owned' && ($document->user_id == user()->id && $document->added_by != user()->id))
                                    || ($editDocumentPermission == 'both' && ($document->added_by == user()->id || $document->user_id == user()->id)))
                                        <a class="dropdown-item edit-document-expiry" href="javascript:;" data-document-id="{{ $document->id }}">
                                            <i class="fa fa-edit mr-2"></i>@lang('app.edit')
                                        </a>
                                    @endif

                                    @if ($deleteDocumentPermission == 'all'
                                    || ($deleteDocumentPermission == 'added' && $document->added_by == user()->id)
                                    || ($deleteDocumentPermission == 'owned' && ($document->user_id == user()->id && $document->added_by != user()->id))
                                    || ($deleteDocumentPermission == 'both' && ($document->added_by == user()->id || $document->user_id == user()->id)))
                                        <a class="dropdown-item delete-document-expiry" href="javascript:;" data-document-id="{{ $document->id }}">
                                            <i class="fa fa-trash mr-2"></i>@lang('app.delete')
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($document->document_number)
                            <p class="text-muted small mb-2">
                                <strong>@lang('modules.employees.documentNumber'):</strong> {{ $document->document_number }}
                            </p>
                        @endif

                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">@lang('modules.employees.issueDate')</small>
                                <strong>{{ $document->issue_date->translatedFormat(company()->date_format) }}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">@lang('modules.employees.expiryDate')</small>
                                <strong>{{ $document->expiry_date->translatedFormat(company()->date_format) }}</strong>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">@lang('modules.employees.alertBefore')</small>
                                <strong>{{ $document->alert_before_days }} @lang('modules.employees.days')</strong>
                            </div>
                            <div class="col-6">
                                <span class="badge badge-{{ $document->alert_enabled == 1 ? 'success' : 'secondary' }}">
                                    @lang('modules.employees.alert') {{ $document->alert_enabled ? __('app.on') : __('app.off') }}
                                </span>
                            </div>
                        </div>

                        @if($document->is_expired)
                            <div class="alert alert-danger py-2 mb-2">
                                <i class="fa fa-exclamation-triangle mr-1"></i>
                                @lang('modules.employees.documentExpired')
                            </div>
                        @elseif($document->is_expiring_soon)
                            <div class="alert alert-warning py-2 mb-2">
                                <i class="fa fa-clock mr-1"></i>
                                @lang('modules.employees.documentExpiringSoon', ['days' => $document->days_until_expiry])
                            </div>
                        @endif

                        <div class="text-muted small">
                            @lang('modules.employees.uploaded') {{ $document->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @empty
        <div class="col-12">
            <div class="align-items-center d-flex flex-column text-lightest p-20 w-100">
                <i class="fa fa-file-alt f-21 w-100"></i>
                <div class="f-15 mt-4">
                    - @lang('modules.employees.noDocumentExpiryUploaded')
                </div>
            </div>
        </div>
    @endforelse
</div>
