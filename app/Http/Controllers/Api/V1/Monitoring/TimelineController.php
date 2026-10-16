<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Modules\Timeline\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function __invoke(Request $request, TimelineService $timeline, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json(['data' => $timeline->events($access->filtersFor($request))]);
    }
}
