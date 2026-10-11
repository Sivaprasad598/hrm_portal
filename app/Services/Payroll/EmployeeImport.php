<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Filling in employee details from a spreadsheet.
 *
 * The device sync creates people from their enrolment — a name and a
 * fingerprint — so most of the company has no salary, no PAN and no bank
 * account, and payroll cannot value them. Typing that in one screen at a time
 * for a hundred and seventy people is the thing this avoids.
 *
 * The shape is deliberately a round trip: download what is on record, fill in
 * the blanks in Excel, upload it back. Nothing is written until the changes
 * have been shown and accepted, and a row that matches nobody is reported
 * rather than guessed at.
 */
class EmployeeImport
{

    /**
     * The columns the sheet carries, in order: heading => where it lives.
     *
     * The first two identify the person and are never written.
     */
    public const COLUMNS = [
        // How a line is tied back to a person. Everybody has a reference; only
        // eight of a hundred and seventy-five have a code, so the reference is
        // what the matching leans on.
        'Ref' => ['table' => null, 'column' => 'id'],
        'Name' => ['table' => null, 'column' => 'name'],
        // Writable, but only into a blank: it is a key, and silently moving one
        // would re-point a payslip history at the wrong person.
        'Employee Code' => ['table' => 'employee_details', 'column' => 'employee_card_id', 'type' => 'code'],
        'Employee ID' => ['table' => 'employee_details', 'column' => 'employee_id', 'type' => 'text'],
        'Annual CTC' => ['table' => 'users', 'column' => 'employee_ctc', 'type' => 'money'],
        'Basic %' => ['table' => 'users', 'column' => 'basic_for_payroll', 'type' => 'percent'],
        'Monthly TDS' => ['table' => 'users', 'column' => 'monthly_tds', 'type' => 'money'],
        'PAN' => ['table' => 'users', 'column' => 'pan_number', 'type' => 'pan'],
        'Mobile' => ['table' => 'users', 'column' => 'mobile', 'type' => 'text'],
        'Bank Account No' => ['table' => 'employee_details', 'column' => 'personal_account_number', 'type' => 'text'],
        'IFSC Code' => ['table' => 'employee_details', 'column' => 'ifsc_code', 'type' => 'ifsc'],
        'PF Account No' => ['table' => 'employee_details', 'column' => 'pf_account_number', 'type' => 'text'],
        'ESI Number' => ['table' => 'employee_details', 'column' => 'esi_number', 'type' => 'text'],
        'Aadhaar' => ['table' => 'employee_details', 'column' => 'aadhar_card_number', 'type' => 'text'],
        'Department' => ['table' => 'employee_details', 'column' => 'department_id', 'type' => 'department'],
        'Designation' => ['table' => 'employee_details', 'column' => 'designation_id', 'type' => 'designation'],
        'Joining Date' => ['table' => 'employee_details', 'column' => 'joining_date', 'type' => 'date'],
        'Employment Type' => ['table' => 'employee_details', 'column' => 'employment_type', 'type' => 'text'],
    ];

    /**
     * Everyone on record, as the sheet to be filled in.
     *
     * @return array<int, array<string, mixed>>
     */
    public function current(): array
    {
        $departments = DB::table('teams')->pluck('team_name', 'id');
        $designations = DB::table('designations')->pluck('name', 'id');

        return DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds',
                'u.pan_number', 'u.mobile', 'ed.employee_id', 'ed.employee_card_id',
                'ed.personal_account_number', 'ed.ifsc_code', 'ed.pf_account_number', 'ed.esi_number',
                'ed.aadhar_card_number', 'ed.department_id', 'ed.designation_id', 'ed.joining_date',
                'ed.employment_type'])
            ->map(fn ($row) => [
                'Ref' => $row->id,
                'Name' => $row->name,
                'Employee Code' => $row->employee_card_id,
                'Employee ID' => $row->employee_id,
                'Annual CTC' => $row->employee_ctc !== null ? (float) $row->employee_ctc : null,
                'Basic %' => $row->basic_for_payroll !== null ? (float) $row->basic_for_payroll : null,
                'Monthly TDS' => $row->monthly_tds !== null ? (float) $row->monthly_tds : null,
                'PAN' => $row->pan_number,
                'Mobile' => $row->mobile,
                'Bank Account No' => $row->personal_account_number,
                'IFSC Code' => $row->ifsc_code,
                'PF Account No' => $row->pf_account_number,
                'ESI Number' => $row->esi_number,
                'Aadhaar' => $row->aadhar_card_number,
                'Department' => $departments[$row->department_id] ?? null,
                'Designation' => $designations[$row->designation_id] ?? null,
                'Joining Date' => $row->joining_date ? Carbon::parse($row->joining_date)->format('Y-m-d') : null,
                'Employment Type' => $row->employment_type,
            ])
            ->all();
    }

    /**
     * Read an uploaded sheet and work out what it would change.
     *
     * Nothing is written. Every row comes back in one of three states: it
     * changes something, it changes nothing, or it could not be matched to an
     * employee — and anything that fails a check is reported against the row
     * and the column it came from.
     *
     * @param  array<int, array<int, mixed>>  $sheet  raw rows, the first being headings
     * @return array{rows: array, summary: array<string, int>, errors: array}
     */
    public function preview(array $sheet): array
    {
        [$headings, $body] = $this->split($sheet);

        if ($headings === []) {
            return ['rows' => [], 'summary' => $this->summary([]), 'errors' => [['row' => 1,
                'message' => 'The first line of the sheet must be the column headings.']]];
        }

        $known = array_keys(self::COLUMNS);
        $unknown = array_diff(array_filter($headings), $known);
        $errors = [];

        foreach ($unknown as $column) {
            $errors[] = ['row' => 1, 'column' => $column,
                'message' => 'Not a column this import knows; it is ignored.'];
        }

        $people = $this->people();
        $departments = $this->lookup('teams', 'team_name');
        $designations = $this->lookup('designations', 'name');
        $rows = [];

        foreach ($body as $index => $raw) {
            $lineNumber = $index + 2;
            $values = $this->asMap($headings, $raw);
            $ref = trim((string) ($values['Ref'] ?? ''));
            $code = trim((string) ($values['Employee Code'] ?? ''));

            if ($ref === '' && $code === '' && trim(implode('', array_map('strval', $values))) === '') {
                continue;
            }

            // The reference is what the sheet was given; the code is a courtesy
            // for a sheet somebody typed from scratch.
            $person = ($ref !== '' ? ($people['ref'][$ref] ?? null) : null)
                ?? ($code !== '' ? ($people['code'][$code] ?? null) : null);

            if (!$person) {
                $rows[] = [
                    'line' => $lineNumber,
                    'code' => $code,
                    'name' => trim((string) ($values['Name'] ?? '')),
                    'state' => 'unmatched',
                    'changes' => [],
                    'problems' => [$ref === '' && $code === ''
                        ? 'This line names nobody: it needs the Ref it was given, or an employee code.'
                        : 'No active employee matches ' . ($ref !== '' ? 'reference ' . $ref : 'the code ' . $code) . '.'],
                ];
                continue;
            }

            [$changes, $problems] = $this->changesFor($person, $values, $departments, $designations);

            $rows[] = [
                'line' => $lineNumber,
                'user_id' => $person->user_id,
                'code' => $code,
                'name' => $person->name,
                'state' => $problems !== [] ? 'problem' : ($changes === [] ? 'unchanged' : 'changes'),
                'changes' => $changes,
                'problems' => $problems,
            ];
        }

        return ['rows' => $rows, 'summary' => $this->summary($rows), 'errors' => $errors];
    }

    /**
     * Write the changes a preview worked out.
     *
     * Only rows that change something and carry no problem are written, so
     * applying a sheet that was previewed does exactly what the preview said.
     *
     * @return array{employees: int, fields: int}
     */
    public function apply(array $preview, ?int $by = null): array
    {
        $employees = 0;
        $fields = 0;

        DB::transaction(function () use ($preview, &$employees, &$fields, $by) {
            foreach ($preview['rows'] as $row) {
                if ($row['state'] !== 'changes') {
                    continue;
                }

                $users = [];
                $details = [];

                foreach ($row['changes'] as $change) {
                    $target = self::COLUMNS[$change['column']];
                    $target['table'] === 'users'
                        ? $users[$target['column']] = $change['to']
                        : $details[$target['column']] = $change['to'];
                    $fields++;
                }

                if ($users !== []) {
                    DB::table('users')->where('id', $row['user_id'])->update($users + ['updated_at' => now()]);
                }

                if ($details !== []) {
                    DB::table('employee_details')->where('user_id', $row['user_id'])
                        ->update($details + ['updated_at' => now()]);
                }

                $employees++;
            }
        });

        return ['employees' => $employees, 'fields' => $fields];
    }

    /**
     * What one sheet row would change about one person, and what is wrong with it.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    protected function changesFor(object $person, array $values, array $departments, array $designations): array
    {
        $changes = [];
        $problems = [];

        foreach (self::COLUMNS as $heading => $target) {
            if ($target['table'] === null || !array_key_exists($heading, $values)) {
                continue;
            }

            $raw = $values[$heading];

            // A blank cell means "leave this as it is", never "erase it".
            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }

            [$value, $problem] = $this->clean($heading, $raw, $target['type'], $departments, $designations);

            if ($problem !== null) {
                $problems[] = $heading . ': ' . $problem;
                continue;
            }

            $was = $person->{$target['column']} ?? null;

            // An employee code can be given to somebody who has none; changing
            // one already in use would re-point their payslip history.
            if ($target['type'] === 'code') {
                if (trim((string) $was) !== '' && (string) $was !== (string) $value) {
                    $problems[] = 'Employee Code: ' . $person->name . ' already has the code '
                        . $was . '. It is what their payslips are filed under and cannot be changed here.';
                    continue;
                }

                if (trim((string) $value) !== '' && $this->codeTaken((string) $value, (int) $person->user_id)) {
                    $problems[] = 'Employee Code: ' . $value . ' already belongs to somebody else.';
                    continue;
                }
            }

            if ($this->same($was, $value, $target['type'])) {
                continue;
            }

            $changes[] = [
                'column' => $heading,
                'from' => $this->show($was, $target['type'], $departments, $designations),
                'to' => $value,
                'to_shown' => $this->show($value, $target['type'], $departments, $designations),
            ];
        }

        return [$changes, $problems];
    }

    /**
     * Turn a cell into what the column needs, or say why it cannot be.
     *
     * @return array{0: mixed, 1: ?string}
     */
    protected function clean(string $heading, $raw, string $type, array $departments, array $designations): array
    {
        $value = trim((string) $raw);

        return match ($type) {
            'money' => is_numeric(str_replace([',', '₹', ' '], '', $value))
                ? [round((float) str_replace([',', '₹', ' '], '', $value), 2), null]
                : [null, 'must be a number'],
            'percent' => is_numeric($value) && (float) $value > 0 && (float) $value <= 100
                ? [round((float) $value, 2), null]
                : [null, 'must be a percentage between 0 and 100'],
            'pan' => preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/i', $value)
                ? [strtoupper($value), null]
                : [null, 'does not look like a PAN (ABCDE1234F)'],
            'ifsc' => preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/i', $value)
                ? [strtoupper($value), null]
                : [null, 'does not look like an IFSC code (ABCD0123456)'],
            'code' => preg_match('/^[A-Za-z0-9\-\/]{1,10}$/', $value)
                ? [$value, null]
                : [null, 'must be up to 10 letters, digits, - or /'],
            'date' => $this->asDate($value),
            'department' => isset($departments[mb_strtolower($value)])
                ? [$departments[mb_strtolower($value)], null]
                : [null, 'no department called "' . $value . '"'],
            'designation' => isset($designations[mb_strtolower($value)])
                ? [$designations[mb_strtolower($value)], null]
                : [null, 'no designation called "' . $value . '"'],
            default => [$value, null],
        };
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function asDate(string $value): array
    {
        // Excel hands dates over as a day count when the cell is formatted as one.
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 60000) {
            return [Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString(), null];
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd M Y', 'd-M-Y', 'Y/m/d'] as $format) {
            try {
                // Carbon throws on anything it cannot read in the given shape,
                // so each attempt has to be allowed to fail quietly.
                $parsed = Carbon::createFromFormat($format, $value);
            } catch (\Throwable $notThisFormat) {
                continue;
            }

            if ($parsed && $parsed->format($format) === $value) {
                return [$parsed->toDateString(), null];
            }
        }

        return [null, 'is not a date this understands (use 2026-03-31)'];
    }

    /** Whether what is on record already says the same thing. */
    protected function same($was, $now, string $type): bool
    {
        if ($was === null) {
            return false;
        }

        return match ($type) {
            'money', 'percent' => abs((float) $was - (float) $now) < 0.005,
            'date' => Carbon::parse($was)->toDateString() === $now,
            'department', 'designation' => (int) $was === (int) $now,
            default => (string) $was === (string) $now,
        };
    }

    /** A value as a person would read it. */
    protected function show($value, string $type, array $departments, array $designations)
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2),
            'percent' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') . '%',
            'date' => Carbon::parse($value)->format('d M Y'),
            'department' => array_flip($departments)[(int) $value] ?? $value,
            'designation' => array_flip($designations)[(int) $value] ?? $value,
            default => (string) $value,
        };
    }

    /** @return array<string, int> name in lower case => id */
    protected function lookup(string $table, string $column): array
    {
        $out = [];

        foreach (DB::table($table)->get(['id', $column]) as $row) {
            $out[mb_strtolower(trim((string) $row->$column))] = (int) $row->id;
        }

        return $out;
    }

    /** Whether an employee code is already somebody else's. */
    protected function codeTaken(string $code, int $exceptUserId): bool
    {
        return DB::table('employee_details')
            ->where('employee_card_id', $code)
            ->where('user_id', '!=', $exceptUserId)
            ->exists();
    }

    /**
     * Active employees, reachable by the reference the sheet carries and by
     * their code where they have one.
     *
     * @return array{ref: array, code: array}
     */
    protected function people(): array
    {
        $people = DB::table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->get(['u.id as user_id', 'u.id', 'u.name', 'u.employee_ctc', 'u.basic_for_payroll', 'u.monthly_tds',
                'u.pan_number', 'u.mobile', 'ed.employee_id', 'ed.employee_card_id',
                'ed.personal_account_number', 'ed.ifsc_code', 'ed.pf_account_number', 'ed.esi_number',
                'ed.aadhar_card_number', 'ed.department_id', 'ed.designation_id', 'ed.joining_date',
                'ed.employment_type']);

        $byCode = [];

        foreach ($people as $person) {
            $code = trim((string) $person->employee_card_id);

            if ($code !== '') {
                $byCode[$code] = $person;
            }
        }

        return ['ref' => $people->keyBy('user_id')->all(), 'code' => $byCode];
    }

    /** @return array{0: array<int, string>, 1: array<int, array>} */
    protected function split(array $sheet): array
    {
        foreach ($sheet as $index => $line) {
            $cells = array_map(fn ($c) => trim((string) $c), (array) $line);

            if (in_array('Ref', $cells, true) || in_array('Employee Code', $cells, true)) {
                return [$cells, array_slice($sheet, $index + 1)];
            }
        }

        return [[], []];
    }

    /** @return array<string, mixed> */
    protected function asMap(array $headings, $line): array
    {
        $cells = array_values((array) $line);
        $out = [];

        foreach ($headings as $position => $heading) {
            if ($heading !== '') {
                $out[$heading] = $cells[$position] ?? null;
            }
        }

        return $out;
    }

    /** @return array<string, int> */
    protected function summary(array $rows): array
    {
        return [
            'rows' => count($rows),
            'changes' => count(array_filter($rows, fn ($r) => $r['state'] === 'changes')),
            'unchanged' => count(array_filter($rows, fn ($r) => $r['state'] === 'unchanged')),
            'unmatched' => count(array_filter($rows, fn ($r) => $r['state'] === 'unmatched')),
            'problems' => count(array_filter($rows, fn ($r) => $r['state'] === 'problem')),
            'fields' => array_sum(array_map(fn ($r) => count($r['changes']), $rows)),
        ];
    }

}
