<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

// Only for the isolated loopback benchmark. Normal web authentication and
// private-channel authorization stay enabled; no test-only HTTP routes exist.
$run = getenv('GPS_AUDIT_RUN');
$isolatedMysql = getenv('DB_CONNECTION') === 'mysql' && preg_match('/^fleet_gps_audit_[a-f0-9]{16}$/D', (string) getenv('DB_DATABASE'))
    && is_file($run.'/db-name') && trim(file_get_contents($run.'/db-name')) === getenv('DB_DATABASE');
if (! $run || (! $isolatedMysql && (! is_file($run.'/audit.sqlite') || getenv('DB_CONNECTION') !== 'sqlite'
    || realpath((string) getenv('DB_DATABASE')) !== realpath($run.'/audit.sqlite')))) {
    http_response_code(500);
    exit('Isolated audit database required.');
}
$root = dirname(__DIR__, 3);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path && is_file($root.'/public'.$path)) {
    return false;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
app(Vite::class)->useHotFile($run.'/vite.hot');
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
