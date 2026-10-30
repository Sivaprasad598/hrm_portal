<?php

namespace App\Services\Employee;

use App\Models\EmployeeDocument;

/**
 * The kinds of employee document, for the type chosen on upload and the tabs
 * on the employee's Documents page.
 *
 * A file uploaded before types existed has none on record; its type is read
 * from the onboarding category it was filed under (the resume, a joining
 * checklist item, the declaration), and anything else is "Other".
 */
class DocumentTypes
{
    /** type => label, in the order the tabs and the drop-down show them. */
    const TYPES = [
        'aadhaar' => 'Aadhaar',
        'pan' => 'PAN',
        'ssc' => 'SSC / 10th',
        'intermediate' => 'Intermediate / 12th',
        'degree' => 'Degree',
        'post_graduation' => 'Post Graduation',
        'resume' => 'Resume',
        'offer_letter' => 'Offer Letter',
        'experience_letter' => 'Experience Letter',
        'relieving_letter' => 'Relieving Letter',
        'payslips' => 'Previous Payslips',
        'salary_certificate' => 'Salary Certificate',
        'form_16' => 'Form 16',
        'bank' => 'Bank Proof',
        'passport' => 'Passport',
        'photo' => 'Photograph',
        'references' => 'References',
        'nda' => 'NDA',
        'declaration' => 'Declaration',
        'other' => 'Other',
    ];

    /** Joining checklist item => type. */
    const FROM_CHECKLIST = [
        'educational_testimonials' => 'degree',
        'experience_letter' => 'experience_letter',
        'relieving_letter' => 'relieving_letter',
        'two_references' => 'references',
        'salary_certificate' => 'salary_certificate',
        'bank_statement' => 'bank',
        'passport_copy' => 'passport',
        'form_16' => 'form_16',
        'passport_photographs' => 'photo',
        'offer_letter' => 'offer_letter',
        'resume_copy' => 'resume',
        'nda' => 'nda',
    ];

    public static function of(EmployeeDocument $doc): string
    {
        if ($doc->doc_type && isset(self::TYPES[$doc->doc_type])) {
            return $doc->doc_type;
        }

        return match ($doc->category) {
            OnboardingDocuments::CATEGORY_RESUME => 'resume',
            OnboardingDocuments::CATEGORY_CHECKLIST => self::FROM_CHECKLIST[$doc->checklist_key] ?? 'other',
            OnboardingDocuments::CATEGORY_DECLARATION_CANDIDATE, OnboardingDocuments::CATEGORY_DECLARATION_HR => 'declaration',
            default => 'other',
        };
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? self::TYPES['other'];
    }

    /** A type from the form, or null when it is not one of ours. */
    public static function clean(?string $type): ?string
    {
        return $type !== null && isset(self::TYPES[$type]) ? $type : null;
    }

    /** The employee's most recent resume, or null. */
    public static function latestResume(int $userId): ?EmployeeDocument
    {
        return EmployeeDocument::where('user_id', $userId)
            ->where(fn ($q) => $q->where('doc_type', 'resume')
                ->orWhere(fn ($q) => $q->whereNull('doc_type')->where('category', OnboardingDocuments::CATEGORY_RESUME))
                ->orWhere(fn ($q) => $q->whereNull('doc_type')->where('checklist_key', 'resume_copy')))
            ->orderByDesc('created_at')
            ->first();
    }
}
