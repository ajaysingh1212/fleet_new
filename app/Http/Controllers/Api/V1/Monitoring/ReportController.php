<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\SmartAttendanceService;
use App\Modules\Dashboard\MonitoringDashboardService;
use App\Modules\History\LocationHistoryService;
use App\Modules\Reports\Phase4ReportInventoryService;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Modules\Reports\ReportDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request, LocationHistoryService $history, MonitoringDashboardService $dashboard, SmartAttendanceService $attendance, Phase4ReportInventoryService $inventory, MonitoringReportAccessService $access, ReportDetailService $detail): JsonResponse
    {
        $filters = $access->filtersFor($request);

        return response()->json([
            'data' => [
                'type' => $request->string('type', 'employee_daily')->toString(),
                'filters' => $filters,
                'summary' => $dashboard->overview($filters),
                'attendance' => $attendance->summarize($filters),
                'device_diagnostics' => $detail->deviceDiagnostics($filters),
                'geofence_activity' => $detail->geofenceActivity($filters),
                'history_sample' => array_slice($history->points($filters), 0, 100),
                'metadata' => [
                    'generated_at' => now()->toIso8601String(),
                    'formats' => ['pdf', 'excel', 'csv', 'print'],
                    'signature_placeholder' => true,
                ],
                'phase4_tables' => $inventory->summary(),
            ],
        ]);
    }
}
