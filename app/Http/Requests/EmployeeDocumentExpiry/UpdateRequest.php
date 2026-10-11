<?php

namespace App\Http\Requests\EmployeeDocumentExpiry;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dateFormat = company()->date_format;
        
        return [
            'document_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'issue_date' => 'required|date_format:' . $dateFormat,
            'expiry_date' => 'required|date_format:' . $dateFormat . '|after:issue_date',
            'alert_before_days' => 'required|integer|min:1|max:365',
            'alert_enabled' => 'required|boolean',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,rtf,png,jpg,jpeg,gif,svg|max:10240'
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'document_name.required' => __('modules.employees.documentNameRequired'),
            'document_name.string' => __('modules.employees.documentNameString'),
            'document_name.max' => __('modules.employees.documentNameMax'),
            'document_number.string' => __('modules.employees.documentNumberString'),
            'document_number.max' => __('modules.employees.documentNumberMax'),
            'issue_date.required' => __('modules.employees.issueDateRequired'),
            'issue_date.date' => __('modules.employees.issueDateDate'),
            'expiry_date.required' => __('modules.employees.expiryDateRequired'),
            'expiry_date.date' => __('modules.employees.expiryDateDate'),
            'expiry_date.after' => __('modules.employees.expiryDateAfter'),
            'alert_before_days.required' => __('modules.employees.alertBeforeDaysRequired'),
            'alert_before_days.integer' => __('modules.employees.alertBeforeDaysInteger'),
            'alert_before_days.min' => __('modules.employees.alertBeforeDaysMin'),
            'alert_before_days.max' => __('modules.employees.alertBeforeDaysMax'),
            'alert_enabled.required' => __('modules.employees.alertEnabledRequired'),
            'alert_enabled.boolean' => __('modules.employees.alertEnabledBoolean'),
            'file.file' => __('modules.employees.fileFile'),
            'file.mimes' => __('modules.employees.fileMimes'),
            'file.max' => __('modules.employees.fileMax'),
        ];
    }
}
