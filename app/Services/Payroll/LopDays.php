<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * LOP days HR decides for a pay period (Payroll Run -> Upload LOP days), kept in payroll_lop_days.
 * AttendanceDays pays the period as its days less these, so Payroll Run, the payslip and Payroll Import
 * all agree. The sheet: Employee Code | Name | LOP Days | Note (one row per employee; a blank LOP Days
 * leaves that employee to attendance, 0 means no loss of pay).
 */
class LopDays
{

    /** The sheet to fill in: every active employee, with what is already set for the period. */
    public function sheet(PayPeriod $cycle): Spreadsheet
    {
        $set = DB::table('payroll_lop_days')->where('period_start', $cycle->start->toDateString())->pluck('lop_days', 'user_id');
        $people = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')->whereNotIn('u.id', \App\Services\Access\SystemAccounts::notCounted())
            ->orderBy('ed.employee_id')->get(['u.id', 'u.name', 'ed.employee_id']);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('LOP days');
        $sheet->fromArray(['Employee Code', 'Name', 'LOP Days', 'Note'], null, 'A1');
        $row = 2;

        foreach ($people as $p) {
            $sheet->fromArray([$p->employee_id, $p->name, isset($set[$p->id]) ? (float) $set[$p->id] : null, null], null, 'A' . $row++);
        }

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (['A' => 14, 'B' => 34, 'C' => 11, 'D' => 40] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->setCellValue('F1', $cycle->describe() . ': LOP Days blank = by attendance; 0 = full pay; half days allowed (1.5)');

        return $book;
    }

    /** @return array{saved:int, cleared:int, unknown:array<int,string>, invalid:array<int,string>} */
    public function upload(string $path, PayPeriod $cycle, ?int $actorId): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $head = array_map(fn ($v) => strtolower(preg_replace('/[^a-z]/i', '', (string) $v)), $rows[0] ?? []);
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $head, true))->first(fn ($i) => $i !== false);
        $iCode = $col(['employeecode', 'empcode', 'employeeid', 'empid', 'code']);
        $iName = $col(['name', 'employeename']);
        $iLop = $col(['lopdays', 'lop']);
        $iNote = $col(['note', 'remarks', 'remark']);

        if ($iLop === null || ($iCode === null && $iName === null)) {
            throw new \RuntimeException('The sheet needs an "Employee Code" (or "Name") column and a "LOP Days" column.');
        }

        $byCode = DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')->where('u.status', 'active')
            ->get(['u.id', 'u.name', 'ed.employee_id', 'u.company_id']);
        $result = ['saved' => 0, 'cleared' => 0, 'unknown' => [], 'invalid' => []];

        foreach (array_slice($rows, 1) as $r) {
            $code = trim((string) ($iCode !== null ? ($r[$iCode] ?? '') : ''));
            $name = trim((string) ($iName !== null ? ($r[$iName] ?? '') : ''));
            $lop = trim((string) ($r[$iLop] ?? ''));

            if ($code === '' && $name === '') {
                continue;
            }

            $person = $code !== '' ? $byCode->first(fn ($p) => strcasecmp((string) $p->employee_id, $code) === 0) : null;
            $person ??= $name !== '' ? (($same = $byCode->filter(fn ($p) => strcasecmp(trim($p->name), $name) === 0))->count() === 1 ? $same->first() : null) : null;

            if (!$person) {
                $result['unknown'][] = trim($code . ' ' . $name);
                continue;
            }

            $key = ['user_id' => $person->id, 'period_start' => $cycle->start->toDateString()];

            if ($lop === '') {   // blank: back to attendance
                $result['cleared'] += DB::table('payroll_lop_days')->where($key)->delete();
                continue;
            }

            if (!is_numeric($lop) || (float) $lop < 0 || (float) $lop > $cycle->days()) {
                $result['invalid'][] = trim($code . ' ' . $name) . ": '{$lop}'";
                continue;
            }

            DB::table('payroll_lop_days')->updateOrInsert($key, [
                'company_id' => $person->company_id, 'period_end' => $cycle->end->toDateString(),
                'lop_days' => round((float) $lop, 2), 'note' => $iNote !== null ? mb_substr(trim((string) ($r[$iNote] ?? '')), 0, 255) ?: null : null,
                'created_by' => $actorId, 'updated_at' => now(), 'created_at' => now(),
            ]);
            $result['saved']++;
        }

        return $result;
    }

}
