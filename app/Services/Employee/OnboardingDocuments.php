<?php

namespace App\Services\Employee;

use App\Helper\Files;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The files collected when an employee joins: the resume, a document behind
 * each joining-checklist item, and the declaration signed by the employee and
 * by HR.
 *
 * Every file goes into employee_docs (stored under
 * public/user-uploads/employee-docs/{user id}/) with a category saying which
 * part of onboarding it belongs to, so the employee's Documents tab lists
 * them alongside anything uploaded later.
 *
 * The rules:
 *  - a checklist item that is ticked must have at least one document;
 *  - both declaration copies must be on file before the employee is saved.
 */
class OnboardingDocuments
{
    public const CATEGORY_RESUME = 'resume';
    public const CATEGORY_CHECKLIST = 'checklist';
    public const CATEGORY_DECLARATION_CANDIDATE = 'declaration_candidate';
    public const CATEGORY_DECLARATION_HR = 'declaration_hr';

    public const MIMES = 'pdf,jpg,jpeg,png';
    public const MAX_KB = 5120;

    /** The joining checklist, in the order the form shows it. */
    public const CHECKLIST = [
        'educational_testimonials' => 'Educational Testimonials',
        'experience_letter' => 'Experience Letter',
        'relieving_letter' => 'Relieving Letter',
        'two_references' => 'Two References',
        'salary_certificate' => 'Salary Certificate',
        'bank_statement' => 'Bank Statement',
        'passport_copy' => 'Passport Copy',
        'form_16' => 'Form 16',
        'passport_photographs' => 'Passport Photographs',
        'offer_letter' => 'Offer Letter',
        'resume_copy' => 'Resume Copy',
        'nda' => 'NDA',
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_RESUME => 'Resume',
        self::CATEGORY_CHECKLIST => 'Joining checklist',
        self::CATEGORY_DECLARATION_CANDIDATE => 'Declaration - signed by employee',
        self::CATEGORY_DECLARATION_HR => 'Declaration - signed by HR',
    ];

    /**
     * Validation rules for the files on the form.
     *
     * @return array<string, mixed>
     */
    public static function fileRules(): array
    {
        $file = 'file|mimes:' . self::MIMES . '|max:' . self::MAX_KB;

        return [
            'resume_file' => 'nullable|file|mimes:pdf|max:' . self::MAX_KB,
            'checklist_files' => 'nullable|array',
            'checklist_files.*' => 'nullable|array|max:10',
            'checklist_files.*.*' => $file,
            'declaration_candidate_copy' => 'nullable|' . $file,
            'declaration_hr_copy' => 'nullable|' . $file,
            'remove_document_ids' => 'nullable|array',
            'remove_document_ids.*' => 'integer',
        ];
    }

    /**
     * The checks that depend on what is already on file: a ticked checklist
     * item needs a document, and both declaration copies are needed.
     *
     * @return array<string, string>  field => message
     */
    public static function missing(Request $request, ?int $userId = null): array
    {
        $kept = $userId ? self::forUser($userId)->reject(
            fn ($doc) => in_array($doc->id, array_map('intval', (array) $request->input('remove_document_ids', [])), true)
        ) : collect();

        $errors = [];

        foreach (self::CHECKLIST as $key => $label) {
            if (!$request->boolean($key)) {
                continue;
            }

            $uploaded = array_filter((array) ($request->file('checklist_files')[$key] ?? []));
            $onFile = $kept->where('category', self::CATEGORY_CHECKLIST)->where('checklist_key', $key)->isNotEmpty();

            if (!$uploaded && !$onFile) {
                $errors['checklist_files.' . $key] = $label . ' is ticked - upload the document for it, or untick it.';
            }
        }

        foreach ([
            'declaration_candidate_copy' => [self::CATEGORY_DECLARATION_CANDIDATE, 'Upload the declaration signed by the employee.'],
            'declaration_hr_copy' => [self::CATEGORY_DECLARATION_HR, 'Upload the declaration signed by HR.'],
        ] as $field => [$category, $message]) {
            if (!$request->hasFile($field) && $kept->where('category', $category)->isEmpty()) {
                $errors[$field] = $message;
            }
        }

        return $errors;
    }

    /**
     * Store everything the form sent for this employee, and remove what was
     * marked for removal.
     *
     * @return array<int, string>  stored hash names, so a failed save can remove them again
     */
    public function saveFromRequest(Request $request, int $userId, ?int $companyId): array
    {
        $stored = [];

        foreach (array_map('intval', (array) $request->input('remove_document_ids', [])) as $id) {
            // Only this employment's documents can be removed from the form.
            $doc = self::forUser($userId)->firstWhere('id', $id);

            if ($doc) {
                Files::deleteFile($doc->hashname, EmployeeDocument::FILE_PATH . '/' . $userId);
                $doc->delete();
            }
        }

        if ($request->hasFile('resume_file')) {
            // One resume on file: a new one replaces the old.
            $this->removeCategory($userId, self::CATEGORY_RESUME);
            $stored[] = $this->store($request->file('resume_file'), $userId, $companyId, self::CATEGORY_RESUME, null, 'Resume');
        }

        foreach ((array) $request->file('checklist_files', []) as $key => $files) {
            if (!isset(self::CHECKLIST[$key])) {
                continue;
            }

            foreach (array_filter((array) $files) as $file) {
                $stored[] = $this->store($file, $userId, $companyId, self::CATEGORY_CHECKLIST, $key, 'Checklist - ' . self::CHECKLIST[$key]);
            }
        }

        foreach ([
            'declaration_candidate_copy' => [self::CATEGORY_DECLARATION_CANDIDATE, 'Declaration (signed by employee)'],
            'declaration_hr_copy' => [self::CATEGORY_DECLARATION_HR, 'Declaration (signed by HR)'],
        ] as $field => [$category, $name]) {
            if ($request->hasFile($field)) {
                $this->removeCategory($userId, $category);
                $stored[] = $this->store($request->file($field), $userId, $companyId, $category, null, $name);
            }
        }

        return $stored;
    }

    /** Remove files written during a save that then failed. */
    public function discard(int $userId, array $hashnames): void
    {
        foreach ($hashnames as $hashname) {
            Files::deleteFile($hashname, EmployeeDocument::FILE_PATH . '/' . $userId);
        }
    }

    /**
     * The onboarding documents of the employee's current employment. Someone
     * who left and rejoined hands in a new set; the earlier set stays on record
     * under its own employment (see overview()['previous']).
     */
    public static function forUser(int $userId): Collection
    {
        $period = self::currentPeriodId($userId);

        return EmployeeDocument::where('user_id', $userId)->whereNotNull('category')
            ->where(fn ($q) => $q->where('employment_period_id', $period)->orWhereNull('employment_period_id'))
            ->orderBy('id')->get();
    }

    /** The employment the documents being handed in now belong to. */
    public static function currentPeriodId(int $userId): ?int
    {
        return (int) app(\App\Services\Employment\EmploymentPeriods::class)->ensure($userId)->id ?: null;
    }

    /**
     * Everything on file for an employee, grouped for the review screens:
     * resume, each checklist item, both declaration copies, then other documents.
     *
     * @return array<string, mixed>
     */
    public static function overview(int $userId): array
    {
        $period = self::currentPeriodId($userId);
        $everything = EmployeeDocument::where('user_id', $userId)->orderBy('id')->get();
        $all = $everything->filter(fn ($d) => !$d->category || !$d->employment_period_id || (int) $d->employment_period_id === $period);

        // Joining documents from earlier employments, by employment
        $previous = $everything->filter(fn ($d) => $d->category && $d->employment_period_id && (int) $d->employment_period_id !== $period)
            ->groupBy('employment_period_id')
            ->map(function ($docs, $periodId) {
                $row = DB::table('employment_periods')->find($periodId);

                return ['period' => $row, 'files' => $docs->values()];
            })->values();

        return [
            'previous' => $previous,
            'resume' => $all->where('category', self::CATEGORY_RESUME)->values(),
            'checklist' => collect(self::CHECKLIST)->map(fn ($label, $key) => [
                'label' => $label,
                'files' => $all->where('category', self::CATEGORY_CHECKLIST)->where('checklist_key', $key)->values(),
            ]),
            'declaration_candidate' => $all->where('category', self::CATEGORY_DECLARATION_CANDIDATE)->values(),
            'declaration_hr' => $all->where('category', self::CATEGORY_DECLARATION_HR)->values(),
            'other' => $all->whereNull('category')->values(),
        ];
    }

    protected function store(UploadedFile $file, int $userId, ?int $companyId, string $category, ?string $key, string $name): string
    {
        $hashname = Files::uploadLocalOrS3($file, EmployeeDocument::FILE_PATH . '/' . $userId);

        $doc = new EmployeeDocument();
        $doc->company_id = $companyId;
        $doc->user_id = $userId;
        $doc->name = $name;
        $doc->category = $category;
        $doc->checklist_key = $key;
        $doc->employment_period_id = self::currentPeriodId($userId);
        $doc->filename = $file->getClientOriginalName();
        $doc->hashname = $hashname;
        $doc->size = $file->getSize();
        $doc->mime = $file->getMimeType();
        $doc->added_by = user()?->id;
        $doc->last_updated_by = user()?->id;
        $doc->save();

        return $hashname;
    }

    protected function removeCategory(int $userId, string $category): void
    {
        // Only this employment's copy is replaced; an earlier employment's stays on record.
        $period = self::currentPeriodId($userId);

        foreach (EmployeeDocument::where('user_id', $userId)->where('category', $category)
            ->where(fn ($q) => $q->where('employment_period_id', $period)->orWhereNull('employment_period_id'))->get() as $doc) {
            Files::deleteFile($doc->hashname, EmployeeDocument::FILE_PATH . '/' . $userId);
            $doc->delete();
        }
    }
}
