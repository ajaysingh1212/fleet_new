@extends('layouts.app')

@section('page-eyebrow', 'Field Operations')
@section('page-title', 'Tasks')

@section('page-actions')
    <a href="{{ route('tasks.export', request()->query()) }}" class="btn tracker-outline-btn" title="Download filtered report"><i class="fa-solid fa-download"></i></a>
    @if ($canCreate)<a href="{{ route('tasks.create') }}" class="btn tracker-primary-btn"><i class="fa-solid fa-plus me-2"></i>New Task</a>@endif
@endsection

@section('content')
    <div class="card tracker-filter-card mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end">
                <div class="col-md-3"><label class="tracker-form-label">Date range</label><select name="range" class="form-select" onchange="this.form.submit()"><option value="today" @selected(request('range','today')==='today')>Today</option><option value="yesterday" @selected(request('range')==='yesterday')>Yesterday</option><option value="last_30_days" @selected(request('range')==='last_30_days')>Last 30 days</option><option value="custom" @selected(request('range')==='custom')>Custom</option></select></div>
                <div class="col-md-2"><label class="tracker-form-label">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
                <div class="col-md-2"><label class="tracker-form-label">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
                <div class="col-md-3"><label class="tracker-form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['assigned','in_progress','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ Str::headline($status) }}</option>@endforeach</select></div>
                <div class="col-md-2 d-grid"><button class="btn tracker-primary-btn"><i class="fa-solid fa-filter me-2"></i>Apply</button></div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @forelse($tasks as $task)
            @php($done = $task->stops->where('status','completed')->count())
            <div class="col-xl-4 col-md-6">
                <article class="card tracker-surface-card task-list-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3 mb-3"><span class="task-priority task-priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span><span class="tracker-status-pill tracker-status-{{ $task->status === 'completed' ? 'active' : 'info' }}">{{ Str::headline($task->status) }}</span></div>
                        <h3 class="tracker-card-title">{{ $task->title }}</h3>
                        <p class="text-muted mb-3">{{ Str::limit($task->description, 100) ?: 'No description' }}</p>
                        <div class="task-meta-grid"><span><i class="fa-solid fa-user"></i>{{ $task->assignee->name }}</span><span><i class="fa-solid fa-repeat"></i>{{ Str::headline($task->schedule_type) }}</span><span><i class="fa-solid fa-location-dot"></i>{{ $done }}/{{ $task->stops->count() }} stops</span><span><i class="fa-regular fa-clock"></i>{{ $task->starts_at->format('d M, H:i') }}</span></div>
                        <div class="progress task-progress mt-3"><div class="progress-bar" style="width: {{ $task->stops->count() ? ($done/$task->stops->count())*100 : 0 }}%"></div></div>
                        <div class="d-flex gap-2 mt-4"><a href="{{ route('tasks.show',$task) }}" class="btn tracker-primary-btn flex-grow-1"><i class="fa-solid fa-map-location-dot me-2"></i>Open</a>@if($task->creator_id===auth()->id() && $task->status==='assigned')<a href="{{ route('tasks.edit',$task) }}" class="btn tracker-outline-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>@endif</div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="card tracker-surface-card"><div class="tracker-empty-state py-5"><i class="fa-solid fa-list-check"></i><p>No tasks in this date range.</p>@if($canCreate)<a href="{{ route('tasks.create') }}" class="btn tracker-primary-btn">Create first task</a>@endif</div></div></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $tasks->links() }}</div>
@endsection
