<footer class="app-footer tracker-footer border-0">
    <strong>{{ now()->year }} {{ $__siteName }}</strong>
    <div class="float-end d-none d-sm-inline-block">
        <span>{{ $__siteSettings['tagline'] ?? 'Employee Tracking SaaS' }}</span>
        @if (! empty($__siteSettings['support_email']))
            <span class="ms-2"><a href="mailto:{{ $__siteSettings['support_email'] }}">{{ $__siteSettings['support_email'] }}</a></span>
        @endif
        @if (! empty($__siteSettings['support_phone']))
            <span class="ms-2">{{ $__siteSettings['support_phone'] }}</span>
        @endif
    </div>
</footer>
