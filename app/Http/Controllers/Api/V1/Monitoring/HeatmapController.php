<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Heatmap\HeatmapService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeatmapController extends Controller
{
    public function __invoke(Request $request, HeatmapService $heatmap, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json(['data' => $heatmap->generate($access->filtersFor($request))]);
    }
}
