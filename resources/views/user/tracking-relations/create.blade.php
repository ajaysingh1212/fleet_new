@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'Add Tracked Person')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-body">
            @if ($availableLicenseCount < 1)
                <div class="alert alert-warning tracker-alert d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <span>You need an available license to add a tracked person.</span>
                    <a href="{{ route('my-licenses.plans') }}" class="btn tracker-primary-btn">Buy a License</a>
                </div>
            @endif
            @if ($errors->has('tracker_user_id'))
                <div class="alert alert-danger tracker-alert">{{ $errors->first('tracker_user_id') }} <a href="{{ route('my-licenses.plans') }}">Browse licenses</a></div>
            @endif

            <ul class="nav nav-pills gap-2 mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#existing-user" type="button" role="tab">Request Existing User</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#managed-user" type="button" role="tab">Create Managed User</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="existing-user" role="tabpanel">
                    <form method="POST" action="{{ route('my-tracking.requests.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="identifier">Email or Phone</label>
                                <input id="identifier" name="identifier" type="text" maxlength="255" class="form-control @error('identifier') is-invalid @enderror" value="{{ old('identifier') }}" placeholder="Search by email or phone" required>
                                @error('identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="request_relationship_name">Relationship</label>
                                <input id="request_relationship_name" name="relationship_name" type="text" maxlength="120" class="form-control @error('relationship_name') is-invalid @enderror" value="{{ old('relationship_name') }}" placeholder="e.g. Friend, Family, Field Manager" required>
                                @error('relationship_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn tracker-primary-btn">Send Friend Request</button>
                            <a href="{{ route('my-tracking.index') }}" class="btn tracker-outline-btn">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="managed-user" role="tabpanel">
                    <form method="POST" action="{{ route('my-tracking.managed-users.store') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="name">Full Name</label>
                                <input id="name" name="name" type="text" maxlength="255" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="email">Email</label>
                                <input id="email" name="email" type="email" maxlength="255" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="phone">Phone</label>
                                <input id="phone" name="phone" type="text" maxlength="30" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="managed_relationship_name">Relationship</label>
                                <input id="managed_relationship_name" name="relationship_name" type="text" maxlength="120" class="form-control @error('relationship_name') is-invalid @enderror" value="{{ old('relationship_name') }}" placeholder="e.g. Friend, Family, Field Manager" required>
                                @error('relationship_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="password">Password</label>
                                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="tracker-form-label" for="password_confirmation">Confirm Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="tracker-form-label" for="department">Department</label>
                                <input id="department" name="department" type="text" maxlength="100" class="form-control" value="{{ old('department') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="tracker-form-label" for="designation">Designation</label>
                                <input id="designation" name="designation" type="text" maxlength="100" class="form-control" value="{{ old('designation') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="tracker-form-label" for="company">Company</label>
                                <input id="company" name="company" type="text" maxlength="150" class="form-control" value="{{ old('company') }}">
                            </div>
                            <div class="col-12">
                                <label class="tracker-form-label" for="address">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3">{{ old('address') }}</textarea>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn tracker-primary-btn">Create User and Activate Tracking</button>
                            <a href="{{ route('my-tracking.index') }}" class="btn tracker-outline-btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
