<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * An appraisal cycle from a spreadsheet: the template lists the employees with
 * their current CTC; HR fills the increment (%, amount or new CTC) and any new
 * designation, and the rows come back into the cycle's grid to check and save.
 */
class SalaryRevisionImport
{

    public const COLUMNS = [
        'code' => ['Employee ID', ['emp id', 'employee code', 'code']],
        'name' => ['Employee Name', ['name', 'employee']],
        'department' => ['Department', []],
        'designation' => ['Designation', []],
        'current_ctc' => ['Current CTC', ['ctc', 'current annual ctc']],
        'pct' => ['Increment %', ['hike %', 'increment percent', 'percent', '%']],
        'amount' => ['Increment Amount', ['hike amount', 'increment']],
        'new_ctc' => ['New CTC', ['revised ctc', 'new annual ctc']],
        'new_designation' => ['New Designation', ['promoted to']],
        'new_department' => ['New Department', []],
        'basic_pct' => ['Basic %', ['new basic %']],
        'type' => ['Type', []],
        'reason' => ['Reason', ['remarks', 'note']],
    ];

    public function template(?int $departmentId = null): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Revisions');
        $sheet->fromArray(array_column(self::COLUMNS, 0), null, 'A1');

        $employees = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->where('u.status', 'active')->where('u.employee_ctc', '>', 0)->whereNotIn('u.id', \App\Services\Access\SystemAccounts::ids())
            ->when($departmentId, fn ($q) => $q->where('ed.department_id', $departmentId))
            ->orderBy('t.team_name')->orderBy('u.name')
            ->get(['ed.employee_id', 'u.name', 't.team_name', 'd.name as designation', 'u.employee_ctc']);

        $row = 2;
        foreach ($employees as $e) {
            $sheet->fromArray([$e->employee_id, $e->name, $e->team_name, $e->designation, (float) $e->employee_ctc, '', '', '', '', '', '', 'appraisal', ''], null, 'A' . $row++);
        }

        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('F1:M1')->getFill()->setFillType('solid')->getStartColor()->setRGB('FFF4CE');
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('C2');

        $help = $book->createSheet()->setTitle('How to fill');
        $help->fromArray([
            ['Column', 'What to put'],
            ['Employee ID', 'Required. Rows left without an increment, new CTC or new designation are skipped.'],
            ['Increment %, Increment Amount, New CTC', 'Fill one: 10 (percent), 36000 (annual amount added) or the new annual CTC. New CTC wins over the others.'],
            ['New Designation / Department', 'Only for a promotion or transfer: the name exactly as in the portal.'],
            ['Basic %', 'Optional: a new Basic % for this employee; empty keeps it.'],
            ['Type', 'appraisal, promotion, special or correction (empty = appraisal).'],
            ['Current CTC, Name, Department, Designation', 'For your reference; the portal uses what it has on record.'],
        ], null, 'A1');
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(36);
        $help->getColumnDimension('B')->setWidth(110);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/salary-revision-template-' . uniqid() . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** @return array{rows: array, errors: array} rows ready for the grid */
    public function read(string $path): array
    {
        try {
            $data = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return ['rows' => [], 'errors' => ['The file could not be read as a spreadsheet.']];
        }

        $header = null;
        $map = [];
        foreach (array_slice($data, 0, 10, true) as $i => $row) {
            $map = $this->mapHeadings($row);
            if (isset($map['code'])) {
                $header = $i;
                break;
            }
        }

        if ($header === null) {
            return ['rows' => [], 'errors' => ['No heading row with "Employee ID" was found. Download the template and use its headings.']];
        }

        $designations = DB::table('designations')->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $departments = DB::table('teams')->pluck('id', 'team_name')->mapWithKeys(fn ($id, $n) => [mb_strtolower(trim($n)) => $id]);
        $rows = [];
        $errors = [];
        $grid = new SalaryRevisionGrid();

        foreach (array_slice($data, $header + 1, null, true) as $i => $raw) {
            $get = fn ($f) => isset($map[$f]) ? trim((string) ($raw[$map[$f]] ?? '')) : '';
            $line = $i + 1;
            $code = $get('code');

            if ($code === '') {
                continue;
            }

            $pct = $get('pct');
            $amount = str_replace(',', '', $get('amount'));
            $newCtc = str_replace(',', '', $get('new_ctc'));
            $newDesignation = $get('new_designation');
            $newDepartment = $get('new_department');

            if ($pct === '' && $amount === '' && $newCtc === '' && $newDesignation === '' && $newDepartment === '') {
                continue;   // nobody to revise on this row
            }

            $userId = DB::table('employee_details')->where('employee_id', $code)->value('user_id');

            if (!$userId) {
                $errors[] = "Row $line: no employee with ID $code.";
                continue;
            }

            $row = $grid->row((int) $userId);
            $old = (float) $row['old_ctc'];

            if ($newCtc !== '' && is_numeric($newCtc)) {
                $row['new_ctc'] = round((float) $newCtc);
            } elseif ($amount !== '' && is_numeric($amount)) {
                $row['new_ctc'] = round($old + (float) $amount);
            } elseif ($pct !== '' && is_numeric(rtrim($pct, '%'))) {
                $row['new_ctc'] = round($old * (1 + (float) rtrim($pct, '%') / 100));
            } elseif ($pct !== '' || $amount !== '' || $newCtc !== '') {
                $errors[] = "Row $line ($code): the increment is not a number.";
                continue;
            }

            if ($newDesignation !== '') {
                if (!isset($designations[mb_strtolower($newDesignation)])) {
                    $errors[] = "Row $line ($code): no designation \"$newDesignation\".";
                    continue;
                }
                $row['new_designation_id'] = $designations[mb_strtolower($newDesignation)];
            }

            if ($newDepartment !== '') {
                if (!isset($departments[mb_strtolower($newDepartment)])) {
                    $errors[] = "Row $line ($code): no department \"$newDepartment\".";
                    continue;
                }
                $row['new_department_id'] = $departments[mb_strtolower($newDepartment)];
            }

            $basic = rtrim($get('basic_pct'), '%');
            $row['new_basic_pct'] = is_numeric($basic) ? (float) $basic : null;
            $type = mb_strtolower($get('type'));
            $row['type'] = array_key_exists($type, SalaryRevisions::TYPES) ? $type : ($row['new_designation_id'] ? 'promotion' : 'appraisal');
            $row['reason'] = $get('reason');
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    protected function mapHeadings(array $headings): array
    {
        $norm = fn ($s) => trim(preg_replace('/[^a-z0-9%]+/', ' ', mb_strtolower((string) $s)));
        $map = [];

        foreach ($headings as $i => $heading) {
            $h = $norm($heading);
            foreach (self::COLUMNS as $field => [$title, $aliases]) {
                if (!isset($map[$field]) && $h !== '' && ($h === $norm($title) || in_array($h, array_map($norm, $aliases), true))) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

}
