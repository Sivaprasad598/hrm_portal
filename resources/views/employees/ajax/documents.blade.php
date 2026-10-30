@php
$addDocumentPermission = user()->permission('add_documents');
$viewDocumentPermission = user()->permission('view_documents');
$deleteDocumentPermission = user()->permission('delete_documents');
$editDocumentPermission = user()->permission('edit_documents');
@endphp

<style>
    .file-action {
        visibility: hidden;
    }

    .doc-type-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
    .doc-type-tabs a { padding: 6px 14px; border: 1px solid #e4e7ec; border-radius: 18px; font-size: 13px; color: #475467; background: #fff; }
    .doc-type-tabs a span { margin-left: 4px; color: #98a2b3; font-size: 12px; }
    .doc-type-tabs a:hover { text-decoration: none; border-color: #1d82f5; color: #1d82f5; }
    .doc-type-tabs a.active { background: #1d82f5; border-color: #1d82f5; color: #fff; }
    .doc-type-tabs a.active span { color: #e0edff; }

    .file-card:hover .file-action {
        visibility: visible;
    }

</style>

<!-- TAB CONTENT START -->
<div class="tab-pane fade show active mt-5" role="tabpanel" aria-labelledby="nav-email-tab">
    <x-cards.data :title="__('app.menu.documents')">

        @if ($addDocumentPermission == 'all' || $addDocumentPermission == 'added')

            <div class="row">
                <div class="col-md-12">
                    <a class="f-15 f-w-500" href="javascript:;" id="add-task-file"><i
                            class="icons icon-plus font-weight-bold mr-1"></i>@lang('modules.lead.addFile')
                        </a>
                </div>
            </div>

            <x-form id="save-taskfile-data-form" class="d-none">
                <input type="hidden" name="user_id" id="doc_user_id" value="{{ $employee->id }}">
                <div class="row">
                    <div class="col-md-6">
                        <x-forms.select fieldId="doc_type" fieldName="doc_type" fieldLabel="Document Type" fieldRequired="true">
                            @foreach (\App\Services\Employee\DocumentTypes::TYPES as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}" @selected($typeKey === 'other')>{{ $typeLabel }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                    <div class="col-md-6">
                        <x-forms.text :fieldLabel="__('modules.projects.fileName')" fieldName="name"
                            fieldRequired="true" fieldId="file_name" />
                    </div>
                    <div class="col-md-12">
                        <x-forms.file :fieldLabel="__('modules.projects.uploadFile')" fieldName="file"
                            fieldRequired="true" fieldId="employee_file"
                            allowedFileExtensions="txt pdf doc xls xlsx docx rtf png jpg jpeg svg"
                            :popover="__('messages.fileFormat.multipleImageFile')" />
                    </div>
                    <div class="col-md-12">
                        <div class="w-100 justify-content-end d-flex mt-2">
                            <x-forms.button-cancel id="cancel-document" class="border-0 mr-3">@lang('app.cancel')
                            </x-forms.button-cancel>
                            <x-forms.button-primary id="submit-document" icon="check">@lang('app.submit')
                            </x-forms.button-primary>
                        </div>
                    </div>
                </div>
            </x-form>
        @endif

        @php
            // The files this person may see, with a tab for each type that has any
            // (App\Services\Employee\DocumentTypes): Aadhaar, PAN, SSC and so on.
            $docTypes = \App\Services\Employee\DocumentTypes::class;
            $visibleDocs = $employee->documents->filter(fn ($file) => $viewDocumentPermission == 'all'
                || ($viewDocumentPermission == 'added' && $file->added_by == user()->id)
                || ($viewDocumentPermission == 'owned' && ($file->user_id == user()->id && $file->added_by != user()->id))
                || ($viewDocumentPermission == 'both' && ($file->added_by == user()->id || $file->user_id == user()->id)));
            $typeOrder = array_keys($docTypes::TYPES);
            $byType = $visibleDocs->groupBy(fn ($file) => $docTypes::of($file))
                ->sortBy(fn ($files, $type) => array_search($type, $typeOrder));
        @endphp

        @if ($visibleDocs->isNotEmpty())
            <div class="doc-type-tabs mt-4">
                <a href="javascript:;" class="active" data-doc-filter="all">All <span>{{ $visibleDocs->count() }}</span></a>
                @foreach ($byType as $type => $files)
                    <a href="javascript:;" data-doc-filter="{{ $type }}">{{ $docTypes::label($type) }} <span>{{ $files->count() }}</span></a>
                @endforeach
            </div>
        @endif

        <div class="d-flex flex-wrap mt-3" id="task-file-list">
            @forelse ($visibleDocs as $file)
                    <x-file-card :fileName="$file->name" data-doc-type="{{ $docTypes::of($file) }}"
                        :dateAdded="$docTypes::label($docTypes::of($file)) . ' · ' . $file->created_at->translatedFormat(company()->date_format)">
                        {{-- Click: images and PDFs open in the preview popup --}}
                        @if ($file->icon == 'images')
                            <a class="doc-preview" data-preview-url="{{ $file->doc_url }}" data-preview-kind="image" data-preview-name="{{ $file->name }}" href="javascript:;"><img src="{{ $file->doc_url }}"></a>
                        @else
                            <a data-doc-view data-preview-name="{{ $file->name }}" href="{{ $file->doc_url }}" target="_blank"><i class="fa {{ $file->icon }} text-lightest"></i></a>
                        @endif
                        <x-slot name="action">
                            <div class="dropdown ml-auto file-action">
                                <button
                                    class="btn btn-lg f-14 p-0 text-lightest  rounded  dropdown-toggle"
                                    type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-ellipsis-h"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0"
                                    aria-labelledby="dropdownMenuLink" tabindex="0">
                                    @if ($viewDocumentPermission == 'all'
                                    || ($viewDocumentPermission == 'added' && $file->added_by == user()->id)
                                    || ($viewDocumentPermission == 'owned' && ($file->user_id == user()->id && $file->added_by != user()->id))
                                    || ($viewDocumentPermission == 'both' && ($file->added_by == user()->id || $file->user_id == user()->id)))

                                        <a class="cursor-pointer d-block text-dark-grey f-13 pt-3 px-3 "
                                            target="_blank" data-doc-view href="{{ $file->doc_url }}">@lang('app.view')</a>

                                        <a class="cursor-pointer d-block text-dark-grey f-13 py-3 px-3 "
                                            href="{{ route('employee-docs.download', md5($file->id)) }}">@lang('app.download')</a>
                                    @endif

                                    @if ($editDocumentPermission == 'all'
                                    || ($editDocumentPermission == 'added' && $file->added_by == user()->id)
                                    || ($editDocumentPermission == 'owned' && ($file->user_id == user()->id && $file->added_by != user()->id))
                                    || ($editDocumentPermission == 'both' && ($file->added_by == user()->id || $file->user_id == user()->id)))
                                        <a class="cursor-pointer d-block text-dark-grey pb-3 f-13 px-3 edit-file"
                                            href="javascript:;" data-file-id="{{ $file->id }}">@lang('app.edit')</a>
                                    @endif

                                    @if ($deleteDocumentPermission == 'all'
                                    || ($deleteDocumentPermission == 'added' && $file->added_by == user()->id)
                                    || ($deleteDocumentPermission == 'owned' && ($file->user_id == user()->id && $file->added_by != user()->id))
                                    || ($deleteDocumentPermission == 'both' && ($file->added_by == user()->id || $file->user_id == user()->id)))
                                        <a class="cursor-pointer d-block text-dark-grey f-13 pb-3 px-3 delete-file"
                                            data-row-id="{{ $file->id }}"
                                            href="javascript:;">@lang('app.delete')</a>
                                    @endif
                                </div>
                            </div>
                        </x-slot>
                    </x-file-card>
            @empty
                <div class="align-items-center d-flex flex-column text-lightest p-20 w-100">
                    <i class="fa fa-file f-21 w-100"></i>

                    <div class="f-15 mt-4">
                        - @lang('messages.noFileUploaded') -
                    </div>
                </div>
            @endforelse
        </div>
    </x-cards.data>

    <!-- Employee Document Expiry Section -->
    <x-cards.data :title="__('modules.employees.employeeDocumentExpiry')" class="mt-4">
        @if ($addDocumentPermission == 'all' || $addDocumentPermission == 'added')
            <div class="row">
                <div class="col-md-12">
                    <a class="f-15 f-w-500" href="javascript:;" id="add-document-expiry">
                        <i class="icons icon-plus font-weight-bold mr-1"></i>@lang('modules.employees.addDocumentExpiry')
                    </a>
                </div>
            </div>

            <div id="document-expiry-form-container" class="d-none">
                <!-- Form will be loaded here via AJAX -->
            </div>
        @endif

        <div id="document-expiry-list-container">
            @php
                $documentExpiries = collect();
                // Try to get the employee from the controller's data
                if (isset($employee) && $employee->relationLoaded('documentExpiries')) {
                    $documentExpiries = $employee->documentExpiries;
                } elseif (isset($user) && $user->relationLoaded('documentExpiries')) {
                    $documentExpiries = $user->documentExpiries;
                } else {
                    // Fallback: load the data directly using the user ID from the URL
                    $userId = request()->route('id') ?? $user->id ?? user()->id;
                    $documentExpiries = \App\Models\EmployeeDocumentExpiry::where('user_id', $userId)->get();
                }
            @endphp
            @include('employees.ajax.document-expiry.show', ['documents' => $documentExpiries])
        </div>
    </x-cards.data>
</div>
<!-- TAB CONTENT END -->

<script>
    $('#add-task-file').click(function() {
        $(this).closest('.row').addClass('d-none');
        $('#save-taskfile-data-form').removeClass('d-none');
    });

    $('body').on('click', '.edit-file', function() {
        var fileId = $(this).data('file-id');
        var url = "{{ route('employee-docs.edit', ':id') }}";
        url = url.replace(':id', fileId);

        $(MODAL_DEFAULT + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_DEFAULT, url);
    });

    // Type tabs: show one kind of document, or all.
    $('body').off('click', '[data-doc-filter]').on('click', '[data-doc-filter]', function () {
        var type = $(this).data('doc-filter');
        $('[data-doc-filter]').removeClass('active');
        $(this).addClass('active');
        $('#task-file-list [data-doc-type]').each(function () {
            $(this).toggleClass('d-none', type !== 'all' && $(this).data('doc-type') !== type);
        });
    });

    $('#cancel-document').click(function() {
        $('#save-taskfile-data-form').addClass('d-none');
        $('#add-task-file').closest('.row').removeClass('d-none');
    });

    $('#submit-document').click(function() {
        var url = "{{ route('employee-docs.store') }}";

        $.easyAjax({
            url: url,
            container: '#save-taskfile-data-form',
            type: "POST",
            disableButton: true,
            buttonSelector: "#submit-document",
            file: true,
            data: $('#editSettings').serialize(),
            success: function(response) {
                if (response.status == 'success') {
                    // A new file can bring a new type tab: draw the tab again.
                    window.location.reload();
                }
            }
        })
    });

    // Add Document Expiry button handler - use event delegation to prevent duplicate bindings
    $('body').off('click', '#add-document-expiry').on('click', '#add-document-expiry', function() {
        var url = "{{ route('employee-document-expiries.create') }}?user_id=:id";
        var userId = $('#doc_user_id').val();
        console.log(userId);
        url = url.replace(':id', userId);
        console.log(url);
        
        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

    // Document Expiry form submission - prevent duplicate submissions
    $('body').off('click', '#submit-document-expiry').on('click', '#submit-document-expiry', function(e) {
        e.preventDefault();
        console.log('submit-document-expiry');
        // Prevent double submission
        if ($(this).prop('disabled') || $(this).data('submitting')) {
            return false;
        }
        
        // Set submitting flag
        $(this).data('submitting', true);
        
        var url = "{{ route('employee-document-expiries.store') }}";
        var userId = $('#doc_user_id').val();
        
        $.easyAjax({
            url: url,
            container: '#save-document-expiry-form',
            type: "POST",
            disableButton: true,
            buttonSelector: "#submit-document-expiry",
            file: true,
            data: $('#save-document-expiry-form').serialize(),
            success: function(response) {
                if (response.status == 'success') {
                    $('#document-expiry-list-container').html(response.view);
                    $(MODAL_XL).modal('hide');
                }
                // Reset submitting flag
                $('#submit-document-expiry').data('submitting', false);
            },
            error: function(xhr) {
                // Reset submitting flag on error
                $('#submit-document-expiry').data('submitting', false);
                
                // Handle validation errors
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    // Clear previous errors
                    $(MODAL_XL).find('.invalid-feedback').remove();
                    $(MODAL_XL).find('.is-invalid').removeClass('is-invalid');
                    
                    // Display validation errors
                    $.showErrors(xhr.responseJSON.errors);
                }
            }
        })
    });

    // Cancel Document Expiry form
    $('body').on('click', '#cancel-document-expiry', function() {
        $(MODAL_XL).modal('hide');
    });

    // Cancel Document Expiry Edit form
    $('body').on('click', '#cancel-document-expiry-edit', function() {
        $(MODAL_XL).modal('hide');
    });

    var docId= 0;

    // Update Document Expiry form submission - prevent duplicate submissions
    $('body').off('click', '#update-document-expiry').on('click', '#update-document-expiry', function(e) {
        e.preventDefault();
        
        // Prevent double submission
        if ($(this).prop('disabled') || $(this).data('submitting')) {
            return false;
        }
        
        // Set submitting flag
        $(this).data('submitting', true);
        
        var documentId = docId;
        var url = "{{ route('employee-document-expiries.update', ':id') }}";
        url = url.replace(':id', documentId);

        $.easyAjax({
            url: url,
            container: '#update-document-expiry-form',
            type: "POST",
            disableButton: true,
            buttonSelector: "#update-document-expiry",
            file: true,
            data: $('#update-document-expiry-form').serialize(),
            success: function(response) {
                if (response.status == 'success') {
                    $(MODAL_XL).modal('hide');
                    $('#document-expiry-list-container').html(response.view);
                    
                }
                // Reset submitting flag
                $('#update-document-expiry').data('submitting', false);
                // window.location.reload();
            },
            error: function(xhr) {
                // Reset submitting flag on error
                $('#update-document-expiry').data('submitting', false);
                
                // Handle validation errors
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    // Clear previous errors
                    $(MODAL_XL).find('.invalid-feedback').remove();
                    $(MODAL_XL).find('.is-invalid').removeClass('is-invalid');
                    
                    // Display validation errors
                    $.showErrors(xhr.responseJSON.errors);
                }
            }
        })
    });

    // Edit Document Expiry
    $('body').on('click', '.edit-document-expiry', function() {
        var documentId = $(this).data('document-id');
        docId = documentId;
        var url = "{{ route('employee-document-expiries.edit', ':id') }}";
        url = url.replace(':id', documentId);

        $(MODAL_XL + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_XL, url);
    });

    // Delete Document Expiry
    $('body').on('click', '.delete-document-expiry', function() {
        var documentId = $(this).data('document-id');
        
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
                var url = "{{ route('employee-document-expiries.destroy', ':id') }}";
                url = url.replace(':id', documentId);

                $.easyAjax({
                    url: url,
                    type: "DELETE",
                    container: "#document-expiry-list-container",
                    success: function(response) {
                        if (response.status == 'success') {
                            $('#document-expiry-list-container').html(response.view);
                        }
                    }
                });
            }
        });
    });

    $('body').on('click', '.delete-file', function() {
        var id = $(this).data('row-id');
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
                var url = "{{ route('employee-docs.destroy', ':id') }}";
                url = url.replace(':id', id);

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
                            $('#task-file-list').html(response.view);
                        }
                    }
                });
            }
        });
    });


    $('body').on('click', '.delete-document-expiry', function() {
        var id = $(this).data('document-id');
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
                var url = "{{ route('employee-document-expiries.destroy', ':id') }}";
                url = url.replace(':id', id);

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
                            $('#document-expiry-list-container').html(response.view);
                        }
                    }
                });
            }
        });
    });
</script>
