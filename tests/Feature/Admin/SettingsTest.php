<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_view_settings(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedSettings();

        $response = $this->put(route('admin.settings.update'), [
            'group' => 'system',
            'values' => [
                'timezone' => 'Asia/Kolkata',
                'registration' => '1',
                'maintenance_mode' => '0',
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('Asia/Kolkata', app(SettingsService::class)->get('system', 'timezone'));
        $this->assertTrue((bool) app(SettingsService::class)->get('system', 'registration'));
        $this->assertFalse((bool) app(SettingsService::class)->get('system', 'maintenance_mode'));
    }

    public function test_admin_can_select_a_gateway_without_exposing_or_clearing_saved_secrets(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedSettings();
        $settings = app(SettingsService::class);
        $settings->set('payments', 'razorpay_test_key_secret', 'keep-this-secret', 'secret');

        $response = $this->put(route('admin.settings.update'), [
            'group' => 'payments',
            'values' => [
                'gateway' => 'phonepe',
                'environment' => 'live',
                'razorpay_test_key_secret' => '',
                'phonepe_live_client_id' => 'phonepe-client-id',
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.edit'));
        $this->assertSame('phonepe', $settings->get('payments', 'gateway'));
        $this->assertSame('live', $settings->get('payments', 'environment'));
        $this->assertSame('keep-this-secret', $settings->get('payments', 'razorpay_test_key_secret'));
        $this->assertSame('phonepe-client-id', $settings->get('payments', 'phonepe_live_client_id'));
        $this->assertNotSame('phonepe-client-id', Setting::query()->where('group', 'payments')->where('key', 'phonepe_live_client_id')->value('value'));
        $this->assertArrayNotHasKey('phonepe_live_client_id', $settings->forGroup('payments'));
    }

    public function test_site_branding_settings_are_rendered_in_the_panel_and_guest_layouts(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedSettings();
        Storage::fake('public');

        $this->put(route('admin.settings.update'), [
            'group' => 'site',
            'values' => [
                'name' => 'Northwind Tracker',
                'tagline' => 'A clearer field operation',
                'support_email' => 'help@northwind.test',
                'support_phone' => '+91-1234567890',
            ],
            'logo' => UploadedFile::fake()->image('brand.png'),
            'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertSessionHasNoErrors();

        $logo = Setting::query()->where('group', 'site')->where('key', 'logo')->firstOrFail()->value;
        $favicon = Setting::query()->where('group', 'site')->where('key', 'favicon')->firstOrFail()->value;

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Northwind Tracker')
            ->assertSee('A clearer field operation')
            ->assertSee('help@northwind.test')
            ->assertSee(asset('storage/'.$logo), false)
            ->assertSee(asset('storage/'.$favicon), false);

        Auth::logout();
        $this->get(route('password.request'))->assertOk()->assertSee('Northwind Tracker');
    }
}
