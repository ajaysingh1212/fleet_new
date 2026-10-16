<form method="post" action="{{ route('profile.update') }}" class="row g-3">
    @csrf
    @method('patch')

    <div class="col-md-6">
        <label class="tracker-form-label" for="name">Full Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="email">Email Address</label>
        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="username">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="mt-2">
                <p class="small text-muted mb-1">
                    Your email address is unverified.
                    <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">Resend verification email</button>
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="small text-success mb-0">A new verification link has been sent to your email address.</p>
                @endif
            </div>
        @endif
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="phone">Phone</label>
        <input id="phone" name="phone" type="text" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" autocomplete="tel">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="department">Department</label>
        <input id="department" name="department" type="text" class="form-control @error('department') is-invalid @enderror" value="{{ old('department', $user->department) }}">
        @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="designation">Designation</label>
        <input id="designation" name="designation" type="text" class="form-control @error('designation') is-invalid @enderror" value="{{ old('designation', $user->designation) }}">
        @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="company">Company</label>
        <input id="company" name="company" type="text" class="form-control @error('company') is-invalid @enderror" value="{{ old('company', $user->company) }}">
        @error('company')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="timezone">Timezone</label>
        <input id="timezone" name="timezone" type="text" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $user->timezone) }}">
        @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="theme">Theme</label>
        <select id="theme" name="theme" class="form-select @error('theme') is-invalid @enderror">
            @foreach (\App\Enums\ThemeMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected(old('theme', $user->theme->value) === $mode->value)>{{ ucfirst($mode->value) }}</option>
            @endforeach
        </select>
        @error('theme')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="tracker-form-label" for="address">Address</label>
        <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $user->address) }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn tracker-primary-btn">Save Changes</button>
    </div>
</form>

<form id="send-verification" method="post" action="{{ route('verification.send') }}" class="d-none">
    @csrf
</form>
