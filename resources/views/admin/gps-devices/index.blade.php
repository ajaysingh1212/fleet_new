@extends('layouts.app')
@section('page-title', 'GPS devices and vehicles')
@section('content')

{{-- ============ Fleet page ka scoped CSS (sirf .fleet-page ke andar apply hoga) ============ --}}
<style>
.fleet-page{--fl-primary:#4f46e5;--fl-primary-2:#7c3aed;--fl-green:#16a34a;--fl-amber:#d97706;--fl-blue:#2563eb;--fl-grey:#64748b;--fl-red:#dc2626;--fl-ink:#0f172a;--fl-muted:#64748b;--fl-line:#e2e8f0;--fl-bg:#f8fafc}
.fleet-page *{box-sizing:border-box}

/* Hero header - gradient + halka sa moving glow */
.fleet-hero{position:relative;overflow:hidden;border-radius:20px;padding:26px 28px;margin-bottom:20px;color:#fff;background:linear-gradient(120deg,#1e1b4b 0%,#4f46e5 55%,#7c3aed 100%);box-shadow:0 18px 40px -18px rgba(79,70,229,.55);animation:flFadeUp .6s ease both}
.fleet-hero::before,.fleet-hero::after{content:"";position:absolute;border-radius:50%;filter:blur(40px);opacity:.35;pointer-events:none}
.fleet-hero::before{width:260px;height:260px;background:#a78bfa;top:-90px;right:-50px;animation:flFloat 9s ease-in-out infinite}
.fleet-hero::after{width:200px;height:200px;background:#38bdf8;bottom:-90px;left:30%;animation:flFloat 11s ease-in-out infinite reverse}
.fleet-hero-inner{position:relative;z-index:1;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between}
.fleet-hero h2{font-size:1.55rem;font-weight:700;margin:0;letter-spacing:-.01em}
.fleet-hero p{margin:4px 0 0;opacity:.8;font-size:.92rem}
.fleet-actions{display:flex;flex-wrap:wrap;gap:10px}
.fleet-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:12px;font-weight:600;font-size:.88rem;text-decoration:none;border:1px solid transparent;transition:transform .2s ease,box-shadow .2s ease,background .2s ease}
.fleet-btn:hover{transform:translateY(-2px);text-decoration:none}
.fleet-btn-light{background:#fff;color:var(--fl-primary)}
.fleet-btn-light:hover{color:var(--fl-primary-2);box-shadow:0 10px 24px -10px rgba(0,0,0,.45)}
.fleet-btn-ghost{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.35);backdrop-filter:blur(6px)}
.fleet-btn-ghost:hover{background:rgba(255,255,255,.22);color:#fff}

/* Stats cards */
.fleet-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px}
.fleet-stat{--c:var(--fl-primary);position:relative;background:#fff;border:1px solid var(--fl-line);border-radius:16px;padding:16px 18px;overflow:hidden;transition:transform .25s ease,box-shadow .25s ease;animation:flFadeUp .6s ease both;animation-delay:calc(var(--i,0)*70ms)}
.fleet-stat:hover{transform:translateY(-4px);box-shadow:0 16px 30px -18px rgba(15,23,42,.35)}
.fleet-stat::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--c)}
.fleet-stat::after{content:"";position:absolute;right:-24px;top:-24px;width:90px;height:90px;border-radius:50%;background:var(--c);opacity:.08}
.fleet-stat small{display:flex;align-items:center;gap:8px;color:var(--fl-muted);font-weight:600;font-size:.76rem;text-transform:uppercase;letter-spacing:.06em}
.fleet-stat strong{display:block;margin-top:8px;font-size:1.9rem;line-height:1;color:var(--fl-ink);font-weight:800;font-variant-numeric:tabular-nums}
.fleet-stat strong.is-bump{animation:flBump .6s ease}
.fleet-dot{width:9px;height:9px;border-radius:50%;background:var(--c);flex:none}
.fleet-stat.is-live .fleet-dot{animation:flPulse 1.8s infinite}
.fleet-stat[data-tone=online],.fleet-stat[data-tone=moving]{--c:var(--fl-green)}
.fleet-stat[data-tone=offline]{--c:var(--fl-grey)}
.fleet-stat[data-tone=inactive]{--c:var(--fl-red)}
.fleet-stat[data-tone=stopped]{--c:var(--fl-amber)}
.fleet-stat[data-tone=parked]{--c:var(--fl-blue)}

/* Main panel card */
.fleet-panel{background:#fff;border:1px solid var(--fl-line);border-radius:20px;padding:20px;box-shadow:0 12px 34px -24px rgba(15,23,42,.35);animation:flFadeUp .7s ease both;animation-delay:.2s}

/* Filter bar */
.fleet-filter{display:grid;grid-template-columns:minmax(220px,2.2fr) minmax(150px,1fr) minmax(150px,1fr) auto;gap:10px;margin-bottom:16px}
.fleet-field{position:relative}
.fleet-field svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--fl-muted);pointer-events:none}
.fleet-field .form-control,.fleet-field .form-select{border-radius:12px;border:1px solid var(--fl-line);background-color:var(--fl-bg);height:44px;font-size:.9rem;transition:border-color .2s,box-shadow .2s,background .2s}
.fleet-field .form-control{padding-left:38px}
.fleet-field .form-control:focus,.fleet-field .form-select:focus{background-color:#fff;border-color:var(--fl-primary);box-shadow:0 0 0 4px rgba(79,70,229,.12)}
.fleet-submit{height:44px;border:0;border-radius:12px;padding:0 24px;font-weight:600;color:#fff;background:linear-gradient(120deg,var(--fl-primary),var(--fl-primary-2));transition:transform .2s,box-shadow .2s}
.fleet-submit:hover{transform:translateY(-2px);box-shadow:0 12px 22px -12px rgba(79,70,229,.8)}

/* Live status strip */
.fleet-live{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:12px;background:var(--fl-bg);border:1px dashed var(--fl-line);margin-bottom:14px}
.fleet-live .beacon{width:10px;height:10px;border-radius:50%;background:var(--fl-amber);flex:none;animation:flPulseAmber 1.6s infinite}
.fleet-live.is-ok .beacon{background:var(--fl-green);animation:flPulse 1.8s infinite}
.fleet-live.is-bad .beacon{background:var(--fl-red);animation:none}
.fleet-live p{margin:0;font-size:.86rem;color:var(--fl-muted)}

/* Toolbar + legend */
.fleet-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:10px}
.fleet-toolbar .tb{border-radius:10px;font-weight:600;font-size:.82rem;padding:7px 14px;border:1px solid var(--fl-line);background:#fff;color:var(--fl-ink);transition:all .2s}
.fleet-toolbar .tb:hover:not(:disabled){border-color:var(--fl-primary);color:var(--fl-primary);transform:translateY(-1px)}
.fleet-toolbar .tb:disabled{opacity:.45;cursor:not-allowed}
.fleet-toolbar .tb-primary{background:var(--fl-primary);border-color:var(--fl-primary);color:#fff}
.fleet-toolbar .tb-primary:hover:not(:disabled){background:var(--fl-primary-2);border-color:var(--fl-primary-2);color:#fff}
.fleet-legend{display:flex;flex-wrap:wrap;gap:12px;margin-left:auto;font-size:.78rem;color:var(--fl-muted)}
.fleet-legend span{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:var(--fl-bg)}
.fleet-legend i{width:9px;height:9px;border-radius:50%;display:inline-block}
.fleet-note{font-size:.8rem;color:var(--fl-muted);margin-bottom:14px}

/* Map + list */
.fleet-map-wrap{position:relative;border-radius:18px;overflow:hidden;border:1px solid var(--fl-line);box-shadow:inset 0 0 0 1px rgba(255,255,255,.6)}
#hardware-gps-map{height:560px;width:100%;background:linear-gradient(135deg,#e0e7ff,#f1f5f9)}
.fleet-map-badge{position:absolute;top:14px;left:14px;z-index:500;display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;font-size:.76rem;font-weight:700;color:var(--fl-ink);background:rgba(255,255,255,.92);backdrop-filter:blur(8px);box-shadow:0 8px 20px -10px rgba(15,23,42,.5)}
.fleet-map-badge .dot{width:8px;height:8px;border-radius:50%;background:var(--fl-green);animation:flPulse 1.8s infinite}
.fleet-side{border:1px solid var(--fl-line);border-radius:18px;overflow:hidden;background:#fff;height:100%}
.fleet-side-head{padding:12px 16px;font-weight:700;font-size:.85rem;border-bottom:1px solid var(--fl-line);background:var(--fl-bg);display:flex;align-items:center;justify-content:space-between}
#hardware-fleet-list{max-height:512px;overflow:auto;border-radius:0}
#hardware-fleet-list .list-group-item{border:0;border-bottom:1px solid var(--fl-line);padding:12px 16px;font-size:.86rem;transition:background .2s,transform .2s,padding-left .2s;animation:flSlideIn .4s ease both}
#hardware-fleet-list .list-group-item:hover,#hardware-fleet-list .list-group-item.active{background:#eef2ff;color:var(--fl-ink);padding-left:22px;cursor:pointer}
#hardware-fleet-list::-webkit-scrollbar{width:6px}
#hardware-fleet-list::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}

/* Table */
.fleet-table-wrap{margin-top:20px;border:1px solid var(--fl-line);border-radius:16px;overflow:hidden}
.fleet-table{margin:0;width:100%}
.fleet-table thead th{background:var(--fl-bg);color:var(--fl-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;padding:13px 16px;border-bottom:1px solid var(--fl-line);white-space:nowrap}
.fleet-table tbody td{padding:14px 16px;font-size:.87rem;vertical-align:middle;border-bottom:1px solid #f1f5f9;color:var(--fl-ink)}
.fleet-table tbody tr{transition:background .2s;animation:flFadeUp .5s ease both;animation-delay:calc(var(--r,0)*35ms)}
.fleet-table tbody tr:hover{background:#f5f3ff}
.fleet-table tbody tr:last-child td{border-bottom:0}
.fleet-table tbody tr.is-updated{animation:flRowFlash 1.4s ease}
.dev-name{font-weight:700;color:var(--fl-primary);text-decoration:none}
.dev-name:hover{color:var(--fl-primary-2);text-decoration:underline}
.dev-imei{display:block;margin-top:2px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.76rem;color:var(--fl-muted)}
.veh-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:8px;background:#eef2ff;color:var(--fl-primary);font-weight:700;font-size:.8rem;letter-spacing:.03em}
.veh-chip.is-none{background:#f1f5f9;color:var(--fl-muted);font-weight:500}
.pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:700;text-transform:capitalize}
.pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
.pill-online,.pill-active{background:#dcfce7;color:#15803d}
.pill-offline{background:#f1f5f9;color:#475569}
.pill-inactive,.pill-blocked{background:#fee2e2;color:#b91c1c}
.pill-maintenance{background:#fef3c7;color:#b45309}
.pill-online::before{animation:flPulse 1.8s infinite}
.speed-chip{display:inline-block;min-width:64px;text-align:center;padding:4px 10px;border-radius:8px;background:#f1f5f9;font-weight:700;font-variant-numeric:tabular-nums}
.speed-chip.is-moving{background:#dcfce7;color:#15803d}
.fleet-pagination{margin-top:16px;display:flex;justify-content:flex-end}

/* Hinglish toast (JS se ya niche wale script se dikhega) */
.fleet-toast-host{position:fixed;right:20px;bottom:20px;z-index:2000;display:flex;flex-direction:column;gap:10px}
.fleet-toast{min-width:260px;max-width:340px;padding:12px 16px;border-radius:14px;color:#fff;font-size:.86rem;font-weight:600;background:#0f172a;box-shadow:0 18px 36px -16px rgba(0,0,0,.6);animation:flToastIn .35s ease both}
.fleet-toast.is-ok{background:linear-gradient(120deg,#15803d,#16a34a)}
.fleet-toast.is-bad{background:linear-gradient(120deg,#b91c1c,#dc2626)}
.fleet-toast.is-out{animation:flToastOut .3s ease both}

@keyframes flFadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes flSlideIn{from{opacity:0;transform:translateX(12px)}to{opacity:1;transform:none}}
@keyframes flFloat{0%,100%{transform:translate(0,0)}50%{transform:translate(-24px,18px)}}
@keyframes flBump{0%{transform:scale(1)}40%{transform:scale(1.18);color:var(--fl-primary)}100%{transform:scale(1)}}
@keyframes flPulse{0%{box-shadow:0 0 0 0 rgba(22,163,74,.55)}70%{box-shadow:0 0 0 9px rgba(22,163,74,0)}100%{box-shadow:0 0 0 0 rgba(22,163,74,0)}}
@keyframes flPulseAmber{0%{box-shadow:0 0 0 0 rgba(217,119,6,.55)}70%{box-shadow:0 0 0 9px rgba(217,119,6,0)}100%{box-shadow:0 0 0 0 rgba(217,119,6,0)}}
@keyframes flRowFlash{0%{background:#ede9fe}100%{background:transparent}}
@keyframes flToastIn{from{opacity:0;transform:translateY(16px) scale(.96)}to{opacity:1;transform:none}}
@keyframes flToastOut{to{opacity:0;transform:translateX(30px)}}

@media (max-width:991px){.fleet-filter{grid-template-columns:1fr 1fr}.fleet-legend{margin-left:0}#hardware-gps-map{height:420px}#hardware-fleet-list{max-height:320px}}
@media (max-width:575px){.fleet-filter{grid-template-columns:1fr}.fleet-hero{padding:20px}}
@media (prefers-reduced-motion:reduce){.fleet-page *{animation:none!important;transition:none!important}}

body:has(#fleet-map-stage) .tracker-page-header,body:has(#fleet-map-stage) .app-footer{display:none}
body:has(#fleet-map-stage) .app-content>.container-fluid{padding:8px}
.fleet-page>.fleet-hero,.fleet-page>.fleet-stats,.fleet-panel>.fleet-filter,.fleet-panel>.fleet-live,.fleet-panel>.fleet-toolbar,.fleet-panel>.fleet-note,.fleet-panel>.fleet-table-wrap,.fleet-panel>.fleet-pagination{display:none}
.fleet-panel{padding:0;border:0;background:transparent;box-shadow:none;animation:none}
.fleet-page.devices-open .fleet-table-wrap,.fleet-page.devices-open .fleet-pagination{display:block}
#fleet-map-stage{position:relative;display:block;height:calc(100dvh - 82px);min-height:550px;margin:0;overflow:hidden;border-radius:20px;background:#e2e8f0}
#fleet-map-stage>.fleet-map-controls{width:auto;max-width:none;margin:0;padding:0}#fleet-map-stage>.col-lg-9,#fleet-map-stage>#fleet-vehicle-drawer,#fleet-map-stage>#fleet-alert-drawer{margin:0}
#fleet-map-stage:fullscreen{height:100dvh;border-radius:0}
#fleet-map-stage>.col-lg-9{width:100%;height:100%;padding:0}.fleet-map-wrap{height:100%;border:0}.fleet-map-badge{display:none}#hardware-gps-map{height:100%!important;width:100%}
.fleet-console-legend{position:absolute;bottom:36px;right:16px;z-index:600;background:#ffffffed;padding:8px 12px;border-radius:10px;font-size:.72rem;color:#334155;box-shadow:0 5px 20px #10243b16}.fleet-console-legend span{margin-left:8px}
.fleet-map-controls{position:absolute;top:16px;left:16px;right:16px;z-index:700;display:flex;justify-content:space-between;gap:12px;pointer-events:none}.fleet-map-controls>*{pointer-events:auto}
.fleet-map-control{display:inline-flex;align-items:center;gap:9px;padding:12px 15px;border:1px solid #ffffff88;border-radius:13px;background:#ffffffef;color:#15304b;font-size:.84rem;font-weight:700;text-decoration:none;box-shadow:0 6px 24px #13233e20;backdrop-filter:blur(12px)}.fleet-map-control:hover{background:#e9f2ff;color:#155eef}.fleet-console-brand{font-size:.9rem;font-weight:900;letter-spacing:.08em;color:#17395b;background:#ffffffeb;border-radius:13px;padding:9px 16px;box-shadow:0 5px 24px #13233e18}.fleet-console-brand small{display:block;font-size:.65rem;letter-spacing:0;font-weight:500;color:#64748b}
#fleet-vehicle-drawer{position:absolute;left:12px;top:80px;bottom:20px;width:360px;max-width:calc(100% - 24px);padding:0;z-index:650;transform:translateX(-110%);transition:transform .25s ease;pointer-events:none}.vehicles-open #fleet-vehicle-drawer{transform:translateX(0);pointer-events:auto}.fleet-side{height:100%;display:flex;flex-direction:column;background:#fffffff5;backdrop-filter:blur(14px);border-radius:18px;box-shadow:0 14px 44px #10243b30;overflow:hidden}.fleet-side-head{flex-shrink:0}#hardware-fleet-list{overflow:auto;flex:1;max-height:none}.fleet-card-footer{padding:12px;border-top:1px solid #e2e8f0;background:#fff}.fleet-card-footer .tb{border:1px solid #dce5ef;background:#f4f7fb;border-radius:8px;font-size:.72rem;padding:6px}
#hardware-fleet-list .list-group-item{margin:5px 12px;padding:14px;border:1px solid #e4ebf5;border-radius:12px;background:#fff;text-align:left;animation:none;display:block;width:calc(100% - 24px)}#hardware-fleet-list [hidden]{display:none!important}.fleet-device-title{font-weight:800;color:#17395b;font-size:.92rem}.fleet-device-meta{display:block;font-size:.72rem;color:#64748b;margin:4px 0}.fleet-device-state{display:block;font-size:.78rem;color:#2563eb}.fleet-device-alert{display:block;color:#b91c1c;font-size:.75rem;font-weight:700;margin-top:5px}.fleet-device-alert:empty{display:none}
#fleet-alert-drawer{position:absolute;right:12px;top:80px;bottom:20px;width:380px;max-width:calc(100% - 24px);z-index:660;padding:20px;background:#fffffff5;backdrop-filter:blur(14px);border-radius:18px;box-shadow:0 14px 44px #10243b30;transform:translateX(115%);transition:transform .25s ease;display:flex;flex-direction:column}.alerts-open #fleet-alert-drawer{transform:translateX(0)}#fleet-alert-history{overflow:auto;flex:1}.fleet-alert-entry{padding:14px 0;border-bottom:1px solid #e2e8f0;font-size:.82rem}.fleet-alert-entry strong{color:#b91c1c}.fleet-alert-entry small{color:#64748b}
#fleet-map-stage .leaflet-top.leaflet-left{top:86px}.fleet-live-console-status{position:absolute;bottom:10px;left:16px;z-index:600;max-width:calc(100% - 40px);padding:7px 12px;border-radius:9px;background:#10243be8;color:#fff;font-size:.7rem;margin:0;pointer-events:none}.fleet-marker.has-violation>div{outline:2px solid #ef4444}.fleet-marker.violation-historical>div{outline-color:#d97706}
.fleet-marker.has-violation .fleet-marker-body{background:#dc2626!important;animation:fleetViolationPulse 1.4s ease-out infinite;position:relative}
.fleet-marker.has-violation .fleet-marker-body::after{content:'!';position:absolute;right:-5px;top:-7px;background:#fff;color:#b91c1c;font-weight:900;border:2px solid #dc2626;border-radius:50%;width:20px;height:20px;text-align:center;line-height:16px}
.fleet-marker.violation-historical .fleet-marker-body{background:#d97706!important;animation-name:fleetWarningPulse}.fleet-marker.violation-historical .fleet-marker-body::after{color:#92400e;border-color:#d97706}
@keyframes fleetViolationPulse{0%{box-shadow:0 0 0 0 #dc26269c}80%,100%{box-shadow:0 0 0 19px #dc262600}}
@keyframes fleetWarningPulse{0%{box-shadow:0 0 0 0 #d977069c}80%,100%{box-shadow:0 0 0 19px #d9770600}}
.fleet-warning-banner{position:absolute;left:16px;bottom:58px;max-width:calc(100% - 32px);z-index:650;border:1px solid #fca5a5;border-left:5px solid #dc2626;border-radius:12px;background:#fff1f2f5;color:#991b1b;padding:12px 16px;box-shadow:0 4px 18px #0f172a20;text-align:left;font-size:.82rem;font-weight:700}.fleet-warning-banner[hidden]{display:none}.fleet-warning-banner.historical{background:#fffbebf5;color:#92400e;border-color:#fcd34d}
#fleet-current-warnings{overflow:auto;max-height:45%;flex-shrink:0}.fleet-current-warning{display:block;text-align:left;width:100%;padding:12px;margin-top:8px;border:1px solid #fecaca;border-left:4px solid #dc2626;border-radius:10px;background:#fff1f2;color:#991b1b;font-size:.8rem}.fleet-current-warning.historical{background:#fffbeb;color:#92400e;border-color:#fcd34d}.fleet-current-warning small{display:block;margin-top:4px}
@media(prefers-reduced-motion:reduce){.fleet-marker.has-violation .fleet-marker-body{animation:none}}
@media(max-width:1250px){.fleet-console-brand{display:none}}@media(max-width:760px){.fleet-map-controls>div:last-child>a{display:none}.fleet-console-brand{display:none}.fleet-map-control{padding:10px;font-size:.72rem}#fleet-map-stage{height:calc(100dvh - 72px)}#fleet-vehicle-drawer{width:320px}}@media(prefers-reduced-motion:reduce){#fleet-vehicle-drawer,#fleet-alert-drawer{transition:none}}
</style>

<div class="fleet-page">

    {{-- ============ Hero header + quick actions ============ --}}
    <div class="fleet-hero">
        <div class="fleet-hero-inner">
            <div>
                <h2>Fleet Command Center</h2>
                <p>Saari gaadiyan, saare GPS devices — ek hi screen par live.</p>
            </div>
            <div class="fleet-actions">
                <a class="fleet-btn fleet-btn-light" href="{{ route('admin.gps-devices.create') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Register device
                </a>
                <a class="fleet-btn fleet-btn-ghost" href="{{ route('admin.vehicles.index') }}">Vehicles</a>
                <a class="fleet-btn fleet-btn-ghost" href="{{ route('admin.gps-devices.diagnostics') }}">Pending / diagnostics</a>
            </div>
        </div>
    </div>

    {{-- ============ Stats cards (data-fleet-stat ko JS update karta hai, isliye attribute same rakha hai) ============ --}}
    <div class="fleet-stats">
        @foreach($stats as $label => $value)
            @php
                $isLoadedStat = in_array($label, ['online','offline','inactive','moving','stopped','parked']);
                $isLive = in_array($label, ['online','moving']);
            @endphp
            <div class="fleet-stat {{ $isLive ? 'is-live' : '' }}" data-tone="{{ $label }}" style="--i:{{ $loop->index }}">
                <small><span class="fleet-dot"></span>{{ $isLoadedStat ? 'Loaded '.ucfirst($label) : ucfirst($label) }}</small>
                <strong data-fleet-stat="{{ $label }}">{{ $value }}</strong>
            </div>
        @endforeach
    </div>

    <div class="fleet-panel">

        {{-- ============ Filter form (name attributes same hain: search, protocol, status) ============ --}}
        <form class="fleet-filter" method="GET">
            <div class="fleet-field">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input aria-label="Search vehicles, devices or IMEI" class="form-control" name="search" value="{{ request('search') }}" placeholder="Vehicle registration, device name ya IMEI">
            </div>
            <div class="fleet-field">
                <select aria-label="Protocol filter" class="form-select" name="protocol">
                    <option value="">All protocols</option>
                    @foreach(config('gps.protocols') as $name => $info)
                        <option @selected(request('protocol') === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fleet-field">
                <select aria-label="Status filter" class="form-select" name="status">
                    <option value="">All statuses</option>
                    @foreach(['online','offline','active','inactive','maintenance','blocked'] as $status)
                        <option @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <button class="fleet-submit">Filter</button>
        </form>

        {{-- ============ Live connection status (id same rakhna, JS yahin text likhta hai) ============ --}}
        <div class="fleet-live" id="fleet-live-box">
            <span class="beacon"></span>
            <p id="hardware-live-status">Connecting to live fleet updates. Positions update when devices report; the page stays in place.</p>
        </div>

        {{-- ============ Map controls + legend (button ids JS se bandhe hain) ============ --}}
        <div class="fleet-toolbar">
            <button type="button" id="hardware-fit-fleet" class="tb tb-primary">Fit loaded vehicles</button>
            <button type="button" id="hardware-sync" class="tb">Sync now</button>
            <button type="button" id="hardware-page-prev" class="tb" disabled>&larr; Previous devices</button>
            <button type="button" id="hardware-page-next" class="tb" @disabled(!$fleetMeta['has_more'])>Next devices &rarr;</button>
            <div class="fleet-legend">
                <span><i style="background:var(--fl-green)"></i>Moving</span>
                <span><i style="background:var(--fl-amber)"></i>Idle / awaiting GPS</span>
                <span><i style="background:var(--fl-blue)"></i>Parked</span>
                <span><i style="background:var(--fl-grey)"></i>Offline</span>
            </div>
        </div>
        <p class="fleet-note">Map aur loaded counters ek baar mein max {{ $fleetMeta['limit'] }} matching devices dikhate hain. Bade fleet ke liye search, filters ya Next devices use karein. Parking latest ignition report se aati hai; GPS freshness alag se dikhti hai.</p>

        {{-- ============ Map + vehicle list ============ --}}
        <div class="row g-3" id="fleet-map-stage">
            <div class="fleet-map-controls"><div class="d-flex gap-2 align-items-center"><button id="fleet-vehicles-toggle" aria-controls="fleet-vehicle-drawer" aria-expanded="false" title="Show vehicles" class="fleet-map-control"><i class="fa-solid fa-car"></i><span>Vehicles</span></button><button id="fleet-alerts-toggle" class="fleet-map-control" title="Violation history"><i class="fa-solid fa-bell"></i><span id="fleet-alert-count">0</span></button><span class="fleet-console-brand">FLEET LIVE <small>Movement. Boundaries. Control.</small></span></div><div class="d-flex gap-2"><a href="{{ route('admin.geofences.index') }}" class="fleet-map-control">Geofences</a><a href="{{ route('admin.vehicles.index') }}" class="fleet-map-control">Manage fleet</a><button id="fleet-fullscreen" class="fleet-map-control" title="Full screen"><i class="fa-solid fa-expand"></i></button></div></div>
            <div class="fleet-console-legend"><span style="color:#16803c">?</span> Moving <span style="color:#2563eb">?</span> Parked <span style="color:#64748b">?</span> Offline <span style="color:#ef4444">?</span> Alert</div><button type="button" id="fleet-warning-banner" class="fleet-warning-banner" hidden aria-live="polite"></button><aside id="fleet-alert-drawer" aria-label="Vehicle violation history"><div class="d-flex justify-content-between"><h5>Violation timeline</h5><button id="fleet-alerts-close" class="btn btn-light" aria-label="Close alert history">?</button></div><small id="fleet-alert-status"></small><div id="fleet-current-warnings" aria-live="polite"></div><h6 class="mt-3">Recorded violations</h6><div id="fleet-alert-history"></div><div class="d-flex justify-content-between mt-3"><button id="fleet-alert-prev" class="btn btn-sm btn-light">Previous</button><button id="fleet-alert-next" class="btn btn-sm btn-light">Next</button></div></aside>
            <div class="col-lg-9">
                <div class="fleet-map-wrap">
                    <div class="fleet-map-badge"><span class="dot"></span>Live map</div>
                    <div id="hardware-gps-map"
                         data-fleet-url="{{ route('admin.gps-devices.fleet') }}"
                         data-positions='@json($positions)'
                         data-meta='@json($fleetMeta)'
                         data-filters='@json(request()->only(['search', 'protocol', 'status']))'></div>
                </div>
            </div>
            <div class="col-lg-3" id="fleet-vehicle-drawer" aria-label="Vehicle list">
                <div class="fleet-side">
                    <div class="fleet-side-head"><div><strong>Your vehicles</strong><small class="d-block text-muted">Select a card to locate it</small></div><button id="fleet-vehicles-close" class="btn btn-light" aria-label="Close vehicle list">?</button></div>
                    <div class="p-3"><input type="search" id="fleet-card-search" class="form-control rounded-pill mb-2" aria-label="Search fleet vehicles" placeholder="Registration, device or IMEI" value="{{ request('search') }}"><select id="fleet-card-status" class="form-select rounded-pill" aria-label="Vehicle connection filter"><option value="">All vehicles</option><option value="online" @selected(request('status')==='online')>Online</option><option value="offline" @selected(request('status')==='offline')>Offline</option></select></div>
                    <div id="hardware-fleet-list" class="list-group"></div><div class="fleet-card-footer"><div class="d-flex justify-content-between align-items-center"><button id="fleet-card-prev" class="btn btn-sm btn-light" aria-label="Previous vehicle cards">?</button><small id="fleet-card-page"></small><button id="fleet-card-next" class="btn btn-sm btn-light" aria-label="Next vehicle cards">?</button></div><div id="fleet-group-controls" class="d-flex gap-2 mt-2"></div><div class="d-flex justify-content-between mt-2"><a class="small" href="{{ route('admin.gps-devices.create') }}">+ Register tracker</a><button type="button" id="fleet-show-devices" class="btn btn-link btn-sm p-0">Manage devices</button></div></div>
                </div>
            </div>
        </div>

        {{-- ============ Devices table (data-* hooks JS update ke liye same hain) ============ --}}
        <div class="fleet-table-wrap table-responsive">
            <table class="table fleet-table">
                <thead>
                    <tr>
                        <th>Device / IMEI</th>
                        <th>Vehicle</th>
                        <th>Protocol / model</th>
                        <th>Authorization / connection</th>
                        <th>Last communication</th>
                        <th>Last fix</th>
                        <th>Speed km/h</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($devices as $device)
                    <tr data-hardware-device="{{ $device->id }}" style="--r:{{ $loop->index }}">
                        <td>
                            <a class="dev-name" href="{{ route('admin.gps-devices.show', $device) }}">{{ $device->name }}</a>
                            <span class="dev-imei">{{ $device->identifier }}</span>
                        </td>
                        <td>
                            @if($device->vehicle?->registration_number)
                                <span class="veh-chip">{{ $device->vehicle->registration_number }}</span>
                            @else
                                <span class="veh-chip is-none">Unassigned</span>
                            @endif
                        </td>
                        <td>{{ $device->protocol }} {{ $device->protocol_version }} / {{ $device->model ?? 'Unknown' }}</td>
                        <td>
                            <span class="pill pill-{{ $device->status }}">{{ $device->status }}</span>
                            <span class="mx-1 text-muted">/</span>
                            <span data-connection class="pill {{ $device->is_online ? 'pill-online' : 'pill-offline' }}">{{ $device->is_online ? 'online' : 'offline' }}</span>
                        </td>
                        <td data-communication>{{ $device->last_packet_at?->timezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d H:i:s T') ?? 'Never' }}</td>
                        <td data-fix>{{ $device->latestLocation?->recorded_at?->timezone(auth()->user()->timezone ?? 'UTC')->format('Y-m-d H:i:s T') ?? 'No valid fix' }}</td>
                        <td><span data-speed class="speed-chip {{ ($device->latestLocation?->speed ?? 0) > 0 ? 'is-moving' : '' }}">{{ $device->latestLocation?->speed ?? 'Unknown' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="fleet-pagination">{{ $devices->links() }}</div>
    </div>
</div>

<div class="fleet-toast-host" id="fleet-toast-host"></div>

{{-- ============ Animation helpers - existing fleet JS ko touch nahi karta, sirf DOM changes observe karta hai ============ --}}
<script>
(function () {
    // Toast dikhane ka helper - window.fleetToast('message', 'ok'|'bad') se kahin se bhi call kar sakte ho
    var host = document.getElementById('fleet-toast-host');
    window.fleetToast = function (msg, type) {
        if (!host) return;
        var t = document.createElement('div');
        t.className = 'fleet-toast ' + (type === 'ok' ? 'is-ok' : type === 'bad' ? 'is-bad' : '');
        t.textContent = msg;
        host.appendChild(t);
        setTimeout(function () {
            t.classList.add('is-out');
            setTimeout(function () { t.remove(); }, 320);
        }, 3200);
    };

    // Stat number badle to halka sa bump animation
    document.querySelectorAll('[data-fleet-stat]').forEach(function (el) {
        new MutationObserver(function () {
            el.classList.remove('is-bump');
            void el.offsetWidth; // reflow taaki animation dobara chale
            el.classList.add('is-bump');
        }).observe(el, { childList: true, characterData: true, subtree: true });
    });

    // Live status text ke hisaab se beacon ka color badlo
    var live = document.getElementById('hardware-live-status');
    var box = document.getElementById('fleet-live-box');
    if (live && box) {
        var lastTone = '';
        new MutationObserver(function () {
            var txt = (live.textContent || '').toLowerCase();
            var tone = /fail|error|lost|disconnect|unable|offline/.test(txt) ? 'bad'
                     : /live|connected|updated|synced|reporting/.test(txt) && !/connecting/.test(txt) ? 'ok' : '';
            box.classList.toggle('is-ok', tone === 'ok');
            box.classList.toggle('is-bad', tone === 'bad');
            if (tone && tone !== lastTone) {
                window.fleetToast(tone === 'ok' ? 'Live updates chalu hain' : 'Live connection mein dikkat aayi', tone);
            }
            lastTone = tone;
        }).observe(live, { childList: true, characterData: true, subtree: true });
    }

    // Table row mein connection / speed badle to row flash kare + pill class sync rahe
    document.querySelectorAll('[data-hardware-device]').forEach(function (row) {
        var conn = row.querySelector('[data-connection]');
        var speed = row.querySelector('[data-speed]');
        var flash = function () {
            row.classList.remove('is-updated');
            void row.offsetWidth;
            row.classList.add('is-updated');
        };
        if (conn) {
            new MutationObserver(function () {
                var on = (conn.textContent || '').trim().toLowerCase() === 'online';
                conn.classList.toggle('pill-online', on);
                conn.classList.toggle('pill-offline', !on);
                flash();
            }).observe(conn, { childList: true, characterData: true, subtree: true });
        }
        if (speed) {
            new MutationObserver(function () {
                var v = parseFloat(speed.textContent);
                speed.classList.toggle('is-moving', !isNaN(v) && v > 0);
                flash();
            }).observe(speed, { childList: true, characterData: true, subtree: true });
        }
    });
})();
</script>
@endsection