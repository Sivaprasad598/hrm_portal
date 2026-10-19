{{-- Joining checklist: a switch and the document behind it for each item.
     A ticked item needs a document; uploading one ticks it.
     $joiningChecklist: the saved checklist (Edit) or null; $onboardingDocs: overview() or null. --}}
<p class="f-13 text-dark-grey mb-2">
    Tick an item when it is received and upload its document — a ticked item cannot be saved without one.
    PDF, JPG or PNG, up to 5 MB each; several files per item are fine.
</p>
@if ($onboardingDocs && collect($onboardingDocs['previous'] ?? [])->isNotEmpty())
    {{-- Joining documents of earlier employments (before a rejoin): kept, read-only --}}
    <div class="border rounded p-2 px-3 mb-3 bg-light">
        <p class="f-13 f-w-500 mb-1"><i class="fa fa-history mr-1"></i> Documents from earlier employment — kept on record; this employment needs its own set</p>
        @foreach ($onboardingDocs['previous'] as $earlier)
            <div class="f-12 text-dark-grey mb-1">
                {{ $earlier['period'] && $earlier['period']->joined_on ? \Carbon\Carbon::parse($earlier['period']->joined_on)->translatedFormat(company()->date_format) : '' }}
                – {{ $earlier['period'] && $earlier['period']->left_on ? \Carbon\Carbon::parse($earlier['period']->left_on)->translatedFormat(company()->date_format) : '' }}:
                @foreach ($earlier['files'] as $doc)
                    <a href="{{ $doc->doc_url }}" target="_blank" rel="noopener" class="doc-chip ob-doc-link" data-mime="{{ $doc->mime }}"
                       data-name="{{ $doc->filename }}">{{ $doc->name }}</a>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
<div class="row">
    @foreach (\App\Services\Employee\OnboardingDocuments::CHECKLIST as $key => $label)
        @php
            $existing = $onboardingDocs ? ($onboardingDocs['checklist'][$key]['files'] ?? collect()) : collect();
            $ticked = ($joiningChecklist ?? null) && $joiningChecklist->{$key};
        @endphp
        <div class="col-lg-6 col-md-12">
            <div class="checklist-item {{ $ticked ? 'is-ticked' : '' }}" data-key="{{ $key }}">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="mb-0 f-14 text-dark-grey" for="{{ $key }}">{{ $label }}</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input checklist-toggle" id="{{ $key }}"
                               name="{{ $key }}" value="1" @checked($ticked)>
                        <label class="custom-control-label" for="{{ $key }}"></label>
                    </div>
                </div>
                <div class="mt-2">
                    @foreach ($existing as $doc)
                        <span class="doc-chip existing-doc" data-doc-id="{{ $doc->id }}" data-key="{{ $key }}">
                            <a href="{{ $doc->doc_url }}" target="_blank" rel="noopener" class="text-reset ob-doc-link"
                               data-mime="{{ $doc->mime }}" data-name="{{ $doc->filename }}"
                               data-download="{{ route('employee-docs.download', md5($doc->id)) }}">
                                <i class="fa fa-paperclip"></i> {{ $doc->filename }}
                            </a>
                            <a href="javascript:;" class="text-danger remove-existing-doc ml-1" title="Remove">&times;</a>
                        </span>
                    @endforeach
                    <input type="file" class="checklist-file f-12 d-block mt-1" id="checklist_file_{{ $key }}"
                           name="checklist_files[{{ $key }}][]" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <div class="checklist-file-list"></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
