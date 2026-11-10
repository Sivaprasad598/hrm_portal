<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses emojis (the same ranges the menu and app-launcher forms refuse).
 */
class NoEmoji implements ValidationRule
{
    public const PATTERN = '/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u';

    /** Letters, spaces and . ' ( ) - / only: names, relations, labels. */
    public const TEXT_ONLY = '/^[\pL\pM\s.\'()\/-]+$/u';

    /** An Indian mobile: 10 digits starting 6-9, optionally after +91 or 0. */
    public const INDIAN_MOBILE = '/^(\+91[\s-]?|0)?[6-9]\d{9}$/';

    /**
     * Add "no emojis" to these fields of a rules array, keeping their other
     * rules (string "a|b" or array form). A field not in the array gets just
     * this check, which passes when the field is empty or absent.
     */
    public static function addTo(array $rules, array $fields): array
    {
        foreach ($fields as $field) {
            $existing = $rules[$field] ?? [];
            $existing = is_array($existing) ? $existing : array_filter(explode('|', (string) $existing), 'strlen');
            $existing[] = new self();
            $rules[$field] = array_values($existing);
        }

        return $rules;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match(self::PATTERN, $value)) {
            $fail('Emojis are not allowed in :attribute.');
        }
    }
}
