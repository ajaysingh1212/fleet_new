@php $language = $language ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="tracker-form-label" for="name">Language Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $language?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="tracker-form-label" for="locale">Locale</label>
        <input id="locale" name="locale" type="text" class="form-control @error('locale') is-invalid @enderror" value="{{ old('locale', $language?->locale) }}" placeholder="en, hi, fr" required>
        @error('locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1" @checked(old('is_default', $language?->is_default))>
            <label class="form-check-label" for="is_default">Default Language</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $language?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
