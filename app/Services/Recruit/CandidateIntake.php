<?php

namespace App\Services\Recruit;

use App\Helper\Files;
use App\Models\AiSetting;
use App\Models\Candidate;
use App\Models\CandidateFile;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitSetting;
use App\Services\Ai\AiClient;
use App\Services\Ai\ResumeExtractor;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Where every candidate comes in - the careers mailbox, the website's intake
 * API and HR's own upload all go through receive():
 *
 *  - the same person is never entered twice: matched on email, then on the
 *    mobile number's last ten digits; a known person gets the new details
 *    only where theirs are empty, and the new files;
 *  - files: the resume (a PDF or Word file, preferring one named resume / CV),
 *    other documents alongside;
 *  - the position is found from what was sent (an id, code or title), or the
 *    application waits as "Unassigned" for HR;
 *  - with AI on (Settings -> AI, and "Read resumes with AI" here) aiFill()
 *    reads the resume PDF into the empty fields. Nothing typed is overwritten.
 */
class CandidateIntake
{

    public const FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    public const RESUME_EXTENSIONS = ['pdf', 'doc', 'docx'];

    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    public const MAX_FILES = 6;

    public function __construct(protected Pipeline $pipeline)
    {
    }

    /**
     * @param array<string, mixed> $data    name, email, mobile, position, qualification, total_experience, current_location,
     *                                      current_company, current_designation, current_ctc, expected_ctc, notice_period_days,
     *                                      skills (list or comma text), message, source_detail
     * @param array<int, array{name: string, content: string, mime?: ?string}> $files
     * @return array{status: string, candidate: Candidate, application: JobApplication, position_matched: bool, files: int}
     */
    public function receive(array $data, array $files, string $source, ?int $companyId, ?string $positionText = null): array
    {
        $email = $this->email($data['email'] ?? null);
        $mobile = $this->mobile($data['mobile'] ?? null);
        $candidate = $this->findExisting($email, $mobile, $companyId);
        $status = $candidate ? 'updated' : 'imported';

        $fields = array_filter([
            'name' => $this->text($data['name'] ?? null, 191),
            'email' => $email,
            'mobile' => $mobile,
            'qualification' => $this->text($data['qualification'] ?? null, 191),
            'total_experience' => is_numeric($data['total_experience'] ?? null) ? min(60, max(0, round((float) $data['total_experience'], 1))) : null,
            'current_location' => $this->text($data['current_location'] ?? null, 191),
            'current_company' => $this->text($data['current_company'] ?? null, 191),
            'current_designation' => $this->text($data['current_designation'] ?? null, 191),
            'current_ctc' => is_numeric($data['current_ctc'] ?? null) ? (float) $data['current_ctc'] : null,
            'expected_ctc' => is_numeric($data['expected_ctc'] ?? null) ? (float) $data['expected_ctc'] : null,
            'notice_period_days' => is_numeric($data['notice_period_days'] ?? null) ? min(365, (int) $data['notice_period_days']) : null,
            'skills' => $this->skills($data['skills'] ?? null) ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($candidate) {
            // Only what is still empty: what HR has entered or corrected stays.
            foreach ($fields as $key => $value) {
                if ($candidate->{$key} === null || $candidate->{$key} === '' || $candidate->{$key} === []) {
                    $candidate->{$key} = $value;
                }
            }

            $candidate->save();
        } else {
            $candidate = new Candidate($fields);
            $candidate->company_id = $companyId;
            $candidate->name = $fields['name'] ?? ($email ? Str::before($email, '@') : 'Unknown candidate');
            $candidate->source = $source;
            $candidate->source_detail = $this->text($data['source_detail'] ?? null, 191);
            $candidate->created_by = user()?->id;
            $candidate->save();
        }

        $stored = $this->storeFiles($candidate, $files);

        $position = $this->findPosition($data['position'] ?? null, $positionText, $companyId);
        $application = $this->application($candidate, $position, $source, $this->text($data['message'] ?? null, 20000), $companyId);

        $sourceText = Candidate::SOURCES[$source][0] ?? $source;
        $this->pipeline->log($candidate->id, $application->id, 'received', trim($sourceText
            . ($data['source_detail'] ?? null ? ' — ' . $data['source_detail'] : '')
            . ($position ? ' · for ' . $position->title : ' · no position named')
            . ($stored ? ' · ' . $stored . ' file(s)' : '')), null, null, $companyId);

        return ['status' => $status, 'candidate' => $candidate, 'application' => $application,
            'position_matched' => (bool) $position, 'files' => $stored];
    }

    /**
     * Read the candidate's resume PDF with AI into the fields still empty.
     *
     * @return string|null  what happened, for the timeline; null when AI is not set up or there is no PDF
     */
    public function aiFill(Candidate $candidate, bool $force = false): ?string
    {
        $setting = AiSetting::forCompany($candidate->company_id);
        $recruit = RecruitSetting::forCompany($candidate->company_id);

        if (!$setting->ready() || (!$force && !$recruit->ai_autofill)) {
            return null;
        }

        $resume = $candidate->files()->where('category', 'resume')->get()->first(fn (CandidateFile $f) => $f->isPdf());

        if (!$resume || !is_readable($resume->localPath())) {
            return null;
        }

        try {
            $data = (new ResumeExtractor(new AiClient($setting)))->extractBytes((string) file_get_contents($resume->localPath()), $resume->filename);
        } catch (\Throwable $e) {
            logger()->warning('Recruit resume extraction failed', ['candidate' => $candidate->id, 'error' => $e->getMessage()]);
            $this->pipeline->log($candidate->id, null, 'ai', 'The resume could not be read with AI: ' . mb_substr($e->getMessage(), 0, 300),
                null, null, $candidate->company_id);

            return 'failed';
        }

        $filled = [];
        $put = function (string $field, $value, string $label) use ($candidate, &$filled) {
            if ($value === null || $value === '' || $value === []) {
                return;
            }

            $current = $candidate->{$field};

            if ($current === null || $current === '' || $current === [] || ($field === 'name' && str_contains((string) $current, '@'))) {
                $candidate->{$field} = $value;
                $filled[] = $label;
            }
        };

        $name = trim(implode(' ', array_filter([$data['first_name'] ?? null, $data['middle_name'] ?? null, $data['last_name'] ?? null])));
        $nameLooksMadeUp = $candidate->email && strcasecmp($candidate->name, Str::before($candidate->email, '@')) === 0;

        if ($name !== '' && ($nameLooksMadeUp || $candidate->name === 'Unknown candidate')) {
            $candidate->name = mb_substr($name, 0, 191);
            $filled[] = 'name';
        }

        $put('email', $data['email'] ?? null, 'email');
        $put('mobile', $data['mobile'] ?? null, 'mobile');
        $put('gender', $data['gender'] ?? null, 'gender');
        $put('date_of_birth', $this->companyDate($data['date_of_birth'] ?? null), 'date of birth');
        $put('address', $data['address'] ?? null, 'address');
        $put('qualification', $data['qualification'] ?? null, 'qualification');
        $put('total_experience', $data['total_experience_years'] ?? null, 'experience');
        $put('skills', $data['skills'] ?? [], 'skills');
        $put('work_experience', $data['work_experience'] ?? [], 'work history');

        $latest = ($data['work_experience'] ?? [])[0] ?? null;

        if ($latest && empty($latest['leaving_date'])) {
            $put('current_company', $latest['company_name'] ?? null, 'current company');
            $put('current_designation', $latest['designation'] ?? null, 'current designation');
        }

        $candidate->ai_filled_at = now();
        $candidate->save();

        $text = $filled ? 'Filled from the resume: ' . implode(', ', array_unique($filled)) . '.' : 'Nothing new found in the resume.';
        $this->pipeline->log($candidate->id, null, 'ai', $text . ' (' . (AiSetting::PROVIDERS[$setting->provider] ?? $setting->provider) . ')',
            null, null, $candidate->company_id);

        return $text;
    }

    /**
     * Keep files for a candidate. Each is [name, content, mime?]; anything not
     * an allowed type, empty or too large is left out.
     *
     * @return int how many were kept
     */
    public function storeFiles(Candidate $candidate, array $files, ?string $category = null): int
    {
        $files = array_values(array_filter($files, function ($file) {
            $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

            return in_array($ext, self::FILE_EXTENSIONS, true)
                && strlen((string) ($file['content'] ?? '')) > 0 && strlen($file['content']) <= self::MAX_FILE_BYTES;
        }));

        if (!$files) {
            return 0;
        }

        $resumeIndex = $category ? null : $this->resumeIndex($files);
        $hasResume = $candidate->files()->where('category', 'resume')->exists();
        $kept = 0;

        foreach (array_slice($files, 0, self::MAX_FILES) as $i => $file) {
            $fileCategory = $category ?? ($i === $resumeIndex ? 'resume' : 'document');

            // One file the upload checks refuse (a signature image, a tiny file) does not stop the rest.
            try {
                $this->storeOne($candidate, $file['name'], $file['content'], $file['mime'] ?? null, $fileCategory,
                    $fileCategory === 'resume' ? 'Resume' . ($hasResume ? ' (' . now()->format('d M Y') . ')' : '') : null);
                $kept++;
            } catch (\Throwable $e) {
                logger()->info('Candidate file not kept', ['candidate' => $candidate->id, 'file' => $file['name'], 'error' => $e->getMessage()]);
            }
        }

        return $kept;
    }

    public function storeUploaded(Candidate $candidate, UploadedFile $file, string $category, ?string $name = null): CandidateFile
    {
        return $this->storeOne($candidate, $file->getClientOriginalName(), (string) file_get_contents($file->getRealPath()),
            $file->getMimeType(), $category, $name);
    }

    protected function storeOne(Candidate $candidate, string $filename, string $content, ?string $mime, string $category, ?string $name): CandidateFile
    {
        $filename = $this->safeFilename($filename);
        $temp = tempnam(sys_get_temp_dir(), 'cand');
        file_put_contents($temp, $content);

        try {
            $upload = new UploadedFile($temp, $filename, $mime ?: null, null, true);
            $hashname = Files::uploadLocalOrS3($upload, CandidateFile::FILE_PATH . '/' . $candidate->id);
        } finally {
            @unlink($temp);
        }

        $file = CandidateFile::create([
            'company_id' => $candidate->company_id,
            'candidate_id' => $candidate->id,
            'category' => $category,
            'name' => $name ?: (CandidateFile::CATEGORIES[$category] ?? 'Document') . ' — ' . $filename,
            'filename' => $filename,
            'hashname' => $hashname,
            'size' => strlen($content),
            'mime' => $mime ?: (new \finfo(FILEINFO_MIME_TYPE))->buffer($content),
            'added_by' => user()?->id,
        ]);

        return $file;
    }

    public function findExisting(?string $email, ?string $mobile, ?int $companyId): ?Candidate
    {
        $query = fn () => Candidate::withoutGlobalScopes()->where('company_id', $companyId);

        if ($email && ($found = $query()->where('email', $email)->orderBy('id')->first())) {
            return $found;
        }

        if ($mobile && strlen($digits = substr(preg_replace('/\D/', '', $mobile), -10)) === 10) {
            return $query()->where('mobile', 'like', '%' . $digits)->orderBy('id')->first();
        }

        return null;
    }

    /**
     * The position a submission is for: an id or code given outright, else a
     * title found in the text (an email subject). Only positions still open or on hold.
     */
    public function findPosition($given, ?string $text, ?int $companyId): ?JobPosition
    {
        $positions = JobPosition::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('status', ['open', 'on_hold'])->orderByDesc('id')->get();

        $given = is_scalar($given) ? trim((string) $given) : '';

        if ($given !== '') {
            $match = $positions->first(fn ($p) => (ctype_digit($given) && (int) $given === $p->id)
                || strcasecmp($p->code, $given) === 0 || strcasecmp($p->title, $given) === 0);

            if ($match) {
                return $match;
            }

            $text = trim($given . ' ' . $text);
        }

        $text = mb_strtolower((string) $text);

        if ($text === '') {
            return null;
        }

        // The code first (JOB-0004), then the longest title found in the text.
        foreach ($positions as $p) {
            if (str_contains($text, mb_strtolower($p->code))) {
                return $p;
            }
        }

        return $positions->sortByDesc(fn ($p) => mb_strlen($p->title))
            ->first(fn ($p) => mb_strlen($p->title) >= 3 && str_contains($text, mb_strtolower($p->title)));
    }

    protected function application(Candidate $candidate, ?JobPosition $position, string $source, ?string $note, ?int $companyId): JobApplication
    {
        // Applying again for the same position adds to the application already open.
        $existing = JobApplication::withoutGlobalScopes()->where('candidate_id', $candidate->id)
            ->where('job_position_id', $position?->id)
            ->whereNotIn('stage', ['joined', 'rejected', 'withdrawn'])->orderByDesc('id')->first();

        if ($existing) {
            if ($note) {
                $existing->cover_note = trim(($existing->cover_note ? $existing->cover_note . "\n\n---\n\n" : '') . $note);
                $existing->save();
            }

            return $existing;
        }

        $application = JobApplication::create([
            'company_id' => $companyId,
            'candidate_id' => $candidate->id,
            'job_position_id' => $position?->id,
            'stage' => 'new',
            'stage_changed_at' => now(),
            'source' => $source,
            'cover_note' => $note,
            'created_by' => user()?->id,
        ]);

        $this->pipeline->log($candidate->id, $application->id, 'applied', $position ? 'For ' . $position->label() : 'No position yet (Unassigned)',
            null, 'new', $companyId);

        return $application;
    }

    /** The file most likely to be the resume: a PDF/Word file named resume / CV, else the first PDF/Word file. */
    protected function resumeIndex(array $files): ?int
    {
        $first = null;

        foreach ($files as $i => $file) {
            $name = strtolower((string) $file['name']);

            if (!in_array(pathinfo($name, PATHINFO_EXTENSION), self::RESUME_EXTENSIONS, true)) {
                continue;
            }

            if (preg_match('/resume|cv|curriculum|bio.?data|profile/', $name)) {
                return $i;
            }

            $first ??= $i;
        }

        return $first;
    }

    public function email(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 191) : null;
    }

    /** Digits (and a leading +) only; an Indian number without its +91 / 0. */
    public function mobile(?string $value): ?string
    {
        $value = preg_replace('/[^0-9+]/', '', (string) $value);
        $value = preg_replace('/^(\+91|0091|91(?=\d{10}$)|0(?=\d{10}$))/', '', $value);

        return strlen(preg_replace('/\D/', '', $value)) >= 7 ? substr($value, 0, 20) : null;
    }

    /** @return string[] */
    public function skills($value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_slice(array_unique(array_filter(array_map(fn ($s) => is_scalar($s) ? mb_substr(trim((string) $s), 0, 50) : '', $list))), 0, 30));
    }

    protected function text($value, int $max): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, $max) : null;
    }

    /** ResumeExtractor gives dates in the company format; the column wants Y-m-d. */
    protected function companyDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat(company()?->date_format ?? 'd-m-Y', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\w.\- ()]+/u', '_', basename(str_replace('\\', '/', $name)));

        return mb_substr(trim($name, '. ') ?: 'file', 0, 150);
    }

}
