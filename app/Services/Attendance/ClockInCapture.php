<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The photograph and the place that go with a clock-in.
 *
 * The browser hands over a picture as a data URL and a pair of coordinates.
 * Neither can be trusted on arrival: the picture has to be a real image of a
 * sane size, and the coordinates have to be coordinates. Anything that fails
 * is dropped rather than stored, because a corrupt file in an attendance
 * record is worse than no file.
 */
class ClockInCapture
{

    /** Where the files live under the public disk. */
    public const FOLDER = 'attendance-photos';

    /** Anything larger than this is refused rather than stored. */
    public const MAX_BYTES = 2097152;

    /**
     * What the settings ask for, as the screens and the server both read it.
     *
     * @return array{photo: string, location: string}
     */
    public function requirements(): array
    {
        $settings = DB::table('attendance_settings')->first(['clock_in_photo', 'clock_in_location']);

        return [
            'photo' => $settings->clock_in_photo ?? 'off',
            'location' => $settings->clock_in_location ?? 'off',
        ];
    }

    /**
     * Store a captured picture, and say where it went.
     *
     * @param  ?string $dataUrl  "data:image/jpeg;base64,..." as the camera gives it
     * @return array{path: ?string, problem: ?string}
     */
    public function storePhoto(?string $dataUrl, int $userId): array
    {
        if ($dataUrl === null || trim($dataUrl) === '') {
            return ['path' => null, 'problem' => null];
        }

        if (!preg_match('#^data:image/(jpe?g|png|webp);base64,#i', $dataUrl, $match)) {
            return ['path' => null, 'problem' => 'That was not a picture the camera produced.'];
        }

        $extension = strtolower($match[1]) === 'png' ? 'png' : (strtolower($match[1]) === 'webp' ? 'webp' : 'jpg');
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || $binary === '') {
            return ['path' => null, 'problem' => 'The picture could not be read.'];
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return ['path' => null, 'problem' => 'The picture is too large.'];
        }

        // It claims to be an image; check that it actually is one.
        $size = @getimagesizefromstring($binary);

        if ($size === false || ($size[0] ?? 0) < 20 || ($size[1] ?? 0) < 20) {
            return ['path' => null, 'problem' => 'That file is not a usable image.'];
        }

        $path = self::FOLDER . '/' . $userId . '/' . now()->format('Y-m') . '/'
            . now()->format('Ymd-His') . '-' . Str::random(6) . '.' . $extension;

        Storage::disk('public')->put($path, $binary);

        return ['path' => $path, 'problem' => null];
    }

    /**
     * Coordinates, if they are coordinates.
     *
     * @return array{latitude: ?float, longitude: ?float}
     */
    public function coordinates($latitude, $longitude): array
    {
        $latitude = is_numeric($latitude) ? (float) $latitude : null;
        $longitude = is_numeric($longitude) ? (float) $longitude : null;

        if ($latitude === null || $longitude === null
            || abs($latitude) > 90 || abs($longitude) > 180
            // Exactly nought is the sea off Africa, and in practice means
            // the browser gave nothing.
            || (abs($latitude) < 0.000001 && abs($longitude) < 0.000001)) {
            return ['latitude' => null, 'longitude' => null];
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    /**
     * Whether a punch may go ahead, given what the settings demand.
     *
     * One pair of settings governs both ends of the day, so $action only
     * changes the wording of the refusal, never the rule.
     *
     * @param  string $action  'clock in' or 'clock out'
     * @return ?string  the reason it may not, or null
     */
    public function missing(array $requirements, ?string $photoPath, ?float $latitude, string $action = 'clock in'): ?string
    {
        if ($requirements['photo'] === 'mandatory' && $photoPath === null) {
            return 'A photograph is required to ' . $action . '. Allow the camera and try again.';
        }

        if ($requirements['location'] === 'mandatory' && $latitude === null) {
            return 'Your location is required to ' . $action . '. Allow location access and try again.';
        }

        return null;
    }

    /**
     * Where the person said they were, in words, as a snapshot.
     *
     * The row already stores a company-address id, but that is a foreign key:
     * rename or delete the address later and every historical punch starts
     * reporting the new name, or nothing at all. This does not move.
     *
     * @param  ?int    $locationId    the chosen company address, if any
     * @param  ?string $workFromType  office / home / other
     * @param  ?string $workingFrom   what they typed when they chose "other"
     */
    public function place($locationId, ?string $workFromType, ?string $workingFrom = null): ?string
    {
        $parts = [];

        if (is_numeric($locationId)) {
            // `location` is the short label the clock-in dropdown shows;
            // `address` is the full postal one, used only if the short one is
            // blank.
            $row = DB::table('company_addresses')->where('id', (int) $locationId)
                ->first(['location', 'address']);

            $label = trim((string) ($row->location ?? '')) !== ''
                ? $row->location
                : ($row->address ?? '');

            if (trim((string) $label) !== '') {
                // One line: a stored address may carry its own line breaks.
                $parts[] = trim(preg_replace('/\s+/', ' ', $label));
            }
        }

        if ($workFromType === 'other' && trim((string) $workingFrom) !== '') {
            $parts[] = trim($workingFrom);
        } elseif ($workFromType !== null && $workFromType !== '') {
            $parts[] = $workFromType;
        }

        $place = implode(' - ', array_filter($parts));

        return $place === '' ? null : mb_substr($place, 0, 255);
    }

    /**
     * A web address for a stored picture, or nothing.
     */
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * How a map would show a pair of coordinates.
     */
    public static function mapUrl($latitude, $longitude): ?string
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude;
    }

}
