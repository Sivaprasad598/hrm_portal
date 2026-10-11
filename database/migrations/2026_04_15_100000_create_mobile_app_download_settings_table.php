<?php

use App\Models\MobileAppDownloadSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mobile_app_download_settings')) {
            return;
        }

        Schema::create('mobile_app_download_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_ios', 2048)->nullable();
            $table->string('app_android', 2048)->nullable();
            $table->timestamps();
        });

        DB::table('mobile_app_download_settings')->insert([
            'id' => 1,
            'app_ios' => MobileAppDownloadSetting::DEFAULT_APP_IOS,
            'app_android' => MobileAppDownloadSetting::DEFAULT_APP_ANDROID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_app_download_settings');
    }
};
