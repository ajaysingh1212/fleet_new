<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Modules\Analytics\VehicleMonitoringService;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function dashboard(VehicleMonitoringService $vehicles): View
    {
        return view('admin.monitoring.dashboard', ['vehicles' => $vehicles->visibleVehicles(auth()->user())->get(['id', 'name', 'registration_number'])]);
    }

    public function history(): View
    {
        return view('admin.monitoring.history');
    }

    public function replay(VehicleMonitoringService $vehicles): View
    {
        return view('admin.monitoring.replay', ['vehicles' => $vehicles->visibleVehicles(auth()->user())->get(['id', 'name', 'registration_number'])]);
    }

    public function reports(MonitoringReportAccessService $access): View
    {
        return view('admin.monitoring.reports', [
            'reportUsers' => $access->visibleUsers(auth()->user()),
        ]);
    }
}
