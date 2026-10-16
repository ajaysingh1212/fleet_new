<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(config('services.traffic_tiles.url')) data-traffic-tiles="{{ config('services.traffic_tiles.url') }}" @endif>
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
    <title>@yield('page-title', 'Dashboard') · {{ $__siteName }}</title>
    @if ($__siteFavicon)
        <link rel="icon" href="{{ asset('storage/'.$__siteFavicon) }}">
    @endif
    <script>
        (function () {
            try {
                var s = JSON.parse(localStorage.getItem('tracker.theme') || '{}');
                var root = document.documentElement;
                root.setAttribute('data-bs-theme', s.mode === 'dark' ? 'dark' : 'light');
                root.setAttribute('data-accent', s.accent || 'blue');
                root.setAttribute('data-sidebar-position', s.sidebarPosition || 'left');
                root.setAttribute('data-sidebar-visibility', s.sidebarVisibility || 'show');
            } catch (e) {}
        })();
    </script>
    @auth
        <script>
            window.__trackerUserId = {{ auth()->id() }};
            window.__trackerUserName = @json(auth()->user()->name);
            window.__trackerUserAvatar = @json(auth()->user()->avatar ? asset('storage/'.auth()->user()->avatar) : null);
            window.__trackerSelfTrackingEnabled = @json((bool) auth()->user()->trackingPreference?->self_tracking_enabled);
        </script>
    @endauth
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini sidebar-expand-lg layout-fixed app-loaded tracker-shell">
    <script>
        (function () {
            try {
                var s = JSON.parse(localStorage.getItem('tracker.theme') || '{}');
                if (s.sidebarStyle === 'compact') {
                    document.body.classList.add('sidebar-collapse');
                }
            } catch (e) {}
        })();
    </script>
    <div class="app-wrapper">
        @if (session('status'))
            <div data-toast-message="{{ session('status') }}" data-toast-type="success"></div>
        @endif

        @if (session('error'))
            <div data-toast-message="{{ session('error') }}" data-toast-type="error"></div>
        @endif

        @include('layouts.partials.topbar')

        <button type="button" class="tracker-sidebar-reveal" data-sidebar-reveal title="Show sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        @include('layouts.partials.sidebar')

        <main class="app-main tracker-content-wrapper">
            <section class="app-content-header tracker-page-header">
                <div class="container-fluid">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <div class="tracker-page-eyebrow">@yield('page-eyebrow', 'Workspace')</div>
                            <h1 class="tracker-page-title mb-0">@yield('page-title', 'Dashboard')</h1>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            @hasSection('page-actions')
                                <div class="tracker-page-actions d-flex gap-2">
                                    @yield('page-actions')
                                </div>
                            @endif
                            <ol class="breadcrumb tracker-breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                @yield('breadcrumb')
                                <li class="breadcrumb-item active">@yield('page-title', 'Dashboard')</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-content">
                <div class="container-fluid">
                    @if ($errors->any())
                        <div class="alert alert-danger tracker-alert">
                            <strong>Please fix the following:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </section>
        </main>

        @include('layouts.partials.footer')
    </div>

    @include('layouts.partials.theme-customizer')

    @auth
        @include('layouts.partials.incoming-call')
    @endauth

    @stack('scripts')
</body>
</html>
