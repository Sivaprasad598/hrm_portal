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

    public function html(array $revisionIds): string
    {
        $pages = array_map(fn ($id) => view('salary-revisions.letter', $this->data((int) $id))->render(), $revisionIds);

        return view('salary-revisions.letter-document', ['pages' => $pages])->render();
    }

    public function pdf(array $revisionIds)
    {
        return Pdf::loadHTML($this->html($revisionIds))->setPaper('a4')->setOption('dpi', 96)->setOption('isRemoteEnabled', true)
            ->setOption('chroot', [base_path(), storage_path()]);
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
