<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\MovementAnalyticsService;
use App\Modules\History\LocationHistoryService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request, LocationHistoryService $history, MovementAnalyticsService $analytics, MonitoringReportAccessService $access): JsonResponse
    {
        $locations = $history->query($access->filtersFor($request))->reorder('recorded_at')->get();

        return response()->json(['data' => $analytics->summarize($locations)]);
    }
}
