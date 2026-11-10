@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $fmt = company()->date_format;
    $counts = $inboundCounts->groupBy('channel');
    $count = fn ($channel, $status) => (int) ($counts->get($channel, collect())->firstWhere('status', $status)->n ?? 0);
@endphp
<div class="w-100 d-flex">

    @include('sections.setting-sidebar')

    <x-setting-card>
        <x-slot name="header">
            <div class="s-b-n-header d-flex justify-content-between align-items-center border-bottom-grey">
                <h2 class="mb-0 p-20 f-21 font-weight-normal">Recruitment Settings</h2>
                <a href="{{ route('recruitment-settings.inbox') }}" class="btn btn-secondary rounded f-14 mr-3"><i class="fa fa-inbox mr-1"></i>Incoming log</a>
            </div>
        </x-slot>

    <div class="col-lg-12 col-md-12 w-100 p-4">

    {{-- Careers mailbox --}}
    <div class="bg-white rounded border mb-4">
        <div class="p-20 border-bottom-grey d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h5 class="f-16 font-weight-600 mb-1"><i class="fa fa-envelope mr-1"></i>Careers Mailbox</h5>
                <p class="f-13 text-dark-grey mb-0">Job applications emailed to this mailbox become candidates. The mailbox is only read — nothing is marked read, moved or deleted.</p>
            </div>
            <div class="f-13 text-right">
                @if ($setting->mailboxReady())
                    <span class="badge badge-success">On</span>
                @else
                    <span class="badge badge-secondary">Off</span>
                @endif
                <div class="text-dark-grey mt-1">30 days: {{ $count('email', 'imported') }} new · {{ $count('email', 'updated') }} existing · {{ $count('email', 'skipped') }} not applications{{ $count('email', 'failed') ? ' · ' . $count('email', 'failed') . ' failed' : '' }}</div>
            </div>
        </div>

        <x-form id="mailbox-form">
            <input type="hidden" name="section" value="mailbox">
            <div class="row p-20">
                <div class="col-12 mb-2">
                    <x-forms.checkbox fieldId="mailbox_enabled" fieldName="mailbox_enabled" fieldValue="1" :checked="$setting->mailbox_enabled"
                        fieldLabel="Read the careers mailbox (every 15 minutes, and with Fetch now)" />
                </div>
                <div class="col-md-4">
                    <x-forms.text fieldId="imap_username" fieldLabel="Mailbox address" fieldName="imap_username" :fieldValue="$setting->imap_username"
                        fieldPlaceholder="careers@arowanasoft.com" />
                </div>
                <div class="col-md-4 form-group my-3">
                    <x-forms.label fieldId="imap_password" fieldLabel="Password" />
                    <input type="password" class="form-control height-35 f-14" name="imap_password" id="imap_password" autocomplete="new-password"
                           placeholder="{{ $setting->password() ? 'Saved — leave empty to keep it' : 'For Gmail: an app password' }}">
                    @if ($setting->password())
                        <div class="form-check mt-1"><input type="checkbox" class="form-check-input" name="remove_password" id="remove_password" value="1">
                            <label class="form-check-label f-12" for="remove_password">Remove the saved password</label></div>
                    @endif
                </div>
                <div class="col-md-4">
                    <x-forms.text fieldId="imap_folder" fieldLabel="Folder" fieldName="imap_folder" :fieldValue="$setting->imap_folder" />
                </div>
                <div class="col-md-4">
                    <x-forms.text fieldId="imap_host" fieldLabel="Mail server (IMAP)" fieldName="imap_host" :fieldValue="$setting->imap_host" />
                </div>
                <div class="col-md-2 col-6">
                    <x-forms.number fieldId="imap_port" fieldLabel="Port" fieldName="imap_port" :fieldValue="$setting->imap_port" />
                </div>
                <div class="col-md-3 col-6">
                    <x-forms.select fieldId="imap_encryption" fieldLabel="Encryption" fieldName="imap_encryption">
                        @foreach (\App\Models\RecruitSetting::ENCRYPTIONS as $key => $label)
                            <option value="{{ $key }}" @selected($setting->imap_encryption === $key)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </div>
                <div class="col-md-3">
                    <x-forms.number fieldId="mail_lookback_days" fieldLabel="First run reads back (days)" fieldName="mail_lookback_days" :fieldValue="$setting->mail_lookback_days" />
                </div>

                <div class="col-12">
                    <h6 class="f-14 font-weight-600 mt-3 mb-1 border-top-grey pt-3">Which emails are job applications</h6>
                </div>
                <div class="col-md-7">
                    <x-forms.label fieldId="mail_keywords" fieldLabel="Keywords (comma separated)" />
                    <textarea class="form-control f-14" rows="3" name="mail_keywords" id="mail_keywords">{{ $setting->mail_keywords }}</textarea>
                    <small class="text-muted">An email is taken when one of these appears in the subject, the body or an attachment name.</small>
                </div>
                <div class="col-md-5">
                    <x-forms.label fieldId="mail_ignore_senders" fieldLabel="Ignore senders containing" />
                    <textarea class="form-control f-14" rows="3" name="mail_ignore_senders" id="mail_ignore_senders">{{ $setting->mail_ignore_senders }}</textarea>
                    <small class="text-muted">Newsletters, job-board alerts, no-reply senders …</small>
                </div>
                <div class="col-12 mt-2">
                    <x-forms.checkbox fieldId="mail_require_attachment" fieldName="mail_require_attachment" fieldValue="1" :checked="$setting->mail_require_attachment"
                        fieldLabel="Only emails with a resume attached (PDF or Word)" />
                    <x-forms.checkbox fieldId="ai_autofill" fieldName="ai_autofill" fieldValue="1" :checked="$setting->ai_autofill"
                        fieldLabel="Read incoming resumes with AI (fills experience, skills, work history …)" />
                    @unless ($aiReady)
                        <small class="text-warning d-block">AI is not set up, so resumes are kept but not read. Settings → AI Settings switches it on.</small>
                    @endunless
                </div>

                <div class="col-12 mt-3 f-13 bg-light rounded p-3">
                    <strong>Gmail (careers@arowanasoft.com on Google Workspace, or a Gmail address for testing):</strong>
                    server <code>imap.gmail.com</code>, port <code>993</code>, SSL. Turn on 2-Step Verification for the account, then make an
                    <em>app password</em> (Google Account → Security → App passwords) and enter it here — the normal password will not work.
                    IMAP must be on in Gmail → Settings → Forwarding and POP/IMAP.
                </div>

                @if ($setting->mail_last_fetched_at || $setting->mail_last_result)
                    <div class="col-12 mt-3 f-13">
                        <strong>Last run:</strong> {{ $setting->mail_last_result ?: $setting->mail_last_fetched_at?->tz(company()->timezone)->format($fmt . ' h:i A') }}
                    </div>
                @endif
            </div>
            <x-form-actions>
                <x-forms.button-primary id="save-mailbox" class="mr-3" icon="check">Save</x-forms.button-primary>
                <button type="button" class="btn btn-secondary rounded f-14 mr-2" id="test-mailbox"><i class="fa fa-plug mr-1"></i>Test connection</button>
                <button type="button" class="btn btn-secondary rounded f-14" id="fetch-mailbox"><i class="fa fa-download mr-1"></i>Fetch now</button>
            </x-form-actions>
        </x-form>
    </div>

    {{-- Website API --}}
    <div class="bg-white rounded border mb-4" id="website-api">
        <div class="p-20 border-bottom-grey d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h5 class="f-16 font-weight-600 mb-1"><i class="fa fa-globe mr-1"></i>Website Applications (API)</h5>
                <p class="f-13 text-dark-grey mb-0">The "join us" form on arowanasoft.com sends each candidate here with curl. They arrive under source "Website".</p>
            </div>
            <div class="f-13 text-right">
                {!! $setting->api_enabled ? '<span class="badge badge-success">On</span>' : '<span class="badge badge-secondary">Off</span>' !!}
                <div class="text-dark-grey mt-1">30 days: {{ $count('api', 'imported') }} new · {{ $count('api', 'updated') }} existing{{ $count('api', 'failed') ? ' · ' . $count('api', 'failed') . ' failed' : '' }}</div>
            </div>
        </div>

        <div class="p-20">
            @if ($newKey)
                <div class="alert alert-success f-14">
                    <strong>Your new key — copy it now, it is not shown again:</strong>
                    <div class="input-group mt-2">
                        <input type="text" class="form-control f-14" value="{{ $newKey }}" id="new-key" readonly>
                        <div class="input-group-append"><button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('new-key').value); this.innerText='Copied';">Copy</button></div>
                    </div>
                </div>
            @endif

            <div class="row align-items-center">
                <div class="col-md-8 f-14 mb-2">
                    <strong>Key:</strong>
                    @if ($setting->api_key_hash)
                        <code>rk_{{ $setting->api_key_hint }}</code> <span class="text-dark-grey f-12">made {{ $setting->api_key_created_at?->tz(company()->timezone)->format($fmt) }}</span>
                    @else
                        <span class="text-dark-grey">none yet</span>
                    @endif
                </div>
                <div class="col-md-4 text-md-right mb-2">
                    <button type="button" class="btn btn-secondary rounded f-14" id="new-api-key"><i class="fa fa-key mr-1"></i>{{ $setting->api_key_hash ? 'Replace key' : 'Generate key' }}</button>
                </div>
            </div>

            <x-form id="api-form">
                <input type="hidden" name="section" value="api">
                <x-forms.checkbox fieldId="api_enabled" fieldName="api_enabled" fieldValue="1" :checked="$setting->api_enabled" fieldLabel="Accept applications from the website" />
                <x-forms.button-primary id="save-api" class="mt-2" icon="check">Save</x-forms.button-primary>
            </x-form>

            <h6 class="f-14 font-weight-600 mt-4">For the website developer</h6>
            <p class="f-13 mb-1"><code>POST {{ $apiUrl }}</code> — header <code>X-Api-Key: &lt;key&gt;</code>, form data (multipart) or JSON.</p>
            <p class="f-13 mb-2">Fields: <code>name</code>*, <code>email</code>*, <code>mobile</code>, <code>position</code> (title or code of an open position),
                <code>total_experience</code>, <code>qualification</code>, <code>current_location</code>, <code>current_company</code>, <code>current_designation</code>,
                <code>current_ctc</code>, <code>expected_ctc</code>, <code>notice_period_days</code>, <code>skills</code>, <code>message</code>,
                <code>resume</code> (file: PDF/DOC/DOCX, 5 MB) — or <code>resume_base64</code> + <code>resume_name</code> with JSON.
                Answer: <code>201 {"status":"success","reference":"APP-000123"}</code>. The open positions for a dropdown: <code>GET {{ url('/api/recruit/v1/positions') }}</code>.</p>
<pre class="bg-light border rounded p-3 f-12 mb-2" style="white-space: pre-wrap">curl -X POST "{{ $apiUrl }}" \
  -H "X-Api-Key: YOUR_KEY" \
  -F "name=Ravi Kumar" -F "email=ravi@example.com" -F "mobile=9876543210" \
  -F "position=Site Engineer" -F "total_experience=3" \
  -F "message=I would like to join Arowana" \
  -F "resume=@/path/to/resume.pdf"</pre>
<pre class="bg-light border rounded p-3 f-12 mb-0" style="white-space: pre-wrap">// PHP, in the website's form handler
$ch = curl_init('{{ $apiUrl }}');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-Api-Key: YOUR_KEY', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => [
        'name' => $_POST['name'], 'email' => $_POST['email'], 'mobile' => $_POST['mobile'],
        'position' => $_POST['position'] ?? '', 'message' => $_POST['message'] ?? '',
        'resume' => new CURLFile($_FILES['resume']['tmp_name'], $_FILES['resume']['type'], $_FILES['resume']['name']),
    ],
]);
$answer = json_decode(curl_exec($ch), true);   // ['status' => 'success', 'reference' => 'APP-000123']</pre>
        </div>
    </div>

    {{-- Hiring rounds --}}
    <div class="bg-white rounded border mb-4" id="hiring-rounds">
        <div class="p-20 border-bottom-grey">
            <h5 class="f-16 font-weight-600 mb-1"><i class="fa fa-route mr-1"></i>Hiring Rounds</h5>
            <p class="f-13 text-dark-grey mb-0">The default plan: every position uses it unless it has its own (set on the position).
                Once HR shortlists a candidate, the system suggests each round's slot for HR to confirm, scores the round, and moves the candidate on —
                or rejects them under the cut-off — and drafts the offer after the last round.</p>
        </div>
        <x-form id="rounds-form">
            <input type="hidden" name="section" value="rounds">
            <div class="p-20">
                @include('recruitment.partials.round-plan', ['plan' => $plan, 'employees' => $employees, 'planId' => 'default-plan'])

                <h6 class="f-14 font-weight-600 mt-4 mb-2">Timings</h6>
                <div class="row">
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="slot_lead_hours" fieldLabel="Earliest slot (hours from now)" fieldName="slot_lead_hours" :fieldValue="$setting->slot_lead_hours ?? 24" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="slot_horizon_days" fieldLabel="Look for a slot within (days)" fieldName="slot_horizon_days" :fieldValue="$setting->slot_horizon_days ?? 14" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="reminder_hours" fieldLabel="Remind the panel every (hours)" fieldName="reminder_hours" :fieldValue="$setting->reminder_hours ?? 24" />
                    </div>
                    <div class="col-md-3 col-6">
                        <x-forms.number fieldId="escalate_hours" fieldLabel="Tell HR when overdue by (hours)" fieldName="escalate_hours" :fieldValue="$setting->escalate_hours ?? 48" />
                    </div>
                </div>
                <p class="f-12 text-dark-grey mb-0">Slots are suggested inside office hours on office days, skipping holidays, the panel's leave, meetings and other interviews.</p>
            </div>
            <x-form-actions>
                <x-forms.button-primary id="save-rounds" icon="check">Save</x-forms.button-primary>
            </x-form-actions>
        </x-form>
    </div>

    {{-- Lists --}}
    <div class="bg-white rounded border mb-4">
        <div class="p-20 border-bottom-grey">
            <h5 class="f-16 font-weight-600 mb-1"><i class="fa fa-list mr-1"></i>Lists</h5>
            <p class="f-13 text-dark-grey mb-0">One per line.</p>
        </div>
        <x-form id="lists-form">
            <input type="hidden" name="section" value="lists">
            <div class="row p-20">
                <div class="col-md-4">
                    <x-forms.label fieldId="rejection_reasons" fieldLabel="Rejection reasons" />
                    <textarea class="form-control f-14" rows="8" name="rejection_reasons" id="rejection_reasons">{{ implode("\n", $setting->rejectionReasons()) }}</textarea>
                </div>
                <div class="col-md-4">
                    <x-forms.label fieldId="interview_rounds" fieldLabel="Round names for extra interviews" />
                    <textarea class="form-control f-14" rows="8" name="interview_rounds" id="interview_rounds">{{ implode("\n", $setting->rounds()) }}</textarea>
                </div>
                <div class="col-md-4">
                    <x-forms.label fieldId="scorecard_questions" fieldLabel="Scorecard questions (rated 1–5)" />
                    <textarea class="form-control f-14" rows="8" name="scorecard_questions" id="scorecard_questions">{{ implode("\n", $setting->questions()) }}</textarea>
                </div>
            </div>
            <x-form-actions>
                <x-forms.button-primary id="save-lists" icon="check">Save</x-forms.button-primary>
            </x-form-actions>
        </x-form>
    </div>
    </div>
    </x-setting-card>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        function save(form, button) {
            $.easyAjax({
                url: '{{ route('recruitment-settings.update') }}', container: '#' + form, type: 'POST', blockUI: true,
                disableButton: true, buttonSelector: '#' + button, data: $('#' + form).serialize(),
                success: function (response) { if (response.status === 'success') { window.location.reload(); } }
            });
        }

        $('#save-mailbox').on('click', function () { save('mailbox-form', 'save-mailbox'); });
        $('#save-api').on('click', function () { save('api-form', 'save-api'); });
        $('#save-lists').on('click', function () { save('lists-form', 'save-lists'); });
        $('#save-rounds').on('click', function () { save('rounds-form', 'save-rounds'); });

        $('#test-mailbox').on('click', function () {
            $.easyAjax({ url: '{{ route('recruitment-settings.test-mailbox') }}', type: 'POST', blockUI: true, disableButton: true,
                buttonSelector: '#test-mailbox', data: {_token: '{{ csrf_token() }}'} });
        });

        $('#fetch-mailbox').on('click', function () {
            $.easyAjax({ url: '{{ route('recruitment-settings.fetch') }}', type: 'POST', blockUI: true, disableButton: true,
                buttonSelector: '#fetch-mailbox', data: {_token: '{{ csrf_token() }}'},
                success: function (response) { if (response.status === 'success') { setTimeout(function () { window.location.reload(); }, 1500); } } });
        });

        $('#new-api-key').on('click', function () {
            Swal.fire({
                title: 'Make a new key?',
                text: @json($setting->api_key_hash ? 'The current key stops working at once; the website must be given the new one.' : 'The key is shown once, to copy for the website.'),
                icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, make it', cancelButtonText: "@lang('app.cancel')",
                customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.easyAjax({ url: '{{ route('recruitment-settings.api-key') }}', type: 'POST', blockUI: true, data: {_token: '{{ csrf_token() }}'},
                        success: function (response) { if (response.status === 'success') { window.location.hash = 'website-api'; window.location.reload(); } } });
                }
            });
        });
    });
</script>
@endpush
