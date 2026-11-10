<?php

namespace App\Models;

use App\Traits\HasCompany;
use Illuminate\Support\Collection;

/**
 * One round of a hiring plan: the company's default plan (job_position_id
 * null) or a position's own. A test / exam is passed on marks against its
 * cut-off; a face-to-face interview on the interviewers' average score (out
 * of 5) against its pass mark - or, with no pass mark, HR decides.
 */
class RecruitRound extends BaseModel
{
    use HasCompany;

    protected $table = 'recruit_rounds';

    protected $guarded = ['id'];

    protected $casts = [
        'interviewer_ids' => 'array',
        'max_marks' => 'float',
        'cut_off' => 'float',
    ];

    public const TYPES = [
        'interview' => 'Face-to-face interview',
        'test' => 'Test / exam',
    ];

    /** The rounds a position runs: its own, else the company's default. */
    public static function planFor(?int $positionId, ?int $companyId = null): Collection
    {
        $companyId ??= company()?->id;
        $query = fn () => static::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('sort_order')->orderBy('id');

        if ($positionId) {
            $own = $query()->where('job_position_id', $positionId)->get();

            if ($own->isNotEmpty()) {
                return $own;
            }
        }

        return $query()->whereNull('job_position_id')->get();
    }

    /**
     * Save a plan (a position's, or the default) from the form. Rounds are
     * updated in place (their ids are what the candidates' interviews point
     * at), new ones added, the ones left out removed - unless candidates are
     * still in them.
     *
     * @throws \RuntimeException when a round taken away has candidates in it
     */
    public static function savePlan(?int $positionId, array $rows, ?int $companyId = null): void
    {
        $companyId ??= company()?->id;
        $existing = static::withoutGlobalScopes()->where('company_id', $companyId)
            ->when($positionId, fn ($q) => $q->where('job_position_id', $positionId), fn ($q) => $q->whereNull('job_position_id'))
            ->get()->keyBy('id');

        $kept = collect($rows)->pluck('id')->filter(fn ($id) => $id && $existing->has($id))->all();
        $removed = $existing->keys()->diff($kept)->all();
        static::assertNotInUse($removed, $positionId === null ? null : $positionId);

        foreach (array_values($rows) as $i => $row) {
            $id = $row['id'] ?? null;
            unset($row['id']);
            $values = ['sort_order' => $i + 1] + $row;

            if ($id && $existing->has($id)) {
                $existing[$id]->update($values);
            } else {
                static::create(['company_id' => $companyId, 'job_position_id' => $positionId] + $values);
            }
        }

        static::withoutGlobalScopes()->whereIn('id', $removed)->delete();
    }

    /**
     * Rounds candidates are still going through (an application still moving
     * with an interview on one of them).
     *
     * @throws \RuntimeException
     */
    public static function assertNotInUse(array $roundIds, ?int $positionId = null): void
    {
        if (!$roundIds) {
            return;
        }

        $busy = RecruitInterview::withoutGlobalScopes()->whereIn('recruit_round_id', $roundIds)
            ->whereHas('application', fn ($q) => $q->withoutGlobalScopes()->whereIn('stage', ['shortlisted', 'interview', 'on_hold'])
                ->when($positionId, fn ($p) => $p->where('job_position_id', $positionId)))
            ->with('planRound')->get();

        if ($busy->isNotEmpty()) {
            $names = $busy->map(fn ($i) => $i->planRound?->name ?? $i->round)->unique()->implode(', ');
            $count = $busy->pluck('job_application_id')->unique()->count();

            throw new \RuntimeException($count . ' candidate(s) are still in ' . $names . '. Keep the round(s) until they are done '
                . '(you can rename them, change the panel or the cut-off, and add rounds).');
        }
    }

    public function isTest(): bool
    {
        return $this->type === 'test';
    }

    /** @return string[] */
    public function questionList(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) $this->questions)))));
    }

    public function passText(): string
    {
        if ($this->isTest()) {
            return $this->cut_off !== null
                ? 'Cut-off ' . self::num($this->cut_off) . ($this->max_marks ? ' / ' . self::num($this->max_marks) : '')
                : 'No cut-off (HR decides)';
        }

        return $this->cut_off !== null ? 'Pass mark ' . self::num($this->cut_off) . ' / 5' : 'HR decides';
    }

    public static function num($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

}
