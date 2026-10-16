@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'Add Tracking Relation')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.tracking-relations.index') }}">Tracking Relations</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.tracking-relations.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="tracker_user_id">Tracker (license owner)</label>
                        <select id="tracker_user_id" name="tracker_user_id" class="form-select @error('tracker_user_id') is-invalid @enderror" required>
                            <option value="">Select User</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('tracker_user_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('tracker_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="tracked_user_id">Tracked User</label>
                        <select id="tracked_user_id" name="tracked_user_id" class="form-select @error('tracked_user_id') is-invalid @enderror" required>
                            <option value="">Select User</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('tracked_user_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('tracked_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="relationship_name">Relationship Name</label>
                        <input id="relationship_name" name="relationship_name" type="text" class="form-control @error('relationship_name') is-invalid @enderror" value="{{ old('relationship_name') }}" placeholder="e.g. Field Manager - Sales Rep" required>
                        @error('relationship_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Create Relation</button>
                    <a href="{{ route('admin.tracking-relations.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
