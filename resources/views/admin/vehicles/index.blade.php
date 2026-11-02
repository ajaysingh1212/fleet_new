@extends('layouts.app')
@section('page-title', 'Vehicles')
@push('styles') @vite('resources/css/vehicle-registration.css') @endpush
@push('styles')
<style>
/* ===== Vehicles page: animated, professional card design (flag/tricolor yahan nahi) ===== */
.vh-page{
  --vh-accent:#3b6dff;--vh-accent-2:#12b5c7;--vh-ok:#1fae5a;--vh-warn:#e5a43a;--vh-off:#8293a5;
  --vh-line:color-mix(in srgb,var(--bs-body-color) 12%,transparent);
  --vh-soft:color-mix(in srgb,var(--bs-body-color) 5%,transparent);
  --vh-card:var(--bs-body-bg);
  --vh-ease:cubic-bezier(.2,.8,.2,1);
  position:relative;
}

/* ----- Hero ----- */
.vh-hero{position:relative;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1.25rem;margin-bottom:1.75rem;padding:1.6rem 1.75rem;border:1px solid var(--vh-line);border-radius:24px;overflow:hidden;
  background:linear-gradient(135deg,color-mix(in srgb,var(--vh-accent) 9%,var(--vh-card)),var(--vh-card) 60%)}
.vh-hero::before,.vh-hero::after{content:"";position:absolute;border-radius:50%;filter:blur(46px);opacity:.55;pointer-events:none;animation:vhFloat 14s ease-in-out infinite alternate}
.vh-hero::before{width:260px;height:260px;right:-60px;top:-90px;background:color-mix(in srgb,var(--vh-accent) 38%,transparent)}
.vh-hero::after{width:220px;height:220px;right:180px;bottom:-110px;background:color-mix(in srgb,var(--vh-accent-2) 34%,transparent);animation-duration:18s;animation-delay:-6s}
.vh-hero>*{position:relative;z-index:1}
.vh-page .registration-eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.68rem;font-weight:800;letter-spacing:.2em;color:var(--vh-accent)}
.vh-page .registration-eyebrow::before{content:"";width:22px;height:2px;border-radius:2px;background:linear-gradient(90deg,var(--vh-accent),var(--vh-accent-2))}
.vh-title{margin:.5rem 0 .75rem;font-size:clamp(1.7rem,3.2vw,2.5rem);font-weight:800;letter-spacing:-.035em;
  background:linear-gradient(100deg,var(--bs-body-color) 15%,var(--vh-accent) 65%,var(--vh-accent-2));-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent}
.vh-chips{display:flex;flex-wrap:wrap;gap:.6rem}
.vh-chip{display:inline-flex;align-items:center;gap:.5rem;padding:.4rem .85rem;border:1px solid var(--vh-line);border-radius:99px;background:color-mix(in srgb,var(--vh-card) 80%,transparent);backdrop-filter:blur(8px);font-size:.8rem;color:var(--bs-secondary-color)}
.vh-chip strong{color:var(--bs-body-color);font-size:1rem;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.vh-chip i{width:8px;height:8px;border-radius:50%;background:var(--vh-ok);animation:vhPulse 2.2s infinite}
.vh-chip.is-pending i{background:var(--vh-warn);animation-name:vhPulseWarn}
.vh-actions{display:flex;flex-wrap:wrap;gap:.6rem}
.vh-page .btn{border-radius:12px;font-weight:700;transition:transform .25s var(--vh-ease),box-shadow .25s,background .25s}
.vh-page .btn:hover{transform:translateY(-2px)}
.vh-page .btn-primary{border:0;background:linear-gradient(135deg,var(--vh-accent),#5b8cff);box-shadow:0 10px 24px rgba(59,109,255,.32)}
.vh-page .btn-primary:hover{box-shadow:0 14px 32px rgba(59,109,255,.48)}
.vh-page .btn-light{border:1px solid var(--vh-line)}

/* ----- Search ----- */
.vh-search{position:relative;display:flex;gap:.6rem;margin-bottom:1.75rem}
.vh-search-field{position:relative;flex:1}
.vh-search-field>i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--bs-secondary-color);transition:color .2s;pointer-events:none}
.vh-search-field .form-control{height:100%;padding:.8rem 1rem .8rem 2.7rem;border:1px solid var(--vh-line);border-radius:14px;background:var(--vh-card);transition:border-color .2s,box-shadow .2s}
.vh-search-field .form-control:focus{border-color:var(--vh-accent);box-shadow:0 0 0 .25rem color-mix(in srgb,var(--vh-accent) 22%,transparent)}
.vh-search-field:focus-within>i{color:var(--vh-accent)}

/* ----- Section heading ----- */
.vh-section-title{display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem;font-weight:800;letter-spacing:-.02em}
.vh-section-title .badge{padding:.4rem .75rem;border-radius:99px;background:var(--vh-soft)!important;color:var(--bs-body-color)!important}
.vh-section-title::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,var(--vh-line),transparent)}

/* ----- Vehicle card ----- */
.vh-card{position:relative;border:1px solid var(--vh-line)!important;border-radius:22px!important;background:var(--vh-card);overflow:hidden;
  box-shadow:0 10px 28px rgba(15,27,61,.07)!important;
  opacity:0;transform:translateY(26px) scale(.98);
  animation:vhRise .75s var(--vh-ease) forwards;animation-delay:min(calc(var(--i,0)*70ms),630ms);
  transition:transform .4s var(--vh-ease),box-shadow .4s,border-color .4s}
.vh-card::before{content:"";position:absolute;left:0;right:0;top:0;height:3px;z-index:3;background:linear-gradient(90deg,var(--vh-accent),var(--vh-accent-2));transform:scaleX(0);transform-origin:left;transition:transform .5s var(--vh-ease)}
.vh-card::after{content:"";position:absolute;inset:0;z-index:2;pointer-events:none;opacity:0;transition:opacity .35s;
  background:radial-gradient(380px circle at var(--mx,50%) var(--my,0%),color-mix(in srgb,var(--vh-accent) 14%,transparent),transparent 42%)}
.vh-card:hover{transform:translateY(-8px)!important;border-color:color-mix(in srgb,var(--vh-accent) 40%,var(--vh-line))!important;box-shadow:0 26px 54px rgba(59,109,255,.20)!important}
.vh-card:hover::before{transform:scaleX(1)}
.vh-card:hover::after{opacity:1}

/* Media (photo / placeholder) */
.vh-media{position:relative;height:178px;overflow:hidden;background:var(--vh-soft)}
.vh-media::after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(to top,rgba(8,15,40,.62),rgba(8,15,40,0) 58%)}
.vh-media .vehicle-card-photo{display:block;width:100%;height:100%;object-fit:cover;transition:transform .9s var(--vh-ease)}
.vh-card:hover .vh-media .vehicle-card-photo{transform:scale(1.09)}
.vh-media .vehicle-card-placeholder{display:flex;align-items:center;justify-content:center;width:100%;height:100%;
  background:linear-gradient(120deg,color-mix(in srgb,var(--vh-accent) 18%,var(--vh-card)),color-mix(in srgb,var(--vh-accent-2) 20%,var(--vh-card)),color-mix(in srgb,var(--vh-accent) 18%,var(--vh-card)));background-size:200% 100%;animation:vhShift 9s ease-in-out infinite alternate}
.vh-media .vehicle-card-placeholder i{font-size:3.4rem;color:var(--vh-accent);opacity:.8;animation:vhBob 4.5s ease-in-out infinite;filter:drop-shadow(0 10px 14px rgba(59,109,255,.28))}

.vh-type{position:absolute;left:14px;bottom:12px;z-index:1;display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .75rem;border-radius:99px;
  background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(10px);color:#fff;font-size:.74rem;font-weight:700;letter-spacing:.02em}
.vh-live{position:absolute;right:14px;top:14px;z-index:1;display:inline-flex;align-items:center;gap:.45rem;padding:.32rem .7rem;border-radius:99px;
  background:rgba(8,15,40,.55);border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(10px);color:#fff;font-size:.72rem;font-weight:700}
.vh-live i{width:8px;height:8px;border-radius:50%;background:var(--vh-off)}
.vh-live.is-online i{background:var(--vh-ok);animation:vhPulse 2s infinite}
.vh-live.is-none i{background:transparent;border:1.5px dashed rgba(255,255,255,.7)}

/* Body */
.vh-body{padding:1.25rem 1.35rem 1.35rem!important}
.vh-idrow{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.vh-plate{display:inline-flex;align-items:stretch;border:2px solid #14213d;border-radius:9px;background:#fff;overflow:hidden;box-shadow:0 4px 12px rgba(15,27,61,.14);transition:transform .35s var(--vh-ease)}
.vh-card:hover .vh-plate{transform:scale(1.04) rotate(-1deg)}
.vh-plate-ind{display:grid;place-items:center;padding:0 .4rem;background:#1b3a9a;color:#fff;font-size:.56rem;font-weight:800;letter-spacing:.08em}
.vh-plate strong{padding:.25rem .65rem;color:#14213d;font-size:1.05rem;font-weight:800;letter-spacing:.07em;font-variant-numeric:tabular-nums;white-space:nowrap}
.vh-status{padding:.28rem .7rem;border-radius:99px;background:var(--vh-soft);color:var(--bs-secondary-color);font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.vh-card[data-status="active"] .vh-status{background:color-mix(in srgb,var(--vh-ok) 15%,transparent);color:var(--vh-ok)}
.vh-card[data-status="maintenance"] .vh-status,.vh-card[data-status="pending"] .vh-status{background:color-mix(in srgb,var(--vh-warn) 18%,transparent);color:#a97212}
.vh-name{margin:.85rem 0 .35rem;color:var(--bs-secondary-color);font-size:.95rem}
.vh-fix{display:inline-flex;align-items:center;gap:.5rem;margin-bottom:.35rem;padding:.25rem .65rem;border-radius:99px;background:color-mix(in srgb,var(--vh-warn) 14%,transparent);color:#a97212;font-size:.72rem;font-weight:700}
.vh-fix i{width:10px;height:10px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;animation:vhSpin 1s linear infinite}

/* Specs grid */
.vh-specs{display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin:.9rem 0 1.1rem}
.vh-specs>div{min-width:0;padding:.55rem .7rem;border:1px solid transparent;border-radius:12px;background:var(--vh-soft);transition:border-color .25s,background .25s,transform .25s var(--vh-ease)}
.vh-specs>div.is-wide{grid-column:1/-1}
.vh-card:hover .vh-specs>div{border-color:var(--vh-line)}
.vh-specs>div:hover{background:color-mix(in srgb,var(--vh-accent) 8%,transparent);transform:translateX(3px)}
.vh-specs dt{margin:0;color:var(--bs-secondary-color);font-size:.62rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.vh-specs dd{margin:.15rem 0 0;color:var(--bs-body-color);font-size:.86rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vh-specs dd.is-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.04em}

/* Actions */
.vh-card-actions{display:flex;flex-wrap:wrap;gap:.5rem;padding-top:1rem;border-top:1px dashed var(--vh-line)}
.vh-card-actions .btn{padding:.4rem .85rem;font-size:.8rem}
.vh-card-actions .btn-primary i{margin-left:.35rem;transition:transform .25s var(--vh-ease)}
.vh-card-actions .btn-primary:hover i{transform:translateX(4px)}

/* Empty state */
.vh-empty{position:relative;overflow:hidden;padding:3.5rem 1.5rem!important;border:2px dashed var(--vh-line)!important;border-radius:24px!important;text-align:center;
  background:radial-gradient(420px 220px at 50% 0%,color-mix(in srgb,var(--vh-accent) 10%,transparent),transparent 70%)!important;animation:vhRise .7s var(--vh-ease) both}
.vh-empty-icon{display:grid;place-items:center;width:84px;height:84px;margin:0 auto 1rem;border-radius:26px;background:linear-gradient(135deg,var(--vh-accent),var(--vh-accent-2));color:#fff;font-size:2rem;box-shadow:0 18px 36px rgba(59,109,255,.35);animation:vhBob 4s ease-in-out infinite}
.vh-empty a{font-weight:700;text-decoration:none}

/* Pagination */
.vh-page .pagination{gap:.3rem;flex-wrap:wrap}
.vh-page .page-link{border-radius:10px!important;border:1px solid var(--vh-line);transition:all .2s}
.vh-page .page-link:hover{transform:translateY(-2px);border-color:var(--vh-accent)}
.vh-page .page-item.active .page-link{border-color:transparent;background:linear-gradient(135deg,var(--vh-accent),#5b8cff)}

/* ----- Keyframes ----- */
@keyframes vhRise{to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes vhFloat{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(-30px,24px,0) scale(1.12)}}
@keyframes vhShift{to{background-position:100% 0}}
@keyframes vhBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
@keyframes vhSpin{to{transform:rotate(360deg)}}
@keyframes vhPulse{0%{box-shadow:0 0 0 0 rgba(31,174,90,.6)}70%{box-shadow:0 0 0 8px rgba(31,174,90,0)}100%{box-shadow:0 0 0 0 rgba(31,174,90,0)}}
@keyframes vhPulseWarn{0%{box-shadow:0 0 0 0 rgba(229,164,58,.6)}70%{box-shadow:0 0 0 8px rgba(229,164,58,0)}100%{box-shadow:0 0 0 0 rgba(229,164,58,0)}}

@media (max-width:575.98px){
  .vh-hero{padding:1.2rem}
  .vh-search{flex-wrap:wrap}.vh-search .btn{flex:1}
  .vh-media{height:158px}
  .vh-plate strong{font-size:.95rem}
}
@media (prefers-reduced-motion:reduce){
  .vh-card{opacity:1;transform:none;animation:none}
  .vh-hero::before,.vh-hero::after,.vh-chip i,.vh-live i,.vh-fix i,.vehicle-card-placeholder,.vehicle-card-placeholder i,.vh-empty,.vh-empty-icon{animation:none!important}
  .vh-card,.vh-card *{transition:none!important}
}
</style>
@endpush
@section('content')
<div class="vh-page">

    {{-- ===== Hero: title + live counters + primary actions ===== --}}
    <div class="vh-hero">
        <div>
            <span class="registration-eyebrow">YOUR CONNECTED FLEET</span>
            <h2 class="vh-title">Every vehicle. One place.</h2>
            <div class="vh-chips">
                <span class="vh-chip"><i></i><strong data-count="{{ $vehicles->total() }}">{{ $vehicles->total() }}</strong> registered vehicles</span>
                <span class="vh-chip is-pending"><i></i><strong data-count="{{ $pendingDevices->total() }}">{{ $pendingDevices->total() }}</strong> awaiting vehicle number</span>
            </div>
        </div>
        <div class="vh-actions">
            <a class="btn btn-light" href="{{ route('admin.device-models.index') }}">Device models</a>
            <a class="btn btn-primary px-4" href="{{ route('admin.vehicles.create') }}">+ Register vehicle</a>
        </div>
    </div>

    {{-- ===== Search ===== --}}
    <form class="vh-search" method="get">
        <div class="vh-search-field">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input name="search" aria-label="Search vehicles" class="form-control" value="{{ request('search') }}" placeholder="Search registration, vehicle name or IMEI">
        </div>
        <button class="btn btn-dark px-4">Search</button>
        <a class="btn btn-outline-primary text-nowrap" href="{{ route('admin.gps-devices.index') }}"><i class="fa-solid fa-location-dot me-2" aria-hidden="true"></i>Live map</a>
    </form>

    @include('admin.vehicles.pending-devices')

    <h4 class="vh-section-title">Registered vehicles <span class="badge">{{ $vehicles->total() }}</span></h4>

    <div class="row g-4 vh-grid">
    @forelse($vehicles as $vehicle)
        @php
            $device = $vehicle->gpsDevice;
            $icon = config('fleet.icons.'.($vehicle->details['icon'] ?? $vehicle->details['type'] ?? 'car').'.icon','fa-car-side');
            $photoIndex = collect($vehicle->details['documents'] ?? [])->search(fn($d) => ($d['kind'] ?? null) === 'vehicle' && str_starts_with($d['mime'] ?? '', 'image/'));
        @endphp
        <div class="col-md-6 col-xl-4">
            {{-- --i: har card ki entry animation ko staggered delay deta hai --}}
            <article class="card vehicle-card vh-card h-100 border-0 shadow-sm rounded-4 overflow-hidden" style="--i: {{ $loop->index }}" data-status="{{ strtolower((string) $vehicle->status) }}">
                <div class="vh-media">
                    @if($photoIndex !== false)
                        <img class="vehicle-card-photo" src="{{ route('admin.vehicles.document',[$vehicle,$photoIndex,'preview'=>1]) }}" alt="{{ $vehicle->name }}" loading="lazy">
                    @else
                        <div class="vehicle-card-placeholder"><i class="fa-solid {{ $icon }}"></i></div>
                    @endif
                    <span class="vh-type"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ config('fleet.icons.'.($vehicle->details['icon'] ?? 'car').'.label','Vehicle') }}</span>
                    @if($device)
                        <span class="vh-live {{ $device->is_online ? 'is-online' : 'is-offline' }}"><i></i>{{ $device->is_online ? 'Online' : 'Offline' }}</span>
                    @else
                        <span class="vh-live is-none"><i></i>No device</span>
                    @endif
                </div>

                <div class="card-body vh-body">
                    <div class="vh-idrow">
                        <div class="vh-plate"><span class="vh-plate-ind">IND</span><strong>{{ $vehicle->registration_number }}</strong></div>
                        <span class="vh-status">{{ ucfirst($vehicle->status) }}</span>
                    </div>
                    <p class="vh-name">{{ $vehicle->name }}</p>
                    @if($device && !$device->latest_location_id)
                        <span class="vh-fix"><i></i>Awaiting first GPS fix</span>
                    @endif

                    <dl class="vh-specs">
                        <div class="is-wide"><dt>IMEI</dt><dd class="is-mono">{{ $device?->identifier ?: 'Not installed' }}</dd></div>
                        <div><dt>Device model</dt><dd>{{ $device?->model ?: '-' }}</dd></div>
                        <div><dt>Device status</dt><dd>{{ ucfirst($device?->status ?? 'Unassigned') }}</dd></div>
                        <div><dt>SIM provider</dt><dd>{{ $device?->details['network_provider'] ?? '-' }}</dd></div>
                        <div><dt>SIM number</dt><dd class="is-mono">{{ $device?->details['sim_number'] ?? '-' }}</dd></div>
                        <div class="is-wide"><dt>Driver</dt><dd>{{ $vehicle->details['driver_name'] ?? 'Not assigned' }}</dd></div>
                    </dl>

                    <div class="vh-card-actions">
                        <a class="btn btn-sm btn-primary" href="{{ route('admin.vehicles.show',$vehicle) }}">View details<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.vehicles.edit',$vehicle) }}">Edit</a>
                        <a class="btn btn-sm btn-light" href="{{ route('admin.gps-devices.index') }}">Map</a>
                    </div>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12">
            <div class="card card-body vh-empty">
                <div class="vh-empty-icon"><i class="fa-solid fa-car-side"></i></div>
                <h4>{{ request('search') ? 'No matching vehicles' : 'Build your connected fleet' }}</h4>
                <p class="text-muted">Register a vehicle and its GPS device together.</p>
                <a href="{{ route('admin.vehicles.create') }}">Register vehicle &rarr;</a>
            </div>
        </div>
    @endforelse
    </div>

    <div class="mt-4">{{ $vehicles->links() }}</div>
</div>

<script>
(function () {
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Mouse ke saath card par halka spotlight chalta hai */
    var grid = document.querySelector('.vh-grid');
    if (grid && !reduce) {
        grid.addEventListener('pointermove', function (e) {
            var card = e.target.closest('.vh-card');
            if (!card) return;
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    }

    /* Hero ke counters 0 se upar tak count-up hote hain */
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (reduce || isNaN(target) || target < 1) return;
        var start = null, dur = 900;
        function tick(t) {
            if (start === null) start = t;
            var p = Math.min((t - start) / dur, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(tick);
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    });
})();
</script>
@endsection