<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Modules\Replay\RouteReplayService;
use App\Modules\Analytics\VehicleMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplayController extends Controller
{
    public function __invoke(Request $request, RouteReplayService $replay, MonitoringReportAccessService $access, VehicleMonitoringService $vehicles): JsonResponse
    {
        [$fleet, $selected] = $vehicles->filters($request);
        if ($request->filled('vehicle_id')) return response()->json(['data' => $vehicles->replay($selected, $request)]);
        if ($fleet->isNotEmpty()) return response()->json(['data' => ['vehicles' => $fleet->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'registration_number' => $v->registration_number])->values(), 'points' => [], 'statistics' => null]]);
        return response()->json(['data' => $replay->build($access->filtersFor($request))]);
    }
}
