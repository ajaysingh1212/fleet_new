@php $template = $template ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="tracker-form-label" for="name">Template Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $template?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="tracker-form-label" for="channel">Channel</label>
        <select id="channel" name="channel" class="form-select @error('channel') is-invalid @enderror">
            @foreach (['mail' => 'Mail', 'database' => 'Database', 'sms' => 'SMS'] as $value => $label)
                <option value="{{ $value }}" @selected(old('channel', $template?->channel) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $template?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>

    <div class="col-12">
        <label class="tracker-form-label" for="subject">Subject</label>
        <input id="subject" name="subject" type="text" class="form-control" value="{{ old('subject', $template?->subject) }}">
    </div>
    <div class="col-12">
        <label class="tracker-form-label" for="body">Body</label>
        <textarea id="body" name="body" rows="6" class="form-control @error('body') is-invalid @enderror">{{ old('body', $template?->body) }}</textarea>
        <span class="tracker-form-hint">You can use placeholders like @{{ name }} or @{{ link }} depending on the channel.</span>
        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
