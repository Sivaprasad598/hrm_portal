<?php

namespace App\Services\Iapp\Reports;

use App\Models\IappEmployee;
use App\Models\User;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Sync\EmployeeSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The two employee lists side by side: the ERP's, the devices', and the overlap.
 *
 * The devices are where people are enrolled first, so the two populations are
 * nothing like the same size — 9 ERP employees against 173 biometric codes here.
 * Most codes belong to people the ERP has never heard of, and until somebody
 * looks at them they are invisible: their punches are stored and reported, but
 * no attendance can ever be written for them.
 *
 * This puts all three groups on one screen and lets a device-only person be
 * created in the ERP from what the devices actually know — a name and a code.
 */
class EmployeeComparison
{

    /** Enrolments that are a door or a test account rather than a person. */
    protected const SHARED_PATTERNS = '/^(security|temp|test|guest|visitor|admin|reception|office)[-_ ]?\d*$/i';

    public function __construct(
        protected IappConfig $config
    ) {
    }

    /**
     * @return array{matched:array, erp_only:array, device_only:array, counts:array}
     */
    public function build(): array
    {
        $companyId = $this->config->companyId();

        $erp = DB::table('users')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'employee')
            ->when($companyId, fn ($q) => $q->where('users.company_id', $companyId))
            ->select(
                'users.id', 'users.name', 'users.email', 'users.status',
                'employee_details.employee_id', 'employee_details.employee_card_id',
                'employee_details.iapp_emp_code', 'employee_details.designation_id',
                'employee_details.department_id', 'employee_details.joining_date'
            )
            ->distinct()
            ->orderBy('users.name')
            ->get();

        $devices = IappEmployee::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('punch_count')
            ->get();

        // Punch and attendance totals for every code, in two queries rather
        // than two per row.
        $stats = $this->stats($companyId);
        $byCode = $devices->keyBy(fn ($d) => (string) $d->emp_code);

        $matched = [];
        $erpOnly = [];

        foreach ($erp as $employee) {
            $code = (string) ($employee->iapp_emp_code ?? '');
            $device = $code !== '' ? $byCode->get($code) : null;

            if (!$device) {
                $erpOnly[] = [
                    'user_id' => (int) $employee->id,
                    'name' => $employee->name,
                    'employee_id' => $employee->employee_id,
                    'employee_card_id' => $employee->employee_card_id,
                    'email' => $employee->email,
                    'status' => $employee->status,
                ];

                continue;
            }

            $matched[] = [
                'user_id' => (int) $employee->id,
                'name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'device_id' => (int) $device->id,
                'emp_code' => $code,
                'device_name' => $device->employee_name,
                'punches' => $stats['punches'][$code] ?? 0,
                'attendance_days' => $stats['days'][$code] ?? 0,
                'written' => $stats['written'][(int) $employee->id] ?? 0,
                'last_seen' => $device->last_seen_at?->format('d M Y H:i'),
            ];
        }

        $deviceOnly = [];

        foreach ($devices as $device) {
            if ($device->user_id !== null) {
                continue;
            }

            $code = (string) $device->emp_code;
            $name = trim((string) $device->employee_name);

            $deviceOnly[] = [
                'device_id' => (int) $device->id,
                'emp_code' => $code,
                'employee_name' => $name !== '' ? $name : null,
                'punches' => $stats['punches'][$code] ?? 0,
                'attendance_days' => $stats['days'][$code] ?? 0,
                'first_seen' => $device->first_seen_at?->format('d M Y'),
                'last_seen' => $device->last_seen_at?->format('d M Y H:i'),
                'ignored' => $device->map_status === IappEmployee::STATUS_IGNORED,
                // A shared door account or a test enrolment should be ignored,
                // not turned into an employee.
                'looks_shared' => $name === '' || $name === $code || preg_match(self::SHARED_PATTERNS, $name) === 1,
                'creatable' => $name !== '' && $device->map_status !== IappEmployee::STATUS_IGNORED,
            ];
        }

        return [
            'matched' => $matched,
            'erp_only' => $erpOnly,
            'device_only' => $deviceOnly,
            'counts' => [
                'erp' => $erp->count(),
                'devices' => $devices->count(),
                'matched' => count($matched),
                'erp_only' => count($erpOnly),
                'device_only' => count($deviceOnly),
                'shared' => count(array_filter($deviceOnly, fn ($d) => $d['looks_shared'])),
            ],
        ];
    }

    /**
     * Punch counts, derived attendance days and ERP rows written, per code.
     */
    protected function stats(?int $companyId): array
    {
        $punches = DB::table('iapp_device_transactions')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('emp_code')
            ->selectRaw('emp_code, COUNT(*) AS total')
            ->groupBy('emp_code')
            ->pluck('total', 'emp_code')
            ->all();

        $days = DB::table('iapp_attendance_records')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('emp_id')
            ->selectRaw('emp_id, COUNT(*) AS total')
            ->groupBy('emp_id')
            ->pluck('total', 'emp_id')
            ->all();

        $written = DB::table('iapp_attendance_links')
            ->where('link_type', 'created')
            ->whereNotNull('attendance_id')
            ->selectRaw('user_id, COUNT(*) AS total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->all();

        return ['punches' => $punches, 'days' => $days, 'written' => $written];
    }

    /**
     * Create an ERP employee from what the devices know.
     *
     * Only two facts exist to work with — the person's name and their
     * enrolment code — so only those are written. Every other field the
     * employee form demands (PAN, passport, bank, department, designation) is
     * left empty for HR to complete; the ERP's schema allows that, and one of
     * the existing employees already has no department or designation.
     *
     * Nothing is invented. In particular no email address is fabricated: the
     * column is nullable and unique per company, so an employee simply has
     * none until HR supplies a real one.
     *
     * @return array{ok:bool, message:?string, user_id:?int}
     */
    public function createFromDevice(IappEmployee $device, ?int $actorId = null): array
    {
        $name = trim((string) $device->employee_name);

        if ($name === '') {
            return ['ok' => false, 'message' => 'That code has no name on the device, so there is nothing to create from.', 'user_id' => null];
        }

        if ($device->user_id !== null) {
            return ['ok' => false, 'message' => 'That code is already linked to an ERP employee.', 'user_id' => null];
        }

        $code = (string) $device->emp_code;
        $companyId = $this->config->companyId();

        $taken = DB::table('employee_details')->where('iapp_emp_code', $code)->value('user_id');

        if ($taken) {
            $existing = DB::table('users')->where('id', $taken)->value('name');

            return ['ok' => false, 'message' => "Code {$code} already belongs to {$existing}.", 'user_id' => null];
        }

        $userId = null;

        DB::transaction(function () use ($name, $code, $companyId, $device, $actorId, &$userId) {
            $user = new User();
            $user->name = $name;
            $user->company_id = $companyId;
            // Never used to sign in: login stays disabled until HR completes the
            // record, so a half-built account is not a way in.
            $user->password = Hash::make(Str::random(40));
            $user->login = 'disable';
            $user->status = 'active';
            $user->dark_theme = 0;
            $user->rtl = 0;
            $user->save();

            $userId = $user->id;

            $roleId = DB::table('roles')
                ->where('name', 'employee')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->value('id') ?? DB::table('roles')->where('name', 'employee')->value('id');

            if ($roleId) {
                DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
            }

            // joining_date is NOT NULL and defaults to CURRENT_TIMESTAMP, so
            // leaving it out would silently record today as this person's start
            // date — someone punching since March would look like a new joiner.
            // The earliest punch on record is at least evidence rather than
            // invention, and it is labelled provisional below.
            $firstSeen = $device->first_seen_at;

            DB::table('employee_details')->insert([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'iapp_emp_code' => $code,
                'iapp_linked_at' => now(),
                'joining_date' => ($firstSeen ?: now())->format('Y-m-d H:i:s'),
                // Says plainly where the record came from and what is missing,
                // so an incomplete employee is never mistaken for a finished one.
                'about_me' => 'Created from biometric device code ' . $code
                    . ' on ' . now()->format('d M Y') . '. '
                    . ($firstSeen
                        ? 'Joining date is provisional — it is the earliest punch on record ('
                            . $firstSeen->format('d M Y') . '), not a confirmed start date. '
                        : '')
                    . 'Details still to be completed by HR.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $device->mapTo($user->id, IappEmployee::METHOD_MANUAL, 'created-from-device', $actorId);
        });

        // Attribute the history this person already has on the devices.
        (new EmployeeSync($this->config))->applyMappings();

        return ['ok' => true, 'message' => null, 'user_id' => $userId];
    }

}
