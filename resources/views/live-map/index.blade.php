@extends('layouts.app')

@section('page-eyebrow', 'Realtime')
@section('page-title', 'Live Map')

@section('page-actions')
    <form method="POST" action="{{ route('live-map.self-tracking') }}" class="lm-actions">
        @csrf
        <button type="submit" class="btn lm-btn-track {{ $selfTrackingEnabled ? 'btn-outline-danger is-live' : 'tracker-primary-btn' }}" @disabled(! $selfTrackingEnabled && ! $hasOwnedValidLicense)>
            {{-- Tracking on ho to pulse ring, off ho to crosshair icon --}}
            <span class="lm-btn-icon {{ $selfTrackingEnabled ? 'is-live' : '' }}">
                <i class="fa-solid {{ $selfTrackingEnabled ? 'fa-location-dot' : 'fa-location-crosshairs' }}"></i>
            </span>
            {{ $selfTrackingEnabled ? 'Stop Self Tracking' : 'Start Self Tracking' }}
        </button>
        @if (! $selfTrackingEnabled && ! $hasOwnedValidLicense)
            <a href="{{ route('my-licenses.plans') }}" class="btn tracker-outline-btn ms-2">
                <i class="fa-solid fa-key me-2"></i>Buy License
            </a>
        @endif
    </form>
@endsection

@push('scripts')
    @vite(['resources/js/live-map.js'])
@endpush

@section('content')
    <style>
        /* ===== Live Map – scoped styles (sirf is page ke liye) ===== */
        .lm-scope {
            --lm-ink: #0d1b2a;
            --lm-ink-2: #14283d;
            --lm-accent: #12b5a0;
            --lm-accent-soft: rgba(18, 181, 160, .14);
            --lm-warn: #f2994a;
            --lm-danger: #e5484d;
            --lm-ease: cubic-bezier(.2, .7, .2, 1);
        }

        /* Hero strip – yahi page ka ek memorable element hai (radar) */
        .lm-hero {
            display: flex; align-items: center; gap: 1rem;
            padding: .85rem 1.25rem;
            color: #e8f1f7;
            background:
                radial-gradient(120% 140% at 0% 0%, rgba(18,181,160,.22), transparent 55%),
                linear-gradient(135deg, var(--lm-ink), var(--lm-ink-2));
            border-radius: .75rem .75rem 0 0;
        }
        .lm-hero h2 { font-size: 1rem; font-weight: 650; margin: 0; letter-spacing: .01em; }
        .lm-hero p  { margin: 0; font-size: .8rem; color: rgba(232,241,247,.68); }
        .lm-hero-copy { flex: 1; min-width: 0; }
        .lm-radar { width: 56px; height: 56px; flex: none; }
        .lm-radar .sweep {
            transform-origin: 50% 50%; transform-box: fill-box;
            animation: lm-spin 4s linear infinite;
        }
        .lm-radar .blip { animation: lm-blip 4s ease-out infinite; }
        .lm-radar .blip.b2 { animation-delay: 1.3s; }
        .lm-radar .blip.b3 { animation-delay: 2.6s; }

        .lm-live-pill {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .3rem .7rem; border-radius: 999px;
            font-size: .75rem; font-weight: 600;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.14);
        }
        .lm-live-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--lm-accent);
            box-shadow: 0 0 0 0 rgba(18,181,160,.7);
            animation: lm-pulse 1.8s infinite;
        }

        /* Map card */
        .lm-map-card { overflow: hidden; animation: lm-rise .6s var(--lm-ease) both; }
        .lm-map-wrap {
            position: relative;
            /* tiles load hone se pehle subtle grid dikhega */
            background-color: #eaf1f4;
            background-image:
                linear-gradient(rgba(13,27,42,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(13,27,42,.06) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .lm-map-wrap::after { /* map ke upar halki inner shadow */
            content: ""; position: absolute; inset: 0; pointer-events: none;
            box-shadow: inset 0 0 0 1px rgba(13,27,42,.06), inset 0 12px 18px -14px rgba(13,27,42,.35);
            z-index: 400;
        }

        /* Side cards */
        .lm-side-card { transition: box-shadow .25s var(--lm-ease), transform .25s var(--lm-ease); }
        .lm-side-card:hover { box-shadow: 0 10px 28px -14px rgba(13,27,42,.35); }
        .lm-side-title { display: flex; align-items: center; gap: .6rem; }
        .lm-side-title svg { width: 28px; height: 28px; flex: none; }

        /* People rows */
        .tracker-map-person-row { position: relative; transition: background-color .2s ease, transform .2s var(--lm-ease); border-radius: .6rem; }
        .tracker-map-person-row[data-person-row]:hover { background: var(--lm-accent-soft); transform: translateX(2px); }
        .tracker-map-person-row-self { box-shadow: inset 3px 0 0 var(--lm-accent); }
        .tracker-map-person-row-warning { box-shadow: inset 3px 0 0 var(--lm-danger); animation: lm-warn 2.4s ease-in-out infinite; }
        .tracker-map-person-row .tracker-avatar-sm { position: relative; }
        .tracker-map-person-row-self .tracker-avatar-sm::after {
            content: ""; position: absolute; inset: -3px; border-radius: 50%;
            border: 2px solid var(--lm-accent); opacity: .7;
            animation: lm-ring 2.2s ease-out infinite;
        }
        .tracker-status-pill i { animation: lm-shake 2.6s ease-in-out infinite; }

        /* Empty states */
        .lm-empty { text-align: center; padding: 1.25rem .5rem; }
        .lm-empty svg { width: 96px; height: 96px; margin-bottom: .5rem; }
        .lm-empty p { color: var(--bs-secondary-color, #6c7a89); font-size: .88rem; }

        /* Buttons */
        .lm-btn-track { display: inline-flex; align-items: center; transition: transform .2s var(--lm-ease), box-shadow .2s ease; }
        .lm-btn-track:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 8px 18px -10px rgba(13,27,42,.5); }
        .lm-btn-icon { position: relative; display: inline-flex; margin-right: .55rem; }
        .lm-btn-icon.is-live::after {
            content: ""; position: absolute; inset: -5px; border-radius: 50%;
            border: 2px solid currentColor; opacity: .5; animation: lm-ring 1.8s ease-out infinite;
        }

        /* Keyframes */
        @keyframes lm-spin  { to { transform: rotate(360deg); } }
        @keyframes lm-blip  { 0%,55% { opacity: 0; transform: scale(.4); } 65% { opacity: 1; transform: scale(1); } 100% { opacity: 0; transform: scale(1.6); } }
        @keyframes lm-pulse { 70% { box-shadow: 0 0 0 9px rgba(18,181,160,0); } 100% { box-shadow: 0 0 0 0 rgba(18,181,160,0); } }
        @keyframes lm-ring  { 0% { transform: scale(.85); opacity: .7; } 100% { transform: scale(1.5); opacity: 0; } }
        @keyframes lm-rise  { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes lm-warn  { 50% { background-color: rgba(229,72,77,.08); } }
        @keyframes lm-shake { 0%,88%,100% { transform: none; } 92% { transform: rotate(-12deg); } 96% { transform: rotate(10deg); } }
        @keyframes lm-float { 50% { transform: translateY(-5px); } }
        @keyframes lm-draw  { to { stroke-dashoffset: 0; } }
        .lm-float { animation: lm-float 3s ease-in-out infinite; }
        .lm-ripple { transform-origin: 50% 50%; transform-box: fill-box; animation: lm-ring 2.4s ease-out infinite; }
        .lm-ripple.r2 { animation-delay: 1.2s; }
        .lm-dash { stroke-dasharray: 6 6; animation: lm-dashmove 1.2s linear infinite; }
        @keyframes lm-dashmove { to { stroke-dashoffset: -24; } }

        @media (max-width: 575.98px) {
            .lm-hero { padding: .7rem .9rem; }
            .lm-live-pill span.lm-pill-text { display: none; }
        }
        /* Jinko motion pasand nahi, unke liye sab band */
        @media (prefers-reduced-motion: reduce) {
            .lm-scope *, .lm-scope *::before, .lm-scope *::after,
            .lm-btn-track, .lm-btn-icon::after { animation: none !important; transition: none !important; }
        }
    </style>

    <script id="live-map-data" type="application/json">{!! json_encode($people) !!}</script>

    <div class="row g-4 lm-scope">
        <div class="col-xl-8">
            <div class="card tracker-surface-card lm-map-card">
                {{-- Animated radar header --}}
                <div class="lm-hero">
                    <svg class="lm-radar" viewBox="0 0 64 64" role="img" aria-label="Live radar">
                        <defs>
                            <linearGradient id="lmSweep" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0" stop-color="#12b5a0" stop-opacity="0"/>
                                <stop offset="1" stop-color="#12b5a0" stop-opacity=".85"/>
                            </linearGradient>
                        </defs>
                        <circle cx="32" cy="32" r="29" fill="rgba(255,255,255,.04)" stroke="rgba(255,255,255,.28)"/>
                        <circle cx="32" cy="32" r="19" fill="none" stroke="rgba(255,255,255,.2)"/>
                        <circle cx="32" cy="32" r="9"  fill="none" stroke="rgba(255,255,255,.16)"/>
                        <path d="M32 3v58M3 32h58" stroke="rgba(255,255,255,.12)"/>
                        <g class="sweep"><path d="M32 32 L32 3 A29 29 0 0 1 58.5 20 Z" fill="url(#lmSweep)"/></g>
                        <circle class="blip" cx="44" cy="21" r="2.6" fill="#7ff0de"/>
                        <circle class="blip b2" cx="20" cy="40" r="2.6" fill="#7ff0de"/>
                        <circle class="blip b3" cx="40" cy="46" r="2.6" fill="#ffd27a"/>
                        <circle cx="32" cy="32" r="2.4" fill="#fff"/>
                    </svg>
                    <div class="lm-hero-copy">
                        <h2>Live operations</h2>
                        <p>Team location &amp; movement</p>
                    </div>
                    <span class="lm-live-pill"><span class="lm-live-dot"></span><span class="lm-pill-text">Live</span></span>
                </div>

                <div class="card-body p-0 position-relative lm-map-wrap">
                    <div id="live-map-geofence-alerts" class="tracker-live-map-alerts d-none"></div>
                    <div id="live-map" class="tracker-live-map"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            {{-- Who tracks me --}}
            <div class="card tracker-surface-card lm-side-card mb-4" style="animation: lm-rise .6s .1s var(--lm-ease) both">
                <div class="card-header border-0 bg-transparent">
                    <div class="lm-side-title">
                        <span class="lm-panel-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                        <div>
                            <h3 class="tracker-card-title mb-1">Who Tracks Me</h3>
                            <p class="tracker-card-subtitle mb-0">{{ $trackedBy->count() }} tracking connections</p>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    @forelse ($trackedBy as $tracker)
                        <div class="tracker-map-person-row">
                            <div class="tracker-avatar-sm">{{ strtoupper(substr($tracker['user']?->name ?? '?', 0, 1)) }}</div>
                            <div class="tracker-map-person-copy">
                                <strong>{{ $tracker['user']?->name }}</strong>
                                <span class="tracker-status-pill {{ $tracker['has_active_license'] ? 'tracker-status-active' : 'tracker-status-muted' }}">
                                    {{ $tracker['has_active_license'] ? 'Licensed tracker' : ($tracker['license_expired'] ? 'License expired' : 'License inactive') }}
                                </span>
                                @if ($tracker['license_expired'])
                                    <span class="text-muted small d-block">{{ $tracker['user']?->name }}'s license for your account has expired.</span>
                                    <span class="d-flex gap-2 mt-2">
                                        <a href="{{ route('my-licenses.index') }}" class="btn tracker-outline-btn btn-sm" title="Renew license">
                                            <i class="fa-solid fa-rotate"></i>
                                        </a>
                                        <a href="{{ route('my-licenses.plans') }}" class="btn tracker-primary-btn btn-sm" title="Upgrade license">
                                            <i class="fa-solid fa-arrow-up-right-dots"></i>
                                        </a>
                                    </span>
                                @endif
                            </div>
                            @if ($tracker['has_active_license'] && ! $tracker['reverse_exists'] && $hasAvailableLicense)
                                <form method="POST" action="{{ route('my-tracking.requests.back', $tracker['relation']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm tracker-primary-btn" title="Ask to track this person back">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </button>
                                </form>
                            @elseif ($tracker['reverse_exists'])
                                <span class="tracker-status-pill tracker-status-pending">Requested</span>
                            @endif
                        </div>
                    @empty
                        <div class="tracker-empty-state lm-empty py-3">
                            <span class="lm-empty-symbol"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                            {{-- Animated shield + ripple --}}
                            <svg viewBox="0 0 96 96" aria-hidden="true">
                                <circle class="lm-ripple" cx="48" cy="48" r="26" fill="none" stroke="var(--lm-accent)" stroke-width="1.5"/>
                                <circle class="lm-ripple r2" cx="48" cy="48" r="26" fill="none" stroke="var(--lm-accent)" stroke-width="1.5"/>
                                <g class="lm-float">
                                    <path d="M48 20l20 7v14c0 13-8.500 21.500-20 25-11.500-3.500-20-12-20-25V27z" fill="var(--lm-accent-soft)" stroke="var(--lm-accent)" stroke-width="2.4" stroke-linejoin="round"/>
                                    <path d="M39 45l6.500 6.500L58 39" fill="none" stroke="var(--lm-accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="40" stroke-dashoffset="40" style="animation: lm-draw 1.2s .4s ease forwards"/>
                                </g>
                            </svg>
                            <p class="mb-0">No tracking connections</p>
                        </div>
                    @endforelse
                    @if ($trackedBy->isNotEmpty() && ! $hasAvailableLicense)
                        <a href="{{ route('my-licenses.plans') }}" class="btn tracker-outline-btn btn-sm mt-3">Buy a license to track back</a>
                    @endif
                </div>
            </div>

            {{-- Tracked people --}}
            <div class="card tracker-surface-card lm-side-card" style="animation: lm-rise .6s .2s var(--lm-ease) both">
                <div class="card-header border-0 bg-transparent">
                    <div class="lm-side-title">
                        <span class="lm-panel-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                        <div>
                            <h3 class="tracker-card-title mb-1">Tracked People</h3>
                            <p class="tracker-card-subtitle mb-0" id="live-map-people-count">{{ $people->count() }} visible</p>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="live-map-people-list">
                    <div class="lm-status-key"><span><i class="is-moving"></i> Moving</span><span><i class="is-idle"></i> Stationary</span><span><i></i> Offline</span></div>
                    @forelse ($people as $person)
                        <div class="tracker-map-person-row {{ $person['isSelf'] ? 'tracker-map-person-row-self' : '' }} {{ collect($person['geofences'])->contains('is_outside', true) ? 'tracker-map-person-row-warning' : '' }}" data-person-row="{{ $person['id'] }}" role="button">
                            <div class="tracker-avatar-sm">{{ strtoupper(substr($person['name'], 0, 1)) }}</div>
                            <div class="tracker-map-person-copy">
                                <strong>{{ $person['name'] }}</strong>
                                <span data-field="license-expired" class="tracker-status-pill tracker-status-danger {{ ($person['licenseExpired'] ?? false) ? '' : 'd-none' }}">License expired</span>
                                <a data-license-renew href="{{ $person['renewUrl'] ?? '#' }}" class="btn btn-sm tracker-outline-btn {{ ($person['licenseExpired'] ?? false) ? '' : 'd-none' }}"><i class="fa-solid fa-rotate"></i> Renew</a>
                                @if ($person['isSelf'])
                                    <span class="tracker-status-pill tracker-status-info">You</span>
                                @endif
                                <span data-field="status" class="tracker-status-pill {{ $person['isOnline'] ? 'tracker-status-active' : 'tracker-status-muted' }}">
                                    {{ $person['isOnline'] ? 'Online' : 'Offline' }}
                                </span>
                                <span data-field="movement" class="text-muted small {{ $person['isOnline'] && $person['lat'] !== null ? '' : 'd-none' }}">
                                    {{ $person['movementStatus'] === 'moving' ? 'Moving' : 'Idle' }}
                                    · {{ $person['speed'] !== null ? number_format($person['speed'] * 3.6, 1).' km/h' : '—' }}
                                </span>
                                <span data-field="last-seen" class="text-muted small">
                                    @if ($person['lat'] !== null)
                                        {{ $person['lastSeen'] ? \Illuminate\Support\Carbon::parse($person['lastSeen'])->diffForHumans() : 'Never' }}
                                    @elseif ($person['isOnline'])
                                        No location shared yet
                                    @else
                                        Never logged in
                                    @endif
                                </span>
                                <span data-field="geofence-warning" class="tracker-status-pill tracker-status-danger {{ collect($person['geofences'])->contains('is_outside', true) ? '' : 'd-none' }}">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Outside geofence
                                </span>
                                @if ($person['isSelf'])
                                    <span class="text-muted small d-block">
                                        @if (count($person['trackedBy']))
                                            Tracked by: {{ implode(', ', $person['trackedBy']) }}
                                        @else
                                            Abhi aapko koi track nahi kar raha
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <button type="button" class="btn btn-sm tracker-outline-btn tracker-live-report-btn {{ ($person['canReport'] ?? true) ? '' : 'd-none' }}" data-report-user="{{ $person['id'] }}" title="Open reports">
                                <i class="fa-solid fa-chart-pie"></i>
                            </button>
                            @if ($person['canRouteHistory'])
                                <button type="button" class="btn btn-sm tracker-outline-btn" data-route-history="{{ $person['id'] }}" title="Route history" aria-label="Route history"><i class="fa-solid fa-route"></i></button>
                            @endif
                        </div>
                    @empty
                        <div class="tracker-empty-state lm-empty">
                            <span class="lm-empty-symbol"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                            {{-- Animated map pin + dashed route --}}
                            <svg viewBox="0 0 96 96" aria-hidden="true">
                                <path class="lm-dash" d="M14 78c14-4 18-20 32-16s18 8 36-6" fill="none" stroke="var(--lm-accent)" stroke-width="2" stroke-linecap="round" opacity=".55"/>
                                <ellipse class="lm-ripple" cx="48" cy="72" rx="16" ry="5" fill="none" stroke="var(--lm-accent)" stroke-width="1.5"/>
                                <ellipse class="lm-ripple r2" cx="48" cy="72" rx="16" ry="5" fill="none" stroke="var(--lm-accent)" stroke-width="1.5"/>
                                <g class="lm-float">
                                    <path d="M48 14c-12 0-21 9-21 21 0 16 21 34 21 34s21-18 21-34c0-12-9-21-21-21z" fill="var(--lm-accent-soft)" stroke="var(--lm-accent)" stroke-width="2.4" stroke-linejoin="round"/>
                                    <circle cx="48" cy="35" r="7.500" fill="var(--lm-accent)"/>
                                </g>
                            </svg>
                            <p class="mb-0">No tracked people</p>
                        </div>
                    @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="map-marker-options">@json(\App\Models\MapMarkerPreference::OPTIONS)</script>
    <div class="modal fade" id="map-marker-modal" tabindex="-1" aria-labelledby="map-marker-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="map-marker-form">
                <div class="modal-header"><h5 class="modal-title" id="map-marker-title">Map icon</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label" for="map-marker-kind">Person / Vehicle</label>
                    <select class="form-select" id="map-marker-kind" name="kind">
                        @foreach (\App\Models\MapMarkerPreference::OPTIONS as $kind => $option)
                            <option value="{{ $kind }}">{{ $option[0] }}</option>
                        @endforeach
                    </select>
                    <div id="map-marker-upload" class="mt-3 d-none">
                        <label class="form-label" for="map-marker-image">Custom image</label>
                        <input type="file" class="form-control" id="map-marker-image" name="image" accept="image/png,image/jpeg,image/webp">
                    </div>
                    <div class="text-center mt-3" id="map-marker-preview" aria-label="Icon preview"></div>
                    <div class="text-danger small mt-2" id="map-marker-error" role="alert"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn tracker-outline-btn" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn tracker-primary-btn" id="map-marker-save"><i class="fa-solid fa-check"></i> Save</button></div>
            </form>
        </div></div>
    </div>
    @include('live-map._route-history')

    <div class="modal fade" id="live-map-report-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content tracker-modal">
                <div class="modal-header border-0">
                    <div class="d-flex align-items-center gap-3">
                        {{-- Chhota animated bar-chart icon --}}
                        <svg width="40" height="40" viewBox="0 0 40 40" aria-hidden="true">
                            <rect x="4" y="4" width="32" height="32" rx="9" fill="var(--lm-accent-soft)"/>
                            <rect x="11" y="20" width="4" height="9" rx="1.500" fill="var(--lm-accent)"><animate attributeName="height" values="9;14;9" dur="2.4s" repeatCount="indefinite"/><animate attributeName="y" values="20;15;20" dur="2.4s" repeatCount="indefinite"/></rect>
                            <rect x="18" y="12" width="4" height="17" rx="1.500" fill="var(--lm-accent)"><animate attributeName="height" values="17;10;17" dur="2.4s" begin=".3s" repeatCount="indefinite"/><animate attributeName="y" values="12;19;12" dur="2.4s" begin=".3s" repeatCount="indefinite"/></rect>
                            <rect x="25" y="16" width="4" height="13" rx="1.500" fill="var(--lm-accent)"><animate attributeName="height" values="13;18;13" dur="2.4s" begin=".6s" repeatCount="indefinite"/><animate attributeName="y" values="16;11;16" dur="2.4s" begin=".6s" repeatCount="indefinite"/></rect>
                        </svg>
                        <div>
                            <h5 class="modal-title" id="live-map-report-title">Employee Reports</h5>
                            <p class="tracker-card-subtitle mb-0">Realtime Phase 4 monitoring reports</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="tracker-form-label">Report</label>
                            <select class="form-select" id="live-report-type">
                                @foreach (['employee_daily', 'employee_weekly', 'employee_monthly', 'attendance', 'travel', 'mileage', 'idle', 'geofence', 'visit', 'diagnostic', 'communication', 'license_usage', 'organization_summary'] as $type)
                                    <option value="{{ $type }}">{{ Str::headline($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="tracker-form-label">Range</label>
                            <select class="form-select" id="live-report-preset">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="last_7_days">Last 7 Days</option>
                                <option value="last_30_days">Last 30 Days</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="tracker-form-label">Min speed</label>
                            <input type="number" min="0" step="0.1" class="form-control" id="live-report-min-speed">
                        </div>
                        <div class="col-md-2">
                            <label class="tracker-form-label">Max speed</label>
                            <input type="number" min="0" step="0.1" class="form-control" id="live-report-max-speed">
                        </div>
                        <div class="col-md-1 d-grid">
                            <label class="tracker-form-label">&nbsp;</label>
                            <button type="button" class="btn tracker-primary-btn" id="live-report-load" title="Report load karo" aria-label="Report load karo"><i class="fa-solid fa-rotate"></i></button>
                        </div>
                    </div>
                    <div id="live-report-chart-root"></div>
                    <div id="live-report-content-block" class="d-none">
                        <div class="row g-3 mb-3" id="live-report-cards"></div>
                        <div class="row g-3 mb-3" id="live-report-menu"></div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <h6 class="tracker-card-title">Device Diagnostics — GPS / Internet / Battery History</h6>
                                <div class="tracker-report-table-wrap">
                                    <table class="table table-sm tracker-report-table">
                                        <thead><tr><th>Event</th><th>Time</th><th>Battery</th><th>Network</th></tr></thead>
                                        <tbody id="live-report-diagnostics"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <h6 class="tracker-card-title">Geofence Activity</h6>
                                <div class="tracker-report-table-wrap">
                                    <table class="table table-sm tracker-report-table">
                                        <thead><tr><th>Geofence</th><th>Type</th><th>Time</th></tr></thead>
                                        <tbody id="live-report-geofence"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <pre class="mt-3 mb-0 tracker-report-json" id="live-report-output">Loading...</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
