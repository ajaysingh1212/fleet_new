<div class="fleet-dashboard" id="fleet-dashboard" data-url="{{ route('dashboard.fleet') }}"

     data-admin="{{ auth()->user()->hasAnyRole(['Super Admin', 'Admin']) ? '1' : '0' }}" data-user="{{ auth()->id() }}" data-hardware-access="{{ auth()->user()->can('viewAny', \App\Models\GpsDevice::class) ? '1' : '0' }}">

    {{-- ===== Lehrata hua tiranga (background) : sirf design, saare layers aria-hidden ===== --}}

    <div class="fl-flag-bg" aria-hidden="true">
        <svg class="fl-flag-svg" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" focusable="false">
            <defs>
                <linearGradient id="flSaffronBase" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#fff4e7"/>
                    <stop offset=".48" stop-color="#fffaf4"/>
                    <stop offset="1" stop-color="#ffe2c2"/>
                </linearGradient>
                <linearGradient id="flSaffronWave" x1="0" y1="0" x2="1" y2=".3">
                    <stop offset="0" stop-color="#ff7a00" stop-opacity=".88"/>
                    <stop offset=".46" stop-color="#ff9933" stop-opacity=".64"/>
                    <stop offset="1" stop-color="#f15a24" stop-opacity=".82"/>
                </linearGradient>
                <linearGradient id="flSaffronLight" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#ffd9ad" stop-opacity=".18"/>
                    <stop offset=".5" stop-color="#ff9b3d" stop-opacity=".44"/>
                    <stop offset="1" stop-color="#fff0dc" stop-opacity=".18"/>
                </linearGradient>
                <linearGradient id="flGreenWave" x1="0" y1="0" x2="1" y2=".2">
                    <stop offset="0" stop-color="#087f23" stop-opacity=".86"/>
                    <stop offset=".48" stop-color="#21a447" stop-opacity=".66"/>
                    <stop offset="1" stop-color="#006b2d" stop-opacity=".9"/>
                </linearGradient>
                <linearGradient id="flGreenLight" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#bcebc4" stop-opacity=".3"/>
                    <stop offset=".55" stop-color="#48bd68" stop-opacity=".42"/>
                    <stop offset="1" stop-color="#d8f4dc" stop-opacity=".2"/>
                </linearGradient>
                <linearGradient id="flWhiteCenter" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fff"/>
                    <stop offset="1" stop-color="#fbfdfb"/>
                </linearGradient>
                <radialGradient id="flChakraDisk">
                    <stop offset="0" stop-color="#fff"/>
                    <stop offset=".78" stop-color="#fff"/>
                    <stop offset="1" stop-color="#edf5f8"/>
                </radialGradient>
                <filter id="flChakraShadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="13"/>
                    <feOffset dy="13"/>
                    <feComponentTransfer><feFuncA type="linear" slope=".22"/></feComponentTransfer>
                    <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
                <path id="fl-spoke" d="M720 450 L713.8 270 Q720 257 726.2 270 Z" fill="#07547d"/>
            </defs>

            <rect width="1440" height="900" fill="url(#flWhiteCenter)"/>

            <!-- Layered saffron ribbons, sweeping inward from the upper edge -->
            <g class="fl-wave fl-wave-saffron">
                <path d="M-80 0H1520V105 C1320 55 1195 66 1050 117 C900 170 810 77 650 108 C490 139 400 248 225 250 C105 252 16 214 -80 185Z" fill="url(#flSaffronLight)"/>
                <path d="M-80 120 C85 70 180 154 325 164 C485 175 560 96 705 112 C865 130 900 217 1050 206 C1225 194 1280 74 1520 93 L1520 190 C1310 157 1240 253 1055 265 C875 277 820 184 675 184 C520 184 425 301 250 294 C110 289 8 246 -80 226Z" fill="url(#flSaffronWave)"/>
                <path d="M-80 172 C90 128 192 208 334 216 C490 225 578 148 714 159 C866 171 928 252 1070 239 C1240 224 1310 132 1520 143 L1520 221 C1320 205 1245 291 1074 302 C893 314 824 224 684 226 C524 228 432 336 256 326 C106 317 9 276 -80 259Z" fill="#f47b20" opacity=".28"/>
                <path d="M-80 224 C90 190 192 258 337 266 C494 274 585 203 718 211 C860 219 938 285 1084 272 C1250 258 1324 188 1520 191" fill="none" stroke="#fff8ef" stroke-width="7" opacity=".76"/>
                <path d="M-80 103 C90 61 194 142 338 151 C493 161 577 89 712 101 C865 114 918 196 1060 186 C1230 174 1300 75 1520 91" fill="none" stroke="#ffbd70" stroke-width="2" opacity=".68"/>
                <path d="M-40 34 C175 4 277 104 440 111 S690 48 842 85 S1110 149 1270 90 S1432 61 1490 72" fill="none" stroke="#fff" stroke-width="1.5" opacity=".52"/>
            </g>

            <!-- Layered green ribbons rise from the lower edge to meet the white center -->
            <g class="fl-wave fl-wave-green">
                <path d="M-80 900H1520V716 C1340 653 1228 673 1080 716 C920 763 845 690 704 708 C550 728 470 827 300 830 C155 833 45 760 -80 728Z" fill="url(#flGreenLight)"/>
                <path d="M-80 650 C100 713 206 645 348 647 C505 649 575 752 724 759 C879 766 955 680 1100 677 C1260 674 1350 742 1520 685 L1520 900H-80Z" fill="url(#flGreenWave)"/>
                <path d="M-80 716 C93 771 209 706 348 709 C507 712 582 813 729 817 C885 821 965 741 1111 736 C1274 730 1364 790 1520 748 L1520 900H-80Z" fill="#006b2d" opacity=".34"/>
                <path d="M-80 782 C88 825 220 773 359 780 C515 788 596 873 744 872 C902 871 981 807 1124 798 C1280 789 1372 836 1520 810" fill="none" stroke="#e7f8e9" stroke-width="7" opacity=".78"/>
                <path d="M-80 619 C99 681 207 612 352 615 C509 618 582 717 730 724 C886 732 962 646 1108 643 C1265 640 1355 705 1520 647" fill="none" stroke="#a8dfb2" stroke-width="3" opacity=".7"/>
                <path d="M-40 862 C145 900 271 819 411 831 S647 909 803 859 S1057 774 1207 819 S1410 865 1490 830" fill="none" stroke="#fff" stroke-width="1.5" opacity=".48"/>
            </g>

            <!-- Fine flowing silk contours -->
            <g fill="none" stroke-linecap="round">
                <path d="M-40 132 C180 45 288 196 458 185 S698 82 862 126 S1114 185 1287 120 S1430 96 1490 112" stroke="#fff" stroke-width="2" opacity=".5"/>
                <path d="M-40 160 C180 75 290 224 463 213 S704 110 868 154 S1118 213 1290 148 S1430 124 1490 140" stroke="#ffd4a8" stroke-width="1.5" opacity=".6"/>
                <path d="M-40 750 C170 680 286 822 458 808 S704 710 868 754 S1118 813 1290 748 S1430 724 1490 740" stroke="#fff" stroke-width="2" opacity=".48"/>
                <path d="M-40 777 C170 707 287 850 461 836 S706 737 870 781 S1118 840 1292 775 S1430 751 1490 767" stroke="#c8edcf" stroke-width="1.5" opacity=".6"/>
            </g>

            <!-- Raised circular Ashoka Chakra medallion -->
            <g class="fl-chakra-medallion" filter="url(#flChakraShadow)">
                <circle cx="720" cy="450" r="238" fill="#fff" opacity=".98"/>
                <circle cx="720" cy="450" r="218" fill="url(#flChakraDisk)" stroke="#f2f7f8" stroke-width="3"/>
                <circle cx="720" cy="450" r="202" fill="none" stroke="#07547d" stroke-width="13"/>
                <circle cx="720" cy="450" r="187" fill="none" stroke="#07547d" stroke-width="2.5" opacity=".9"/>
                <g class="fl-chakra-spokes"><use href="#fl-spoke" transform="rotate(0 720 450)"/><use href="#fl-spoke" transform="rotate(15 720 450)"/><use href="#fl-spoke" transform="rotate(30 720 450)"/><use href="#fl-spoke" transform="rotate(45 720 450)"/><use href="#fl-spoke" transform="rotate(60 720 450)"/><use href="#fl-spoke" transform="rotate(75 720 450)"/><use href="#fl-spoke" transform="rotate(90 720 450)"/><use href="#fl-spoke" transform="rotate(105 720 450)"/><use href="#fl-spoke" transform="rotate(120 720 450)"/><use href="#fl-spoke" transform="rotate(135 720 450)"/><use href="#fl-spoke" transform="rotate(150 720 450)"/><use href="#fl-spoke" transform="rotate(165 720 450)"/><use href="#fl-spoke" transform="rotate(180 720 450)"/><use href="#fl-spoke" transform="rotate(195 720 450)"/><use href="#fl-spoke" transform="rotate(210 720 450)"/><use href="#fl-spoke" transform="rotate(225 720 450)"/><use href="#fl-spoke" transform="rotate(240 720 450)"/><use href="#fl-spoke" transform="rotate(255 720 450)"/><use href="#fl-spoke" transform="rotate(270 720 450)"/><use href="#fl-spoke" transform="rotate(285 720 450)"/><use href="#fl-spoke" transform="rotate(300 720 450)"/><use href="#fl-spoke" transform="rotate(315 720 450)"/><use href="#fl-spoke" transform="rotate(330 720 450)"/><use href="#fl-spoke" transform="rotate(345 720 450)"/></g>
                <circle cx="720" cy="450" r="18" fill="#07547d"/>
                <circle cx="720" cy="450" r="8" fill="#fff"/>
                <circle cx="720" cy="450" r="3.5" fill="#07547d"/>
            </g>
        </svg>
        <div class="fl-flag-sheen"></div>
    </div>

    <script>

        /* reduced-motion walon ke liye lehrati animation band kar do (CSS se SMIL ruk nahi sakta) */

        if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {

            document.querySelectorAll('#fleet-dashboard .fl-flag-svg animate').forEach(function (a) { a.remove(); });

        }

    </script>



    <h2 class="visually-hidden">{{ auth()->user()->hasAnyRole(['Super Admin', 'Admin']) ? 'One country. Your entire fleet.' : 'Your fleet, in focus.' }}</h2>

    <nav class="fleet-icon-dock" aria-label="Dashboard panels">

        {{-- Dock ke upar chhota Ashoka Chakra (sirf design, koi action nahi) --}}

        <span class="fl-chakra fl-dock-mark" aria-hidden="true"></span>

        @foreach([

            ['fleet-vehicles-button', 'fleet-vehicles-drawer', 'fa-car-side', 'Vehicle list'],

            ['fleet-summary-button', 'fleet-summary-drawer', 'fa-chart-pie', 'Summary cards'],

            ['fleet-details-button', 'fleet-details-drawer', 'fa-gauge-high', 'Vehicle information'],

            ['dashboard-information-button', 'dashboard-information-tab', 'fa-chart-line', 'Information & activity'],

        ] as [$button, $panel, $icon, $label])

            <button type="button" class="fleet-dock-button" id="{{ $button }}" data-bs-toggle="offcanvas" data-bs-target="#{{ $panel }}" aria-controls="{{ $panel }}" aria-expanded="false" aria-label="{{ $label }}" title="{{ $label }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></button>

        @endforeach

    </nav>

    <aside class="offcanvas offcanvas-start fleet-summary-drawer fl-drawer" id="fleet-summary-drawer" tabindex="-1" aria-labelledby="fleet-summary-title" data-bs-scroll="true" data-bs-backdrop="false">

        <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="fleet-summary-title">Fleet summary</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close summary cards"></button></div>

        <div class="offcanvas-body"><div class="fleet-dashboard-stats">

        @foreach(['total' => 'Total vehicles', 'online' => 'Online', 'reconnecting' => 'Online · TCP reconnecting', 'moving' => 'Moving', 'offline' => 'Offline / inactive', 'alerts' => 'Geofence warnings'] as $key => $label)

            <div class="fleet-metric fleet-metric-{{ $key }}"><span>{{ $label }}</span><strong data-dashboard-stat="{{ $key }}">—</strong></div>

        @endforeach

        </div></div>

    </aside>

    <div class="fleet-workspace">

        <aside class="offcanvas offcanvas-start fl-drawer" id="fleet-vehicles-drawer" tabindex="-1" aria-labelledby="fleet-vehicles-title" data-bs-scroll="true" data-bs-backdrop="false">

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

            {{-- Legend ke status colors jaan-bujhkar tricolor se alag rakhe hain, taaki moving/idle/offline/alert turant pehchane jaye --}}

            <div class="fleet-map-legend"><span><i style="background:#21a778"></i>Moving</span><span><i style="background:#e5a43a"></i>Idle / parked</span><span><i style="background:#8293a5"></i>Offline / inactive</span><span><i style="background:#ef5454"></i>Geofence warning</span><span>Click a state or vehicle · Zoom in for streets and landmarks</span></div>

            <div class="fleet-map-message" id="fleet-map-message" role="status" hidden></div>

        </div>

    </div>

    <div class="fleet-live-pill"><span></span><span id="fleet-connection" role="status">Connecting to fleet…</span></div>

    <aside class="offcanvas offcanvas-end fleet-telemetry-drawer fl-drawer" id="fleet-details-drawer" tabindex="-1" aria-labelledby="fleet-details-title" data-bs-scroll="true" data-bs-backdrop="false">

        <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="fleet-details-title">Vehicle information</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close vehicle information"></button></div>

        <div class="offcanvas-body"><section class="fleet-detail-panel" id="fleet-vehicle-detail" aria-live="polite"><span class="fleet-eyebrow">VEHICLE TELEMETRY</span><p class="mb-0">Select a vehicle to see its speed, ignition, connection, GPS timestamps and geofence alerts.</p></section></div>

    </aside>

</div>



@push('styles')

<style>

/* ===== Layout (original) ===== */

body:has(#fleet-dashboard) .tracker-page-header,body:has(#fleet-dashboard) .app-footer{display:none}

body:has(#fleet-dashboard) .tracker-content-wrapper{padding-bottom:0}

body:has(#fleet-dashboard) .app-content,body:has(#fleet-dashboard) .app-content>.container-fluid{padding:0}

#fleet-dashboard{position:relative;height:var(--fleet-stage-height,calc(100dvh - 80px));min-height:320px;overflow:hidden}



/* ===== Tricolor theme tokens ===== */

:root{

  --fl-saffron:#ff9933;--fl-saffron-deep:#d96a00;--fl-saffron-soft:#fff1de;

  --fl-green:#138808;--fl-green-bright:#1fae5a;--fl-green-soft:#e5f5ea;

  --fl-navy:#10286b;--fl-navy-ink:#10286b;--fl-navy-soft:#e8edfb;

  --fl-red:#ef5454;--fl-red-soft:#fdeaea;

  --fl-ink:#0f1b3d;--fl-muted:#5d6b8c;

  --fl-surface:#ffffff;--fl-surface-2:#f4f6fb;--fl-line:rgba(16,40,107,.12);

  --fl-glass:color-mix(in srgb,var(--fl-surface) 74%,transparent);         /* flag dikhne dene ke liye translucent panels */

  --fl-glass-strong:color-mix(in srgb,var(--fl-surface) 88%,transparent);

  --fl-band:#dfe5f3;          /* tricolor strip ki beech wali safed patti (light theme par thodi grey taaki dikhe) */

  --fl-badge-ink:#a85200;

  --fl-shadow:0 16px 44px rgba(10,26,71,.14);

  --fl-chakra-c:#2447b8;

}

[data-bs-theme="dark"]{

  --fl-saffron-soft:rgba(255,153,51,.14);--fl-green-soft:rgba(42,212,106,.14);--fl-navy-soft:rgba(111,155,255,.14);--fl-red-soft:rgba(239,84,84,.16);

  --fl-navy-ink:#9db5ff;--fl-ink:#eef2ff;--fl-muted:#9aa8cc;

  --fl-surface:#0e1733;--fl-surface-2:#14204a;--fl-line:rgba(255,255,255,.12);

  --fl-band:#ffffff;--fl-badge-ink:#ffc27d;--fl-shadow:0 18px 50px rgba(0,0,0,.45);--fl-chakra-c:#7fa0ff;

}

@keyframes flPulse{0%{box-shadow:0 0 0 0 rgba(31,174,90,.55)}70%{box-shadow:0 0 0 9px rgba(31,174,90,0)}100%{box-shadow:0 0 0 0 rgba(31,174,90,0)}}

@keyframes flSpin{to{transform:rotate(360deg)}}



/* Ashoka Chakra - sirf CSS se (24 spokes) */

.fl-chakra{display:inline-block;width:22px;height:22px;border-radius:50%;flex:none;

  background:radial-gradient(circle,var(--fl-chakra-c) 0 14%,transparent 15%),repeating-conic-gradient(var(--fl-chakra-c) 0 2deg,transparent 2deg 15deg);

  box-shadow:inset 0 0 0 2px var(--fl-chakra-c);animation:flSpin 40s linear infinite}



/* ===== Reference-inspired Indian tricolour silk background ===== */
#fleet-dashboard{isolation:isolate;background:#fff;color:var(--fl-ink)}
[data-bs-theme="dark"] #fleet-dashboard{background:#0a1230}
#fleet-dashboard .fl-flag-bg{
    position:absolute;inset:-2%;z-index:-1;pointer-events:none;overflow:hidden;
    background:#fff;isolation:isolate;
}
#fleet-dashboard .fl-flag-svg{position:absolute;inset:0;width:100%;height:100%;display:block}
#fleet-dashboard .fl-wave{transform-box:fill-box;transform-origin:center;will-change:transform}
#fleet-dashboard .fl-wave-saffron{animation:flSaffronDrift 18s ease-in-out infinite alternate}
#fleet-dashboard .fl-wave-green{animation:flGreenDrift 21s ease-in-out infinite alternate}
#fleet-dashboard .fl-chakra-medallion{transform-box:fill-box;transform-origin:center;animation:flChakraFloat 9s ease-in-out infinite}
#fleet-dashboard .fl-chakra-spokes{transform-box:fill-box;transform-origin:50% 50%;animation:flChakraRotate 180s linear infinite}
#fleet-dashboard .fl-flag-sheen{
    position:absolute;inset:0;pointer-events:none;
    background:linear-gradient(112deg,transparent 34%,rgba(255,255,255,.42) 49%,transparent 63%);
    background-size:240% 100%;animation:flSilkShine 14s ease-in-out infinite;
}
@keyframes flSaffronDrift{
    0%{transform:translate3d(-8px,-5px,0) scale(1.005)}
    100%{transform:translate3d(9px,8px,0) scale(1.015)}
}
@keyframes flGreenDrift{
    0%{transform:translate3d(7px,5px,0) scale(1.005)}
    100%{transform:translate3d(-9px,-7px,0) scale(1.015)}
}
@keyframes flChakraFloat{
    0%,100%{transform:translateY(0) scale(1)}
    50%{transform:translateY(-5px) scale(1.008)}
}
@keyframes flChakraRotate{to{transform:rotate(360deg)}}
@keyframes flSilkShine{
    0%,15%{background-position:130% 0}
    70%,100%{background-position:-30% 0}
}
[data-bs-theme="dark"] #fleet-dashboard .fl-flag-bg{filter:brightness(.38) saturate(.82)}
@media(max-width:768px){
    #fleet-dashboard .fl-flag-svg{preserveAspectRatio:xMidYMid slice}
    #fleet-dashboard .fl-chakra-medallion{transform:scale(.76);transform-origin:50% 50%}
}
/* Panels transparent, taaki peeche flag dikhe (map canvas bhi) */

#fleet-dashboard .fleet-workspace,#fleet-dashboard .fleet-map-panel{background:transparent}

#fleet-dashboard .fleet-map-canvas,#dashboard-india-map.leaflet-container{background:transparent!important}



/* ===== Icon dock ===== */

#fleet-dashboard .fleet-icon-dock{background:var(--fl-glass);backdrop-filter:blur(14px) saturate(1.2);border:1px solid var(--fl-line);border-left:4px solid var(--fl-saffron);border-radius:18px;box-shadow:var(--fl-shadow)}

#fleet-dashboard .fl-dock-mark{display:block;margin:8px auto 4px}

#fleet-dashboard .fleet-dock-button{background:transparent;color:var(--fl-navy-ink);border:1px solid transparent;border-radius:14px;transition:background .2s,color .2s,transform .2s,box-shadow .2s}

#fleet-dashboard .fleet-dock-button:hover{background:var(--fl-saffron-soft);color:var(--fl-saffron-deep);transform:translateY(-1px)}

#fleet-dashboard .fleet-dock-button:focus-visible{outline:2px solid var(--fl-saffron);outline-offset:2px}

#fleet-dashboard .fleet-dock-button[aria-expanded="true"]{background:linear-gradient(135deg,#ff9933,#ffb566);color:#0a1a47;box-shadow:0 8px 20px rgba(255,153,51,.38)}



/* ===== Drawers ===== */

.fl-drawer{background:var(--fl-glass-strong);backdrop-filter:blur(18px) saturate(1.2);color:var(--fl-ink);border-color:var(--fl-line)!important;box-shadow:var(--fl-shadow)}

.fl-drawer::before{content:"";position:absolute;top:0;bottom:0;width:4px;z-index:2;pointer-events:none;

  background:linear-gradient(180deg,#ff9933 33.33%,var(--fl-band) 33.33% 66.66%,#138808 66.66%)}

.fl-drawer.offcanvas-start::before{right:0}

.fl-drawer.offcanvas-end::before{left:0}

.fl-drawer .offcanvas-header{border-bottom:1px solid var(--fl-line);background:linear-gradient(180deg,var(--fl-glass),transparent)}

.fl-drawer .offcanvas-title{display:flex;align-items:center;gap:.6rem;color:var(--fl-ink);font-weight:800;letter-spacing:-.01em}

.fl-drawer .offcanvas-title::before{content:"";width:18px;height:18px;border-radius:50%;flex:none;

  background:radial-gradient(circle,var(--fl-chakra-c) 0 14%,transparent 15%),repeating-conic-gradient(var(--fl-chakra-c) 0 2deg,transparent 2deg 15deg);

  box-shadow:inset 0 0 0 2px var(--fl-chakra-c)}

.fl-drawer .btn-close{opacity:.6}

.fl-drawer .btn-close:hover{opacity:1}



/* ===== Summary metrics ===== */

#fleet-dashboard .fleet-metric{position:relative;overflow:hidden;background:var(--fl-glass-strong);border:1px solid var(--fl-line);border-radius:16px;padding:14px 16px;box-shadow:0 8px 22px rgba(10,26,71,.07);transition:transform .2s,box-shadow .2s}

#fleet-dashboard .fleet-metric:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(10,26,71,.12)}

#fleet-dashboard .fleet-metric::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:var(--m,var(--fl-navy-ink))}

#fleet-dashboard .fleet-metric-total{--m:#3b6dff}

#fleet-dashboard .fleet-metric-online{--m:#1fae5a}

#fleet-dashboard .fleet-metric-reconnecting{--m:#ff9933}

#fleet-dashboard .fleet-metric-moving{--m:#138808}

#fleet-dashboard .fleet-metric-offline{--m:#8293a5}

#fleet-dashboard .fleet-metric-alerts{--m:#ef5454}

#fleet-dashboard .fleet-metric span{display:block;color:var(--fl-muted);font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase}

#fleet-dashboard .fleet-metric strong{display:block;margin-top:.35rem;color:var(--fl-ink);font-size:1.75rem;font-weight:800;letter-spacing:-.04em}



/* ===== Vehicle explorer ===== */

#fleet-dashboard .fleet-panel-heading{color:var(--fl-muted);font-size:.7rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}

#fleet-dashboard #fleet-visible-count:not(:empty){display:inline-block;padding:.15rem .6rem;border-radius:99px;background:var(--fl-green-soft);color:var(--fl-green-bright);letter-spacing:0}

#fleet-search,#fleet-state-filter{background-color:var(--fl-surface-2);border:1px solid var(--fl-line);border-radius:12px;color:var(--fl-ink)}

#fleet-search:focus,#fleet-state-filter:focus{border-color:var(--fl-saffron);box-shadow:0 0 0 .22rem rgba(255,153,51,.25);background-color:var(--fl-surface)}

#fleet-dashboard .fleet-filter-row button{background:var(--fl-surface);color:var(--fl-muted);border:1px solid var(--fl-line);border-radius:99px;font-size:.78rem;font-weight:700;transition:all .2s}

#fleet-dashboard .fleet-filter-row button:hover{border-color:var(--fl-saffron);color:var(--fl-saffron-deep)}

#fleet-dashboard .fleet-filter-row button[aria-pressed="true"]{background:linear-gradient(135deg,#ff9933,#ffb566);border-color:transparent;color:#0a1a47;box-shadow:0 6px 16px rgba(255,153,51,.32)}

#fleet-dashboard .fleet-empty,#fleet-dashboard .fleet-roster-footer{color:var(--fl-muted);font-size:.8rem}



/* ===== Eyebrow label with mini flag ===== */

.fleet-eyebrow{display:flex;align-items:center;gap:.55rem;color:var(--fl-saffron-deep);font-size:.68rem;font-weight:800;letter-spacing:.2em}

.fleet-eyebrow::before{content:"";width:18px;height:12px;border-radius:2px;flex:none;box-shadow:0 0 0 1px var(--fl-line);

  background:linear-gradient(180deg,#ff9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%)}



/* ===== Map panel ===== */

#fleet-dashboard .fleet-map-toolbar{background:linear-gradient(90deg,#ff9933 33.33%,var(--fl-band) 33.33% 66.66%,#138808 66.66%) bottom left/100% 3px no-repeat,var(--fl-glass);backdrop-filter:blur(14px) saturate(1.2);border-bottom:0;color:var(--fl-ink);box-shadow:0 10px 30px rgba(10,26,71,.10)}

#fleet-dashboard .fleet-map-toolbar strong{color:var(--fl-ink);font-weight:800;letter-spacing:-.01em}

#fleet-dashboard .fleet-map-toolbar .btn-light{background:var(--fl-surface-2);border:1px solid var(--fl-line);color:var(--fl-navy-ink);border-radius:10px;transition:all .2s}

#fleet-dashboard .fleet-map-toolbar .btn-light:hover{background:var(--fl-saffron-soft);border-color:var(--fl-saffron);color:var(--fl-saffron-deep)}

#fleet-dashboard .fleet-map-toolbar .btn-light[aria-pressed="true"]{background:linear-gradient(135deg,#ff9933,#ffb566);border-color:transparent;color:#0a1a47}

#fleet-dashboard .fleet-map-legend{background:var(--fl-glass-strong);backdrop-filter:blur(12px);border:1px solid var(--fl-line);border-radius:14px;color:var(--fl-muted);font-size:.72rem;box-shadow:0 8px 24px rgba(10,26,71,.10)}

#fleet-dashboard .fleet-map-legend i{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;box-shadow:0 0 0 3px rgba(130,147,165,.18)}

#fleet-dashboard .fleet-map-message{background:var(--fl-surface);color:var(--fl-ink);border:1px solid var(--fl-line);border-left:4px solid var(--fl-saffron);border-radius:12px;box-shadow:var(--fl-shadow)}



/* ===== Live pill ===== */

#fleet-dashboard .fleet-live-pill{background:var(--fl-glass-strong);backdrop-filter:blur(12px);color:var(--fl-ink);border:1px solid var(--fl-line);border-radius:99px;box-shadow:var(--fl-shadow);font-weight:600}

#fleet-dashboard .fleet-live-pill>span:first-child{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--fl-green-bright);animation:flPulse 2s infinite}



/* ===== Vehicle telemetry panel ===== */

#fleet-dashboard .fleet-detail-panel{background:var(--fl-surface-2);border:1px solid var(--fl-line);border-top:3px solid var(--fl-saffron);border-radius:16px;padding:1rem 1.1rem;color:var(--fl-ink)}

#fleet-dashboard .fleet-detail-panel p{color:var(--fl-muted);margin-top:.6rem}



/* ===== Information & activity drawer (admin + user dono ke liye) ===== */

.fleet-information-drawer .offcanvas-body{background:color-mix(in srgb,var(--fl-surface-2) 70%,transparent)}

.fleet-information-drawer .tracker-hero-card{position:relative;overflow:hidden;--fl-band:#fff;border:0;border-radius:22px;padding:1.6rem;color:#fff;

  background:linear-gradient(90deg,#ff9933 33.33%,#fff 33.33% 66.66%,#138808 66.66%) bottom left/100% 4px no-repeat,

             radial-gradient(520px 260px at 0% 0%,rgba(255,153,51,.38),transparent 70%),

             radial-gradient(520px 260px at 100% 100%,rgba(19,136,8,.5),transparent 70%),

             linear-gradient(135deg,#0b1d52,#16358a);

  box-shadow:0 22px 50px rgba(10,26,71,.30)}

.fleet-information-drawer .tracker-hero-card>.row{position:relative;z-index:1}

.fleet-information-drawer .fl-hero-chakra{position:absolute;right:-70px;top:-70px;width:260px;height:260px;opacity:.12;--fl-chakra-c:#fff;animation-duration:90s;pointer-events:none}

.fleet-information-drawer .tracker-hero-kicker{display:inline-flex;align-items:center;gap:.5rem;color:#ffb566;font-size:.7rem;font-weight:800;letter-spacing:.22em;text-transform:uppercase}

.fleet-information-drawer .tracker-hero-kicker::before{content:"";width:22px;height:2px;background:#ff9933;box-shadow:0 0 10px #ff9933}

.fleet-information-drawer .tracker-hero-title{color:#fff;font-weight:800;letter-spacing:-.03em}

.fleet-information-drawer .tracker-hero-text{color:rgba(255,255,255,.74)}

.fleet-information-drawer .tracker-hero-metric{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:14px;backdrop-filter:blur(8px)}

.fleet-information-drawer .tracker-hero-metric span{color:rgba(255,255,255,.62);font-size:.66rem;letter-spacing:.14em;text-transform:uppercase}

.fleet-information-drawer .tracker-hero-metric strong{color:#fff;font-weight:800}

.fleet-information-drawer .tracker-hero-action{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.28);color:#fff;border-radius:12px;font-weight:700;transition:all .2s}

.fleet-information-drawer .tracker-hero-action:hover{background:linear-gradient(135deg,#ff9933,#ffb566);border-color:transparent;color:#0a1a47}



.fleet-information-drawer .tracker-filter-card,

.fleet-information-drawer .tracker-surface-card{background:linear-gradient(90deg,#ff9933 33.33%,var(--fl-band) 33.33% 66.66%,#138808 66.66%) top left/100% 3px no-repeat,var(--fl-surface);border:1px solid var(--fl-line);border-radius:18px;color:var(--fl-ink);box-shadow:0 10px 28px rgba(10,26,71,.07)}

.fleet-information-drawer .tracker-section-label{color:var(--fl-muted);font-size:.68rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}

.fleet-information-drawer .tracker-pill-btn{border:1px solid var(--fl-line);background:var(--fl-surface);color:var(--fl-muted);border-radius:99px;font-size:.78rem;font-weight:700;transition:all .2s}

.fleet-information-drawer .tracker-pill-btn:hover{border-color:var(--fl-saffron);color:var(--fl-saffron-deep)}

.fleet-information-drawer .tracker-pill-btn.active{background:linear-gradient(135deg,#ff9933,#ffb566);border-color:transparent;color:#0a1a47;box-shadow:0 6px 16px rgba(255,153,51,.32)}

.fleet-information-drawer .tracker-filter-summary span{color:var(--fl-muted);font-size:.7rem;letter-spacing:.12em;text-transform:uppercase}

.fleet-information-drawer .tracker-filter-summary strong{color:var(--fl-ink)}



/* Stat cards: har card ka apna accent (saffron / green / navy / red) */

.fleet-information-drawer .tracker-stat-card{position:relative;overflow:hidden;background:var(--fl-surface);border:1px solid var(--fl-line);border-radius:18px;padding-left:1.35rem;box-shadow:0 10px 26px rgba(10,26,71,.07);transition:transform .2s,box-shadow .2s;--acc:#ff9933;--acc-ink:var(--fl-saffron-deep);--acc-soft:var(--fl-saffron-soft)}

.fleet-information-drawer .tracker-stat-card:hover{transform:translateY(-3px);box-shadow:0 16px 34px rgba(10,26,71,.13)}

.fleet-information-drawer .tracker-stat-card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:var(--acc)}

.fleet-information-drawer .fl-acc-green{--acc:#1fae5a;--acc-ink:var(--fl-green-bright);--acc-soft:var(--fl-green-soft)}

.fleet-information-drawer .fl-acc-navy{--acc:#3b6dff;--acc-ink:var(--fl-navy-ink);--acc-soft:var(--fl-navy-soft)}

.fleet-information-drawer .fl-acc-red{--acc:#ef5454;--acc-ink:#d63b3b;--acc-soft:var(--fl-red-soft)}

.fleet-information-drawer .tracker-stat-icon{background:var(--acc-soft);color:var(--acc-ink)}

.fleet-information-drawer .tracker-stat-value{color:var(--fl-ink);font-weight:800;letter-spacing:-.03em}

.fleet-information-drawer .tracker-stat-label{color:var(--fl-muted);font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}



.fleet-information-drawer .tracker-card-title{color:var(--fl-ink);font-weight:800;letter-spacing:-.01em}

.fleet-information-drawer .tracker-card-subtitle{color:var(--fl-muted)}

.fleet-information-drawer .tracker-panel-chip{display:inline-flex;align-items:center;gap:.45rem;padding:.3rem .75rem;border-radius:99px;background:var(--fl-green-soft);color:var(--fl-green-bright);font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}

.fleet-information-drawer .tracker-panel-chip::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--fl-green-bright);animation:flPulse 2s infinite}



.fleet-information-drawer .tracker-table{color:var(--fl-ink)}

.fleet-information-drawer .tracker-table thead th{color:var(--fl-muted);font-size:.66rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;border-bottom:2px solid var(--fl-saffron);background:transparent}

.fleet-information-drawer .tracker-table tbody tr{transition:background .15s}

.fleet-information-drawer .tracker-table tbody tr:hover{background:var(--fl-saffron-soft)}

.fleet-information-drawer .tracker-table td{border-color:var(--fl-line);color:var(--fl-ink)}

.fleet-information-drawer .tracker-badge{display:inline-block;padding:.25rem .7rem;border-radius:99px;background:var(--fl-saffron-soft);border:1px solid rgba(255,153,51,.4);color:var(--fl-badge-ink);font-size:.72rem;font-weight:700}



.fleet-information-drawer .tracker-chart-wave{min-height:10px;height:10px;border-radius:99px;background:linear-gradient(90deg,#ff9933 33.33%,var(--fl-band) 33.33% 66.66%,#138808 66.66%);box-shadow:0 6px 18px rgba(255,153,51,.25)}

.fleet-information-drawer .tracker-chart-stats span{color:var(--fl-muted);font-size:.7rem;letter-spacing:.1em;text-transform:uppercase}

.fleet-information-drawer .tracker-chart-stats strong{color:var(--fl-ink);font-weight:800}



.fleet-information-drawer .tracker-mini-item{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem;border-radius:12px;background:var(--fl-surface-2);border:1px solid var(--fl-line);border-left:4px solid var(--fl-saffron);color:var(--fl-muted)}

.fleet-information-drawer .tracker-mini-item:nth-child(3n+2){border-left-color:#3b6dff}

.fleet-information-drawer .tracker-mini-item:nth-child(3n){border-left-color:#1fae5a}

.fleet-information-drawer .tracker-mini-item strong{color:var(--fl-ink);font-weight:800}



.fleet-information-drawer .tracker-notice-item{display:flex;align-items:center;gap:.9rem;padding:.8rem 1rem;margin-bottom:.6rem;border-radius:14px;background:var(--fl-surface-2);border:1px solid var(--fl-line);color:var(--fl-ink)}

.fleet-information-drawer .tracker-notice-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:50%;background:var(--fl-saffron-soft);color:var(--fl-saffron-deep);flex:none}



.fleet-information-drawer .tracker-alert{background:var(--fl-green-soft);color:var(--fl-ink);border:1px solid var(--fl-line);border-left:5px solid var(--fl-green-bright);border-radius:14px}

.fleet-information-drawer .tracker-alert .fa-flask{color:var(--fl-green-bright)}



.fleet-information-drawer .tracker-primary-btn,#supportModal .tracker-primary-btn{background:linear-gradient(135deg,#ff9933,#ffb566);border:0;border-radius:12px;color:#0a1a47;font-weight:800;box-shadow:0 10px 24px rgba(255,153,51,.34);transition:transform .2s,box-shadow .2s}

.fleet-information-drawer .tracker-primary-btn:hover,#supportModal .tracker-primary-btn:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(255,153,51,.5);color:#0a1a47}

.fleet-information-drawer .tracker-outline-btn{background:transparent;border:1.5px solid var(--fl-navy-ink);border-radius:12px;color:var(--fl-navy-ink);font-weight:700;transition:all .2s}

.fleet-information-drawer .tracker-outline-btn:hover{background:var(--fl-green-soft);border-color:var(--fl-green-bright);color:var(--fl-green-bright)}



#supportModal .tracker-modal{background:linear-gradient(90deg,#ff9933 33.33%,var(--fl-band) 33.33% 66.66%,#138808 66.66%) top left/100% 4px no-repeat,var(--fl-surface);color:var(--fl-ink);border:0;border-radius:20px;box-shadow:0 30px 70px rgba(10,26,71,.35)}

#supportModal .modal-title{color:var(--fl-ink);font-weight:800}



@media (prefers-reduced-motion:reduce){
    #fleet-dashboard .fl-wave,
    #fleet-dashboard .fl-chakra-medallion,
    #fleet-dashboard .fl-chakra-spokes,
    #fleet-dashboard .fl-flag-sheen,
    .fl-chakra,
    .fleet-live-pill>span:first-child,
    .tracker-panel-chip::before{animation:none!important}
    #fleet-dashboard *, .fleet-information-drawer *{transition:none!important}
}
</style>

@endpush
