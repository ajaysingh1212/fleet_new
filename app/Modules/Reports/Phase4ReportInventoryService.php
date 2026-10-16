<?php

namespace App\Modules\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Phase4ReportInventoryService
{
    public function modules(): array
    {
        return [
            ['key' => 'attendance_logs', 'label' => 'Attendance Logs', 'description' => 'Present, absent, late, early exit, work hours'],
            ['key' => 'movement_statistics', 'label' => 'Movement Statistics', 'description' => 'Distance, speed, idle, moving, accuracy'],
            ['key' => 'route_history', 'label' => 'Route History', 'description' => 'Historical route ranges and route summaries'],
            ['key' => 'route_replay_sessions', 'label' => 'Replay Sessions', 'description' => 'Replay windows, points, distance, playback stats'],
            ['key' => 'heatmaps', 'label' => 'Heatmaps', 'description' => 'High and low activity zones'],
            ['key' => 'analytics_cache', 'label' => 'Analytics Cache', 'description' => 'Cached dashboard and BI payloads'],
            ['key' => 'employee_reports', 'label' => 'Employee Reports', 'description' => 'Generated reports and export artifacts'],
            ['key' => 'trip_summary', 'label' => 'Trip Summary', 'description' => 'Trips, duration, distance, max speed'],
            ['key' => 'timeline_events', 'label' => 'Timeline Events', 'description' => 'Movement, diagnostic, geofence and communication timeline'],
            ['key' => 'visit_logs', 'label' => 'Visit Logs', 'description' => 'Geofence/customer visit duration and status'],
        ];
    }

    public function summary(): array
    {
        return collect($this->modules())->map(function (array $module) {
            $table = $module['key'];

            return [
                ...$module,
                'records' => Schema::hasTable($table) ? DB::table($table)->count() : 0,
                'latest_at' => Schema::hasTable($table) && Schema::hasColumn($table, 'created_at')
                    ? DB::table($table)->max('created_at')
                    : null,
                'formats' => ['PDF', 'Excel', 'CSV', 'Print'],
            ];
        })->all();
    }
}
