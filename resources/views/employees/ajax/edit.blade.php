@php
$addDesignationPermission = user()->permission('add_designation');
$changeEmployeeRolePermission = user()->permission('change_employee_role');
$addDepartmentPermission = user()->permission('add_department');
$passportDetails = $employee->empPassportDetails;
$joiningChecklist = $employee->empJoiningChecklist;
$finalDeclaration = $finalDeclaration ?? $employee->empFinalDeclaration;
// Most recently configured probation duration — same "latest wins" rule
// CheckProbationCompletion uses — drives the auto-filled Probation End
// Date (Joining Date + this many months) when the admin flips Yes.
$probationMonths = (int) (\Illuminate\Support\Facades\DB::table('probation_period_settings')
    ->where('company_id', company()->id)
    ->orderByDesc('id')
    ->value('months') ?? 0);
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

.tagify_tags .height-35 {
    height: auto !important;
}
</style>

<div class="row">
    <div class="col-sm-12">
        <x-form id="save-data-form" method="PUT">

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
                 STEP 1 — Account Details
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane" id="wizardStep1">
                <div class="add-client bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-id-card"></i></div>
                        <div>
                            <h5>@lang('modules.employees.accountDetails')</h5>
                            <p>Identity, credentials, role and personal information</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-2">
                                    <x-forms.text fieldId="employee_id" :fieldLabel="__('modules.employees.employeeId')"
                                        fieldName="employee_id" :fieldValue="$employee->employeeDetail->employee_id"
                                        fieldRequired="true" :fieldPlaceholder="__('modules.employees.employeeIdInfo')" :popover="__('modules.employees.employeeIdHelp')">
                                    </x-forms.text>
                                </div>
                                <div class="col-md-2">
                                    <x-forms.select fieldId="salutation" fieldName="salutation"
                                        :fieldLabel="__('modules.client.salutation')">
                                        <option value="">--</option>
                                        @foreach ($salutations as $salutation)
                                            <option value="{{ $salutation->value }}" @selected($employee->salutation == $salutation)>{{ $salutation->label() }}</option>
                                        @endforeach
                                    </x-forms.select>
                                </div>
                                <div class="col-md-4">
                                    <x-forms.text fieldId="name" :fieldLabel="__('modules.employees.employeeName')"
                                        fieldName="name" :fieldValue="$employee->name" fieldRequired="true"
                                        :fieldPlaceholder="__('placeholders.name')">
                                    </x-forms.text>
                                </div>
                                <div class="col-md-4">
                                    <x-forms.text fieldId="email" :fieldLabel="__('modules.employees.employeeEmail')"
                                        fieldName="email" fieldRequired="true" :fieldValue="$employee->email"
                                        :fieldPlaceholder="__('placeholders.email')">
                                    </x-forms.text>
                                </div>
                                <div class="col-md-4">
                                    <x-forms.label class="mt-3" fieldId="password"
                                        :fieldLabel="__('app.password')">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <input type="password" name="password" id="password" autocomplete="off"
                                            class="form-control height-35 f-14">
                                        <x-slot name="preappend">
                                            <button type="button" data-toggle="tooltip"
                                                data-original-title="@lang('app.viewPassword')"
                                                class="btn btn-outline-secondary border-grey height-35 toggle-password"><i
                                                    class="fa fa-eye"></i></button>
                                        </x-slot>
                                        <x-slot name="append">
                                            <button id="random_password" type="button" data-toggle="tooltip"
                                                data-original-title="@lang('modules.client.generateRandomPassword')"
                                                class="btn btn-outline-secondary border-grey height-35"><i
                                                    class="fa fa-random"></i></button>
                                        </x-slot>
                                    </x-forms.input-group>
                                    <small class="form-text text-muted">@lang('modules.client.passwordUpdateNote')</small>
                                </div>
                                <div class="col-md-4">
                                    <x-forms.label class="my-3" fieldId="designation"
                                        :fieldLabel="__('app.designation')" fieldRequired="true">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <select class="form-control select-picker" name="designation"
                                            id="employee_designation" data-live-search="true">
                                            <option value="">--</option>
                                            @foreach ($designations as $designation)
                                                <option @if ($employee->employeeDetail->designation_id == $designation->id) selected @endif value="{{ $designation->id }}">
                                                    {{ $designation->name }}</option>
                                            @endforeach
                                        </select>

                                        @if ($addDesignationPermission == 'all')
                                            <x-slot name="append">
                                                <button id="designation-setting-edit" type="button"
                                                    class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                                            </x-slot>
                                        @endif
                                    </x-forms.input-group>
                                </div>
                                <div class="col-md-4">
                                    <x-forms.label class="my-3" fieldId="department"
                                        :fieldLabel="__('app.department')" fieldRequired="true">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <select class="form-control select-picker" name="department"
                                            id="employee_department" data-live-search="true">
                                            <option value="">--</option>
                                            @foreach ($teams as $team)
                                                <option @if ($employee->employeeDetail->department_id == $team->id) selected @endif value="{{ $team->id }}">
                                                    {{ $team->team_name }}</option>
                                            @endforeach
                                        </select>
                                        @if ($addDepartmentPermission == 'all')
                                            <x-slot name="append">
                                                <button id="department-edit" type="button"
                                                    class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                                            </x-slot>
                                        @endif
                                    </x-forms.input-group>
                                </div>
                                <div class="col-md-4">
                                    <x-forms.label class="my-3" fieldId="branch"
                                        :fieldLabel="__('app.branch')">
                                    </x-forms.label>
                                    <x-forms.input-group>
                                        <select class="form-control select-picker" name="branch"
                                            id="employee_branch" data-live-search="true">
                                            <option value="">--</option>
                                            @foreach ($branches as $branch)
                                                <option @if ($employee->employeeDetail->branch_id == $branch->id) selected @endif value="{{ $branch->id }}">
                                                    {{ $branch->branch_name }}</option>
                                            @endforeach
                                        </select>
                                        <x-slot name="append">
                                            <button id="branch-edit" type="button"
                                                class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                                        </x-slot>
                                    </x-forms.input-group>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">

                            <x-forms.file allowedFileExtensions="png jpg jpeg svg bmp" class="mr-0 mr-lg-2 mr-md-2 cropper"
                                :fieldLabel="__('modules.profile.profilePicture')"
                                :fieldValue="($employee->image ? $employee->masked_image_url : $employee->image_url)" fieldName="image"
                                fieldId="image" fieldHeight="119" :popover="__('messages.fileFormat.ImageFile')" />
                        </div>
                        <div class="form-group my-3 col-md-4">
                            <x-forms.label fieldId="employee_card_id" :fieldLabel="__('modules.employees.employeeCardId')"
                                fieldRequired="true"></x-forms.label>
                            <input type="text" class="form-control height-35 f-14" id="employee_card_id"
                                name="employee_card_id" placeholder="{{ __('placeholders.employeeCardId') }}"
                                value="{{ $employee->employeeDetail->employee_card_id ?? '' }}"
                                inputmode="numeric" maxlength="10"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>
                        <div class="col-md-4">
                            <x-forms.label class="my-3" fieldId="employee_ctc" :fieldLabel="__('modules.employees.employeeCtc')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span>
                                </x-slot>
                                <input type="number" step="0.01" min="0" class="form-control height-35 f-14"
                                    name="employee_ctc" id="employee_ctc" value="{{ $employee->employee_ctc ?? '' }}">
                            </x-forms.input-group>
                        </div>
                        <div class="form-group col-md-4">
                            <x-forms.label class="my-3" fieldId="basic_for_payroll" :fieldLabel="__('modules.employees.basicForPayroll')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">%</span>
                                </x-slot>
                                <input type="number" step="1" min="0" max="999" class="form-control height-35 f-14"
                                    name="basic_for_payroll" id="basic_for_payroll" placeholder="Enter the Basic percentage %"
                                    value="{{ $employee->basic_for_payroll ?? '' }}"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3)">
                            </x-forms.input-group>
                            <small class="form-text text-muted">Whole numbers only, up to 3 digits (max 999). You can type the amount or use the increment/decrement arrows.</small>
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="pan_number" :fieldLabel="__('PAN Card Number')" fieldName="pan_number"
                                fieldValue="{{ $employee->pan_number ?? '' }}" :fieldPlaceholder="__('ABCDE1234F')"
                                maxlength="10"
                                pattern="[A-Z]{5}[0-9]{4}[A-Z]"
                                autocomplete="off"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);">
                            </x-forms.text>
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="aadhar_card_number" :fieldLabel="__('modules.employees.aadharCardNumber')"
                                fieldName="aadhar_card_number" :fieldValue="$employee->employeeDetail->aadhar_card_number ?? ''"
                                :fieldPlaceholder="__('modules.employees.aadharCardNumber')" maxlength="12">
                            </x-forms.text>
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="father_or_husband_name" :fieldLabel="__('modules.employees.fatherOrHusbandName')"
                                fieldName="father_or_husband_name" :fieldValue="$employee->employeeDetail->father_or_husband_name ?? ''"
                                :fieldPlaceholder="__('modules.employees.fatherOrHusbandName')">
                            </x-forms.text>
                        </div>
                        <div class="col-md-4">
                            <x-forms.text fieldId="qualification" :fieldLabel="__('modules.employees.qualification')"
                                fieldName="qualification" :fieldValue="$employee->employeeDetail->qualification ?? ''"
                                :fieldPlaceholder="__('modules.employees.qualification')">
                            </x-forms.text>
                        </div>
                        <div class="col-md-4">
                            <x-forms.select fieldId="blood_group" :fieldLabel="__('modules.employees.bloodGroup')" fieldName="blood_group">
                                <option value="">-- {{ __('app.select') }} --</option>
                                <option value="A+" @selected(($employee->employeeDetail->blood_group ?? '') === 'A+')>A+</option>
                                <option value="A-" @selected(($employee->employeeDetail->blood_group ?? '') === 'A-')>A-</option>
                                <option value="B+" @selected(($employee->employeeDetail->blood_group ?? '') === 'B+')>B+</option>
                                <option value="B-" @selected(($employee->employeeDetail->blood_group ?? '') === 'B-')>B-</option>
                                <option value="AB+" @selected(($employee->employeeDetail->blood_group ?? '') === 'AB+')>AB+</option>
                                <option value="AB-" @selected(($employee->employeeDetail->blood_group ?? '') === 'AB-')>AB-</option>
                                <option value="O+" @selected(($employee->employeeDetail->blood_group ?? '') === 'O+')>O+</option>
                                <option value="O-" @selected(($employee->employeeDetail->blood_group ?? '') === 'O-')>O-</option>
                            </x-forms.select>
                        </div>
                        <div class="col-md-4">
                            <x-forms.select fieldId="country" :fieldLabel="__('app.country')" fieldName="country"
                                search="true">
                                <option value="">--</option>
                                @foreach ($countries as $item)
                                    <option @if ($employee->country_id == $item->id) selected @endif data-mobile="{{ $employee->mobile }}" data-tokens="{{ $item->iso3 }}" data-iso="{{ $item->iso }}" data-phonecode="{{ $item->phonecode }}" data-content="<span
                                    class='flag-icon flag-icon-{{ strtolower($item->iso) }} flag-icon-squared'></span>
                                {{ $item->nicename }}" value="{{ $item->id }}">{{ $item->nicename }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-4">
                            <x-forms.label class="my-3" fieldId="mobile"
                                :fieldLabel="__('app.mobile')"></x-forms.label>
                            <x-forms.input-group style="margin-top:-4px">
                                <x-forms.select fieldId="country_phonecode" fieldName="country_phonecode"
                                    search="true">
                                    @foreach ($countries as $item)
                                        <option @selected($employee->country_phonecode == $item->phonecode && !is_null($item->numcode))
                                                data-tokens="{{ $item->name }}" data-country-iso="{{ $item->iso }}"
                                                data-content="{{$item->flagSpanCountryCode()}}"
                                                value="{{ $item->phonecode }}">{{ $item->phonecode }}
                                        </option>
                                    @endforeach
                                </x-forms.select>
                                <input type="tel" class="form-control height-35 f-14" placeholder="@lang('placeholders.mobile')"
                                    name="mobile" id="mobile" value="{{ $employee->mobile }}">
                            </x-forms.input-group>
                        </div>
                        <div class="col-md-4">
                            <x-forms.select fieldId="gender" :fieldLabel="__('modules.employees.gender')"
                                fieldName="gender">
                                <option @if ($employee->gender == 'male') selected @endif value="male">@lang('app.male')</option>
                                <option @if ($employee->gender == 'female') selected @endif value="female">@lang('app.female')</option>
                                <option @if ($employee->gender == 'others') selected @endif value="others">@lang('app.others')</option>
                            </x-forms.select>
                        </div>
                        <div class="col-md-4">
                            <x-forms.datepicker fieldId="joining_date" :fieldLabel="__('modules.employees.joiningDate')"
                                fieldName="joining_date" :fieldPlaceholder="__('placeholders.date')" fieldRequired="true"
                                :fieldValue="$employee->employeeDetail->joining_date->format(company()->date_format)" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.datepicker fieldId="date_of_birth" :fieldLabel="__('modules.employees.dateOfBirth')"
                                fieldName="date_of_birth" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="($employee->employeeDetail->date_of_birth ? $employee->employeeDetail->date_of_birth->format(company()->date_format) : '')" />
                        </div>

                        <div class="col-md-4">
                            <x-forms.select fieldId="reporting_to" :fieldLabel="__('modules.employees.reportingTo')"
                                fieldName="reporting_to" :fieldPlaceholder="__('placeholders.date')" search="true">
                                <option value="">--</option>
                                @foreach ($employees as $item)
                                    <x-user-option :user="$item" :selected="$employee->employeeDetail->reporting_to == $item->id"/>
                                @endforeach
                            </x-forms.select>
                        </div>
                        <div class="col-md-4">
                            <x-forms.select fieldId="locale" :fieldLabel="__('app.language')"
                                fieldName="locale" search="true">
                                @foreach ($languages as $language)
                                    <option @if ($employee->locale == $language->language_code) selected @endif
                                    data-content="<span class='flag-icon flag-icon-{{ ($language->flag_code == 'en') ? 'gb' : $language->flag_code }} flag-icon-squared'></span> {{ $language->language_name }}"
                                    value="{{ $language->language_code }}">{{ $language->language_name }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>

                        @if (
                        ((in_array('admin', $userRoles) && in_array('admin', user_roles()))
                        || (!in_array('admin', $userRoles)))
                        && $employee->id != user()->id
                        && $changeEmployeeRolePermission == 'all'
                        )
                            <div class="col-md-4">
                                <x-forms.select fieldId="role" :fieldLabel="__('app.role')" fieldName="role">
                                    @foreach ($roles as $role)
                                        <option
                                        @if (
                                            (in_array($role->name, $userRoles) && $role->name == 'admin')
                                            || (in_array($role->name, $userRoles) && !in_array('admin', $userRoles))
                                        )
                                         selected
                                        @endif
                                        value="{{ $role->id }}">{{ $role->display_name }}</option>
                                    @endforeach
                                </x-forms.select>
                            </div>
                        @endif

                        <div class="col-md-12">
                            <div class="form-group my-3">
                                <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.address')"
                                    :fieldValue="$employee->employeeDetail->address" fieldName="address" fieldId="address"
                                    :fieldPlaceholder="__('placeholders.address')">
                                </x-forms.textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group my-3">
                                <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.about')"
                                    fieldName="about_me" fieldId="about_me" fieldPlaceholder="" :fieldValue="$employee->employeeDetail->about_me">
                                </x-forms.textarea>
                            </div>
                        </div>

                    </div>

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
                            <x-forms.text fieldId="personal_account_number" :fieldLabel="__('modules.employees.personalAccountNumber')"
                                fieldName="personal_account_number" :fieldValue="$employee->employeeDetail->personal_account_number ?? ''"
                                :fieldPlaceholder="__('placeholders.accountNumber')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="ifsc_code" :fieldLabel="__('modules.employees.ifscCode')"
                                fieldName="ifsc_code" :fieldValue="$employee->employeeDetail->ifsc_code ?? ''"
                                :fieldPlaceholder="__('placeholders.ifscCode')"
                                pattern="[A-Z]{4}0[A-Z0-9]{6}" maxlength="11"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 11);">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="pf_account_number" :fieldLabel="__('modules.employees.pfAccountNumber')"
                                fieldName="pf_account_number" :fieldValue="$employee->employeeDetail->pf_account_number ?? ''"
                                :fieldPlaceholder="__('placeholders.pfAccountNumber')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="esi_number" :fieldLabel="__('modules.employees.esiNumber')"
                                fieldName="esi_number" :fieldValue="$employee->employeeDetail->esi_number ?? ''"
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
                 STEP 3 — Work Experience & Other Details
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
                    <input type="hidden" name="total_experience" id="total_experience" value="{{ $employee->employeeDetail->total_experience ?? '' }}">

                    <div id="experienceCards">
                        {{-- Cards injected by JS from @initialExperiences --}}
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
                            <h5>@lang('modules.client.clientOtherDetails')</h5>
                            <p>Login settings, employment type, rates and more</p>
                        </div>
                    </div>
                    <div class="row">

                        @if ($employee->id != user()->id)
                            <div class="col-md-4">
                                <div class="form-group my-3">
                                    <label class="f-14 text-dark-grey mb-12 w-100"
                                        for="usr">@lang('modules.client.clientCanLogin')</label>
                                    <div class="d-flex">
                                        <x-forms.radio fieldId="login-yes" :fieldLabel="__('app.yes')" fieldName="login"
                                            fieldValue="enable" :checked="($employee->login == 'enable') ? 'checked' : ''">
                                        </x-forms.radio>
                                        <x-forms.radio fieldId="login-no" :fieldLabel="__('app.no')" fieldValue="disable"
                                            fieldName="login" :checked="($employee->login == 'disable') ? 'checked' : ''">
                                        </x-forms.radio>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-4">
                            <div class="form-group my-3">
                                <label class="f-14 text-dark-grey mb-12 w-100"
                                    for="usr">@lang('modules.emailSettings.emailNotifications')</label>
                                <div class="d-flex">
                                    <x-forms.radio fieldId="notification-yes" :fieldLabel="__('app.yes')" fieldValue="1"
                                        fieldName="email_notifications"
                                        :checked="($employee->email_notifications) ? 'checked' : ''">
                                    </x-forms.radio>
                                    <x-forms.radio fieldId="notification-no" :fieldLabel="__('app.no')" fieldValue="0"
                                        fieldName="email_notifications"
                                        :checked="(!$employee->email_notifications) ? 'checked' : ''">
                                    </x-forms.radio>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <x-forms.label class="my-3" fieldId="hourly_rate"
                                :fieldLabel="__('modules.employees.hourlyRate')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span
                                        class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span>
                                </x-slot>

                                <input type="number" step=".01" min="0" class="form-control height-35 f-14"
                                    value="{{ $employee->employeeDetail->hourly_rate ?? '' }}" name="hourly_rate"
                                    id="hourly_rate">
                            </x-forms.input-group>
                        </div>

                        <div class="col-md-4">
                            <x-forms.label class="my-3" fieldId="slack_username"
                                :fieldLabel="__('modules.employees.slackUsername')"></x-forms.label>
                            <x-forms.input-group>
                                <x-slot name="prepend">
                                    <span class="input-group-text f-14 bg-white-shade">@</span>
                                </x-slot>

                                <input type="text" class="form-control height-35 f-14" autocomplete="off"
                                    value="{{ $employee->employeeDetail->slack_username ?? '' }}" name="slack_username"
                                    id="slack_username">
                            </x-forms.input-group>
                        </div>

                        <div class="col-md-12">
                            <x-forms.text class="tagify_tags" fieldId="tags" :fieldLabel="__('app.skills')"
                                fieldName="tags" :fieldPlaceholder="__('placeholders.skills')"
                                :fieldValue="implode(',', $employee->skills())" />
                        </div>

                        @if (function_exists('sms_setting') && sms_setting()->telegram_status)
                            <div class="col-md-6">
                                <x-forms.number fieldName="telegram_user_id" fieldId="telegram_user_id"
                                    fieldLabel="<i class='fab fa-telegram'></i> {{ __('sms::modules.telegramUserId') }}"
                                    :fieldValue="$employee->telegram_user_id" :popover="__('sms::modules.userIdInfo')" />
                                <p class="text-bold text-danger">
                                    @lang('sms::modules.telegramBotNameInfo')
                                </p>
                                <p class="text-bold"><span id="telegram-link-text">https://t.me/{{ sms_setting()->telegram_bot_name }}</span>
                                    <a href="javascript:;" class="btn-copy btn-secondary f-12 rounded p-1 py-2 ml-1"
                                        data-clipboard-target="#telegram-link-text">
                                        <i class="fa fa-copy mx-1"></i>@lang('app.copy')</a>
                                    <a href="https://t.me/{{ sms_setting()->telegram_bot_name }}" target="_blank" class="btn-secondary f-12 rounded p-1 py-2 ml-1">
                                        <i class="fa fa-copy mx-1"></i>@lang('app.openInNewTab')</a>
                                </p>
                            </div>
                        @endif
                        <div class="col-lg-3 col-md-6">
                            <div class="form-group my-3">
                                <label class="f-14 text-dark-grey mb-12 w-100">Probation Period</label>
                                <div class="d-flex">
                                    <x-forms.radio fieldId="probation-period-yes" :fieldLabel="__('app.yes')" fieldValue="1"
                                        fieldName="probation_period" :checked="$employee->employeeDetail->probation_end_date ? 'checked' : ''">
                                    </x-forms.radio>
                                    <x-forms.radio fieldId="probation-period-no" :fieldLabel="__('app.no')" fieldValue="0"
                                        fieldName="probation_period" :checked="!$employee->employeeDetail->probation_end_date ? 'checked' : ''">
                                    </x-forms.radio>
                                </div>
                            </div>
                            <div id="probationEndDateWrap" class="{{ $employee->employeeDetail->probation_end_date ? '' : 'd-none' }}">
                                <x-forms.datepicker fieldId="probation_end_date" :fieldLabel="__('modules.employees.probationEndDate')"
                                    fieldName="probation_end_date" :fieldPlaceholder="__('placeholders.date')"
                                    :fieldValue="$employee->employeeDetail->probation_end_date ? Carbon\Carbon::parse($employee->employeeDetail->probation_end_date)->format(company()->date_format) : '' "
                                    :fieldRequired="$employee->employeeDetail->probation_end_date ? 'true' : 'false'"
                                    :popover="__('messages.probationEndDate')"/>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="notice_period_start_date" :fieldLabel="__('modules.employees.noticePeriodStartDate')"
                                fieldName="notice_period_start_date" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$employee->employeeDetail->notice_period_start_date ? Carbon\Carbon::parse($employee->employeeDetail->notice_period_start_date)->format(company()->date_format) : '' "
                                :popover="__('messages.noticePeriodStartDate')"/>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="notice_period_end_date" :fieldLabel="__('modules.employees.noticePeriodEndDate')"
                                fieldName="notice_period_end_date" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$employee->employeeDetail->notice_period_end_date ? Carbon\Carbon::parse($employee->employeeDetail->notice_period_end_date)->format(company()->date_format) : '' "
                                :popover="__('messages.noticePeriodEndDate')"/>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="employment_type" :fieldLabel="__('modules.employees.employmentType')"
                                fieldName="employment_type" :fieldPlaceholder="__('placeholders.date')">
                                <option value="">--</option>
                                <option value="full_time" @if($employee->employeeDetail->employment_type == 'full_time') selected @endif>@lang('app.fullTime')</option>
                                <option value="part_time" @if($employee->employeeDetail->employment_type == 'part_time') selected @endif>@lang('app.partTime')</option>
                                <option value="on_contract" @if($employee->employeeDetail->employment_type == 'on_contract') selected @endif>@lang('app.onContract')</option>
                                <option value="internship" @if($employee->employeeDetail->employment_type == 'internship') selected @endif>@lang('app.internship')</option>
                                <option value="trainee" @if($employee->employeeDetail->employment_type == 'trainee') selected @endif>@lang('app.trainee')</option>
                            </x-forms.select>
                        </div>

                        <div class="col-lg-3 col-md-6 d-none internship-date">
                            <x-forms.datepicker fieldId="internship_end_date" :fieldLabel="__('modules.employees.internshipEndDate')"
                                fieldName="internship_end_date" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$employee->employeeDetail->internship_end_date ? Carbon\Carbon::parse($employee->employeeDetail->internship_end_date)->format(company()->date_format) : '' "/>
                        </div>

                        <div class="col-lg-3 col-md-6 d-none contract-date">
                            <x-forms.datepicker fieldId="contract_end_date" :fieldLabel="__('modules.employees.contractEndDate')"
                                fieldName="contract_end_date" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$employee->employeeDetail->contract_end_date ? Carbon\Carbon::parse($employee->employeeDetail->contract_end_date)->format(company()->date_format) : '' "/>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="employment_status" fieldLabel="Employee Exit"
                                fieldName="employment_status">
                                <option value="confirmed" @selected(($employee->employeeDetail->employment_status ?? 'confirmed') == 'confirmed')>Confirmed</option>
                                <option value="probation" @selected($employee->employeeDetail->employment_status == 'probation')>Probation Period</option>
                                <option value="notice_period" @selected($employee->employeeDetail->employment_status == 'notice_period')>Notice Period</option>
                                <option value="off_boarded" @selected($employee->employeeDetail->employment_status == 'off_boarded')>Off Board</option>
                                <option value="none" @selected($employee->employeeDetail->employment_status == 'none')>None</option>
                            </x-forms.select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="marital_status" :fieldLabel="__('modules.employees.maritalStatus')"
                                fieldName="marital_status" :fieldPlaceholder="__('placeholders.date')">
                                @foreach (\App\Enums\MaritalStatus::cases() as $status)
                                    <option @selected($employee->employeeDetail->marital_status == $status)
                                        value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.label class="my-3" fieldId="category_id"
                                :fieldLabel="__('app.menu.businessAddresses')" fieldRequired="true">
                            </x-forms.label>
                            <x-forms.input-group>
                                <select class="form-control select-picker" name="company_address"
                                    id="company_address" data-live-search="true">
                                    @foreach ($companyAddresses as $address)
                                        <option @selected($employee->employeeDetail->company_address_id == $address->id)
                                         value="{{ $address->id }}">{{ $address->location }}</option>
                                    @endforeach
                                </select>
                            </x-forms.input-group>
                        </div>

                        <div class="col-lg-3 col-md-6 d-none marriage_date">
                            <x-forms.datepicker fieldId="marriage_anniversary_date" :fieldLabel="__('modules.employees.marriageAnniversaryDate')"
                                fieldName="marriage_anniversary_date" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$employee->employeeDetail->marriage_anniversary_date ? Carbon\Carbon::parse($employee->employeeDetail->marriage_anniversary_date)->format(company()->date_format) : '' " />
                        </div>


                    </div>
                    <div class="row border-top-grey pt-3">
                        {{-- Users cannot change their own status --}}
                        @if ($employee->id != user()->id && $employee->id != 1)
                            <div class="col-md-4">
                                <div class="form-group my-3">
                                    <label class="f-14 text-dark-grey mb-12 w-100" for="usr">@lang('app.status')</label>
                                    <div class="d-flex">
                                        <x-forms.radio fieldId="status-active" :fieldLabel="__('app.active')"
                                                       fieldValue="active" fieldName="status" onclick="checkExitDate()"
                                                       checked="($employee->status == 'active') ? 'checked' : ''">
                                        </x-forms.radio>
                                        <x-forms.radio fieldId="status-inactive" :fieldLabel="__('app.inactive')"
                                                       fieldValue="deactive" fieldName="status"
                                                       :checked="($employee->status == 'deactive') ? 'checked' : ''">
                                        </x-forms.radio>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($employee->id != user()->id)
                            <div @class([
                                'col-md-4',
                                // 'd-none' => $employee->status != 'deactive'
                            ]) id="employeeExitDate">
                                <x-forms.datepicker fieldId="last_date" :fieldLabel="__('modules.employees.lastDate')"
                                                    fieldName="last_date" :fieldPlaceholder="__('placeholders.date')" fieldRequired="true"
                                                    :fieldValue="($employee->employeeDetail->last_date ? $employee->employeeDetail->last_date->format(company()->date_format) : '')"
                                                    :popover="__('messages.lastDateTooltip')" />
                            </div>
                            {{-- Display the message if the conditions are met --}}
                            <x-alert id="exitDateError" class="alert alert-danger ml-3 w-100 mr-3" type="danger" style="display: none;" icon="info-circle">
                                <span class="alert-text"></span>
                            </x-alert>
                        @endif
                    </div>

                    <x-forms.custom-field :fields="$fields" :model="$employeeDetail"></x-forms.custom-field>

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
                 STEP 4 — Passport Details
                 ═══════════════════════════════════════════════════ --}}
            <div class="wizard-pane d-none" id="wizardStep4">
                <div class="bg-white rounded p-20">

                    <div class="wizard-section-header mb-4">
                        <div class="wizard-section-icon"><i class="fa fa-passport"></i></div>
                        <div>
                            <h5>@lang('modules.employees.passportDetails')</h5>
                            <p>Passport number, issue date, place and expiry</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="passport_number" :fieldLabel="__('modules.employees.passportNumber')"
                                fieldName="passport_number" :fieldValue="$passportDetails->passport_number ?? ''"
                                :fieldPlaceholder="__('placeholders.passportNumber')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="passport_date_of_issue" :fieldLabel="__('modules.employees.passportDateOfIssue')"
                                fieldName="passport_date_of_issue" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$passportDetails && $passportDetails->passport_date_of_issue ? $passportDetails->passport_date_of_issue->format(company()->date_format) : ''" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="passport_place_of_issue" :fieldLabel="__('modules.employees.passportPlaceOfIssue')"
                                fieldName="passport_place_of_issue" :fieldValue="$passportDetails->passport_place_of_issue ?? ''"
                                :fieldPlaceholder="__('placeholders.location')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.datepicker fieldId="passport_valid_up_to" :fieldLabel="__('modules.employees.passportValidUpTo')"
                                fieldName="passport_valid_up_to" :fieldPlaceholder="__('placeholders.date')"
                                :fieldValue="$passportDetails && $passportDetails->passport_valid_up_to ? $passportDetails->passport_valid_up_to->format(company()->date_format) : ''" />
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
                 STEP 5 — Joining Checklist
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
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.educationalTestimonials')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="educational_testimonials" name="educational_testimonials" value="1" @checked($joiningChecklist && $joiningChecklist->educational_testimonials)><label class="custom-control-label" for="educational_testimonials"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.experienceLetter')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="experience_letter" name="experience_letter" value="1" @checked($joiningChecklist && $joiningChecklist->experience_letter)><label class="custom-control-label" for="experience_letter"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.relievingLetter')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="relieving_letter" name="relieving_letter" value="1" @checked($joiningChecklist && $joiningChecklist->relieving_letter)><label class="custom-control-label" for="relieving_letter"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.twoReferences')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="two_references" name="two_references" value="1" @checked($joiningChecklist && $joiningChecklist->two_references)><label class="custom-control-label" for="two_references"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.salaryCertificate')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="salary_certificate" name="salary_certificate" value="1" @checked($joiningChecklist && $joiningChecklist->salary_certificate)><label class="custom-control-label" for="salary_certificate"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.bankStatement')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="bank_statement" name="bank_statement" value="1" @checked($joiningChecklist && $joiningChecklist->bank_statement)><label class="custom-control-label" for="bank_statement"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.passportCopy')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="passport_copy" name="passport_copy" value="1" @checked($joiningChecklist && $joiningChecklist->passport_copy)><label class="custom-control-label" for="passport_copy"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.form16')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="form_16" name="form_16" value="1" @checked($joiningChecklist && $joiningChecklist->form_16)><label class="custom-control-label" for="form_16"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.passportPhotographs')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="passport_photographs" name="passport_photographs" value="1" @checked($joiningChecklist && $joiningChecklist->passport_photographs)><label class="custom-control-label" for="passport_photographs"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.offerLetter')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="offer_letter" name="offer_letter" value="1" @checked($joiningChecklist && $joiningChecklist->offer_letter)><label class="custom-control-label" for="offer_letter"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.resumeCopy')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="resume_copy" name="resume_copy" value="1" @checked($joiningChecklist && $joiningChecklist->resume_copy)><label class="custom-control-label" for="resume_copy"></label></div></div></div>
                        <div class="col-lg-6 col-md-6"><div class="my-3 form-group d-flex justify-content-between align-items-center"><label class="mb-0 f-14 text-dark-grey">@lang('modules.employees.nda')</label><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="nda" name="nda" value="1" @checked($joiningChecklist && $joiningChecklist->nda)><label class="custom-control-label" for="nda"></label></div></div></div>
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
                 STEP 6 — Final Declaration
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
                        <div class="col-lg-12 col-md-12">
                            <p class="text-dark-grey f-14 mb-3">@lang('modules.employees.reportedToDutyOn')</p>
                            <div class="row">
                                <div class="col-lg-2 col-md-6">
                                    <x-forms.text fieldId="reported_duty_day" :fieldLabel="__('modules.employees.day')"
                                        fieldName="reported_duty_day" :fieldValue="$finalDeclaration->reported_duty_day ?? ''"
                                        :fieldPlaceholder="__('placeholders.day')">
                                    </x-forms.text>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <x-forms.text fieldId="reported_duty_month" :fieldLabel="__('modules.employees.month')"
                                        fieldName="reported_duty_month" :fieldValue="$finalDeclaration->reported_duty_month ?? ''"
                                        :fieldPlaceholder="__('placeholders.month')">
                                    </x-forms.text>
                                </div>
                                <div class="col-lg-2 col-md-6">
                                    <x-forms.text fieldId="reported_duty_year" :fieldLabel="__('modules.employees.year')"
                                        fieldName="reported_duty_year" :fieldValue="$finalDeclaration->reported_duty_year ?? ''"
                                        :fieldPlaceholder="__('placeholders.year')">
                                    </x-forms.text>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <x-forms.text fieldId="candidate_signature" :fieldLabel="__('modules.employees.signatureOfCandidate')"
                                fieldName="candidate_signature" :fieldValue="$finalDeclaration->candidate_signature ?? ''"
                                :fieldPlaceholder="__('placeholders.signature')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <x-forms.text fieldId="hr_signature" :fieldLabel="__('modules.employees.signatureOfHR')"
                                fieldName="hr_signature" :fieldValue="$finalDeclaration->hr_signature ?? ''"
                                :fieldPlaceholder="__('placeholders.signature')">
                            </x-forms.text>
                        </div>
                        <div class="col-lg-12 col-md-12">
                            <x-forms.textarea fieldId="hr_comments" :fieldLabel="__('modules.employees.hrComments')"
                                fieldName="hr_comments" :fieldValue="$finalDeclaration->hr_comments ?? ''"
                                :fieldPlaceholder="__('placeholders.comments')">
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
                 STEP 7 — Review & Submit
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
                            <x-forms.button-primary id="save-form" class="mr-3" icon="check">@lang('app.save')
                            </x-forms.button-primary>
                            <x-forms.button-cancel :link="route('employees.index')" class="border-0">@lang('app.cancel')
                            </x-forms.button-cancel>
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

    var messages = {
        exitDateErrorForPast: "@lang('messages.exitDateErrorForPast')",
        exitDateErrorForFuture: "@lang('messages.exitDateErrorForFuture')"
    };

    // Function to update the message text
    function updateMessageText(message, date) {
        var messageElement = document.getElementById('exitDateError');
        var messageText = document.querySelector('#exitDateError .alert-text');
        if (messageElement && messageText) {
            messageElement.classList.remove('alert-danger', 'alert-success'); // Clear existing classes
            messageElement.classList.add('alert-danger'); // Add danger class for errors

            // Replace the placeholder with the actual date
            var formattedMessage = message.replace(':date', date);
            messageText.innerHTML = formattedMessage;
        }
    }

    function formatDateToISO(date) {
        var year = date.getFullYear();
        var month = ('0' + (date.getMonth() + 1)).slice(-2); // Month is zero-indexed
        var day = ('0' + date.getDate()).slice(-2);
        return `${year}-${month}-${day}`;
    }

    function checkExitDate() {
        var lastDateInput = document.getElementById('last_date');
        var lastDate = lastDateInput ? lastDateInput.value : ''; // Get the new value from the date picker
        var activeRadioButton = document.getElementById('status-active');
        var isExitDate = "{{ $employee?->employeeDetail?->last_date }}" ? true : false;;
        var exitDateError = document.getElementById('exitDateError')

        if (activeRadioButton && activeRadioButton.checked && lastDate ) {

            $.ajax({
                url: '{{ route('getExitDateMessage') }}', // Your route to handle the request
                method: 'POST',
                data: {
                    last_date: lastDate,
                    is_exit_date: isExitDate,
                    _token: '{{ csrf_token() }}' // CSRF token for Laravel
                },
                success: function(response) {
                    console.log(response);
                    var message = response.message;
                    var showMessage = response.showMessage;

                    if (showMessage) {
                        exitDateError.style.display = 'block'; // Show the error message
                        updateMessageText(message, lastDate);
                    } else {
                        exitDateError.style.display = 'none'; // Hide the error message
                    }
                },
                error: function(xhr) {
                    console.error('Error fetching message:', xhr);
                }
            });

        } else {
            if (exitDateError) {
                exitDateError.style.display = 'none'; // Hide the error message
            }
        }
    }


    // Add event listeners for radio buttons and date picker to call checkExitDate on change
    document.getElementById('status-active').addEventListener('change', checkExitDate);
    document.getElementById('status-inactive').addEventListener('change', checkExitDate);
    document.getElementById('last_date').addEventListener('change', checkExitDate);

    // Initialize the message state on page load
    window.onload = checkExitDate;

</script>

<script>
    $(document).ready(function() {

        checkExitDate();

        $('.custom-date-picker').each(function(ind, el) {
            datepicker(el, {
                position: 'bl',
                ...datepickerConfig
            });
        });

        datepicker('#joining_date', {
            position: 'bl',
            @if (!is_null($employee->employeeDetail->joining_date))
            dateSelected: new Date("{{ str_replace('-', '/', $employee->employeeDetail->joining_date) }}"),
            @endif
            ...datepickerConfig
        });

        datepicker('#date_of_birth', {
            position: 'bl',
            maxDate: new Date(),
            @if (!is_null($employee->employeeDetail->date_of_birth))
            dateSelected: new Date("{{ str_replace('-', '/', $employee->employeeDetail->date_of_birth) }}"),
            @endif
            ...datepickerConfig
        });

        datepicker('#probation_end_date', {
            position: 'bl',
            ...datepickerConfig
        });

        datepicker('#notice_period_start_date', {
            position: 'bl',
            ...datepickerConfig
        });

        datepicker('#notice_period_end_date', {
            position: 'bl',
            ...datepickerConfig
        });

        datepicker('#marriage_anniversary_date', {
            position: 'bl',
            ...datepickerConfig
        });

        datepicker('#internship_end_date', {
            position: 'bl',
            ...datepickerConfig
        });

        datepicker('#contract_end_date', {
            position: 'bl',
            ...datepickerConfig
        });

        var employment_type = $('#employment_type').val();
        if(employment_type == 'internship') {
            $('.internship-date').removeClass('d-none');
        }
        else if(employment_type == 'on_contract') {
            $('.contract-date').removeClass('d-none');
        }

        $('#employment_type').change(function(){
            var value = $(this).val();

            if(value == 'on_contract') {
                $('.contract-date').removeClass('d-none');
                $('#internship_end_date').val('');
            }
            else {
                $('.contract-date').addClass('d-none');
            }

            if(value == 'internship') {
                $('.internship-date').removeClass('d-none');
                $('#contract_end_date').val('');
            }
            else {
                $('.internship-date').addClass('d-none');
            }
        });

        // ── Probation Period Yes/No toggle ──
        var probationMonths = {{ $probationMonths }};

        // Joining Date + configured Probation Period (months) = Probation End
        // Date — same formula CheckProbationCompletion uses server-side.
        function computeProbationEndDate() {
            var joiningInput = document.getElementById('joining_date');

            if (!joiningInput || !joiningInput.value || !probationMonths || typeof moment === 'undefined') {
                return '';
            }

            var joined = moment(joiningInput.value, company.moment_date_format);

            if (!joined.isValid()) {
                return '';
            }

            return joined.add(probationMonths, 'months').format(company.moment_date_format);
        }

        function toggleProbationEndDate(show) {
            var wrap = $('#probationEndDateWrap');
            var dateInput = $('#probation_end_date');
            var label = wrap.find('label[for="probation_end_date"]');

            if (show) {
                wrap.removeClass('d-none');
                if (!label.find('sup').length) {
                    label.append(' <sup class="f-14 mr-1">*</sup>');
                }

                // Only fill it in if the admin hasn't already picked a date —
                // never overwrite a value that's already there.
                if (!dateInput.val()) {
                    var computed = computeProbationEndDate();
                    if (computed) {
                        dateInput.val(computed);
                    }
                }

                // Probation period -> employee is on probation.
                $('#employment_status').val('probation').selectpicker('refresh');
            } else {
                wrap.addClass('d-none');
                dateInput.val('');
                label.find('sup').remove();
                if (typeof clearFieldState === 'function') {
                    clearFieldState(dateInput[0]);
                }

                // No probation period -> employee is confirmed.
                $('#employment_status').val('confirmed').selectpicker('refresh');
            }
        }

        $('input[name="probation_period"]').on('change', function () {
            toggleProbationEndDate($(this).val() === '1');
        });

        var input = document.querySelector('input[name=tags]'),
            // init Tagify script on the above inputs
            tagify = new Tagify(input, {
                whitelist: {!! json_encode($skills) !!},
            });

        var marital_status = $('#marital_status').val();
        if(marital_status == '{{ \App\Enums\MaritalStatus::Married->value }}') {
            $('.marriage_date').removeClass('d-none');
        }

        $('#marital_status').change(function(){
            var value = $(this).val();
            if(value == '{{ \App\Enums\MaritalStatus::Married->value }}') {
                $('.marriage_date').removeClass('d-none');
            }
            else {
                $('.marriage_date').addClass('d-none');
            }
        })

        // ── Dynamic Experience Cards (ported from employees/ajax/create.blade.php) ──
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

        function addExpCard(prefill) {
            var card = $(buildExpCard(expCardCount));
            $('#experienceCards').append(card);
            if (prefill) {
                card.find('.exp-company').val(prefill.company || '');
                card.find('.exp-location').val(prefill.location || '');
                card.find('.exp-designation').val(prefill.designation || '');
                card.find('.exp-joining-date').val(prefill.joining || '');
                card.find('.exp-leaving-date').val(prefill.leaving || '');
            }
            initExpCardDp(card);
            updateExpCardDuration(card);
            expCardCount++;
        }

        $('#expAddCard').on('click', function() { addExpCard(); });

        $(document).on('click', '.exp-remove-btn', function() {
            $(this).closest('.experience-card').remove();
            renumberExpCards();
            calcTotalExperience();
        });

        // Pre-populate cards from the employee's existing experience records
        var initialExperiences = {!! json_encode($initialExperiences) !!};
        initialExperiences.forEach(function(exp) { addExpCard(exp); });
        // Only recompute when there's a card to compute from — otherwise leave the
        // server-rendered legacy total_experience value (set with no company on
        // record) alone instead of blanking it out.
        if (initialExperiences.length > 0) { calcTotalExperience(); }

        // -------------------------------------------------------------------
        // Field-level validation (ported from employees/ajax/create.blade.php
        // so the edit form gives the same inline feedback as create, instead
        // of only finding out about bad input after a server round trip).
        // -------------------------------------------------------------------

        // For a select-picker (bootstrap-select), the real <select> is hidden and
        // the visible control is the generated ".bootstrap-select" wrapper, so the
        // red/green border has to be applied there instead of on the hidden element.
        function getFieldBorderTarget(field) {
            if (field.tagName === 'SELECT') {
                const selectWrapper = field.closest('.bootstrap-select');

                if (selectWrapper) {
                    return selectWrapper;
                }
            }

            return field;
        }

        // Fields inside an .input-group (prepend/append buttons, select-picker + "Add"
        // button, etc.) need the message placed after the whole row, otherwise it gets
        // squeezed in between the row's flex children.
        function getFieldMessageAnchor(field) {
            return field.closest('.input-group') || field.closest('.bootstrap-select') || field;
        }

        function clearFieldState(field) {
            const errorElement = document.getElementById(field.id + '-error');

            field.classList.remove('is-valid', 'is-invalid');
            getFieldBorderTarget(field).classList.remove('is-valid', 'is-invalid');

            if (errorElement) {
                errorElement.remove();
            }
        }

        function setFieldState(field, isValid, message = '') {
            clearFieldState(field);

            const borderTarget = getFieldBorderTarget(field);

            if (isValid) {
                borderTarget.classList.add('is-valid');
                return;
            }

            borderTarget.classList.add('is-invalid');

            const errorElement = document.createElement('div');
            errorElement.id = field.id + '-error';
            errorElement.className = 'invalid-feedback d-block';
            errorElement.textContent = message;
            getFieldMessageAnchor(field).insertAdjacentElement('afterend', errorElement);
        }

        function validateAccountDetailsSection() {
            const employeeId = document.getElementById('employee_id');
            const employeeName = document.getElementById('name');
            const employeeEmail = document.getElementById('email');
            const employeePassword = document.getElementById('password');
            const employeeDesignation = document.getElementById('employee_designation');
            const employeeDepartment = document.getElementById('employee_department');
            const employeeCardId = document.getElementById('employee_card_id');
            const employeeCtc = document.getElementById('employee_ctc');
            const basicForPayroll = document.getElementById('basic_for_payroll');
            const panNumber = document.getElementById('pan_number');
            const joiningDate = document.getElementById('joining_date');
            const dateOfBirth = document.getElementById('date_of_birth');

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const employeeCardIdRegex = /^[A-Za-z0-9_-]{1,50}$/;
            const panNumberRegex = /^[A-Z]{5}[0-9]{4}[A-Z]$/;
            let isValid = true;

            if (!employeeId.value.trim()) {
                setFieldState(employeeId, false, 'Employee ID is required.');
                isValid = false;
            }
            else {
                setFieldState(employeeId, true);
            }

            if (!employeeName.value.trim()) {
                setFieldState(employeeName, false, 'Employee Name is required.');
                isValid = false;
            }
            else {
                setFieldState(employeeName, true);
            }

            if (!employeeEmail.value.trim() || !emailRegex.test(employeeEmail.value.trim())) {
                setFieldState(employeeEmail, false, 'Please enter a valid email address.');
                isValid = false;
            }
            else {
                setFieldState(employeeEmail, true);
            }

            // Password is optional on edit — only validated when the admin is
            // actually setting a new one (leaving it blank keeps the current one).
            if (employeePassword.value && employeePassword.value.length < 8) {
                setFieldState(employeePassword, false, 'Password must be at least 8 characters.');
                isValid = false;
            }
            else {
                setFieldState(employeePassword, true);
            }

            if (!employeeDesignation.value) {
                setFieldState(employeeDesignation, false, 'Designation is required.');
                isValid = false;
            }
            else {
                setFieldState(employeeDesignation, true);
            }

            if (!employeeDepartment.value) {
                setFieldState(employeeDepartment, false, 'Department is required.');
                isValid = false;
            }
            else {
                setFieldState(employeeDepartment, true);
            }

            if (!employeeCardId.value || !employeeCardIdRegex.test(employeeCardId.value.trim())) {
                setFieldState(employeeCardId, false, 'Employee Card ID is required (letters, numbers, hyphens and underscores, max 50).');
                isValid = false;
            }
            else {
                setFieldState(employeeCardId, true);
            }

            if (!panNumber.value.trim() || !panNumberRegex.test(panNumber.value.trim().toUpperCase())) {
                setFieldState(panNumber, false, 'Please enter a valid PAN Card Number in the format ABCDE1234F.');
                isValid = false;
            }
            else {
                panNumber.value = panNumber.value.trim().toUpperCase();
                setFieldState(panNumber, true);
            }

            if (!joiningDate.value) {
                setFieldState(joiningDate, false, 'Joining Date is required.');
                isValid = false;
            }
            else {
                setFieldState(joiningDate, true);
            }

            if (employeeCtc.value && (isNaN(employeeCtc.value) || Number(employeeCtc.value) < 0)) {
                setFieldState(employeeCtc, false, 'Employee CTC must be a valid positive number.');
                isValid = false;
            }
            else {
                setFieldState(employeeCtc, true);
            }

            if (basicForPayroll.value && (isNaN(basicForPayroll.value) || !Number.isInteger(Number(basicForPayroll.value)) || Number(basicForPayroll.value) < 0 || Number(basicForPayroll.value) > 999)) {
                setFieldState(basicForPayroll, false, 'Basic (For Payroll) must be a whole number between 0 and 999.');
                isValid = false;
            }
            else {
                setFieldState(basicForPayroll, true);
            }

            if (dateOfBirth.value && typeof moment !== 'undefined') {
                const parseFormats = [];

                if (typeof company !== 'undefined' && company.moment_date_format) {
                    parseFormats.push(company.moment_date_format);
                }

                parseFormats.push('YYYY-MM-DD', 'DD-MM-YYYY', 'MM-DD-YYYY', 'DD/MM/YYYY', 'MM/DD/YYYY');

                const dob = moment(dateOfBirth.value, parseFormats, true);

                if (dob.isValid() && dob.isAfter(moment(), 'day')) {
                    setFieldState(dateOfBirth, false, 'Date of Birth cannot be a future date.');
                    isValid = false;
                }
                else {
                    setFieldState(dateOfBirth, true);
                }
            }
            else {
                setFieldState(dateOfBirth, true);
            }

            return isValid;
        }

        function validateExperienceSection() {
            // Work experience cards are optional (freshers have none) — only the
            // statutory/bank fields on this step have anything to validate.
            const pfAccountNumber = document.getElementById('pf_account_number');
            const esiNumber = document.getElementById('esi_number');
            const personalAccountNumber = document.getElementById('personal_account_number');
            const ifscCode = document.getElementById('ifsc_code');

            const pfAccountRegex = /^[A-Za-z0-9\/\-]+$/;
            const esiRegex = /^[0-9]+$/;
            const personalAccountRegex = /^[0-9]{9,18}$/;
            const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
            let isValid = true;

            if (pfAccountNumber.value && !pfAccountRegex.test(pfAccountNumber.value.trim())) {
                setFieldState(pfAccountNumber, false, 'PF Account Number can only contain letters, numbers, / and -.');
                isValid = false;
            }
            else {
                setFieldState(pfAccountNumber, true);
            }

            if (esiNumber.value && !esiRegex.test(esiNumber.value.trim())) {
                setFieldState(esiNumber, false, 'ESI (IP Number) must contain numbers only.');
                isValid = false;
            }
            else {
                setFieldState(esiNumber, true);
            }

            if (personalAccountNumber.value && !personalAccountRegex.test(personalAccountNumber.value.trim())) {
                setFieldState(personalAccountNumber, false, 'Personal Account Number must be 9 to 18 digits.');
                isValid = false;
            }
            else {
                setFieldState(personalAccountNumber, true);
            }

            if (ifscCode.value && !ifscRegex.test(ifscCode.value.trim().toUpperCase())) {
                setFieldState(ifscCode, false, 'IFSC Code must follow format SBIN0001234.');
                isValid = false;
            }
            else {
                setFieldState(ifscCode, true);
            }

            const probationPeriodYes = document.getElementById('probation-period-yes');
            const probationEndDate = document.getElementById('probation_end_date');

            if (probationPeriodYes && probationPeriodYes.checked) {
                if (!probationEndDate.value.trim()) {
                    setFieldState(probationEndDate, false, 'Probation End Date is required when Probation Period is Yes.');
                    isValid = false;
                }
                else {
                    setFieldState(probationEndDate, true);
                }
            }
            else if (probationEndDate) {
                clearFieldState(probationEndDate);
            }

            return isValid;
        }

        function validatePassportSection() {
            const passportNumber = document.getElementById('passport_number');
            const passportDateOfIssue = document.getElementById('passport_date_of_issue');
            const passportPlaceOfIssue = document.getElementById('passport_place_of_issue');
            const passportValidUpTo = document.getElementById('passport_valid_up_to');

            const passportNumberRegex = /^[A-Z][0-9]{7}$/;
            const passportPlaceRegex = /^[A-Za-z,.\- ]+$/;
            let isValid = true;

            const parseFormats = [];

            if (typeof company !== 'undefined' && company.moment_date_format) {
                parseFormats.push(company.moment_date_format);
            }

            parseFormats.push('YYYY-MM-DD', 'DD-MM-YYYY', 'MM-DD-YYYY', 'DD/MM/YYYY', 'MM/DD/YYYY');

            const passportNumberValue = passportNumber.value.trim().toUpperCase();
            const passportPlaceValue = passportPlaceOfIssue.value.trim();
            const issueDateValue = passportDateOfIssue.value.trim();
            const validUpToValue = passportValidUpTo.value.trim();

            if (passportNumberValue && !passportNumberRegex.test(passportNumberValue)) {
                setFieldState(passportNumber, false, 'Passport Number must be one uppercase letter followed by 7 digits (e.g. A1234567).');
                isValid = false;
            }
            else {
                passportNumber.value = passportNumberValue;
                setFieldState(passportNumber, true);
            }

            setFieldState(passportDateOfIssue, true);

            if (passportPlaceValue && !passportPlaceRegex.test(passportPlaceValue)) {
                setFieldState(passportPlaceOfIssue, false, 'Passport Place of Issue can only contain alphabets, spaces, commas, hyphens and periods.');
                isValid = false;
            }
            else {
                setFieldState(passportPlaceOfIssue, true);
            }

            setFieldState(passportValidUpTo, true);

            if (issueDateValue && validUpToValue && typeof moment !== 'undefined') {
                const issueDate = moment(issueDateValue, parseFormats, true);
                const validDate = moment(validUpToValue, parseFormats, true);

                if (issueDate.isValid() && validDate.isValid() && !validDate.isAfter(issueDate, 'day')) {
                    setFieldState(passportValidUpTo, false, 'Passport Valid Up To must be greater than Passport Date of Issue.');
                    isValid = false;
                }
            }

            return isValid;
        }

        function validateFinalDeclarationSection() {
            const reportedDutyDay = document.getElementById('reported_duty_day');
            const reportedDutyMonth = document.getElementById('reported_duty_month');
            const reportedDutyYear = document.getElementById('reported_duty_year');
            const candidateSignature = document.getElementById('candidate_signature');
            const hrSignature = document.getElementById('hr_signature');
            const hrComments = document.getElementById('hr_comments');

            const monthRegex = /^(?:[1-9]|1[0-2]|january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sep|sept|october|oct|november|nov|december|dec)$/i;
            const signatureRegex = /^[A-Za-z ]+$/;
            const commentsRegex = /^[A-Za-z0-9 ,.\-()\r\n]*$/;
            let isValid = true;

            const dayValue = reportedDutyDay.value.trim();
            const monthValue = reportedDutyMonth.value.trim();
            const yearValue = reportedDutyYear.value.trim();
            const candidateSignatureValue = candidateSignature.value.trim();
            const hrSignatureValue = hrSignature.value.trim();
            const hrCommentsValue = hrComments.value.trim();

            if (dayValue && (!/^[0-9]+$/.test(dayValue) || Number(dayValue) < 1 || Number(dayValue) > 31)) {
                setFieldState(reportedDutyDay, false, 'Day must be between 1 and 31.');
                isValid = false;
            }
            else {
                setFieldState(reportedDutyDay, true);
            }

            if (monthValue && !monthRegex.test(monthValue)) {
                setFieldState(reportedDutyMonth, false, 'Month must be a valid month name (January to December) or month number (1 to 12).');
                isValid = false;
            }
            else {
                setFieldState(reportedDutyMonth, true);
            }

            if (yearValue && (!/^[0-9]{4}$/.test(yearValue) || Number(yearValue) < 1900 || Number(yearValue) > 2100)) {
                setFieldState(reportedDutyYear, false, 'Year must be a valid 4-digit year.');
                isValid = false;
            }
            else {
                setFieldState(reportedDutyYear, true);
            }

            if (candidateSignatureValue && !signatureRegex.test(candidateSignatureValue)) {
                setFieldState(candidateSignature, false, 'Signature of the Candidate can only contain alphabets and spaces.');
                isValid = false;
            }
            else {
                setFieldState(candidateSignature, true);
            }

            if (hrSignatureValue && !signatureRegex.test(hrSignatureValue)) {
                setFieldState(hrSignature, false, 'Signature of the HR Representative can only contain alphabets and spaces.');
                isValid = false;
            }
            else {
                setFieldState(hrSignature, true);
            }

            if (hrCommentsValue && (!commentsRegex.test(hrCommentsValue) || hrCommentsValue.length > 500)) {
                setFieldState(hrComments, false, 'HR Comments can only contain alphabets, numbers, spaces, commas, periods, hyphens, and parentheses (max 500 characters).');
                isValid = false;
            }
            else {
                setFieldState(hrComments, true);
            }

            return isValid;
        }

        [
            'employee_id',
            'name',
            'email',
            'password',
            'employee_designation',
            'employee_department',
            'employee_card_id',
            'employee_ctc',
            'basic_for_payroll',
            'pan_number',
            'joining_date',
            'date_of_birth'
        ].forEach(function(fieldId) {
            const field = document.getElementById(fieldId);

            if (!field) {
                return;
            }

            const eventName = (fieldId === 'employee_designation' || fieldId === 'employee_department')
                ? 'change'
                : (fieldId.includes('date') ? 'change' : 'input');

            field.addEventListener(eventName, function() {
                validateAccountDetailsSection();
            });

            field.addEventListener('blur', function() {
                validateAccountDetailsSection();
            });
        });

        ['passport_number', 'passport_date_of_issue', 'passport_place_of_issue', 'passport_valid_up_to'].forEach(function(fieldId) {
            const field = document.getElementById(fieldId);

            if (!field) {
                return;
            }

            const eventName = fieldId.includes('date') || fieldId.includes('valid_up_to') ? 'change' : 'input';

            field.addEventListener(eventName, function() {
                validatePassportSection();
            });

            field.addEventListener('blur', function() {
                validatePassportSection();
            });
        });

        [
            'reported_duty_day',
            'reported_duty_month',
            'reported_duty_year',
            'candidate_signature',
            'hr_signature',
            'hr_comments'
        ].forEach(function(fieldId) {
            const field = document.getElementById(fieldId);

            if (!field) {
                return;
            }

            const eventName = (fieldId === 'reported_duty_day' || fieldId === 'reported_duty_month') ? 'change' : 'input';

            field.addEventListener(eventName, function() {
                validateFinalDeclarationSection();
            });

            field.addEventListener('blur', function() {
                validateFinalDeclarationSection();
            });
        });

        [
            'pf_account_number',
            'esi_number',
            'personal_account_number',
            'ifsc_code'
        ].forEach(function(fieldId) {
            const field = document.getElementById(fieldId);

            if (!field) {
                return;
            }

            field.addEventListener('input', function() {
                validateExperienceSection();
            });

            field.addEventListener('blur', function() {
                validateExperienceSection();
            });
        });

        $('#save-form').click(function() {
            if (!validateAccountDetailsSection()) {
                return;
            }

            if (!validateExperienceSection()) {
                return;
            }

            if (!validatePassportSection()) {
                return;
            }

            if (!validateFinalDeclarationSection()) {
                return;
            }

            const url = "{{ route('employees.update', $employee->id) }}";

            $.easyAjax({
                url: url,
                container: '#save-data-form',
                type: "POST",
                disableButton: true,
                blockUI: true,
                buttonSelector: "#save-form",
                file: true,
                data: $('#save-data-form').serialize(),
                success: function(response) {
                    if (response.status == 'success') {
                        window.location.href = response.redirectUrl;
                    }
                }
            });
        });


        $('#random_password').click(function() {
            const randPassword = Math.random().toString(36).substr(2, 8);

            $('#password').val(randPassword);
        });

        $('#designation-setting-edit').click(function() {
            const url = "{{ route('designations.create') }}?model=true";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        })

        $('#department-edit').click(function() {
            const url = "{{ route('departments.create') }}?model=true";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#branch-edit').click(function() {
            const url = "{{ route('branches.create') }}?model=true";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        // ══════════════════════════════════════════════════════════
        //  WIZARD NAVIGATION (mirrors employees/ajax/create.blade.php,
        //  adapted to this page's own field IDs and validation functions)
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
            var to = parseInt($(this).data('to'));
            var ok = true;

            if (wizardStep === 1) {
                ok = validateAccountDetailsSection();
            } else if (wizardStep === 2) {
                ok = validateExperienceSection();
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
            function radioLabel(name, map) {
                var el = document.querySelector('input[name="' + name + '"]:checked');
                if (!el) return '';
                return (map && map[el.value] !== undefined) ? map[el.value] : el.value;
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
            h += r('Employee ID',          val('employee_id'));
            h += r('Name',                 val('name'));
            h += r('Email',                val('email'));
            h += r('Designation',          selText('employee_designation'));
            h += r('Department',           selText('employee_department'));
            h += r('Branch',               selText('employee_branch'));
            h += r('Employee Card ID',     val('employee_card_id'));
            h += r('CTC',                  val('employee_ctc'));
            h += r('Basic (For Payroll)',  val('basic_for_payroll'));
            h += r('PAN Number',           val('pan_number'));
            h += r('Aadhar Number',        val('aadhar_card_number'));
            h += r('Father/Husband Name',  val('father_or_husband_name'));
            h += r('Qualification',        val('qualification'));
            h += r('Blood Group',          selText('blood_group'));
            h += r('Country',              selText('country'));
            h += r('Mobile',               val('mobile'));
            h += r('Gender',               selText('gender'));
            h += r('Joining Date',         val('joining_date'));
            h += r('Date of Birth',        val('date_of_birth'));
            h += r('Reporting To',         selText('reporting_to'));
            h += r('Language',             selText('locale'));
            h += r('Role',                 selText('role'));
            h += r('Address',              val('address'));
            h += r('About',                val('about_me'));

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

            h += '<p class="review-section-title"><i class="fa fa-cog mr-2"></i>Other Details</p>';
            h += r('Login Enabled',        radioLabel('login', {enable: 'Yes', disable: 'No'}));
            h += r('Email Notifications',  radioLabel('email_notifications', {'1': 'Yes', '0': 'No'}));
            h += r('Hourly Rate',          val('hourly_rate'));
            h += r('Slack Username',       val('slack_username'));
            h += r('Probation End Date',   val('probation_end_date'));
            h += r('Notice Period Start',  val('notice_period_start_date'));
            h += r('Notice Period End',    val('notice_period_end_date'));
            h += r('Employment Type',      selText('employment_type'));
            h += r('Employee Exit',        selText('employment_status'));
            h += r('Marital Status',       selText('marital_status'));
            h += r('Business Address',     selText('company_address'));
            h += r('Status',               radioLabel('status', {active: 'Active', deactive: 'Inactive'}));
            h += r('Last Working Date',    val('last_date'));

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
            var dutyDate = [val('reported_duty_day'), val('reported_duty_month'), val('reported_duty_year')].filter(Boolean).join(' ');
            h += r('Reported to Duty',     dutyDate);
            h += r('Candidate Signature',  val('candidate_signature'));
            h += r('HR Signature',         val('hr_signature'));
            h += r('HR Comments',          val('hr_comments'));

            $('#wizardReviewContent').html(h);
        }

        if ($('#last_date').length > 0) {
            datepicker('#last_date', {
                position: 'bl',
                @if ($employee->employeeDetail->last_date)
                    dateSelected: new Date("{{ str_replace('-', '/', $employee->employeeDetail->last_date) }}"),
                @endif
                ...datepickerConfig,
                onSelect: function(dateText) {
                    checkExitDate();
                }
            });
        }

        function updatePhoneCode() {
            var selectedCountry = $('#country').find(':selected');
            var phonecode = selectedCountry.data('phonecode');
            var iso = selectedCountry.data('iso');

            $('#country_phonecode').find('option').each(function() {
                if ($(this).data('country-iso') === iso) {
                    $(this).val(phonecode);
                    $(this).prop('selected', true); // Set the option as selected
                }
            });
        }
        updatePhoneCode();

        $('#country').change(function(){
            updatePhoneCode();
            $('.select-picker').selectpicker('refresh');
        });
        <x-forms.custom-field-filejs/>

        init(RIGHT_MODAL);
    });

    $('.cropper').on('dropify.fileReady', function(e) {
            var inputId = $(this).find('input').attr('id');
            var url = "{{ route('cropper', ':element') }}";
            url = url.replace(':element', inputId);
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        @if (function_exists('sms_setting') && sms_setting()->telegram_status)
        var clipboard = new ClipboardJS('.btn-copy');

        clipboard.on('success', function(e) {
            Swal.fire({
                icon: 'success',
                text: '@lang("app.urlCopied")',
                toast: true,
                position: 'top-end',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                customClass: {
                    confirmButton: 'btn btn-primary',
                },
                showClass: {
                    popup: 'swal2-noanimation',
                    backdrop: 'swal2-noanimation'
                },
            })
        });
    @endif
</script>
