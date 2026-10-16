<form method="post" action="{{ route('password.update') }}" class="row g-3">
    @csrf
    @method('put')

    <div class="col-md-4">
        <label class="tracker-form-label" for="update_password_current_password">Current Password</label>
        <input id="update_password_current_password" name="current_password" type="password" class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password">
        @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="update_password_password">New Password</label>
        <input id="update_password_password" name="password" type="password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
        @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="update_password_password_confirmation">Confirm Password</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
        @error('password_confirmation', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn tracker-primary-btn">Update Password</button>
    </div>
</form>
