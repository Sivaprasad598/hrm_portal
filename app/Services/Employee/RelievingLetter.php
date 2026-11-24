<?php

namespace App\Services\Employee;

use App\Helper\Files;
use App\Models\EmployeeDocument;
use App\Services\Assets\AssetFlow;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two letters for someone who has left, as the company issues them, on the
 * offer letter's letterhead and footer:
 *
 *   relieving   "RELIEVING LETTER": To, name / employee ID / designation /
 *               department, Subject, Dear .., relieved from .. effective ..
 *   experience  "Experience Letter", To Whom It May Concern: the particulars
 *               table (name, designation, department, tenure, dates)
 *
 * Every word from Settings -> Letter Settings (Relieving Letter / Experience
 * Letter), with placeholders filled in:
 *   {salutation} {name} {employee_id} {designation} {department} {joining_date}
 *   {relieving_date} {tenure} {he} {his} {him} {He} {His} {Him} {date} {company}
 *
 * From Exited Employees both are issued together, only once they have left
 * (their last working day is past, or they are off-boarded), hold no company
 * asset, the exit's asset clearance is done, and their final settlement is paid
 * (or marked completed on the exit). The Letter Generator makes either for
 * anyone, from a form.
 *
 * Each letter is kept (relieving_letters, with its kind and the other letter
 * of the pair) with its date and what it printed, so it can be reopened.
 */
class RelievingLetter
{

    public const KINDS = ['relieving' => 'Relieving Letter', 'experience' => 'Experience Letter'];

    public const PLACEHOLDERS = [
        '{salutation}' => 'Mr. / Ms.',
        '{name}' => 'Employee name',
        '{employee_id}' => 'Employee ID',
        '{designation}' => 'Designation at the time of relieving',
        '{department}' => 'Department (their Division)',
        '{joining_date}' => 'Date of joining',
        '{relieving_date}' => 'Last working day',
        '{tenure}' => 'e.g. 1 Year, 2 Years 3 Months',
        '{he} {his} {him}' => 'he / she, his / her, him / her',
        '{He} {His} {Him}' => 'The same, with a capital (start of a sentence)',
        '{date}' => 'Letter date',
        '{company}' => 'Company name (footer)',
    ];

    public static function kind(?string $kind): string
    {
        return isset(self::KINDS[$kind]) ? $kind : 'relieving';
    }

    protected static function employee(int $userId): ?object
    {
        $e = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.id', $userId)
            ->first(['u.id', 'u.company_id', 'u.name', 'u.salutation', 'u.gender', 'ed.employee_id as code', 'ed.address',
                'ed.permanent_address', 'ed.joining_date', 'ed.last_date', 'ed.employment_status',
                'd.name as designation', 't.team_name as department']);

        if ($e) {
            $e->exit = DB::table('employee_exits')->where('user_id', $userId)->orderByDesc('id')->first();
            // The last working day: the exit's, else the one on the employee.
            $e->relieving_date = $e->exit->last_working_date ?? $e->last_date;
        }

        return $e;
    }

    /** Has this person left? Off-boarded, or their last working day is past. */
    public static function hasLeft(object $e): bool
    {
        return $e->employment_status === 'off_boarded'
            || ($e->relieving_date && Carbon::parse($e->relieving_date)->lt(now()->startOfDay()));
    }

    /** What stands in the way of the letters: one line each, empty when they can be issued. */
    public static function problems(int $userId): array
    {
        $e = self::employee($userId);

        if (!$e) {
            return ['The employee record was not found.'];
        }

        if (!self::hasLeft($e)) {
            return ['The relieving and experience letters are only for employees who have left - their last working day is not past yet.'];
        }

        $problems = [];

        // Clearances first: the letters are the last step of the exit.
        $held = AssetFlow::heldBy($userId);

        if ($held->isNotEmpty()) {
            $names = $held->map(fn ($a) => trim(($a->asset->name ?? 'Asset') . ($a->asset?->asset_code ? ' (' . $a->asset->asset_code . ')' : '')))->take(5)->implode(', ');
            $problems[] = $held->count() . ' company asset(s) not returned yet: ' . $names . '. Return them in Assets.';
        } elseif ($e->exit && !in_array($e->exit->asset_clearance_status, ['completed', 'waived'], true)) {
            $problems[] = 'Asset clearance on the exit is still pending.';
        }

        if (!self::settled($userId, $e)) {
            $problems[] = 'Final settlement is not done - pay it in Payroll -> Settlements (or mark Final Settlement completed on the exit).';
        }

        if (trim((string) $e->name) === '') {
            $problems[] = 'Name is empty.';
        }

        if (!OfferLetter::salutation($e)) {
            $problems[] = 'Gender (or salutation) is not set - needed for Mr. / Ms. and his / her.';
        }

        if (!$e->designation) {
            $problems[] = 'Designation is not set.';
        }

        if (!$e->department) {
            $problems[] = 'Division (Department on the letter) is not set.';
        }

        if (!$e->joining_date) {
            $problems[] = 'Joining date is not set.';
        }

        if (!$e->relieving_date) {
            $problems[] = 'Last working day is not set.';
        }

        return $problems;
    }

    /** Final settlement paid (Payroll -> Settlements), or completed on the exit. */
    protected static function settled(int $userId, object $e): bool
    {
        if ($e->exit && $e->exit->final_settlement_status === 'completed') {
            return true;
        }

        return DB::table('settlements')->where('user_id', $userId)->whereIn('status', ['paid', 'completed'])->exists();
    }

    protected static function hasKinds(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('relieving_letters', 'kind');
    }

    /** The latest relieving letter of this employee (its experience letter is its pair). */
    public static function latestFor(int $userId): ?object
    {
        return DB::table('relieving_letters')->where('user_id', $userId)
            ->when(self::hasKinds(), fn ($q) => $q->where('kind', 'relieving'))
            ->orderByDesc('id')->first();
    }

    /** The other letter of the two issued together, if any. */
    public static function pairOf(object $letter): ?object
    {
        return !empty($letter->pair_id) ? DB::table('relieving_letters')->where('id', $letter->pair_id)->first() : null;
    }

    protected static function insert(array $row, string $kind): int
    {
        if (self::hasKinds()) {
            $row['kind'] = self::kind($kind);
        }

        return DB::table('relieving_letters')->insertGetId($row + ['created_at' => now(), 'updated_at' => now()]);
    }

    /** Two letters issued together point at each other. */
    protected static function pair(int $a, int $b): void
    {
        if (self::hasKinds()) {
            DB::table('relieving_letters')->where('id', $a)->update(['pair_id' => $b]);
            DB::table('relieving_letters')->where('id', $b)->update(['pair_id' => $a]);
        }
    }

    /**
     * From Exited Employees: the relieving letter and the experience letter together.
     * Answers the relieving letter (the experience letter is its pair).
     */
    public static function issue(int $userId, ?string $date): object
    {
        $e = self::employee($userId);
        $details = self::detailsFor($userId);
        $row = [
            'company_id' => $e->company_id,
            'user_id' => $userId,
            'source' => 'employee',
            'person_name' => trim($details['salutation'] . ' ' . $details['name']),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'employee_exit_id' => $e->exit->id ?? null,
            'letter_date' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
            'generated_by' => user()?->id,
        ];

        return DB::transaction(function () use ($row) {
            $relieving = self::insert($row, 'relieving');
            $experience = self::insert($row, 'experience');
            self::pair($relieving, $experience);

            return DB::table('relieving_letters')->find($relieving);
        });
    }

    /**
     * A letter from the Letter Generator form: someone who left before the
     * system (or an employee's details as typed). No clearance checks - those
     * belong to the Exited Employees route; kept with what it printed.
     * $withPair: the relieving letter brings its experience letter along.
     */
    public static function issueManual(array $details, ?string $date, ?int $userId = null, string $kind = 'relieving', bool $withPair = false): object
    {
        $details = self::cleanDetails($details);
        $row = [
            'company_id' => company()->id,
            'user_id' => $userId,
            'source' => 'manual',
            'person_name' => trim(($details['salutation'] ?? '') . ' ' . ($details['name'] ?? '')),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'letter_date' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
            'generated_by' => user()?->id,
        ];

        return DB::transaction(function () use ($row, $kind, $withPair, $details) {
            $id = self::insert($row, $kind);

            if ($withPair) {
                // The wording typed for this letter is its own: the pair keeps its settings wording.
                $other = $row;
                $other['details'] = json_encode(array_diff_key($details, ['paragraphs' => 1]), JSON_UNESCAPED_UNICODE);
                self::pair($id, self::insert($other, $kind === 'relieving' ? 'experience' : 'relieving'));
            }

            return DB::table('relieving_letters')->find($id);
        });
    }

    /** An employee's details, as the form holds them (the generator's prefill). */
    public static function detailsFor(int $userId): array
    {
        $e = self::employee($userId);

        return [
            'salutation' => OfferLetter::salutation($e),
            'gender' => strtolower((string) $e->gender),
            'name' => (string) $e->name,
            'employee_id' => (string) $e->code,
            'address' => trim((string) ($e->address ?: $e->permanent_address)),
            'joining_date' => $e->joining_date ? Carbon::parse($e->joining_date)->toDateString() : '',
            'relieving_date' => $e->relieving_date ? Carbon::parse($e->relieving_date)->toDateString() : '',
            'designation' => (string) $e->designation,
            'department' => (string) $e->department,
        ];
    }

    public static function cleanDetails(array $d): array
    {
        $text = fn ($k) => trim((string) ($d[$k] ?? ''));

        return array_filter([
            'salutation' => $text('salutation'),
            'gender' => in_array($text('gender'), ['male', 'female'], true) ? $text('gender') : '',
            'name' => $text('name'),
            'employee_id' => $text('employee_id'),
            'address' => str_replace("\r", '', $text('address')),
            'joining_date' => $text('joining_date'),
            'relieving_date' => $text('relieving_date'),
            'designation' => $text('designation'),
            'department' => $text('department'),
            'tenure' => $text('tenure') !== '' ? $text('tenure') : null,
            'paragraphs' => $text('paragraphs') !== '' ? str_replace("\r", '', $text('paragraphs')) : null,
        ], fn ($v) => $v !== null);
    }

    public static function formProblems(array $d): array
    {
        $problems = [];

        foreach (['name' => 'Name', 'joining_date' => 'Joining date', 'relieving_date' => 'Relieving date',
            'designation' => 'Designation', 'department' => 'Department'] as $key => $label) {
            if (trim((string) ($d[$key] ?? '')) === '') {
                $problems[] = $label . ' is empty.';
            }
        }

        if (!empty($d['joining_date']) && !empty($d['relieving_date']) && strtotime($d['relieving_date']) < strtotime($d['joining_date'])) {
            $problems[] = 'The relieving date is before the joining date.';
        }

        return $problems;
    }

    protected static function fromDetails(array $d): object
    {
        $salutation = strtolower(rtrim((string) ($d['salutation'] ?? ''), '.'));

        return (object) [
            'name' => $d['name'] ?? '', 'salutation' => $d['salutation'] ?? null, 'code' => $d['employee_id'] ?? '',
            // his / her: the gender given, else from Mr. / Ms.
            'gender' => ($d['gender'] ?? '') ?: (['mr' => 'male', 'ms' => 'female', 'mrs' => 'female', 'miss' => 'female'][$salutation] ?? null),
            'address' => $d['address'] ?? '', 'permanent_address' => null,
            'joining_date' => $d['joining_date'] ?? null, 'relieving_date' => $d['relieving_date'] ?? null,
            'designation' => $d['designation'] ?? '', 'department' => $d['department'] ?? '',
            'tenure_text' => $d['tenure'] ?? null, 'paragraphs' => $d['paragraphs'] ?? null,
        ];
    }

    /** A date in the letter's format ("16th May 2018" printed plain, as in the company's letter). */
    public static function formatDate($value, string $format): string
    {
        return $value ? e(Carbon::parse($value)->format($format)) : '';
    }

    /**
     * Everything the letter shows. $letter null: the form's details, or the preview in
     * Settings with a sample employee. The kind: the letter's own, else $kind.
     */
    public static function data(?object $letter, bool $forPdf, ?array $settings = null, ?array $details = null, string $kind = 'relieving'): array
    {
        $kind = self::kind($letter->kind ?? $kind);
        $key = fn ($k) => $kind . '_' . $k;

        // The copy kept with the letter, the form's details, or the employee as they are.
        $kept = $letter && !empty($letter->details) ? json_decode($letter->details, true) : null;
        $e = match (true) {
            (bool) $kept => self::fromDetails($kept),
            $details !== null => self::fromDetails(self::cleanDetails($details)),
            $letter && $letter->user_id => self::employee((int) $letter->user_id),
            default => null,
        };
        $settings ??= OfferLetter::settings($letter->company_id ?? null);

        if (!$e) {
            $e = (object) [
                'name' => 'Toorubilli Ravi', 'salutation' => null, 'gender' => 'male', 'code' => 'ACPL0000',
                'address' => "0-0, main veedhi\nkothavalasa mandalam,\nseeramsettivanipalem gramam,\nAP, 531173", 'permanent_address' => null,
                'joining_date' => '2025-08-11', 'relieving_date' => '2026-08-20', 'designation' => 'Sr. BIM Modeler', 'department' => 'Revit',
            ];
        }

        $format = $settings[$key('date_format')] ?: 'd/m/Y';
        $date = fn ($v) => self::formatDate($v, $format);
        $salutation = OfferLetter::salutation($e);
        [$he, $his, $him] = ['male' => ['he', 'his', 'him'], 'female' => ['she', 'her', 'her']][strtolower((string) $e->gender)] ?? ['they', 'their', 'them'];
        $letterDate = self::formatDate($letter->letter_date ?? now(), $settings[$key('letter_date_format')] ?: $format);

        $values = [
            '{salutation}' => e($salutation),
            '{name}' => e($e->name),
            '{employee_id}' => e((string) ($e->code ?? '')),
            '{designation}' => e((string) $e->designation),
            '{department}' => e((string) $e->department),
            '{joining_date}' => $date($e->joining_date),
            '{relieving_date}' => $date($e->relieving_date),
            '{tenure}' => e(!empty($e->tenure_text) ? $e->tenure_text : self::tenure($e->joining_date, $e->relieving_date)),
            '{he}' => $he, '{his}' => $his, '{him}' => $him,
            '{He}' => ucfirst($he), '{His}' => ucfirst($his), '{Him}' => ucfirst($him),
            '{date}' => $letterDate,
            '{company}' => e($settings['footer_company']),
        ];
        $fill = fn ($text) => OfferLetter::fill((string) $text, $values);
        $own = !empty($e->paragraphs) ? $e->paragraphs : null;

        $common = [
            'kind' => $kind,
            's' => $settings,
            'forPdf' => $forPdf,
            'letterDate' => $letterDate,
            'title' => $fill($settings[$key('title')]),
            'closing' => trim((string) $settings[$key('closing')]) !== '' ? $fill($settings[$key('closing')]) : '',
            'signoff' => implode('<br>', array_map($fill, OfferLetter::lines($settings[$key('signoff')]))),
            'logo' => OfferLetter::logo($forPdf),
            'stamp' => OfferLetter::stamp($settings, $forPdf),
            'name' => $e->name,
        ];

        if ($kind === 'experience') {
            // "Label | value" a line; the first line is the table's heading row.
            $rows = array_map(function ($line) use ($fill) {
                [$label, $value] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

                return [$fill($label), $fill($value)];
            }, OfferLetter::lines($settings['experience_particulars']));

            return $common + [
                'heading' => $fill($settings['experience_heading']),
                'intro' => array_map($fill, OfferLetter::blocks($settings['experience_intro'])),
                'particulars' => $rows,
                'paragraphs' => array_map($fill, OfferLetter::blocks($own ?? $settings['experience_paragraphs'])),
            ];
        }

        return $common + [
            'to' => array_map($fill, OfferLetter::lines($settings['relieving_to'])),
            'subject' => trim((string) $settings['relieving_subject']) !== '' ? $fill($settings['relieving_subject']) : '',
            'greeting' => $fill($settings['relieving_greeting']),
            'paragraphs' => array_map($fill, OfferLetter::blocks($own ?? $settings['relieving_paragraphs'])),
        ];
    }

    /**
     * 11-08-2025 to 20-08-2026 -> "1 Year"; "2 Years 3 Months"; "8 Months"; under a month, days.
     * Half a month or more counts as a month, as HR writes it: 16-05-2018 to 01-09-2025 -> "7 Years 4 Months".
     */
    public static function tenure($from, $to): string
    {
        if (!$from || !$to) {
            return '';
        }

        $diff = Carbon::parse($from)->startOfDay()->diff(Carbon::parse($to)->startOfDay()->addDay());

        if ($diff->y === 0 && $diff->m === 0) {
            return max(1, $diff->d) . ' Days';
        }

        $months = $diff->y * 12 + $diff->m + ($diff->d >= 15 ? 1 : 0);
        [$y, $m] = [intdiv($months, 12), $months % 12];
        $parts = [];

        if ($y) {
            $parts[] = $y . ' ' . ($y === 1 ? 'Year' : 'Years');
        }

        if ($m) {
            $parts[] = $m . ' ' . ($m === 1 ? 'Month' : 'Months');
        }

        return implode(' ', $parts);
    }

    public static function html(?object $letter, bool $forPdf, ?array $settings = null, ?array $details = null, string $kind = 'relieving'): string
    {
        $data = self::data($letter, $forPdf, $settings, $details, $kind);

        return view($data['kind'] === 'experience' ? 'relieving-letters.experience' : 'relieving-letters.letter', $data)->render();
    }

    public static function pdf(object $letter)
    {
        return Pdf::loadHTML(self::html($letter, true))->setPaper('a4')->setOption('dpi', 96)
            ->setOption('isRemoteEnabled', true)->setOption('isFontSubsettingEnabled', true)
            ->setOption('chroot', [base_path(), storage_path()]);
    }

    public static function label(object $letter): string
    {
        return self::KINDS[self::kind($letter->kind ?? null)];
    }

    public static function fileName(object $letter): string
    {
        $name = $letter->user_id ? DB::table('users')->where('id', $letter->user_id)->value('name') : ($letter->person_name ?? 'Employee');

        return self::label($letter) . ' - ' . preg_replace('/[^A-Za-z0-9 ._-]+/', '', (string) $name) . '.pdf';
    }

    /**
     * The PDF kept with the employee's documents (one per letter) and the
     * exit's "Relieving letter issued" / "Experience letter issued" ticked.
     */
    public static function keep(object $letter, string $pdfBinary): void
    {
        $name = self::label($letter) . ' ' . Carbon::parse($letter->letter_date)->format('d-m-Y') . ' #' . $letter->id;

        // Someone not in the system (an old employee's letter): nothing to file it under.
        if (!$letter->user_id) {
            return;
        }

        if (!EmployeeDocument::where('user_id', $letter->user_id)->where('name', $name)->exists()) {
            $temp = tempnam(sys_get_temp_dir(), 'relieve');
            file_put_contents($temp, $pdfBinary);

            try {
                $file = new UploadedFile($temp, self::fileName($letter), 'application/pdf', null, true);
                $doc = new EmployeeDocument();
                $doc->company_id = $letter->company_id;
                $doc->user_id = $letter->user_id;
                $doc->name = $name;
                $doc->filename = self::fileName($letter);
                $doc->hashname = Files::uploadLocalOrS3($file, EmployeeDocument::FILE_PATH . '/' . $letter->user_id);
                $doc->size = strlen($pdfBinary);
                $doc->mime = 'application/pdf';
                $doc->added_by = user()?->id;
                $doc->last_updated_by = user()?->id;
                $doc->save();
            } finally {
                @unlink($temp);
            }
        }

        if ($letter->employee_exit_id) {
            DB::table('employee_exit_checklists')->where('exit_id', $letter->employee_exit_id)
                ->where('item_key', self::kind($letter->kind ?? null) . '_letter')->where('status', '<>', 'completed')
                ->update(['status' => 'completed', 'completed_by' => user()?->id, 'completed_date' => now()->toDateString(), 'updated_at' => now()]);
        }
    }

}
