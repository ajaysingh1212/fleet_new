<?php

namespace Tests\Feature;

use App\Models\GpsDevice;
use App\Services\Gps\DeviceCommands;
use App\Services\Gps\Gateway\Protocols\Pt06;
use App\Services\Gps\Gateway\Protocols\Pt06CommandEncoder;
use App\Services\Gps\Pt06CommandCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class GpsDeviceCommandsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['gps.live_cache_store' => 'array']);
    }

    private function device(string $imei = '864540081540460'): GpsDevice
    {
        return GpsDevice::create(['name' => 'Test tracker', 'identifier' => $imei, 'imei' => $imei,
            'protocol' => 'pt06', 'protocol_version' => Pt06::VERSION, 'status' => 'active']);
    }

    public function test_encoder_matches_crc_valid_manual_example_exactly(): void
    {
        $expected = trim(file_get_contents(base_path('tests/Fixtures/gps/pt06/pdf-server-command.hex')));
        $this->assertSame($expected, strtoupper(bin2hex(Pt06CommandEncoder::encode('DYD,000000#', 0x0001A958, 0x00A0, false))));
    }

    public function test_command_includes_language_outside_command_length(): void
    {
        $frame = Pt06CommandEncoder::encode('STATUS#', 1, 7, true);
        $this->assertSame(24, strlen($frame));
        $this->assertSame(19, ord($frame[2]));
        $this->assertSame(11, ord($frame[4]));
        $this->assertSame("STATUS#\x00\x02\x00\x07", substr($frame, 9, -4));
        $buffer = $frame;
        $this->assertSame($frame, (new Pt06)->extract($buffer, true));
        $this->assertSame('', $buffer);
    }

    public function test_catalog_contains_all_39_manual_groups_and_validates_queries(): void
    {
        $catalog = app(Pt06CommandCatalog::class);
        $this->assertCount(39, $catalog->entries());
        foreach ($catalog->entries() as $entry) {
            if ($entry['query']) {
                $this->assertFalse($catalog->validate($entry['query'])['mutating']);
            }
        }
        foreach (['GMT,E,5,30#', 'SERVER,0,127.0.0.1,9000,0#', 'TIMER,10,10#', 'LJDW#', 'RELAY,1#'] as $command) {
            $this->assertTrue($catalog->validate($command)['mutating']);
        }
    }

    public function test_only_administrators_can_view_or_send_commands(): void
    {
        $device = $this->device();
        $this->actingAsPlainUser();
        $this->getJson('/admin/gps-devices/'.$device->id.'/commands')->assertForbidden();
        $this->postJson('/admin/gps-devices/'.$device->id.'/commands', ['command' => 'STATUS#', 'request_id' => Str::uuid()])->assertForbidden();
    }

    public function test_offline_and_unconfirmed_changes_are_rejected(): void
    {
        $this->actingAsSuperAdmin();
        $device = $this->device();
        $url = '/admin/gps-devices/'.$device->id.'/commands';
        $this->postJson($url, ['command' => 'STATUS#', 'request_id' => Str::uuid()])->assertStatus(409);
        app(DeviceCommands::class)->connected($device->id, (string) Str::uuid());
        $this->postJson($url, ['command' => 'GMT,E,5,30#', 'request_id' => Str::uuid()])->assertUnprocessable();
        $this->postJson($url, ['command' => 'RELAY,1#', 'confirmed' => true, 'request_id' => Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('gps_device_commands', 0);
    }

    public function test_request_is_idempotent_and_reply_requires_same_device_session_and_flag(): void
    {
        $user = $this->actingAsSuperAdmin();
        $device = $this->device();
        $other = $this->device('861128068689493');
        $service = app(DeviceCommands::class);
        $session = (string) Str::uuid();
        $service->connected($device->id, $session);
        $payload = ['command' => 'STATUS#', 'request_id' => (string) Str::uuid()];
        $url = '/admin/gps-devices/'.$device->id.'/commands';
        $id = $this->postJson($url, $payload)->assertStatus(202)->json('command.id');
        $this->postJson($url, $payload)->assertStatus(202)->assertJsonPath('command.id', $id);
        $this->assertDatabaseCount('gps_device_commands', 1);
        $this->assertNull($service->claim($device->id, $device->identifier, 'wrong-session'));
        $claim = $service->claim($device->id, $device->identifier, $session, 0x01FF);
        $this->assertSame($id, $claim['id']);
        $this->assertSame(Pt06CommandEncoder::encode('STATUS#', $id, 0x01FF), $claim['frame']);
        $this->assertNull($service->claim($device->id, $device->identifier, $session));
        $service->sent($id);
        $events = ['server_flag' => $id, 'response_text' => 'GPS: NOT FIXED; GSM: Strong'];
        $this->assertFalse($service->reply($other->id, $session, $events));
        $this->assertFalse($service->reply($device->id, 'wrong-session', $events));
        $this->assertTrue($service->reply($device->id, $session, $events));
        $this->getJson($url)->assertOk()->assertJsonPath('commands.0.response', $events['response_text'])->assertJsonPath('commands.0.status', 'replied');
        $this->assertDatabaseHas('gps_device_commands', ['id' => $id, 'requested_by' => $user->id]);
    }

    public function test_expired_and_disconnected_commands_are_never_resent(): void
    {
        $user = $this->actingAsSuperAdmin();
        $device = $this->device();
        $service = app(DeviceCommands::class);
        $session = (string) Str::uuid();
        $service->connected($device->id, $session);
        $row = $service->request($device, $user->id, 'WHERE#', (string) Str::uuid(), false);
        $this->travel(31)->seconds();
        $this->assertNull($service->claim($device->id, $device->identifier, $session));
        $this->assertDatabaseHas('gps_device_commands', ['id' => $row['id'], 'status' => 'timed_out']);
        $service->connected($device->id, $session);
        $next = $service->request($device, $user->id, 'STATUS#', (string) Str::uuid(), false);
        $service->disconnected($device->id, $session);
        $this->assertDatabaseHas('gps_device_commands', ['id' => $next['id'], 'status' => 'failed']);
        $this->assertNull($service->claim($device->id, $device->identifier, $session));
    }

    public function test_invalid_commands_and_parameter_ranges_are_rejected(): void
    {
        foreach (["STATUS#\nRESET#", 'UNKNOWN#', 'GMT,E,15,0#', 'GMT,E,5,17#', 'SERVER,0,not-an-ip,9000,0#', 'TIMER,0,10#', 'RELAY,2#'] as $text) {
            try {
                app(Pt06CommandCatalog::class)->validate($text);
                $this->fail('Accepted invalid command '.$text);
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
