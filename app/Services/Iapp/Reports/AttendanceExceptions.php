<?php

namespace App\Services\Iapp\Reports;

use App\Models\IappAttendanceRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance days that need a human to look at them.
 *
 * These are the records that would otherwise distort hours and payroll
 * silently: a day with no exit punch reports zero or partial hours, and a
 * single punch cannot be paired at all. Surfacing them lets HR regularise the
 * day before it feeds anything downstream.
 *
 * Read-only: nothing here changes a record.
 */
class AttendanceExceptions
{

    public const TYPE_ALL = 'all';
    public const TYPE_SINGLE_PUNCH = 'single_punch';
    public const TYPE_NO_EXIT = 'no_exit';
    public const TYPE_LONG_DAY = 'long_day';
    public const TYPE_UNLINKED = 'unlinked';

    /**
     * A worked span beyond this is almost always an unpaired punch rather than
     * a real shift — someone who swiped in and never swiped out until the
     * next morning.
     */
    public const LONG_DAY_MINUTES = 840;

    public const LABELS = [
        self::TYPE_SINGLE_PUNCH => 'Single punch',
        self::TYPE_NO_EXIT => 'No exit punch',
        self::TYPE_LONG_DAY => 'Unusually long day',
        self::TYPE_UNLINKED => 'Not linked to ERP',
    ];

    public function __construct(
        protected Carbon $from,
        protected Carbon $to
    ) {
    }

    protected function base(): Builder
    {
        return IappAttendanceRecord::query()
            ->whereBetween('attendance_date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    /**
     * Records for one exception type, or every attendance exception.
     *
     * 'all' deliberately excludes TYPE_UNLINKED: until mapping is done nearly
     * every row is unlinked, and it would bury the genuine punch problems.
     */
    public function query(string $type = self::TYPE_ALL, ?string $controllerCode = null): Builder
    {
        $query = $this->base();

        match ($type) {
            self::TYPE_SINGLE_PUNCH => $query->where('punch_count', 1),
            // Excludes single-punch days so the two lists never double-count.
            self::TYPE_NO_EXIT => $query->whereNull('last_out')->where('punch_count', '>', 1),
            self::TYPE_LONG_DAY => $query->where('work_minutes', '>', self::LONG_DAY_MINUTES),
            self::TYPE_UNLINKED => $query->whereNull('user_id'),
            default => $query->where(function ($q) {
                $q->where('punch_count', 1)
                    ->orWhereNull('last_out')
                    ->orWhere('work_minutes', '>', self::LONG_DAY_MINUTES);
            }),
        };

        if ($controllerCode) {
            $query->where(function ($q) use ($controllerCode) {
                $q->where('controller_code', $controllerCode)
                    ->orWhereJsonContains('controller_codes', $controllerCode);
            });
        }

        return $query->orderByDesc('attendance_date')->orderBy('emp_id');
    }

    /**
     * Counts for every type in one grouped query rather than one per tile.
     */
    public function summary(): array
    {
        $row = $this->base()
            ->selectRaw('
                COUNT(*) AS total_days,
                SUM(CASE WHEN punch_count = 1 THEN 1 ELSE 0 END) AS single_punch,
                SUM(CASE WHEN last_out IS NULL AND punch_count > 1 THEN 1 ELSE 0 END) AS no_exit,
                SUM(CASE WHEN work_minutes > ? THEN 1 ELSE 0 END) AS long_day,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS unlinked
            ', [self::LONG_DAY_MINUTES])
            ->toBase()
            ->first();

        $single = (int) ($row->single_punch ?? 0);
        $noExit = (int) ($row->no_exit ?? 0);
        $longDay = (int) ($row->long_day ?? 0);

        return [
            'total_days' => (int) ($row->total_days ?? 0),
            self::TYPE_SINGLE_PUNCH => $single,
            self::TYPE_NO_EXIT => $noExit,
            self::TYPE_LONG_DAY => $longDay,
            self::TYPE_UNLINKED => (int) ($row->unlinked ?? 0),
            // A long day can also lack an exit punch; the headline counts the
            // distinct days affected, not the sum of the tiles.
            self::TYPE_ALL => $this->query()->count(),
        ];
    }

    /**
     * Which exceptions a single record carries.
     *
     * @return array<int, string>
     */
    public static function classify(IappAttendanceRecord $record): array
    {
        $types = [];

        if ((int) $record->punch_count === 1) {
            $types[] = self::TYPE_SINGLE_PUNCH;
        } elseif ($record->last_out === null) {
            $types[] = self::TYPE_NO_EXIT;
        }

        if ((int) $record->work_minutes > self::LONG_DAY_MINUTES) {
            $types[] = self::TYPE_LONG_DAY;
        }

        if ($record->user_id === null) {
            $types[] = self::TYPE_UNLINKED;
        }

        return $types;
    }

    /**
     * Punches the device could not attribute to anyone.
     *
     * Failed reads, unenrolled cards or someone following another person
     * through a door. Grouped by day and device so a faulty reader stands out.
     */
    public function unidentifiedPunches(?string $controllerCode = null): Collection
    {
        return DB::table('iapp_device_transactions')
            ->selectRaw('DATE(punch_time) AS day, controller_code, COUNT(*) AS punches,
                         MIN(punch_time) AS first_seen, MAX(punch_time) AS last_seen')
            ->where(fn ($q) => $q->whereNull('emp_code')->orWhere('emp_code', ''))
            ->whereBetween('punch_time', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->when($controllerCode, fn ($q) => $q->where('controller_code', $controllerCode))
            ->groupBy('day', 'controller_code')
            ->orderByDesc('day')
            ->orderByDesc('punches')
            ->get();
    }

}
