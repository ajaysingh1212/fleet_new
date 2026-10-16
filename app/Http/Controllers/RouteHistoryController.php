<?php

namespace App\Http\Controllers;

use App\Models\GpsLocation;
use App\Models\TrackingRelation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RouteHistoryController extends Controller
{
    private function query(Request $request, User $user): array
    {
        abort_unless(TrackingRelation::where('tracker_user_id', $request->user()->id)
            ->where('tracked_user_id', $user->id)->usableForTracking()->exists(), 403);
        $timezone = 'Asia/Kolkata';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $earliest = $today->subDays(29)->toDateString();
        $latest = $today->toDateString();
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$earliest, 'before_or_equal:'.$latest],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$earliest, 'before_or_equal:'.$latest],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? $latest, $timezone)->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? $data['from'] ?? $latest, $timezone)->endOfDay();
        abort_if($to->lt($from), 422, 'End date must be on or after start date.');
        $query = GpsLocation::where('user_id', $user->id)
            ->whereBetween('recorded_at', [$from->utc(), $to->utc()])
            ->orderBy('recorded_at')->orderBy('id');
        return [$query, ['user_id' => $user->id, 'name' => $user->name,
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'min_date' => $earliest, 'max_date' => $latest, 'timezone' => $timezone]];
    }

    public function index(Request $request, User $user)
    {
        [$query, $range] = $this->query($request, $user);
        $page = $query->paginate(500);
        return response()->json(['data' => $page->getCollection()->map(fn ($point) => [
            'latitude' => (float) $point->latitude, 'longitude' => (float) $point->longitude,
            'recorded_at' => $point->recorded_at->toIso8601String(),
            'speed' => $point->speed !== null ? (float) $point->speed : null,
            'tracking_session_id' => $point->tracking_session_id,
        ]), 'meta' => [...$range, 'total' => $page->total(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]])
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, User $user)
    {
        [$query, $range] = $this->query($request, $user);
        return response()->streamDownload(function () use ($query, $range) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Recorded At UTC', 'Recorded At IST', 'Latitude', 'Longitude',
                'Speed (m/s)', 'Battery (%)', 'Tracking Session', 'Source']);
            foreach ($query->cursor() as $point) {
                fputcsv($output, [$point->recorded_at->utc()->format('Y-m-d H:i:s'),
                    $point->recorded_at->timezone($range['timezone'])->format('Y-m-d H:i:s'),
                    $point->latitude, $point->longitude, $point->speed,
                    $point->battery_level, $point->tracking_session_id, $point->source_type->value]);
            }
            fclose($output);
        }, 'route-'.$user->id.'-'.$range['from'].'-'.$range['to'].'.csv',
            ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
    }
}
