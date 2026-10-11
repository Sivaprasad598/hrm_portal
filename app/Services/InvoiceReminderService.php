<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class InvoiceReminderService
{

    public static function parseFromRequest($request): array
    {
        $beforeRules = [];
        $rawRules = $request->reminder_before_rules ?? $request->input('reminder_before_rules', []);

        if (is_array($rawRules)) {
            foreach ($rawRules as $rule) {
                $value = (int) Arr::get($rule, 'value', 0);
                $unit = Arr::get($rule, 'unit', 'days') === 'hours' ? 'hours' : 'days';

                if ($value <= 0) {
                    continue;
                }

                $beforeRules[] = [
                    'enabled' => filter_var(Arr::get($rule, 'enabled', false), FILTER_VALIDATE_BOOLEAN)
                        || Arr::get($rule, 'enabled') === 'on'
                        || Arr::get($rule, 'enabled') === '1'
                        || Arr::get($rule, 'enabled') === 1,
                    'value' => $value,
                    'unit' => $unit,
                ];
            }
        }

        $limitType = $request->reminder_limit_type ?? 'until_paid';
        $allowedLimits = ['until_paid', 'times', 'days', 'custom_date'];

        if (!in_array($limitType, $allowedLimits, true)) {
            $limitType = 'until_paid';
        }

        $limitValue = null;
        $limitDate = null;

        if ($limitType === 'times' || $limitType === 'days') {
            $limitValue = max(1, (int) ($request->reminder_limit_value ?? 1));
        }

        if ($limitType === 'custom_date' && !empty($request->reminder_limit_date)) {
            try {
                $limitDate = function_exists('companyToYmd')
                    ? companyToYmd($request->reminder_limit_date)
                    : $request->reminder_limit_date;
            } catch (\Throwable $e) {
                $limitDate = $request->reminder_limit_date;
            }
        }

        return [
            'before_enabled' => $request->has('reminder_before_enabled') || filter_var($request->reminder_before_enabled ?? false, FILTER_VALIDATE_BOOLEAN),
            'before_rules' => $beforeRules,
            'after_enabled' => $request->has('reminder_after_enabled') || filter_var($request->reminder_after_enabled ?? false, FILTER_VALIDATE_BOOLEAN),
            'after_frequency' => max(1, (int) ($request->reminder_after_frequency ?? 3)),
            'after_frequency_unit' => ($request->reminder_after_frequency_unit ?? 'hours') === 'days' ? 'days' : 'hours',
            'after_start' => max(0, (int) ($request->reminder_after_start ?? 1)),
            'after_start_unit' => ($request->reminder_after_start_unit ?? 'hours') === 'days' ? 'days' : 'hours',
            'limit_type' => $limitType,
            'limit_value' => $limitValue,
            'limit_date' => $limitDate,
            'include_pdf' => false,
            'include_payment_link' => true,
        ];
    }

    public static function applyToInvoiceSetting(InvoiceSetting $setting, array $data): void
    {
        $setting->reminder_before_enabled = $data['before_enabled'];
        $setting->reminder_before_rules = $data['before_rules'];
        $setting->reminder_after_enabled = $data['after_enabled'];
        $setting->reminder_after_frequency = $data['after_frequency'];
        $setting->reminder_after_frequency_unit = $data['after_frequency_unit'];
        $setting->reminder_after_start = $data['after_start'];
        $setting->reminder_after_start_unit = $data['after_start_unit'];
        $setting->reminder_limit_type = $data['limit_type'];
        $setting->reminder_limit_value = $data['limit_value'];
        $setting->reminder_limit_date = $data['limit_date'];
        $setting->reminder_include_pdf = $data['include_pdf'];
        $setting->reminder_include_payment_link = $data['include_payment_link'];

        // Keep legacy columns in sync for older code paths
        $firstBefore = collect($data['before_rules'])->firstWhere('enabled', true);
        $setting->send_reminder = $firstBefore ? (int) $firstBefore['value'] : 0;
        $setting->reminder = $data['after_enabled'] ? 'every' : null;
        $setting->send_reminder_after = $data['after_enabled'] ? (int) $data['after_frequency'] : 0;
    }

    public static function resolveForInvoice(Invoice $invoice, ?InvoiceSetting $setting = null): array
    {
        $setting = $setting ?: InvoiceSetting::where('company_id', $invoice->company_id)->first();

        if ($invoice->reminder_use_custom && is_array($invoice->reminder_settings) && !empty($invoice->reminder_settings)) {
            return array_merge(self::emptySettings(), $invoice->reminder_settings);
        }

        if ($setting) {
            return $setting->reminderSettingsArray();
        }

        return self::emptySettings();
    }

    public static function emptySettings(): array
    {
        return [
            'before_enabled' => false,
            'before_rules' => [],
            'after_enabled' => false,
            'after_frequency' => 3,
            'after_frequency_unit' => 'hours',
            'after_start' => 1,
            'after_start_unit' => 'hours',
            'limit_type' => 'until_paid',
            'limit_value' => null,
            'limit_date' => null,
            'include_pdf' => false,
            'include_payment_link' => true,
        ];
    }

    public static function ruleKey(array $rule): string
    {
        return (int) $rule['value'] . '_' . ($rule['unit'] ?? 'days');
    }

    public static function addDuration(Carbon $date, int $value, string $unit): Carbon
    {
        return $unit === 'hours' ? $date->copy()->addHours($value) : $date->copy()->addDays($value);
    }

    public static function subDuration(Carbon $date, int $value, string $unit): Carbon
    {
        return $unit === 'hours' ? $date->copy()->subHours($value) : $date->copy()->subDays($value);
    }

    public static function isWithinCurrentHour(Carbon $target, Carbon $now): bool
    {
        return $target->copy()->startOfHour()->equalTo($now->copy()->startOfHour());
    }

    public static function withinScheduleLimit(Invoice $invoice, array $settings, Carbon $now): bool
    {
        $type = $settings['limit_type'] ?? 'until_paid';

        if ($type === 'times') {
            $max = (int) ($settings['limit_value'] ?? 0);

            return $max <= 0 || (int) $invoice->reminder_sent_count < $max;
        }

        if ($type === 'days') {
            $days = (int) ($settings['limit_value'] ?? 0);

            if ($days <= 0 || !$invoice->due_date) {
                return true;
            }

            return $now->lte($invoice->due_date->copy()->addDays($days)->endOfDay());
        }

        if ($type === 'custom_date' && !empty($settings['limit_date'])) {
            return $now->lte(Carbon::parse($settings['limit_date'], $now->timezone)->endOfDay());
        }

        return true;
    }

    public static function dueBeforeRules(Invoice $invoice, array $settings, Carbon $now): array
    {
        if (empty($settings['before_enabled']) || empty($settings['before_rules']) || !$invoice->due_date) {
            return [];
        }

        $alreadySent = is_array($invoice->reminder_before_sent) ? $invoice->reminder_before_sent : [];
        $due = Carbon::createFromFormat('Y-m-d', $invoice->due_date->format('Y-m-d'), $now->timezone)->endOfDay();
        $matches = [];

        foreach ($settings['before_rules'] as $rule) {
            if (empty($rule['enabled']) || (int) ($rule['value'] ?? 0) <= 0) {
                continue;
            }

            $key = self::ruleKey($rule);

            if (in_array($key, $alreadySent, true)) {
                continue;
            }

            $unit = ($rule['unit'] ?? 'days') === 'hours' ? 'hours' : 'days';
            $value = (int) $rule['value'];

            if ($unit === 'days') {
                $targetDay = $due->copy()->startOfDay()->subDays($value)->toDateString();

                if ($now->toDateString() === $targetDay) {
                    $matches[] = $rule;
                }
            } else {
                $target = $due->copy()->subHours($value);

                if (self::isWithinCurrentHour($target, $now)) {
                    $matches[] = $rule;
                }
            }
        }

        return $matches;
    }

    public static function shouldSendAfterReminder(Invoice $invoice, array $settings, Carbon $now): bool
    {
        if (empty($settings['after_enabled']) || !$invoice->due_date) {
            return false;
        }

        $due = Carbon::createFromFormat('Y-m-d', $invoice->due_date->format('Y-m-d'), $now->timezone)->endOfDay();
        $startAt = self::addDuration(
            $due,
            (int) ($settings['after_start'] ?? 0),
            $settings['after_start_unit'] ?? 'hours'
        );

        if ($now->lt($startAt)) {
            return false;
        }

        if (!self::withinScheduleLimit($invoice, $settings, $now)) {
            return false;
        }

        if (!$invoice->reminder_last_sent_at) {
            return true;
        }

        $lastSent = $invoice->reminder_last_sent_at->copy()->timezone($now->timezone);
        $nextDue = self::addDuration(
            $lastSent,
            (int) ($settings['after_frequency'] ?? 1),
            $settings['after_frequency_unit'] ?? 'hours'
        );

        return $now->gte($nextDue);
    }

    public static function markBeforeSent(Invoice $invoice, array $rule, $notifyUser = null): void
    {
        $sent = is_array($invoice->reminder_before_sent) ? $invoice->reminder_before_sent : [];
        $key = self::ruleKey($rule);

        if (!in_array($key, $sent, true)) {
            $sent[] = $key;
        }

        $invoice->reminder_before_sent = $sent;
        $invoice->reminder_sent_count = (int) $invoice->reminder_sent_count + 1;
        $invoice->reminder_last_sent_at = now();
        $invoice->saveQuietly();

        self::logHistory($invoice, \App\Models\InvoiceReminderHistory::TYPE_BEFORE, $notifyUser, [
            'value' => (int) ($rule['value'] ?? 0),
            'unit' => $rule['unit'] ?? 'days',
        ]);
    }

    public static function markAfterSent(Invoice $invoice, $notifyUser = null): void
    {
        $invoice->reminder_last_sent_at = now();
        $invoice->reminder_sent_count = (int) $invoice->reminder_sent_count + 1;
        $invoice->saveQuietly();

        self::logHistory($invoice, \App\Models\InvoiceReminderHistory::TYPE_AFTER, $notifyUser);
    }

    public static function markManualSent(Invoice $invoice, $notifyUser = null, $sentBy = null): void
    {
        $invoice->reminder_last_sent_at = now();
        $invoice->reminder_sent_count = (int) $invoice->reminder_sent_count + 1;
        $invoice->saveQuietly();

        self::logHistory($invoice, \App\Models\InvoiceReminderHistory::TYPE_MANUAL, $notifyUser, [], $sentBy);
    }

    public static function logHistory(Invoice $invoice, string $type, $notifyUser = null, array $meta = [], $sentBy = null): void
    {
        \App\Models\InvoiceReminderHistory::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'type' => $type,
            'recipient_email' => $notifyUser->email ?? null,
            'recipient_name' => $notifyUser->name ?? null,
            'sent_by' => $sentBy ?: (user() ? user()->id : null),
            'meta' => $meta ?: null,
        ]);
    }
}
