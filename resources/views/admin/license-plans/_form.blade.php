@php $plan = $plan ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="tracker-form-label" for="name">Plan Name</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $plan?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="tracker-form-label" for="type">Type</label>
        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $plan?->type?->value) === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="tracker-form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $plan?->status?->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="duration_in_days">Duration (days)</label>
        <input id="duration_in_days" name="duration_in_days" type="number" min="1" class="form-control @error('duration_in_days') is-invalid @enderror" value="{{ old('duration_in_days', $plan?->duration_in_days) }}">
        <span class="tracker-form-hint">Leave blank for Lifetime plans.</span>
        @error('duration_in_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="price">Price</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i></span>
            <input id="price" name="price" type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $plan?->price) }}" required>
        </div>
        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="tracker-form-label" for="renewal_price">Renewal Price</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i></span>
            <input id="renewal_price" name="renewal_price" type="number" step="0.01" min="0" class="form-control @error('renewal_price') is-invalid @enderror" value="{{ old('renewal_price', $plan?->renewal_price ?? 0) }}" required>
        </div>
        @error('renewal_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <input type="hidden" name="is_free" value="0">
        <div class="form-check form-switch mt-4">
            <input id="is_free" name="is_free" type="checkbox" class="form-check-input" value="1" @checked(old('is_free', $plan?->is_free ?? false))>
            <label class="form-check-label" for="is_free">Free license (one per account)</label>
        </div>
    </div>

    <div class="col-md-4">
        <label class="tracker-form-label" for="display_order">Display Order</label>
        <input id="display_order" name="display_order" type="number" min="0" class="form-control" value="{{ old('display_order', $plan?->display_order ?? 0) }}">
    </div>
    <div class="col-md-8">
        <label class="tracker-form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $plan?->description) }}</textarea>
    </div>
</div>
