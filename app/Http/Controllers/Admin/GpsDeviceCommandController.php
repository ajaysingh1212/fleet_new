<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GpsDevice;
use App\Services\Gps\DeviceCommands;
use App\Services\Gps\Pt06CommandCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GpsDeviceCommandController extends Controller
{
    public function index(GpsDevice $gpsDevice, DeviceCommands $commands, Pt06CommandCatalog $catalog)
    {
        $this->authorize('manage', GpsDevice::class);
        $commands->expire($gpsDevice->id);

        return response()->json([
            'device_id' => $gpsDevice->id, 'imei' => $gpsDevice->identifier,
            'label' => $gpsDevice->vehicle?->registration_number ?? $gpsDevice->name,
            'supported' => $gpsDevice->protocol === 'pt06',
            'connected' => $gpsDevice->status === 'active' && $commands->session($gpsDevice->id) !== null,
            'catalog' => $catalog->entries(),
            'commands' => DB::table('gps_device_commands')->where('gps_device_id', $gpsDevice->id)
                ->orderByDesc('id')->limit(20)->get(['id', 'command', 'status', 'response', 'error', 'created_at', 'sent_at', 'replied_at']),
        ]);
    }

    public function store(Request $request, GpsDevice $gpsDevice, DeviceCommands $commands)
    {
        $this->authorize('manage', GpsDevice::class);
        $data = $request->validate(['command' => 'required|string|max:180', 'request_id' => 'required|uuid', 'confirmed' => 'sometimes|boolean']);
        $result = $commands->request($gpsDevice, $request->user()->id, $data['command'], $data['request_id'], $data['confirmed'] ?? false);

        return response()->json(['command' => collect($result)->only(['id', 'command', 'status', 'response', 'error'])->all()], 202);
    }
}
