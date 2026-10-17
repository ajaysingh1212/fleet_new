<?php

use App\Services\Gps\HardwareFleet;
use Illuminate\Contracts\Console\Kernel;

// Read-only view of the configured application's hardware fleet; no credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
echo json_encode(app(HardwareFleet::class)->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
