{{-- Step 1 of the employee wizard - one form for Add and Edit, so the two can
     never drift apart again. $e is the employee on Edit, null on Add; every
     other variable comes from the including view (designations, teams,
     branches, countries, employees, languages, roles, permissions).

     Layout: three columns beside the photo, four columns below, then the
     addresses, About and the emergency contacts. --}}
@php
    $isEdit = (bool) $e;
    $detail = $e?->employeeDetail;
    $dateFormat = company()->date_format;

    // Names: the stored parts, or the single name split for an employee saved
    // before first / middle / last existed.
    $nameParts = preg_split('/\s+/', trim((string) ($e->name ?? '')), -1, PREG_SPLIT_NO_EMPTY);
    $firstName = $e ? ($e->first_name ?: ($nameParts[0] ?? '')) : '';
    $middleName = $e ? ($e->middle_name ?? '') : '';
    $lastName = $e ? ($e->last_name ?: (count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '')) : '';

    $contacts = $e
        ? \App\Models\EmployeeRelation::where('user_id', $e->id)->orderBy('serial_no')->get(['name', 'relation', 'contact_number'])
        : collect();
    if ($contacts->isEmpty()) {
        $contacts = collect([(object) ['name' => '', 'relation' => '', 'contact_number' => '']]);
    }
    $relations = ['Spouse', 'Father', 'Mother', 'Brother', 'Sister', 'Friend', 'Colleague', 'Other'];

    $canChangeRole = !$isEdit || (
        ((in_array('admin', $userRoles ?? []) && in_array('admin', user_roles())) || !in_array('admin', $userRoles ?? []))
        && $e->id != user()->id && ($changeEmployeeRolePermission ?? null) == 'all'
    );
@endphp

<div class="row">
    <div class="col-lg-9">
        <div class="row">
            <div class="col-md-4 col-sm-6">
                <x-forms.text fieldId="employee_id" :fieldLabel="__('modules.employees.employeeId')" fieldName="employee_id"
                    :fieldValue="$isEdit ? $detail->employee_id : ((!$checkifExistEmployeeId) ? ($lastEmployeeID + 1) : '')"
                    fieldRequired="true" :fieldPlaceholder="__('modules.employees.employeeIdInfo')"
                    :popover="__('modules.employees.employeeIdHelp')">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-6">
                <x-forms.select fieldId="salutation" fieldName="salutation" :fieldLabel="__('modules.client.salutation')">
                    <option value="">--</option>
                    @foreach ($salutations as $salutation)
                        <option value="{{ $salutation->value }}" @selected($isEdit && $e->salutation == $salutation)>{{ $salutation->label() }}</option>
                    @endforeach
                </x-forms.select>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.text fieldId="employee_card_id" fieldName="employee_card_id"
                    :fieldLabel="__('modules.employees.employeeCardId')" fieldRequired="true"
                    :fieldValue="$detail->employee_card_id ?? ''"
                    :fieldPlaceholder="__('placeholders.employeeCardId')" maxlength="50"
                    oninput="this.value = this.value.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 50)">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.text fieldId="first_name" fieldLabel="First Name" fieldName="first_name" fieldRequired="true"
                    :fieldValue="$firstName" fieldPlaceholder="First Name" maxlength="50">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.text fieldId="middle_name" fieldLabel="Middle Name" fieldName="middle_name"
                    :fieldValue="$middleName" fieldPlaceholder="Middle Name (Optional)" maxlength="50">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.text fieldId="last_name" fieldLabel="Last Name" fieldName="last_name" fieldRequired="true"
                    :fieldValue="$lastName" fieldPlaceholder="Last Name" maxlength="50">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.text fieldId="email" :fieldLabel="__('modules.employees.employeeEmail')" fieldName="email"
                    fieldRequired="true" :fieldValue="$e->email ?? ''" :fieldPlaceholder="__('placeholders.email')">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.text fieldId="personal_email" fieldLabel="Personal Email" fieldName="personal_email" fieldType="email"
                    :fieldValue="$e->personal_email ?? ''" fieldPlaceholder="Personal Email (Optional)">
                </x-forms.text>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.datepicker fieldId="date_of_birth" :fieldLabel="__('modules.employees.dateOfBirth')" fieldRequired="true"
                    fieldName="date_of_birth" :fieldPlaceholder="__('placeholders.date')"
                    :fieldValue="$detail?->date_of_birth ? $detail->date_of_birth->format($dateFormat) : ''" />
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.label class="mt-3" fieldId="password" :fieldLabel="__('app.password')"
                    :fieldRequired="$isEdit ? 'false' : 'true'">
                </x-forms.label>
                <x-forms.input-group>
                    <input type="password" name="password" id="password" autocomplete="new-password"
                        class="form-control height-35 f-14">
                    <x-slot name="preappend">
                        <button type="button" data-toggle="tooltip" data-original-title="@lang('app.viewPassword')"
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
                <small class="form-text text-muted">
                    {{ $isEdit ? __('modules.client.passwordUpdateNote') : 'Auto-generated from first 4 letters of name + date of birth. You can change it.' }}
                </small>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.label class="my-3" fieldId="employee_designation" :fieldLabel="__('app.designation')" fieldRequired="true">
                </x-forms.label>
                <x-forms.input-group>
                    <select class="form-control select-picker" name="designation" id="employee_designation" data-live-search="true">
                        <option value="">--</option>
                        @foreach ($designations as $designation)
                            <option value="{{ $designation->id }}" @selected($detail && $detail->designation_id == $designation->id)>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                    @if ($addDesignationPermission == 'all')
                        <x-slot name="append">
                            <button id="{{ $isEdit ? 'designation-setting-edit' : 'designation-setting-add' }}" type="button"
                                class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                        </x-slot>
                    @endif
                </x-forms.input-group>
            </div>
            <div class="col-md-4 col-sm-12">
                <x-forms.label class="my-3" fieldId="employee_department" :fieldLabel="__('app.department')" fieldRequired="true">
                </x-forms.label>
                <x-forms.input-group>
                    <select class="form-control select-picker" name="department" id="employee_department" data-live-search="true">
                        <option value="">--</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected($detail && $detail->department_id == $team->id)>{{ $team->team_name }}</option>
                        @endforeach
                    </select>
                    @if ($addDepartmentPermission == 'all')
                        <x-slot name="append">
                            <button id="{{ $isEdit ? 'department-edit' : 'department-add' }}" type="button"
                                class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
                        </x-slot>
                    @endif
                </x-forms.input-group>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <x-forms.file allowedFileExtensions="png jpg jpeg svg bmp" class="mr-0 mr-lg-2 mr-md-2 cropper"
            :fieldLabel="__('modules.profile.profilePicture')" fieldName="image" fieldId="image" fieldHeight="119"
            :fieldValue="$isEdit ? ($e->image ? $e->masked_image_url : $e->image_url) : null"
            :popover="__('messages.fileFormat.ImageFile')" />
    </div>

    {{-- four columns below the photo --}}
    <div class="col-lg-3 col-md-6">
        <x-forms.label class="my-3" fieldId="employee_branch" :fieldLabel="__('app.branch')"></x-forms.label>
        <x-forms.input-group>
            <select class="form-control select-picker" name="branch" id="employee_branch" data-live-search="true">
                <option value="">--</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($detail && $detail->branch_id == $branch->id)>{{ $branch->branch_name }}</option>
                @endforeach
            </select>
            <x-slot name="append">
                <button id="{{ $isEdit ? 'branch-edit' : 'branch-add' }}" type="button"
                    class="btn btn-outline-secondary border-grey">@lang('app.add')</button>
            </x-slot>
        </x-forms.input-group>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.label class="my-3" fieldId="employee_ctc" fieldLabel="Employee CTC (per annum)"></x-forms.label>
        <x-forms.input-group>
            <x-slot name="prepend">
                <span class="input-group-text f-14 bg-white-shade">{{ company()->currency->currency_symbol }}</span>
            </x-slot>
            <input type="number" step="0.01" min="0" class="form-control height-35 f-14" name="employee_ctc" id="employee_ctc"
                value="{{ $e->employee_ctc ?? '' }}" placeholder="Annual CTC">
        </x-forms.input-group>
    </div>
    @include('employees.ajax.partials.onboarding-monthly', ['monthlySalary' => $e->monthly_salary ?? null])
    <div class="col-lg-3 col-md-6">
        <x-forms.label class="my-3" fieldId="basic_for_payroll" :fieldLabel="__('modules.employees.basicForPayroll')"></x-forms.label>
        <x-forms.input-group>
            <x-slot name="prepend">
                <span class="input-group-text f-14 bg-white-shade">%</span>
            </x-slot>
            <input type="number" step="1" min="0" max="100" class="form-control height-35 f-14" name="basic_for_payroll"
                id="basic_for_payroll" placeholder="Enter the Basic percentage %" value="{{ $e->basic_for_payroll ?? '' }}"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3)">
        </x-forms.input-group>
        <small class="form-text text-muted">Whole number, 0–100. Empty uses the payroll default.</small>
    </div>

    <div class="col-lg-3 col-md-6">
        <x-forms.text fieldId="pan_number" :fieldLabel="__('PAN Card Number')" fieldName="pan_number"
            :fieldValue="$e->pan_number ?? ''" :fieldPlaceholder="__('ABCDE1234F')" :fieldRequired="$isEdit ? 'false' : 'true'"
            maxlength="10" pattern="[A-Z]{5}[0-9]{4}[A-Z]" autocomplete="off"
            oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);">
        </x-forms.text>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.text fieldId="aadhar_card_number" :fieldLabel="__('modules.employees.aadharCardNumber')" fieldName="aadhar_card_number"
            :fieldValue="$detail->aadhar_card_number ?? ''" :fieldPlaceholder="__('modules.employees.aadharCardNumber')" maxlength="12">
        </x-forms.text>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.text fieldId="father_or_husband_name" :fieldLabel="__('modules.employees.fatherOrHusbandName')"
            fieldName="father_or_husband_name" :fieldValue="$detail->father_or_husband_name ?? ''"
            :fieldPlaceholder="__('modules.employees.fatherOrHusbandName')">
        </x-forms.text>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.text fieldId="qualification" :fieldLabel="__('modules.employees.qualification')" fieldName="qualification"
            :fieldValue="$detail->qualification ?? ''" :fieldPlaceholder="__('modules.employees.qualification')">
        </x-forms.text>
    </div>

    <div class="col-lg-3 col-md-6">
        <x-forms.select fieldId="blood_group" :fieldLabel="__('modules.employees.bloodGroup')" fieldName="blood_group">
            <option value="">-- {{ __('app.select') }} --</option>
            @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                <option value="{{ $bg }}" @selected(($detail->blood_group ?? '') === $bg)>{{ $bg }}</option>
            @endforeach
        </x-forms.select>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.select fieldId="country" :fieldLabel="__('app.country')" fieldName="country" search="true">
            <option value="">--</option>
            @foreach ($countries as $item)
                <option data-tokens="{{ $item->iso3 }}" data-phonecode="{{ $item->phonecode }}" data-iso="{{ $item->iso }}"
                    data-content="<span class='flag-icon flag-icon-{{ strtolower($item->iso) }} flag-icon-squared'></span> {{ $item->nicename }}"
                    value="{{ $item->id }}"
                    @selected($isEdit ? $e->country_id == $item->id : strtoupper($item->iso) == 'IN')>{{ $item->nicename }}</option>
            @endforeach
        </x-forms.select>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.label class="my-3" fieldId="mobile" :fieldLabel="__('app.mobile')"></x-forms.label>
        <x-forms.input-group>
            <x-forms.select fieldId="country_phonecode" fieldName="country_phonecode" search="true">
                @foreach ($countries as $item)
                    <option data-tokens="{{ $item->name }}" data-country-iso="{{ $item->iso }}"
                        data-content="{{ $item->flagSpanCountryCode() }}" value="{{ $item->phonecode }}"
                        @selected($isEdit && $e->country_phonecode
                            ? ($e->country_phonecode == $item->phonecode && !is_null($item->numcode))
                            : strtoupper($item->iso) == 'IN')>{{ $item->phonecode }}</option>
                @endforeach
            </x-forms.select>
            <input type="tel" class="form-control height-35 f-14" placeholder="@lang('placeholders.mobile')" name="mobile" id="mobile"
                value="{{ $e->mobile ?? '' }}">
        </x-forms.input-group>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.select fieldId="gender" :fieldLabel="__('modules.employees.gender')" fieldName="gender">
            @foreach (['male' => __('app.male'), 'female' => __('app.female'), 'others' => __('app.others')] as $value => $label)
                <option value="{{ $value }}" @selected(($e->gender ?? 'male') === $value)>{{ $label }}</option>
            @endforeach
        </x-forms.select>
    </div>

    <div class="col-lg-3 col-md-6">
        <x-forms.datepicker fieldId="joining_date" :fieldLabel="__('modules.employees.joiningDate')" fieldName="joining_date"
            :fieldPlaceholder="__('placeholders.date')" fieldRequired="true"
            :fieldValue="$detail?->joining_date ? $detail->joining_date->format($dateFormat) : now(company()->timezone)->format($dateFormat)" />
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.select fieldId="reporting_to" :fieldLabel="__('modules.employees.reportingTo')" fieldName="reporting_to" search="true">
            <option value="">--</option>
            @foreach ($employees as $item)
                <x-user-option :user="$item" :selected="$detail && $detail->reporting_to == $item->id" />
            @endforeach
        </x-forms.select>
    </div>
    <div class="col-lg-3 col-md-6">
        <x-forms.select fieldId="locale" :fieldLabel="__('app.language')" fieldName="locale" search="true">
            @foreach ($languages as $language)
                <option value="{{ $language->language_code }}" @selected(($e->locale ?? user()->locale) == $language->language_code)
                    data-content="<span class='flag-icon flag-icon-{{ ($language->flag_code == 'en') ? 'gb' : $language->flag_code }} flag-icon-squared'></span> {{ $language->language_name }}">{{ $language->language_name }}</option>
            @endforeach
        </x-forms.select>
    </div>
    <div class="col-lg-3 col-md-6">
        @if ($canChangeRole)
            <x-forms.select fieldId="role" :fieldLabel="__('app.role')" fieldName="role">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($isEdit
                        ? ((in_array($role->name, $userRoles) && $role->name == 'admin') || (in_array($role->name, $userRoles) && !in_array('admin', $userRoles)))
                        : $role->name == 'employee')>{{ $role->display_name }}</option>
                @endforeach
            </x-forms.select>
        @else
            {{-- Shown, not changeable: own account, or no permission to change roles --}}
            <x-forms.label class="my-3" fieldId="role_readonly" :fieldLabel="__('app.role')"></x-forms.label>
            <input type="text" class="form-control height-35 f-14 bg-light" id="role_readonly" readonly tabindex="-1"
                value="{{ $roles->whereIn('name', $userRoles ?? [])->where('name', '<>', 'employee')->pluck('display_name')->first() ?? 'Employee' }}">
        @endif
    </div>

    {{-- The employee's own shift --}}
    @include('employees.ajax.partials.shift-assignment', ['shiftEmployee' => $e])
    <div class="w-100"></div>{{-- the addresses start their own row --}}

    <div class="col-md-6">
        <div class="my-3 form-group">
            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" fieldLabel="Communication Address" fieldName="address" fieldId="address"
                :fieldValue="$detail->address ?? ''" :fieldPlaceholder="__('placeholders.address')">
            </x-forms.textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="my-3 form-group">
            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" fieldLabel="Permanent Address" fieldName="permanent_address"
                fieldId="permanent_address" :fieldValue="$detail->permanent_address ?? ''" :fieldPlaceholder="__('placeholders.address')">
            </x-forms.textarea>
        </div>
    </div>
    <div class="col-md-12">
        <div class="my-3 form-group">
            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.about')" fieldName="about_me" fieldId="about_me"
                fieldPlaceholder="" :fieldValue="$detail->about_me ?? ''">
            </x-forms.textarea>
        </div>
    </div>
</div>

{{-- Emergency contacts --}}
<div class="wizard-section-header mt-4 mb-3">
    <div class="wizard-section-icon"><i class="fa fa-phone-alt"></i></div>
    <div>
        <h5>Emergency Contact Details</h5>
        <p>People to contact in case of emergency</p>
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
            @foreach ($contacts as $i => $contact)
                <tr>
                    <td class="align-middle text-center ec-serial">{{ $i + 1 }}.</td>
                    <td><input type="text" name="emergency_name[]" class="form-control ec-name" maxlength="100" value="{{ $contact->name }}"></td>
                    <td>
                        <select name="emergency_relation[]" class="form-control ec-relation" data-no-search>
                            <option value="">-- Select --</option>
                            @foreach ($relations as $relation)
                                <option value="{{ $relation }}" @selected($contact->relation === $relation)>{{ $relation }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="text" name="emergency_number[]" class="form-control ec-number" maxlength="20" value="{{ $contact->contact_number }}"></td>
                    <td class="align-middle text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger ec-remove-row" title="Remove row"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="ecAddRow">
    <i class="fa fa-plus mr-1"></i> Add Contact
</button>
