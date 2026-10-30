<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The employee's calendar as a list: holidays that apply to them, their own
 * leave (approved and pending, one entry per application) and the events they
 * are invited to - the HR side of the website's My Calendar.
 *
 * Attendance day by day is GET /attendance and /attendance/calendar.
 */
class CalendarController extends MobileController
{
    const MAX_RANGE_DAYS = 366;

    const TYPES = ['holiday', 'leave', 'event'];

    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'types' => 'nullable|string',
        ]);

        $tz = $this->tz();

        if ($request->from || $request->to) {
            $from = Carbon::parse($request->from ?: $request->to, $tz)->startOfDay();
            $to = Carbon::parse($request->to ?: $from->copy()->endOfMonth()->toDateString(), $tz)->startOfDay();
        } else {
            $from = Carbon::create((int) ($request->year ?: now($tz)->year), (int) ($request->month ?: now($tz)->month), 1, 0, 0, 0, $tz);
            $to = $from->copy()->endOfMonth()->startOfDay();
        }

        if ($from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
            return $this->fail('Choose a period of at most ' . self::MAX_RANGE_DAYS . ' days.', 422);
        }

        $types = $request->types ? array_values(array_intersect(self::TYPES, explode(',', $request->types))) : self::TYPES;
        $items = collect();

        if (in_array('holiday', $types, true)) {
            $items = $items->concat($this->holidays($from, $to));
        }

        if (in_array('leave', $types, true)) {
            $items = $items->concat($this->leaves($from, $to));
        }

        if (in_array('event', $types, true)) {
            $items = $items->concat($this->events($from, $to));
        }

        return $this->ok([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'items' => $items->sortBy(fn ($i) => $i['date'] . ' ' . ($i['start_time'] ?? '00:00') . ' ' . $i['type'])->values(),
        ]);
    }

    private function holidays(Carbon $from, Carbon $to)
    {
        return Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->applicableTo($this->me()->employeeDetail)
            ->orderBy('date')->get(['id', 'date', 'occassion'])
            ->map(fn ($h) => [
                'type' => 'holiday',
                'id' => $h->id,
                'title' => $h->occassion,
                'date' => Carbon::parse($h->date)->toDateString(),
                'end_date' => Carbon::parse($h->date)->toDateString(),
                'start_time' => null,
                'end_time' => null,
                'status' => null,
                'color' => null,
                'details' => null,
            ]);
    }

    /** One entry per application, spanning its first to last day within the range. */
    private function leaves(Carbon $from, Carbon $to)
    {
        return DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
            ->where('l.user_id', $this->me()->id)
            ->whereIn('l.status', ['approved', 'pending'])
            ->whereBetween('l.leave_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('l.leave_date')
            ->get(['l.id', 'l.unique_id', 'l.leave_date', 'l.duration', 'l.half_day_type', 'l.status', 'l.reason', 't.type_name', 't.color'])
            // Days of one application, split only where the status differs.
            ->groupBy(fn ($l) => ($l->unique_id ?: 'id-' . $l->id) . '|' . $l->status)
            ->map(function ($days) {
                $first = $days->first();
                $half = $first->duration === 'half day' ? ' (' . str_replace('_', ' ', (string) $first->half_day_type) . ')' : '';

                return [
                    'type' => 'leave',
                    'id' => $first->id,
                    'title' => ($first->type_name ?: 'Leave') . $half,
                    'date' => substr((string) $first->leave_date, 0, 10),
                    'end_date' => substr((string) $days->last()->leave_date, 0, 10),
                    'start_time' => null,
                    'end_time' => null,
                    'status' => $first->status,
                    'color' => $first->color,
                    'details' => ['days' => (float) $days->sum(fn ($d) => $d->duration === 'half day' ? 0.5 : 1), 'reason' => $first->reason],
                ];
            })->values();
    }

    private function events(Carbon $from, Carbon $to)
    {
        $tz = $this->tz();
        $utcFrom = $from->copy()->startOfDay()->timezone(config('app.timezone'));
        $utcTo = $to->copy()->endOfDay()->timezone(config('app.timezone'));

        return DB::table('events as e')->join('event_attendees as a', 'a.event_id', '=', 'e.id')
            ->where('a.user_id', $this->me()->id)
            ->where('e.start_date_time', '<=', $utcTo)->where('e.end_date_time', '>=', $utcFrom)
            ->orderBy('e.start_date_time')->distinct()
            ->get(['e.id', 'e.event_name', 'e.label_color', 'e.where', 'e.description', 'e.start_date_time', 'e.end_date_time', 'e.status', 'e.event_link'])
            ->map(function ($e) use ($tz) {
                $start = Carbon::parse($e->start_date_time)->timezone($tz);
                $end = Carbon::parse($e->end_date_time)->timezone($tz);

                return [
                    'type' => 'event',
                    'id' => $e->id,
                    'title' => $e->event_name,
                    'date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'start_time' => $start->format('H:i'),
                    'end_time' => $end->format('H:i'),
                    'status' => $e->status,
                    'color' => $e->label_color,
                    'details' => ['where' => $e->where, 'link' => $e->event_link, 'description' => $e->description ? trim(strip_tags($e->description)) : null],
                ];
            });
    }
}
