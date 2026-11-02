<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\MovementAnalyticsService;
use App\Modules\Analytics\VehicleMonitoringService;
use App\Modules\History\LocationHistoryService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request, LocationHistoryService $history, MovementAnalyticsService $analytics, MonitoringReportAccessService $access, VehicleMonitoringService $vehicles): JsonResponse
    {
        [$fleet, $selected] = $vehicles->filters($request);
        if ($selected) return response()->json(['data' => $vehicles->summary($selected, $request)]);
        if ($fleet->isNotEmpty()) return response()->json(['data' => ['vehicles' => $fleet->map(fn ($v) => $vehicles->summary($v, $request))->values()]]);
        $locations = $history->query($access->filtersFor($request))->reorder('recorded_at')->get();

        return response()->json(['data' => $analytics->summarize($locations)]);
    }
}
