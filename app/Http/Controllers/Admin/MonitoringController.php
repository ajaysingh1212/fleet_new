<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.monitoring.dashboard');
    }

    public function history(): View
    {
        return view('admin.monitoring.history');
    }

    public function replay(): View
    {
        return view('admin.monitoring.replay');
    }

    public function reports(MonitoringReportAccessService $access): View
    {
        return view('admin.monitoring.reports', [
            'reportUsers' => $access->visibleUsers(auth()->user()),
        ]);
    }
}
