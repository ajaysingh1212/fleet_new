<?php

// Creates and drops only its own random, empty database. Never migrate:fresh an
// existing application database. Uses existing private MySQL credentials.
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
$name = 'fleet_gps_test_'.bin2hex(random_bytes(6));
if (! preg_match('/^fleet_gps_test_[a-f0-9]{12}$/D', $name)) {
    exit(1);
}
$xmlPath = base_path('.'.$name.'.xml');
$created = false;
$exitCode = 1;
try {
    $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4';
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $xml = new DOMDocument;
    $xml->load(base_path('phpunit.xml'));
    foreach ($xml->getElementsByTagName('env') as $node) {
        if ($node->getAttribute('name') === 'DB_CONNECTION') {
            $node->setAttribute('value', 'mysql');
        }
        if ($node->getAttribute('name') === 'DB_DATABASE') {
            $node->setAttribute('value', $name);
        }
        $node->setAttribute('force', 'true');
    }
    // Laravel's environment repository reads $_SERVER before $_ENV. Both must
    // agree, including on Windows where the bootstrapped parent has populated
    // server variables from .env.
    $php = $xml->getElementsByTagName('php')->item(0);
    $envNodes = iterator_to_array($xml->getElementsByTagName('env'));
    foreach ($envNodes as $node) {
        $server = $xml->createElement('server');
        $server->setAttribute('name', $node->getAttribute('name'));
        $server->setAttribute('value', $node->getAttribute('value'));
        $php->appendChild($server);
    }
    $xml->save($xmlPath);
    $process = new Process([PHP_BINARY, base_path('vendor/phpunit/phpunit/phpunit'), '-c', $xmlPath,
        '--stop-on-error', base_path('tests/Feature/HardwareGpsTest.php')], base_path(), [
            'DB_URL' => false, 'DB_DATABASE' => $name, 'DB_CONNECTION' => 'mysql', 'GPS_ISOLATED_TEST_DATABASE' => $name,
        ]);
    $process->setTimeout(180);
    $exitCode = $process->run(fn ($type, $buffer) => print ($buffer));
} catch (Throwable) {
    fwrite(STDERR, "Isolated MySQL tests failed to start/finish. Verify local MySQL and CREATE DATABASE permission. No credentials printed.\n");
} finally {
    if ($created) {
        $pdo->exec('DROP DATABASE `'.$name.'`');
    }
    if (is_file($xmlPath)) {
        unlink($xmlPath);
    }
}
exit($exitCode);
