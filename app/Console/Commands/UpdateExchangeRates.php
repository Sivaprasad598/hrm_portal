<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\GlobalSetting;
use App\Models\Invoice;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\Expense;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateExchangeRates extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-exchange-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the exchange rates for all the currencies in currencies table.';


    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        $this->info('Exchange rate update started.');

        $globalSetting = GlobalSetting::first();

        if (!$globalSetting) {
            $this->warn('No global settings record found. Cannot update exchange rates.');

            return Command::SUCCESS;
        }

        $currencyApiKey = ($globalSetting->currency_converter_key) ?: config('app.currency_converter_key');

        if ($globalSetting->currency_key_version == 'dedicated') {
            $currencyApiKeyVersion = $globalSetting->dedicated_subdomain;
        } else {
            $currencyApiKeyVersion = $globalSetting->currency_key_version === 'premium' ? 'api' : $globalSetting->currency_key_version;
        }

        if ($currencyApiKey && $currencyApiKeyVersion) {

            $this->fetchRemoteCurrencyRates(new Client(), $currencyApiKey, $currencyApiKeyVersion);

            $this->syncStoredRatesFromCurrencies();

            $this->info('Exchange rate update finished.');

            return Command::SUCCESS;

        }

        $this->warn('Currency converter API key or host version is missing. Configure it in settings or .env. Skipping update.');

        return Command::SUCCESS;
    }

    /**
     * Build unique currconv pairs across all companies, then fetch in batched HTTP calls (comma-separated q)
     * and apply by currency id. Skips API when from/to codes are identical (rate = 1).
     */
    private function fetchRemoteCurrencyRates(Client $client, string $currencyApiKey, string $currencyApiKeyVersion): void
    {
        $this->line('Scanning companies and currencies…');

        $pairToCurrencyIds = [];
        $sameCurrencyCount = 0;
        $companiesSkippedNoBase = 0;

        Company::with(['currencies', 'currency'])
            ->chunk(50, function ($companies) use (&$pairToCurrencyIds, &$sameCurrencyCount, &$companiesSkippedNoBase) {
                foreach ($companies as $company) {
                    if (!$company->currency) {
                        $companiesSkippedNoBase++;

                        continue;
                    }

                    $baseCode = $company->currency->currency_code;

                    foreach ($company->currencies as $currency) {
                        $from = $currency->currency_code;

                        if ($from === $baseCode) {
                            $currency->exchange_rate = 1;
                            $currency->saveQuietly();
                            $sameCurrencyCount++;

                            continue;
                        }

                        $pair = $from . '_' . $baseCode;
                        $pairToCurrencyIds[$pair][] = $currency->id;
                    }
                }
            });

        if ($companiesSkippedNoBase > 0) {
            $this->warn("{$companiesSkippedNoBase} company/companies skipped (no default currency configured).");
        }

        if ($sameCurrencyCount > 0) {
            $this->line("Set exchange_rate to 1 for {$sameCurrencyCount} currency row(s) matching the company base (no API call).");
        }

        if ($pairToCurrencyIds === []) {
            $this->info('No remote conversion pairs to fetch. Done with API step.');

            return;
        }

        $uniquePairs = count($pairToCurrencyIds);
        $currencyRows = array_sum(array_map('count', $pairToCurrencyIds));
        $this->info("Found {$uniquePairs} unique pair(s) for {$currencyRows} currency row(s). Calling currconv…");

        $currencyTable = (new Currency)->getTable();
        $baseUrl = 'https://' . $currencyApiKeyVersion . '.currconv.com/api/v7/convert';

        $chunks = array_chunk(array_keys($pairToCurrencyIds), 15);
        $totalBatches = count($chunks);
        $rowsUpdatedFromApi = 0;

        foreach ($chunks as $index => $pairChunk) {
            $batchNum = $index + 1;
            $pairCount = count($pairChunk);
            $this->line("API request batch {$batchNum}/{$totalBatches} ({$pairCount} pair(s))…");

            $q = implode(',', $pairChunk);

            try {
                $response = $client->request('GET', $baseUrl, [
                    'query' => [
                        'q' => $q,
                        'compact' => 'ultra',
                        'apiKey' => $currencyApiKey,
                    ],
                    'timeout' => 30,
                ]);
                $data = json_decode($response->getBody()->getContents(), true);

                if (!is_array($data)) {
                    $this->warn("Batch {$batchNum}: unexpected response body; skipped.");

                    continue;
                }

                $batchRows = 0;

                foreach ($data as $pair => $rate) {
                    if (!isset($pairToCurrencyIds[$pair]) || !is_numeric($rate)) {
                        continue;
                    }

                    $ids = array_unique($pairToCurrencyIds[$pair]);

                    $affected = DB::table($currencyTable)->whereIn('id', $ids)->update([
                        'exchange_rate' => $rate,
                        'updated_at' => now(),
                    ]);

                    $rowsUpdatedFromApi += $affected;
                    $batchRows += $affected;
                }

                $this->line("  Batch {$batchNum} complete: {$batchRows} currency row(s) updated.");
            } catch (Exception $e) {
                $this->error("Batch {$batchNum} failed: {$e->getMessage()}");
            }
        }

        $this->info("Remote rates applied to {$rowsUpdatedFromApi} currency row(s) in total.");
    }

    /**
     * Copy current exchange_rate from currencies onto invoice/payment/expense rows (single UPDATE per table).
     */
    private function syncStoredRatesFromCurrencies(): void
    {
        $this->line('Syncing invoice, payment, and expense rows from currencies table…');

        $currencyTable = (new Currency)->getTable();

        foreach ([
            'invoices' => (new Invoice)->getTable(),
            'payments' => (new Payment)->getTable(),
            'expenses' => (new Expense)->getTable(),
        ] as $label => $table) {
            $affected = DB::table($table)
                ->join($currencyTable, "{$table}.currency_id", '=', "{$currencyTable}.id")
                ->whereNotNull("{$table}.currency_id")
                ->update([
                    "{$table}.exchange_rate" => DB::raw("`{$currencyTable}`.`exchange_rate`"),
                ]);

            $this->line("  {$label}: {$affected} row(s) updated.");
        }

        $this->info('Stored exchange rates synced from currencies.');
    }

}
