<?php

namespace App\Services\Iapp;

/**
 * deviceMaster/getdevices — the access controllers and readers.
 *
 * The server returns one row per *gate*, so several rows share a
 * controllerCode (Muralinagar-ii reports four doors). Callers must key on
 * controller + gate, not controller alone.
 */
class DeviceEndpoint
{

    public const PATH = 'api/deviceMaster/getdevices';

    public function __construct(
        protected IappClient $client
    ) {
    }

    /**
     * Devices for one location.
     *
     * Parameters go in a JSON body on a GET: query-string parameters are
     * rejected with HTTP 400, and POST with 405.
     */
    public function forLocation(string $companyCode, string $locationCode): IappResponse
    {
        return $this->client->get(self::PATH, [], [
            'company_code' => $companyCode,
            'location_code' => $locationCode,
        ]);
    }

    /**
     * Normalise a device row into iapp_devices column names.
     *
     * Field casing is inconsistent in the source (ControllerStatus and
     * LastActivityOn are PascalCase while the rest are camelCase), so each is
     * looked up under both.
     */
    public static function normalise(array $row, string $locationCode): array
    {
        $gateNo = IappResponse::scalar($row, 'gateNo', 'GateNo');

        return [
            'controller_code' => IappResponse::scalar($row, 'controllerCode', 'ControllerCode') ?? '',
            'controller_id' => ($id = IappResponse::scalar($row, 'controllerID', 'controllerId')) !== null ? (int) $id : null,
            'controller_name' => IappResponse::scalar($row, 'controllerName', 'ControllerName'),
            'location_code' => $locationCode,
            'ip_address' => IappResponse::scalar($row, 'ipAddress', 'IpAddress'),
            'port' => ($p = IappResponse::scalar($row, 'port', 'Port')) !== null ? (int) $p : null,
            'mac_address' => IappResponse::scalar($row, 'macAddress', 'MacAddress'),
            'controller_model' => IappResponse::scalar($row, 'controllerModel', 'ControllerModel'),
            'controller_status' => IappResponse::scalar($row, 'ControllerStatus', 'controllerStatus'),
            'device_mode' => IappResponse::scalar($row, 'deviceMode', 'DeviceMode'),
            'controller_mode' => IappResponse::scalar($row, 'controllerMode', 'ControllerMode'),
            'entry_auth_mode' => IappResponse::scalar($row, 'entryAuthMode', 'EntryAuthMode'),
            'exit_auth_mode' => IappResponse::scalar($row, 'exitAuthMode', 'ExitAuthMode'),
            'gate_no' => $gateNo,
            // Never null, so the unique index stays usable for upserts.
            'gate_key' => $gateNo ?? '',
            'gate_name' => IappResponse::scalar($row, 'gateName', 'GateName'),
            'reference_code' => IappResponse::scalar($row, 'ReferenceCode', 'referenceCode'),
            'last_activity_on' => IappHelper::parseDateTime(
                IappResponse::scalar($row, 'LastActivityOn', 'lastActivityOn')
            ),
            'raw_payload' => $row,
        ];
    }

}
