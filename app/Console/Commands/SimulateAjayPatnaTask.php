<?php

namespace App\Console\Commands;

use App\DTO\LocationUpdateData;
use App\Enums\DiagnosticEventType;
use App\Enums\SourceType;
use App\Enums\TrackingSessionStatus;
use App\Jobs\StoreGpsLocationJob;
use App\Models\DeviceSession;
use App\Models\DiagnosticLog;
use App\Models\FieldTask;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\Gps\CoordinateOptimizerService;
use Carbon\CarbonImmutable;
use Database\Seeders\AjayPatnaLiveTaskSeeder;
use Illuminate\Console\Command;

class SimulateAjayPatnaTask extends Command
{
    protected $signature = 'demo:simulate-ajay-task {--delay=1 : Seconds between live GPS points} {--steps=8 : Intermediate points per route leg} {--fresh : Recreate the demo before moving}';
    protected $description = 'Move Ajay Kumar through a three-stop Patna task using the real GPS, task-completion, storage, and WebSocket pipeline.';

    public function handle(CoordinateOptimizerService $optimizer): int
    {
        if ($this->option('fresh')) $this->call('db:seed', ['--class' => AjayPatnaLiveTaskSeeder::class, '--force' => true]);

        $ajay = User::where('email', 'ajay@gmail.com')->first();
        $task = FieldTask::where('reference_code', AjayPatnaLiveTaskSeeder::TASK_REFERENCE)->with('stops')->first();
        if (! $ajay || ! $task) {
            $this->error('Demo is missing. Run: php artisan db:seed --class=AjayPatnaLiveTaskSeeder');
            return self::FAILURE;
        }

        $device = DeviceSession::query()->updateOrCreate(
            ['user_id' => $ajay->id, 'session_id' => 'ajay-patna-live-demo'],
            ['device_name' => 'Ajay Demo Mobile', 'platform' => 'browser', 'browser' => 'Simulation', 'is_current' => true, 'last_login_at' => now(), 'last_activity_at' => now(), 'logged_out_at' => null],
        );
        $session = TrackingSession::create([
            'user_id' => $ajay->id, 'device_session_id' => $device->id, 'source_type' => SourceType::Browser,
            'started_at' => now(), 'status' => TrackingSessionStatus::Active,
        ]);
        DiagnosticLog::create([
            'user_id' => $ajay->id, 'device_session_id' => $device->id,
            'event_type' => DiagnosticEventType::GpsEnabled, 'network_type' => '4g',
            'battery_level' => 94, 'reason' => 'Patna live task simulation started', 'occurred_at' => now(),
        ]);

        $waypoints = [[25.5913170, 85.0879900]];
        foreach ($task->stops as $stop) $waypoints[] = [(float) $stop->latitude, (float) $stop->longitude];
        $points = $this->interpolate($waypoints, max(2, (int) $this->option('steps')));
        $delay = max(0.0, (float) $this->option('delay'));
        $started = CarbonImmutable::now();

        $this->info("Simulating {$task->title}: ".count($points).' live points. Keep User 1 Live Map open.');
        foreach ($points as $index => [$lat, $lng, $bearing]) {
            $battery = max(70, 94 - (int) floor($index / 4));
            $dto = new LocationUpdateData(
                deviceId: $device->session_id, sourceType: SourceType::Browser,
                latitude: $lat, longitude: $lng, accuracy: 7.0, speed: $index === count($points) - 1 ? 0.0 : 8.5,
                bearing: $bearing, heading: $bearing, altitude: 53.0, batteryLevel: $battery,
                networkType: '4g', signalStrength: 86, provider: 'demo-route', isMock: false,
                recordedAt: $started->addSeconds($index * max(1, (int) ceil($delay))), trackingSessionUuid: $session->uuid,
            );
            (new StoreGpsLocationJob($ajay->id, $device->id, $session->id, $dto))->handle($optimizer);
            $this->output->write("\rPoint ".($index + 1).'/'.count($points)." saved and broadcast");
            if ($delay > 0 && $index < count($points) - 1) usleep((int) ($delay * 1_000_000));
        }

        $session->refresh()->update(['status' => TrackingSessionStatus::Ended, 'ended_at' => now()]);
        $task->refresh()->load('stops');
        $this->newLine(2);
        $this->info('Task status: '.$task->status.'; stops completed: '.$task->stops->where('status', 'completed')->count().'/3; route data saved: '.count($points).' points.');
        return $task->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }

    private function interpolate(array $waypoints, int $steps): array
    {
        $result = [];
        for ($leg = 0; $leg < count($waypoints) - 1; $leg++) {
            [$startLat, $startLng] = $waypoints[$leg]; [$endLat, $endLng] = $waypoints[$leg + 1];
            $bearing = $this->bearing($startLat, $startLng, $endLat, $endLng);
            for ($step = $leg === 0 ? 0 : 1; $step <= $steps; $step++) {
                $ratio = $step / $steps;
                $result[] = [$startLat + (($endLat - $startLat) * $ratio), $startLng + (($endLng - $startLng) * $ratio), $bearing];
            }
        }
        return $result;
    }

    private function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $y = sin(deg2rad($lng2 - $lng1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng2 - $lng1));
        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }
}
