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
                        [6, 'file-signature', 'Declaration'],
                        [7, 'check-circle',   'Review'],
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

                    <div class="row">
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-5 col-sm-6">
                                    <x-forms.text fieldId="employee_id" :fieldLabel="__('modules.employees.employeeId')"
                                        fieldName="employee_id"
                                        :fieldValue="((!$checkifExistEmployeeId) ? ($lastEmployeeID+1) : '')"
                                        fieldRequired="true"
                                        :fieldPlaceholder="__('modules.employees.employeeIdInfo')"
                                        :popover="__('modules.employees.employeeIdHelp')">
                                    </x-forms.text>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <x-forms.select fieldId="salutation" fieldName="salutation"
                                        :fieldLabel="__('modules.client.salutation')">
                                        <option value="">--</option>
                                        @foreach ($salutations as $salutation)
                                            <option value="{{ $salutation->value }}">{{ $salutation->label() }}</option>
                                        @endforeach
                                    </x-forms.select>
                                </div>
                                <div class="w-100"></div>
                                <div class="col-md-4 col-sm-12">
                                    <x-forms.text fieldId="first_name" fieldLabel="First Name"
                                        fieldName="first_name" fieldRequired="true" fieldPlaceholder="First Name">
                                    </x-forms.text>
                                </div>
                                <div class="col-md-4 col-sm-12">
                                    <x-forms.text fieldId="middle_name" fieldLabel="Middle Name"
                                        fieldName="middle_name" fieldPlaceholder="Middle Name (Optional)">
                                    </x-forms.text>
                                </div>
                                <div class="col-md-4 col-sm-12">
                                    <x-forms.text fieldId="last_name" fieldLabel="Last Name"
                                        fieldName="last_name" fieldRequired="true" fieldPlaceholder="Last Name">
                                    </x-forms.text>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <x-forms.text fieldId="email" :fieldLabel="__('modules.employees.employeeEmail')"
                                        fieldName="email" fieldRequired="true" :fieldPlaceholder="__('placeholders.email')">
                                    </x-forms.text>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <x-forms.text fieldId="personal_email" fieldLabel="Personal Email"
                                        fieldName="personal_email" fieldType="email" fieldPlaceholder="Personal Email (Optional)">
                                    </x-forms.text>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <x-forms.datepicker fieldId="date_of_birth"
                                        :fieldLabel="__('modules.employees.dateOfBirth')"
                                        fieldName="date_of_birth" :fieldPlaceholder="__('placeholders.date')" />
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <x-forms.label class="mt-3" fieldId="password"
                                        :fieldLabel="__('app.password')" fieldRequired="true">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <input type="password" name="password" id="password"
                                            class="form-control height-35 f-14">
                                        <x-slot name="preappend">
                                            <button type="button" data-toggle="tooltip"
                                                data-original-title="@lang('app.viewPassword')"
                                                class="btn btn-outline-secondary border-grey height-35 toggle-password">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </x-slot>
                                        <x-slot name="append">
                                            <button id="random_password" type="button" data-toggle="tooltip"
                                                data-original-title="@lang('modules.client.generateRandomPassword')"
                                                class="btn btn-outline-secondary border-grey height-35">
                                                <i class="fa fa-random"></i>
                                            </button>
                                        </x-slot>
                                    </x-forms.input-group>
                                    <small class="form-text text-muted">Auto-generated from first 4 letters of name + date of birth. You can change it.</small>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <x-forms.label class="my-3" fieldId="category_id"
                                        :fieldLabel="__('app.designation')" fieldRequired="true">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <select class="form-control select-picker" name="designation"
                                            id="employee_designation" data-live-search="true">
                                            <option value="">--</option>
                                            @foreach ($designations as $designation)
                                                <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                                            @endforeach
                                        </select>
                                        @if ($addDesignationPermission == 'all')
                                            <x-slot name="append">
                                                <button id="designation-setting-add" type="button"
                                                    class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                                            </x-slot>
                                        @endif
                                    </x-forms.input-group>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <x-forms.label class="my-3" fieldId="category_id"
                                        :fieldLabel="__('app.department')" fieldRequired="true">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <select class="form-control select-picker" name="department"
                                            id="employee_department" data-live-search="true">
                                            <option value="">--</option>
                                            @foreach ($teams as $team)
                                                <option value="{{ $team->id }}">{{ $team->team_name }}</option>
                                            @endforeach
                                        </select>
                                        @if ($addDepartmentPermission == 'all')
                                            <x-slot name="append">
                                                <button id="department-add" type="button"
                                                    class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                                            </x-slot>
                                        @endif
                                    </x-forms.input-group>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <x-forms.label class="my-3" fieldId="branch" :fieldLabel="__('app.branch')">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <select class="form-control select-picker" name="branch"
                                            id="employee_branch" data-live-search="true">
                                            <option value="">--</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                                            @endforeach
                                        </select>
                                        <x-slot name="append">
                                            <button id="branch-add" type="button"
                                                class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                                        </x-slot>
                                    </x-forms.input-group>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <x-forms.file allowedFileExtensions="png jpg jpeg svg bmp"
                                class="mr-0 mr-lg-2 mr-md-2 cropper"
                                :fieldLabel="__('modules.profile.profilePicture')" fieldName="image" fieldId="image"
                                fieldHeight="119" :popover="__('messages.fileFormat.ImageFile')" />
                        </div>

                        <div class="form-group my-3 col-lg-3 col-md-6">
                            <x-forms.label fieldId="employee_card_id"
                                :fieldLabel="__('modules.employees.employeeCardId')"
                                fieldRequired="true"></x-forms.label>
                            <input type="text" class="form-control height-35 f-14" id="employee_card_id"
                                name="employee_card_id"
                                placeholder="{{ __('placeholders.employeeCardId') }}"
                                inputmode="numeric" maxlength="10"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="employee_ctc"
                                :fieldLabel="__('modules.employees.employeeCtc')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span>
                                </x-slot>
                                <input type="number" step="0.01" min="0" class="form-control height-35 f-14"
                                    name="employee_ctc" id="employee_ctc">
                            </x-forms.input-group>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="basic_for_payroll"
                                :fieldLabel="__('modules.employees.basicForPayroll')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">%</span>
                                </x-slot>
                                <input type="number" step="1" min="0" max="999" class="form-control height-35 f-14"
                                    name="basic_for_payroll" id="basic_for_payroll"
                                    placeholder="Enter the Basic percentage %"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3)">
                            </x-forms.input-group>
                            <small class="form-text text-muted">Whole numbers only, up to 3 digits (max 999).</small>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="pan_number" :fieldLabel="__('PAN Card Number')"
                                fieldName="pan_number" :fieldPlaceholder="__('ABCDE1234F')"
                                fieldRequired="true" required maxlength="10"
                                pattern="[A-Z]{5}[0-9]{4}[A-Z]" autocomplete="off"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="aadhar_card_number"
                                :fieldLabel="__('modules.employees.aadharCardNumber')"
                                fieldName="aadhar_card_number"
                                :fieldPlaceholder="__('modules.employees.aadharCardNumber')" maxlength="12">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="father_or_husband_name"
                                :fieldLabel="__('modules.employees.fatherOrHusbandName')"
                                fieldName="father_or_husband_name"
                                :fieldPlaceholder="__('modules.employees.fatherOrHusbandName')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="qualification"
                                :fieldLabel="__('modules.employees.qualification')"
                                fieldName="qualification"
                                :fieldPlaceholder="__('modules.employees.qualification')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="blood_group"
                                :fieldLabel="__('modules.employees.bloodGroup')" fieldName="blood_group">
                                <option value="">-- {{ __('app.select') }} --</option>
                                @foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
                                    <option value="{{ $bg }}">{{ $bg }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="country" :fieldLabel="__('app.country')"
                                fieldName="country" search="true">
                                @foreach ($countries as $item)
                                    <option data-tokens="{{ $item->iso3 }}"
                                        data-phonecode="{{ $item->phonecode }}"
                                        data-iso="{{ $item->iso }}"
                                        data-content="<span class='flag-icon flag-icon-{{ strtolower($item->iso) }} flag-icon-squared'></span> {{ $item->nicename }}"
                                        value="{{ $item->id }}"
                                        {{ strtoupper($item->iso) == 'IN' ? 'selected' : '' }}>{{ $item->nicename }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="mobile"
                                :fieldLabel="__('app.mobile')"></x-forms.label>
                            <x-forms.input-group style="margin-top:-4px">
                                <x-forms.select fieldId="country_phonecode" fieldName="country_phonecode"
                                    search="true">
                                    @foreach ($countries as $item)
                                        <option data-tokens="{{ $item->name }}"
                                            data-country-iso="{{ $item->iso }}"
                                            data-content="{{ $item->flagSpanCountryCode() }}"
                                            value="{{ $item->phonecode }}"
                                            {{ strtoupper($item->iso) == 'IN' ? 'selected' : '' }}>{{ $item->phonecode }}
                                        </option>
                                    @endforeach
                                </x-forms.select>
                                <input type="tel" class="form-control height-35 f-14"
                                    placeholder="@lang('placeholders.mobile')" name="mobile" id="mobile">
                            </x-forms.input-group>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="gender" :fieldLabel="__('modules.employees.gender')"
                                fieldName="gender">
                                <option value="male">@lang('app.male')</option>
                                <option value="female">@lang('app.female')</option>
                                <option value="others">@lang('app.others')</option>
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="joining_date"
                                :fieldLabel="__('modules.employees.joiningDate')"
                                fieldName="joining_date" :fieldPlaceholder="__('placeholders.date')"
                                fieldRequired="true"
                                :fieldValue="now(company()->timezone)->format(company()->date_format)" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="reporting_to"
                                :fieldLabel="__('modules.employees.reportingTo')"
                                fieldName="reporting_to" search="true">
                                <option value="">--</option>
                                @foreach ($employees as $item)
                                    <x-user-option :user="$item" />
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="locale" :fieldLabel="__('app.language')"
                                fieldName="locale" search="true">
                                @foreach ($languages as $language)
                                    <option {{ user()->locale == $language->language_code ? 'selected' : '' }}
                                        data-content="<span class='flag-icon flag-icon-{{ ($language->flag_code == 'en') ? 'gb' : $language->flag_code }} flag-icon-squared'></span> {{ $language->language_name }}"
                                        value="{{ $language->language_code }}">{{ $language->language_name }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="role" :fieldLabel="__('app.role')" fieldName="role">
                                @foreach ($roles as $role)
                                    <option {{ ($role->name == 'employee') ? 'selected' : '' }}
                                        value="{{ $role->id }}">{{ $role->display_name }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-6">
                            <div class="my-3 form-group">
                                <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2"
                                    fieldLabel="Communication Address" fieldName="address" fieldId="address"
                                    :fieldPlaceholder="__('placeholders.address')">
                                </x-forms.textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="my-3 form-group">
                                <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2"
                                    fieldLabel="Permanent Address" fieldName="permanent_address" fieldId="permanent_address"
                                    :fieldPlaceholder="__('placeholders.address')">
                                </x-forms.textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="my-3 form-group">
                                <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2"
                                    :fieldLabel="__('app.about')" fieldName="about_me"
                                    fieldId="about_me" fieldPlaceholder="">
                                </x-forms.textarea>
                            </div>
                        </div>
                    </div>{{-- /account details row --}}

                    {{-- Emergency Contacts --}}
                    <div class="wizard-section-header mt-4 mb-3">
                        <div class="wizard-section-icon"><i class="fa fa-phone-alt"></i></div>
                        <div>
                            <h5>Emergency Contact Details</h5>
                            <p>Two persons to contact in case of emergency</p>
                        </div>
                    </div>
                    <label class="f-14 text-dark-grey mb-2 d-block">Add one or more persons to contact in case of emergency</label>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="emergencyContactTable">
                            <thead>
                                <tr>
                                    <th style="width:50px">S.No</th>
                                    <th>Name <span class="text-danger">*</span></th>
                                    <th>Relation <span class="text-danger">*</span></th>
                                    <th>Contact Number <span class="text-danger">*</span></th>
                                    <th style="width:50px"></th>
                                </tr>
                            </thead>
                            <tbody id="emergencyContactRows">
                                <tr>
                                    <td class="align-middle text-center ec-serial">1.</td>
                                    <td><input type="text" name="emergency_name[]" class="form-control ec-name" maxlength="100"></td>
                                    <td>
                                        <select name="emergency_relation[]" class="form-control ec-relation">
                                            <option value="">-- Select --</option>
                                            <option value="Spouse">Spouse</option>
                                            <option value="Father">Father</option>
                                            <option value="Mother">Mother</option>
                                            <option value="Brother">Brother</option>
                                            <option value="Sister">Sister</option>
                                            <option value="Friend">Friend</option>
                                            <option value="Colleague">Colleague</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="emergency_number[]" class="form-control ec-number" maxlength="20"></td>
                                    <td class="align-middle text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger ec-remove-row" title="Remove row">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="ecAddRow">
                        <i class="fa fa-plus mr-1"></i> Add Contact
                    </button>

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
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="ifsc_code"
                                :fieldLabel="__('modules.employees.ifscCode')"
                                fieldName="ifsc_code" fieldRequired="true" required
                                :fieldPlaceholder="__('placeholders.ifscCode')"
                                pattern="[A-Z]{4}0[A-Z0-9]{6}" maxlength="11"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11);">
                            </x-forms.text>
                        </div>
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
                            <p>Passport number, issue date, place and expiry</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="passport_number"
                                :fieldLabel="__('modules.employees.passportNumber')"
                                fieldName="passport_number" fieldRequired="true" required maxlength="8"
                                pattern="[A-Z][0-9]{7}"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);"
                                :fieldPlaceholder="__('placeholders.passportNumber')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="passport_date_of_issue"
                                :fieldLabel="__('modules.employees.passportDateOfIssue')"
                                fieldName="passport_date_of_issue" :fieldPlaceholder="__('placeholders.date')"
                                fieldRequired="true" required />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="passport_place_of_issue"
                                :fieldLabel="__('modules.employees.passportPlaceOfIssue')"
                                fieldName="passport_place_of_issue" fieldRequired="true" required maxlength="100"
                                pattern="[A-Za-z,.\-\s]+"
                                oninput="this.value = this.value.replace(/[^A-Za-z,.\-\s]/g, '');"
                                :fieldPlaceholder="__('placeholders.location')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="passport_valid_up_to"
                                :fieldLabel="__('modules.employees.passportValidUpTo')"
                                fieldName="passport_valid_up_to" :fieldPlaceholder="__('placeholders.date')"
                                fieldRequired="true" required />
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
                            <p>Documents received from the employee at joining</p>
                        </div>
                    </div>
                    <div class="row">
                        @foreach ([
                            ['educational_testimonials', 'modules.employees.educationalTestimonials'],
                            ['experience_letter',        'modules.employees.experienceLetter'],
                            ['relieving_letter',         'modules.employees.relievingLetter'],
                            ['two_references',           'modules.employees.twoReferences'],
                            ['salary_certificate',       'modules.employees.salaryCertificate'],
                            ['bank_statement',           'modules.employees.bankStatement'],
                            ['passport_copy',            'modules.employees.passportCopy'],
                            ['form_16',                  'modules.employees.form16'],
                            ['passport_photographs',     'modules.employees.passportPhotographs'],
                            ['offer_letter',             'modules.employees.offerLetter'],
                            ['resume_copy',              'modules.employees.resumeCopy'],
                            ['nda',                      'modules.employees.nda'],
                        ] as [$id, $langKey])
                            <div class="col-lg-6 col-md-6">
                                <div class="my-3 form-group d-flex justify-content-between align-items-center">
                                    <label class="mb-0 f-14 text-dark-grey">@lang($langKey)</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input"
                                            id="{{ $id }}" name="{{ $id }}" value="1">
                                        <label class="custom-control-label" for="{{ $id }}"></label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="4">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="6">
                            Next: Declaration &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 5 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 5 — Final Declaration
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep6">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-file-signature"></i></div>
                        <div>
                            <h5>@lang('modules.employees.finalDeclaration')</h5>
                            <p>Reported duty date and authorized signatures</p>
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
                    </div>

                    <div class="wizard-nav-bar">
                        <button type="button" class="btn btn-outline-secondary wizardPrev" data-to="5">
                            <i class="fa fa-chevron-left"></i>&nbsp; Back
                        </button>
                        <button type="button" class="btn btn-primary wizardNext" data-to="7">
                            Review &amp; Submit &nbsp;<i class="fa fa-chevron-right"></i>
                        </button>
                    </div>

                </div>
            </div>{{-- /step 6 --}}

            {{-- ═══════════════════════════════════════════════════
                 STEP 6 — Review & Submit
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep7">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-check-circle"></i></div>
                        <div>
                            <h5>Review &amp; Submit</h5>
                            <p>Please verify all details before saving the employee</p>
                        </div>
                    </div>

                    <div class="border rounded overflow-hidden" id="wizardReviewContent">
                        {{-- populated by buildWizardReview() --}}
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
        '#passport_date_of_issue', '#passport_valid_up_to'
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

        if (target === 7) { buildWizardReview(); }
    }

    // Next button
    $(document).on('click', '.wizardNext', function () {
        var to   = parseInt($(this).data('to'));
        var ok   = true;

        if (wizardStep === 1) {
            ok = validateAccountDetailsSection() && validateEmergencySection();
        } else if (wizardStep === 2) {
            ok = validateBankSection();
        } else if (wizardStep === 3) {
            ok = validateExperienceSection();
        } else if (wizardStep === 4) {
            ok = validatePassportSection();
        } else if (wizardStep === 5) {
            ok = true; // checklist — no required fields
        } else if (wizardStep === 6) {
            ok = validateFinalDeclarationSection();
        }

        if (ok) { wizardGoTo(to); }
    });

    // Back button
    $(document).on('click', '.wizardPrev', function () {
        wizardGoTo(parseInt($(this).data('to')));
    });

    // Click on a completed step indicator to jump back
    $(document).on('click', '.wizard-step-item.completed', function () {
        wizardGoTo(parseInt($(this).data('step')));
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

        h += '<p class="review-section-title"><i class="fa fa-clipboard-list mr-2"></i>Joining Checklist</p>';
        h += chk('Educational Testimonials', 'educational_testimonials');
        h += chk('Experience Letter',        'experience_letter');
        h += chk('Relieving Letter',         'relieving_letter');
        h += chk('Two References',           'two_references');
        h += chk('Salary Certificate',       'salary_certificate');
        h += chk('Bank Statement',           'bank_statement');
        h += chk('Passport Copy',            'passport_copy');
        h += chk('Form 16',                  'form_16');
        h += chk('Passport Photographs',     'passport_photographs');
        h += chk('Offer Letter',             'offer_letter');
        h += chk('Resume Copy',              'resume_copy');
        h += chk('NDA',                      'nda');

        h += '<p class="review-section-title"><i class="fa fa-file-signature mr-2"></i>Final Declaration</p>';
        var dutyDate = [val('reported_duty_day'), selText('reported_duty_month'), val('reported_duty_year')].filter(Boolean).join(' ');
        h += r('Reported to Duty',     dutyDate);
        h += r('Candidate Signature',  val('candidate_signature'));
        h += r('HR Signature',         val('hr_signature'));
        h += r('HR Comments',          val('hr_comments'));

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
        if (!employeePassword.value || employeePassword.value.length < 6)         { setFieldState(employeePassword, false, 'Password must be at least 6 characters.');         isValid = false; } else { setFieldState(employeePassword, true); }
        if (!designation.value)                                                    { setFieldState(designation, false, 'Designation is required.');                             isValid = false; } else { setFieldState(designation, true); }
        if (!department.value)                                                     { setFieldState(department, false, 'Department is required.');                               isValid = false; } else { setFieldState(department, true); }
        if (!cardId.value || !cardIdRegex.test(cardId.value.trim()))               { setFieldState(cardId, false, 'Employee Card ID may use letters, numbers, hyphens and underscores (max 50).');         isValid = false; } else { setFieldState(cardId, true); }
        if (!panNumber.value.trim() || !panRegex.test(panNumber.value.toUpperCase())) { setFieldState(panNumber, false, 'Enter a valid PAN in format ABCDE1234F.');             isValid = false; } else { panNumber.value = panNumber.value.toUpperCase(); setFieldState(panNumber, true); }
        if (!joiningDate.value)                                                    { setFieldState(joiningDate, false, 'Joining Date is required.');                            isValid = false; } else { setFieldState(joiningDate, true); }

        if (employeeCtc.value && (isNaN(employeeCtc.value) || Number(employeeCtc.value) < 0)) {
            setFieldState(employeeCtc, false, 'CTC must be a valid positive number.'); isValid = false;
        } else { setFieldState(employeeCtc, true); }

        if (basicForPayroll.value && (!Number.isInteger(Number(basicForPayroll.value)) || Number(basicForPayroll.value) < 0 || Number(basicForPayroll.value) > 999)) {
            setFieldState(basicForPayroll, false, 'Basic % must be a whole number 0–999.'); isValid = false;
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

    function validateEmergencySection() {
        var rows    = $('#emergencyContactRows tr');
        var isValid = true;
        rows.each(function (i) {
            var row   = $(this);
            var label = 'Contact ' + (i + 1);
            var name  = row.find('.ec-name')[0];
            var rel   = row.find('.ec-relation')[0];
            var num   = row.find('.ec-number')[0];
            if (!name || !name.value.trim()) { setFieldState(name, false, label + ' Name is required.');     isValid = false; } else { setFieldState(name, true); }
            if (!rel  || !rel.value)         { setFieldState(rel,  false, label + ' Relation is required.'); isValid = false; } else { setFieldState(rel,  true); }
            if (!num  || !num.value.trim())  { setFieldState(num,  false, label + ' Number is required.');   isValid = false; } else { setFieldState(num,  true); }
        });
        return isValid;
    }

    // ── Dynamic Emergency Contact rows ───────────────────────
    var ecRelationOptions =
        '<option value="">-- Select --</option>' +
        '<option value="Spouse">Spouse</option>' +
        '<option value="Father">Father</option>' +
        '<option value="Mother">Mother</option>' +
        '<option value="Brother">Brother</option>' +
        '<option value="Sister">Sister</option>' +
        '<option value="Friend">Friend</option>' +
        '<option value="Colleague">Colleague</option>' +
        '<option value="Other">Other</option>';

    function renumberEcRows() {
        $('#emergencyContactRows tr').each(function (i) {
            $(this).find('.ec-serial').text((i + 1) + '.');
        });
    }

    $('#ecAddRow').on('click', function () {
        var row = '<tr>' +
            '<td class="align-middle text-center ec-serial"></td>' +
            '<td><input type="text" name="emergency_name[]" class="form-control ec-name" maxlength="100"></td>' +
            '<td><select name="emergency_relation[]" class="form-control ec-relation">' + ecRelationOptions + '</select></td>' +
            '<td><input type="text" name="emergency_number[]" class="form-control ec-number" maxlength="20"></td>' +
            '<td class="align-middle text-center"><button type="button" class="btn btn-sm btn-outline-danger ec-remove-row" title="Remove row"><i class="fa fa-trash"></i></button></td>' +
            '</tr>';
        $('#emergencyContactRows').append(row);
        renumberEcRows();
    });

    $(document).on('click', '.ec-remove-row', function () {
        if ($('#emergencyContactRows tr').length <= 1) {
            alert('At least one emergency contact is required.');
            return;
        }
        $(this).closest('tr').remove();
        renumberEcRows();
    });

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

        if (!passNum.value || !numRe.test(passNum.value.toUpperCase()))   { setFieldState(passNum, false, 'Passport No.: one uppercase letter followed by 7 digits (e.g. A1234567).');   isValid = false; } else { passNum.value = passNum.value.toUpperCase(); setFieldState(passNum, true); }
        if (!passIssue.value)                                              { setFieldState(passIssue, false, 'Passport Date of Issue is required.');                                       isValid = false; } else { setFieldState(passIssue, true); }
        if (!passPlace.value || !placeRe.test(passPlace.value.trim()))     { setFieldState(passPlace, false, 'Place of Issue: alphabets, spaces, commas, hyphens and periods only.');     isValid = false; } else { setFieldState(passPlace, true); }
        if (!passValid.value)                                              { setFieldState(passValid, false, 'Passport Valid Up To is required.');                                         isValid = false; } else { setFieldState(passValid, true); }

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


    // ── Save functions ───────────────────────────────────────
    function saveEmployee(data, url, buttonSelector) {
        $.easyAjax({
            url: url,
            container: '#save-employee-data-form',
            type: 'POST',
            disableButton: true,
            blockUI: true,
            buttonSelector: buttonSelector,
            file: true,
            data: data,
            success: function (response) {
                if (response.status === 'success') {
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
            }
        });
    }

    $('#save-employee-form').click(function () {
        var url  = "{{ route('employees.store') }}";
        var data = $('#save-employee-data-form').serialize();
        if (!validateAccountDetailsSection() || !validateEmergencySection()) { wizardGoTo(1); return; }
        if (!validateBankSection())                                           { wizardGoTo(2); return; }
        if (!validateExperienceSection())                                     { wizardGoTo(3); return; }
        if (!validatePassportSection())                                       { wizardGoTo(4); return; }
        if (!validateFinalDeclarationSection())                               { wizardGoTo(6); return; }
        saveEmployee(data, url, '#save-employee-form');
    });

    $('#save-more-employee-form').click(function () {
        $('#add_more').val(true);
        var url  = "{{ route('employees.store') }}";
        var data = $('#save-employee-data-form').serialize();
        if (!validateAccountDetailsSection() || !validateEmergencySection()) { wizardGoTo(1); return; }
        if (!validateBankSection())                                           { wizardGoTo(2); return; }
        if (!validateExperienceSection())                                     { wizardGoTo(3); return; }
        if (!validatePassportSection())                                       { wizardGoTo(4); return; }
        if (!validateFinalDeclarationSection())                               { wizardGoTo(6); return; }
        saveEmployee(data, url, '#save-more-employee-form');
    });

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
