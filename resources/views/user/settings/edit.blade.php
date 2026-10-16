@extends('layouts.app')

@section('page-eyebrow', 'Workspace')
@section('page-title', 'My Settings')

@section('content')
    <div class="card tracker-surface-card">
        <div class="card-header border-0 bg-transparent">
            <h3 class="tracker-card-title mb-1">Personal Preferences</h3>
            <p class="tracker-card-subtitle mb-0">Theme, timezone, and language for your account</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('user-settings.update') }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-4">
                    <label class="tracker-form-label" for="theme">Theme</label>
                    <select id="theme" name="theme" class="form-select @error('theme') is-invalid @enderror">
                        @foreach (\App\Enums\ThemeMode::cases() as $mode)
                            <option value="{{ $mode->value }}" @selected(old('theme', auth()->user()->theme->value) === $mode->value)>{{ ucfirst($mode->value) }}</option>
                        @endforeach
                    </select>
                    @error('theme')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="tracker-form-label" for="timezone">Timezone</label>
                    <input id="timezone" name="timezone" type="text" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', auth()->user()->timezone) }}" required>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="tracker-form-label" for="language_id">Language</label>
                    <select id="language_id" name="language_id" class="form-select">
                        <option value="">Select Language</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language->id }}" @selected(old('language_id', auth()->user()->language_id) == $language->id)>{{ $language->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn tracker-primary-btn">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>
@endsection
