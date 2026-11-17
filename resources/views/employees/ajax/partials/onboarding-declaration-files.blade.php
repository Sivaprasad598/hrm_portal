{{-- The signed declaration, from the employee and from HR.
     $required: true on Add (both copies are needed before the employee can be
     saved), false on Edit (optional; a copy on file is kept). $onboardingDocs:
     overview() or null. The server checks the same (OnboardingDocuments::missing). --}}
@php $required = $required ?? true; @endphp
<div class="col-12 mt-2">
    <div class="fd-block mb-0">
        <h6>Signed declaration copies
            @if ($required)
                <sup class="text-red f-14">*</sup>
            @else
                <span class="fd-optional">(optional)</span>
            @endif
        </h6>
        <p class="fd-hint">Scan or photograph the signed declaration. PDF, JPG or PNG, up to 5 MB.
            @unless ($required) A copy already on file is kept unless you choose a new one.@endunless</p>
        <div class="row">
            @foreach ([
                'declaration_candidate_copy' => ['Signed by the employee', 'declaration_candidate'],
                'declaration_hr_copy' => ['Signed by HR', 'declaration_hr'],
            ] as $field => [$label, $category])
                @php $onFile = $onboardingDocs ? ($onboardingDocs[$category][0] ?? null) : null; @endphp
                <div class="col-lg-6 col-md-12">
                    <div class="form-group my-2">
                        <label class="f-14 text-dark-grey mb-1" for="{{ $field }}">
                            {{ $label }}
                            @if ($required) <sup class="text-red f-14">*</sup> @endif
                        </label>
                        @if ($onFile)
                            <div class="mb-1">
                                <a href="{{ $onFile->doc_url }}" target="_blank" rel="noopener" class="doc-chip ob-doc-link"
                                   id="{{ $field }}_on_file" data-on-file="1" data-mime="{{ $onFile->mime }}"
                                   data-name="{{ $onFile->filename }}"
                                   data-download="{{ route('employee-docs.download', md5($onFile->id)) }}">
                                    <i class="fa fa-paperclip"></i> {{ $onFile->filename }}
                                </a>
                                <span class="f-12 text-lightest">on file — choosing a new file replaces it</span>
                            </div>
                        @endif
                        <input type="file" class="form-control height-35 f-14 declaration-file" id="{{ $field }}" name="{{ $field }}"
                               accept=".pdf,.jpg,.jpeg,.png" data-on-file="{{ $onFile ? 1 : 0 }}" data-required="{{ $required ? 1 : 0 }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
