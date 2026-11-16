<?php

namespace App\Services\Employee;

use App\Helper\Files;
use App\Models\EmployeeDocument;
use App\Services\Assets\AssetFlow;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The relieving cum experience letter, for an employee who has left: on the
 * same letterhead and footer as the offer letter, every word from Settings ->
 * Letter Settings -> Relieving Letter, with placeholders filled in:
 *   {salutation} {name} {designation} {department} {joining_date}
 *   {relieving_date} {tenure} {he} {his} {him} {date} {company}
 *
 * Only once they have left (their last working day is past, or they are
 * off-boarded), hold no company asset, the exit's asset clearance is done, and
 * their final settlement is paid (or marked completed on the exit).
 *
 * No reference number, as the company's own letter has none; each letter is
 * kept (relieving_letters) with its date, so it can be reopened.
 */
class RelievingLetter
{

    public const PLACEHOLDERS = [
        '{salutation}' => 'Mr. / Ms.',
        '{name}' => 'Employee name',
        '{designation}' => 'Designation at the time of relieving',
        '{department}' => 'Department (their Division)',
        '{joining_date}' => 'Date of joining',
        '{relieving_date}' => 'Last working day',
        '{tenure}' => 'e.g. 1 Year, 2 Years 3 Months',
        '{he}' => 'he / she',
        '{his}' => 'his / her',
        '{him}' => 'him / her',
        '{date}' => 'Letter date',
        '{company}' => 'Company name (footer)',
    ];

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

    /** What stands in the way of the letter: one line each, empty when it can be issued. */
    public static function problems(int $userId): array
    {
        $e = self::employee($userId);

        if (!$e) {
            return ['The employee record was not found.'];
        }

        if (!self::hasLeft($e)) {
            return ['The relieving letter is only for employees who have left - their last working day is not past yet.'];
        }

        $problems = [];

        // Clearances first: the letter is the last step of the exit.
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

        if (trim((string) ($e->address ?: $e->permanent_address)) === '') {
            $problems[] = 'Address is empty - the letter is addressed to it.';
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

    public static function latestFor(int $userId): ?object
    {
        return DB::table('relieving_letters')->where('user_id', $userId)->orderByDesc('id')->first();
    }

    public static function issue(int $userId, ?string $date): object
    {
        $e = self::employee($userId);
        $details = self::detailsFor($userId);
        $id = DB::table('relieving_letters')->insertGetId([
            'company_id' => $e->company_id,
            'user_id' => $userId,
            'source' => 'employee',
            'person_name' => trim($details['salutation'] . ' ' . $details['name']),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'employee_exit_id' => $e->exit->id ?? null,
            'letter_date' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
            'generated_by' => user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('relieving_letters')->find($id);
    }

    /**
     * A letter from the Letter Generator form: someone who left before the
     * system (or an employee's details as typed). No clearance checks - those
     * belong to the Exited Employees route; kept with what it printed.
     */
    public static function issueManual(array $details, ?string $date, ?int $userId = null): object
    {
        $details = self::cleanDetails($details);
        $id = DB::table('relieving_letters')->insertGetId([
            'company_id' => company()->id,
            'user_id' => $userId,
            'source' => 'manual',
            'person_name' => trim(($details['salutation'] ?? '') . ' ' . ($details['name'] ?? '')),
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'letter_date' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
            'generated_by' => user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('relieving_letters')->find($id);
    }

    /** An employee's details, as the form holds them (the generator's prefill). */
    public static function detailsFor(int $userId): array
    {
        $e = self::employee($userId);

        return [
            'salutation' => OfferLetter::salutation($e),
            'gender' => strtolower((string) $e->gender),
            'name' => (string) $e->name,
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

        foreach (['name' => 'Name', 'address' => 'Address', 'joining_date' => 'Joining date', 'relieving_date' => 'Relieving date',
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
            'name' => $d['name'] ?? '', 'salutation' => $d['salutation'] ?? null,
            // his / her: the gender given, else from Mr. / Ms.
            'gender' => ($d['gender'] ?? '') ?: (['mr' => 'male', 'ms' => 'female', 'mrs' => 'female', 'miss' => 'female'][$salutation] ?? null),
            'address' => $d['address'] ?? '', 'permanent_address' => null,
            'joining_date' => $d['joining_date'] ?? null, 'relieving_date' => $d['relieving_date'] ?? null,
            'designation' => $d['designation'] ?? '', 'department' => $d['department'] ?? '',
            'tenure_text' => $d['tenure'] ?? null, 'paragraphs' => $d['paragraphs'] ?? null,
        ];
    }

    /** Everything the letter shows. $letter null: the preview in Settings, with a sample employee. */
    public static function data(?object $letter, bool $forPdf, ?array $settings = null, ?array $details = null): array
    {
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
                'name' => 'Toorubilli Ravi', 'salutation' => null, 'gender' => 'male',
                'address' => "0-0, main veedhi\nkothavalasa mandalam,\nseeramsettivanipalem gramam,\nAP, 531173", 'permanent_address' => null,
                'joining_date' => '2025-08-11', 'relieving_date' => '2026-08-20', 'designation' => 'Sr. BIM Modeler', 'department' => 'Revit Department',
            ];
        }

        $format = $settings['relieving_date_format'] ?: 'd-m-Y';
        $date = fn ($v) => $v ? e(Carbon::parse($v)->format($format)) : '';
        $salutation = OfferLetter::salutation($e);
        [$he, $his, $him] = ['male' => ['he', 'his', 'him'], 'female' => ['she', 'her', 'her']][strtolower((string) $e->gender)] ?? ['they', 'their', 'them'];
        $letterDate = Carbon::parse($letter->letter_date ?? now());

        $values = [
            '{salutation}' => e($salutation),
            '{name}' => e($e->name),
            '{designation}' => e((string) $e->designation),
            '{department}' => e((string) $e->department),
            '{joining_date}' => $date($e->joining_date),
            '{relieving_date}' => $date($e->relieving_date),
            '{tenure}' => e(!empty($e->tenure_text) ? $e->tenure_text : self::tenure($e->joining_date, $e->relieving_date)),
            '{he}' => $he, '{his}' => $his, '{him}' => $him,
            '{date}' => OfferLetter::ordinalDate($letterDate),
            '{company}' => e($settings['footer_company']),
        ];

        return [
            's' => $settings,
            'forPdf' => $forPdf,
            'letterDate' => OfferLetter::ordinalDate($letterDate),
            'title' => OfferLetter::fill($settings['relieving_title'], $values),
            'addressee' => trim($salutation . ' ' . $e->name),
            'address' => trim((string) ($e->address ?: $e->permanent_address)),
            'greeting' => OfferLetter::fill($settings['relieving_greeting'], $values),
            'paragraphs' => array_map(fn ($p) => OfferLetter::fill($p, $values), OfferLetter::blocks(!empty($e->paragraphs) ? $e->paragraphs : $settings['relieving_paragraphs'])),
            'closing' => OfferLetter::fill($settings['relieving_closing'], $values),
            'signoff' => implode('<br>', array_map(fn ($l) => OfferLetter::fill($l, $values), OfferLetter::lines($settings['relieving_signoff']))),
            'logo' => OfferLetter::logo($forPdf),
            'stamp' => OfferLetter::stamp($settings, $forPdf),
            'name' => $e->name,
        ];
    }

    /** 11-08-2025 to 20-08-2026 -> "1 Year"; "2 Years 3 Months"; "8 Months"; under a month, days. */
    public static function tenure($from, $to): string
    {
        if (!$from || !$to) {
            return '';
        }

        $diff = Carbon::parse($from)->startOfDay()->diff(Carbon::parse($to)->startOfDay()->addDay());
        $parts = [];

        if ($diff->y) {
            $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'Year' : 'Years');
        }

        if ($diff->m) {
            $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'Month' : 'Months');
        }

        return $parts ? implode(' ', $parts) : max(1, $diff->d) . ' Days';
    }

    public static function html(?object $letter, bool $forPdf, ?array $settings = null, ?array $details = null): string
    {
        return view('relieving-letters.letter', self::data($letter, $forPdf, $settings, $details))->render();
    }

    public static function pdf(object $letter)
    {
        return Pdf::loadHTML(self::html($letter, true))->setPaper('a4')->setOption('dpi', 96)
            ->setOption('isRemoteEnabled', true)->setOption('isFontSubsettingEnabled', true)
            ->setOption('chroot', [base_path(), storage_path()]);
    }

    public static function fileName(object $letter): string
    {
        $name = $letter->user_id ? DB::table('users')->where('id', $letter->user_id)->value('name') : ($letter->person_name ?? 'Employee');

        return 'Relieving Letter - ' . preg_replace('/[^A-Za-z0-9 ._-]+/', '', (string) $name) . '.pdf';
    }

    /**
     * The PDF kept with the employee's documents (one per letter) and the
     * exit's "Relieving letter issued" ticked.
     */
    public static function keep(object $letter, string $pdfBinary): void
    {
        $name = 'Relieving Letter ' . Carbon::parse($letter->letter_date)->format('d-m-Y') . ' #' . $letter->id;

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
                ->whereIn('item_key', ['relieving_letter', 'experience_letter'])->where('status', '<>', 'completed')
                ->update(['status' => 'completed', 'completed_by' => user()?->id, 'completed_date' => now()->toDateString(), 'updated_at' => now()]);
        }
    }

}
