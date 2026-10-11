<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // EmailNotificationSetting::NOTIFICATIONS only gets inserted for companies
        // created after this slug was added (via CompanyObserver). Backfill it for
        // every company that already exists.
        $companies = Company::pluck('id');

        foreach ($companies as $companyId) {
            $exists = DB::table('email_notification_settings')
                ->where('company_id', $companyId)
                ->where('slug', 'new-resignation-request')
                ->exists();

            if (!$exists) {
                DB::table('email_notification_settings')->insert([
                    'company_id'   => $companyId,
                    'slug'         => 'new-resignation-request',
                    'setting_name' => 'New Resignation Request',
                    'send_email'   => 'yes',
                    'send_slack'   => 'no',
                    'send_push'    => 'no',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('email_notification_settings')->where('slug', 'new-resignation-request')->delete();
    }
};
