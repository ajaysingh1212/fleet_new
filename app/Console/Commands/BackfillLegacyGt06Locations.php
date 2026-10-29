<?php

namespace App\Console\Commands;

use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Services\Gps\Gateway\Protocols\ConcoxX1;
use App\Services\Gps\Gateway\Protocols\Gt06;
use App\Services\Gps\Gateway\Protocols\Pt06;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLegacyGt06Locations extends Command
{
    protected $signature = 'gps:backfill-legacy-location {imei : Registered PT06 IMEI}';

    protected $description = 'Recover stored legacy GT06 0x12 GPS payloads previously recorded as unsupported PT06 events';

    public function handle(): int
    {
        $device = GpsDevice::where('identifier', $this->argument('imei'))->first();
        if (! $device || $device->protocol !== 'pt06' || $device->protocol_version !== Pt06::VERSION) {
            $this->error('Active PT06 device not found.');

            return self::FAILURE;
        }

        $events = DB::table('gps_device_events')->where('gps_device_id', $device->id)->where('type', 'unsupported')
            ->orderBy('id')->get(['packet_hash', 'telemetry', 'received_at']);
        $recovered = 0;
        $skipped = 0;
        foreach ($events as $event) {
            $telemetry = json_decode($event->telemetry, true);
            $hex = $telemetry['payload_hex'] ?? null;
            if (! is_string($hex) || ! preg_match('/^[0-9A-F]{52}$/D', $hex)) {
                $skipped++;

                continue;
            }
            $payload = hex2bin($hex);
            $body = "\x12".$payload."\x00\x00";
            $checked = chr(strlen($body) + 2).$body;
            $frame = "\x78\x78".$checked.pack('n', ConcoxX1::crc($checked))."\r\n";
            try {
                $packet = (new Gt06)->decode($frame, true);
                (new ProcessHardwarePacket($device->id, $device->identifier, 'legacy-backfill-'.$event->packet_hash,
                    $event->received_at, 'backfill', 'pt06', Pt06::VERSION, 'location', $event->packet_hash,
                    array_map(fn ($location) => $location->toArray(), $packet->locations),
                    ['wire_format' => 'legacy_gt06_0x12', 'recovered_from_event' => true], $event->received_at,
                    $device->vehicle_id, true))->handle();
                $recovered++;
            } catch (\Throwable) {
                $skipped++;
            }
        }
        $this->info("Recovered {$recovered} legacy location packet(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
