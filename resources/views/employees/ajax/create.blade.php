@php
$addDesignationPermission = user()->permission('add_designation');
$addDepartmentPermission = user()->permission('add_department');
@endphp

<link rel="stylesheet" href="{{ asset('vendor/css/tagify.css') }}">

<style>
/* ── Employee Wizard — scoped inline styles (wins over cache) ── */
.employee-wizard-header {
    padding: 24px 28px 18px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06) !important;
}

/* Horizontal step track */
.wizard-steps-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: flex-start !important;
    justify-content: space-between !important;
    position: relative !important;
    padding: 0 !important;
}

/* Connecting line behind circles */
.wizard-steps-row::before {
    content: '' !important;
    display: block !important;
    position: absolute !important;
    top: 21px !important;
    left: 22px !important;
    right: 22px !important;
    height: 2px !important;
    background: #e2e8f0 !important;
    z-index: 0 !important;
}

/* Each step item */
.wizard-step-item {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 8px !important;
    flex: 1 1 0 !important;
    position: relative !important;
    z-index: 1 !important;
    cursor: default !important;
    min-width: 0 !important;
}

.wizard-step-item.completed { cursor: pointer !important; }

/* Circle */
.step-circle {
    width: 44px !important;
    height: 44px !important;
    border-radius: 50% !important;
    border: 2px solid #e2e8f0 !important;
    background: #ffffff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 15px !important;
    color: #94a3b8 !important;
    transition: all .25s ease !important;
    position: relative !important;
    z-index: 1 !important;
    flex-shrink: 0 !important;
}

.wizard-step-item.active .step-circle {
    background: #4f46e5 !important;
    border-color: #4f46e5 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(79,70,229,.35) !important;
}

.wizard-step-item.completed .step-circle {
    background: #ecfdf5 !important;
    border-color: #10b981 !important;
    color: #10b981 !important;
}

/* Label */
.step-label {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #94a3b8 !important;
    text-align: center !important;
    letter-spacing: .02em !important;
    white-space: nowrap !important;
    display: block !important;
    line-height: 1.3 !important;
}

.wizard-step-item.active .step-label    { color: #4f46e5 !important; }
.wizard-step-item.completed .step-label { color: #10b981 !important; }

/* Progress bar */
.wizard-progress-bar-wrap {
    margin-top: 20px !important;
    background: #e2e8f0 !important;
    border-radius: 4px !important;
    height: 4px !important;
    overflow: hidden !important;
    display: block !important;
}

.wizard-progress-fill {
    height: 100% !important;
    background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 100%) !important;
    border-radius: 4px !important;
    transition: width .35s ease !important;
    display: block !important;
}

/* Section sub-header */
.wizard-section-header {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 14px !important;
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    padding: 14px 18px !important;
}

.wizard-section-icon {
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    border-radius: 10px !important;
    background: linear-gradient(135deg, #ede9fe 0%, #e0e7ff 100%) !important;
    color: #4f46e5 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
}

.wizard-section-header h5 {
    font-size: 14px !important;
    font-weight: 700 !important;
    margin-bottom: 2px !important;
    color: #1e293b !important;
}

.wizard-section-header p {
    font-size: 12px !important;
    color: #64748b !important;
    margin-bottom: 0 !important;
}

/* Nav bar */
.wizard-nav-bar {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding-top: 20px !important;
    border-top: 1px solid #e2e8f0 !important;
    margin-top: 28px !important;
}

/* Onboarding additions: steps that still need something, fields the AI filled */
.wizard-step-item { cursor: pointer !important; }
.wizard-step-item.has-error .step-circle { border-color: #ef4444 !important; background: #fee2e2 !important; color: #dc2626 !important; }
.wizard-step-item.has-error .step-label { color: #dc2626 !important; }
.ai-filled, .ai-filled + .bootstrap-select .btn { background: #fffbeb !important; }
.onboard-box { border: 1px dashed #c7d2fe; background: #f8faff; border-radius: 8px; }
.checklist-item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin: 8px 0; }
.checklist-item.is-ticked { border-color: #a7f3d0; background: #f0fdf4; }
.checklist-item .doc-chip, .doc-chip { display: inline-flex; align-items: center; gap: 4px; background: #eef2ff; color: #3730a3;
    border-radius: 12px; padding: 2px 8px; margin: 2px 4px 2px 0; font-size: 12px; }
.doc-chip.is-removed { text-decoration: line-through; opacity: .5; }
.salary-breakdown table td { padding: 3px 8px; }
#ifsc-result.ok { color: #047857; }
#ifsc-result.bad { color: #dc2626; }

/* One rhythm for every field: each label 16px below the row above and 8px
   above its box, whichever component drew it (text, select, datepicker, or a
   bare label with my-3 / mt-3) - they used to differ by 4px, so boxes in one
   row did not line up. */
.wizard-pane .form-group.my-3,
.wizard-pane .form-group.mb-0 { margin-top: 0 !important; margin-bottom: 0 !important; }
.wizard-pane .row > [class*="col-"] > label.f-14,
.wizard-pane .row > [class*="col-"] .form-group > label.f-14 {
    display: block; margin-top: 16px !important; margin-bottom: 8px !important;
    line-height: 20px; min-height: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.wizard-pane label.f-14 sup { top: -.1em; font-size: 13px !important; margin-left: 2px; color: #dc2626; }
.wizard-pane .form-text, .wizard-pane .invalid-feedback { margin-top: 6px; }
.wizard-pane .input-group > .form-control.height-35 { height: 39px !important; }
/* A dropdown inside an input group (the +91 code beside Mobile) draws its own
   empty label and form-group; inside the group they only push the box out of line */
.wizard-pane .input-group > label { display: none !important; }
.wizard-pane .input-group > .form-group { margin: 0 !important; }
/* Text areas reach the same right edge as the fields above them */
.wizard-pane .form-group.mr-lg-2, .wizard-pane .form-group.mr-md-2 { margin-right: 0 !important; }

/* Review rows */
.review-section-title {
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .08em !important;
    color: #64748b !important;
    padding: 10px 16px !important;
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 1px solid #e2e8f0 !important;
    margin: 0 !important;
}
.review-section-title:first-child { border-top: none !important; }

.review-row {
    display: flex !important;
    padding: 9px 16px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    align-items: baseline !important;
}
.review-row:last-child { border-bottom: none !important; }

.review-label {
    min-width: 200px !important;
    width: 200px !important;
    flex-shrink: 0 !important;
    font-size: 12px !important;
    color: #64748b !important;
    font-weight: 600 !important;
}

.review-value {
    font-size: 13.5px !important;
    color: #1e293b !important;
    font-weight: 500 !important;
    word-break: break-word !important;
}

.review-check-yes { color: #10b981 !important; font-weight: 600 !important; }
.review-check-no  { color: #94a3b8 !important; }

@media (max-width: 576px) {
    .step-label { display: none !important; }
    .wizard-steps-row::before { top: 19px !important; }
    .step-circle { width: 38px !important; height: 38px !important; font-size: 13px !important; }
}
</style>

<div class="row">
    <div class="col-sm-12">
        <x-form id="save-employee-data-form">

            {{-- ═══════════════════════════════════════════════════
                 WIZARD HEADER — step indicators + progress bar
                 ═══════════════════════════════════════════════════ --}}
            <div class="bg-white rounded mb-4 employee-wizard-header">
                <div class="wizard-steps-row">
                    @foreach ([
                        [1, 'user',           'Personal Info'],
                        [2, 'university',     'Bank Details'],
                        [3, 'briefcase',      'Employment'],
                        [4, 'passport',       'Passport'],
                        [5, 'clipboard-list', 'Checklist'],
                        [6, 'check-circle',   'Review'],
                        [7, 'file-signature', 'Declaration'],
                    ] as [$n, $ico, $lbl])
                        <div class="wizard-step-item {{ $n === 1 ? 'active' : '' }}" data-step="{{ $n }}">
                            <div class="step-circle"><i class="fa fa-{{ $ico }}"></i></div>
                            <span class="step-label">{{ $lbl }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="wizard-progress-bar-wrap">
                    <div class="wizard-progress-fill" id="wizardProgressFill"></div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════
                 STEP 1 — Personal Info + Emergency Contacts
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane" id="wizardStep1">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-id-card"></i></div>
                        <div>
                            <h5>Account Details</h5>
                            <p>Identity, credentials, role and personal information</p>
                        </div>
                    </div>

                    @include('employees.ajax.partials.onboarding-resume', ['onboardingDocs' => null])

                    {{-- Identity, job, pay, personal details, shift, addresses, emergency contacts:
                         the same partial on Add and Edit --}}
                    @include('employees.ajax.partials.account-details', ['e' => null])

                    <div class="wizard-nav-bar">
                        <div></div>
                        <button type="button" class="btn btn-primary wizardNext" data-to="2">
                            Next: Bank Details &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 1 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 2 — Bank Details
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep2">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-university"></i></div>
                        <div>
                            <h5>Bank Details</h5>
                            <p>Bank account, IFSC code and statutory account numbers</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="personal_account_number"
                                :fieldLabel="__('modules.employees.personalAccountNumber')"
                                fieldName="personal_account_number" fieldRequired="true" required
                                inputmode="numeric" minlength="9" maxlength="18" pattern="[0-9]{9,18}"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 18);"
                                :fieldPlaceholder="__('placeholders.accountNumber')">
                            </x-forms.text>
                        </div>
                        @include('employees.ajax.partials.onboarding-bank', ['part' => 'confirm'])
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="ifsc_code"
                                :fieldLabel="__('modules.employees.ifscCode')"
                                fieldName="ifsc_code" fieldRequired="true" required
                                :fieldPlaceholder="__('placeholders.ifscCode')"
                                pattern="[A-Z]{4}0[A-Z0-9]{6}" maxlength="11"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11);">
                            </x-forms.text>
                        </div>
                        @include('employees.ajax.partials.onboarding-bank', ['part' => 'branch'])
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="pf_account_number"
                                :fieldLabel="__('modules.employees.pfAccountNumber')"
                                fieldName="pf_account_number" maxlength="30"
                                pattern="[A-Za-z0-9/\-]+"
                                oninput="this.value = this.value.replace(/[^A-Za-z0-9\/\-]/g, '');"
                                :fieldPlaceholder="__('placeholders.pfAccountNumber')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="esi_number"
                                :fieldLabel="__('modules.employees.esiNumber')"
                                fieldName="esi_number" inputmode="numeric" maxlength="20"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                                :fieldPlaceholder="__('placeholders.esiNumber')">
                            </x-forms.text>
                        </div>
                    </div>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="1">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="3">
                            Next: Employment &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 2 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 3 — Experience, Payroll & Other Details
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep3">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-briefcase"></i></div>
                        <div>
                            <h5>Work Experience</h5>
                            <p>Add previous employment history (optional for freshers)</p>
                        </div>
                    </div>

                    {{-- Hidden total_experience updated by JS --}}
                    <input type="hidden" name="total_experience" id="total_experience" value="">

                    <div id="experienceCards">
                        {{-- Cards injected by JS --}}
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="expAddCard">
                            <i class="fa fa-plus mr-1"></i> Add Experience
                        </button>
                        <div class="d-flex align-items-center" id="totalExpWrap" style="display:none!important">
                            <span class="text-muted f-13 mr-2">Total Experience:</span>
                            <span class="badge badge-primary px-3 py-2 f-13" id="totalExpDisplay">—</span>
                        </div>
                    </div>

                    <div class="wizard-section-header mt-4 mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-cog"></i></div>
                        <div>
                            <h5>Other Details</h5>
                            <p>Login settings, employment type, rates and more</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="my-3 form-group">
                                <label class="mb-12 f-14 text-dark-grey w-100">@lang('modules.client.clientCanLogin')</label>
                                <div class="d-flex">
                                    <x-forms.radio fieldId="login-yes" :fieldLabel="__('app.yes')"
                                        fieldName="login" fieldValue="enable" checked="true">
                                    </x-forms.radio>
                                    <x-forms.radio fieldId="login-no" :fieldLabel="__('app.no')"
                                        fieldValue="disable" fieldName="login"></x-forms.radio>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="my-3 form-group">
                                <label class="mb-12 f-14 text-dark-grey w-100">@lang('modules.emailSettings.emailNotifications')</label>
                                <div class="d-flex">
                                    <x-forms.radio fieldId="notification-yes" :fieldLabel="__('app.yes')"
                                        fieldValue="yes" fieldName="email_notifications" checked="true">
                                    </x-forms.radio>
                                    <x-forms.radio fieldId="notification-no" :fieldLabel="__('app.no')"
                                        fieldValue="no" fieldName="email_notifications">
                                    </x-forms.radio>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="hourly_rate"
                                :fieldLabel="__('modules.employees.hourlyRate')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span>
                                </x-slot>
                                <input type="number" step=".01" min="0" class="form-control height-35 f-14"
                                    name="hourly_rate" id="hourly_rate">
                            </x-forms.input-group>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="slack_username"
                                :fieldLabel="__('modules.employees.slackUsername')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">@</span>
                                </x-slot>
                                <input type="text" class="form-control height-35 f-14"
                                    name="slack_username" id="slack_username">
                            </x-forms.input-group>
                        </div>
                        <div class="col-md-12">
                            <x-forms.text fieldId="tags" :fieldLabel="__('app.skills')"
                                fieldName="tags" :fieldPlaceholder="__('placeholders.skills')" />
                        </div>
                        @if (function_exists('sms_setting') && sms_setting()->telegram_status)
                            <div class="col-md-6">
                                <x-forms.number fieldName="telegram_user_id" fieldId="telegram_user_id"
                                    fieldLabel="<i class='fab fa-telegram'></i> {{ __('sms::modules.telegramUserId') }}"
                                    :popover="__('sms::modules.userIdInfo')" />
                                <p class="text-bold text-danger">@lang('sms::modules.telegramBotNameInfo')</p>
                                <p class="text-bold">
                                    <span id="telegram-link-text">https://t.me/{{ sms_setting()->telegram_bot_name }}</span>
                                    <a href="javascript:;" class="p-1 py-2 ml-1 rounded btn-copy btn-secondary f-12"
                                        data-clipboard-target="#telegram-link-text">
                                        <i class="mx-1 fa fa-copy"></i>@lang('app.copy')</a>
                                    <a href="https://t.me/{{ sms_setting()->telegram_bot_name }}" target="_blank"
                                        class="p-1 py-2 ml-1 rounded btn-secondary f-12">
                                        <i class="mx-1 fa fa-copy"></i>@lang('app.openInNewTab')</a>
                                </p>
                            </div>
                        @endif
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="probation_end_date"
                                :fieldLabel="__('modules.employees.probationEndDate')"
                                fieldName="probation_end_date" :fieldPlaceholder="__('placeholders.date')"
                                :popover="__('messages.probationEndDate')"/>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="notice_period_start_date"
                                :fieldLabel="__('modules.employees.noticePeriodStartDate')"
                                fieldName="notice_period_start_date" :fieldPlaceholder="__('placeholders.date')"
                                :popover="__('messages.noticePeriodStartDate')"/>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="notice_period_end_date"
                                :fieldLabel="__('modules.employees.noticePeriodEndDate')"
                                fieldName="notice_period_end_date" :fieldPlaceholder="__('placeholders.date')"
                                :popover="__('messages.noticePeriodEndDate')"/>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="employment_type"
                                :fieldLabel="__('modules.employees.employmentType')"
                                fieldName="employment_type">
                                <option value="">--</option>
                                <option value="full_time">@lang('app.fullTime')</option>
                                <option value="part_time">@lang('app.partTime')</option>
                                <option value="on_contract">@lang('app.onContract')</option>
                                <option value="internship">@lang('app.internship')</option>
                                <option value="trainee">@lang('app.trainee')</option>
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6 d-none internship-date">
                            <x-forms.datepicker fieldId="internship_end_date"
                                :fieldLabel="__('modules.employees.internshipEndDate')"
                                fieldName="internship_end_date" :fieldPlaceholder="__('placeholders.date')"/>
                        </div>
                        <div class="col-lg-3 col-md-6 d-none contract-date">
                            <x-forms.datepicker fieldId="contract_end_date"
                                :fieldLabel="__('modules.employees.contractEndDate')"
                                fieldName="contract_end_date" :fieldPlaceholder="__('placeholders.date')"/>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="employment_status"
                                fieldLabel="Employee Exit"
                                fieldName="employment_status">
                                <option value="confirmed">Confirmed</option>
                                <option value="probation">Probation Period</option>
                                <option value="notice_period">Notice Period</option>
                                <option value="off_boarded">Off Board</option>
                                <option value="none">None</option>
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="marital_status"
                                :fieldLabel="__('modules.employees.maritalStatus')"
                                fieldName="marital_status">
                                @foreach (\App\Enums\MaritalStatus::cases() as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6 d-none marriage_date">
                            <x-forms.datepicker fieldId="marriage_anniversary_date"
                                :fieldLabel="__('modules.employees.marriageAnniversaryDate')"
                                fieldName="marriage_anniversary_date" :fieldPlaceholder="__('placeholders.date')"/>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="category_id"
                                :fieldLabel="__('app.menu.businessAddresses')" fieldRequired="true">
                            </x-forms.label>
                            <x-forms.input-group>
                                <select class="form-control select-picker" name="company_address"
                                    id="company_address" data-live-search="true">
                                    @foreach ($companyAddresses as $address)
                                        <option value="{{ $address->id }}">{{ $address->location }}</option>
                                    @endforeach
                                </select>
                            </x-forms.input-group>
                        </div>
                        <input type="hidden" name="add_more" value="false" id="add_more" />
                    </div>

                    <x-forms.custom-field :fields="$fields"></x-forms.custom-field>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="2">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="4">
                            Next: Passport &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 3 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 3 — Passport Details
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep4">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-passport"></i></div>
                        <div>
                            <h5>Passport Details</h5>
                            <p>Optional — fill it in when the employee has a passport</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="passport_number"
                                :fieldLabel="__('modules.employees.passportNumber')"
                                fieldName="passport_number" maxlength="8"
                                pattern="[A-Z][0-9]{7}"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);"
                                fieldPlaceholder="e.g. A1234567">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="passport_date_of_issue"
                                :fieldLabel="__('modules.employees.passportDateOfIssue')"
                                fieldName="passport_date_of_issue" :fieldPlaceholder="__('placeholders.date')" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="passport_place_of_issue"
                                :fieldLabel="__('modules.employees.passportPlaceOfIssue')"
                                fieldName="passport_place_of_issue" maxlength="100"
                                pattern="[A-Za-z,.\-\s]+"
                                oninput="this.value = this.value.replace(/[^A-Za-z,.\-\s]/g, '');"
                                :fieldPlaceholder="__('placeholders.location')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="passport_valid_up_to"
                                :fieldLabel="__('modules.employees.passportValidUpTo')"
                                fieldName="passport_valid_up_to" :fieldPlaceholder="__('placeholders.date')" />
                        </div>
                    </div>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="3">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="5">
                            Next: Checklist &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 4 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 4 — Joining Checklist
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep5">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-clipboard-list"></i></div>
                        <div>
                            <h5>@lang('modules.employees.joiningChecklist')</h5>
                            <p>Documents received from the employee at joining — each ticked item needs its document</p>
                        </div>
                    </div>
                    @include('employees.ajax.partials.onboarding-checklist', ['joiningChecklist' => null, 'onboardingDocs' => null])

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="4">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="6">
                            Next: Review &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 5 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 6 — Review
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep6">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-check-circle"></i></div>
                        <div>
                            <h5>Review</h5>
                            <p>Check every detail and document; the declaration and Save come next</p>
                        </div>
                    </div>

                    <div class="border rounded overflow-hidden" id="wizardReviewContent">
                        {{-- populated by buildWizardReview() --}}
                    </div>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="5">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="7">
                            Next: Declaration &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 6 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 7 — Final Declaration (last step: Save)
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep7">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-file-signature"></i></div>
                        <div>
                            <h5>@lang('modules.employees.finalDeclaration')</h5>
                            <p>Reported duty date, signatures and the signed copies — then Save</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <p class="text-dark-grey f-14 mb-3">@lang('modules.employees.reportedToDutyOn')</p>
                            <div class="row">
                                <div class="col-lg-2 col-md-6">
                                    <x-forms.select fieldId="reported_duty_day"
                                        :fieldLabel="__('modules.employees.day')"
                                        fieldName="reported_duty_day" fieldRequired="true" search="false">
                                        <option value="">--</option>
                                        @for ($day = 1; $day <= 31; $day++)
                                            <option value="{{ $day }}">{{ $day }}</option>
                                        @endfor
                                    </x-forms.select>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <x-forms.select fieldId="reported_duty_month"
                                        :fieldLabel="__('modules.employees.month')"
                                        fieldName="reported_duty_month" fieldRequired="true" search="false">
                                        <option value="">--</option>
                                        @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $month)
                                            <option value="{{ $month }}">{{ $month }}</option>
                                        @endforeach
                                    </x-forms.select>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <x-forms.text fieldId="reported_duty_year"
                                        :fieldLabel="__('modules.employees.year')"
                                        fieldName="reported_duty_year" fieldRequired="true" required
                                        inputmode="numeric" maxlength="4" pattern="[0-9]{4}"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);"
                                        :fieldPlaceholder="__('placeholders.year')">
                                    </x-forms.text>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <x-forms.text fieldId="candidate_signature"
                                :fieldLabel="__('modules.employees.signatureOfCandidate')"
                                fieldName="candidate_signature" fieldRequired="true" required maxlength="100"
                                pattern="[A-Za-z\s]+"
                                oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '');"
                                :fieldPlaceholder="__('placeholders.signature')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <x-forms.text fieldId="hr_signature"
                                :fieldLabel="__('modules.employees.signatureOfHR')"
                                fieldName="hr_signature" fieldRequired="true" required maxlength="100"
                                pattern="[A-Za-z\s]+"
                                oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '');"
                                :fieldPlaceholder="__('placeholders.signature')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-12 col-md-12">
                            <x-forms.textarea fieldId="hr_comments"
                                :fieldLabel="__('modules.employees.hrComments')"
                                fieldName="hr_comments" :fieldPlaceholder="__('placeholders.comments')"
                                maxlength="500"
                                oninput="this.value = this.value.replace(/[^A-Za-z0-9 ,.\-()\r\n]/g, '').slice(0, 500);">
                            </x-forms.textarea>
                        </div>
                        @include('employees.ajax.partials.onboarding-declaration-files', ['onboardingDocs' => null])
                    </div>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="6">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <div>
                            <button id="save-employee-form" type="button" class="btn btn-primary mr-2">
                                <i class="fa fa-check mr-1"></i> @lang('app.save')
                            </button>
                            <button id="save-more-employee-form" type="button" class="btn btn-secondary mr-2">
                                <i class="fa fa-check-double mr-1"></i> @lang('app.saveAddMore')
                            </button>
                            <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">
                                @lang('app.cancel')
                            </a>
                        </div>
                    </div>

                </div>
            </div>{{-- /step 7 --}}

        </x-form>
    </div>
</div>

<script src="{{ asset('vendor/jquery/tagify.min.js') }}"></script>
@if (function_exists('sms_setting') && sms_setting()->telegram_status)
    <script src="{{ asset('vendor/jquery/clipboard.min.js') }}"></script>
@endif

<script>
$(document).ready(function () {

    // ── Datepickers ───────────────────────────────────────────
    var dpConfig = { position: 'bl', ...datepickerConfig };
    var dpFields = [
        '#joining_date',
        '#probation_end_date', '#notice_period_start_date', '#notice_period_end_date',
        '#marriage_anniversary_date', '#internship_end_date', '#contract_end_date',
        '#passport_date_of_issue', '#passport_valid_up_to', '#shift_effective_from'
    ];
    dpFields.forEach(function (sel) { datepicker(sel, dpConfig); });

    datepicker('#date_of_birth', { position: 'bl', maxDate: new Date(), ...datepickerConfig, onSelect: function() { generateAutoPassword(); } });

    // Read-only datepicker inputs
    $('#joining_date, #date_of_birth, #passport_date_of_issue, #passport_valid_up_to')
        .attr('readonly', true).attr('autocomplete', 'off')
        .on('keydown paste drop', function (e) { e.preventDefault(); });

    // ── Auto-generate password from name + DOB ───────────────
    function generateAutoPassword() {
        var namePart = $('#first_name').val().trim().toLowerCase().replace(/[^a-z]/g, '').substring(0, 4);
        var dobPart  = $('#date_of_birth').val().replace(/[^0-9]/g, '');
        if (namePart || dobPart) {
            $('#password').val(namePart + dobPart);
        }
    }

    $('#first_name').on('input', generateAutoPassword);

    $('.custom-date-picker').each(function (i, el) { datepicker(el, dpConfig); });

    // ── Tagify ────────────────────────────────────────────────
    var tagsInput = document.querySelector('input[name=tags]');
    if (tagsInput) { new Tagify(tagsInput); }

    // ── Country → phone-code sync ────────────────────────────
    $('#country').change(function () {
        var phonecode = $(this).find(':selected').data('phonecode');
        var iso       = $(this).find(':selected').data('iso');
        $('#country_phonecode').find('option').each(function () {
            if ($(this).data('country-iso') === iso) {
                $(this).val(phonecode).prop('selected', true);
            }
        });
        $('.select-picker').selectpicker('refresh');
    });

    // ── Marital status → marriage date ───────────────────────
    $('#marital_status').change(function () {
        var v = $(this).val();
        $('.marriage_date').toggleClass('d-none', v !== '{{ \App\Enums\MaritalStatus::Married->value }}');
    });

    // ── Employment type → contract / internship date ─────────
    $('#employment_type').change(function () {
        var v = $(this).val();
        $('.contract-date').toggleClass('d-none', v !== 'on_contract');
        $('.internship-date').toggleClass('d-none', v !== 'internship');
    });

    // ── Random password ──────────────────────────────────────
    $('#random_password').click(function () {
        $('#password').val(Math.random().toString(36).substr(2, 8));
    });

    // ── Quick-add designation / department / branch ──────────
    $('#designation-setting-add').click(function () {
        var url = "{{ route('designations.create') }}?model=true";
        $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_LG, url);
    });
    $('#department-add').click(function () {
        var url = "{{ route('departments.create') }}?model=true";
        $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_LG, url);
    });
    $('#branch-add').click(function () {
        var url = "{{ route('branches.create') }}?model=true";
        $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
        $.ajaxModal(MODAL_LG, url);
    });

    // ══════════════════════════════════════════════════════════
    //  WIZARD NAVIGATION
    // ══════════════════════════════════════════════════════════
    var wizardStep  = 1;
    var wizardTotal = 7;

    function wizardGoTo(target) {
        // Update panes
        $('.wizard-pane').addClass('d-none');
        $('#wizardStep' + target).removeClass('d-none');

        // Update step indicators
        $('.wizard-step-item').removeClass('active completed');
        for (var i = 1; i < target; i++) {
            $('[data-step="' + i + '"]').addClass('completed');
        }
        $('[data-step="' + target + '"]').addClass('active');

        // Progress bar
        var pct = Math.round(((target - 1) / (wizardTotal - 1)) * 100);
        $('#wizardProgressFill').css('width', pct + '%');

        wizardStep = target;

        // Scroll to wizard header
        var header = $('.employee-wizard-header');
        if (header.length) {
            $('html, body').animate({ scrollTop: header.offset().top - 80 }, 200);
        }

        if (target === 6) { buildWizardReview(); }
    }

    // Next, Back and the step circles: Back is here; Next and the circles are in
    // onboarding-script, which lets every step open and marks unfinished ones.
    $(document).on('click', '.wizardPrev', function () {
        wizardGoTo(parseInt($(this).data('to')));
    });

    // ── Build Review tab ─────────────────────────────────────
    function buildWizardReview() {
        function val(id) {
            var el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }
        function selText(id) {
            var el = document.getElementById(id);
            if (!el || el.selectedIndex < 0) return '';
            return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text.trim() : '';
        }
        function r(label, value) {
            var v = value || '<span class="text-muted">—</span>';
            return '<div class="review-row"><span class="review-label">' + label +
                   '</span><span class="review-value">' + v + '</span></div>';
        }
        function chk(label, id) {
            var el  = document.getElementById(id);
            var yes = el && el.checked;
            return '<div class="review-row"><span class="review-label">' + label +
                   '</span><span class="review-value ' + (yes ? 'review-check-yes' : 'review-check-no') + '">' +
                   (yes ? '<i class="fa fa-check-circle mr-1"></i>Yes'
                        : '<i class="fa fa-times-circle mr-1"></i>No') +
                   '</span></div>';
        }

        var h = '';

        h += '<p class="review-section-title"><i class="fa fa-user mr-2"></i>Personal Information</p>';
        h += r('Employee ID',    val('employee_id'));
        var fullName = [val('first_name'), val('middle_name'), val('last_name')].filter(Boolean).join(' ');
        h += r('Full Name',      fullName || '<span class="text-muted">—</span>');
        h += r('Email',          val('email'));
        h += r('Personal Email', val('personal_email'));
        h += r('Designation',       selText('employee_designation'));
        h += r('Department',        selText('employee_department'));
        h += r('Branch',            selText('employee_branch'));
        h += r('Joining Date',      val('joining_date'));
        h += r('Date of Birth',     val('date_of_birth'));
        h += r('Gender',            selText('gender'));
        h += r('Mobile',            val('mobile'));
        h += r('Country',           selText('country'));
        h += r('Blood Group',       selText('blood_group'));
        h += r('PAN Number',        val('pan_number'));
        h += r('Aadhar Number',     val('aadhar_card_number'));
        h += r('Qualification',     val('qualification'));
        h += r('Employee Card ID',  val('employee_card_id'));
        h += r('CTC',               val('employee_ctc'));
        h += r('Role',                 selText('role'));
        h += r('Work Shift',           selText('employee_shift_id') + (val('shift_effective_from') ? ' — from ' + val('shift_effective_from') : ''));
        h += r('Communication Address', val('address'));
        h += r('Permanent Address',     val('permanent_address'));

        h += '<p class="review-section-title"><i class="fa fa-phone-alt mr-2"></i>Emergency Contacts</p>';
        $('#emergencyContactRows tr').each(function (i) {
            var name = $(this).find('.ec-name').val() || '';
            var rel  = $(this).find('.ec-relation').val() || '';
            var num  = $(this).find('.ec-number').val() || '';
            h += r('Contact ' + (i + 1), name + (rel ? ' (' + rel + ')' : '') + (num ? ' — ' + num : ''));
        });

        h += '<p class="review-section-title"><i class="fa fa-university mr-2"></i>Bank Details</p>';
        h += r('Bank Account No.',   val('personal_account_number'));
        h += r('IFSC Code',          val('ifsc_code'));
        h += r('PF Account No.',     val('pf_account_number'));
        h += r('ESI (IP) No.',       val('esi_number'));

        h += '<p class="review-section-title"><i class="fa fa-briefcase mr-2"></i>Work Experience</p>';
        var expCards = $('#experienceCards .experience-card');
        if (expCards.length === 0) {
            h += r('Work Experience', '<span class="text-muted">No experience added (fresher)</span>');
        } else {
            expCards.each(function(i) {
                var card   = $(this);
                var co     = card.find('.exp-company').val() || '';
                var loc    = card.find('.exp-location').val() || '';
                var desig  = card.find('.exp-designation').val() || '';
                var join   = card.find('.exp-joining-date').val() || '';
                var leave  = card.find('.exp-leaving-date').val() || 'Present';
                var dur    = card.find('.exp-duration').text() || '';
                var detail = [desig, co + (loc ? ', ' + loc : ''), join + ' – ' + leave, dur].filter(Boolean).join(' | ');
                h += r('Experience ' + (i + 1), detail);
            });
            var totalExp = $('#totalExpDisplay').text();
            if (totalExp && totalExp !== '—') { h += r('Total Experience', totalExp); }
        }
        h += r('Employment Type', selText('employment_type'));
        h += r('Employee Exit',   selText('employment_status'));

        h += '<p class="review-section-title"><i class="fa fa-passport mr-2"></i>Passport Details</p>';
        h += r('Passport No.',   val('passport_number'));
        h += r('Date of Issue',  val('passport_date_of_issue'));
        h += r('Place of Issue', val('passport_place_of_issue'));
        h += r('Valid Until',    val('passport_valid_up_to'));

        // The joining checklist is listed once, with its documents, by onboardingReviewHtml()

        // Salary, bank and every document (onboarding-script)
        h += onboardingReviewHtml();

        $('#wizardReviewContent').html(h);
    }

    // ══════════════════════════════════════════════════════════
    //  VALIDATION FUNCTIONS
    // ══════════════════════════════════════════════════════════

    function validateAccountDetailsSection() {
        var employeeId       = document.getElementById('employee_id');
        var firstName        = document.getElementById('first_name');
        var lastName         = document.getElementById('last_name');
        var employeeEmail    = document.getElementById('email');
        var employeePassword = document.getElementById('password');
        var designation      = document.getElementById('employee_designation');
        var department       = document.getElementById('employee_department');
        var cardId           = document.getElementById('employee_card_id');
        var employeeCtc      = document.getElementById('employee_ctc');
        var basicForPayroll  = document.getElementById('basic_for_payroll');
        var panNumber        = document.getElementById('pan_number');
        var joiningDate      = document.getElementById('joining_date');
        var dateOfBirth      = document.getElementById('date_of_birth');

        var emailRegex   = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        var cardIdRegex  = /^[A-Za-z0-9_-]{1,50}$/;
        var panRegex     = /^[A-Z]{5}[0-9]{4}[A-Z]$/;
        var isValid      = true;

        if (!employeeId.value.trim())                                              { setFieldState(employeeId, false, 'Employee ID is required.');                              isValid = false; } else { setFieldState(employeeId, true); }
        if (!firstName.value.trim())                                               { setFieldState(firstName, false, 'First Name is required.');                               isValid = false; } else { setFieldState(firstName, true); }
        if (!lastName.value.trim())                                                { setFieldState(lastName, false, 'Last Name is required.');                                 isValid = false; } else { setFieldState(lastName, true); }
        if (!employeeEmail.value.trim() || !emailRegex.test(employeeEmail.value)) { setFieldState(employeeEmail, false, 'Please enter a valid email address.');                isValid = false; } else { setFieldState(employeeEmail, true); }
        if (!employeePassword.value || employeePassword.value.length < 8)         { setFieldState(employeePassword, false, 'Password must be at least 8 characters.');         isValid = false; } else { setFieldState(employeePassword, true); }
        if (!designation.value)                                                    { setFieldState(designation, false, 'Designation is required.');                             isValid = false; } else { setFieldState(designation, true); }
        if (!department.value)                                                     { setFieldState(department, false, 'Department is required.');                               isValid = false; } else { setFieldState(department, true); }
        if (!cardId.value || !cardIdRegex.test(cardId.value.trim()))               { setFieldState(cardId, false, 'Employee Card ID may use letters, numbers, hyphens and underscores (max 50).');         isValid = false; } else { setFieldState(cardId, true); }
        if (!panNumber.value.trim() || !panRegex.test(panNumber.value.toUpperCase())) { setFieldState(panNumber, false, 'Enter a valid PAN in format ABCDE1234F.');             isValid = false; } else { panNumber.value = panNumber.value.toUpperCase(); setFieldState(panNumber, true); }
        if (!joiningDate.value)                                                    { setFieldState(joiningDate, false, 'Joining Date is required.');                            isValid = false; } else { setFieldState(joiningDate, true); }

        if (employeeCtc.value && (isNaN(employeeCtc.value) || Number(employeeCtc.value) < 0)) {
            setFieldState(employeeCtc, false, 'CTC must be a valid positive number.'); isValid = false;
        } else { setFieldState(employeeCtc, true); }

        if (basicForPayroll.value && (!Number.isInteger(Number(basicForPayroll.value)) || Number(basicForPayroll.value) < 0 || Number(basicForPayroll.value) > 100)) {
            setFieldState(basicForPayroll, false, 'Basic % must be a whole number 0–100.'); isValid = false;
        } else { setFieldState(basicForPayroll, true); }

        if (dateOfBirth.value && typeof moment !== 'undefined') {
            var fmts = ['YYYY-MM-DD','DD-MM-YYYY','MM-DD-YYYY','DD/MM/YYYY','MM/DD/YYYY'];
            if (typeof company !== 'undefined' && company.moment_date_format) fmts.unshift(company.moment_date_format);
            var dob = moment(dateOfBirth.value, fmts, true);
            if (dob.isValid() && dob.isAfter(moment(), 'day')) { setFieldState(dateOfBirth, false, 'Date of Birth cannot be a future date.'); isValid = false; }
            else { setFieldState(dateOfBirth, true); }
        } else { setFieldState(dateOfBirth, true); }

        return isValid;
    }

    @include('employees.ajax.partials.emergency-script')

    // ── Dynamic Experience Cards ─────────────────────────────
    var expCardCount = 0;
    var expDpConfig  = Object.assign({}, { position: 'bl' }, datepickerConfig);

    function buildExpCard(idx) {
        return '<div class="experience-card card border mb-3" data-exp-idx="' + idx + '">' +
            '<div class="card-header d-flex justify-content-between align-items-center py-2 px-3" style="background:#f8fafc">' +
                '<span class="f-14 font-weight-600 text-dark exp-card-title">Experience ' + (idx + 1) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger exp-remove-btn"><i class="fa fa-trash"></i></button>' +
            '</div>' +
            '<div class="card-body py-3">' +
                '<div class="row">' +
                    '<div class="col-lg-4 col-md-6">' +
                        '<div class="form-group mb-3">' +
                            '<label class="f-14 text-dark-grey d-block mb-1">Company Name</label>' +
                            '<input type="text" name="exp_company[]" class="form-control height-35 f-14 exp-company" maxlength="100" placeholder="Company Name">' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-lg-4 col-md-6">' +
                        '<div class="form-group mb-3">' +
                            '<label class="f-14 text-dark-grey d-block mb-1">Location</label>' +
                            '<input type="text" name="exp_location[]" class="form-control height-35 f-14 exp-location" maxlength="150" placeholder="City, Country">' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-lg-4 col-md-6">' +
                        '<div class="form-group mb-3">' +
                            '<label class="f-14 text-dark-grey d-block mb-1">Designation / Job Title</label>' +
                            '<input type="text" name="exp_designation[]" class="form-control height-35 f-14 exp-designation" maxlength="100" placeholder="e.g. Software Engineer">' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-lg-4 col-md-6">' +
                        '<div class="form-group mb-3">' +
                            '<label class="f-14 text-dark-grey d-block mb-1">Date of Joining</label>' +
                            '<input type="text" name="exp_joining[]" class="form-control height-35 f-14 exp-joining-date" placeholder="{{ __('placeholders.date') }}" readonly>' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-lg-4 col-md-6">' +
                        '<div class="form-group mb-3">' +
                            '<label class="f-14 text-dark-grey d-block mb-1">Date of Leaving <small class="text-muted">(blank = current)</small></label>' +
                            '<input type="text" name="exp_leaving[]" class="form-control height-35 f-14 exp-leaving-date" placeholder="{{ __('placeholders.date') }}" readonly>' +
                        '</div>' +
                    '</div>' +
                    '<div class="col-lg-4 col-md-6 d-flex align-items-end">' +
                        '<div class="form-group mb-3 w-100">' +
                            '<label class="f-14 text-dark-grey d-block mb-1">Duration</label>' +
                            '<div class="form-control height-35 f-14 d-flex align-items-center text-muted exp-duration">—</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function initExpCardDp(card) {
        var joinEl  = card.find('.exp-joining-date')[0];
        var leaveEl = card.find('.exp-leaving-date')[0];
        if (joinEl)  datepicker(joinEl,  Object.assign({}, expDpConfig, { onSelect: function() { updateExpCardDuration(card); calcTotalExperience(); } }));
        if (leaveEl) datepicker(leaveEl, Object.assign({}, expDpConfig, { onSelect: function() { updateExpCardDuration(card); calcTotalExperience(); } }));
        card.find('.exp-joining-date, .exp-leaving-date').on('keydown paste drop', function(e) { e.preventDefault(); });
    }

    function parseExpDate(val) {
        if (!val || typeof moment === 'undefined') return null;
        var fmts = ['DD-MM-YYYY','MM-DD-YYYY','DD/MM/YYYY','MM/DD/YYYY','YYYY-MM-DD'];
        if (typeof datepickerConfig !== 'undefined' && datepickerConfig.dateFormat) {
            // convert datepicker format tokens to moment
            var mFmt = datepickerConfig.dateFormat.replace('dd','DD').replace('mm','MM').replace('yyyy','YYYY');
            fmts.unshift(mFmt);
        }
        var m = moment(val, fmts, false);
        return m.isValid() ? m : null;
    }

    function updateExpCardDuration(card) {
        var joining = parseExpDate(card.find('.exp-joining-date').val());
        var leaving = parseExpDate(card.find('.exp-leaving-date').val()) || moment();
        var dur     = card.find('.exp-duration');
        if (!joining) { dur.text('—'); return; }
        var months  = leaving.diff(joining, 'months');
        if (months < 0) { dur.text('—'); return; }
        var yrs = Math.floor(months / 12), mo = months % 12;
        var txt = '';
        if (yrs)     txt += yrs + ' yr' + (yrs > 1 ? 's' : '');
        if (mo)      txt += (txt ? ' ' : '') + mo + ' mo';
        if (!txt)    txt = '< 1 mo';
        dur.text(txt);
    }

    function calcTotalExperience() {
        var totalMonths = 0;
        $('#experienceCards .experience-card').each(function() {
            var card    = $(this);
            var joining = parseExpDate(card.find('.exp-joining-date').val());
            var leaving = parseExpDate(card.find('.exp-leaving-date').val()) || moment();
            if (!joining) return;
            var m = leaving.diff(joining, 'months');
            if (m > 0) totalMonths += m;
        });
        if (totalMonths > 0) {
            var yrs = Math.floor(totalMonths / 12), mo = totalMonths % 12;
            var txt = '';
            if (yrs) txt += yrs + ' yr' + (yrs > 1 ? 's' : '');
            if (mo)  txt += (txt ? ' ' : '') + mo + ' mo';
            $('#totalExpDisplay').text(txt || '< 1 mo');
            $('#totalExpWrap').show();
            $('#total_experience').val((totalMonths / 12).toFixed(2));
        } else {
            $('#totalExpDisplay').text('—');
            $('#totalExpWrap').hide();
            $('#total_experience').val('');
        }
    }

    function renumberExpCards() {
        $('#experienceCards .experience-card').each(function(i) {
            $(this).attr('data-exp-idx', i).find('.exp-card-title').text('Experience ' + (i + 1));
        });
    }

    function addExpCard() {
        var card = $(buildExpCard(expCardCount));
        $('#experienceCards').append(card);
        initExpCardDp(card);
        expCardCount++;
    }

    $('#expAddCard').on('click', function() { addExpCard(); });

    $(document).on('click', '.exp-remove-btn', function() {
        $(this).closest('.experience-card').remove();
        renumberExpCards();
        calcTotalExperience();
    });

    function validateBankSection() {
        var bankAcc = document.getElementById('personal_account_number');
        var ifsc    = document.getElementById('ifsc_code');
        var pfAcc   = document.getElementById('pf_account_number');
        var esi     = document.getElementById('esi_number');
        var bankRe  = /^[0-9]{9,18}$/;
        var ifscRe  = /^[A-Z]{4}0[A-Z0-9]{6}$/;
        var pfRe    = /^[A-Za-z0-9\/\-]+$/;
        var esiRe   = /^[0-9]+$/;
        var isValid = true;
        if (!bankRe.test(bankAcc.value.trim()))                          { setFieldState(bankAcc, false, 'Bank Account must be 9–18 digits.');         isValid = false; } else { setFieldState(bankAcc, true); }
        if (!ifscRe.test(ifsc.value.trim().toUpperCase()))               { setFieldState(ifsc, false, 'IFSC must follow format SBIN0001234.');          isValid = false; } else { setFieldState(ifsc, true); }
        if (pfAcc.value && !pfRe.test(pfAcc.value.trim()))              { setFieldState(pfAcc, false, 'PF Account: letters, numbers, / and - only.');  isValid = false; } else { setFieldState(pfAcc, true); }
        if (esi.value && !esiRe.test(esi.value.trim()))                 { setFieldState(esi, false, 'ESI Number must be numeric.');                     isValid = false; } else { setFieldState(esi, true); }
        return isValid;
    }

    function validateExperienceSection() {
        // Work experience is optional — no required fields
        return true;
    }

    function validatePassportSection() {
        var passNum   = document.getElementById('passport_number');
        var passIssue = document.getElementById('passport_date_of_issue');
        var passPlace = document.getElementById('passport_place_of_issue');
        var passValid = document.getElementById('passport_valid_up_to');

        var numRe   = /^[A-Z][0-9]{7}$/;
        var placeRe = /^[A-Za-z,.\- ]+$/;
        var isValid = true;
        var fmts    = ['YYYY-MM-DD','DD-MM-YYYY','MM-DD-YYYY','DD/MM/YYYY','MM/DD/YYYY'];
        if (typeof company !== 'undefined' && company.moment_date_format) fmts.unshift(company.moment_date_format);

        // Passport details are optional: only what has been filled in is checked.
        [passNum, passIssue, passPlace, passValid].forEach(function (el) { clearFieldState(el); });
        if (passNum.value && !numRe.test(passNum.value.toUpperCase()))     { setFieldState(passNum, false, 'Passport No.: one uppercase letter followed by 7 digits (e.g. A1234567).');   isValid = false; } else if (passNum.value) { passNum.value = passNum.value.toUpperCase(); setFieldState(passNum, true); }
        if (passPlace.value && !placeRe.test(passPlace.value.trim()))      { setFieldState(passPlace, false, 'Place of Issue: alphabets, spaces, commas, hyphens and periods only.');     isValid = false; } else if (passPlace.value) { setFieldState(passPlace, true); }

        if (passIssue.value && passValid.value && typeof moment !== 'undefined') {
            var d1 = moment(passIssue.value, fmts, true);
            var d2 = moment(passValid.value, fmts, true);
            if (d1.isValid() && d2.isValid() && !d2.isAfter(d1, 'day')) {
                setFieldState(passValid, false, 'Valid Until must be after Date of Issue.'); isValid = false;
            }
        }
        return isValid;
    }

    function validateFinalDeclarationSection() {
        var day    = document.getElementById('reported_duty_day');
        var month  = document.getElementById('reported_duty_month');
        var year   = document.getElementById('reported_duty_year');
        var candSig = document.getElementById('candidate_signature');
        var hrSig   = document.getElementById('hr_signature');
        var hrCmt   = document.getElementById('hr_comments');

        var sigRe  = /^[A-Za-z ]+$/;
        var cmpRe  = /^[A-Za-z0-9 ,.\-()\r\n]*$/;
        var isValid = true;

        if (!day.value)                                                              { setFieldState(day, false, 'Day is required.');    isValid = false; } else { setFieldState(day, true); }
        if (!month.value)                                                            { setFieldState(month, false, 'Month is required.'); isValid = false; } else { setFieldState(month, true); }
        if (!year.value || !/^[0-9]{4}$/.test(year.value))                          { setFieldState(year, false, 'Enter a valid 4-digit year.'); isValid = false; } else { setFieldState(year, true); }
        if (!candSig.value.trim() || !sigRe.test(candSig.value.trim()))              { setFieldState(candSig, false, 'Candidate Signature: alphabets and spaces only.'); isValid = false; } else { setFieldState(candSig, true); }
        if (!hrSig.value.trim() || !sigRe.test(hrSig.value.trim()))                 { setFieldState(hrSig, false, 'HR Signature: alphabets and spaces only.');          isValid = false; } else { setFieldState(hrSig, true); }
        if (hrCmt.value && (!cmpRe.test(hrCmt.value) || hrCmt.value.length > 500)) { setFieldState(hrCmt, false, 'HR Comments: invalid characters or over 500 chars.'); isValid = false; } else { setFieldState(hrCmt, true); }

        return isValid;
    }

    // ── Field state helpers ──────────────────────────────────
    function getFieldBorderTarget(field) {
        if (field.tagName === 'SELECT') {
            var wrap = field.closest('.bootstrap-select');
            if (wrap) return wrap;
        }
        return field;
    }

    function getFieldMessageAnchor(field) {
        return field.closest('.input-group') || field.closest('.bootstrap-select') || field;
    }

    function clearFieldState(field) {
        var errEl = document.getElementById(field.id + '-error');
        field.classList.remove('is-valid', 'is-invalid');
        getFieldBorderTarget(field).classList.remove('is-valid', 'is-invalid');
        if (errEl) errEl.remove();
    }

    function setFieldState(field, valid, message) {
        clearFieldState(field);
        var target = getFieldBorderTarget(field);
        if (valid) { target.classList.add('is-valid'); return; }
        target.classList.add('is-invalid');
        if (message) {
            var el = document.createElement('div');
            el.id = field.id + '-error';
            el.className = 'invalid-feedback d-block';
            el.textContent = message;
            getFieldMessageAnchor(field).insertAdjacentElement('afterend', el);
        }
    }

    // ── Live validation listeners ────────────────────────────
    ['employee_id','first_name','last_name','email','password','employee_designation','employee_department',
     'employee_card_id','employee_ctc','basic_for_payroll','pan_number','joining_date','date_of_birth'
    ].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        var ev = (id === 'employee_designation' || id === 'employee_department') ? 'change'
               : (id.includes('date') ? 'change' : 'input');
        el.addEventListener(ev,   function () { validateAccountDetailsSection(); });
        el.addEventListener('blur', function () { validateAccountDetailsSection(); });
    });

    ['passport_number','passport_date_of_issue','passport_place_of_issue','passport_valid_up_to'
    ].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        var ev = (id.includes('date') || id.includes('valid')) ? 'change' : 'input';
        el.addEventListener(ev,   function () { validatePassportSection(); });
        el.addEventListener('blur', function () { validatePassportSection(); });
    });

    ['reported_duty_day','reported_duty_month','reported_duty_year',
     'candidate_signature','hr_signature','hr_comments'
    ].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        var ev = (id.endsWith('_day') || id.endsWith('_month')) ? 'change' : 'input';
        el.addEventListener(ev,   function () { validateFinalDeclarationSection(); });
        el.addEventListener('blur', function () { validateFinalDeclarationSection(); });
    });

    ['personal_account_number','ifsc_code','pf_account_number','esi_number'
    ].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function () { validateBankSection(); });
        el.addEventListener('blur',  function () { validateBankSection(); });
    });


    // ── Save ─────────────────────────────────────────────────
    function afterEmployeeSaved(response) {
        if ($(MODAL_XL).hasClass('show')) {
            $(MODAL_XL).modal('hide');
            window.location.reload();
        } else if (response.add_more === true) {
            var content = $.trim($(RIGHT_MODAL_CONTENT).html());
            if (content.length) {
                $(RIGHT_MODAL_CONTENT).html(response.html.html);
                $('#add_more').val(false);
            } else {
                $('.content-wrapper').html(response.html.html);
                init('.content-wrapper');
                $('#add_more').val(false);
            }
        } else {
            window.location.href = response.redirectUrl;
        }
        if (typeof showTable === 'function') { showTable(); }
    }

    $('#save-employee-form').click(function () {
        $('#add_more').val(false);
        onboardingSubmit("{{ route('employees.store') }}", '#save-employee-form', afterEmployeeSaved);
    });

    $('#save-more-employee-form').click(function () {
        $('#add_more').val(true);
        onboardingSubmit("{{ route('employees.store') }}", '#save-more-employee-form', afterEmployeeSaved);
    });

    // ── Onboarding: open navigation, documents, resume, bank, salary ──
    var onboardingForm = '#save-employee-data-form';
    var onboardingStepChecks = {
        1: function () { var account = validateAccountDetailsSection(); var emergency = validateEmergencySection(); return account && emergency; },
        2: validateBankSection,
        3: validateExperienceSection,
        4: validatePassportSection,
        7: validateFinalDeclarationSection
    };
    @include('employees.ajax.partials.onboarding-script', ['onboardingMode' => 'create'])

    init(RIGHT_MODAL);
});

// ── Image cropper ────────────────────────────────────────────
$('.cropper').on('dropify.fileReady', function () {
    var inputId = $(this).find('input').attr('id');
    var url     = "{{ route('cropper', ':element') }}".replace(':element', inputId);
    $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
    $.ajaxModal(MODAL_LG, url);
});

@if (function_exists('sms_setting') && sms_setting()->telegram_status)
    var clipboard = new ClipboardJS('.btn-copy');
    clipboard.on('success', function () {
        Swal.fire({
            icon: 'success', text: '@lang("app.urlCopied")', toast: true,
            position: 'top-end', timer: 3000, timerProgressBar: true,
            showConfirmButton: false,
            showClass: { popup: 'swal2-noanimation', backdrop: 'swal2-noanimation' }
        });
    });
@endif
</script>
