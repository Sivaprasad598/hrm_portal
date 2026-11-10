<?php

namespace App\Services\Recruit;

use App\Helper\Files;
use App\Models\Candidate;
use App\Models\CandidateFile;
use App\Models\EmpJoiningChecklist;
use App\Models\EmployeeDocument;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Employee\OnboardingDocuments;
use Illuminate\Http\UploadedFile;

/**
 * A candidate becoming an employee.
 *
 * "Convert to Employee" opens the usual Add Employee form with
 * ?candidate={id}: prefill() gives the form what is known - name, contact,
 * personal details, qualification, work history and skills, and from the
 * accepted offer the designation, department, branch, joining date and CTC.
 * HR checks it and saves as for anyone else, so every rule of onboarding
 * still applies.
 *
 * When that save succeeds, complete() links the candidate to the new
 * employee, copies the resume and the signed offer into the employee's
 * documents, and moves the application to Joined.
 */
class CandidateConversion
{

    public function __construct(protected Pipeline $pipeline)
    {
    }

    /** Why this candidate cannot be converted, or null. */
    public static function problem(Candidate $candidate): ?string
    {
        if ($candidate->user_id) {
            return $candidate->name . ' has already been added as an employee.';
        }

        // Joining follows an accepted offer (with its proof of acceptance)
        if (!$candidate->applications()->where('stage', 'offer_accepted')->exists()) {
            return $candidate->name . ' can be added as an employee once an offer is accepted.';
        }

        if ($candidate->email && User::withoutGlobalScopes()->where('email', $candidate->email)->exists()) {
            return 'An employee or user with the email ' . $candidate->email . ' already exists. Check whether this person is already on file.';
        }

        return null;
    }

    /** The application being converted: the one with the accepted offer, else the furthest along. */
    public static function applicationFor(Candidate $candidate): ?JobApplication
    {
        $applications = $candidate->applications()->with('offer', 'position')->get();

        return $applications->firstWhere('stage', 'offer_accepted')
            ?? $applications->whereIn('stage', JobApplication::ACTIVE)->sortByDesc(fn ($a) => array_search($a->stage, array_keys(JobApplication::STAGES)))->first();
    }

    /** @return array<string, mixed> what the Add Employee form is filled with */
    public function prefill(Candidate $candidate): array
    {
        $application = self::applicationFor($candidate);
        $offer = $application?->offer;
        $position = $application?->position;
        $format = company()->date_format;

        $parts = preg_split('/\s+/', trim($candidate->name)) ?: [];
        $first = array_shift($parts) ?? '';
        $last = count($parts) ? array_pop($parts) : '';

        return [
            'candidate_id' => $candidate->id,
            'candidate_name' => $candidate->name,
            'position' => $application?->positionLabel(),
            'resume' => $candidate->resume?->filename,
            'resume_data' => [
                'first_name' => $first,
                'middle_name' => implode(' ', $parts) ?: null,
                'last_name' => $last ?: null,
                'email' => $candidate->email,
                'mobile' => $candidate->mobile ? preg_replace('/^\+91/', '', $candidate->mobile) : null,
                'date_of_birth' => $candidate->date_of_birth?->format($format),
                'gender' => $candidate->gender,
                'address' => $candidate->address,
                'qualification' => $candidate->qualification,
                'total_experience_years' => $candidate->total_experience,
                'skills' => $candidate->skills ?: [],
                'work_experience' => $candidate->work_experience ?: [],
            ],
            'job' => [
                'designation' => $offer?->designation_id ?? $position?->designation_id,
                'department' => $offer?->department_id ?? $position?->department_id,
                'branch' => $offer?->branch_id ?? $position?->branch_id,
                'joining_date' => $offer?->joining_date?->format($format),
                'employee_ctc' => $offer?->annual_ctc ? (string) round($offer->annual_ctc) : null,
            ],
        ];
    }

    /** After Add Employee saved $user from this candidate. */
    public function complete(int $candidateId, User $user): void
    {
        $candidate = Candidate::withoutGlobalScopes()->find($candidateId);

        if (!$candidate || $candidate->user_id) {
            return;
        }

        // Only a candidate with an accepted offer is joined (the Add Employee form is filled only for them)
        if (!$candidate->applications()->withoutGlobalScopes()->where('stage', 'offer_accepted')->exists()) {
            return;
        }

        $candidate->user_id = $user->id;
        $candidate->converted_at = now();
        $candidate->save();

        $application = self::applicationFor($candidate);
        $copied = $this->copyFiles($candidate, $user);

        if ($application) {
            $this->pipeline->move($application, 'joined', null, 'Added as employee ' . $user->name, false);
        }

        $this->pipeline->log($candidate->id, $application?->id, 'converted',
            'Added as an employee by ' . (user()?->name ?? 'the system') . ($copied ? ' · ' . $copied . ' file(s) copied to their documents' : ''),
            null, null, $candidate->company_id);
    }

    /** The resume, the signed offer and the other documents go into the employee's documents. @return int */
    protected function copyFiles(Candidate $candidate, User $user): int
    {
        $existing = EmployeeDocument::where('user_id', $user->id)->get();
        $hasResume = $existing->where('category', OnboardingDocuments::CATEGORY_RESUME)->isNotEmpty();
        $resume = $candidate->resume;
        $copied = 0;

        foreach ($candidate->files()->get() as $file) {
            if (!is_readable($file->localPath())) {
                continue;
            }

            if ($file->category === 'resume') {
                // One resume: the latest, unless HR attached one on the form.
                if ($hasResume || $file->id !== $resume?->id || !$file->isPdf()) {
                    $this->copy($file, $user, null, null, $file->name);
                } else {
                    $this->copy($file, $user, OnboardingDocuments::CATEGORY_RESUME, null, 'Resume');
                }
            } elseif ($file->category === 'signed_offer') {
                $this->copy($file, $user, OnboardingDocuments::CATEGORY_CHECKLIST, 'offer_letter', 'Checklist - Offer Letter');
                EmpJoiningChecklist::where('user_id', $user->id)->update(['offer_letter' => 1]);
            } else {
                $this->copy($file, $user, null, null, $file->name);
            }

            $copied++;
        }

        if ($resume && !$hasResume && $resume->isPdf()) {
            EmpJoiningChecklist::where('user_id', $user->id)->update(['resume_copy' => 1]);
        }

        return $copied;
    }

    protected function copy(CandidateFile $file, User $user, ?string $category, ?string $key, string $name): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'conv');
        copy($file->localPath(), $temp);

        try {
            $hashname = Files::uploadLocalOrS3(new UploadedFile($temp, $file->filename, $file->mime, null, true), EmployeeDocument::FILE_PATH . '/' . $user->id);
        } finally {
            @unlink($temp);
        }

        $doc = new EmployeeDocument();
        $doc->company_id = $user->company_id;
        $doc->user_id = $user->id;
        $doc->name = mb_substr($name, 0, 191);
        $doc->category = $category;
        $doc->checklist_key = $key;
        $doc->employment_period_id = $category ? OnboardingDocuments::currentPeriodId($user->id) : null;
        $doc->filename = $file->filename;
        $doc->hashname = $hashname;
        $doc->size = $file->size;
        $doc->mime = $file->mime;
        $doc->added_by = user()?->id;
        $doc->last_updated_by = user()?->id;
        $doc->save();
    }

}
