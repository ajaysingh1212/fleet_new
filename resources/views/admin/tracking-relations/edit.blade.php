@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'Edit Tracking Relation')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.tracking-relations.index') }}">Tracking Relations</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="tracker-form-label">Tracker</label>
                    <input type="text" class="form-control" value="{{ $relation->trackerUser?->name }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="tracker-form-label">Tracked User</label>
                    <input type="text" class="form-control" value="{{ $relation->trackedUser?->name }}" disabled>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.tracking-relations.update', $relation) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="relationship_name">Relationship Name</label>
                        <input id="relationship_name" name="relationship_name" type="text" class="form-control @error('relationship_name') is-invalid @enderror" value="{{ old('relationship_name', $relation->relationship_name) }}" required>
                        @error('relationship_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $relation->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Update Relation</button>
                    <a href="{{ route('admin.tracking-relations.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
