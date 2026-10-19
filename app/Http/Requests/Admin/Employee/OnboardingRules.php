<?php

namespace App\Http\Requests\Admin\Employee;

use App\Services\Bank\IfscLookup;
use App\Services\Employee\OnboardingDocuments;

/**
 * Rules shared by Add and Edit Employee for the onboarding additions: the
 * documents, the bank account checks and the confirm-account field.
 */
trait OnboardingRules
{
    /**
     * @param  bool         $bankRequired    Add Employee requires bank details; Edit validates them when given
     * @param  string|null  $currentAccount  the account number on file (Edit): re-entering it is only
     *                                       asked for when it is being changed
     * @return array<string, mixed>
     */
    protected function onboardingRules(bool $bankRequired, ?string $currentAccount = null): array
    {
        $presence = $bankRequired ? 'required' : 'nullable';
        $changing = (string) $this->input('personal_account_number') !== (string) $currentAccount;
        $confirm = ($bankRequired || ($changing && $this->filled('personal_account_number')))
            ? ['required', 'same:personal_account_number'] : ['nullable'];

        return OnboardingDocuments::fileRules() + [
            'personal_account_number' => [$presence, 'regex:/^[0-9]{9,18}$/', function ($attribute, $value, $fail) {
                if ($value && ($problem = IfscLookup::accountNumberProblem($value))) {
                    $fail($problem);
                }
            }],
            'personal_account_number_confirmation' => $confirm,
            'ifsc_code' => [$presence, 'regex:' . IfscLookup::PATTERN],
            'monthly_salary' => 'nullable|numeric|min:0',
            'employee_shift_id' => 'nullable|integer|exists:employee_shifts,id',
            'shift_effective_from' => 'nullable|date_format:"' . company()->date_format . '"',
        ];
    }

    /** @return array<string, string> */
    protected function onboardingMessages(): array
    {
        return [
            'personal_account_number_confirmation.same' => 'The re-entered account number does not match.',
            'personal_account_number_confirmation.required' => 'Re-enter the bank account number to confirm it.',
            'checklist_files.*.*.mimes' => 'Checklist documents must be PDF, JPG or PNG.',
            'checklist_files.*.*.max' => 'Each checklist document can be at most 5 MB.',
            'declaration_candidate_copy.mimes' => 'The declaration copy must be a PDF, JPG or PNG.',
            'declaration_hr_copy.mimes' => 'The declaration copy must be a PDF, JPG or PNG.',
            'resume_file.mimes' => 'The resume must be a PDF.',
        ];
    }

    /**
     * After the field rules: documents the ticked checklist items and the
     * declaration need, and whether the IFSC is a real branch.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userId = $this->route('employee') ? (int) $this->route('employee') : null;

            foreach (OnboardingDocuments::missing($this, $userId) as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            $ifsc = strtoupper((string) $this->input('ifsc_code'));

            if ($ifsc !== '' && !$validator->errors()->has('ifsc_code')) {
                $result = app(IfscLookup::class)->lookup($ifsc);

                // An unreachable directory does not stop the save; an unknown code does.
                if ($result['status'] === 'invalid') {
                    $validator->errors()->add('ifsc_code', $result['message']);
                }
            }
        });
    }
}
