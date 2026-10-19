{{-- Resume: kept with the employee; with AI on, read into this form.
     $onboardingDocs: OnboardingDocuments::overview() on Edit, null on Add. --}}
@php
    $aiSetting = \App\Models\AiSetting::forCompany(company()->id);
    $aiReady = $aiSetting->ready();
    $currentResume = $onboardingDocs['resume'][0] ?? null;
@endphp
<div class="onboard-box p-3 mb-4" id="resume-box">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="mr-3 mb-2">
            <h6 class="mb-1 f-14 f-w-500"><i class="fa fa-file-pdf text-danger mr-1"></i> Resume</h6>
            <p class="f-12 text-lightest mb-0">
                PDF, up to 5 MB. Saved with the employee.
                @if ($aiReady)
                    <strong>Extract with AI</strong> fills this form from it using
                    {{ \App\Models\AiSetting::PROVIDERS[$aiSetting->provider] }} — check the highlighted fields before saving.
                @endif
            </p>
            @if ($currentResume)
                <p class="f-12 mb-0 mt-1">On file:
                    <a href="{{ $currentResume->doc_url }}" class="doc-chip ob-doc-link" target="_blank" rel="noopener"
                       data-mime="{{ $currentResume->mime }}" data-name="{{ $currentResume->filename }}"
                       data-download="{{ route('employee-docs.download', md5($currentResume->id)) }}">
                        <i class="fa fa-paperclip"></i> {{ $currentResume->filename }}
                    </a>
                    <span class="text-lightest">— choosing a new file replaces it</span>
                </p>
            @endif
        </div>
        <div class="d-flex align-items-center flex-wrap mb-2">
            <input type="file" name="resume_file" id="resume_file" accept="application/pdf,.pdf"
                   class="form-control-file f-13 mr-2 mb-1" style="max-width: 260px">
            <button type="button" id="resume-extract" class="btn btn-primary btn-sm mb-1" @disabled(!$aiReady)
                    title="{{ $aiReady ? 'Read the resume into this form' : 'Enable AI on Settings → AI Settings first' }}">
                <i class="fa fa-magic mr-1"></i> Extract with AI
            </button>
        </div>
    </div>
    @unless ($aiReady)
        <p class="f-12 text-dark-grey mb-0">
            <i class="fa fa-info-circle text-warning mr-1"></i>
            Resume auto-fill needs AI. Enable it and add an OpenAI, Claude or Gemini key on
            @if (user()->permission('manage_company_setting') == 'all')
                <a href="{{ route('ai-settings.index') }}" target="_blank">Settings → AI Settings</a>.
            @else
                Settings → AI Settings (ask an admin).
            @endif
        </p>
    @endunless
    <div id="resume-extract-result" class="f-12 mt-1"></div>
</div>
