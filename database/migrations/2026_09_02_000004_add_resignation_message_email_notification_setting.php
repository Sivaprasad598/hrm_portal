<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companies = Company::pluck('id');

        foreach ($companies as $companyId) {
            $exists = DB::table('email_notification_settings')
                ->where('company_id', $companyId)
                ->where('slug', 'resignation-message-received')
                ->exists();

            if (!$exists) {
                DB::table('email_notification_settings')->insert([
                    'company_id'   => $companyId,
                    'slug'         => 'resignation-message-received',
                    'setting_name' => 'New Message on Resignation Request',
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
        DB::table('email_notification_settings')->where('slug', 'resignation-message-received')->delete();
    }
};
