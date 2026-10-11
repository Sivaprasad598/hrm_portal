<?php

namespace App\Console\Commands;

use App\Models\IappDevice;
use App\Models\IappSyncLog;
use App\Services\Iapp\IappClient;
use App\Services\Iapp\IappConfig;
use App\Services\Iapp\Sync\DeviceSync;
use Illuminate\Console\Command;

/**
 * Refreshes the controller/reader list from iApp.
 *
 * Read-only: deviceMaster/getdevices only.
 */
class IappSyncDevices extends Command
{

    protected $signature = 'iapp:sync-devices
                            {--location=* : Location code(s) to sync. Defaults to the configured location, or those seen in the punch feed.}';

    protected $description = 'Refresh the biometric device list from iApp';

    public function handle(): int
    {
        $config = new IappConfig();

        if (!$config->isConfigured()) {
            $this->error('iApp connection is not configured.');

            return self::FAILURE;
        }

        $locations = $this->option('location') ?: null;

        $log = (new DeviceSync(new IappClient($config)))->run($locations, 'command');

        if ($log->status === IappSyncLog::STATUS_FAILED) {
            $this->error('Device sync failed: ' . $log->message);

            return self::FAILURE;
        }

        $this->info($log->message);

        // Reported per controller rather than per row: getdevices returns one
        // row per gate, so a four-door controller arrives as four records.
        $devices = IappDevice::withoutGlobalScopes()->distinct()->count('controller_code');
        $gates = IappDevice::withoutGlobalScopes()->count();

        $this->line("  stored: {$devices} device(s) across {$gates} gate(s)");

        return self::SUCCESS;
    }

}
