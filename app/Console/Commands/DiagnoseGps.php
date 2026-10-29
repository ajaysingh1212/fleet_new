<?php

namespace App\Console\Commands;

use App\Jobs\CloseHardwareSession;
use App\Services\Gps\Gateway\GpsDiagnosticFormatter;
use App\Services\Gps\Gateway\GpsDiagnosticState;
use App\Services\Gps\Gateway\Gt06DiagnosticDetails;
use App\Services\Gps\Gateway\Gt06DiagnosticFrames;
use App\Services\Gps\Gateway\Protocols\Gt06;
use App\Services\Gps\Gateway\Protocols\Pt06;
use App\Services\Gps\Gateway\TcpListenerLease;
use App\Services\Gps\HardwareConnectionState;
use App\Services\Gps\HardwareTelemetry;
use App\Services\Gps\DeviceCommands;
use App\Services\Gps\LocalHardwareRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/** Bounded packet observation; optional registered SQL storage and queued broadcasts. */
class DiagnoseGps extends Command
{
    protected $signature = 'gps:diagnose {--host=0.0.0.0} {--port=9000} {--imei= : Comma-separated diagnostic IMEIs (up to 32)} {--seconds=0} {--format=pretty} {--protocol=pt06} {--persist : Journal registered packets; live-first updates and background history} {--registered : Accept all active SQL registrations with --persist}';

    protected $description = 'Observe PT06/GT06 TCP packets with optional registered SQL storage and queued fleet updates';

    public function handle(Gt06 $legacyProtocol, Gt06DiagnosticFrames $framer, Gt06DiagnosticDetails $details): int
    {
        $host = (string) $this->option('host');
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $imei = (string) $this->option('imei');
        $imeis = $imei === '' ? [] : array_values(array_unique(array_map('trim', explode(',', $imei))));
        $validImeis = count($imeis) <= 32 && count(array_filter($imeis, fn ($value) => ! preg_match('/^[0-9]{15}$/D', $value))) === 0;
        $profile = (string) $this->option('protocol');
        $protocol = $profile === 'pt06' ? app(Pt06::class) : $legacyProtocol;
        $seconds = filter_var($this->option('seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 86400]]);
        if (! filter_var($host, FILTER_VALIDATE_IP) || ! $port || $seconds === false || ! in_array($profile, ['pt06', 'gt06'], true) || ! in_array($this->option('format'), ['pretty', 'json'], true) || ! $validImeis || ($this->option('registered') && ! $this->option('persist'))) {
            $this->error('Use a literal bind IP, valid port, optional 15-digit diagnostic IMEI and duration 0–86400 seconds.');

            return self::FAILURE;
        }
        try {
            $listenerLease = TcpListenerLease::acquire($port);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $address = 'tcp://'.(str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
        $context = stream_context_create(['socket' => ['tcp_nodelay' => true]]);
        $server = @stream_socket_server($address, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if (! $server) {
            $this->error('Cannot bind '.$address.': '.$error.' Stop the other port-9000 listener first.');

            return self::FAILURE;
        }
        stream_set_blocking($server, false);
        $this->info('GPS TCP DIAGNOSTIC LISTENER '.$address);
        $this->info('Decoder profile: '.$profile.($profile === 'pt06' ? ' / '.Pt06::VERSION : ' / classic 1.8.1'));
        $this->info('Terminal decoder: decoded-device-summary-v2 (restart required after code updates)');
        $this->warn(($this->option('persist') ? 'LOCAL STORAGE ENABLED: active SQL registrations required; '.(config('gps.local_broadcast') ? 'queued WebSocket updates.' : 'broadcasts disabled.') : 'DIAGNOSTIC ONLY: no database storage or broadcasts.').' ACK allowlist: '.($this->option('registered') ? 'all active SQL registrations (checked for every packet)' : ($imei ?: 'none; observation only')));
        $this->info('Live connections, bytes, login, heartbeat and location status appear below. Ctrl+C stops.');
        $clients = [];
        $totals = ['connections' => 0, 'reads' => 0, 'bytes' => 0, 'logins' => 0, 'heartbeats' => 0, 'locations' => 0, 'valid_fixes' => 0,
            'alarms' => 0, 'lbs_packets' => 0, 'information_packets' => 0, 'unsupported_packets' => 0];
        $started = time();
        $lastStatus = 0;
        $lastHealth = 0;
        $lastData = null;
        $lastCommandPoll = 0;
        $lastFix = null;
        $deviceStates = [];
        foreach ($imeis as $allowedImei) {
            $deviceStates[$allowedImei] = new GpsDiagnosticState;
        }
        $close = function (int $id, string $reason) use (&$clients, $profile): void {
            if (! isset($clients[$id])) {
                return;
            }
            $this->record('DISCONNECTED', ['peer' => $clients[$id]['peer'], 'imei' => $clients[$id]['imei'], 'reason' => $reason,
                'received_bytes' => $clients[$id]['bytes'], 'packets' => $clients[$id]['packets']]);
            HardwareTelemetry::phase('socket_closed', ['device_id' => $clients[$id]['device_id'], 'session_id' => $clients[$id]['session_id'], 'reason' => $reason]);
            if ($profile === 'pt06' && $this->option('persist') && $clients[$id]['device_id']) {
                try {
                    app(DeviceCommands::class)->disconnected($clients[$id]['device_id'], $clients[$id]['session_id'], $reason);
                } catch (Throwable) {
                    $this->record('COMMAND_STORAGE_ERROR', ['reason' => 'disconnect_update_failed']);
                }
            }
            if ($this->option('persist')) {
                try {
                    $presenceDeviceId = $clients[$id]['imei'] ? DB::table('gps_devices')->where('identifier', $clients[$id]['imei'])->value('id') : null;
                    if ($presenceDeviceId) {
                        app(HardwareConnectionState::class)->close($presenceDeviceId, $clients[$id]['session_id']);
                    }
                    if (config('gps.live_first')) {
                        $deviceId = $clients[$id]['imei'] ? DB::table('gps_devices')->where('identifier', $clients[$id]['imei'])->value('id') : null;
                        if ($deviceId) {
                            Queue::connection(config('gps.live_connection'))->push(new CloseHardwareSession([
                                'id' => $clients[$id]['session_id'], 'gps_device_id' => $deviceId, 'protocol' => $profile,
                                'remote_ip' => $clients[$id]['peer'], 'connected_at' => $clients[$id]['connected_at'],
                                'last_packet_at' => now()->utc()->format('Y-m-d H:i:s'),
                                'disconnected_at' => now()->utc()->format('Y-m-d H:i:s'), 'authenticated' => true,
                            ]), '', config('gps.persistence_queue'));
                        }
                    } else {
                        DB::table('gps_connection_sessions')->where('id', $clients[$id]['session_id'])->update(['disconnected_at' => now()->utc()]);
                    }
                } catch (Throwable) {
                    $this->record('STORAGE_ERROR', ['reason' => 'session_close_failed']);
                }
            }
            fclose($clients[$id]['socket']);
            unset($clients[$id]);
        };
        try {
            while ($seconds === 0 || time() - $started < $seconds) {
                if (time() - $lastHealth >= 5 && $this->option('persist')) {
                    foreach ($clients as $client) {
                        if ($client['device_id']) {
                            try {
                                app(HardwareConnectionState::class)->keepAlive($client['device_id'], $client['session_id']);
                                if ($profile === 'pt06') {
                                    app(DeviceCommands::class)->connected($client['device_id'], $client['session_id']);
                                }
                            } catch (Throwable) {
                            }
                        }
                    }
                    HardwareTelemetry::receiver($port, count($clients), count(array_filter($clients, fn ($client) => $client['imei'] !== null)));
                    $lastHealth = time();
                }
                if ($profile === 'pt06' && $this->option('persist') && time() !== $lastCommandPoll) {
                    $lastCommandPoll = time();
                    foreach ($clients as $id => $client) {
                        if (! $client['device_id'] || ! $client['imei'] || $client['out'] !== '') {
                            continue;
                        }
                        try {
                            $nextSerial = $client['last_serial'] === null ? null : (($client['last_serial'] + 1) & 0xFFFF);
                            $command = app(DeviceCommands::class)->claim($client['device_id'], $client['imei'], $client['session_id'], $nextSerial);
                            if ($command) {
                                $clients[$id]['out'] .= $command['frame'];
                                $clients[$id]['acks'][] = ['remaining' => strlen($command['frame']), 'command_id' => $command['id']];
                                $this->record('COMMAND_QUEUED', ['device_id' => $client['device_id'], 'command_id' => $command['id']]);
                            }
                        } catch (Throwable) {
                            $this->record('COMMAND_STORAGE_ERROR', ['reason' => 'command_dispatch_failed']);
                        }
                    }
                }
                $read = [$server];
                $write = [];
                $except = null;
                foreach ($clients as $client) {
                    $read[] = $client['socket'];
                    if ($client['out'] !== '') {
                        $write[] = $client['socket'];
                    }
                }
                if (@stream_select($read, $write, $except, 1) === false) {
                    break;
                }
                foreach ($read as $socket) {
                    if ($socket === $server) {
                        $socket = @stream_socket_accept($server, 0, $peer);
                        if (! $socket) {
                            continue;
                        }
                        if (count($clients) >= 32) {
                            fclose($socket);

                            continue;
                        }
                        stream_set_blocking($socket, false);
                        $clients[(int) $socket] = ['socket' => $socket, 'peer' => $peer, 'in' => '', 'out' => '',
                            'session_id' => (string) Str::uuid(), 'connected_at' => now()->utc()->format('Y-m-d H:i:s'),
                            'imei' => null, 'device_id' => null, 'last_serial' => null, 'acks' => [], 'activity' => time(), 'bytes' => 0, 'packets' => 0, 'window' => time(), 'window_bytes' => 0];
                        $totals['connections']++;
                        $this->record('CONNECTED', ['peer' => $peer, 'connected_now' => count($clients)]);
                        HardwareTelemetry::phase('socket_opened', ['session_id' => $clients[(int) $socket]['session_id']]);

                        continue;
                    }
                    $id = (int) $socket;
                    if (! isset($clients[$id])) {
                        continue;
                    }
                    try {
                        // Bound bytes per turn rather than discard a valid burst.
                        error_clear_last();
                        $bytes = @fread($socket, 128);
                        if ($bytes === false) {
                            $message = error_get_last()['message'] ?? '';
                            $nativeSocket = @socket_import_stream($socket);
                            $code = $nativeSocket === false ? 0 : socket_last_error($nativeSocket);
                            $reset = $code === 10054 || $code === 104
                                || preg_match('/10054|ECONNRESET|connection reset|forcibly closed/i', $message);
                            $close($id, $reset ? 'connection_reset_by_peer' : 'socket_read_failed');

                            continue;
                        }
                        if ($bytes === '' && feof($socket)) {
                            $close($id, 'peer_closed');

                            continue;
                        }
                        if ($bytes === '') {
                            continue;
                        }
                        $lastData = now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s T');
                        $receivedAt = HardwareTelemetry::stamp();
                        $readStarted = hrtime(true);
                        $totals['reads']++;
                        $totals['bytes'] += strlen($bytes);
                        $clients[$id]['bytes'] += strlen($bytes);
                        $clients[$id]['activity'] = time();
                        if (time() - $clients[$id]['window'] >= 60) {
                            $clients[$id]['window'] = time();
                            $clients[$id]['window_bytes'] = 0;
                        }
                        $clients[$id]['window_bytes'] += strlen($bytes);
                        if ($clients[$id]['window_bytes'] > 1048576) {
                            throw new InvalidArgumentException('rate_limit');
                        }
                        $clients[$id]['in'] .= $bytes;
                        $this->record('DATA_RECEIVED', ['peer' => $clients[$id]['peer'], 'bytes' => strlen($bytes), 'buffer_bytes' => strlen($clients[$id]['in'])]);
                        if (strlen($clients[$id]['in']) > 65536 || strlen($clients[$id]['out']) > 65536) {
                            throw new InvalidArgumentException('buffer_limit');
                        }
                        $frames = 0;
                        while (($frame = $framer->extract($clients[$id]['in'])) !== null) {
                                $clients[$id]['last_serial'] = unpack('n', substr($frame, -6, 2))[1];
                            $traceId = bin2hex(random_bytes(8));
                            HardwareTelemetry::phase('packet_received', ['trace_id' => $traceId, 'session_id' => $clients[$id]['session_id'],
                                'received_at' => $receivedAt, 'bytes' => strlen($frame)]);
                            if (++$frames > 64) {
                                throw new InvalidArgumentException('frame_limit');
                            }
                            $clients[$id]['packets']++;
                            $packetDetails = $details->packet($frame);
                            $packetContext = ['peer' => $clients[$id]['peer'], 'imei' => $clients[$id]['imei']];
                            if (in_array($clients[$id]['imei'], $imeis, true)) {
                                $this->record('PACKET_DETAILS', [...$packetContext, ...$packetDetails]);
                            }
                            if ($profile === 'gt06' && substr($frame, 0, 2) === "\x79\x79") {
                                $information = $details->simInformation($frame);
                                if (in_array($clients[$id]['imei'], $imeis, true) && $information !== null) {
                                    $this->record('DEVICE_SIM_INFORMATION', [...$packetContext, ...$information]);

                                    continue;
                                }
                                $this->record('EXTENDED_PACKET_CAPTURED', ['peer' => $clients[$id]['peer'],
                                    'imei' => $clients[$id]['imei'], 'type' => $framer->type($frame), 'bytes' => strlen($frame),
                                    'checksum' => 'valid', 'hex' => in_array($clients[$id]['imei'], $imeis, true) ? strtoupper(bin2hex(substr($frame, 0, 256))) : null,
                                    'sha256' => hash('sha256', $frame), 'note' => 'framing verified; payload not decoded; no guessed ACK']);

                                continue;
                            }
                            try {
                                $binaryLogin = $framer->type($frame) === '01';
                                $packet = $protocol->decode($frame, $clients[$id]['imei'] !== null && ! $binaryLogin);
                                HardwareTelemetry::phase('packet_parsed', ['trace_id' => $traceId, 'type' => $packet->type,
                                    'parsed_at' => HardwareTelemetry::stamp(), 'duration_ms' => (hrtime(true) - $readStarted) / 1000000]);
                            } catch (InvalidArgumentException $e) {
                                $this->record('PACKET_NOT_DECODED', ['peer' => $clients[$id]['peer'], 'type' => $framer->type($frame),
                                    'bytes' => strlen($frame), 'reason' => $e->getMessage(),
                                    'hex' => in_array($clients[$id]['imei'], $imeis, true) ? strtoupper(bin2hex(substr($frame, 0, 256))) : null,
                                    'sha256' => hash('sha256', $frame)]);

                                continue;
                            }
                            if ($packet->identifier !== null) {
                                if ($clients[$id]['imei'] !== null && $packet->identifier !== $clients[$id]['imei']) {
                                    throw new InvalidArgumentException('device_identity_changed');
                                }
                                if ($this->option('registered') && app(LocalHardwareRecorder::class)->allows($packet->identifier, $profile, $packet->version)) {
                                    if (! in_array($packet->identifier, $imeis, true)) {
                                        $imeis[] = $packet->identifier;
                                        $deviceStates[$packet->identifier] = new GpsDiagnosticState;
                                    }
                                }
                                $this->record('LOGIN', ['peer' => $clients[$id]['peer'], 'imei' => $packet->identifier, 'checksum' => 'valid',
                                    'diagnostic_allowed' => in_array($packet->identifier, $imeis, true)]);
                                $totals['logins']++;
                                if (! in_array($packet->identifier, $imeis, true)) {
                                    $close($id, 'not_diagnostic_allowlisted');
                                    break;
                                }
                                $clients[$id]['imei'] = $packet->identifier;
                                // A reconnect replaces old sockets for this tracker;
                                // commands must never be routed to a stale session.
                                foreach (array_keys($clients) as $otherId) {
                                    if ($otherId !== $id && $clients[$otherId]['imei'] === $packet->identifier) {
                                        $close($otherId, 'replaced_by_new_connection');
                                    }
                                }
                                $this->record('PACKET_DETAILS', ['peer' => $clients[$id]['peer'], 'imei' => $packet->identifier, ...$packetDetails]);
                            }
                            if ($this->option('persist')) {
                                $clients[$id]['device_id'] = app(LocalHardwareRecorder::class)->record($packet, $frame, $clients[$id]['imei'], $profile,
                                    $clients[$id]['session_id'], $clients[$id]['connected_at'], $clients[$id]['peer'], $receivedAt, $traceId);
                                $this->record(config('gps.live_first') ? 'PACKET_QUEUED' : 'DATABASE_SAVED', ['imei' => $clients[$id]['imei'], 'type' => $packet->type]);
                                if ($profile === 'pt06') {
                                    app(DeviceCommands::class)->connected($clients[$id]['device_id'], $clients[$id]['session_id']);
                                    if ($packet->type === 'command_response') {
                                        app(DeviceCommands::class)->reply($clients[$id]['device_id'], $clients[$id]['session_id'], $packet->events);
                                    }
                                }
                            }
                            if ($packet->ack !== '') {
                                $clients[$id]['out'] .= $packet->ack;
                                $clients[$id]['acks'][] = ['remaining' => strlen($packet->ack), 'trace_id' => $traceId, 'started' => $readStarted];
                                $this->record('ACK_QUEUED', ['imei' => $clients[$id]['imei'], 'type' => $packet->type,
                                    'bytes' => strlen($packet->ack)]);
                                $this->flushAcks($clients, $id, $close);
                                if (! isset($clients[$id])) {
                                    break;
                                }
                            }
                            $deviceState = $deviceStates[$clients[$id]['imei']];
                            $deviceState->observe($packet, $clients[$id]['imei'], $lastData);
                            if ($packet->type === 'heartbeat') {
                                $totals['heartbeats']++;
                                $this->record('HEARTBEAT', ['imei' => $clients[$id]['imei'], ...$packet->events]);
                            }
                            if ($packet->events && $packet->type !== 'heartbeat') {
                                if ($packet->type === 'alarm') {
                                    $totals['alarms']++;
                                } elseif ($packet->type === 'lbs') {
                                    $totals['lbs_packets']++;
                                } elseif ($packet->type === 'unsupported') {
                                    $totals['unsupported_packets']++;
                                } elseif (in_array($packet->type, ['information', 'device_sim_information'], true)) {
                                    $totals['information_packets']++;
                                }
                                $this->record(strtoupper($packet->type).'_DETAILS', ['imei' => $clients[$id]['imei'], ...$packet->events]);
                            }
                            foreach ($packet->locations as $location) {
                                $totals['locations']++;
                                if ($location->gpsFix) {
                                    $totals['valid_fixes']++;
                                    $lastFix = ['imei' => $clients[$id]['imei'], ...$location->toArray()];
                                }
                                $this->record($location->gpsFix ? 'GPS_LOCATION' : 'GPS_NO_FIX', ['imei' => $clients[$id]['imei'], ...$location->toArray()]);
                            }
                            if (in_array($packet->type, ['heartbeat', 'device_sim_information'], true)) {
                                $this->record('DEVICE_STATUS', $deviceState->snapshot(true));
                            }

                        }
                    } catch (Throwable $e) {
                        $close($id, $e instanceof InvalidArgumentException ? $e->getMessage() : 'socket_error');
                    }
                }
                foreach (array_keys($clients) as $id) {
                    $this->flushAcks($clients, $id, $close);
                }
                foreach ($clients as $id => $client) {
                    if (($client['imei'] === null && time() - strtotime($client['connected_at'].' UTC') > config('gps.login_timeout_seconds'))
                        || (config('gps.socket_idle_seconds') > 0 && time() - $client['activity'] > config('gps.socket_idle_seconds'))) {
                        $close($id, 'idle_timeout');
                    }
                }
                if (time() - $lastStatus >= 15) {
                    $snapshots = [];
                    foreach ($imeis as $allowedImei) {
                        $deviceConnected = collect($clients)->contains(fn ($client) => $client['imei'] === $allowedImei);
                        $snapshots[] = [...$deviceStates[$allowedImei]->snapshot($deviceConnected), 'imei' => $allowedImei];
                    }
                    $this->record('STATUS', ['connected_now' => count($clients), ...$totals, 'last_data' => $lastData, 'last_valid_fix' => $lastFix,
                        'devices' => $snapshots]);
                    $lastStatus = time();
                }
            }
        } finally {
            foreach (array_keys($clients) as $id) {
                $close($id, 'listener_stopped');
            }
            fclose($server);
        }

        return self::SUCCESS;
    }

    private function flushAcks(array &$clients, int $id, callable $close): void
    {
        if (! isset($clients[$id]) || $clients[$id]['out'] === '') {
            return;
        }
        try {
            $written = @fwrite($clients[$id]['socket'], $clients[$id]['out']);
        } catch (Throwable) {
            $written = false;
        }
        if ($written === false) {
            $close($id, 'write_failed');

            return;
        }
        if ($written === 0) {
            return;
        }
        $remaining = $written;
        while ($remaining > 0 && $clients[$id]['acks']) {
            $ack = &$clients[$id]['acks'][0];
            $used = min($remaining, $ack['remaining']);
            $ack['remaining'] -= $used;
            $remaining -= $used;
            if ($ack['remaining'] > 0) {
                unset($ack);
                break;
            }
            if (isset($ack['command_id'])) {
                try {
                    app(DeviceCommands::class)->sent($ack['command_id']);
                    $this->record('COMMAND_SENT', ['device_id' => $clients[$id]['device_id'], 'command_id' => $ack['command_id']]);
                } catch (Throwable) {
                    $this->record('COMMAND_STORAGE_ERROR', ['reason' => 'sent_update_failed']);
                }
            } else {
                HardwareTelemetry::phase('ack_sent', ['trace_id' => $ack['trace_id'], 'device_id' => $clients[$id]['device_id'],
                    'duration_ms' => (hrtime(true) - $ack['started']) / 1000000]);
            }
            unset($ack);
            array_shift($clients[$id]['acks']);
        }
        $clients[$id]['out'] = substr($clients[$id]['out'], $written);
        $this->record('ACK_SENT', ['imei' => $clients[$id]['imei'], 'bytes' => $written]);
    }

    private function record(string $event, array $fields): void
    {
        $fields = HardwareTelemetry::redact($fields);
        $time = now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s T');
        $text = $this->option('format') === 'json'
            ? json_encode(['time' => $time, 'event' => $event, ...$fields], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : app(GpsDiagnosticFormatter::class)->format($time, $event, $fields);
        $this->output->writeln($text, OutputInterface::OUTPUT_RAW);
    }
}
