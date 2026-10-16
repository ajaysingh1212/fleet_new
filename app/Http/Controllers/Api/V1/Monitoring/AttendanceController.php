<?php

namespace App\Http\Controllers\Api\V1\Monitoring;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\SmartAttendanceService;
use App\Modules\Reports\MonitoringReportAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __invoke(Request $request, SmartAttendanceService $attendance, MonitoringReportAccessService $access): JsonResponse
    {
        return response()->json(['data' => $attendance->summarize($access->filtersFor($request))]);
    }
}
