<?php

require __DIR__.'/../vendor/autoload.php';

use App\Services\Gps\HexPacketCodec;

$payload = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
if ($deviceKey = getenv('GPS_DEVICE_KEY')) {
    $payload['device_key'] = $deviceKey;
    unset($payload['token']);
} else {
    $payload['token'] = getenv('GPS_TOKEN') ?: throw new RuntimeException('Set GPS_DEVICE_KEY (registered device) or GPS_TOKEN (legacy login).');
}
$payload['packet_id'] ??= (string) \Illuminate\Support\Str::uuid();
$payload['recorded_at'] ??= gmdate('c');
$codec = new HexPacketCodec;
$code = isset($argv[1]) ? hexdec($argv[1]) : 2;
$host = $argv[2] ?? '127.0.0.1';
$port = (int) ($argv[3] ?? 9000);
$tls = ! in_array($host, ['127.0.0.1', '::1'], true);
$context = stream_context_create(['ssl' => ['verify_peer' => true,
    'verify_peer_name' => true, 'peer_name' => $host]]);
$address = ($tls ? 'tls' : 'tcp').'://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
$socket = stream_socket_client($address, $errno, $error, 10, STREAM_CLIENT_CONNECT, $context);
if (! $socket) throw new RuntimeException('Cannot connect: '.$error);
stream_set_timeout($socket, 10);
$frame = $codec->encode($code, $payload);
$offset = 0;
while ($offset < strlen($frame)) {
    $written = fwrite($socket, substr($frame, $offset));
    if ($written === false || $written === 0) throw new RuntimeException('Send failed.');
    $offset += $written;
}
$reply = fgets($socket, HexPacketCodec::MAX_HEX_LENGTH + 3);
if ($reply === false) throw new RuntimeException('No acknowledgement.');
fclose($socket);
echo json_encode($codec->decode($reply), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
