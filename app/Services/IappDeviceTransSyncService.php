<?php

namespace App\Services;

use App\Models\EmployeeDetails;
use App\Models\IappDeviceTransaction;
use Carbon\Carbon;

class IappDeviceTransSyncService
{

    protected IappService $iapp;

    public function __construct(IappService $iapp)
    {
        $this->iapp = $iapp;
    }

    /**
     * Pull raw punches from iApp's deviceTrans/get and store them as-is in
     * iapp_device_transactions — one row per punch. This does not touch
     * `attendances`; pairing punches into clock-in/clock-out sessions is a
     * separate step once this data is flowing.
     *
     * @param string $filter required by iApp, e.g. "empcode eq '1000603'"
     * @param array $select extra fields to request beyond the ones the sync
     *   itself depends on (empcode, controllerCode, cardid, PunchDetail)
     */
    public function sync(string $filter, array $select = []): array
    {
        $select = array_values(array_unique(array_merge(
            ['empcode', 'controllerCode', 'cardid', 'PunchDetail'],
            $select
        )));

        $records = $this->iapp->getAllDeviceTrans($filter, $select);

        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'unmatched' => []];

        foreach ($records as $record) {
            $punchTime = $this->parsePunchTime($record['PunchDetail'] ?? null);

            if (!$punchTime) {
                $summary['skipped']++;
                continue;
            }

            $empCode = $record['empcode'] ?? null;
            $employeeDetail = $empCode ? EmployeeDetails::where('employee_id', $empCode)->first() : null;

            if ($empCode && !$employeeDetail) {
                $summary['unmatched'][] = $empCode;
            }

            $attributes = [
                'company_id' => $employeeDetail->company_id ?? null,
                'user_id' => $employeeDetail->user_id ?? null,
                'emp_code' => $empCode,
                'controller_code' => $record['controllerCode'] ?? null,
                'card_id' => $record['cardid'] ?? null,
                'punch_time' => $punchTime,
                'raw_payload' => $record,
            ];

            $transaction = IappDeviceTransaction::updateOrCreate(
                [
                    'controller_code' => $attributes['controller_code'],
                    'card_id' => $attributes['card_id'],
                    'punch_time' => $attributes['punch_time'],
                ],
                $attributes
            );

            $summary[$transaction->wasRecentlyCreated ? 'created' : 'updated']++;
        }

        $summary['unmatched'] = array_values(array_unique($summary['unmatched']));

        return $summary;
    }

    /**
     * PunchDetail's exact format isn't documented (the doc only shows it as
     * a select-able field name, no example value). Assumed to be a
     * 'DD-MM-YYYY HH:MM' datetime like getDetail's firstIn/lastOut — adjust
     * once a real sample response is available.
     */
    protected function parsePunchTime(?string $punchDetail): ?Carbon
    {
        if (!$punchDetail) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-m-Y H:i', $punchDetail);
        } catch (\Exception $e) {
            return null;
        }
    }

}
