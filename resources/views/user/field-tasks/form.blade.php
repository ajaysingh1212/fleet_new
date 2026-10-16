@extends('layouts.app')

@section('page-eyebrow', 'Field Operations')
@section('page-title', $task->exists ? 'Edit Task' : 'Create Task')
@push('scripts') @vite(['resources/js/field-task-form.js']) @endpush

@section('content')
<form method="POST" action="{{ $action }}" id="field-task-form">
    @csrf @if($method==='PUT') @method('PUT') @endif
    <script id="task-stops-data" type="application/json">{!! json_encode(old('stops', $task->exists ? $task->stops->map(fn($s)=>['title'=>$s->title,'description'=>$s->description,'address'=>$s->address,'latitude'=>(float)$s->latitude,'longitude'=>(float)$s->longitude,'expected_at'=>$s->expected_at?->format('Y-m-d\TH:i'),'radius_meters'=>$s->radius_meters])->all() : [])) !!}</script>
    <div class="task-builder-grid">
        <div>
            <div class="card tracker-surface-card mb-3"><div class="card-body"><h3 class="tracker-card-title mb-3">Assignment</h3><div class="row g-3">
                <div class="col-md-8"><label class="tracker-form-label">Task title *</label><input name="title" value="{{ old('title',$task->title) }}" class="form-control" required maxlength="180"></div>
                <div class="col-md-4"><label class="tracker-form-label">Priority *</label><select name="priority" class="form-select">@foreach(['low','normal','high','urgent'] as $v)<option value="{{ $v }}" @selected(old('priority',$task->priority)===$v)>{{ ucfirst($v) }}</option>@endforeach</select></div>
                <div class="col-12"><label class="tracker-form-label">Assign to *</label><select name="assignee_id" class="form-select" required><option value="">Select a licensed tracked person</option>@foreach($assignees as $person)<option value="{{ $person->id }}" @selected((int)old('assignee_id',$task->assignee_id)===$person->id)>{{ $person->name }} - {{ $person->email }}</option>@endforeach</select></div>
                <div class="col-12"><label class="tracker-form-label">Description</label><textarea name="description" class="form-control" rows="3">{{ old('description',$task->description) }}</textarea></div>
            </div></div></div>
            <div class="card tracker-surface-card mb-3"><div class="card-body"><h3 class="tracker-card-title mb-3">Schedule & service details</h3><div class="row g-3">
                <div class="col-md-4"><label class="tracker-form-label">Frequency *</label><select name="schedule_type" class="form-select">@foreach(['once','hourly','daily','weekly','monthly','quarterly','half_yearly','yearly','custom'] as $v)<option value="{{ $v }}" @selected(old('schedule_type',$task->schedule_type)===$v)>{{ Str::headline($v) }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="tracker-form-label">Starts *</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at',$task->starts_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" class="form-control" required></div>
                <div class="col-md-4"><label class="tracker-form-label">Due</label><input type="datetime-local" name="due_at" value="{{ old('due_at',$task->due_at?->format('Y-m-d\TH:i')) }}" class="form-control"></div>
                <div class="col-md-4"><label class="tracker-form-label">Repeat until</label><input type="date" name="repeat_until" value="{{ old('repeat_until',$task->repeat_until?->format('Y-m-d')) }}" class="form-control"></div>
                <div class="col-md-4"><label class="tracker-form-label">Default arrival radius</label><div class="input-group"><input type="number" name="arrival_radius_meters" value="{{ old('arrival_radius_meters',$task->arrival_radius_meters ?? 100) }}" min="25" max="1000" class="form-control"><span class="input-group-text">meters</span></div></div>
                <div class="col-md-4"><label class="tracker-form-label">Reference code</label><input name="reference_code" value="{{ old('reference_code',$task->reference_code) }}" class="form-control"></div>
                <div class="col-md-4"><label class="tracker-form-label">Customer / site</label><input name="customer_name" value="{{ old('customer_name',$task->customer_name) }}" class="form-control"></div>
                <div class="col-md-4"><label class="tracker-form-label">Contact phone</label><input name="customer_phone" value="{{ old('customer_phone',$task->customer_phone) }}" class="form-control"></div>
                <div class="col-md-4"><label class="tracker-form-label">Tags</label><input name="tags" value="{{ old('tags',implode(', ', $task->tags ?? [])) }}" class="form-control" placeholder="delivery, urgent"></div>
                <div class="col-12"><label class="tracker-form-label">Instructions</label><textarea name="instructions" class="form-control" rows="3">{{ old('instructions',$task->instructions) }}</textarea></div>
            </div></div></div>
        </div>
        <div class="task-map-column">
            <div class="card tracker-surface-card task-map-card"><div class="card-body p-0"><div class="task-map-toolbar"><div><strong>Route stops</strong><span>Click map to add, then set order and details</span></div><button type="button" class="btn tracker-outline-btn btn-sm" id="task-use-location" title="Center on my location"><i class="fa-solid fa-crosshairs"></i></button></div><div id="task-builder-map"></div><div id="task-stop-list" class="task-stop-list"></div></div></div>
        </div>
    </div>
    <div class="task-builder-actions"><a href="{{ route('tasks.index') }}" class="btn tracker-outline-btn">Cancel</a><button class="btn tracker-primary-btn"><i class="fa-solid fa-paper-plane me-2"></i>{{ $task->exists ? 'Save changes' : 'Assign task' }}</button></div>
</form>
@endsection
