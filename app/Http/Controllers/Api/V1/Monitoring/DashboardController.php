<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\MonitoringDashboardService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, MonitoringDashboardService $dashboard, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json(['data' => $dashboard->overview($access->filtersFor($request))]);
    }
}
