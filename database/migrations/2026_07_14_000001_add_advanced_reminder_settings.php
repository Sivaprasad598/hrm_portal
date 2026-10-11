<?php

use App\Models\InvoiceSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_settings', 'reminder_before_enabled')) {
                $table->boolean('reminder_before_enabled')->default(false)->after('send_reminder_after');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_before_rules')) {
                $table->json('reminder_before_rules')->nullable()->after('reminder_before_enabled');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_after_enabled')) {
                $table->boolean('reminder_after_enabled')->default(false)->after('reminder_before_rules');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_after_frequency')) {
                $table->unsignedInteger('reminder_after_frequency')->default(3)->after('reminder_after_enabled');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_after_frequency_unit')) {
                $table->string('reminder_after_frequency_unit', 10)->default('hours')->after('reminder_after_frequency');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_after_start')) {
                $table->unsignedInteger('reminder_after_start')->default(1)->after('reminder_after_frequency_unit');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_after_start_unit')) {
                $table->string('reminder_after_start_unit', 10)->default('hours')->after('reminder_after_start');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_limit_type')) {
                $table->string('reminder_limit_type', 20)->default('until_paid')->after('reminder_after_start_unit');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_limit_value')) {
                $table->unsignedInteger('reminder_limit_value')->nullable()->after('reminder_limit_type');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_limit_date')) {
                $table->date('reminder_limit_date')->nullable()->after('reminder_limit_value');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_include_pdf')) {
                $table->boolean('reminder_include_pdf')->default(false)->after('reminder_limit_date');
            }

            if (!Schema::hasColumn('invoice_settings', 'reminder_include_payment_link')) {
                $table->boolean('reminder_include_payment_link')->default(true)->after('reminder_include_pdf');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'reminder_use_custom')) {
                $table->boolean('reminder_use_custom')->default(false)->after('send_status');
            }

            if (!Schema::hasColumn('invoices', 'reminder_settings')) {
                $table->json('reminder_settings')->nullable()->after('reminder_use_custom');
            }

            if (!Schema::hasColumn('invoices', 'reminder_sent_count')) {
                $table->unsignedInteger('reminder_sent_count')->default(0)->after('reminder_settings');
            }

            if (!Schema::hasColumn('invoices', 'reminder_last_sent_at')) {
                $table->timestamp('reminder_last_sent_at')->nullable()->after('reminder_sent_count');
            }

            if (!Schema::hasColumn('invoices', 'reminder_before_sent')) {
                $table->json('reminder_before_sent')->nullable()->after('reminder_last_sent_at');
            }
        });

        $this->migrateLegacySettings();
    }

    /**
     * Convert legacy day-based reminder columns into the advanced structure.
     */
    private function migrateLegacySettings(): void
    {
        InvoiceSetting::query()->each(function (InvoiceSetting $setting) {
            $beforeRules = [];

            if ((int) $setting->send_reminder > 0) {
                $beforeRules[] = [
                    'enabled' => true,
                    'value' => (int) $setting->send_reminder,
                    'unit' => 'days',
                ];
            }

            $setting->reminder_before_enabled = count($beforeRules) > 0;
            $setting->reminder_before_rules = $beforeRules ?: InvoiceSetting::defaultBeforeReminderRules();

            $afterValue = (int) $setting->send_reminder_after;
            $setting->reminder_after_enabled = $afterValue > 0;
            $setting->reminder_after_frequency = $afterValue > 0 ? $afterValue : 3;
            $setting->reminder_after_frequency_unit = 'days';
            $setting->reminder_after_start = $afterValue > 0 ? $afterValue : 1;
            $setting->reminder_after_start_unit = 'days';
            $setting->reminder_limit_type = 'until_paid';
            $setting->reminder_limit_value = null;
            $setting->reminder_limit_date = null;
            $setting->reminder_include_pdf = false;
            $setting->reminder_include_payment_link = true;
            $setting->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            $columns = [
                'reminder_before_enabled',
                'reminder_before_rules',
                'reminder_after_enabled',
                'reminder_after_frequency',
                'reminder_after_frequency_unit',
                'reminder_after_start',
                'reminder_after_start_unit',
                'reminder_limit_type',
                'reminder_limit_value',
                'reminder_limit_date',
                'reminder_include_pdf',
                'reminder_include_payment_link',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invoice_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $columns = [
                'reminder_use_custom',
                'reminder_settings',
                'reminder_sent_count',
                'reminder_last_sent_at',
                'reminder_before_sent',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
