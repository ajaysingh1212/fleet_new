<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GeofenceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGeofenceAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateGeofenceAssignmentRequest;
use App\Http\Resources\GeofenceAssignmentResource;
use App\Http\Resources\GeofenceAssignmentRunResource;
use App\Models\Geofence;
use App\Models\GeofenceAssignment;
use App\Models\User;
use App\Services\RelationshipAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeofenceAssignmentController extends Controller
{
    private const EAGER_LOAD = ['geofence.points', 'user', 'assignedByUser'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GeofenceAssignment::class);

        $query = GeofenceAssignment::query()->with(self::EAGER_LOAD)
            ->when(! $request->user()->can('manage geofences'), fn ($query) => $query->where('assigned_by', $request->user()->id));

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($geofenceUuid = $request->string('geofence_uuid')->toString()) {
            $query->whereHas('geofence', fn ($q) => $q->where('uuid', $geofenceUuid));
        }

        if ($routeLabel = $request->string('route_label')->toString()) {
            $query->where('route_label', $routeLabel);
        }

        $assignments = $query->orderBy('route_label')->orderBy('sequence')->paginate(50);

        return GeofenceAssignmentResource::collection($assignments);
    }

    public function store(StoreGeofenceAssignmentRequest $request): GeofenceAssignmentResource
    {
        $this->authorize('create', GeofenceAssignment::class);

        $geofence = Geofence::where('uuid', $request->validated('geofence_uuid'))->firstOrFail();
        $target = User::query()->findOrFail($request->integer('user_id'));

        abort_unless(
            $request->user()->can('manage geofences')
            || ((int) $geofence->created_by === (int) $request->user()->id
                && app(RelationshipAuthorizationService::class)->canTrackUser($request->user(), $target)),
            403,
        );

        $assignment = GeofenceAssignment::create([
            ...$request->safe()->except(['geofence_uuid', 'alert_on_exit', 'alert_on_missed']),
            'geofence_id' => $geofence->id,
            'assigned_by' => $request->user()->id,
            'alert_on_exit' => $request->boolean('alert_on_exit', true),
            'alert_on_missed' => $request->boolean('alert_on_missed', true),
            'status' => GeofenceAssignmentStatus::Active,
        ]);

        return new GeofenceAssignmentResource($assignment->load(self::EAGER_LOAD));
    }

    public function show(GeofenceAssignment $geofenceAssignment): GeofenceAssignmentResource
    {
        $this->authorize('view', $geofenceAssignment);

        return new GeofenceAssignmentResource($geofenceAssignment->load(self::EAGER_LOAD));
    }

    public function update(UpdateGeofenceAssignmentRequest $request, GeofenceAssignment $geofenceAssignment): GeofenceAssignmentResource
    {
        $this->authorize('update', $geofenceAssignment);

        abort_unless($request->user()->can('manage geofences')
            || app(RelationshipAuthorizationService::class)->canTrackUser($request->user(), $geofenceAssignment->user), 403);

        $geofenceAssignment->update($request->validated());

        return new GeofenceAssignmentResource($geofenceAssignment->load(self::EAGER_LOAD));
    }

    public function destroy(GeofenceAssignment $geofenceAssignment): JsonResponse
    {
        $this->authorize('delete', $geofenceAssignment);

        $geofenceAssignment->delete();

        return response()->json(['success' => true]);
    }

    public function runs(GeofenceAssignment $geofenceAssignment): AnonymousResourceCollection
    {
        $this->authorize('view', $geofenceAssignment);

        $runs = $geofenceAssignment->runs()->orderByDesc('run_date')->paginate(30);

        return GeofenceAssignmentRunResource::collection($runs);
    }
}
