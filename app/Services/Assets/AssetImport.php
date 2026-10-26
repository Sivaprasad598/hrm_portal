<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\User;
use App\Scopes\ActiveScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bulk import of the asset register from a spreadsheet: read and check every
 * row first (preview), then create what passed (apply).
 *
 * A row can also say who holds the asset today (Employee ID + date), so an
 * existing register with laptops already out can be loaded in one go.
 */
class AssetImport
{

    /** field => [heading in the template, other headings accepted] */
    public const COLUMNS = [
        'name' => ['Asset Name', ['asset', 'name', 'item', 'description']],
        'asset_code' => ['Asset Code', ['code', 'tag', 'asset tag', 'asset no', 'asset number']],
        'type' => ['Asset Type', ['type', 'category']],
        'serial_number' => ['Serial Number', ['serial', 'serial no', 'sn']],
        'brand' => ['Brand', ['make', 'manufacturer']],
        'model' => ['Model', []],
        'branch' => ['Branch', ['location']],
        'condition' => ['Condition', []],
        'purchase_date' => ['Purchase Date', ['purchased on', 'date of purchase']],
        'purchase_cost' => ['Purchase Cost', ['cost', 'price', 'amount', 'value']],
        'vendor' => ['Vendor', ['supplier']],
        'warranty_until' => ['Warranty Until', ['warranty', 'warranty end', 'warranty expiry']],
        'notes' => ['Notes', ['remarks', 'comments']],
        'employee_code' => ['Assigned To (Employee ID)', ['assigned to', 'employee id', 'employee code', 'emp id', 'holder']],
        'assigned_on' => ['Assigned On', ['assigned date', 'issued on', 'issue date']],
    ];

    public const MAX_ROWS = 2000;

    public function template(): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Assets');
        $headings = array_column(self::COLUMNS, 0);
        $sheet->fromArray($headings, null, 'A1');
        $sheet->fromArray(['Dell Latitude 5420', 'AST-0101', 'Laptop', 'CN0X1234', 'Dell', 'Latitude 5420', 'Visakhapatnam', 'Good',
            '10-01-2026', '65000', 'Sri Computers', '10-01-2027', 'With charger and bag', '', ''], null, 'A2');
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $help = $book->createSheet()->setTitle('How to fill');
        $rows = [
            ['Column', 'What to put'],
            ['Asset Name', 'Required.'],
            ['Asset Code', 'Unique tag. Leave empty to number it automatically (AST-0001...).'],
            ['Asset Type', 'One of: ' . AssetType::orderBy('name')->pluck('name')->implode(', ') . '. A new name creates the type.'],
            ['Branch', 'One of: ' . (Branch::orderBy('branch_name')->pluck('branch_name')->implode(', ') ?: '(none set up)') . '.'],
            ['Condition', 'New, Good, Fair or Damaged. Empty means Good.'],
            ['Dates', 'Purchase Date, Warranty Until, Assigned On: DD-MM-YYYY or an Excel date.'],
            ['Assigned To (Employee ID)', 'Only if somebody holds it now: their Employee ID. Assigned On is then required.'],
        ];
        $help->fromArray($rows, null, 'A1');
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(28);
        $help->getColumnDimension('B')->setWidth(110);
        $book->setActiveSheetIndex(0);

        $path = storage_path('app/asset-import-template-' . uniqid() . '.xlsx');
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** @return array{rows: array, headings: array, unknown: array, counts: array, error: ?string} */
    public function preview(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getSheet(0);
        } catch (\Throwable $e) {
            return $this->fail('The file could not be read as a spreadsheet.');
        }

        $data = $sheet->toArray(null, true, false, false);
        $headerRow = null;

        // The heading row is the first row naming the asset.
        foreach (array_slice($data, 0, 10, true) as $i => $row) {
            if (isset($this->mapHeadings($row)['name'])) {
                $headerRow = $i;
                break;
            }
        }

        if ($headerRow === null) {
            return $this->fail('No heading row with "Asset Name" was found. Download the template and use its headings.');
        }

        $map = $this->mapHeadings($data[$headerRow]);
        $unknown = array_values(array_filter(array_map('trim', array_map('strval', array_diff_key($data[$headerRow], array_flip($map))))));
        $body = array_slice($data, $headerRow + 1, null, true);

        if (count($body) > self::MAX_ROWS) {
            return $this->fail('The sheet has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');
        }

        $types = AssetType::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id]);
        $branches = Branch::pluck('id', 'branch_name')->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id]);
        $existingCodes = Asset::pluck('asset_code')->map(fn ($c) => mb_strtolower($c))->flip();
        $employees = User::withoutGlobalScope(ActiveScope::class)->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->whereNotIn('users.id', \App\Services\Access\SystemAccounts::ids())   // not the Super Admin login
            ->get(['users.id', 'users.name', 'users.status', 'employee_details.employee_id'])
            ->filter(fn ($e) => $e->employee_id !== null && $e->employee_id !== '')
            ->keyBy(fn ($e) => mb_strtolower(trim($e->employee_id)));

        $rows = [];
        $seenCodes = [];

        foreach ($body as $i => $raw) {
            $get = fn ($field) => isset($map[$field]) ? trim((string) ($raw[$map[$field]] ?? '')) : '';

            if (implode('', array_map(fn ($v) => trim((string) $v), $raw)) === '') {
                continue;
            }

            $errors = [];
            $hints = [];
            $row = ['line' => $i + 1];

            $row['name'] = $get('name');
            if ($row['name'] === '') {
                $errors[] = 'Asset Name is empty.';
            }

            $row['asset_code'] = $get('asset_code');
            if ($row['asset_code'] !== '') {
                $key = mb_strtolower($row['asset_code']);
                if ($existingCodes->has($key)) {
                    $errors[] = 'Asset Code ' . $row['asset_code'] . ' is already in the register.';
                } elseif (isset($seenCodes[$key])) {
                    $errors[] = 'Asset Code ' . $row['asset_code'] . ' is repeated (row ' . $seenCodes[$key] . ').';
                }
                $seenCodes[$key] = $row['line'];
            } else {
                $hints[] = 'Code will be numbered automatically.';
            }

            $type = $get('type');
            $row['type'] = $type;
            $row['type_id'] = $type !== '' ? ($types[mb_strtolower($type)] ?? null) : null;
            if ($type !== '' && !$row['type_id']) {
                $hints[] = 'New type "' . $type . '" will be created.';
            }

            $branch = $get('branch');
            $row['branch'] = $branch;
            $row['branch_id'] = $branch !== '' ? ($branches[mb_strtolower($branch)] ?? null) : null;
            if ($branch !== '' && !$row['branch_id']) {
                $errors[] = 'Branch "' . $branch . '" does not exist.';
            }

            $condition = mb_strtolower($get('condition'));
            $row['condition'] = $condition === '' ? 'good' : (array_key_exists($condition, Asset::CONDITIONS) ? $condition : null);
            if ($row['condition'] === null) {
                $errors[] = 'Condition "' . $get('condition') . '" should be New, Good, Fair or Damaged.';
            }

            foreach (['purchase_date' => 'Purchase Date', 'warranty_until' => 'Warranty Until', 'assigned_on' => 'Assigned On'] as $field => $label) {
                $value = isset($map[$field]) ? ($raw[$map[$field]] ?? null) : null;
                $row[$field] = $this->date($value);
                if ($row[$field] === false) {
                    $errors[] = $label . ' "' . $value . '" is not a date (use DD-MM-YYYY).';
                    $row[$field] = null;
                }
            }

            $cost = str_replace([',', '₹', 'Rs.', 'Rs', ' '], '', $get('purchase_cost'));
            $row['purchase_cost'] = $cost === '' ? null : (is_numeric($cost) && $cost >= 0 ? round((float) $cost, 2) : false);
            if ($row['purchase_cost'] === false) {
                $errors[] = 'Purchase Cost "' . $get('purchase_cost') . '" is not a number.';
                $row['purchase_cost'] = null;
            }

            foreach (['serial_number', 'brand', 'model', 'vendor', 'notes'] as $field) {
                $row[$field] = $get($field) !== '' ? mb_substr($get($field), 0, $field === 'notes' ? 2000 : 191) : null;
            }

            $code = $get('employee_code');
            $row['employee_code'] = $code;
            $row['user_id'] = null;
            $row['employee_name'] = null;
            if ($code !== '') {
                $employee = $employees[mb_strtolower($code)] ?? null;
                if (!$employee) {
                    $errors[] = 'No employee has the Employee ID ' . $code . '.';
                } elseif ($employee->status !== 'active') {
                    $errors[] = $employee->name . ' (' . $code . ') is not active.';
                } else {
                    $row['user_id'] = $employee->id;
                    $row['employee_name'] = $employee->name;
                    if (!$row['assigned_on']) {
                        $errors[] = 'Assigned On is needed when the asset is with somebody.';
                    }
                }
            } elseif ($row['assigned_on']) {
                $hints[] = 'Assigned On ignored: nobody is named in Assigned To.';
            }

            $row['errors'] = $errors;
            $row['hints'] = $hints;
            $rows[] = $row;
        }

        $valid = count(array_filter($rows, fn ($r) => !$r['errors']));

        return [
            'rows' => $rows,
            'unknown' => $unknown,
            'counts' => [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => count($rows) - $valid,
                'assigned' => count(array_filter($rows, fn ($r) => !$r['errors'] && $r['user_id'])),
            ],
            'error' => count($rows) ? null : 'The sheet has no asset rows under the headings.',
        ];
    }

    /**
     * Create the rows that passed. Rows with errors are skipped.
     *
     * @return array{created: int, assigned: int, types: int}
     */
    public function apply(array $rows, bool $notify, int $companyId): array
    {
        $flow = app(AssetFlow::class);
        $created = $assigned = $types = 0;

        DB::transaction(function () use ($rows, $notify, $companyId, $flow, &$created, &$assigned, &$types) {
            $typeIds = AssetType::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id])->all();

            // Codes given further down the sheet are kept free for those rows.
            $reserved = collect($rows)->filter(fn ($r) => empty($r['errors']) && $r['asset_code'] !== '')
                ->map(fn ($r) => mb_strtolower($r['asset_code']))->flip();
            $freeCode = function () use ($reserved) {
                $number = (int) Asset::where('asset_code', 'like', 'AST-%')->pluck('asset_code')->map(fn ($c) => (int) substr($c, 4))->max();

                do {
                    $code = 'AST-' . str_pad((string) ++$number, 4, '0', STR_PAD_LEFT);
                } while ($reserved->has(mb_strtolower($code)));

                return $code;
            };

            foreach ($rows as $row) {
                if (!empty($row['errors'])) {
                    continue;
                }

                $typeId = $row['type_id'];
                if (!$typeId && $row['type'] !== '') {
                    $key = mb_strtolower($row['type']);
                    if (!isset($typeIds[$key])) {
                        $typeIds[$key] = AssetType::create(['company_id' => $companyId, 'name' => $row['type']])->id;
                        $types++;
                    }
                    $typeId = $typeIds[$key];
                }

                $asset = Asset::create([
                    'company_id' => $companyId,
                    'asset_type_id' => $typeId,
                    'name' => $row['name'],
                    'asset_code' => $row['asset_code'] !== '' ? $row['asset_code'] : $freeCode(),
                    'serial_number' => $row['serial_number'],
                    'brand' => $row['brand'],
                    'model' => $row['model'],
                    'branch_id' => $row['branch_id'],
                    'condition' => $row['condition'],
                    'purchase_date' => $row['purchase_date'],
                    'purchase_cost' => $row['purchase_cost'],
                    'vendor' => $row['vendor'],
                    'warranty_until' => $row['warranty_until'],
                    'notes' => $row['notes'],
                    'status' => 'available',
                    'created_by' => user()?->id,
                ]);
                $flow->log($asset, 'created', null, null, 'Imported from a spreadsheet');
                $created++;

                if ($row['user_id']) {
                    $employee = User::withoutGlobalScope(ActiveScope::class)->find($row['user_id']);
                    $flow->assign($asset, $employee, [
                        'assigned_on' => $row['assigned_on'],
                        'condition' => $row['condition'],
                        'notes' => 'Imported: already with the employee',
                    ], $notify);
                    $assigned++;
                }
            }
        });

        return ['created' => $created, 'assigned' => $assigned, 'types' => $types];
    }

    /** heading position => field */
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

    /** Y-m-d, null when empty, false when not a date. */
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

            // Accepts 1-1-2026 as well as 01-01-2026, but not 31-02-2026.
            if ($date && (!$problems || ($problems['warning_count'] === 0 && $problems['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }

    protected function fail(string $message): array
    {
        return ['rows' => [], 'unknown' => [], 'counts' => ['total' => 0, 'valid' => 0, 'invalid' => 0, 'assigned' => 0], 'error' => $message];
    }

}
