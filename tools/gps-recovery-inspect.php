<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only incident inspection. Never prints credentials or personal records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection('mysql');
echo 'Configured database: '.$db->getDatabaseName().PHP_EOL;
foreach (['SHOW VARIABLES LIKE \'log_bin\'', 'SHOW VARIABLES LIKE \'datadir\''] as $sql) {
    foreach ($db->select($sql) as $row) {
        echo json_encode($row).PHP_EOL;
    }
}
foreach (['users', 'gps_locations', 'tracking_relations', 'vehicles', 'gps_devices', 'migrations'] as $table) {
    try {
        echo $table.': '.$db->table($table)->count().PHP_EOL;
    } catch (Throwable) {
        echo $table.': missing'.PHP_EOL;
    }
}
