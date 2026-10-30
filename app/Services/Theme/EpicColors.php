<?php

namespace App\Services\Theme;

/**
 * The Epic HR look's colours (Settings > Theme Settings > Epic colours): the
 * template's own, or ones chosen there, kept on global settings as JSON.
 * sections/epic-colors writes them out as CSS variables over the stylesheets'.
 */
class EpicColors
{
    // primary: buttons, links, the open tab; accent: second colour of the
    // gradient titles, highlights; dark: top bar and the sign-in band; strip: the
    // icon strip down the left
    const FIELDS = ['primary', 'accent', 'dark', 'strip'];

    const PRESETS = [
        'epic' => [
            'label' => 'Epic (blue and green)',
            'primary' => '#3d78e3', 'accent' => '#67b173', 'dark' => '#212529', 'strip' => '#67b173',
        ],
        'black_gold' => [
            'label' => 'Black and gold',
            'primary' => '#b8892e', 'accent' => '#cd6e4f', 'dark' => '#111111', 'strip' => '#111111',
        ],
        'logo_copper' => [
            'label' => 'Logo copper',
            'primary' => '#cd6e4f', 'accent' => '#6d6e71', 'dark' => '#111111', 'strip' => '#1c1c1c',
        ],
    ];

    /** The colours in use, the template's where none were chosen */
    public static function current(): array
    {
        $saved = json_decode((string) (global_setting()->epic_colors ?? ''), true) ?: [];
        $colors = array_intersect_key(self::PRESETS['epic'], array_flip(self::FIELDS));

        foreach (self::FIELDS as $field) {
            if (self::valid($saved[$field] ?? null)) {
                $colors[$field] = strtolower($saved[$field]);
            }
        }

        return $colors;
    }

    /** Only what was chosen, for saving; null when it is just the template's */
    public static function forSaving(array $input): ?string
    {
        $colors = [];

        foreach (self::FIELDS as $field) {
            if (self::valid($input[$field] ?? null)) {
                $colors[$field] = strtolower($input[$field]);
            }
        }

        $default = array_intersect_key(self::PRESETS['epic'], array_flip(self::FIELDS));

        return ($colors && $colors != $default) ? json_encode($colors) : null;
    }

    /** CSS variables for the chosen colours, or nothing when none were chosen */
    public static function css(): string
    {
        if (!global_setting()?->epic_colors) {
            return '';
        }

        $c = self::current();

        return ':root{--epic-blue:' . $c['primary'] . ';--epic-green:' . $c['accent']
            . ';--epic-dark:' . $c['dark'] . ';--epic-strip-bg:' . $c['strip']
            // the open item in the strip in the primary colour
            . ';--epic-strip-active:' . $c['primary'] . ';--epic-strip-active-text:#fff}';
    }

    public static function valid(?string $color): bool
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }
}
