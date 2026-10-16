<?php

namespace App\Http\Controllers\User;

use App\Events\FieldTaskUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\FieldTaskRequest;
use App\Models\DiagnosticLog;
use App\Models\FieldTask;
use App\Models\FieldTaskActivity;
use App\Models\GpsLocation;
use App\Models\TrackingRelation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldTaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = FieldTask::query()
            ->where(fn ($q) => $q->where('creator_id', $user->id)->orWhere('assignee_id', $user->id))
            ->with(['creator:id,name', 'assignee:id,name', 'stops'])
            ->latest('starts_at');

        $this->applyDateFilter($query, $request);
        if ($request->filled('status')) $query->where('status', $request->string('status'));

        return view('user.field-tasks.index', [
            'tasks' => $query->paginate(15)->withQueryString(),
            'assignedCount' => FieldTask::where('assignee_id', $user->id)->whereIn('status', ['assigned', 'in_progress'])->count(),
            'canCreate' => TrackingRelation::usableForTracking()->where('tracker_user_id', $user->id)->exists(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('user.field-tasks.form', [
            'task' => new FieldTask(['priority' => 'normal', 'schedule_type' => 'once', 'arrival_radius_meters' => 100]),
            'assignees' => $this->assignees($request->user()),
            'method' => 'POST',
            'action' => route('tasks.store'),
        ]);
    }

    public function store(FieldTaskRequest $request): RedirectResponse
    {
        $relation = $this->relationFor($request->user(), $request->integer('assignee_id'));
        $task = DB::transaction(function () use ($request, $relation) {
            $data = $request->safe()->except('stops');
            $task = FieldTask::create($data + [
                'creator_id' => $request->user()->id,
                'tracking_relation_id' => $relation->id,
                'status' => 'assigned',
            ]);
            $this->syncStops($task, $request->validated('stops'));
            FieldTaskActivity::create([
                'field_task_id' => $task->id, 'user_id' => $request->user()->id,
                'event_type' => 'created', 'occurred_at' => now(),
            ]);
            return $task;
        });

        broadcast(new FieldTaskUpdated($task->load('stops'), 'created'));
        return redirect()->route('tasks.show', $task)->with('status', 'Task assigned in realtime.');
    }

    public function show(Request $request, FieldTask $task): View
    {
        $this->authorizeTask($request->user(), $task);
        $task->load(['creator:id,name', 'assignee:id,name', 'stops', 'activity.user:id,name']);
        $latestLocation = GpsLocation::where('user_id', $task->assignee_id)->latest('recorded_at')->first();

        return view('user.field-tasks.show', compact('task', 'latestLocation'));
    }

    public function edit(Request $request, FieldTask $task): View
    {
        abort_unless($task->creator_id === $request->user()->id && $task->status === 'assigned', 403);
        $task->load('stops');
        return view('user.field-tasks.form', [
            'task' => $task, 'assignees' => $this->assignees($request->user()),
            'method' => 'PUT', 'action' => route('tasks.update', $task),
        ]);
    }

    public function update(FieldTaskRequest $request, FieldTask $task): RedirectResponse
    {
        abort_unless($task->creator_id === $request->user()->id && $task->status === 'assigned', 403);
        $relation = $this->relationFor($request->user(), $request->integer('assignee_id'));
        DB::transaction(function () use ($request, $task, $relation) {
            $task->update($request->safe()->except('stops') + ['tracking_relation_id' => $relation->id]);
            $task->stops()->delete();
            $this->syncStops($task, $request->validated('stops'));
            FieldTaskActivity::create([
                'field_task_id' => $task->id, 'user_id' => $request->user()->id,
                'event_type' => 'updated', 'occurred_at' => now(),
            ]);
        });
        broadcast(new FieldTaskUpdated($task->refresh()->load('stops'), 'updated'));
        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    public function destroy(Request $request, FieldTask $task): RedirectResponse
    {
        abort_unless($task->creator_id === $request->user()->id, 403);
        $task->delete();
        broadcast(new FieldTaskUpdated($task->load('stops'), 'deleted'));
        return redirect()->route('tasks.index')->with('status', 'Task archived.');
    }

    public function report(Request $request, FieldTask $task): JsonResponse
    {
        $this->authorizeTask($request->user(), $task);
        $from = $task->started_at ?? $task->starts_at;
        $to = $task->completed_at ?? now();
        $locations = GpsLocation::where('user_id', $task->assignee_id)->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')->limit(5000)->get();
        $diagnostics = DiagnosticLog::where('user_id', $task->assignee_id)->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')->get();

        return response()->json(['data' => [
            'task' => $task->load('stops'),
            'route' => $locations->map(fn ($point) => [
                'lat' => (float) $point->latitude, 'lng' => (float) $point->longitude,
                'speed' => $point->speed !== null ? (float) $point->speed : null,
                'battery' => $point->battery_level, 'network' => $point->network_type,
                'recorded_at' => $point->recorded_at->toIso8601String(),
            ]),
            'diagnostics' => $diagnostics->map(fn ($log) => [
                'type' => $log->event_type->value, 'reason' => $log->reason,
                'battery' => $log->battery_level, 'network' => $log->network_type,
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ]),
        ]]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $query = FieldTask::where(fn ($q) => $q->where('creator_id', $user->id)->orWhere('assignee_id', $user->id))
            ->with(['creator:id,name', 'assignee:id,name', 'stops']);
        $this->applyDateFilter($query, $request);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Task', 'Reference', 'Creator', 'Assignee', 'Schedule', 'Status', 'Stops', 'Completed', 'Starts', 'Due']);
            $query->orderBy('starts_at')->chunk(200, function ($tasks) use ($stream) {
                foreach ($tasks as $task) fputcsv($stream, [
                    $task->title, $task->reference_code, $task->creator->name, $task->assignee->name,
                    $task->schedule_type, $task->status, $task->stops->count(),
                    $task->stops->where('status', 'completed')->count(), $task->starts_at, $task->due_at,
                ]);
            });
            fclose($stream);
        }, 'task-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportTask(Request $request, FieldTask $task): StreamedResponse
    {
        $this->authorizeTask($request->user(), $task);
        $from = $task->started_at ?? $task->starts_at;
        $to = $task->completed_at ?? now();

        return response()->streamDownload(function () use ($task, $from, $to) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['TASK REPORT', $task->title]);
            fputcsv($stream, ['Status', $task->status, 'Schedule', $task->schedule_type, 'Started', $from, 'Completed', $task->completed_at]);
            fputcsv($stream, []);
            fputcsv($stream, ['STOPS']);
            fputcsv($stream, ['Order', 'Stop', 'Expected', 'Arrived', 'Radius (m)', 'Arrival distance (m)', 'Status']);
            foreach ($task->stops()->orderBy('sequence')->get() as $stop) {
                fputcsv($stream, [$stop->sequence, $stop->title, $stop->expected_at, $stop->arrived_at, $stop->radius_meters, $stop->arrival_distance_meters, $stop->status]);
            }
            fputcsv($stream, []);
            fputcsv($stream, ['ROUTE TELEMETRY']);
            fputcsv($stream, ['Recorded at', 'Latitude', 'Longitude', 'Speed (m/s)', 'Battery (%)', 'Network']);
            GpsLocation::where('user_id', $task->assignee_id)->whereBetween('recorded_at', [$from, $to])->orderBy('recorded_at')->chunk(500, function ($points) use ($stream) {
                foreach ($points as $point) fputcsv($stream, [$point->recorded_at, $point->latitude, $point->longitude, $point->speed, $point->battery_level, $point->network_type]);
            });
            fputcsv($stream, []);
            fputcsv($stream, ['DEVICE DIAGNOSTICS']);
            fputcsv($stream, ['Occurred at', 'Event', 'Reason', 'Duration (s)', 'Battery (%)', 'Network']);
            foreach (DiagnosticLog::where('user_id', $task->assignee_id)->whereBetween('occurred_at', [$from, $to])->orderBy('occurred_at')->get() as $log) {
                fputcsv($stream, [$log->occurred_at, $log->event_type->value, $log->reason, $log->duration_seconds, $log->battery_level, $log->network_type]);
            }
            fclose($stream);
        }, 'task-'.$task->uuid.'-report.csv', ['Content-Type' => 'text/csv']);
    }

    private function assignees(User $user)
    {
        return $user->trackedUsers()->usableForTracking()->with('trackedUser:id,name,email')->get()->pluck('trackedUser')->filter();
    }

    private function relationFor(User $user, int $assigneeId): TrackingRelation
    {
        return TrackingRelation::usableForTracking()->where('tracker_user_id', $user->id)
            ->where('tracked_user_id', $assigneeId)->firstOrFail();
    }

    private function authorizeTask(User $user, FieldTask $task): void
    {
        abort_unless($task->involves($user) && $task->trackingRelation()->usableForTracking()->exists(), 403);
    }

    private function syncStops(FieldTask $task, array $stops): void
    {
        foreach (array_values($stops) as $index => $stop) {
            $task->stops()->create($stop + ['sequence' => $index + 1, 'status' => 'pending']);
        }
    }

    private function applyDateFilter($query, Request $request): void
    {
        $preset = $request->string('range', 'today')->toString();
        if ($preset === 'yesterday') $query->whereDate('starts_at', today()->subDay());
        elseif ($preset === 'last_30_days') $query->where('starts_at', '>=', now()->subDays(30));
        elseif ($preset === 'custom' && $request->filled(['from', 'to'])) $query->whereBetween('starts_at', [$request->date('from')->startOfDay(), $request->date('to')->endOfDay()]);
        else $query->whereDate('starts_at', today());
    }
}
