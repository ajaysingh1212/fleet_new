<div class="fleet-dashboard" id="fleet-dashboard" data-url="{{ route('dashboard.fleet') }}"
     data-admin="{{ auth()->user()->hasAnyRole(['Super Admin', 'Admin']) ? '1' : '0' }}" data-user="{{ auth()->id() }}" data-hardware-access="{{ auth()->user()->can('viewAny', \App\Models\GpsDevice::class) ? '1' : '0' }}">
    <h2 class="visually-hidden">{{ auth()->user()->hasAnyRole(['Super Admin', 'Admin']) ? 'One country. Your entire fleet.' : 'Your fleet, in focus.' }}</h2>
    <nav class="fleet-icon-dock" aria-label="Dashboard panels">
        @foreach([
            ['fleet-vehicles-button', 'fleet-vehicles-drawer', 'fa-car-side', 'Vehicle list'],
            ['fleet-summary-button', 'fleet-summary-drawer', 'fa-chart-pie', 'Summary cards'],
            ['fleet-details-button', 'fleet-details-drawer', 'fa-gauge-high', 'Vehicle information'],
            ['dashboard-information-button', 'dashboard-information-tab', 'fa-chart-line', 'Information & activity'],
        ] as [$button, $panel, $icon, $label])
            <button type="button" class="fleet-dock-button" id="{{ $button }}" data-bs-toggle="offcanvas" data-bs-target="#{{ $panel }}" aria-controls="{{ $panel }}" aria-expanded="false" aria-label="{{ $label }}" title="{{ $label }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></button>
        @endforeach
    </nav>
    <aside class="offcanvas offcanvas-start fleet-summary-drawer" id="fleet-summary-drawer" tabindex="-1" aria-labelledby="fleet-summary-title" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="fleet-summary-title">Fleet summary</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close summary cards"></button></div>
        <div class="offcanvas-body"><div class="fleet-dashboard-stats">
        @foreach(['total' => 'Total vehicles', 'online' => 'Online', 'reconnecting' => 'Reconnecting', 'moving' => 'Moving', 'offline' => 'Offline / inactive', 'alerts' => 'Geofence warnings'] as $key => $label)
            <div class="fleet-metric fleet-metric-{{ $key }}"><span>{{ $label }}</span><strong data-dashboard-stat="{{ $key }}">—</strong></div>
        @endforeach
        </div></div>
    </aside>
    <div class="fleet-workspace">
        <aside class="offcanvas offcanvas-start" id="fleet-vehicles-drawer" tabindex="-1" aria-labelledby="fleet-vehicles-title" data-bs-scroll="true" data-bs-backdrop="false">
            <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="fleet-vehicles-title">Vehicle explorer</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close vehicle list"></button></div>
            <div class="offcanvas-body fleet-roster-panel">
            <div class="fleet-panel-heading"><span>Visible vehicles</span><span id="fleet-visible-count"></span></div>
            <label class="visually-hidden" for="fleet-search">Search vehicles</label>
            <input class="form-control" id="fleet-search" type="search" placeholder="Search registration, name or IMEI">
            <div class="fleet-filter-row" role="group" aria-label="Vehicle status filter">
                @foreach(['all' => 'All', 'moving' => 'Moving', 'offline' => 'Offline', 'alerts' => 'Alerts'] as $key => $label)
                    <button type="button" data-fleet-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <label class="visually-hidden" for="fleet-state-filter">Filter by state</label>
            <select class="form-select mb-2" id="fleet-state-filter"><option value="">All states &amp; territories</option><option value="__unlocated">Awaiting GPS location</option></select>
            <div id="dashboard-fleet-roster" class="fleet-dashboard-roster"><p class="fleet-empty">Loading your vehicles…</p></div>
            <div class="fleet-roster-footer" id="fleet-unlocated">Vehicles without a GPS fix remain in this list.</div>
            </div>
        </aside>
        <div class="fleet-map-panel">
            <div class="fleet-map-toolbar">
                <div><span class="fleet-eyebrow">NATIONAL OVERVIEW</span><strong id="fleet-map-title">India · All states &amp; territories</strong></div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-light" id="fleet-india-view" aria-label="India overview" title="India overview"><i class="fa-solid fa-earth-asia" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-light" id="fleet-fit" aria-label="Fit all visible vehicles" title="Fit fleet"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-light" id="fleet-roads" aria-pressed="false" aria-label="Show street map" title="Street map"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-light" id="fleet-refresh" aria-label="Refresh fleet" title="Refresh fleet"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-light" id="fleet-fullscreen" aria-label="Enter full screen" title="Full screen"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="fleet-map-canvas" id="dashboard-india-map" aria-label="Live vehicle map of India"></div>
            <div class="fleet-map-legend"><span><i style="background:#21a778"></i>Moving</span><span><i style="background:#e5a43a"></i>Idle / parked</span><span><i style="background:#8293a5"></i>Offline / inactive</span><span><i style="background:#ef5454"></i>Geofence warning</span><span>Click a state or vehicle · Zoom in for streets and landmarks</span></div>
            <div class="fleet-map-message" id="fleet-map-message" role="status" hidden></div>
        </div>
    </div>
    <div class="fleet-live-pill"><span></span><span id="fleet-connection" role="status">Connecting to fleet…</span></div>
    <aside class="offcanvas offcanvas-end fleet-telemetry-drawer" id="fleet-details-drawer" tabindex="-1" aria-labelledby="fleet-details-title" data-bs-scroll="true" data-bs-backdrop="false">
        <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="fleet-details-title">Vehicle information</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close vehicle information"></button></div>
        <div class="offcanvas-body"><section class="fleet-detail-panel" id="fleet-vehicle-detail" aria-live="polite"><span class="fleet-eyebrow">VEHICLE TELEMETRY</span><p class="mb-0">Select a vehicle to see its speed, ignition, connection, GPS timestamps and geofence alerts.</p></section></div>
    </aside>
</div>

@push('styles')
<style>
body:has(#fleet-dashboard) .tracker-page-header,body:has(#fleet-dashboard) .app-footer{display:none}
body:has(#fleet-dashboard) .tracker-content-wrapper{padding-bottom:0}
body:has(#fleet-dashboard) .app-content,body:has(#fleet-dashboard) .app-content>.container-fluid{padding:0}
#fleet-dashboard{position:relative;height:var(--fleet-stage-height,calc(100dvh - 80px));min-height:320px;overflow:hidden}
</style>
@endpush
