@props(['field', 'label', 'value' => null, 'type' => 'text', 'options' => [], 'required' => false, 'hint' => null])
@php
    $parts = explode('.', $field);
    $name = array_shift($parts).implode('', array_map(fn($p) => '['.$p.']', $parts));
    $id = str_replace('.', '_', $field);
@endphp
<div {{ $attributes->class(['col-md-6']) }} @error($field) data-invalid="true" @enderror>
    <label class="form-label fw-semibold" for="{{ $id }}">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    @if($options)
        <select class="form-select" name="{{ $name }}" id="{{ $id }}" @required($required)>
            <option value="">Select {{ strtolower($label) }}</option>
            @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string)old($field, $value) === (string)$key)>{{ $text }}</option>@endforeach
        </select>
    @else
        <input class="form-control" name="{{ $name }}" id="{{ $id }}" type="{{ $type }}" value="{{ old($field, $value) }}" @required($required) @if($type === 'number') min="0" step="any" @endif @if($field === 'device.identifier') inputmode="numeric" pattern="[0-9]{15}" maxlength="15" @endif>
    @endif
    @if($hint)<small class="text-muted">{{ $hint }}</small>@endif
</div>
