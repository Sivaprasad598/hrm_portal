{{-- The signed declaration, from the employee and from HR. Both are needed
     before the employee can be saved. $onboardingDocs: overview() or null. --}}
<div class="col-12 mt-2">
    <p class="f-14 text-dark-grey f-w-500 mb-1">Signed declaration copies <sup class="text-red f-14">*</sup></p>
    <p class="f-12 text-lightest mb-2">Scan or photograph the signed declaration. PDF, JPG or PNG, up to 5 MB.</p>
</div>
@foreach ([
    'declaration_candidate_copy' => ['Signed by the employee', 'declaration_candidate'],
    'declaration_hr_copy' => ['Signed by HR', 'declaration_hr'],
] as $field => [$label, $category])
    @php $onFile = $onboardingDocs ? ($onboardingDocs[$category][0] ?? null) : null; @endphp
    <div class="col-lg-6 col-md-12">
        <div class="form-group my-2">
            <label class="f-14 text-dark-grey mb-1" for="{{ $field }}">{{ $label }} <sup class="text-red f-14">*</sup></label>
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
                   accept=".pdf,.jpg,.jpeg,.png" data-on-file="{{ $onFile ? 1 : 0 }}">
        </div>
    </div>
@endforeach
