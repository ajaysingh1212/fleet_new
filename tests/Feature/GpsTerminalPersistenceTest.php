<?php

namespace Tests\Feature;

use App\Console\Commands\ListenGpsGateway;
use App\Services\Gps\Gateway\DeviceAllowlist;
use App\Services\Gps\Gateway\Gateway;
use Mockery;
use Tests\TestCase;

class GpsTerminalPersistenceTest extends TestCase
{
    private function assertForwardedPersistence(bool $configured, array $options, bool $expected): void
    {
        config(['gps.diagnostic_persist' => $configured]);
        $options += ['diagnostic' => true, 'host' => null, 'port' => 9000,
            'diagnostic-imei' => '865167041931701', 'format' => 'pretty',
            'diagnostic-protocol' => 'pt06', 'persist' => false,
            'registered' => false, 'observe-only' => false];
        $command = Mockery::mock(ListenGpsGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('option')->andReturnUsing(fn ($key) => $options[$key]);
        $command->shouldReceive('call')->once()->with('gps:diagnose', Mockery::on(
            fn ($args) => $args['--persist'] === $expected
                && $args['--imei'] === '865167041931701'
                && $args['--registered'] === false
        ))->andReturn(0);
        $this->assertSame(0, $command->handle(app(Gateway::class), app(DeviceAllowlist::class)));
    }

    public function test_local_setting_preserves_map_updates_when_terminal_command_omits_persist(): void
    {
        $this->assertForwardedPersistence(true, [], true);
    }

    public function test_default_diagnostics_do_not_write_to_database(): void
    {
        $this->assertForwardedPersistence(false, [], false);
    }

    public function test_observation_can_explicitly_disable_local_persistence(): void
    {
        $this->assertForwardedPersistence(true, ['observe-only' => true], false);
    }

    public function test_explicit_persist_still_works_without_local_setting(): void
    {
        $this->assertForwardedPersistence(false, ['persist' => true], true);
    }

    public function test_conflicting_observation_and_storage_options_are_rejected(): void
    {
        $this->artisan('gps:listen', ['--diagnostic' => true, '--observe-only' => true, '--persist' => true])
            ->expectsOutput('--observe-only cannot be combined with --persist or --registered.')
            ->assertExitCode(1);
    }
}
