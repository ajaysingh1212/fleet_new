<?php

use App\Models\GpsDevice;
use App\Services\Gps\HardwareFleet;
use App\Services\Gps\HardwareLiveState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$queues = DB::table('jobs')->whereIn('queue', ['gps-live', 'gps-persist', 'gps-broadcasts', 'gps-hardware'])
    ->selectRaw('queue, COUNT(*) as pending, MIN(created_at) as oldest_at, SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved')
    ->groupBy('queue')->get()->map(fn ($row) => ['queue' => $row->queue, 'pending' => $row->pending,
        'reserved' => $row->reserved, 'oldest_age_seconds' => time() - $row->oldest_at]);
$device = GpsDevice::with(['vehicle', 'latestLocation', 'latestHeartbeat'])->where('identifier', $argv[1] ?? '865167041931701')->first();
$state = $device ? app(HardwareLiveState::class)->overlay(app(HardwareFleet::class)->device($device)) : null;
echo json_encode(['now_utc' => now()->utc()->toIso8601String(), 'queues' => $queues,
    'model' => $device?->model, 'protocol' => $device?->protocol,
    'configured_reporting_interval' => $device?->configuration['reporting_interval'] ?? null,
    'failed_gps_jobs' => DB::table('failed_jobs')->whereIn('queue', ['gps-live', 'gps-persist', 'gps-broadcasts', 'gps-hardware'])->count(),
    'device' => $state ? array_intersect_key($state, array_flip(['device_id', 'imei', 'online', 'state',
        'connection_connected', 'connection_observed_at', 'recorded_at', 'received_at', 'last_packet_at',
        'live_received_at', 'live_revision', 'speed', 'acc', 'gps_state', 'sensors', 'queue_delay_ms', 'projected_at'])) : null,
    'worker_pool' => is_file(storage_path('framework/gps-workers-status.json'))
        ? json_decode(file_get_contents(storage_path('framework/gps-workers-status.json')), true) : null], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
