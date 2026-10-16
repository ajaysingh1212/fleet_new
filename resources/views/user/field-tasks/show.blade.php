@extends('layouts.app')

@section('page-eyebrow', 'Realtime Task')
@section('page-title', $task->title)
@push('scripts') @vite(['resources/js/field-task-show.js']) @endpush

@section('page-actions')
    <a href="{{ route('tasks.export-task',$task) }}" class="btn tracker-outline-btn" title="Download complete task report"><i class="fa-solid fa-download"></i></a>
    @if($task->creator_id===auth()->id() && $task->status==='assigned')<a href="{{ route('tasks.edit',$task) }}" class="btn tracker-primary-btn"><i class="fa-solid fa-pen me-2"></i>Edit</a>@endif
@endsection

@section('content')
<script id="field-task-data" type="application/json">{!! json_encode([
    'uuid'=>$task->uuid,'assignee_id'=>$task->assignee_id,'viewer_id'=>auth()->id(),'status'=>$task->status,
    'report_url'=>route('tasks.report',$task),'latest_location'=>$latestLocation ? ['lat'=>(float)$latestLocation->latitude,'lng'=>(float)$latestLocation->longitude,'speed'=>$latestLocation->speed,'recorded_at'=>$latestLocation->recorded_at->toIso8601String()] : null,
    'stops'=>$task->stops->map(fn($s)=>['uuid'=>$s->uuid,'sequence'=>$s->sequence,'title'=>$s->title,'description'=>$s->description,'address'=>$s->address,'lat'=>(float)$s->latitude,'lng'=>(float)$s->longitude,'expected_at'=>$s->expected_at?->toIso8601String(),'radius'=>$s->radius_meters,'status'=>$s->status,'arrived_at'=>$s->arrived_at?->toIso8601String()])->all(),
]) !!}</script>

<div class="task-live-layout">
    <div class="card tracker-surface-card task-live-map-card"><div class="card-body p-0 position-relative"><div id="task-live-map"></div><div class="task-live-connection"><span class="task-live-dot"></span><span id="task-socket-status">Connecting realtime...</span></div></div></div>
    <aside class="task-live-sidebar">
        <div class="card tracker-surface-card"><div class="card-body">
            <div class="d-flex justify-content-between gap-2"><span class="task-priority task-priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span><span id="task-status" class="tracker-status-pill tracker-status-info">{{ Str::headline($task->status) }}</span></div>
            <p class="mt-3 mb-3 text-muted">{{ $task->description }}</p>
            <div class="task-meta-grid"><span><i class="fa-solid fa-user-tie"></i>{{ $task->creator->name }}</span><span><i class="fa-solid fa-user"></i>{{ $task->assignee->name }}</span><span><i class="fa-solid fa-repeat"></i>{{ Str::headline($task->schedule_type) }}</span><span><i class="fa-regular fa-clock"></i>{{ $task->starts_at->format('d M, H:i') }}</span></div>
            @if($task->instructions)<div class="task-instructions mt-3"><strong>Instructions</strong><p class="mb-0">{{ $task->instructions }}</p></div>@endif
        </div></div>
        <div class="card tracker-surface-card"><div class="card-header bg-transparent border-0"><h3 class="tracker-card-title mb-1">Route plan</h3><p class="tracker-card-subtitle mb-0"><span id="task-completed-count">{{ $task->stops->where('status','completed')->count() }}</span> of {{ $task->stops->count() }} completed</p></div><div class="card-body pt-0"><div id="task-live-stops" class="task-live-stops">
            @foreach($task->stops as $stop)<button type="button" class="task-live-stop {{ $stop->status==='completed'?'is-complete':'' }}" data-stop="{{ $stop->uuid }}"><span class="task-stop-number">{{ $stop->status==='completed'?'✓':$stop->sequence }}</span><span><strong>{{ $stop->title }}</strong><small>{{ $stop->address ?: $stop->radius_meters.' m arrival zone' }}</small><small data-distance>Calculating distance...</small></span><i class="fa-solid fa-chevron-right"></i></button>@endforeach
        </div></div></div>
        <div class="d-grid gap-2"><button type="button" class="btn tracker-primary-btn" id="task-route-history"><i class="fa-solid fa-play me-2"></i>Route playback</button><button type="button" class="btn tracker-outline-btn" id="task-device-diagnostics"><i class="fa-solid fa-mobile-screen-button me-2"></i>Device diagnostics</button></div>
    </aside>
</div>

<div class="modal fade" id="task-report-modal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content tracker-modal"><div class="modal-header border-0"><div><h5 class="modal-title" id="task-report-title">Route playback</h5><p class="tracker-card-subtitle mb-0">Task evidence retained for 30 days</p></div><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="task-replay-map"></div><div class="task-playback-controls mt-3"><button class="btn tracker-outline-btn" id="task-play"><i class="fa-solid fa-play"></i></button><input type="range" id="task-play-range" min="0" value="0" class="form-range"><select id="task-play-speed" class="form-select"><option value="1">1x</option><option value="2">2x</option><option value="4">4x</option><option value="8">8x</option></select></div><div id="task-diagnostics-panel" class="table-responsive d-none mt-3"><table class="table tracker-table"><thead><tr><th>Event</th><th>Time</th><th>Battery</th><th>Network</th><th>Reason</th></tr></thead><tbody></tbody></table></div></div></div></div></div>
@endsection
