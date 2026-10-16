<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $__siteSettings = app(\App\Services\SettingsService::class)->forGroup('site');
        $__siteName = $__siteSettings['name'] ?? config('app.name', 'Tracker Enterprise');
        $__siteLogo = $__siteSettings['logo'] ?? null;
        $__siteFavicon = $__siteSettings['favicon'] ?? null;
    @endphp
    <title>{{ $__siteName }}</title>
    @if ($__siteFavicon)
        <link rel="icon" href="{{ asset('storage/'.$__siteFavicon) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hold-transition login-page">
    <div class="login-box tracker-auth-card">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="{{ url('/') }}" class="h3 text-decoration-none d-inline-flex align-items-center gap-2">
                    @if ($__siteLogo)
                        <img src="{{ asset('storage/'.$__siteLogo) }}" alt="{{ $__siteName }}" style="max-width: 180px; max-height: 48px; object-fit: contain;">
                    @else
                        <b>{{ $__siteName }}</b>
                    @endif
                </a>
            </div>
            <div class="card-body">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
