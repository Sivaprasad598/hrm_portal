<?php

namespace App\Services\Employee;

use App\Models\EmployeeDocument;
use App\Models\EmpJoiningChecklist;
use App\Helper\Files;
use App\Services\Payroll\SalaryCalculator;
use App\Services\Payroll\SalaryRevisionLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An employee's offer letter: the letter on the letterhead and Annexure I, the
 * salary structure, as payroll calculates it (SalaryCalculator).
 *
 * Every word of it comes from Settings -> Offer Letter Settings (defaults: the
 * company's own letter), with placeholders filled in per employee:
 *   {salutation} {name} {designation} {location} {joining_date} {ctc}
 *   {ctc_words} {probation} {notice_probation} {notice_confirmed} {date}
 *   {reference} {company}
 * and **text** for bold.
 *
 * The probation comes from the employee's joining date to probation end date,
 * the notice period after confirmation from the one chosen when they were
 * registered, the notice during probation from the settings.
 *
 * Each letter issued is kept (offer_letters) with its reference number, so a
 * reprint keeps its number and the next is suggested from the last:
 * 0024/2026 -> 0025/2026, and 0001/2027 in a new year.
 */
class OfferLetter
{

    public const PLACEHOLDERS = [
        '{salutation}' => 'Mr. / Ms.',
        '{name}' => 'Employee name',
        '{designation}' => 'Designation',
        '{location}' => 'Branch (where they are based)',
        '{joining_date}' => 'Date of joining',
        '{ctc}' => 'Annual CTC, e.g. Rs. 2,68,140/-',
        '{ctc_words}' => 'Annual CTC in words',
        '{probation}' => 'Probation, e.g. 1 month (joining date to probation end date)',
        '{notice_probation}' => 'Notice during probation (settings)',
        '{notice_confirmed}' => 'Notice after confirmation (Settings -> Notice Period Settings)',
        '{date}' => 'Letter date',
        '{reference}' => 'Reference number',
        '{company}' => 'Company name',
        '{issuer_name}' => 'Who generated the letter, e.g. Mr. B. Hari Kiran',
        '{issuer_designation}' => 'Their designation',
    ];

    /** The company's own letter, word for word: what a new installation starts with. */
    public const DEFAULTS = [
        'reference_format' => '{seq}/{year}',
        'reference_digits' => 4,
        'notice_probation_days' => 15,
        'notice_unit' => 'days',
        'subject' => 'Offer Letter for the Position of {designation}',
        'paragraphs' => "We are pleased to offer you the position of **{designation}** at our organization, based in **{location}**.\n\n"
            . "Your date of joining will be **{joining_date}**.\n\n"
            . "As agreed, your Annual CTC will be **{ctc} ({ctc_words})**.\nOther terms of employment are detailed in the ACPL employee handbook which would be issued on the date of joining.\n\n"
            . "You will be on probation for a period of {probation} from the date of joining. Based on your performance, your services will be regularized. While we hope that this professional relationship will be mutually beneficial, nevertheless the notice period for termination of employment is {notice_probation} during the probation period and {notice_confirmed} during the permanent employment from either side.\n\n"
            . "Your compensation package is confidential and in case of any clarifications you are encouraged to discuss this with the Human Resources Department. Please sign and return this document by email as a token of your acceptance.\n\n"
            . "On the date of Joining **Arowana Corporations Pvt Ltd,** you are requested to bring the photocopies of the following documents for our records along with the originals for our verification.",
        'documents' => "Appointment, Relieving & Experience Letter from your last employer.\n"
            . "Latest / Last 3 Months Salary Pay slips from your last employer.\n"
            . "Your Salary Account Bank Statement for the last three months\n"
            . "All Educational Qualification Certificates\n"
            . "Pan Card & Aadhar Card\n"
            . "3 Latest Passport size Photographs",
        // Used in place of the paragraph with {probation} when there is no probation.
        'no_probation_paragraph' => 'There is no probation period for this position. While we hope that this professional relationship will be mutually beneficial, nevertheless the notice period for termination of employment is {notice_confirmed} from either side.',
        'note' => 'Note- All the employment terms of the offer letter shall be applicable & valid subject to clearance of Background Verification.',
        'closing' => 'Best Regards,',
        'signoff' => "{issuer_name}
{issuer_designation}
Human Resource Department.",
        'enclosure' => 'Encl.: Annexure – I – Salary Structure',
        'annexure_title' => 'Annexure – I - Salary Structure',
        'annexure_company' => 'AROWANA CORPORATIONS PRIVATE LIMITED',
        'annexure_heading' => 'DETAILED COMPENSATION STRUCTURE',
        'acknowledgement' => 'Kindly acknowledge the receipt of this letter and return a signed copy as an acceptance of the offer.',
        'acceptance' => 'I ______________________ acknowledge that I have read, understood, and accept this offer and the terms and conditions contained in the attached annexure and agree to be bound by the terms and conditions of employment as outlined therein.',
        'show_zero_rows' => 1,
        // Both letters: the signature & stamp image (uploaded here, else public/img/sign and stamp.png).
        'show_stamp' => 0,
        'stamp_image' => '',
        // The relieving cum experience letter (RelievingLetter): exited employees only.
        'relieving_title' => 'Relieving cum Experience Letter',
        'relieving_greeting' => 'Dear {name},',
        'relieving_paragraphs' => "We confirm that **{salutation} {name}** was employed with Arowana Corporations Pvt. Ltd. from {joining_date} to {relieving_date} and {his} employment particulars are as below\n\n"
            . "Full Name: **{salutation} {name}**\n\n"
            . "Designation at the time of relieving: {designation}\n\n"
            . "Department: {department}\n\n"
            . "Employment Tenure: {tenure}.\n\n"
            . "During {his} tenure with us, {his} performance was good and to the satisfaction of the management. We wish {him} all the success in {his} future endeavors.",
        'relieving_closing' => 'Best Regards,',
        'relieving_signoff' => "HR Department,\nArowana Corporations Pvt.Ltd.",
        'relieving_date_format' => 'd-m-Y',
        // The increment / hike letter (SalaryRevisionLetter), one per salary revision.
        'hike_title' => 'INCREMENT LETTER',
        'hike_subject' => 'Salary Revision Letter',
        'hike_greeting' => 'Dear {name},',
        'hike_intro' => "We are pleased to inform you that, in recognition of your performance, dedication, and valuable contributions to the organization, the management has decided to revise your salary.\n"
            . 'Your revised compensation will be effective from **{effective_date}**.',
        'hike_promotion' => 'You have also been promoted to the position of **{new_designation}**.',
        'hike_arrears' => 'Arrears of **Rs. {arrears}** {arrears_note}.',
        'hike_table_intro' => 'Please find the details of your salary revision below:',
        'hike_closing' => "This salary revision is based on the organization's compensation policies and management approval.\n"
            . "We appreciate your continued commitment and contribution to Arowana Corporation Pvt. Ltd. We look forward to your continued growth and success with the organization.\n"
            . "Please acknowledge receipt and acceptance of this letter.\n"
            . 'Wishing you all the best in your future endeavors with us.',
        'hike_signoff' => "{signatory_name},\n{signatory_designation},\n**Arowana Corporation Private Limited.**",
        'hike_ack_title' => 'Employee Acknowledgement',
        'hike_ack_text' => 'I hereby acknowledge and accept the revised salary terms mentioned above.',
        'hike_date_format' => 'd/m/Y',
        'footer_company' => 'AROWANA CORPORATIONS PRIVATE LIMITED',
        'footer_address' => '#26-2-33/1, Snehapuri Colony, Chinagantyada, Gajuwaka, Visakhapatnam - 530026, Andhra Pradesh, INDIA.',
        'footer_phone' => '+91 83749 25568',
        'footer_website' => 'www.arowanasoft.com',
        'footer_email' => 'info@arowanasoft.com',
    ];

    // ------------------------------------------------------------------ settings

    public static function settings(?int $companyId = null): array
    {
        $companyId ??= company()?->id;
        $stored = Schema::hasTable('offer_letter_settings')
            ? DB::table('offer_letter_settings')->where('company_id', $companyId)->value('content')
            : null;
        $stored = $stored ? (json_decode($stored, true) ?: []) : [];

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public static function saveSettings(array $values, ?int $companyId = null): void
    {
        $companyId ??= company()?->id;
        $content = array_merge(self::DEFAULTS, array_intersect_key($values, self::DEFAULTS));
        $content['reference_digits'] = max(1, min(8, (int) $content['reference_digits']));
        $content['notice_probation_days'] = max(0, (int) $content['notice_probation_days']);
        $content['notice_unit'] = in_array($content['notice_unit'], ['days', 'months'], true) ? $content['notice_unit'] : 'days';
        $content['show_zero_rows'] = (int) (bool) $content['show_zero_rows'];
        $content['show_stamp'] = (int) (bool) $content['show_stamp'];

        DB::table('offer_letter_settings')->updateOrInsert(
            ['company_id' => $companyId],
            ['content' => json_encode($content, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    // ------------------------------------------------------------------ the employee

    protected static function employee(int $userId): ?object
    {
        return DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->leftJoin('branches as b', 'b.id', '=', 'ed.branch_id')
            ->leftJoin('notice_period_settings as np', 'np.id', '=', 'ed.notice_period_setting_id')
            ->where('u.id', $userId)
            ->first(['u.id', 'u.company_id', 'u.name', 'u.salutation', 'u.gender', 'u.employee_ctc', 'u.basic_for_payroll',
                'ed.employee_id as code', 'ed.address', 'ed.permanent_address', 'ed.joining_date', 'ed.probation_end_date',
                'ed.notice_period_setting_id', 'd.name as designation', 'b.branch_name as location',
                'np.name as notice_name', 'np.months as notice_months']);
    }

    /**
     * What has to be put right on the employee before a letter can be made:
     * one line each, empty when the letter can be issued.
     */
    public static function problems(int $userId): array
    {
        $e = self::employee($userId);

        if (!$e) {
            return ['The employee record was not found.'];
        }

        $problems = [];

        if (trim((string) $e->name) === '') {
            $problems[] = 'Name is empty.';
        }

        if (!self::salutation($e)) {
            $problems[] = 'Gender (or salutation) is not set - needed for Mr. / Ms.';
        }

        if (trim((string) ($e->address ?: $e->permanent_address)) === '') {
            $problems[] = 'Address is empty - the letter is addressed to it.';
        }

        if (!$e->designation) {
            $problems[] = 'Designation is not set.';
        }

        if (!$e->location) {
            $problems[] = 'Branch is not set - the letter says where they are based.';
        }

        if (!$e->joining_date) {
            $problems[] = 'Joining date is not set.';
        }

        if ((float) $e->employee_ctc <= 0) {
            $problems[] = 'Annual CTC (salary) is not set.';
        }

        // No probation (Probation Period: No) is fine: the letter says so. A date given must make sense.
        if ($e->probation_end_date && $e->joining_date && Carbon::parse($e->probation_end_date)->lte(Carbon::parse($e->joining_date))) {
            $problems[] = 'Probation end date is not after the joining date.';
        }

        // The notice after confirmation is the company's (Settings -> Notice Period Settings).
        if (self::standardNoticeMonths() === null) {
            $problems[] = 'No notice period is set up - add one in Settings -> Notice Period Settings.';
        }

        return $problems;
    }

    public static function salutation(object $e): string
    {
        $map = ['mr' => 'Mr.', 'mrs' => 'Mrs.', 'ms' => 'Ms.', 'miss' => 'Miss', 'dr' => 'Dr.', 'sir' => 'Sir', 'madam' => 'Madam'];
        $s = strtolower(rtrim(trim((string) $e->salutation), '.'));

        if (isset($map[$s])) {
            return $map[$s];
        }

        return ['male' => 'Mr.', 'female' => 'Ms.'][strtolower((string) $e->gender)] ?? '';
    }

    // ------------------------------------------------------------------ reference numbers

    /** The latest letter issued to this employee, if any. */
    public static function latestFor(int $userId): ?object
    {
        return DB::table('offer_letters')->where('user_id', $userId)->orderByDesc('id')->first();
    }

    /** The next number for this year: after 0024/2026 comes 0025/2026; a new year starts at 0001. */
    public static function suggestReference(?int $companyId = null): string
    {
        $companyId ??= company()?->id;
        $year = (int) now()->format('Y');
        $last = (int) DB::table('offer_letters')->where('company_id', $companyId)->where('year', $year)->max('seq');

        return self::formatReference($last + 1, $year, self::settings($companyId));
    }

    public static function formatReference(int $seq, int $year, array $settings): string
    {
        return strtr($settings['reference_format'], [
            '{seq}' => str_pad((string) $seq, (int) $settings['reference_digits'], '0', STR_PAD_LEFT),
            '{year}' => (string) $year,
        ]);
    }

    /** [seq, year] read back from a number in the settings' format, or null when it does not fit. */
    public static function parseReference(string $reference, array $settings): ?array
    {
        $pattern = '/^' . strtr(preg_quote($settings['reference_format'], '/'), [
            preg_quote('{seq}', '/') => '(?<seq>\d+)',
            preg_quote('{year}', '/') => '(?<year>\d{4})',
        ]) . '$/';

        if (!preg_match($pattern, trim($reference), $m) || !isset($m['seq'])) {
            return null;
        }

        return [(int) $m['seq'], isset($m['year']) ? (int) $m['year'] : (int) now()->format('Y')];
    }

    /**
     * Record a letter for the employee with the number HR confirmed.
     *
     * @return array{0: ?object, 1: ?string} the letter, or the reason it was refused
     */
    public static function issue(int $userId, string $reference): array
    {
        $e = self::employee($userId);

        return self::record((int) $e->company_id, $userId, 'employee', self::detailsFor($userId), $reference, now()->toDateString());
    }

    /**
     * A letter from the Letter Generator form: anyone (a candidate, or an
     * employee with details changed for this letter only).
     *
     * @return array{0: ?object, 1: ?string}
     */
    public static function issueManual(array $details, string $reference, ?string $letterDate, ?int $userId = null): array
    {
        return self::record((int) company()->id, $userId, 'manual', self::cleanDetails($details), $reference,
            $letterDate ? Carbon::parse($letterDate)->toDateString() : now()->toDateString());
    }

    /** The letter row: its number checked, and what it prints kept with it. */
    protected static function record(int $companyId, ?int $userId, string $source, array $details, string $reference, string $letterDate): array
    {
        $settings = self::settings($companyId);
        $reference = trim($reference);
        $parsed = self::parseReference($reference, $settings);

        if (!$parsed) {
            return [null, 'The reference number should look like ' . self::formatReference(25, (int) now()->format('Y'), $settings) . '.'];
        }

        if (DB::table('offer_letters')->where('company_id', $companyId)->where('reference_no', $reference)->exists()) {
            return [null, 'Reference ' . $reference . ' is already used. The next free one is ' . self::suggestReference($companyId) . '.'];
        }

        $id = DB::table('offer_letters')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $userId,
            'source' => $source,
            'person_name' => trim(($details['salutation'] ?? '') . ' ' . ($details['name'] ?? '')),
            'designation' => $details['designation'] ?? null,
            'location' => $details['location'] ?? null,
            'joining_date' => !empty($details['joining_date']) ? Carbon::parse($details['joining_date'])->toDateString() : null,
            'annual_ctc' => (float) ($details['ctc'] ?? 0) ?: null,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'reference_no' => $reference,
            'seq' => $parsed[0],
            'year' => $parsed[1],
            'letter_date' => $letterDate,
            'generated_by' => user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [DB::table('offer_letters')->find($id), null];
    }

    /** What an employee's letter prints, as the form holds it: the generator's prefill and the kept copy. */
    public static function detailsFor(int $userId): array
    {
        $e = self::employee($userId);
        $settings = self::settings($e?->company_id);

        return [
            'salutation' => self::salutation($e),
            'name' => (string) $e->name,
            'address' => trim((string) ($e->address ?: $e->permanent_address)),
            'designation' => (string) $e->designation,
            'location' => (string) $e->location,
            'joining_date' => $e->joining_date ? Carbon::parse($e->joining_date)->toDateString() : '',
            'ctc' => (float) $e->employee_ctc,
            'basic_pct' => $e->basic_for_payroll !== null ? (float) $e->basic_for_payroll : null,
            'probation' => self::probation($e) ?: 'No probation',
            'notice_probation' => self::period((int) $settings['notice_probation_days'], 'days'),
            // Not chosen on the employee: left empty, so the generator keeps its default.
            'notice_confirmed' => self::noticeConfirmed($e, $settings),
        ];
    }

    /** The form's details, trimmed to what the letter uses. */
    public static function cleanDetails(array $d): array
    {
        $text = fn ($k) => trim((string) ($d[$k] ?? ''));

        return array_filter([
            'salutation' => $text('salutation'),
            'name' => $text('name'),
            'address' => str_replace("\r", '', $text('address')),
            'designation' => $text('designation'),
            'location' => $text('location'),
            'joining_date' => $text('joining_date'),
            'ctc' => round((float) ($d['ctc'] ?? 0), 2),
            'basic_pct' => ($d['basic_pct'] ?? '') !== '' && $d['basic_pct'] !== null ? round((float) $d['basic_pct'], 2) : null,
            'probation' => $text('probation'),
            'notice_probation' => $text('notice_probation'),
            'notice_confirmed' => $text('notice_confirmed'),
            // This letter's own wording, when changed on the form.
            'paragraphs' => $text('paragraphs') !== '' ? str_replace("\r", '', $text('paragraphs')) : null,
        ], fn ($v) => $v !== null);
    }

    /** What is missing on the form, one line each. */
    public static function formProblems(array $d): array
    {
        $problems = [];

        foreach (['name' => 'Name', 'address' => 'Address', 'designation' => 'Designation', 'location' => 'Location (based in)',
            'joining_date' => 'Joining date', 'probation' => 'Probation', 'notice_probation' => 'Notice during probation',
            'notice_confirmed' => 'Notice after confirmation'] as $key => $label) {
            if (trim((string) ($d[$key] ?? '')) === '') {
                $problems[] = $label . ' is empty.';
            }
        }

        if ((float) ($d['ctc'] ?? 0) <= 0) {
            $problems[] = 'Annual CTC is empty.';
        }

        if (!empty($d['joining_date']) && !strtotime($d['joining_date'])) {
            $problems[] = 'Joining date is not a date.';
        }

        return $problems;
    }

    /** The details kept with a letter, as the employee-like record the letter is drawn from. */
    protected static function fromDetails(array $d): object
    {
        return (object) [
            'name' => $d['name'] ?? '', 'salutation' => $d['salutation'] ?? null, 'gender' => null,
            'employee_ctc' => (float) ($d['ctc'] ?? 0), 'basic_for_payroll' => $d['basic_pct'] ?? null, 'code' => null,
            'address' => $d['address'] ?? '', 'permanent_address' => null, 'joining_date' => $d['joining_date'] ?? null,
            'probation_end_date' => null, 'designation' => $d['designation'] ?? '', 'location' => $d['location'] ?? '',
            'notice_name' => null, 'notice_months' => null,
            'probation_text' => $d['probation'] ?? '', 'notice_probation_text' => $d['notice_probation'] ?? '',
            'notice_confirmed_text' => $d['notice_confirmed'] ?? '', 'paragraphs' => $d['paragraphs'] ?? null,
        ];
    }

    // ------------------------------------------------------------------ the letter

    /** Everything the letter shows. $letter: the issued letter (null: a preview with sample data). */
    public static function data(?object $letter, bool $forPdf, ?array $settings = null, ?int $userId = null, ?array $details = null): array
    {
        // What it prints: the copy kept with the letter, the form's details, or the employee as they are.
        $kept = $letter && !empty($letter->details) ? json_decode($letter->details, true) : null;
        $e = match (true) {
            (bool) $kept => self::fromDetails($kept),
            $details !== null => self::fromDetails(self::cleanDetails($details)),
            $letter && $letter->user_id => self::employee((int) $letter->user_id),
            (bool) $userId => self::employee($userId),
            default => null,
        };
        $settings ??= self::settings($letter->company_id ?? null);

        // The preview in Settings: a made-up employee, so every placeholder shows.
        if (!$e) {
            $e = (object) [
                'name' => 'Afajl Hussain', 'salutation' => null, 'gender' => 'male', 'employee_ctc' => 268140, 'basic_for_payroll' => null,
                'code' => 'ACPL0000', 'address' => "Teekarmafi,\nAmethi,\nUP.", 'permanent_address' => null,
                'joining_date' => now()->addDays(7)->toDateString(), 'probation_end_date' => now()->addDays(7)->addMonth()->toDateString(),
                'designation' => 'Sr. DGPS Surveyor', 'location' => 'Vijayawada', 'notice_name' => 'Actual Notice Period', 'notice_months' => 2,
            ];
        }

        $letterDate = Carbon::parse($letter->letter_date ?? now());
        $joining = $e->joining_date ? Carbon::parse($e->joining_date) : null;
        $ctc = (float) $e->employee_ctc;
        $salutation = self::salutation($e);
        $reference = $letter->reference_no ?? self::suggestReference();
        $probationText = isset($e->probation_text) ? $e->probation_text : self::probation($e);
        // The form's "No probation", or an employee without a probation end date (Probation Period: No).
        // A form still being filled in (nothing typed yet) keeps the probation paragraph.
        $noProbation = isset($e->probation_text) ? (trim($probationText) !== '' && self::noProbation($probationText)) : self::noProbation($probationText);

        // Signed off by whoever generated the letter (the person previewing, in Settings).
        $issuer = DB::table('users as u')->leftJoin('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->where('u.id', $letter->generated_by ?? user()?->id)
            ->first(['u.name', 'u.salutation', 'u.gender', 'd.name as designation']);

        $values = [
            '{salutation}' => e($salutation),
            '{name}' => e($e->name),
            '{designation}' => e((string) $e->designation),
            '{location}' => e((string) $e->location),
            '{joining_date}' => $joining ? self::ordinalDate($joining) : '',
            '{ctc}' => 'Rs. ' . SalaryRevisionLetter::inr($ctc) . '/-',
            '{ctc_words}' => e(SalaryRevisionLetter::words($ctc) . ' Rupees Only'),
            '{probation}' => e($probationText),
            '{notice_probation}' => e(isset($e->notice_probation_text) ? $e->notice_probation_text : self::period((int) $settings['notice_probation_days'], 'days')),
            '{notice_confirmed}' => e(isset($e->notice_confirmed_text) ? $e->notice_confirmed_text : self::noticeConfirmed($e, $settings)),
            '{date}' => self::ordinalDate($letterDate),
            '{reference}' => e($reference),
            '{company}' => e($settings['footer_company']),
            '{issuer_name}' => $issuer ? e(trim(self::salutation($issuer) . ' ' . $issuer->name)) : '',
            '{issuer_designation}' => e((string) ($issuer->designation ?? '')),
        ];

        return [
            's' => $settings,
            'e' => $e,
            'forPdf' => $forPdf,
            'reference' => $reference,
            'letterDate' => self::ordinalDate($letterDate),
            'addressee' => trim($salutation . ' ' . $e->name),
            'address' => trim((string) ($e->address ?: $e->permanent_address)),
            'joiningDate' => $joining ? self::ordinalDate($joining, true) : '',
            'subject' => self::fill($settings['subject'], $values),
            // No probation: the paragraph about it gives way to the one for no probation (Letter Settings).
            'paragraphs' => array_map(fn ($p) => self::fill($p, $values), array_map(
                fn ($p) => $noProbation && str_contains($p, '{probation}') ? $settings['no_probation_paragraph'] : $p,
                self::blocks(!empty($e->paragraphs) ? $e->paragraphs : $settings['paragraphs']))),
            'documents' => array_map(fn ($d) => self::fill($d, $values), self::lines($settings['documents'])),
            'note' => self::fill($settings['note'], $values),
            'closing' => self::fill($settings['closing'], $values),
            // One line each; a line left empty (no designation on record) is dropped.
            'signoff' => implode('<br>', array_filter(array_map(fn ($l) => self::fill($l, $values), self::lines($settings['signoff'])),
                fn ($l) => trim(strip_tags($l)) !== '')),
            'enclosure' => self::fill($settings['enclosure'], $values),
            'acknowledgement' => self::fill($settings['acknowledgement'], $values),
            'acceptance' => self::fill($settings['acceptance'], $values),
            'pay' => self::pay($ctc, $e->basic_for_payroll !== null ? (float) $e->basic_for_payroll : null),
            'logo' => self::logo($forPdf),
            'stamp' => self::stamp($settings, $forPdf),
            'inr' => fn ($v) => SalaryRevisionLetter::inr($v),
        ];
    }

    /**
     * Annexure I as payroll calculates the month (SalaryCalculator, a full
     * month, no loss of pay): each amount to the rupee, Special what is left
     * of gross so the column adds up, and the net exactly as the payslip
     * computes it (the PF and ESI payroll deducts, and PT).
     */
    public static function pay(float $ctc, ?float $basicPct): array
    {
        $c = (new SalaryCalculator())->forEmployee($ctc, $basicPct, 30, 30.0);
        $gross = round($c['gross']);
        $earnings = [];

        foreach (['basic' => 'Basic Salary', 'hra' => 'House Rent Allowance (HRA)', 'ca' => 'Conveyance Allowance',
            'lta' => 'Leave Travel Allowance', 'cea' => 'Children’s Education Allowance'] as $key => $label) {
            $earnings[$label] = round((float) $c[$key]);
        }
        $earnings['Special Allowance'] = max(0, $gross - array_sum($earnings));

        $pfEmployer = round((float) $c['pf_employer']);
        $esiEmployer = round((float) $c['esi_employer']);
        $employerTotal = $pfEmployer + $esiEmployer;

        $net = round((float) $c['net']);
        $pfEmployee = round((float) $c['pf_employee']);
        $esiEmployee = round((float) $c['esi_employee']);
        $pt = round((float) $c['pt']);
        $deducted = $gross - $net;

        $deductions = ['Provident Fund - Employee\'s Share' => $pfEmployee, 'ESIC - Employee\'s Share' => $esiEmployee];

        // Payroll recovers the employer's PF / ESI from the salary as well
        // (SalaryCalculator: before tax = gross - PF - ESI - PT, both sides):
        // shown as their own lines so the column adds up to the net.
        $recovered = max(0, $deducted - $pfEmployee - $esiEmployee - $pt);
        if ($recovered >= 1) {
            $deductions['Provident Fund / ESIC - Employer\'s Share (recovered)'] = $recovered;
        }
        $deductions['Professional Tax'] = $pt;

        return [
            'earnings' => $earnings,
            'gross' => $gross,
            'employer' => ['Provident Fund - Employer\'s Share' => $pfEmployer, 'ESIC - Employer\'s Share' => $esiEmployer],
            'employer_total' => $employerTotal,
            'fixed_ctc' => $gross + $employerTotal,
            'deductions' => $deductions,
            'deductions_total' => $deducted,
            'net' => $net,
        ];
    }

    public static function html(?object $letter, bool $forPdf, ?array $settings = null, ?int $userId = null, ?array $details = null): string
    {
        return view('offer-letters.letter', self::data($letter, $forPdf, $settings, $userId, $details))->render();
    }

    public static function pdf(object $letter)
    {
        return Pdf::loadHTML(self::html($letter, true))->setPaper('a4')->setOption('dpi', 96)
            ->setOption('isRemoteEnabled', true)->setOption('isFontSubsettingEnabled', true)
            ->setOption('chroot', [base_path(), storage_path()]);
    }

    public static function fileName(object $letter): string
    {
        $name = $letter->user_id ? DB::table('users')->where('id', $letter->user_id)->value('name') : ($letter->person_name ?? 'Candidate');

        return 'Offer Letter - ' . preg_replace('/[^A-Za-z0-9 ._-]+/', '', (string) $name) . ' - '
            . str_replace('/', '-', $letter->reference_no) . '.pdf';
    }

    /**
     * The PDF kept with the employee's documents (Checklist - Offer Letter,
     * one per reference number) and the joining checklist's "Offer letter"
     * ticked.
     */
    public static function keep(object $letter, string $pdfBinary): void
    {
        $name = 'Offer Letter ' . $letter->reference_no;

        // A candidate's letter (no employee yet) has no documents to go into.
        if (!$letter->user_id || EmployeeDocument::where('user_id', $letter->user_id)->where('name', $name)->exists()) {
            return;
        }

        $temp = tempnam(sys_get_temp_dir(), 'offer');
        file_put_contents($temp, $pdfBinary);

        try {
            $file = new UploadedFile($temp, self::fileName($letter), 'application/pdf', null, true);
            $hashname = Files::uploadLocalOrS3($file, EmployeeDocument::FILE_PATH . '/' . $letter->user_id);

            $doc = new EmployeeDocument();
            $doc->company_id = $letter->company_id;
            $doc->user_id = $letter->user_id;
            $doc->name = $name;
            $doc->category = OnboardingDocuments::CATEGORY_CHECKLIST;
            $doc->checklist_key = 'offer_letter';
            $doc->employment_period_id = OnboardingDocuments::currentPeriodId((int) $letter->user_id);
            $doc->filename = self::fileName($letter);
            $doc->hashname = $hashname;
            $doc->size = strlen($pdfBinary);
            $doc->mime = 'application/pdf';
            $doc->added_by = user()?->id;
            $doc->last_updated_by = user()?->id;
            $doc->save();
        } finally {
            @unlink($temp);
        }

        EmpJoiningChecklist::where('user_id', $letter->user_id)->update(['offer_letter' => 1]);
    }

    // ------------------------------------------------------------------ helpers

    /** Joining date to probation end date: "1 month", "3 months", or "45 days" when not whole months. */
    protected static function probation(object $e): string
    {
        if (!$e->joining_date || !$e->probation_end_date) {
            return '';
        }

        $from = Carbon::parse($e->joining_date)->startOfDay();
        $to = Carbon::parse($e->probation_end_date)->startOfDay();

        for ($m = 1; $m <= 36; $m++) {
            $at = $from->copy()->addMonthsNoOverflow($m);

            // 8 Oct -> 8 Nov is a month; so is 8 Oct -> 7 Nov (the day before).
            if ($at->equalTo($to) || $at->copy()->subDay()->equalTo($to)) {
                return self::period($m, 'months');
            }
        }

        return self::period((int) $from->diffInDays($to), 'days');
    }

    /**
     * The notice after confirmation: the company's standard one (Settings ->
     * Notice Period Settings, the longest set there - "Actual Notice Period"),
     * not something chosen per employee.
     */
    protected static function noticeConfirmed(object $e, array $settings): string
    {
        $months = (int) (self::standardNoticeMonths() ?? 0);

        if ($months <= 0) {
            return 'no notice';
        }

        return $settings['notice_unit'] === 'months' ? self::period($months, 'months') : self::period($months * 30, 'days');
    }

    /** The company's standard notice in months, or null when none is set up. */
    public static function standardNoticeMonths(): ?int
    {
        static $months = false;

        if ($months === false) {
            $value = DB::table('notice_period_settings')->where('company_id', company()?->id)->max('months');
            $months = $value === null ? null : (int) $value;
        }

        return $months;
    }

    /** "No probation" (the generator's wording), or empty: either means there is none. */
    public static function noProbation(?string $text): bool
    {
        $t = strtolower(trim((string) $text));

        return $t === '' || in_array($t, ['no', 'none', 'nil', 'no probation', '0', '0 months', '0 days'], true);
    }

    public static function period(int $n, string $unit): string
    {
        $one = rtrim($unit, 's');

        return $n . ' ' . ($n === 1 ? $one : $one . 's');
    }

    /** 1 -> 1<sup>st</sup> October 2026 (as the company's letters write it); $pad: 08th. */
    public static function ordinalDate(Carbon $date, bool $pad = false): string
    {
        return ($pad ? $date->format('d') : $date->format('j')) . '<sup>' . $date->format('S') . '</sup> ' . $date->format('F Y');
    }

    /** Settings text to safe HTML: escaped, placeholders filled, **bold**, line breaks kept. */
    public static function fill(string $text, array $values): string
    {
        // Bold first, around the placeholders themselves: an empty value (a form not filled in
        // yet) then leaves an empty <strong>, where filling first left "****" that paired up
        // with the next ** and bolded the wrong words. An empty value shows as a blank line.
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($text));
        $blank = '<span style="color:#9aa5b1">__________</span>';
        // (The signer's own lines stay empty, so a sign-off line with nothing in it is left out.)
        $html = strtr($html, array_combine(array_keys($values), array_map(
            fn ($k, $v) => trim(strip_tags((string) $v)) === '' && !str_starts_with($k, '{issuer') && !str_starts_with($k, '{signatory') ? $blank : $v,
            array_keys($values), $values)));

        return nl2br($html, false);
    }

    /** Paragraphs: separated by a blank line. */
    public static function blocks(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\R\s*\R/", str_replace("\r", '', $text))), 'strlen'));
    }

    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), 'strlen'));
    }

    /** The signature & stamp when switched on: the uploaded one, else the company's (public/img/sign and stamp.png). */
    public static function stamp(array $settings, bool $forPdf): ?string
    {
        if (empty($settings['show_stamp'])) {
            return null;
        }

        $relative = $settings['stamp_image'] && is_file(public_path($settings['stamp_image'])) ? $settings['stamp_image'] : 'img/sign and stamp.png';

        if (!is_file(public_path($relative))) {
            return null;
        }

        return $forPdf ? self::scaled($relative, 420) : asset(str_replace(' ', '%20', $relative));
    }

    /**
     * An image from public/ for the PDF, scaled down once and cached (the stamp
     * is 2 MB as uploaded and made every letter 2 MB): a path on disk.
     */
    protected static function scaled(string $relative, int $width): string
    {
        $file = public_path($relative);
        $cache = storage_path('app/letter-images/' . md5($relative . filemtime($file) . $width) . '.png');

        if (!is_file($cache) && function_exists('imagecreatefromstring')) {
            if (!is_dir(dirname($cache))) {
                mkdir(dirname($cache), 0775, true);
            }
            $src = @imagecreatefromstring(file_get_contents($file));

            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $nw = min($width, $w);
                $nh = (int) round($h * $nw / $w);
                $dst = imagecreatetruecolor($nw, $nh);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagepng($dst, $cache, 6);
            }
        }

        return str_replace('\\', '/', is_file($cache) ? $cache : $file);
    }

    public static function logo(bool $forPdf): ?string
    {
        $file = public_path('img/arowana_logo_new.png');

        if (!is_file($file)) {
            return null;
        }

        return $forPdf ? self::scaled('img/arowana_logo_new.png', 700) : asset('img/arowana_logo_new.png');
    }

}
