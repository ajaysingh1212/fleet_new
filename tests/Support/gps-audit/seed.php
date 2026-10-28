<?php

use App\Models\GpsDevice;
use App\Models\GpsDeviceLocation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$run = getenv('GPS_AUDIT_RUN');
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, 'Isolated fixture failure: '.get_class($error).' at '.$error->getFile().':'.$error->getLine().PHP_EOL);
    exit(1);
});
$name = DB::connection()->getDatabaseName();
$isolatedMysql = config('database.default') === 'mysql' && preg_match('/^fleet_gps_audit_[a-f0-9]{16}$/D', $name)
    && is_file($run.'/db-name') && trim(file_get_contents($run.'/db-name')) === $name;
if (! $run || (! $isolatedMysql && (config('database.default') !== 'sqlite'
    || realpath($name) !== realpath($run.'/audit.sqlite')))) {
    throw new RuntimeException('Isolated audit database required.');
}
if (in_array($argv[1] ?? '', ['create', 'drop'], true)) {
    if (! $isolatedMysql) {
        throw new RuntimeException('Isolated MySQL name required.');
    }
    $c = config('database.connections.mysql');
    $pdo = new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(($argv[1] === 'create' ? 'CREATE DATABASE ' : 'DROP DATABASE ').'`'.$name.'`'.($argv[1] === 'create' ? ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' : ''));
    echo 'Isolated audit database '.($argv[1] === 'create' ? 'created.' : 'removed.');
    exit;
}
if (($argv[1] ?? '') === 'status') {
    $f = glob($run.'/storage/logs/gps-pipeline-*.log');
    $events = [];
    foreach ($f as $file) {
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $row = json_decode($line, true);
            if ($row) {
                $events[] = ['phase' => $row['message'], ...$row['context']];
            }
        }
    }
    echo json_encode(['history' => GpsDeviceLocation::count(), 'jobs' => DB::table('jobs')->whereIn('queue', ['gps-live', 'gps-persist', 'gps-broadcasts'])->count(),
        'failed' => DB::table('failed_jobs')->whereIn('queue', ['gps-live', 'gps-persist', 'gps-broadcasts'])->count(), 'events' => $events]);
    exit;
}
(new RoleAndPermissionSeeder)->run();
$user = User::factory()->create(['name' => 'Isolated GPS audit', 'email' => 'gps-audit@example.invalid',
    'password' => Hash::make('Isolated-audit-password-7391'), 'status' => 'active', 'email_verified_at' => now()]);
$user->assignRole('Super Admin');
for ($i = 1; $i <= 3; $i++) {
    GpsDevice::create(['identifier' => (string) (990000000000000 + $i),
        'name' => 'Simulated audit tracker '.$i, 'status' => 'active', 'protocol' => 'pt06', 'protocol_version' => '2022.4.27-v2', 'model' => 'Audit fixture']);
}
echo 'Isolated audit fixtures created.';
