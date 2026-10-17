<?php

use App\Services\Gps\Gateway\Protocols\ConcoxX1;
use App\Services\Gps\Gateway\Protocols\Gt06;
use App\Services\Gps\Gateway\Protocols\Pt06;
use App\Services\Gps\Gateway\Protocols\Teltonika;

return [
    'host' => env('GPS_HOST', '0.0.0.0'),
    'port' => (int) env('GPS_PORT', 9000),
    'cache_store' => env('GPS_CACHE_STORE', 'gps-redis'),
    'queue_connection' => env('GPS_QUEUE_CONNECTION', 'gps-redis'),
    'queue' => 'gps-hardware',
    'max_queue' => 10000,
    'max_clients' => 256,
    'max_buffer' => 65536,
    'max_frame' => 16384,
    'idle_seconds' => 180,
    'pending_diagnostics' => (bool) env('GPS_PENDING_DIAGNOSTICS', false),
    'diagnostic_days' => 7,
    'location_days' => 90,
    'adapters' => [
        Pt06::class,
        Gt06::class,
        Teltonika::class,
        ConcoxX1::class,
    ],
    'protocols' => [
        'pt06' => ['versions' => [Pt06::VERSION], 'status' => 'PT06 supplied V02 manual: 01/13/22/26/24/94/21/2A; 27/2C layouts not provided', 'commands' => []],
        'teltonika' => ['versions' => ['8', '8E'], 'status' => 'implemented; Codec8 vendor fixture, 8E simulated fixtures; hardware acceptance required', 'commands' => []],
        'concox-x1' => ['versions' => ['1.1'], 'status' => 'partial GT06 family: X1 V1.1 only; simulated fixtures; hardware acceptance required', 'commands' => []],
        'gt06' => ['versions' => ['1.8.1'], 'status' => 'classic 01 login / 12 position / 13 heartbeat only; BT50 login capture verified; other variants unverified', 'commands' => []],
        'mictrack-bt50' => ['versions' => [], 'status' => 'awaiting exact BT50 manual; B50 is not assumed equivalent', 'commands' => []],
        'gps103' => ['versions' => [], 'status' => 'awaiting exact variant manual and captures', 'commands' => []],
        'jtt808' => ['versions' => [], 'status' => 'awaiting versioned manual and captures', 'commands' => []],
        'h02' => ['versions' => [], 'status' => 'awaiting exact variant manual and captures', 'commands' => []],
    ],
];
