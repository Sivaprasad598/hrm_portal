<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;

/**
 * Where an attendance row came from, for the screens that show it:
 *
 *   excel      HR's Attendance Import (clock_in_type "import")
 *   biometric  written by the biometric device sync (it links every row it creates)
 *   mobile     the employee's own clock-in on the mobile app
 *   self       the employee's own clock-in on the website
 *   manual     marked by HR / a manager (Add Attendance, an edit, a correction)
 *
 * Read from what the row already records, so it holds for rows written before this existed.
 */
class AttendanceSource
{

    /** key => [label, icon, colour, letter] */
    public const SOURCES = [
        'excel' => ['Excel upload', 'fa-file-excel', '#1d7044', 'E'],
        'biometric' => ['Biometric', 'fa-fingerprint', '#7a5af8', 'B'],
        'manual' => ['Marked manually', 'fa-user-edit', '#dc6803', 'M'],
        'self' => ['Self clock-in (web)', 'fa-user-clock', '#0b8ec9', 'S'],
        'mobile' => ['Mobile app', 'fa-mobile-alt', '#0b8ec9', 'A'],
    ];

    /**
     * The source of each row.
     *
     * @param  iterable<object> $rows  with id, user_id, clock_in_type, added_by
     * @return array<int, string>  attendance id => source key
     */
    public static function forRows(iterable $rows): array
    {
        $rows = collect($rows);
        $ids = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $device = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('iapp_attendance_links')->whereIn('attendance_id', $chunk)->where('link_type', 'created')->pluck('attendance_id') as $id) {
                $device[(int) $id] = true;
            }
        }

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->id] = self::decide($row, isset($device[(int) $row->id]));
        }

        return $out;
    }

    /** One row's source (one query). */
    public static function of(object $row): string
    {
        return self::forRows([$row])[(int) $row->id] ?? 'manual';
    }

    protected static function decide(object $row, bool $fromDevice): string
    {
        return match (true) {
            $row->clock_in_type === 'import' => 'excel',
            $fromDevice => 'biometric',
            $row->clock_in_type === 'mobile_app' => 'mobile',
            $row->added_by !== null && (int) $row->added_by === (int) $row->user_id => 'self',
            default => 'manual',
        };
    }

    public static function label(string $key): string
    {
        return self::SOURCES[$key][0] ?? ucfirst($key);
    }

    /** A small badge for a source. */
    public static function badge(string $key, string $extraClass = ''): string
    {
        [$label, $icon, $colour] = self::SOURCES[$key] ?? [ucfirst($key), 'fa-circle', '#667085'];

        return '<span class="badge ' . e($extraClass) . '" style="background:' . $colour . '1a;color:' . $colour . ';border:1px solid ' . $colour . '55;font-weight:600">'
            . '<i class="fa ' . $icon . ' mr-1"></i>' . e($label) . '</span>';
    }

}
