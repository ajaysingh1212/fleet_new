<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\History\LocationHistoryService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function __invoke(Request $request, LocationHistoryService $history, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json([
            'data' => $history->points($access->filtersFor($request)),
        ]);
    }
}
