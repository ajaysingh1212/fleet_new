<?php

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\DeviceStatus;
use App\Models\GeofenceAssignment;
use App\Models\GeofenceAssignmentRun;
use App\Models\GpsLocation;
use App\Models\User;
use App\Modules\Reports\MonitoringReportAccessService;
use App\Services\Geofence\GeofenceGeometryService;
use App\Services\UserPresenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LiveMapController extends Controller
{
    // Keep in sync with GpsLocationUpdated::MOVING_SPEED_MPS / StoreGpsLocationJob::STOP_SPEED_THRESHOLD.
    private const MOVING_SPEED_MPS = 0.5;

    public function __construct(
        protected UserPresenceService $presenceService,
        protected MonitoringReportAccessService $access,
        protected GeofenceGeometryService $geofenceGeometry,
    ) {}

    public function index(): View
    {
        $user = auth()->user();

        $selfTrackingEnabled = (bool) $user->trackingPreference?->self_tracking_enabled;
        $hasOwnedValidLicense = $user->userLicenses()
            ->where('payment_status', PaymentStatus::Paid)
            ->whereIn('status', [LicenseStatus::Pending, LicenseStatus::Active])
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
            ->exists();

        $visibleUsers = $this->access->visibleUsers($user);
        $historyUserIds = \App\Models\TrackingRelation::where('tracker_user_id', $user->id)
            ->usableForTracking()->pluck('tracked_user_id')->all();

        if ($selfTrackingEnabled) {
            $visibleUsers->push($user);
        }

        $people = $visibleUsers->unique('id')
            ->map(function (User $person) use ($user, $historyUserIds) {
                $location = GpsLocation::where('user_id', $person->id)->latest('recorded_at')->first();
                $live = app(\App\Services\Gps\LiveLocationService::class)->latest($person->id);
                if ($live && (! $location || $live->recorded_at->gt($location->recorded_at))) {
                    $location = $live;
                }
                $deviceStatus = DeviceStatus::query()
                    ->whereHas('deviceSession', fn (Builder $query) => $query->where('user_id', $person->id))
                    ->latest('last_ping_at')
                    ->first();
                $speed = $location?->speed !== null ? (float) $location->speed : null;
                $newerStatus = $deviceStatus?->last_ping_at
                    && (! $location || $deviceStatus->last_ping_at->gte($location->recorded_at));
                $isOnline = $this->presenceService->isOnline($person->id);
                $isSelf = $person->id === $user->id;

                return [
                    'id' => $person->id,
                    'canRouteHistory' => in_array($person->id, $historyUserIds, true),
                    'name' => $person->name,
                    'isSelf' => $isSelf,
                    'trackedBy' => $isSelf ? $this->trackedByNames($person) : [],
                    'lat' => $location ? (float) $location->latitude : null,
                    'lng' => $location ? (float) $location->longitude : null,
                    'speed' => $speed,
                    'bearing' => $location?->bearing !== null ? (float) $location->bearing : null,
                    'battery' => $newerStatus ? $deviceStatus?->battery_level : ($location?->battery_level ?? $deviceStatus?->battery_level),
                    'gpsEnabled' => $newerStatus ? $deviceStatus?->is_gps_enabled : ($live ? true : $deviceStatus?->is_gps_enabled),
                    'internetEnabled' => $deviceStatus?->is_internet_enabled,
                    'networkType' => $newerStatus ? $deviceStatus?->network_type : ($location?->network_type ?? $deviceStatus?->network_type),
                    'movementStatus' => $speed !== null && $speed > self::MOVING_SPEED_MPS ? 'moving' : 'idle',
                    'isOnline' => $isOnline,
                    'lastSeen' => $location?->recorded_at?->toIso8601String(),
                    'lastActivity' => $this->presenceService->lastActivityAt($person->id),
                    'geofences' => $this->geofencesFor($person, $location),
                ];
            })
            ->values();

        // Keep expired relationships visible for renewal, without granting GPS access.
        $expiredRelations = $user->trackedUsers()->where('status', UserStatus::Active)
            ->with(['trackedUser', 'userLicense.plan'])->get();
        foreach ($expiredRelations as $relation) {
            $license = $relation->userLicense;
            if (! $relation->trackedUser || ! $license?->isExpired()
                || $people->contains('id', $relation->tracked_user_id)) continue;
            $people->push([
                'id' => $relation->tracked_user_id, 'name' => $relation->trackedUser->name,
                'isSelf' => false, 'trackedBy' => [], 'canRouteHistory' => false,
                'licenseExpired' => true, 'canReport' => false,
                'renewUrl' => route($license->plan?->is_free ? 'my-licenses.plans' : 'my-licenses.index'),
                'lat' => null, 'lng' => null, 'speed' => null, 'bearing' => null,
                'battery' => null, 'gpsEnabled' => null, 'internetEnabled' => null,
                'networkType' => null, 'movementStatus' => 'idle', 'isOnline' => false,
                'lastSeen' => null, 'lastActivity' => null, 'geofences' => [],
            ]);
        }

        $markerPreferences = \App\Models\MapMarkerPreference::where('viewer_user_id', $user->id)
            ->get()->keyBy('tracked_user_id');
        $people = $people->map(function ($person) use ($markerPreferences) {
            $person['marker'] = $markerPreferences->get($person['id'])?->markerData()
                ?? ['kind' => 'person', 'imageUrl' => null];
            return $person;
        });

        return view('live-map.index', [
            'people' => $people,
            'selfTrackingEnabled' => $selfTrackingEnabled,
            'hasOwnedValidLicense' => $hasOwnedValidLicense,
            'trackedBy' => $this->trackedByDetails($user),
            'hasAvailableLicense' => $user->userLicenses()
                ->where('payment_status', PaymentStatus::Paid)
                ->whereIn('status', [LicenseStatus::Pending, LicenseStatus::Active])
                ->whereNull('assigned_tracked_user_id')
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
                ->exists(),
        ]);
    }

    public function toggleSelfTracking(): RedirectResponse
    {
        $user = auth()->user();
        $preference = $user->trackingPreference()->firstOrCreate([], [
            'distance_filter_meters' => 25,
            'tracking_source' => 'automatic',
        ]);

        if (! $preference->self_tracking_enabled) {
            $hasLicense = $user->userLicenses()
                ->where('payment_status', PaymentStatus::Paid)
                ->whereIn('status', [LicenseStatus::Pending, LicenseStatus::Active])
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
                ->exists();

            if (! $hasLicense) {
                return back()->withErrors(['self_tracking' => 'A valid license is required to enable self tracking.']);
            }
        }

        $preference->update(['self_tracking_enabled' => ! $preference->self_tracking_enabled]);

        return back()->with('status', $preference->self_tracking_enabled
            ? 'Self tracking enabled. Allow browser location access to save your route.'
            : 'Self tracking stopped. No new browser location points will be saved from this map.');
    }

    public function snapshot(): \Illuminate\Http\JsonResponse
    {
        // Reuse the same license/relationship scope as the initial page.
        return response()->json(['people' => $this->index()->getData()['people']])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<int, string>
     */
    private function trackedByNames(User $person): array
    {
        return $person->trackerRelations()
            ->where('status', UserStatus::Active)
            ->whereHas('userLicense', fn ($query) => $query->usable())
            ->with('trackerUser:id,name')
            ->get()
            ->pluck('trackerUser.name')
            ->filter()
            ->values()
            ->all();
    }

    private function trackedByDetails(User $person): Collection
    {
        return $person->trackerRelations()
            ->where('status', UserStatus::Active)
            ->with(['trackerUser', 'userLicense'])
            ->get()
            ->map(fn ($relation) => [
                'relation' => $relation,
                'user' => $relation->trackerUser,
                'has_active_license' => (bool) $relation->userLicense?->isUsable(),
                'license_expired' => (bool) $relation->userLicense?->isExpired(),
                'license' => $relation->userLicense,
                'reverse_exists' => $person->trackedUsers()
                    ->where('tracked_user_id', $relation->tracker_user_id)
                    ->exists(),
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geofencesFor(User $person, ?GpsLocation $location): array
    {
        return GeofenceAssignment::active()
            ->where('user_id', $person->id)
            ->with(['geofence.points', 'runs' => fn ($query) => $query->whereDate('run_date', today())])
            ->orderBy('route_label')
            ->orderBy('sequence')
            ->get()
            ->filter(fn (GeofenceAssignment $assignment) => $assignment->geofence !== null)
            ->map(function (GeofenceAssignment $assignment) use ($location) {
                /** @var GeofenceAssignmentRun|null $run */
                $run = $assignment->runs->first();

                return [
                    'assignment_uuid' => $assignment->uuid,
                    'uuid' => $assignment->geofence->uuid,
                    'name' => $assignment->geofence->name,
                    'type' => $assignment->geofence->type->value,
                    'color' => $assignment->geofence->color,
                    'route_label' => $assignment->route_label,
                    'sequence' => $assignment->sequence,
                    'schedule_type' => $assignment->schedule_type->value,
                    'schedule_days' => $assignment->schedule_days,
                    'schedule_date' => $assignment->schedule_date?->toDateString(),
                    'window_start' => $assignment->window_start,
                    'window_end' => $assignment->window_end,
                    'compliance_status' => $run?->status->value ?? 'pending',
                    'visited_at' => $run?->visited_at?->toIso8601String(),
                    'notified_at' => $run?->notified_at?->toIso8601String(),
                    'center_lat' => $assignment->geofence->center_lat,
                    'center_lng' => $assignment->geofence->center_lng,
                    'radius_meters' => $assignment->geofence->radius_meters,
                    'points' => $assignment->geofence->points->map(fn ($point) => [
                        'latitude' => $point->latitude,
                        'longitude' => $point->longitude,
                    ]),
                    'is_outside' => $location
                        ? ! $this->geofenceGeometry->containsPoint(
                            $assignment->geofence,
                            (float) $location->latitude,
                            (float) $location->longitude,
                        )
                        : false,
                ];
            })
            ->values()
            ->all();
    }
}
