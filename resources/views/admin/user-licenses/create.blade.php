@extends('layouts.app')

@section('page-eyebrow', 'Licensing')
@section('page-title', 'Assign License')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.user-licenses.index') }}">User Licenses</a></li>
@endsection

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.user-licenses.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="user_id">User</label>
                        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Select User</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="tracker-form-label" for="license_plan_id">License Plan</label>
                        <select id="license_plan_id" name="license_plan_id" class="form-select @error('license_plan_id') is-invalid @enderror" required>
                            <option value="">Select Plan</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('license_plan_id') == $plan->id)>{{ $plan->name }} — ₹{{ number_format($plan->price, 2) }}</option>
                            @endforeach
                        </select>
                        @error('license_plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn tracker-primary-btn">Assign License</button>
                    <a href="{{ route('admin.user-licenses.index') }}" class="btn tracker-outline-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
