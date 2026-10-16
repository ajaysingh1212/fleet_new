@php
    $user = $user ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="tracker-form-label" for="employee_id">Employee ID</label>
        <input id="employee_id" name="employee_id" type="text" class="form-control @error('employee_id') is-invalid @enderror" value="{{ old('employee_id', $user?->employee_id) }}" required>
        @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="name">Full Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="email">Email</label>
        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user?->email) }}" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="phone">Phone</label>
        <input id="phone" name="phone" type="text" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user?->phone) }}">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="password">Password @if($user) <span class="text-muted small">(leave blank to keep current)</span>@endif</label>
        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" {{ $user ? '' : 'required' }}>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $user?->status?->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="department">Department</label>
        <input id="department" name="department" type="text" class="form-control" value="{{ old('department', $user?->department) }}">
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="designation">Designation</label>
        <input id="designation" name="designation" type="text" class="form-control" value="{{ old('designation', $user?->designation) }}">
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="company">Company</label>
        <input id="company" name="company" type="text" class="form-control" value="{{ old('company', $user?->company) }}">
    </div>

    <div class="col-md-3">
        <label class="tracker-form-label" for="gender">Gender</label>
        <select id="gender" name="gender" class="form-select">
            <option value="">Select</option>
            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                <option value="{{ $value }}" @selected(old('gender', $user?->gender) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="tracker-form-label" for="dob">Date of Birth</label>
        <input id="dob" name="dob" type="date" class="form-control" value="{{ old('dob', $user?->dob?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3">
        <label class="tracker-form-label" for="timezone">Timezone</label>
        <input id="timezone" name="timezone" type="text" class="form-control" value="{{ old('timezone', $user?->timezone ?? 'UTC') }}">
    </div>
    <div class="col-md-3">
        <label class="tracker-form-label" for="zip_code">Zip Code</label>
        <input id="zip_code" name="zip_code" type="text" class="form-control" value="{{ old('zip_code', $user?->zip_code) }}">
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="country_id">Country</label>
        <select id="country_id" name="country_id" class="form-select" data-country-select data-state-target="#state_id" data-city-target="#city_id">
            <option value="">Select Country</option>
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected(old('country_id', $user?->country_id) == $country->id)>{{ $country->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="state_id">State</label>
        <select id="state_id" name="state_id" class="form-select">
            <option value="">Select State</option>
            @foreach ($states as $state)
                @if (old('country_id', $user?->country_id) == $state->country_id)
                    <option value="{{ $state->id }}" @selected(old('state_id', $user?->state_id) == $state->id)>{{ $state->name }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="city_id">City</label>
        <select id="city_id" name="city_id" class="form-select">
            <option value="">Select City</option>
            @foreach ($cities as $city)
                @if (old('state_id', $user?->state_id) == $city->state_id)
                    <option value="{{ $city->id }}" @selected(old('city_id', $user?->city_id) == $city->id)>{{ $city->name }}</option>
                @endif
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="tracker-form-label" for="language_id">Language</label>
        <select id="language_id" name="language_id" class="form-select">
            <option value="">Select Language</option>
            @foreach ($languages as $language)
                <option value="{{ $language->id }}" @selected(old('language_id', $user?->language_id) == $language->id)>{{ $language->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="tracker-form-label">Roles</label>
        <div class="d-flex flex-wrap gap-3 mt-2">
            @php $selectedRoles = old('roles', $user?->getRoleNames()->all() ?? []); @endphp
            @foreach ($roles as $role)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="roles[]" id="role_{{ $role->id }}" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles))>
                    <label class="form-check-label" for="role_{{ $role->id }}">{{ $role->name }}</label>
                </div>
            @endforeach
        </div>
        @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="tracker-form-label" for="address">Address</label>
        <textarea id="address" name="address" rows="2" class="form-control">{{ old('address', $user?->address) }}</textarea>
    </div>
</div>
