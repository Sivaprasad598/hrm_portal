<?php

namespace Tests\Feature;

use App\Models\MobileAppDownloadSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppDownloadSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_downloads_settings(): void
    {
        $this->get(route('downloads.index'))->assertRedirect();
    }

    public function test_guest_is_redirected_from_app_settings_index(): void
    {
        $this->get(route('app-settings.index'))->assertRedirect();
    }

    public function test_migration_seeds_singleton_download_settings_row(): void
    {
        $this->assertDatabaseCount('mobile_app_download_settings', 1);

        $row = MobileAppDownloadSetting::query()->first();
        $this->assertNotNull($row);
        $this->assertSame(MobileAppDownloadSetting::DEFAULT_APP_IOS, $row->app_ios);
        $this->assertSame(MobileAppDownloadSetting::DEFAULT_APP_ANDROID, $row->app_android);
    }

    public function test_instance_returns_existing_row(): void
    {
        MobileAppDownloadSetting::query()->delete();

        MobileAppDownloadSetting::query()->create([
            'app_ios' => 'https://apps.apple.com/app/example',
            'app_android' => null,
        ]);

        $instance = MobileAppDownloadSetting::instance();

        $this->assertSame('https://apps.apple.com/app/example', $instance->app_ios);
        $this->assertNull($instance->app_android);
    }
}
