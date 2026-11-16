<?php

namespace App\Services\Payroll;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The letter for a salary revision: hike, promotion with a hike, promotion
 * alone, or a correction - on the letterhead, with the old and new pay side by
 * side in an annexure and an acknowledgement for the employee to sign.
 */
class SalaryRevisionLetter
{

    /** Everything the letter shows, for one revision. */
    public function data(int $revisionId): array
    {
        $r = DB::table('salary_revisions as r')->join('salary_revision_batches as b', 'b.id', '=', 'r.batch_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as od', 'od.id', '=', 'r.old_designation_id')->leftJoin('designations as nd', 'nd.id', '=', 'r.new_designation_id')
            ->leftJoin('teams as ot', 'ot.id', '=', 'r.old_department_id')->leftJoin('teams as nt', 'nt.id', '=', 'r.new_department_id')
            ->where('r.id', $revisionId)
            ->first(['r.*', 'b.name as batch_name', 'b.effective_date', 'b.applies_from', 'b.status as batch_status', 'b.company_id as batch_company',
                'u.name', 'u.salutation', 'ed.employee_id as code', 'od.name as old_designation', 'nd.name as new_designation',
                'ot.team_name as old_department', 'nt.team_name as new_department']);
        abort_if(!$r, 404);

        $company = \App\Models\Company::find($r->batch_company);
        $address = DB::table('company_addresses')->where('company_id', $r->batch_company)->orderByDesc('is_default')->value('address');
        $settings = (new SalaryRevisions())->settings($r->batch_company);
        $old = SalaryRevisions::monthly((float) $r->old_ctc, $r->old_basic_pct !== null ? (float) $r->old_basic_pct : null);
        $new = SalaryRevisions::monthly((float) $r->new_ctc, $r->new_basic_pct !== null ? (float) $r->new_basic_pct : ($r->old_basic_pct !== null ? (float) $r->old_basic_pct : null));
        $promoted = (bool) $r->new_designation_id;
        $ctcChanged = abs((float) $r->new_ctc - (float) $r->old_ctc) >= 1;
        $increase = (float) $r->new_ctc - (float) $r->old_ctc;

        $subject = $promoted && $ctcChanged ? 'Promotion and Revision of Compensation'
            : ($promoted ? 'Promotion' : 'Revision of Compensation');

        return [
            'r' => $r, 'company' => $company, 'address' => preg_replace('/^Address:\s*/i', '', (string) $address), 'settings' => $settings,
            'old' => $old, 'new' => $new, 'promoted' => $promoted, 'ctcChanged' => $ctcChanged, 'increase' => $increase,
            'pct' => (float) $r->old_ctc > 0 ? $increase / (float) $r->old_ctc * 100 : 0, 'subject' => $subject,
            'salutation' => $r->salutation ? ucfirst(rtrim($r->salutation, '.')) . '. ' : '',
            'firstName' => trim(explode(' ', trim($r->name))[0]),
            'effective' => Carbon::parse($r->effective_date), 'payMonth' => SalaryRevisions::monthLabel(Carbon::parse($r->applies_from)),
            'letterDate' => Carbon::parse($r->letter_date ?: now()),
            'logo' => $this->image('img/arowana_logo_new.png', 700), 'stamp' => $this->image('img/sign and stamp.png', 420),
            'inr' => fn ($v) => self::inr($v), 'words' => fn ($v) => self::words($v),
            'draft' => $r->batch_status !== 'approved',
        ];
    }

    /**
     * The letters, in the company's increment letter format on the shared
     * letterhead (Settings -> Letter Settings -> Hike Letter), one page each.
     */
    public function html(array $revisionIds, bool $forPdf = true): string
    {
        $s = \App\Services\Employee\OfferLetter::settings();
        $pages = array_map(fn ($id) => self::hikePage($this->hikeFields($this->data((int) $id)), $s, $forPdf), $revisionIds);

        return view('salary-revisions.hike-document', ['pages' => $pages, 's' => $s, 'forPdf' => $forPdf])->render();
    }

    public function pdf(array $revisionIds)
    {
        return Pdf::loadHTML($this->html($revisionIds))->setPaper('a4')->setOption('dpi', 96)->setOption('isRemoteEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)->setOption('chroot', [base_path(), storage_path()]);
    }

    /** What the hike letter shows, from one revision's data(). */
    protected function hikeFields(array $d): array
    {
        $r = $d['r'];

        return [
            'name' => $r->name, 'code' => $r->code, 'gender' => null,
            // As they were before the revision: the letter is addressed to the person as they are.
            'designation' => $r->old_designation ?: $r->new_designation, 'department' => $r->old_department ?: $r->new_department,
            'new_designation' => $d['promoted'] ? $r->new_designation : null,
            'old_ctc' => (float) $r->old_ctc, 'new_ctc' => (float) $r->new_ctc,
            'effective' => $d['effective'], 'letter_date' => $d['letterDate'],
            'arrears' => $r->arrears_amount && abs((float) $r->arrears_amount) >= 1 ? abs((float) $r->arrears_amount) : 0,
            'arrears_note' => (string) $r->arrears_note,
            'signatory_name' => $d['settings']->signatory_name ?: 'Authorised Signatory',
            'signatory_designation' => $d['settings']->signatory_designation ?: '',
            'draft' => $d['draft'],
        ];
    }

    /** One hike letter page. $f: hikeFields(), or the sample in Letter Settings' preview. */
    public static function hikePage(array $f, array $s, bool $forPdf): string
    {
        $O = \App\Services\Employee\OfferLetter::class;
        $format = $s['hike_date_format'] ?: 'd/m/Y';
        $values = [
            '{name}' => e($f['name']),
            '{employee_id}' => e((string) $f['code']),
            '{designation}' => e((string) $f['designation']),
            '{department}' => e((string) $f['department']),
            '{new_designation}' => e((string) $f['new_designation']),
            '{effective_date}' => e($f['effective']->format($format)),
            '{date}' => e($f['letter_date']->format($format)),
            '{old_ctc}' => self::inr($f['old_ctc']),
            '{new_ctc}' => self::inr($f['new_ctc']),
            '{arrears}' => self::inr($f['arrears']),
            '{arrears_note}' => e($f['arrears_note']),
            '{signatory_name}' => e($f['signatory_name']),
            '{signatory_designation}' => e($f['signatory_designation']),
            '{company}' => e($s['footer_company']),
        ];
        $fill = fn ($key) => $O::fill((string) $s[$key], $values);

        return view('salary-revisions.hike-letter', [
            'forPdf' => $forPdf, 's' => $s, 'draft' => $f['draft'],
            'logo' => $O::logo($forPdf), 'stamp' => $O::stamp($s, $forPdf),
            'title' => $fill('hike_title'), 'subject' => $fill('hike_subject'), 'greeting' => $fill('hike_greeting'),
            'intro' => $fill('hike_intro'),
            'promotion' => $f['new_designation'] ? $fill('hike_promotion') : '',
            'arrears' => $f['arrears'] > 0 ? $fill('hike_arrears') : '',
            'tableIntro' => $fill('hike_table_intro'), 'closing' => $fill('hike_closing'),
            'signoff' => implode('<br>', array_map(fn ($l) => $O::fill($l, $values), $O::lines($s['hike_signoff']))),
            'ackTitle' => $fill('hike_ack_title'), 'ackText' => $fill('hike_ack_text'),
            'letterDate' => $f['letter_date']->format($format),
            'name' => $f['name'], 'code' => $f['code'], 'designation' => $f['designation'], 'department' => $f['department'],
            'oldCtc' => $f['old_ctc'], 'newCtc' => $f['new_ctc'],
            'oldMonthly' => round($f['old_ctc'] / 12), 'newMonthly' => round($f['new_ctc'] / 12),
            'inr' => fn ($v) => self::inr($v),
        ])->render();
    }

    /** What the hike letter's wording can use (Letter Settings -> Hike Letter). */
    public const HIKE_PLACEHOLDERS = [
        '{name}' => 'Employee name',
        '{employee_id}' => 'Employee ID',
        '{designation}' => 'Designation (before the revision)',
        '{department}' => 'Department (their Division)',
        '{new_designation}' => 'New designation, on a promotion',
        '{effective_date}' => 'Effective date',
        '{date}' => 'Letter date',
        '{old_ctc}' => 'Existing annual CTC',
        '{new_ctc}' => 'Revised annual CTC',
        '{arrears}' => 'Arrears amount, when months were already paid',
        '{arrears_note}' => 'e.g. for Oct 2026, will be paid along with your salary for November 2026',
        '{signatory_name}' => 'Signatory (Salary Revisions -> Settings)',
        '{signatory_designation}' => 'Signatory\'s designation',
        '{company}' => 'Company name (footer)',
    ];

    /** The settings preview: a sample employee, as the company's own letter. */
    public static function hikePreview(array $s): string
    {
        $f = [
            'name' => 'Gedela Naresh Kumar', 'code' => 'ACPL0367', 'designation' => 'Admin', 'department' => 'IT Department',
            'new_designation' => null, 'old_ctc' => 360000.0, 'new_ctc' => 420000.0,
            'effective' => Carbon::parse('2026-04-01'), 'letter_date' => Carbon::parse('2026-04-01'),
            'arrears' => 0, 'arrears_note' => '',
            'signatory_name' => 'G. Asha Raj', 'signatory_designation' => 'HR Operations Executive', 'draft' => false,
        ];

        return view('salary-revisions.hike-document', ['pages' => [self::hikePage($f, $s, false)], 's' => $s, 'forPdf' => false])->render();
    }

    /** 1234567.5 -> 12,34,568 (Indian grouping, whole rupees). */
    public static function inr($value): string
    {
        $n = (string) (int) round(abs((float) $value));
        $sign = (float) $value < 0 ? '-' : '';

        if (strlen($n) <= 3) {
            return $sign . $n;
        }

        $last = substr($n, -3);
        $rest = substr($n, 0, -3);

        return $sign . preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last;
    }

    /** 360000 -> "Three Lakh Sixty Thousand" */
    public static function words($value): string
    {
        $n = (int) round(abs((float) $value));

        if ($n === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
            'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn ($x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        $three = fn ($x) => trim(($x >= 100 ? $ones[intdiv($x, 100)] . ' Hundred ' : '') . $two($x % 100));

        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$unit, $name]) {
            if ($n >= $unit) {
                $parts[] = ($unit === 10000000 ? self::words(intdiv($n, $unit)) : $two(intdiv($n, $unit))) . ' ' . $name;
                $n %= $unit;
            }
        }

        if ($n > 0) {
            $parts[] = $three($n);
        }

        return trim(implode(' ', $parts));
    }

    /** An image from public/, scaled down once (a 2 MB stamp would slow every PDF). */
    protected function image(string $relative, int $width): ?string
    {
        $file = public_path($relative);

        if (!is_file($file)) {
            return null;
        }

        $cache = storage_path('app/letter-images/' . md5($relative . filemtime($file) . $width) . '.png');

        if (!is_file($cache) && function_exists('imagecreatefrompng')) {
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

        // A path on disk, as the payslips do: DomPDF reads it directly.
        return str_replace('\\', '/', is_file($cache) ? $cache : $file);
    }

}
