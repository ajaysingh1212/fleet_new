<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Modules\Statistics\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function __invoke(Request $request, StatisticsService $statistics, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json(['data' => $statistics->averages($access->filtersFor($request))]);
    }
}
