<?php

namespace App\Enums;

enum MaritalStatus: string
{

    // phpcs:disable
    case Single = 'single';
    case Married = 'married';
    case Widower = 'widower';
    case Widow = 'widow';
    case Separate = 'separate';
    case Divorced = 'divorced';
    case Engaged = 'engaged';
    // phpcs:enable

    // This method is used to display the enum value in the user interface.
    public function label(): string
    {
        return match ($this) {
            self::Single, self::Married, self::Widow, self::Widower, self::Separate, self::Divorced, self::Engaged => __('app.maritalStatus.' . $this->value),
            default => $this->value,
        };
    }

    /**
     * The choices offered on forms: Single and Married only (QA TC-0005).
     * The other cases stay so a value already stored still reads.
     */
    public static function offered(): array
    {
        return [self::Single, self::Married];
    }

    // This method is return all the values as array.
    public static function toArray(): array
    {
        $maritalStatus = [];

        foreach (MaritalStatus::cases() as $status) {
            $maritalStatus [] = $status->value;
        }

        return $maritalStatus;
    }

}
