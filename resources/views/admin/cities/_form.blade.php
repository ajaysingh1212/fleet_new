@php $city = $city ?? null; @endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="tracker-form-label" for="country_id">Country</label>
        <select id="country_id" name="country_id" class="form-select @error('country_id') is-invalid @enderror" data-country-select data-state-target="#state_id" required>
            <option value="">Select Country</option>
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected(old('country_id', $city?->country_id) == $country->id)>{{ $country->name }}</option>
            @endforeach
        </select>
        @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="state_id">State</label>
        <select id="state_id" name="state_id" class="form-select @error('state_id') is-invalid @enderror" required>
            <option value="">Select State</option>
            @foreach ($states as $state)
                @if (old('country_id', $city?->country_id) == $state->country_id)
                    <option value="{{ $state->id }}" @selected(old('state_id', $city?->state_id) == $state->id)>{{ $state->name }}</option>
                @endif
            @endforeach
        </select>
        @error('state_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="name">City Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $city?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $city?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
