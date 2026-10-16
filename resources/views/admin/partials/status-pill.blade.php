@php
    $__value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $__label = is_object($status) && method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', $__value));
@endphp
<span class="tracker-status-pill tracker-status-{{ $__value }}">{{ $__label }}</span>
