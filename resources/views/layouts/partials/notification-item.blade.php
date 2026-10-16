@php
    $__category = $notification->data['category'] ?? 'system';
    $__icon = match ($__category) {
        'message' => 'fa-solid fa-comment',
        'mention' => 'fa-solid fa-at',
        'call' => 'fa-solid fa-phone',
        'tracking' => 'fa-solid fa-location-dot',
        'diagnostic' => 'fa-solid fa-triangle-exclamation',
        default => 'fa-regular fa-bell',
    };
@endphp
<a href="#"
   class="tracker-notification-item {{ $notification->read_at ? '' : 'unread' }}"
   data-notification-id="{{ $notification->id }}"
   data-action-url="{{ $notification->data['action_url'] ?? '' }}"
>
    <div class="tracker-notification-icon tracker-notification-icon-{{ $__category }}">
        <i class="{{ $__icon }}"></i>
    </div>
    <div class="flex-fill">
        <div class="tracker-notification-message">{{ $notification->data['message'] ?? 'Notification' }}</div>
        <span class="small text-muted">{{ $notification->created_at?->diffForHumans() }}</span>
    </div>
</a>
