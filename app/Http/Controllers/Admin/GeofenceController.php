<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GpsDevice;
use Illuminate\View\View;

class GeofenceController extends Controller
{
    public function index(): View
    {
        return view('admin.geofences.index', ['hardwareMode' => auth()->user()->can('viewAny', GpsDevice::class)]);
    }
}
