@extends('layouts.app')

@section('page-eyebrow', 'Phase 4')
@section('page-title', 'Route Replay')

@push('styles')
    <style>
        .rr-page{--rr-ink:#17304b;--rr-muted:#71849a;--rr-line:#e3ebf1;color:var(--rr-ink)}
        .rr-filter,.rr-panel,.rr-stat{border:1px solid var(--rr-line);border-radius:18px;background:#fff;box-shadow:0 9px 28px #183b5608}
        .rr-filter{padding:20px}.rr-label{display:block;font-size:11px;font-weight:750;color:#50677e;margin:0 0 7px}.rr-filter .form-select{min-height:44px;border-radius:10px;border-color:var(--rr-line)}
        .rr-title{font-size:22px;font-weight:800;letter-spacing:-.03em}.rr-subtitle{font-size:12px;color:var(--rr-muted)}
        .rr-stat{padding:17px 19px;height:100%}.rr-stat span{font-size:11px;color:var(--rr-muted)}.rr-stat strong{display:block;font-size:23px;margin-top:7px;font-weight:800;letter-spacing:-.04em}.rr-stat i{color:#079e99;margin-right:6px}
        .rr-map-wrap{position:relative;overflow:hidden;border-radius:0 0 18px 18px}#vehicle-replay-map{height:min(58vh,610px);min-height:360px;background:#eaf1f4}.rr-map-heading{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;border-bottom:1px solid var(--rr-line)}
        .rr-legend{display:flex;gap:14px;font-size:10px;color:var(--rr-muted)}.rr-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px;background:#11a880}.rr-dot.end{background:#e45252}.rr-dot.route{background:#178ca0}
        .rr-controls{display:grid;grid-template-columns:auto minmax(120px,1fr) auto;gap:12px;align-items:center;padding:16px 20px;border-top:1px solid var(--rr-line)}.rr-controls button{border:0;border-radius:10px;background:#e8f6f5;color:#087f80;width:40px;height:40px}.rr-controls input{width:100%;accent-color:#078d8d}.rr-current{font-size:11px;color:var(--rr-muted);min-width:150px;text-align:right}.rr-speed{width:90px!important;min-height:36px!important;font-size:12px}
        .rr-message{padding:38px 20px;text-align:center;color:var(--rr-muted)}.rr-message i{font-size:30px;color:#16a69c;display:block;margin-bottom:12px}.rr-marker{display:grid;place-items:center;width:38px;height:38px;border:3px solid #fff;border-radius:50%;background:#17304b;color:#fff;box-shadow:0 3px 14px #17304b60;font-size:16px}
        @media(max-width:767px){.rr-controls{grid-template-columns:auto 1fr}.rr-current{grid-column:1/-1;text-align:left}.rr-legend{display:none}#vehicle-replay-map{min-height:300px}}
    </style>
@endpush

@section('content')
<div class="rr-page">
    <div class="rr-filter mb-4"><div class="row g-3 align-items-end">
        <div class="col-lg-4 col-md-6"><label class="rr-label" for="replay-vehicle">Vehicle</label><select class="form-select" id="replay-vehicle"><option value="">Select a vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }} · {{ $vehicle->name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="rr-label" for="replay-preset">Time window</label><select class="form-select" id="replay-preset"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="last_7_days">Last 7 days</option><option value="last_30_days" selected>Last 30 days</option></select></div>
        <div class="col-lg-2 col-md-6"><label class="rr-label" for="replay-speed">Playback speed</label><select class="form-select" id="replay-speed"><option value="1">1×</option><option value="2">2×</option><option value="4">4×</option><option value="8">8×</option></select></div>
        <div class="col-lg-4 col-md-6"><button class="btn tracker-primary-btn w-100" id="replay-load"><i class="fa-solid fa-route me-2"></i>Build Replay</button></div>
    </div></div>
    <div id="replay-message" class="rr-panel rr-message mb-4"><i class="fa-solid fa-map-location-dot"></i>Choose a vehicle and time window, then build its route replay.</div>
    <div id="replay-result" hidden>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><div class="text-uppercase fw-bold small" style="letter-spacing:.15em;color:#079e99">Vehicle journey</div><h2 class="rr-title mb-1" id="replay-heading">Route Replay</h2><div class="rr-subtitle" id="replay-window"></div></div><div class="rr-subtitle" id="replay-point-count"></div></div>
        <div class="row g-3 mb-4" id="replay-stats"></div>
        <div class="rr-panel mb-4"><div class="rr-map-heading"><div><div class="text-uppercase fw-bold small" style="letter-spacing:.13em;color:#079e99">Route Explorer</div><strong>Your journey, in motion</strong></div><div class="rr-legend"><span><i class="rr-dot"></i>Start</span><span><i class="rr-dot end"></i>End</span><span><i class="rr-dot route"></i>Recorded route</span></div></div><div class="rr-map-wrap"><div id="vehicle-replay-map"></div></div>
            <div class="rr-controls"><div class="d-flex gap-2"><button id="replay-play" type="button" aria-label="Play route"><i class="fa-solid fa-play"></i></button><button id="replay-restart" type="button" aria-label="Restart route"><i class="fa-solid fa-rotate-left"></i></button></div><input id="replay-seek" type="range" min="0" max="0" value="0" aria-label="Route position"><div class="rr-current"><span id="replay-time">—</span><select id="replay-speed-control" class="form-select form-select-sm d-inline-block rr-speed ms-2"><option value="1">1×</option><option value="2">2×</option><option value="4">4×</option><option value="8">8×</option></select></div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/admin-vehicle-replay.js'])
@endpush
