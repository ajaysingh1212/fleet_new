<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Modules\Replay\RouteReplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplayController extends Controller
{
    public function __invoke(Request $request, RouteReplayService $replay, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json(['data' => $replay->build($access->filtersFor($request))]);
    }
}
