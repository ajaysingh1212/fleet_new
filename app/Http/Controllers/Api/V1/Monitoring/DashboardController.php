<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\MonitoringDashboardService;
use App\Modules\Analytics\VehicleMonitoringService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MonitoringDashboardService $dashboard, MonitoringReportAccessService $access, VehicleMonitoringService $vehicles): JsonResponse
    {
        [$fleet, $selected] = $vehicles->filters($request);
        if ($selected) return response()->json(['data' => ['selected' => $vehicles->summary($selected, $request), 'vehicles' => $fleet->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'registration_number' => $v->registration_number])->values()]]);
        if ($fleet->isNotEmpty()) return response()->json(['data' => ['vehicles' => $fleet->map(fn ($v) => $vehicles->summary($v, $request))->values()]]);
        return response()->json(['data' => $dashboard->overview($access->filtersFor($request))]);
    }
}
