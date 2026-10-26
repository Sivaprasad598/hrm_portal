<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Past overtime from a spreadsheet (Payroll -> OT Hours -> Import past OT):
 * one row per employee per month - or per day, when a Date is given. Every
 * row is checked first (preview); nothing is saved until the import is run,
 * and then all of it or none.
 */
class OtPastImport
{

    /** field => [heading in the template, other headings accepted] */
    public const COLUMNS = [
        'employee_code' => ['Employee ID', ['emp id', 'employee code', 'emp code', 'code']],
        'employee_name' => ['Employee Name', ['name', 'employee']],
        'year' => ['Year', []],
        'month' => ['Month', ['salary month', 'period']],
        'date' => ['Date', ['day', 'ot date']],
        'ot_hours' => ['OT Hours Banked', ['ot hours', 'ot', 'overtime', 'overtime hours', 'banked', 'banked hours', 'hours']],
        'raw_hours' => ['OT Hours Worked', ['worked hours', 'actual hours', 'raw hours']],
        'used_hours' => ['Used for LOP (hours)', ['used', 'used hours', 'lop hours', 'compensated', 'compensated hours']],
        'used_days' => ['LOP Days Covered', ['lop days', 'days covered', 'compensated days']],
        'encashed_hours' => ['Encashed Hours', ['encashed', 'paid hours']],
        'encashed_amount' => ['Encashed Amount', ['amount', 'paid amount']],
        'note' => ['Note', ['notes', 'remarks', 'comments']],
    ];

    public const MAX_ROWS = 10000;

    public function template(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Past OT');
        $sheet->fromArray(array_column(self::COLUMNS, 0), null, 'A1');
        $sheet->fromArray([
            ['EMP001', 'Sample Employee', 2025, 'Oct', '', 14.5, 12, 8, 1, 0, '', 'Monthly figure'],
            ['EMP001', 'Sample Employee', 2025, 'Nov', '', 6, '', 0, '', 4, 1250, ''],
            ['EMP002', 'Another Employee', '', '', '05-08-2025', 3, 2, '', '', '', '', 'Day-wise: Year and Month are taken from the date'],
        ], null, 'A2');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $goLive = OtPast::goLive(company()?->id);
        $help = $book->createSheet()->setTitle('How to fill');
        $help->fromArray([
            ['Column', 'What to put'],
            ['Employee ID', 'Required. The Employee ID in the portal. Employee Name is only for your reference.'],
            ['Year + Month', 'The salary month the overtime belongs to: Month as Oct, October or 10. Months up to ' . $goLive->copy()->subDay()->format('F Y') . ' (the system counts from ' . $goLive->format('d M Y') . ').'],
            ['Date', 'Leave empty for a monthly figure (the usual). Fill it (DD-MM-YYYY) only to enter a month day by day: one row per day, OT Hours Banked only.'],
            ['OT Hours Banked', 'Hours added to the bank that month, already times the rate (4h on a Sunday at 1.5x = 6). 12.5 or 12:30.'],
            ['OT Hours Worked', 'Optional: the hours actually worked, for reference only.'],
            ['Used for LOP (hours)', 'Hours taken from the bank that month to cover LOP. LOP Days Covered is optional, for reference.'],
            ['Encashed Hours / Amount', 'Hours paid out that month, and the amount paid (optional).'],
            ['Again', 'Uploading a month again replaces its figures. A month is either monthly or day by day, not both.'],
        ], null, 'A1');
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(24);
        $help->getColumnDimension('B')->setWidth(120);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/ot-past-template-' . uniqid() . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** @return array{rows: array, unknown: array, counts: array, error: ?string} */
    public function preview(string $path, int $companyId): array
    {
        try {
            $data = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return $this->fail('The file could not be read as a spreadsheet.');
        }

        $headerRow = null;

        foreach (array_slice($data, 0, 10, true) as $i => $row) {
            $m = $this->mapHeadings($row);
            if (isset($m['employee_code']) && (isset($m['ot_hours']) || isset($m['used_hours']) || isset($m['encashed_hours']))) {
                $headerRow = $i;
                break;
            }
        }

        if ($headerRow === null) {
            return $this->fail('No heading row with "Employee ID" and "OT Hours Banked" was found. Download the template and use its headings.');
        }

        $map = $this->mapHeadings($data[$headerRow]);
        $unknown = array_values(array_filter(array_map('trim', array_map('strval', array_diff_key($data[$headerRow], array_flip($map))))));
        $body = array_slice($data, $headerRow + 1, null, true);

        if (count($body) > self::MAX_ROWS) {
            return $this->fail('The sheet has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');
        }

        $employees = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->whereNotNull('ed.employee_id')->where('ed.employee_id', '<>', '')->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())
            ->get(['u.id', 'u.name', 'ed.employee_id'])->keyBy(fn ($e) => mb_strtolower(trim($e->employee_id)));
        $past = new OtPast();
        $goLive = OtPast::goLive($companyId);

        $rows = [];
        $seen = [];

        foreach ($body as $i => $raw) {
            if (implode('', array_map(fn ($v) => trim((string) $v), $raw)) === '') {
                continue;
            }

            $get = fn ($field) => isset($map[$field]) ? $raw[$map[$field]] ?? null : null;
            $row = ['line' => $i + 1, 'errors' => [], 'hints' => [], 'code' => trim((string) $get('employee_code')), 'user_id' => null, 'name' => null];

            $e = $employees[mb_strtolower($row['code'])] ?? null;
            if ($row['code'] === '') {
                $row['errors'][] = 'Employee ID is missing.';
            } elseif (!$e) {
                $row['errors'][] = 'No employee with ID ' . $row['code'] . '.';
            } else {
                $row['user_id'] = (int) $e->id;
                $row['name'] = $e->name;
            }

            // A Date makes it a day-wise row; otherwise Year + Month.
            $row['date'] = $this->date($get('date'));
            if ($row['date'] === false) {
                $row['errors'][] = 'Date not understood (use DD-MM-YYYY).';
                $row['date'] = null;
            }

            if ($row['date']) {
                $p = PayPeriod::containing(Carbon::parse($row['date']));
                [$row['year'], $row['month']] = [$p->year, $p->month];
            } else {
                [$row['year'], $row['month']] = $this->month($get('month'), $get('year'));
                if (!$row['month']) {
                    $row['errors'][] = 'Give the Year and Month (or a Date for a day-wise row).';
                }
            }

            $values = [];
            foreach (OtPast::FIELDS as $f) {
                $values[$f] = $get($f);
            }

            if ($row['date']) {
                foreach (['used_hours', 'used_days', 'encashed_hours', 'encashed_amount'] as $f) {
                    if (trim((string) $values[$f]) !== '' && (float) $values[$f] != 0) {
                        $row['errors'][] = 'A day-wise row takes OT hours only; give used / encashed hours on a monthly row.';
                        break;
                    }
                }
            }

            $clean = $past->clean($values);
            if ($clean === null) {
                $row['errors'][] = 'No hours on this row.';
                $clean = [];
            } elseif (is_string($clean)) {
                $row['errors'][] = ucfirst($clean);
                $clean = [];
            }

            if ($row['date'] && $clean && ($clean['ot_hours'] ?? 0) <= 0) {
                $row['errors'][] = 'Give the OT hours banked that day.';
            }

            $row['values'] = $clean;
            $row['net'] = round(($clean['ot_hours'] ?? 0) - ($clean['used_hours'] ?? 0) - ($clean['encashed_hours'] ?? 0), 2);

            if ($row['user_id'] && $row['month']) {
                $row['label'] = Carbon::create($row['year'], $row['month'], 1)->format('M Y');
                $this->checkAgainstSaved($row, $goLive, $companyId);

                $key = $row['user_id'] . '|' . $row['year'] . '-' . $row['month'];
                $kind = $row['date'] ? 'day' : 'month';
                $seenKind = $seen[$key]['kind'] ?? null;

                if ($seenKind && $seenKind !== $kind) {
                    $row['errors'][] = $row['label'] . ' is in the file both as a monthly figure and day by day.';
                } elseif ($kind === 'month' && $seenKind) {
                    $row['errors'][] = $row['label'] . ' is in the file twice (row ' . $seen[$key]['line'] . ').';
                } elseif ($kind === 'day' && in_array($row['date'], $seen[$key]['dates'] ?? [], true)) {
                    $row['errors'][] = Carbon::parse($row['date'])->format('d M Y') . ' is in the file twice.';
                }

                $seen[$key]['kind'] = $kind;
                $seen[$key]['line'] = $seen[$key]['line'] ?? $row['line'];
                if ($row['date']) {
                    $seen[$key]['dates'][] = $row['date'];
                }
            }

            $rows[] = $row;
        }

        $this->checkBalances($rows);

        $valid = array_filter($rows, fn ($r) => !$r['errors']);

        return [
            'rows' => $rows, 'unknown' => $unknown, 'error' => null,
            'counts' => [
                'total' => count($rows), 'valid' => count($valid), 'invalid' => count($rows) - count($valid),
                'employees' => count(array_unique(array_column($valid, 'user_id'))),
                'hours' => round(array_sum(array_column($valid, 'net')), 2),
            ],
        ];
    }

    /** Save the rows that passed: all of them, or nothing. */
    public function apply(array $rows, int $companyId): array
    {
        $past = new OtPast();
        $rows = array_filter($rows, fn ($r) => !$r['errors']);
        $months = 0;
        $days = 0;

        DB::transaction(function () use ($rows, $companyId, $past, &$months, &$days) {
            $monthly = [];

            foreach ($rows as $r) {
                if ($r['date']) {
                    $id = DB::table('ot_past')->where('user_id', $r['user_id'])->where('date', $r['date'])->value('id');
                    $past->saveDay($r['user_id'], $companyId, $r['date'], $r['values'], $id ? (int) $id : null, 'import');
                    $days++;
                } else {
                    $monthly[$r['user_id']][$r['year']][$r['month']] = $r['values'];
                }
            }

            foreach ($monthly as $userId => $years) {
                foreach ($years as $year => $list) {
                    $months += $past->saveYear((int) $userId, $companyId, (int) $year, $list, 'import');
                }
            }
        });

        return ['months' => $months, 'days' => $days, 'employees' => count(array_unique(array_column($rows, 'user_id')))];
    }

    /** Against what is saved already: go-live, the system's own days, monthly vs day by day, replacing. */
    protected function checkAgainstSaved(array &$row, Carbon $goLive, int $companyId): void
    {
        $saved = DB::table('ot_past')->where('user_id', $row['user_id'])->where('year', $row['year'])->where('month', $row['month']);

        if ($row['date']) {
            $day = Carbon::parse($row['date']);
            if ($day->gte($goLive)) {
                $row['errors'][] = 'From ' . $goLive->format('d M Y') . ' the system counts overtime itself.';
            } elseif (DB::table('ot_daily')->where('user_id', $row['user_id'])->where('date', $row['date'])->exists()) {
                $row['errors'][] = 'The system already holds overtime for this day.';
            } elseif ((clone $saved)->whereNull('date')->exists()) {
                $row['errors'][] = $row['label'] . ' is saved as a monthly figure.';
            } elseif ($old = (clone $saved)->where('date', $row['date'])->first()) {
                $row['hints'][] = 'Replaces ' . OtBank::fmt((float) $old->ot_hours) . 'h saved for this day.';
                $row['replaces'] = round($old->ot_hours - $old->used_hours - $old->encashed_hours, 2);
            }

            return;
        }

        if ($problem = (new OtPast())->monthProblem($row['user_id'], $companyId, $row['month'], $row['year'])) {
            $row['errors'][] = $problem;
        } elseif ((clone $saved)->whereNotNull('date')->exists()) {
            $row['errors'][] = $row['label'] . ' is saved day by day.';
        } elseif ($old = (clone $saved)->whereNull('date')->first()) {
            $row['hints'][] = 'Replaces the ' . OtBank::fmt((float) $old->ot_hours) . 'h saved for ' . $row['label'] . '.';
            $row['replaces'] = round($old->ot_hours - $old->used_hours - $old->encashed_hours, 2);
        }
    }

    /** No employee's bank may end below zero. */
    protected function checkBalances(array &$rows): void
    {
        $bank = new OtBank();
        $change = [];

        foreach ($rows as $r) {
            if (!$r['errors']) {
                $change[$r['user_id']] = ($change[$r['user_id']] ?? 0) + $r['net'] - ($r['replaces'] ?? 0);
            }
        }

        foreach ($change as $userId => $delta) {
            $after = $bank->balance($userId) + $delta;

            if ($after < -0.001) {
                foreach ($rows as &$r) {
                    if ($r['user_id'] === $userId && !$r['errors']) {
                        $r['errors'][] = 'Leaves this employee\'s bank at ' . OtBank::fmt(round($after, 2)) . 'h (below zero).';
                    }
                }
                unset($r);
            }
        }
    }

    /** Month as 10, "Oct", "October", "Oct 2025", "10-2025", "2025-10" or an Excel date; the year from its own column if not in it. */
    protected function month($month, $year): array
    {
        $y = is_numeric($year) ? (int) $year : null;

        if ($month === null || trim((string) $month) === '') {
            return [$y, null];
        }

        if (is_numeric($month) && $month > 20000 && $month < 80000) {
            $d = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $month));

            return [$d->year, $d->month];
        }

        $m = trim((string) $month);

        if (ctype_digit($m) && (int) $m >= 1 && (int) $m <= 12) {
            return $y ? [$y, (int) $m] : [null, null];
        }

        if (preg_match('/^(\d{1,2})[-\/.](\d{4})$/', $m, $x) && $x[1] >= 1 && $x[1] <= 12) {
            return [(int) $x[2], (int) $x[1]];
        }

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})$/', $m, $x) && $x[2] >= 1 && $x[2] <= 12) {
            return [(int) $x[1], (int) $x[2]];
        }

        if (preg_match('/^([a-z]+)[\s\-\/,\']*(\d{2,4})?$/i', $m, $x)) {
            $names = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
            $n = array_search(mb_strtolower(substr($x[1], 0, 3)), $names, true);

            if ($n !== false) {
                $yy = isset($x[2]) ? (int) $x[2] : $y;
                $yy = $yy !== null && $yy < 100 ? 2000 + $yy : $yy;

                return $yy ? [$yy, $n + 1] : [null, null];
            }
        }

        return [$y, null];
    }

    protected function date($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $value = trim((string) $value);

        foreach (array_unique([company()->date_format, 'd-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d', 'd-M-Y', 'd M Y']) as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $problems = \DateTime::getLastErrors();

            if ($date && (!$problems || ($problems['warning_count'] === 0 && $problems['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }

    protected function mapHeadings(array $headings): array
    {
        $norm = fn ($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $s)));
        $map = [];

        foreach ($headings as $i => $heading) {
            $h = $norm($heading);
            if ($h === '') {
                continue;
            }

            foreach (self::COLUMNS as $field => [$title, $aliases]) {
                if (isset($map[$field])) {
                    continue;
                }
                if ($h === $norm($title) || in_array($h, array_map($norm, $aliases), true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    protected function fail(string $message): array
    {
        return ['rows' => [], 'unknown' => [], 'counts' => [], 'error' => $message];
    }

}
