<?php

namespace App\Services\Iapp\Sync;

use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\DB;

/**
 * Maps iApp employee codes to ERP users.
 *
 * Builds the whole lookup in one query and answers from memory afterwards.
 * Resolving per punch instead would mean one query per row — for a normal
 * day's 1,100+ punches that is 1,100 round trips to return at most a few
 * hundred distinct codes.
 */
class EmployeeResolver
{

    /** code => user_id */
    protected array $map = [];

    /** Codes looked up that matched nothing. */
    protected array $unmatched = [];

    /** Codes resolved by the explicit bridge rather than the fallback guess. */
    protected array $bridged = [];

    protected bool $loaded = false;

    public function __construct(
        protected IappConfig $config
    ) {
    }

    /**
     * Load the map for the given codes.
     *
     * @param  array<int, string|null>  $codes
     */
    /** Was this code resolved by an explicit mapping rather than a guess? */
    public function isBridged(string $code): bool
    {
        return isset($this->bridged[trim($code)]);
    }

    public function bridgedCount(): int
    {
        return count($this->bridged);
    }

    public function load(array $codes): self
    {
        $codes = array_values(array_unique(array_filter(
            array_map(fn ($c) => is_string($c) ? trim($c) : null, $codes)
        )));

        if ($codes === []) {
            $this->loaded = true;

            return $this;
        }

        $column = $this->config->matchField();

        // Chunked so a large distinct-code set never builds an oversized IN().
        foreach (array_chunk($codes, 1000) as $chunk) {
            // The device code is the bridge: it is assigned on the devices, it
            // never changes, and it is what every punch carries. So the code
            // written on the employee record decides ownership, and it is read
            // first — before this, resolution went through the configured match
            // field instead, which meant four of five deliberately mapped
            // employees resolved to nobody and a re-sync wiped 462 punches'
            // attribution.
            $bridged = DB::table('employee_details')
                ->whereIn('iapp_emp_code', $chunk)
                ->whereNotNull('user_id')
                ->pluck('user_id', 'iapp_emp_code');

            foreach ($bridged as $code => $userId) {
                $this->map[(string) $code] = (int) $userId;
                $this->bridged[(string) $code] = true;
            }

            // Fallback, for codes nobody has mapped yet: guess from whichever
            // ERP column the settings nominate. Never allowed to override an
            // explicit mapping.
            $unbridged = array_values(array_filter(
                $chunk,
                fn ($code) => !isset($this->map[(string) $code])
            ));

            if ($unbridged === []) {
                continue;
            }

            $rows = DB::table('employee_details')
                ->whereIn($column, $unbridged)
                ->whereNotNull('user_id')
                ->pluck('user_id', $column);

            foreach ($rows as $code => $userId) {
                $this->map[(string) $code] = (int) $userId;
            }
        }

        $this->unmatched = array_values(array_diff($codes, array_keys($this->map)));
        $this->loaded = true;

        return $this;
    }

    public function resolve(?string $code): ?int
    {
        if ($code === null) {
            return null;
        }

        return $this->map[trim($code)] ?? null;
    }

    /** @return array<int, string> */
    public function unmatchedCodes(): array
    {
        return $this->unmatched;
    }

    public function matchedCount(): int
    {
        return count($this->map);
    }

    public function unmatchedCount(): int
    {
        return count($this->unmatched);
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function matchField(): string
    {
        return $this->config->matchField();
    }

}
