@extends('layouts.app')

@section('page-eyebrow', 'Account')
@section('page-title', 'Profile')

@section('content')
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card tracker-surface-card mb-4">
                <div class="card-body text-center">
                    <div class="tracker-profile-avatar-lg mx-auto mb-3">
                        @if ($user->avatar)
                            <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}">
                        @else
                            <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <h3 class="tracker-card-title mb-1">{{ $user->name }}</h3>
                    <p class="tracker-card-subtitle mb-3">{{ $user->getRoleNames()->first() ?? 'User' }} · {{ $user->employee_id }}</p>

                    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="text-start">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="name" value="{{ $user->name }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <label class="tracker-form-label" for="avatar">Update Avatar</label>
                        <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                        @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn tracker-primary-btn flex-fill">Upload</button>
                            @if ($user->avatar)
                                <button type="submit" name="remove_avatar" value="1" class="btn tracker-outline-btn">Remove</button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Session &amp; Security</h3>
                    <p class="tracker-card-subtitle mb-0">Latest sign-in activity</p>
                </div>
                <div class="card-body">
                    <div class="tracker-mini-list">
                        <div class="tracker-mini-item"><span>Last Login</span><strong>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</strong></div>
                        <div class="tracker-mini-item"><span>Last Login IP</span><strong>{{ $user->last_login_ip ?? 'N/A' }}</strong></div>
                        <div class="tracker-mini-item"><span>Last Activity</span><strong>{{ $user->last_activity_at?->diffForHumans() ?? 'N/A' }}</strong></div>
                        <div class="tracker-mini-item"><span>Account Status</span><strong>{{ $user->status->label() }}</strong></div>
                    </div>
                    @if (Route::has('my-devices.index'))
                        <a href="{{ route('my-devices.index') }}" class="btn tracker-outline-btn w-100 mt-3">Manage Devices</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Profile Information</h3>
                    <p class="tracker-card-subtitle mb-0">Update your account's personal and contact details</p>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card tracker-surface-card mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1">Update Password</h3>
                    <p class="tracker-card-subtitle mb-0">Use a long, random password to stay secure</p>
                </div>
                <div class="card-body">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="card tracker-surface-card tracker-danger-card">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="tracker-card-title mb-1 text-danger">Delete Account</h3>
                    <p class="tracker-card-subtitle mb-0">Permanently remove your account and all associated data</p>
                </div>
                <div class="card-body">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
@endsection
