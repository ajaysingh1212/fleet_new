<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\MonitoringDashboardService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __invoke(Request $request, MonitoringDashboardService $dashboard, MonitoringReportAccessService $access): Response
    {
        $payload = $dashboard->overview($access->filtersFor($request));
        $format = $request->string('format', 'csv')->toString();

        if ($format === 'csv') {
            $rows = ["metric,value"];
            foreach ($payload['cards'] as $metric => $value) {
                $rows[] = $metric.','.str_replace(',', ' ', (string) $value);
            }

            return response(implode("\n", $rows), 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="monitoring-export.csv"',
            ]);
        }

        return response(json_encode($payload, JSON_PRETTY_PRINT), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="monitoring-export.json"',
        ]);
    }
}
