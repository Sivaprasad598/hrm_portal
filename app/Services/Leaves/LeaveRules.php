<?php

namespace App\Services\Leaves;

use App\Models\Leave;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The rules set on a leave type (Leave Settings -> Leave type -> Rules):
 * a supporting document, how far back and how far ahead it may be applied,
 * and the most days in one application.
 *
 * Checked when somebody applies for their own leave, and - for the document -
 * again when the leave is approved: a document that may follow later has to
 * be there before approval.
 */
class LeaveRules
{

    /** Save the Rules tab onto a leave type. */
    public static function fill(LeaveType $type, $request): void
    {
        $int = fn ($v) => ($v === null || $v === '') ? null : max(0, (int) $v);
        $dec = fn ($v) => ($v === null || $v === '') ? null : max(0, round((float) $v, 1));

        $type->doc_required = in_array($request->doc_required, ['always', 'over_days'], true) ? $request->doc_required : 'none';
        $type->doc_min_days = $type->doc_required === 'over_days' ? ($dec($request->doc_min_days) ?? 0) : null;
        $type->doc_label = $type->doc_required !== 'none' ? (mb_substr(trim((string) $request->doc_label), 0, 100) ?: null) : null;
        $type->doc_later_days = $type->doc_required !== 'none' ? $int($request->doc_later_days) : null;
        $type->backdate_days = $int($request->backdate_days);
        $type->notice_days = $int($request->notice_days) ?: null;
        $type->max_days = $dec($request->max_days) ?: null;
    }

    public static function docName(LeaveType $type): string
    {
        return $type->doc_label ?: 'supporting document';
    }

    /** Whether a leave of this many days needs the document. */
    public static function needsDocument(LeaveType $type, float $days): bool
    {
        return match ($type->doc_required ?? 'none') {
            'always' => true,
            'over_days' => $days > (float) $type->doc_min_days,
            default => false,
        };
    }

    /**
     * Why an application breaks the type's rules, or null.
     *
     * @param  array<int, Carbon>  $dates  the leave days (holidays already left out)
     * @param  bool  $hasFile  a file is attached to the application
     */
    public static function refusal(LeaveType $type, array $dates, float $days, bool $hasFile, Carbon $today): ?string
    {
        $name = $type->type_name;
        $first = collect($dates)->min()?->copy()->startOfDay();

        if (!$first) {
            return null;
        }

        if ($type->max_days !== null && $days > (float) $type->max_days) {
            return sprintf('%s can be applied for at most %s day(s) at a time; this is %s.', $name, self::n($type->max_days), self::n($days));
        }

        if ($first->lt($today)) {
            if ($type->backdate_days !== null) {
                // Whole calendar days, whatever the timezones of the two dates.
                $late = (int) round(Carbon::parse($first->toDateString())->diffInDays(Carbon::parse($today->toDateString())));

                if ((int) $type->backdate_days === 0) {
                    return sprintf('%s cannot be applied for a past date.', $name);
                }

                if ($late > (int) $type->backdate_days) {
                    return sprintf('%s can be applied up to %d day(s) afterwards; %s was %d days ago.', $name, $type->backdate_days, $first->format('d M'), $late);
                }
            }
        } elseif ($type->notice_days && $first->lt($today->copy()->addDays((int) $type->notice_days))) {
            return sprintf('%s has to be applied at least %d day(s) before - from %s onwards.', $name, $type->notice_days,
                $today->copy()->addDays((int) $type->notice_days)->format('d M Y'));
        }

        if (self::needsDocument($type, $days) && !$hasFile && $type->doc_later_days === null) {
            return sprintf('Attach the %s: %s %s needs it.', self::docName($type), $name,
                $type->doc_required === 'always' ? 'always' : 'longer than ' . self::n($type->doc_min_days) . ' day(s)');
        }

        return null;
    }

    /**
     * Why this leave cannot be approved yet - its document has not come - or null.
     */
    public static function documentAwaited(Leave $leave): ?string
    {
        $type = $leave->type ?? LeaveType::withTrashed()->find($leave->leave_type_id);

        if (!$type || ($type->doc_required ?? 'none') === 'none') {
            return null;
        }

        $group = $leave->unique_id
            ? Leave::where('unique_id', $leave->unique_id)->whereIn('status', ['pending', 'approved'])->get(['id', 'duration'])
            : collect([$leave]);
        $days = $group->sum(fn ($l) => $l->duration === 'half day' ? 0.5 : 1.0);

        if (!self::needsDocument($type, (float) $days)) {
            return null;
        }

        if (DB::table('leave_files')->whereIn('leave_id', $group->pluck('id'))->exists()) {
            return null;
        }

        $user = DB::table('users')->where('id', $leave->user_id)->value('name');

        return sprintf('%s\'s %s is waiting for the %s - it can be approved once it is uploaded.', $user, $type->type_name, self::docName($type));
    }

    /** A plain summary of the rules, for the leave type list and the apply form. */
    public static function describe(LeaveType $type): array
    {
        $out = [];

        if (($type->doc_required ?? 'none') !== 'none') {
            $out[] = ucfirst(self::docName($type)) . ($type->doc_required === 'always' ? ' always' : ' when longer than ' . self::n($type->doc_min_days) . ' day(s)')
                . ($type->doc_later_days !== null ? ' (can follow within ' . $type->doc_later_days . ' day(s))' : '');
        }

        if ($type->backdate_days !== null) {
            $out[] = (int) $type->backdate_days === 0 ? 'Not for past dates' : 'Can be applied up to ' . $type->backdate_days . ' day(s) afterwards';
        }

        if ($type->notice_days) {
            $out[] = 'Apply ' . $type->notice_days . ' day(s) ahead';
        }

        if ($type->max_days !== null) {
            $out[] = 'At most ' . self::n($type->max_days) . ' day(s) at a time';
        }

        return $out;
    }

    protected static function n($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    }

}
