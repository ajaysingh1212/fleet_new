<?php

namespace Database\Seeders;

use App\Jobs\ProcessHardwarePacket;
use App\Models\GpsDevice;
use App\Models\Vehicle;
use App\Services\Gps\Gateway\Protocols\Pt06;
use App\Services\Gps\HardwareRegistrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit real fleet registrations; safe to rerun without deleting any records. */
class HardwareFleetSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['864540081540460', 'BR52C6695', 'bike', 'BT50', '89915426034022540908'],
            ['861128068689493', 'BR28Z4291', 'car', 'PT06-compatible tracker', null],
        ] as [$imei, $registration, $type, $model, $iccid]) {
            DB::transaction(function () use ($imei, $registration, $type, $model, $iccid) {
                $vehicle = Vehicle::firstOrNew(['registration_number' => $registration]);
                $vehicle->fill(['name' => $registration.' '.ucfirst($type), 'status' => 'active',
                    'details' => [...($vehicle->details ?? []), 'type' => $type, 'driver_name' => 'Ajay Kumar']])->save();
                $device = GpsDevice::where('identifier', $imei)->first();
                $details = [...($device?->details ?? []), 'network_provider' => 'Airtel'];
                if ($iccid) {
                    $details['sim_iccid'] = $iccid;
                }
                $details['notes'] = $device?->details['notes'] ?? 'PT06 V02 protocol verified from device packets. Manufacturer and SIM phone number not confirmed.';
                $saved = app(HardwareRegistrationService::class)->save([
                    'name' => $registration.' GPS', 'identifier' => $imei, 'imei' => $imei,
                    'model' => $device?->model ?: $model, 'manufacturer' => $device?->manufacturer,
                    'protocol' => 'pt06', 'protocol_version' => Pt06::VERSION, 'status' => 'active',
                    'vehicle_id' => $vehicle->id, 'details' => $details,
                    'configuration' => [...($device?->configuration ?? []), 'transport' => 'tcp', 'server_port' => 9000,
                        'timezone' => 'UTC'],
                ], $device);
                if ($imei === '861128068689493' && ! $saved->latest_location_id) {
                    // Actual user-provided capture, not a demo position. Retain its
                    // original UTC timestamps; seeding must not pretend it is live.
                    $frame = hex2bin('787822221A0A11062A2FC002BF63DA0922A7F0001400019534064400E901000401000B74B20D0A');
                    $packet = (new Pt06)->decode($frame, true);
                    (new ProcessHardwarePacket($saved->id, $imei, (string) Str::uuid(),
                        '2026-10-17 06:43:11', '106.222.246.98:56980', 'pt06', Pt06::VERSION,
                        'location', hash('sha256', $frame), array_map(fn ($location) => [...$location->toArray(),
                            'telemetry' => [...$location->telemetry, 'source' => 'User terminal capture 2026-10-17; imported historical fix']], $packet->locations),
                        [], '2026-10-17 06:43:11', $saved->vehicle_id, false))->handle();
                }
            });
        }
    }
}
