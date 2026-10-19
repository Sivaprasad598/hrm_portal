<?php

namespace App\Services\Employee;

use App\Models\EmployeeDetails;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Employees from the HR master sheet ("Active Employees").
 *
 * Read, reviewed, then written: preview() reads the sheet and works out
 * everything it would do - the departments, designations and branches to
 * create, every employee to add or update, what was cleaned and what could not
 * be used - and writes nothing. apply() writes exactly that, in one
 * transaction.
 *
 * The sheet is written by hand and it shows: the same designation in five
 * spellings, dates typed three ways, bank account numbers Excel has rounded to
 * fifteen digits. A value that cannot be trusted is left out and reported
 * against its row and column; it never stops the rest of the row.
 *
 * Nothing is linked to the biometric devices here: that is done by Employee ID
 * on Biometric → Employee Mapping ("Sync all employees") once employees exist.
 */
class MasterSheetImport
{

    public const SHEET = 'Active Employees';

    /** The sheet's columns, in order, as the sample file carries them. */
    public const HEADINGS = ['SI No', 'EMP ID', 'Employee Name', 'Department', 'Parent Department', 'Designation', 'DOJ',
        'Branch', 'Fathers name', 'Monthly', 'Yearly', 'Contact Number', 'Alternative Contact Number', 'MAIL-ID', 'Gender',
        'Blood Group', 'Date of Birth (As per Aadhar)', 'Present Address', 'Permanent Address (As per Aadhar)', 'Aadhar Number',
        'PAN Card Number', 'Marital Status', 'Spouse Name', 'Total Experience (in Years)', 'Account No', 'IFSC Code', 'Branch'];

    /** Branch names in the sheet that are an existing branch under another name. */
    public const BRANCH_ALIASES = ['muralinagar' => 'visakhapatnam'];

    /** The name a branch reached through an alias is created with. */
    public const BRANCH_NAMES = ['visakhapatnam' => 'Visakhapatnam'];

    public const DEFAULT_PASSWORD = '123455678';

    protected string $tz;

    public function __construct()
    {
        $this->tz = (company() ?: null)?->timezone ?: (DB::table('companies')->value('timezone') ?: 'Asia/Kolkata');
    }

    // ------------------------------------------------------------------ read

    /**
     * Read the sheet and work out the whole import. Nothing is written.
     */
    public function preview(string $path, string $fileName = ''): array
    {
        $book = IOFactory::load($path);
        [$sheet, $headerRow, $columns] = $this->locate($book);

        if (!$sheet) {
            return ['ok' => false, 'message' => 'No sheet in this file has the master sheet\'s headings (EMP ID, Employee Name, Department, Designation, DOJ…). Download the sample file to see the layout.'];
        }

        $records = [];

        for ($r = $headerRow + 1; $r <= $sheet->getHighestDataRow(); $r++) {
            $code = $this->text($sheet, $columns, 'emp_id', $r);
            $name = $this->text($sheet, $columns, 'name', $r);

            if ($code === '' && $name === '') {
                continue;
            }

            $records[] = $this->readRow($sheet, $columns, $r, $code, $name);
        }

        $this->crossRowChecks($records);
        $master = $this->masterPlan($records);
        $this->matchExisting($records);

        return [
            'ok' => true,
            'file' => $fileName,
            'sheet' => $sheet->getTitle(),
            'read_at' => now()->toIso8601String(),
            'records' => $records,
            'master' => $master,
            // Device codes are linked by Employee ID on Biometric → Employee
            // Mapping ("Sync all employees"), not here.
            'devices' => [],
            'summary' => $this->summary($records),
        ];
    }

    /** The sheet with the master headings, the row they are on, and where each field is. */
    protected function locate(Spreadsheet $book): array
    {
        $sheets = $book->getSheetByName(self::SHEET) ? [$book->getSheetByName(self::SHEET)] : [];
        $sheets = array_merge($sheets, $book->getAllSheets());

        foreach ($sheets as $sheet) {
            for ($r = 1; $r <= min(5, $sheet->getHighestDataRow()); $r++) {
                $headings = [];
                $highest = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

                for ($c = 1; $c <= min($highest, 80); $c++) {
                    $headings[$c] = trim((string) $sheet->getCell([$c, $r])->getValue());
                }

                $columns = $this->columns($headings);

                if (isset($columns['emp_id'], $columns['name'], $columns['department'])) {
                    return [$sheet, $r, $columns];
                }
            }
        }

        return [null, 0, []];
    }

    /** Field => column number, from the headings. Two "Department" and two "Branch" columns are told apart by order. */
    protected function columns(array $headings): array
    {
        $key = fn ($h) => preg_replace('/[^a-z]/', '', strtolower($h));
        $seen = [];
        $out = [];

        foreach ($headings as $col => $heading) {
            $k = $key($heading);

            if ($k === '') {
                continue;
            }

            $n = $seen[$k] = ($seen[$k] ?? 0) + 1;

            $field = match (true) {
                in_array($k, ['empid', 'employeenumber', 'employeeid'], true) => 'emp_id',
                $k === 'employeename' => 'name',
                // First "Department": the employee's department. Second (or
                // "Parent Department"): the department it sits under. Grouping
                // is not used.
                $k === 'department' => $n === 1 ? 'division' : 'department',
                $k === 'parentdepartment' => 'department',
                $k === 'designation' => 'designation',
                $k === 'doj' || str_starts_with($k, 'datejoined') => 'doj',
                $k === 'branch' => $n === 1 ? 'branch' : 'bank_branch',
                str_starts_with($k, 'father') => 'father',
                $k === 'monthly' => 'monthly',
                $k === 'yearly' => 'yearly',
                $k === 'contactnumber' => 'mobile',
                str_starts_with($k, 'alternative') => 'alt_mobile',
                in_array($k, ['mailid', 'email', 'emailid'], true) => 'email',
                $k === 'gender' => 'gender',
                $k === 'bloodgroup' => 'blood',
                str_starts_with($k, 'dateofbirth') => 'dob',
                str_starts_with($k, 'presentaddress') => 'address',
                str_starts_with($k, 'permanentaddress') => 'permanent_address',
                str_starts_with($k, 'aadhar') || str_starts_with($k, 'aadhaar') => 'aadhaar',
                str_starts_with($k, 'pan') => 'pan',
                $k === 'maritalstatus' => 'marital',
                $k === 'spousename' => 'spouse',
                str_starts_with($k, 'totalexperience') => 'experience',
                $k === 'accountno' || $k === 'accountnumber' => 'account',
                $k === 'ifsccode' || $k === 'ifsc' => 'ifsc',
                default => null,
            };

            if ($field && !isset($out[$field])) {
                $out[$field] = $col;
            }
        }

        // A sheet with one department column: that is the department.
        if (isset($out['division']) && !isset($out['department'])) {
            $out['department'] = $out['division'];
            unset($out['division']);
        }

        return $out;
    }

    protected function cell(Worksheet $sheet, array $columns, string $field, int $row): ?Cell
    {
        return isset($columns[$field]) ? $sheet->getCell([$columns[$field], $row]) : null;
    }

    protected function text(Worksheet $sheet, array $columns, string $field, int $row): string
    {
        $cell = $this->cell($sheet, $columns, $field, $row);

        if (!$cell) {
            return '';
        }

        $value = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();

        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    /** One sheet row, cleaned. Every value that could not be used is an issue on the row. */
    protected function readRow(Worksheet $sheet, array $columns, int $r, string $code, string $name): array
    {
        $issues = [];
        $notes = [];
        $issue = function (string $field, string $message) use (&$issues) { $issues[] = ['field' => $field, 'message' => $message]; };
        $note = function (string $field, string $message) use (&$notes) { $notes[] = ['field' => $field, 'message' => $message]; };

        if ($code === '') {
            $issue('EMP ID', 'missing: the row is not imported');
        }

        // "Name (Rejoined)": the marker goes, the rejoin is recorded properly.
        $rejoined = (bool) preg_match('/\(\s*re-?\s*joined\s*\)/i', $name);
        $name = trim(preg_replace('/\(\s*re-?\s*joined\s*\)/i', '', $name));

        // Joining date; two of them mean a rejoin (first stint, then now).
        $dojCell = $this->cell($sheet, $columns, 'doj', $r);
        $dojText = $dojCell ? trim((string) $dojCell->getFormattedValue()) : '';
        $previousJoin = null;

        if (preg_match('/^(.+?)\s*(?:&|\band\b)\s*(.+)$/i', $dojText, $two)) {
            [$previousJoin] = $this->dateFromText($two[1]);
            [$joining, $problem] = $this->dateFromText($two[2]);
            $rejoined = true;
            $note('DOJ', 'two joining dates: ' . $dojText . ' — first employment kept as history, rejoined ' . ($joining ?? '?'));
        } else {
            [$joining, $problem] = $this->date($dojCell);
        }

        if (!$joining) {
            $issue('DOJ', $problem ?: 'missing');
        } elseif ($joining > Carbon::now($this->tz)->toDateString()) {
            $note('DOJ', 'joins on ' . Carbon::parse($joining)->format('d M Y') . ' (in the future)');
        }

        [$dob, $problem] = $this->date($this->cell($sheet, $columns, 'dob', $r));

        if ($problem && $problem !== 'missing') {
            $issue('Date of Birth', $problem);
        } elseif ($dob && (Carbon::parse($dob)->age < 16 || Carbon::parse($dob)->age > 80)) {
            $issue('Date of Birth', Carbon::parse($dob)->format('d M Y') . ' cannot be a date of birth (looks like a joining date)');
            $dob = null;
        }

        // Pay: Yearly is the CTC.
        $monthly = $this->money($this->text($sheet, $columns, 'monthly', $r));
        $yearly = $this->money($this->text($sheet, $columns, 'yearly', $r));

        if ($yearly === null && $monthly !== null) {
            $yearly = round($monthly * 12, 2);
            $note('Yearly', 'blank: taken as Monthly × 12 = ' . number_format($yearly));
        } elseif ($yearly === null) {
            $issue('Yearly', 'no CTC');
        } elseif ($monthly !== null && abs($monthly * 12 - $yearly) > 12) {
            $issue('Monthly', number_format($monthly) . ' × 12 = ' . number_format($monthly * 12) . ' does not match Yearly ' . number_format($yearly) . '; Yearly is used');
        }

        [$email, $emailNote, $emailIssue] = $this->email($this->text($sheet, $columns, 'email', $r));
        $emailNote && $note('MAIL-ID', $emailNote);
        $emailIssue && $issue('MAIL-ID', $emailIssue);

        [$mobile, $problem] = $this->phone($this->cell($sheet, $columns, 'mobile', $r));
        $problem && $issue('Contact Number', $problem);
        [$altMobile] = $this->phone($this->cell($sheet, $columns, 'alt_mobile', $r));

        $aadhaarRaw = $this->text($sheet, $columns, 'aadhaar', $r);
        $aadhaar = preg_replace('/\D/', '', $aadhaarRaw);

        if ($aadhaarRaw !== '' && !$this->placeholder($aadhaarRaw) && !preg_match('/^\d{12}$/', $aadhaar)) {
            $issue('Aadhar Number', '"' . mb_substr($aadhaarRaw, 0, 40) . '" is not a 12-digit Aadhaar number');
            $aadhaar = null;
        }

        $panRaw = strtoupper(str_replace(' ', '', $this->text($sheet, $columns, 'pan', $r)));
        $pan = preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', $panRaw) ? $panRaw : null;

        if ($panRaw !== '' && !$pan && !$this->placeholder($panRaw)) {
            $issue('PAN Card Number', '"' . mb_substr($panRaw, 0, 30) . '" is not a PAN (ABCDE1234F)');
        }

        [$account, $problem] = $this->account($this->cell($sheet, $columns, 'account', $r));
        $problem && $issue('Account No', $problem);

        $ifscRaw = strtoupper(str_replace(' ', '', $this->text($sheet, $columns, 'ifsc', $r)));
        $ifsc = strlen($ifscRaw) === 11 && $ifscRaw[4] === 'O' ? substr_replace($ifscRaw, '0', 4, 1) : $ifscRaw;

        if ($ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            $issue('IFSC Code', '"' . $ifscRaw . '" is not an IFSC code');
            $ifsc = null;
        } elseif ($ifsc !== $ifscRaw) {
            $note('IFSC Code', "$ifscRaw corrected to $ifsc (letter O for zero)");
        }

        $marital = $this->marital($this->text($sheet, $columns, 'marital', $r));

        if ($marital === false) {
            $issue('Marital Status', '"' . $this->text($sheet, $columns, 'marital', $r) . '" is not a marital status');
            $marital = null;
        }

        $blood = $this->blood($this->text($sheet, $columns, 'blood', $r));

        if ($blood === false) {
            $issue('Blood Group', '"' . $this->text($sheet, $columns, 'blood', $r) . '" is not a blood group');
            $blood = null;
        }

        foreach (['division' => 'Department', 'designation' => 'Designation', 'branch' => 'Branch'] as $field => $label) {
            if (isset($columns[$field]) && $this->text($sheet, $columns, $field, $r) === '') {
                $issue($label, 'missing');
            }
        }

        return [
            'line' => $r,
            'code' => $code,
            'name' => $name,
            'division' => $this->text($sheet, $columns, 'division', $r),
            'department' => $this->text($sheet, $columns, 'department', $r),
            'grouping' => '',
            'designation' => $this->text($sheet, $columns, 'designation', $r),
            'branch' => $this->text($sheet, $columns, 'branch', $r),
            'joining_date' => $joining,
            'previous_joining_date' => $previousJoin,
            'rejoined' => $rejoined,
            'father' => $this->text($sheet, $columns, 'father', $r) ?: null,
            'ctc' => $yearly,
            'monthly_in_sheet' => $monthly,
            'mobile' => $mobile,
            'alt_mobile' => $altMobile,
            'email' => $email,
            'gender' => $this->gender($this->text($sheet, $columns, 'gender', $r)),
            'blood_group' => $blood,
            'date_of_birth' => $dob,
            'address' => $this->text($sheet, $columns, 'address', $r) ?: null,
            'permanent_address' => $this->text($sheet, $columns, 'permanent_address', $r) ?: null,
            'aadhaar' => $aadhaar ?: null,
            'pan' => $pan,
            'marital_status' => $marital,
            'spouse' => $this->placeholder($this->text($sheet, $columns, 'spouse', $r)) ? null : ($this->text($sheet, $columns, 'spouse', $r) ?: null),
            'experience' => $this->experience($this->text($sheet, $columns, 'experience', $r)),
            'account' => $account,
            'ifsc' => $ifsc ?: null,
            'bank_branch' => $this->text($sheet, $columns, 'bank_branch', $r) ?: null,
            'issues' => $issues,
            'notes' => $notes,
            'skip' => $code === '' || $name === '',
            'match' => null,
        ];
    }

    /** Problems that only show across rows: an email or employee ID used twice. */
    protected function crossRowChecks(array &$records): void
    {
        $emails = [];
        $codes = [];

        foreach ($records as $i => $rec) {
            if ($rec['code'] !== '') {
                if (isset($codes[strtolower($rec['code'])])) {
                    $records[$i]['issues'][] = ['field' => 'EMP ID', 'message' => $rec['code'] . ' is used on another row too (line ' . $codes[strtolower($rec['code'])] . '): this row is not imported'];
                    $records[$i]['skip'] = true;
                } else {
                    $codes[strtolower($rec['code'])] = $rec['line'];
                }
            }

            if ($rec['email']) {
                if (isset($emails[$rec['email']])) {
                    $records[$i]['issues'][] = ['field' => 'MAIL-ID', 'message' => $rec['email'] . ' is already on line ' . $emails[$rec['email']] . ': one address can sign in only one person, so this employee gets none'];
                    $records[$i]['email'] = null;
                } else {
                    $emails[$rec['email']] = $rec['line'];
                }
            }
        }
    }

    // ------------------------------------------------------- master data

    /**
     * Groupings, departments, divisions (department → child), designations and
     * branches: what exists already and what would be created.
     */
    protected function masterPlan(array &$records): array
    {
        $teams = DB::table('teams')->get(['id', 'team_name', 'parent_id']);
        $findTeam = function (string $name, $parentId = false) use ($teams) {
            $key = $this->key($name);

            return $teams->first(fn ($t) => $this->key($t->team_name) === $key && ($parentId === false || (int) $t->parent_id === (int) $parentId));
        };

        // The most common spelling of each name stands for all of them.
        $spelling = function (array $names) {
            $counts = [];

            foreach ($names as $n) {
                if ($n !== '') {
                    $counts[$this->key($n)][$n] = ($counts[$this->key($n)][$n] ?? 0) + 1;
                }
            }

            return array_map(function ($variants) {
                arsort($variants);

                return array_key_first($variants);
            }, $counts);
        };

        $groupNames = $spelling(array_column($records, 'grouping'));
        $deptNames = $spelling(array_column($records, 'department'));
        $divNames = $spelling(array_column($records, 'division'));

        $groupings = [];
        $departments = [];
        $divisions = [];

        foreach ($records as $i => $rec) {
            if ($rec['skip']) {
                continue;
            }

            $gKey = $rec['grouping'] !== '' ? $this->key($rec['grouping']) : null;
            $dKey = $rec['department'] !== '' ? $this->key($rec['department']) : null;
            $vKey = $rec['division'] !== '' ? $this->key($rec['division']) : null;

            if ($gKey && !isset($groupings[$gKey])) {
                $existing = $findTeam($groupNames[$gKey]);
                $groupings[$gKey] = ['name' => $groupNames[$gKey], 'id' => $existing?->id, 'count' => 0];
            }

            if ($gKey) {
                $groupings[$gKey]['count']++;
            }

            if ($dKey) {
                $node = ($gKey ?? '-') . '|' . $dKey;

                if (!isset($departments[$node])) {
                    $existing = $findTeam($deptNames[$dKey]);
                    $departments[$node] = ['name' => $deptNames[$dKey], 'group' => $gKey, 'id' => $existing?->id,
                        'moves' => $existing && $gKey && (!$existing->parent_id || ($groupings[$gKey]['id'] && (int) $existing->parent_id !== (int) $groupings[$gKey]['id'])),
                        'count' => 0];
                }

                $departments[$node]['count']++;
                $records[$i]['department_node'] = $node;
            }

            // A division named like its department is the department itself.
            if ($vKey && $dKey && $vKey !== $dKey) {
                $node = ($gKey ?? '-') . '|' . $dKey . '|' . $vKey;

                if (!isset($divisions[$node])) {
                    $parent = $departments[($gKey ?? '-') . '|' . $dKey];
                    $existing = $parent['id'] ? $findTeam($divNames[$vKey], $parent['id']) : null;
                    $divisions[$node] = ['name' => $divNames[$vKey], 'department' => ($gKey ?? '-') . '|' . $dKey, 'id' => $existing?->id,
                        'variants' => [], 'count' => 0];
                }

                $divisions[$node]['variants'][$rec['division']] = true;
                $divisions[$node]['count']++;
                $records[$i]['division_node'] = $node;
            }
        }

        // Designations: case, dots, hyphens and spaces do not make a new title,
        // nor do a few common misspellings.
        $designations = [];
        $existingDesignations = DB::table('designations')->get(['id', 'name']);

        foreach ($records as $i => $rec) {
            if ($rec['skip'] || $rec['designation'] === '') {
                continue;
            }

            $key = $this->designationKey($rec['designation']);
            $designations[$key]['variants'][$rec['designation']] = ($designations[$key]['variants'][$rec['designation']] ?? 0) + 1;
            $records[$i]['designation_key'] = $key;
        }

        foreach ($designations as $key => &$d) {
            arsort($d['variants']);
            $existing = $existingDesignations->first(fn ($e) => $this->designationKey($e->name) === $key);
            $d['name'] = $existing->name ?? array_key_first($d['variants']);
            $d['id'] = $existing?->id;
            $d['count'] = array_sum($d['variants']);
        }

        unset($d);
        uasort($designations, fn ($a, $b) => $b['count'] <=> $a['count']);

        // Branches, with the sheet's "Murali Nagar" being Visakhapatnam.
        $branches = [];
        $existingBranches = DB::table('branches')->get(['id', 'branch_name']);

        foreach ($records as $i => $rec) {
            if ($rec['skip'] || $rec['branch'] === '') {
                continue;
            }

            $key = $this->key($rec['branch']);
            $key = self::BRANCH_ALIASES[$key] ?? $key;

            if (!isset($branches[$key])) {
                $existing = $existingBranches->first(fn ($b) => $this->key($b->branch_name) === $key);
                // A new branch takes its proper name ("Visakhapatnam"), not the sheet's spelling ("Murali Nagar").
                $branches[$key] = ['name' => $existing->branch_name ?? (self::BRANCH_NAMES[$key] ?? $rec['branch']), 'id' => $existing?->id, 'sheet_names' => [], 'count' => 0];
            }

            $branches[$key]['sheet_names'][$rec['branch']] = true;
            $branches[$key]['count']++;
            $records[$i]['branch_key'] = $key;
        }

        return compact('groupings', 'departments', 'divisions', 'designations', 'branches');
    }

    /**
     * People already on record: by employee ID, then email, then a name only
     * one person has. They are updated instead of added a second time.
     */
    protected function matchExisting(array &$records): void
    {
        $people = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->get(['u.id', 'u.name', 'u.email', 'ed.employee_id', 'ed.employee_card_id']);
        $byName = $people->groupBy(fn ($p) => $this->nameKey($p->name));

        foreach ($records as $i => $rec) {
            if ($rec['skip']) {
                continue;
            }

            $person = $people->first(fn ($p) => strcasecmp((string) $p->employee_id, $rec['code']) === 0 || strcasecmp((string) $p->employee_card_id, $rec['code']) === 0);
            $how = 'employee ID';

            if (!$person && $rec['email']) {
                $person = $people->first(fn ($p) => strtolower((string) $p->email) === $rec['email']);
                $how = 'email';
            }

            if (!$person && ($same = $byName->get($this->nameKey($rec['name']))) && $same->count() === 1) {
                $person = $same->first();
                $how = 'name';
            }

            if ($person) {
                $records[$i]['match'] = ['user_id' => $person->id, 'by' => $how, 'name' => $person->name, 'was_code' => $person->employee_id];
            }

            // An email somebody else already signs in with cannot be given again.
            if ($rec['email'] && (!$person || strtolower((string) $person->email) !== $rec['email'])) {
                $owner = $people->first(fn ($p) => strtolower((string) $p->email) === $rec['email'] && (!$person || $p->id !== $person->id));

                if ($owner) {
                    $records[$i]['issues'][] = ['field' => 'MAIL-ID', 'message' => $rec['email'] . ' already belongs to ' . $owner->name . ' in the system'];
                    $records[$i]['email'] = null;
                }
            }
        }
    }

    /**
     * Device enrolments not linked to anyone yet, matched to sheet rows by name.
     * Strong: every name word agrees. Possible: two or more words agree. Never by ID.
     */
    protected function deviceSuggestions(array $records): array
    {
        $tokens = fn ($n) => array_values(array_unique(array_filter(preg_split('/[^a-z]+/', strtolower((string) $n)), fn ($t) => strlen($t) > 2)));
        $linked = DB::table('employee_details')->whereNotNull('iapp_emp_code')->pluck('iapp_emp_code')->map(fn ($c) => (string) $c)->all();
        $out = [];

        foreach (DB::table('iapp_employees')->where('map_status', '!=', 'ignored')->whereNull('user_id')->get(['emp_code', 'employee_name', 'last_seen_at']) as $device) {
            if (in_array((string) $device->emp_code, $linked, true)) {
                continue;
            }

            $dt = $tokens($device->employee_name);

            if ($dt === []) {
                continue;
            }

            $hits = [];

            foreach ($records as $rec) {
                if ($rec['skip']) {
                    continue;
                }

                $rt = $tokens($rec['name']);
                $common = count(array_intersect($dt, $rt));

                if ($common >= min(2, count($dt))) {
                    $hits[] = ['code' => $rec['code'], 'name' => $rec['name'], 'strong' => $common === count($dt) && $common === count($rt)];
                }
            }

            if (count($hits) === 1) {
                $out[] = ['device_code' => (string) $device->emp_code, 'device_name' => $device->employee_name,
                    'last_seen' => $device->last_seen_at ? substr((string) $device->last_seen_at, 0, 10) : null,
                    'employee_code' => $hits[0]['code'], 'employee_name' => $hits[0]['name'], 'strong' => $hits[0]['strong']];
            }
        }

        usort($out, fn ($a, $b) => [$b['strong'], $a['employee_code']] <=> [$a['strong'], $b['employee_code']]);

        return $out;
    }

    protected function summary(array $records): array
    {
        $usable = array_filter($records, fn ($r) => !$r['skip']);

        return [
            'rows' => count($records),
            'new' => count(array_filter($usable, fn ($r) => !$r['match'])),
            'update' => count(array_filter($usable, fn ($r) => (bool) $r['match'])),
            'skipped' => count($records) - count($usable),
            'with_issues' => count(array_filter($usable, fn ($r) => $r['issues'] !== [])),
            'clean' => count(array_filter($usable, fn ($r) => $r['issues'] === [])),
            'no_email' => count(array_filter($usable, fn ($r) => !$r['email'])),
            'rejoined' => count(array_filter($usable, fn ($r) => $r['rejoined'])),
        ];
    }

    // ----------------------------------------------------------------- write

    /**
     * Write what the preview worked out.
     *
     * @param  array  $options  password, branches (keys to create), device_links (device codes ticked)
     */
    public function apply(array $preview, array $options, int $actorId): array
    {
        @set_time_limit(900);

        $master = $preview['master'];
        $password = Hash::make($options['password'] ?? self::DEFAULT_PASSWORD);
        $createBranches = array_flip($options['branches'] ?? []);
        $companyId = company()->id;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'teams' => 0, 'designations' => 0, 'branches' => 0,
            'linked' => 0, 'user_ids' => []];

        DB::transaction(function () use (&$master, $preview, $options, $password, $createBranches, $companyId, $actorId, &$result) {
            $now = now();
            $team = function (string $name, ?int $parentId) use ($companyId, $actorId, $now, &$result) {
                $result['teams']++;

                $id = DB::table('teams')->insertGetId(['company_id' => $companyId, 'team_name' => mb_substr($name, 0, 191),
                    'parent_id' => $parentId, 'added_by' => $actorId, 'created_at' => $now, 'updated_at' => $now]);
                // Leave types list the departments they apply to; a new one must be on them.
                \App\Services\Leaves\LeaveTypeScope::include('department', [$id]);

                return $id;
            };

            foreach ($master['groupings'] as $k => $g) {
                $master['groupings'][$k]['id'] = $g['id'] ?: $team($g['name'], null);
            }

            foreach ($master['departments'] as $k => $d) {
                $parent = $d['group'] ? $master['groupings'][$d['group']]['id'] : null;

                if ($d['id']) {
                    // An existing department goes under its grouping.
                    if ($parent && (int) DB::table('teams')->where('id', $d['id'])->value('parent_id') !== (int) $parent) {
                        DB::table('teams')->where('id', $d['id'])->update(['parent_id' => $parent, 'updated_at' => $now]);
                    }
                } else {
                    $master['departments'][$k]['id'] = $team($d['name'], $parent);
                }
            }

            foreach ($master['divisions'] as $k => $v) {
                $master['divisions'][$k]['id'] = $v['id'] ?: $team($v['name'], $master['departments'][$v['department']]['id']);
            }

            foreach ($master['designations'] as $k => $d) {
                if (!$d['id']) {
                    $master['designations'][$k]['id'] = DB::table('designations')->insertGetId(['company_id' => $companyId,
                        'name' => mb_substr($d['name'], 0, 191), 'added_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
                        // The access role its name suggests (Settings -> Designations can change it).
                        'role_id' => \App\Services\Employee\EmployeeDefaults::guessRoleId((string) $d['name'])]);
                    \App\Services\Leaves\LeaveTypeScope::include('designation', [$master['designations'][$k]['id']]);
                    $result['designations']++;
                }
            }

            foreach ($master['branches'] as $k => $b) {
                if (!$b['id'] && isset($createBranches[$k])) {
                    $master['branches'][$k]['id'] = DB::table('branches')->insertGetId(['company_id' => $companyId,
                        'branch_name' => mb_substr($b['name'], 0, 191), 'status' => 1, 'added_by' => $actorId, 'created_at' => $now, 'updated_at' => $now]);
                    $result['branches']++;
                }
            }

            $employeeRole = Role::where('name', 'employee')->first();
            $probationMonths = (int) (DB::table('probation_period_settings')->where('company_id', $companyId)->orderByDesc('id')->value('months') ?? 0);

            foreach ($preview['records'] as $rec) {
                if ($rec['skip']) {
                    $result['skipped']++;

                    continue;
                }

                $userId = $this->writeEmployee($rec, $master, $password, $employeeRole, $probationMonths, $companyId, $actorId);
                $result[$rec['match'] ? 'updated' : 'created']++;
                $result['user_ids'][] = $userId;

                // The designation's access role, and General Shift for anyone without a shift.
                \App\Services\Employee\EmployeeDefaults::applyRole($userId);
                \App\Services\Employee\EmployeeDefaults::applyDefaultShift($userId, $actorId);
            }

            // Device links that were ticked, by name, never by ID.
            $byCode = DB::table('employee_details')->whereIn('employee_id', array_column($preview['records'], 'code'))->pluck('user_id', 'employee_id');

            foreach ($preview['devices'] as $s) {
                if (!in_array($s['device_code'], $options['device_links'] ?? [], true) || !isset($byCode[$s['employee_code']])) {
                    continue;
                }

                $userId = (int) $byCode[$s['employee_code']];

                if (DB::table('employee_details')->where('iapp_emp_code', $s['device_code'])->exists()
                    || DB::table('employee_details')->where('user_id', $userId)->whereNotNull('iapp_emp_code')->exists()) {
                    continue;
                }

                DB::table('employee_details')->where('user_id', $userId)->update(['iapp_emp_code' => $s['device_code'], 'iapp_linked_at' => $now]);
                \App\Models\IappEmployee::where('emp_code', $s['device_code'])->first()?->mapTo($userId, \App\Models\IappEmployee::METHOD_MANUAL, 'name (employee import)', $actorId);
                DB::table('iapp_device_transactions')->where('emp_code', $s['device_code'])->whereNull('user_id')->update(['user_id' => $userId]);
                $result['linked']++;
            }
        });

        // Outside the transaction: one pass over leave quotas for the company,
        // instead of one per employee as the record's own hook would.
        \Illuminate\Support\Facades\Artisan::call('app:recalculate-leaves-quotas', ['company' => $companyId]);

        return $result;
    }

    protected function writeEmployee(array $rec, array $master, string $password, ?Role $employeeRole, int $probationMonths, int $companyId, int $actorId): int
    {
        $now = now();
        [$first, $last] = $this->splitName($rec['name']);
        $departmentId = isset($rec['division_node']) ? $master['divisions'][$rec['division_node']]['id']
            : (isset($rec['department_node']) ? $master['departments'][$rec['department_node']]['id'] : null);
        $designationId = isset($rec['designation_key']) ? $master['designations'][$rec['designation_key']]['id'] : null;
        $branchId = isset($rec['branch_key']) ? ($master['branches'][$rec['branch_key']]['id'] ?? null) : null;

        // Only what the sheet actually says: a blank cell never erases.
        $user = array_filter([
            'name' => $rec['name'],
            'first_name' => $first,
            'last_name' => $last,
            'mobile' => $rec['mobile'],
            'country_phonecode' => $rec['mobile'] ? 91 : null,
            'gender' => $rec['gender'],
            'pan_number' => $rec['pan'],
            'employee_ctc' => $rec['ctc'],
            'monthly_salary' => $rec['ctc'] !== null ? round($rec['ctc'] / 12, 2) : null,
        ], fn ($v) => $v !== null && $v !== '');

        $details = array_filter([
            'employee_id' => $rec['code'],
            'employee_card_id' => $rec['code'],
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'branch_id' => $branchId,
            'joining_date' => $rec['joining_date'],
            'father_or_husband_name' => $rec['father'],
            'date_of_birth' => $rec['date_of_birth'],
            'address' => $rec['address'],
            'permanent_address' => $rec['permanent_address'],
            'aadhar_card_number' => $rec['aadhaar'],
            'blood_group' => $rec['blood_group'],
            'marital_status' => $rec['marital_status'],
            'total_experience' => $rec['experience'],
            'personal_account_number' => $rec['account'],
            'ifsc_code' => $rec['ifsc'],
            'bank_branch' => $rec['bank_branch'],
        ], fn ($v) => $v !== null && $v !== '');

        if ($rec['match']) {
            $userId = (int) $rec['match']['user_id'];

            // The code moves only if nobody else holds it.
            if (DB::table('employee_details')->where('user_id', '!=', $userId)
                ->where(fn ($q) => $q->where('employee_id', $rec['code'])->orWhere('employee_card_id', $rec['code']))->exists()) {
                unset($details['employee_id'], $details['employee_card_id']);
            }

            if ($rec['email'] && !DB::table('users')->where('id', $userId)->whereNotNull('email')->where('email', '<>', '')->exists()) {
                $user['email'] = $rec['email'];
            }

            DB::table('users')->where('id', $userId)->update($user + ['updated_at' => $now]);
            EmployeeDetails::where('user_id', $userId)->first()?->forceFill($details)->saveQuietly();
        } else {
            $model = new User();
            $model->forceFill($user + [
                'company_id' => $companyId,
                'email' => $rec['email'],
                'password' => $password,
                'status' => 'active',
                'login' => $rec['email'] ? 'enable' : 'disable',
                'locale' => 'en',
                'email_notifications' => 1,
            ]);
            // Quietly: no welcome mail, no "new employee" announcement per person.
            $model->saveQuietly();
            $userId = $model->id;

            $joining = $rec['joining_date'] ? Carbon::parse($rec['joining_date']) : null;
            $probationEnd = $joining && $probationMonths ? $joining->copy()->addMonths($probationMonths) : null;

            $detail = new EmployeeDetails();
            $detail->forceFill($details + [
                'user_id' => $userId,
                'company_id' => $companyId,
                'employment_type' => 'full_time',
                'employment_status' => $probationEnd && $probationEnd->isFuture() ? 'probation' : 'confirmed',
                'probation_end_date' => $probationEnd?->toDateString(),
                'marital_status' => $details['marital_status'] ?? 'single',
                'added_by' => $actorId,
            ]);
            $detail->saveQuietly();

            if ($employeeRole) {
                DB::table('role_user')->insertOrIgnore(['user_id' => $userId, 'role_id' => $employeeRole->id]);
                $model->assignUserRolePermission($employeeRole->id);
            }

            if ($rec['spouse']) {
                DB::table('employee_relations')->insert(['user_id' => $userId, 'serial_no' => 1, 'name' => mb_substr($rec['spouse'], 0, 191),
                    'relation' => 'Spouse', 'contact_number' => '', 'created_at' => $now, 'updated_at' => $now]);
            }

            if ($rec['alt_mobile']) {
                DB::table('employee_relations')->insert(['user_id' => $userId, 'serial_no' => 2, 'name' => 'Alternate contact',
                    'relation' => 'Other', 'contact_number' => $rec['alt_mobile'], 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Employment history: a rejoin keeps the first stint as a closed period.
        $periods = app(\App\Services\Employment\EmploymentPeriods::class);

        if ($rec['rejoined'] && $rec['previous_joining_date'] && $rec['joining_date'] && $rec['previous_joining_date'] < $rec['joining_date']
            && !DB::table('employment_periods')->where('user_id', $userId)->exists()) {
            DB::table('employment_periods')->insert([
                'company_id' => $companyId, 'user_id' => $userId, 'period_no' => 1,
                'joined_on' => $rec['previous_joining_date'],
                'left_on' => Carbon::parse($rec['joining_date'])->subDay()->toDateString(),
                'exit_type' => 'other',
                'exit_reason' => 'From the HR master sheet: exit date not recorded, set to the day before rejoining',
                'employee_code' => $rec['code'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('employment_periods')->insert([
                'company_id' => $companyId, 'user_id' => $userId, 'period_no' => 2, 'joined_on' => $rec['joining_date'],
                'employee_code' => $rec['code'], 'department_id' => $departmentId, 'designation_id' => $designationId,
                'branch_id' => $branchId, 'employee_ctc' => $rec['ctc'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $periods->syncFromRecord($userId);
        }

        return $userId;
    }

    // ---------------------------------------------------------- the files

    /** The sample: the master sheet's own columns, two example rows, and the number columns kept as text. */
    public function sample(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle(self::SHEET);
        $sheet->fromArray(self::HEADINGS, null, 'A1');

        $examples = [
            [1, 'ACPL0001', 'Ravi Kumar Varma', 'GIS', 'GIS Department', 'Jr GIS Engineer', '2025-06-02',
                'Visakhapatnam', 'Varma Rao', 25000, 300000, '9876543210', '9876501234', 'ravi.varma@example.com', 'Male', 'O+',
                '1998-03-14', 'Door 1-2-3, MVP Colony, Visakhapatnam', 'Door 4-5, Kakinada', '123412341234', 'ABCDE1234F', 'Single', '',
                '2', '918010012345678', 'UTIB0003057', 'Akkayyapalem'],
            [2, 'ACPL0002', 'Sita Lakshmi', 'TEKLA', 'Steel Detailing Department', 'Detailer', '2024-11-18',
                'Hyderabad', 'Ramana Murthy', 32000, 384000, '9123456789', '', 'sita.l@example.com', 'Female', 'B+',
                '1995-07-21', 'Madhapur, Hyderabad', 'Madhapur, Hyderabad', '432143214321', 'PQRSX6789K', 'Married', 'Kiran',
                'Fresher', '50100361368011', 'HDFC0001234', 'Gachibowli'],
        ];

        // Long numbers as text, so Excel cannot round them away (23 account
        // numbers in the master sheet were lost that way).
        foreach (['L', 'M', 'T', 'Y'] as $col) {
            $sheet->getStyle($col . '2:' . $col . '1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach ($examples as $i => $row) {
            foreach ($row as $c => $value) {
                $coordinate = [$c + 1, $i + 2];

                if (in_array($c, [6, 16], true)) {
                    $sheet->setCellValue($coordinate, ExcelDate::PHPToExcel(Carbon::parse($value)));
                    $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
                } elseif (in_array($c, [11, 12, 19, 24], true)) {
                    $sheet->setCellValueExplicit($coordinate, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
        }

        foreach (['G', 'Q'] as $col) {
            $sheet->getStyle($col . '2:' . $col . '1000')->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        }

        $sheet->getStyle('A1:AA1')->getFont()->setBold(true);
        $sheet->getStyle('A1:AA1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
        $sheet->freezePane('D2');

        foreach (range(1, count(self::HEADINGS)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $help = $book->createSheet()->setTitle('How to fill');
        $help->fromArray(array_map(fn ($l) => [$l], [
            'One row per employee, on the "' . self::SHEET . '" sheet, in these columns.',
            'EMP ID and Employee Name are required. EMP ID is the HR employee number; an existing employee with the same ID, email or name is updated, not added again.',
            'Department (column D) is the employee department (GIS, CYCLONE, TEKLA…); Parent Department (column E) is the department it sits under. Missing ones are created. A sheet with a Grouping column is accepted; that column is not used.',
            'Designation and Branch are created if they do not exist. "Murali Nagar" is the Visakhapatnam branch.',
            'Dates: an Excel date, or dd/mm/yyyy. Write a rejoin as "first date & rejoin date".',
            'Yearly is the CTC; Monthly is only checked against it.',
            'Contact numbers, Aadhar and Account No are text columns: keep them as text, or Excel rounds long numbers.',
            'The second Branch column is the bank branch.',
            'Nothing is saved on upload: every row is shown for review first.',
        ]), null, 'A1');
        $help->getColumnDimension('A')->setWidth(150);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** Every problem and correction, one line each, for HR to work through. */
    public function report(array $preview): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('To check');
        $sheet->fromArray(['Line', 'EMP ID', 'Employee Name', 'Column', 'Problem', 'Kind'], null, 'A1');
        $row = 2;

        foreach ($preview['records'] as $rec) {
            foreach ($rec['issues'] as $i) {
                $sheet->fromArray([$rec['line'], $rec['code'], $rec['name'], $i['field'], $i['message'], 'Needs fixing'], null, 'A' . $row++);
            }

            foreach ($rec['notes'] as $n) {
                $sheet->fromArray([$rec['line'], $rec['code'], $rec['name'], $n['field'], $n['message'], 'Corrected / for information'], null, 'A' . $row++);
            }
        }

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->setAutoFilter('A1:F' . max(1, $row - 1));

        foreach (['A' => 6, 'B' => 11, 'C' => 32, 'D' => 18, 'E' => 110, 'F' => 26] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return $book;
    }

    // ---------------------------------------------------------- cleaning

    /** @return array{0: ?string, 1: ?string} Y-m-d, or why not */
    protected function date(?Cell $cell): array
    {
        if (!$cell || $cell->getValue() === null || trim((string) $cell->getValue()) === '') {
            return [null, 'missing'];
        }

        $value = $cell->getValue();

        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return [ExcelDate::excelToDateTimeObject($value)->format('Y-m-d'), null];
        }

        return $this->dateFromText((string) $cell->getFormattedValue());
    }

    /** Typed dates in this sheet are day first; month names may be misspelt (Arp). */
    protected function dateFromText(string $text): array
    {
        $t = trim(str_ireplace(['arp', 'sept'], ['Apr', 'Sep'], $text));

        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})$/', $t, $m)) {
            $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

            return checkdate((int) $m[2], (int) $m[1], $y) ? [sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]), null] : [null, '"' . $text . '" is not a date'];
        }

        if (preg_match('/^\d{1,2}[\s\-]?[A-Za-z]{3,9}[\s\-]?\d{2,4}$/', $t)) {
            try {
                return [Carbon::parse($t)->toDateString(), null];
            } catch (\Throwable) {
            }
        }

        return [null, '"' . $text . '" is not a date'];
    }

    protected function money(string $v): ?float
    {
        $clean = str_replace([',', ' ', '₹'], '', $v);

        return $clean !== '' && is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} email, a correction made, a problem */
    protected function email(string $raw): array
    {
        $e = strtolower(trim($raw));

        if ($e === '' || $this->placeholder($e)) {
            return [null, null, 'none: the employee cannot sign in until one is added'];
        }

        if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
            return [$e, null, null];
        }

        // name123gmail.com → name123@gmail.com
        if (preg_match('/^([a-z0-9._%+-]+?)(gmail|yahoo|outlook|hotmail|rediffmail|arowanasoft)\.(com|in|co\.in)$/', str_replace(' ', '', $e), $m)) {
            $fixed = $m[1] . '@' . $m[2] . '.' . $m[3];

            if (filter_var($fixed, FILTER_VALIDATE_EMAIL)) {
                return [$fixed, "\"$raw\" corrected to $fixed (missing @)", null];
            }
        }

        return [null, null, '"' . $raw . '" is not an email address: the employee cannot sign in until one is added'];
    }

    /** @return array{0: ?string, 1: ?string} 10 digits, or why not */
    protected function phone(?Cell $cell): array
    {
        if (!$cell) {
            return [null, null];
        }

        $raw = trim((string) $cell->getFormattedValue());

        if ($raw === '' || $this->placeholder($raw)) {
            return [null, null];
        }

        if (stripos($raw, 'E+') !== false) {
            return [null, '"' . $raw . '" was rounded by Excel; the number is lost'];
        }

        $first = preg_split('/[\/,;]| or /i', $raw)[0];
        $digits = preg_replace('/\D/', '', $first);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? [$digits, null] : [null, '"' . $raw . '" is not a 10-digit mobile number'];
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function account(?Cell $cell): array
    {
        if (!$cell || $cell->getValue() === null || trim((string) $cell->getValue()) === '') {
            return [null, null];
        }

        $value = $cell->getValue();
        $shown = trim((string) $cell->getFormattedValue());

        // Excel keeps 15 significant digits: a long account number typed into a
        // number cell comes back with its tail turned to zeros.
        if (is_float($value) || stripos($shown, 'E+') !== false || preg_match('/\d{3,}0{6,}$/', $shown)) {
            return [null, $shown . ' was rounded by Excel and is not the real account number: re-enter it'];
        }

        $digits = preg_replace('/\s/', '', $shown);

        return preg_match('/^\d{9,18}$/', $digits) ? [$digits, null] : [null, '"' . $shown . '" is not an account number'];
    }

    protected function gender(string $v): ?string
    {
        $v = strtolower($v);

        return match (true) {
            str_starts_with($v, 'f'), $v === 'fenale' => 'female',
            str_starts_with($v, 'm') => 'male',
            $v === '' => null,
            default => 'others',
        };
    }

    /** @return string|null|false a blood group, nothing, or false for something unreadable */
    protected function blood(string $v)
    {
        if ($v === '' || $this->placeholder($v)) {
            return null;
        }

        $t = strtoupper(str_replace(['"', '(', ')', ' '], '', $v));
        $t = preg_replace('/^0/', 'O', $t);
        $t = str_replace(['POSITIVE', 'POSTIVE', '+VE', 'VE'], ['+', '+', '+', ''], $t);
        $t = str_replace(['NEGATIVE', '_VE', '-VE', '_'], ['-', '-', '-', '-'], $t);
        $t = preg_replace('/\+\+$/', '+', $t);

        return in_array($t, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true) ? $t : false;
    }

    /**
     * Marital status, compared in lower case with spaces, hyphens and dots
     * ignored: "Single", "Unmarried", "UN-MARRIED", "Un Married" and "Un- married"
     * are all single; "Married" and "married" are married. Nothing given is
     * single, the system's own default.
     *
     * @return string|null|false a status, nothing, or false for a value that is not a status
     */
    protected function marital(string $v)
    {
        $k = preg_replace('/[^a-z]/', '', strtolower(trim($v)));

        $single = ['single', 'unmarried', 'unmarrid', 'unmaried', 'nomarried', 'notmarried', 'bachelor', 'no'];
        $married = ['married', 'maried', 'marrid'];

        return match (true) {
            $k === '' || $this->placeholder($v) => null,
            in_array($k, $single, true) => 'single',
            in_array($k, $married, true) => 'married',
            in_array($k, ['divorced', 'widow', 'widower'], true) => $k,
            in_array($k, ['separated', 'separate'], true) => 'separate',
            default => false,
        };
    }

    /** "Fresher" 0, "9 years" 9, "4 years 5 months" 4.42, "8 months" 0.67; "Experienced" says nothing. */
    protected function experience(string $v): ?float
    {
        $t = strtolower($v);

        if (str_contains($t, 'fresher')) {
            return 0.0;
        }

        $years = preg_match('/(\d+(?:\.\d+)?)\s*(?:\+\s*)?(?:y|yr|yrs|year|years)?\b/', $t, $y) ? (float) $y[1] : null;
        $months = preg_match('/(\d+)\s*(?:m|months?)\b/', $t, $m) ? (int) $m[1] : null;

        if ($months !== null && ($years === null || (float) $months === $years)) {
            return round($months / 12, 2);
        }

        if ($years === null) {
            return null;
        }

        return min(99, round($years + ($months ?? 0) / 12, 2));
    }

    protected function placeholder(string $v): bool
    {
        return in_array(strtolower(trim($v)), ['', '-', '_', 'na', 'n/a', 'nil', 'no', 'none', '--'], true);
    }

    protected function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }

    protected function nameKey(string $name): string
    {
        $words = array_filter(preg_split('/[^a-z]+/', strtolower($name)), fn ($w) => strlen($w) > 1);
        sort($words);

        return implode(' ', $words);
    }

    /** One key per title however it is written: Jr./Junior, Sr./Senior, Modeller/Modeler, Manger/Manager. */
    protected function designationKey(string $name): string
    {
        $t = strtolower($name);
        $t = preg_replace(['/\bjunior\b/', '/\bsenior\b/', '/model+(?:l?er|lor)\b/', '/\bmanger\b/', '/\bengg?\b/'],
            ['jr', 'sr', 'modeler', 'manager', 'engineer'], $t);

        return preg_replace('/[^a-z0-9]/', '', $t);
    }

    /** @return array{0: string, 1: ?string} first name(s), last name */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));

        if (count($parts) < 2) {
            return [$name, null];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

}
