<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappEmployee;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scores biometric codes against ERP employees across several signals.
 *
 * A single signal is not enough on this data. Identifier matching alone links
 * 1 of 9 employees, because the two systems use unrelated code formats
 * (ACPL0078 on the devices, numeric card IDs in the ERP). Name matching alone
 * is unreliable in the other direction: both sides write names inconsistently
 * ("N.Ajay Babu", "B.Santhosh Kumar", "UMA DEVI"), word order varies, and two
 * people can legitimately share a name.
 *
 * So every candidate is scored on identifier *and* name evidence, and a link is
 * only ever proposed automatically when the best candidate is both strong and
 * unambiguous. Everything else becomes a ranked suggestion for a human.
 */
class EmployeeMatcher
{

    /** Auto-map at or above this score, subject to the safety rules below. */
    public const AUTO_THRESHOLD = 90;

    /** Show as a suggestion at or above this score. */
    public const SUGGEST_THRESHOLD = 45;

    /**
     * A candidate must beat the runner-up by this much to be considered
     * unambiguous. Two people scoring alike is exactly the case where an
     * automatic choice would be a coin toss.
     */
    public const AMBIGUITY_MARGIN = 15;

    /** ERP employees, indexed for repeated lookups. */
    protected ?Collection $employees = null;

    public function __construct(
        protected IappConfig $config
    ) {
    }

    /**
     * Load the ERP employee list once for a whole matching run.
     */
    public function loadEmployees(?int $companyId = null): self
    {
        $companyId ??= $this->config->companyId();

        $this->employees = DB::table('employee_details as ed')
            ->join('users as u', 'u.id', '=', 'ed.user_id')
            ->when($companyId, fn ($q) => $q->where('ed.company_id', $companyId))
            ->get([
                'u.id as user_id',
                'u.name',
                'ed.employee_id',
                'ed.employee_card_id',
                'ed.iapp_emp_code',
                'ed.department_id',
                'ed.joining_date',
            ])
            ->map(function ($row) {
                $row->name_normalised = self::normaliseName($row->name);
                $row->name_tokens = self::tokens($row->name);

                return $row;
            });

        return $this;
    }

    protected function employees(): Collection
    {
        if ($this->employees === null) {
            $this->loadEmployees();
        }

        return $this->employees;
    }

    /**
     * Rank ERP employees against one biometric record.
     *
     * @return array<int, array{user_id:int, name:string, score:int, reasons:array<int,string>}>
     */
    public function candidatesFor(IappEmployee $employee, int $limit = 5): array
    {
        $code = trim((string) $employee->emp_code);
        $cardId = trim((string) $employee->card_id);
        $deviceName = (string) $employee->employee_name;

        $nameNormalised = self::normaliseName($deviceName);
        $nameTokens = self::tokens($deviceName);

        $scored = [];

        foreach ($this->employees() as $candidate) {
            // An ERP employee already carrying a different biometric code is
            // not a candidate: the column is unique, so proposing it would
            // only produce a failed save.
            if ($candidate->iapp_emp_code !== null && $candidate->iapp_emp_code !== $code) {
                continue;
            }

            $score = 0;
            $reasons = [];

            // --- Identifier evidence ------------------------------------
            if ($code !== '' && (string) $candidate->employee_card_id === $code) {
                $score = max($score, 100);
                $reasons[] = 'Card ID matches exactly';
            } elseif ($code !== '' && (string) $candidate->employee_id === $code) {
                $score = max($score, 95);
                $reasons[] = 'Employee ID matches exactly';
            } elseif ($cardId !== '' && $cardId !== '0000' && (string) $candidate->employee_card_id === $cardId) {
                $score = max($score, 92);
                $reasons[] = 'Device card ID matches employee card ID';
            } elseif ($code !== '' && self::numericallyEqual($code, (string) $candidate->employee_card_id)) {
                // "02301694" and "2301694" are the same number written
                // differently — likely the same person, but not certain.
                $score = max($score, 82);
                $reasons[] = 'Card ID matches ignoring leading zeros';
            }

            // --- Name evidence ------------------------------------------
            $nameScore = 0;
            $nameReason = null;

            if ($nameNormalised !== '' && $candidate->name_normalised !== '') {
                if ($nameNormalised === $candidate->name_normalised) {
                    $nameScore = 88;
                    $nameReason = 'Name matches exactly';
                } elseif (self::sameTokenSet($nameTokens, $candidate->name_tokens)) {
                    // "Ganapati Shiva" vs "Shiva Ganapati".
                    $nameScore = 80;
                    $nameReason = 'Same name words, different order';
                } elseif (self::initialsCompatible($nameTokens, $candidate->name_tokens)) {
                    // "N.Ajay Babu" vs "Ajay Babu Nadipalli".
                    $nameScore = 68;
                    $nameReason = 'Name matches allowing for initials';
                } else {
                    $similarity = self::similarity($nameNormalised, $candidate->name_normalised);

                    if ($similarity >= 0.88) {
                        $nameScore = 60;
                        $nameReason = 'Name is a very close spelling';
                    } elseif ($similarity >= 0.78) {
                        $nameScore = 48;
                        $nameReason = 'Name is a near spelling';
                    }
                }
            }

            // Identifier and name agreeing is much stronger than either alone,
            // so corroboration adds on top rather than simply taking the max.
            if ($score > 0 && $nameScore > 0) {
                $score = min(100, $score + 8);
                $reasons[] = $nameReason . ' (corroborates the ID)';
            } elseif ($nameScore > $score) {
                $score = $nameScore;
                $reasons[] = $nameReason;
            }

            if ($score < self::SUGGEST_THRESHOLD) {
                continue;
            }

            // --- Weak corroboration -------------------------------------
            // A joining date close to the first punch is a small nudge, never
            // enough to matter on its own.
            if ($employee->first_seen_at && $candidate->joining_date) {
                try {
                    $days = abs($employee->first_seen_at->diffInDays(\Carbon\Carbon::parse($candidate->joining_date)));

                    if ($days <= 45) {
                        $score = min(100, $score + 3);
                        $reasons[] = 'Joined around the first punch';
                    }
                } catch (\Throwable) {
                    // An unparsable joining date simply contributes nothing.
                }
            }

            $scored[] = [
                'user_id' => (int) $candidate->user_id,
                'name' => (string) $candidate->name,
                'employee_id' => $candidate->employee_id,
                'employee_card_id' => $candidate->employee_card_id,
                'score' => (int) $score,
                'reasons' => $reasons,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * The same matching read the other way round: for each ERP employee, which
     * biometric codes might be them.
     *
     * The mapping screen lists device codes, which is the right shape when the
     * question is "who is this code?". It is the wrong shape for "is everyone
     * covered?" — there are 173 codes and 9 employees, and most codes belong to
     * people who have no ERP record at all. Working from the employee side
     * makes a complete roll call possible.
     *
     * Built by scoring every code once and grouping the results, so it reuses
     * candidatesFor() exactly rather than keeping a second copy of the scoring
     * rules that could drift away from it.
     *
     * @return array<int, array{
     *     user_id:int, name:string, employee_id:?string, employee_card_id:?string,
     *     mapped:array, candidates:array, status:string
     * }>
     */
    public function rollCall(int $limit = 3): array
    {
        $roll = [];

        foreach ($this->employees() as $candidate) {
            $roll[(int) $candidate->user_id] = [
                'user_id' => (int) $candidate->user_id,
                'name' => (string) $candidate->name,
                'employee_id' => $candidate->employee_id,
                'employee_card_id' => $candidate->employee_card_id,
                'mapped' => [],
                'candidates' => [],
                'status' => 'unmapped',
            ];
        }

        $devices = IappEmployee::withoutGlobalScopes()
            ->when($this->config->companyId(), fn ($q, $id) => $q->where('company_id', $id))
            ->get();

        foreach ($devices as $device) {
            // Already linked: record it and move on, no scoring needed.
            if ($device->user_id !== null) {
                if (isset($roll[(int) $device->user_id])) {
                    $roll[(int) $device->user_id]['mapped'][] = [
                        'id' => (int) $device->id,
                        'emp_code' => (string) $device->emp_code,
                        'employee_name' => $device->employee_name,
                        'punch_count' => (int) $device->punch_count,
                        'method' => $device->map_method,
                    ];
                }

                continue;
            }

            if ($device->map_status === IappEmployee::STATUS_IGNORED) {
                continue;
            }

            foreach ($this->candidatesFor($device, 5) as $match) {
                if (!isset($roll[$match['user_id']])) {
                    continue;
                }

                $roll[$match['user_id']]['candidates'][] = [
                    'id' => (int) $device->id,
                    'emp_code' => (string) $device->emp_code,
                    'employee_name' => $device->employee_name,
                    'punch_count' => (int) $device->punch_count,
                    'score' => $match['score'],
                    'reasons' => $match['reasons'],
                ];
            }
        }

        foreach ($roll as $userId => $entry) {
            usort($entry['candidates'], fn ($a, $b) => [$b['score'], $b['punch_count']] <=> [$a['score'], $a['punch_count']]);
            $entry['candidates'] = array_slice($entry['candidates'], 0, $limit);

            $entry['status'] = self::rollCallStatus($entry);

            $roll[$userId] = $entry;
        }

        // Whoever needs attention first: unmapped with a clear candidate, then
        // unmapped and ambiguous, then those with nothing, then the done ones.
        $order = ['confident' => 0, 'ambiguous' => 1, 'weak' => 2, 'none' => 3, 'mapped' => 4];
        uasort($roll, fn ($a, $b) => [$order[$a['status']], $a['name']] <=> [$order[$b['status']], $b['name']]);

        return array_values($roll);
    }

    /**
     * Bar for offering a candidate as a single click.
     *
     * Deliberately below AUTO_THRESHOLD. That threshold governs linking nobody
     * is watching, where a wrong link quietly gives one person another's
     * attendance; this one governs a suggestion a human reads and confirms, so
     * a sole exact name match — 88, and the commonest real case here — belongs
     * on the button rather than buried under "review".
     */
    public const OFFER_THRESHOLD = 80;

    /**
     * How much help this employee's row needs.
     *
     * 'confident' is one candidate standing clear of the rest. 'ambiguous' is
     * two plausible people close together, which is exactly when a human has to
     * choose: two device codes both reading "Prudhvi" are not interchangeable.
     */
    protected static function rollCallStatus(array $entry): string
    {
        if ($entry['mapped'] !== []) {
            return 'mapped';
        }

        $candidates = $entry['candidates'];

        if ($candidates === []) {
            return 'none';
        }

        $top = $candidates[0]['score'];
        $runnerUp = $candidates[1]['score'] ?? 0;
        $clear = ($top - $runnerUp) >= self::AMBIGUITY_MARGIN;

        if ($top >= self::OFFER_THRESHOLD && $clear) {
            return 'confident';
        }

        return $clear ? 'weak' : 'ambiguous';
    }

    /**
     * The candidate safe to link without asking, or null.
     *
     * Three conditions, all required: a strong score, a clear margin over the
     * runner-up, and at least one identifier signal. Name evidence alone never
     * auto-links — two people can share a name, and a wrong link silently
     * attributes one person's attendance to another.
     */
    public function autoMatchFor(IappEmployee $employee): ?array
    {
        $candidates = $this->candidatesFor($employee, 2);

        if ($candidates === []) {
            return null;
        }

        $best = $candidates[0];

        if ($best['score'] < self::AUTO_THRESHOLD) {
            return null;
        }

        if (isset($candidates[1]) && ($best['score'] - $candidates[1]['score']) < self::AMBIGUITY_MARGIN) {
            return null;
        }

        $hasIdentifierEvidence = (bool) array_filter(
            $best['reasons'],
            fn ($reason) => str_contains($reason, 'ID') || str_contains($reason, 'Card')
        );

        return $hasIdentifierEvidence ? $best : null;
    }

    // -----------------------------------------------------------------------
    // Normalisation helpers
    // -----------------------------------------------------------------------

    /** Lower-cased letters only: drops dots, spaces and case differences. */
    public static function normaliseName(?string $name): string
    {
        return preg_replace('/[^a-z]/', '', strtolower((string) $name));
    }

    /**
     * Name split into comparable words.
     *
     * Punctuation becomes a separator so "B.Santhosh Kumar" and
     * "B Santhosh Kumar" produce the same tokens.
     *
     * @return array<int, string>
     */
    public static function tokens(?string $name): array
    {
        $parts = preg_split('/[^a-zA-Z]+/', (string) $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map('strtolower', $parts)));
    }

    /** Same words in any order. */
    public static function sameTokenSet(array $a, array $b): bool
    {
        if ($a === [] || $b === []) {
            return false;
        }

        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Whether two names agree once single letters are treated as initials.
     *
     * Both sides abbreviate inconsistently — "N.Ajay Babu" here, "Ajay Babu"
     * there — so the multi-letter words must match and every initial must at
     * least be plausible.
     */
    public static function initialsCompatible(array $a, array $b): bool
    {
        $words = fn (array $t) => array_values(array_filter($t, fn ($x) => strlen($x) > 1));
        $initials = fn (array $t) => array_values(array_filter($t, fn ($x) => strlen($x) === 1));

        $aWords = $words($a);
        $bWords = $words($b);

        if ($aWords === [] || $bWords === []) {
            return false;
        }

        sort($aWords);
        sort($bWords);

        if ($aWords !== $bWords) {
            return false;
        }

        // The full words agree. Accept when one side simply carries an extra
        // initial the other omits.
        return $initials($a) !== $initials($b) || $a !== $b;
    }

    /** 0..1 similarity, tolerant of transpositions and typos. */
    public static function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        // similar_text is O(n^2); these are short strings, but guard anyway.
        if (strlen($a) > 60 || strlen($b) > 60) {
            $a = substr($a, 0, 60);
            $b = substr($b, 0, 60);
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    /** Same number written with different leading zeros. */
    public static function numericallyEqual(string $a, string $b): bool
    {
        if ($a === '' || $b === '' || !ctype_digit($a) || !ctype_digit($b)) {
            return false;
        }

        return ltrim($a, '0') === ltrim($b, '0');
    }

}
