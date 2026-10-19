<?php

namespace App\Observers;

use App\Services\Attendance\DailyAttendanceReview;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Daily Review in step with the facts: when a leave is approved,
 * changed or withdrawn, or an attendance row is added, corrected or removed,
 * the affected employee-day is judged again straight away. An "absent" day
 * whose leave is approved afterwards stops being an exception by itself.
 *
 * Registered alongside the existing Leave and Attendance observers; it never
 * stops the save it follows.
 */
class AttendanceReviewRefresher
{

    public function saved(Model $model): void
    {
        $this->refresh($model);
    }

    public function deleted(Model $model): void
    {
        $this->refresh($model);
    }

    protected function refresh(Model $model): void
    {
        try {
            $date = $model->leave_date ?? $model->clock_in_time ?? null;

            if (!$date || !$model->user_id) {
                return;
            }

            $tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
            $day = Carbon::parse($date, $model->clock_in_time ? 'UTC' : $tz)->setTimezone($tz)->startOfDay();

            // Only days already behind us can have been reviewed.
            if ($day->gte(Carbon::now($tz)->startOfDay())) {
                return;
            }

            (new DailyAttendanceReview())->refresh((int) $model->user_id, $day);
        } catch (\Throwable $e) {
            Log::warning('Daily Review refresh failed', ['model' => $model::class, 'id' => $model->getKey(), 'error' => $e->getMessage()]);
        }
    }

}
