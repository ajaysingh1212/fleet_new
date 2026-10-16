@php $country = $country ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="tracker-form-label" for="name">Country Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $country?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="tracker-form-label" for="iso2">ISO2</label>
        <input id="iso2" name="iso2" type="text" maxlength="2" class="form-control text-uppercase @error('iso2') is-invalid @enderror" value="{{ old('iso2', $country?->iso2) }}" required>
        @error('iso2')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="tracker-form-label" for="iso3">ISO3</label>
        <input id="iso3" name="iso3" type="text" maxlength="3" class="form-control text-uppercase @error('iso3') is-invalid @enderror" value="{{ old('iso3', $country?->iso3) }}">
        @error('iso3')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="tracker-form-label" for="phone_code">Phone Code</label>
        <input id="phone_code" name="phone_code" type="text" class="form-control" value="{{ old('phone_code', $country?->phone_code) }}" placeholder="+1">
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $country?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
