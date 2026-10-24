@extends('layouts.app')
@section('page-title','Vehicle geofences')
@push('scripts') @vite(['resources/js/vehicle-geofences.js']) @endpush
@section('content')
<style>
.vf-workspace{display:grid;grid-template-columns:350px 1fr;height:calc(100dvh - 190px);min-height:650px;border:1px solid #dce5ef;border-radius:22px;overflow:hidden;background:#fff;box-shadow:0 18px 60px #12243b15}.vf-panel{padding:22px;overflow:auto;background:linear-gradient(#f7fbff,#fff)}.vf-panel h2{font-size:1.35rem;font-weight:800}.vf-panel label{display:block;font-size:.78rem;font-weight:700;margin-top:13px;color:#52647b}.vf-panel input,.vf-panel select{width:100%;border:1px solid #dce5ef;padding:10px;border-radius:10px;background:#fff}.vf-zone{padding:14px;border:1px solid #dce5ef;border-radius:13px;margin:10px 0;background:#fff}.vf-zone button{border:0;background:none;color:#2563eb}.vf-tools{display:flex;gap:6px;flex-wrap:wrap;margin:18px 0}.vf-tools button{border:0;background:#e7effb;color:#244a78;padding:9px 12px;border-radius:9px}.vf-map{position:relative}.vf-note{font-size:.8rem;color:#6b7e92}.vf-errors{color:#b91c1c;white-space:pre-line}.vf-save{background:#155eef!important;color:#fff!important;border:0!important;margin-top:18px;font-weight:700}.vf-history{max-height:220px;overflow:auto;font-size:.8rem}.vf-history article{border-bottom:1px solid #dde6f0;padding:10px 0}@media(max-width:800px){.vf-workspace{grid-template-columns:290px 1fr;min-height:650px}.vf-panel{padding:14px}}@media(max-width:560px){.vf-workspace{display:flex;flex-direction:column;height:auto}.vf-panel{max-height:440px}.vf-map{height:500px}}
</style>
<div class="vf-workspace" id="vehicle-geofence-workspace">
 <aside class="vf-panel"><span class="text-primary small fw-bold">FLEET PROTECTION</span><h2>Boundaries that watch over your fleet.</h2>
 <p class="vf-note">Draw a zone, choose a vehicle and set its speed limits. A GPS tracker must be assigned to that vehicle.</p>
 <form id="vf-form"><label for="vf-name">Geofence name</label><input id="vf-name" required maxlength="255" placeholder="Warehouse perimeter">
 <label for="vf-vehicle-search">Find vehicle</label><input id="vf-vehicle-search" type="search" placeholder="Search registration or name">
 <label for="vf-vehicle">Vehicle</label><select id="vf-vehicle" required><option value="">Choose a vehicle</option></select>
 <div class="d-flex justify-content-between mt-1"><button type="button" id="vf-vehicles-prev" class="btn btn-sm btn-light">Previous</button><button type="button" id="vf-vehicles-next" class="btn btn-sm btn-light">More vehicles</button></div>
 <div class="row"><div class="col-6"><label for="vf-min">Min km/h</label><input id="vf-min" type="number" min="0" max="300" placeholder="Optional"></div><div class="col-6"><label for="vf-max">Max km/h</label><input id="vf-max" type="number" min="0" max="300" placeholder="Optional"></div></div>
 <p class="vf-note mt-2">Minimum speed applies while moving. Parked vehicles do not trigger minimum-speed alerts. Boundaries use fresh GPS fixes.</p>
 <div class="vf-tools"><button type="button" data-vf-draw="circle">◯ Circle</button><button type="button" data-vf-draw="polygon">⬡ Polygon</button><button type="button" data-vf-draw="rectangle">▱ Rectangle</button></div>
 <p id="vf-shape-status" class="vf-note">Choose a shape, then draw it on the map.</p><div id="vf-errors" class="vf-errors" role="alert"></div>
 <button class="vf-save btn w-100" type="submit">Save vehicle geofence</button><button type="button" id="vf-reset" class="btn btn-sm btn-link">Create another zone</button></form>
 <hr><h6>Vehicle zones</h6><div id="vf-zones"></div><div class="d-flex justify-content-between"><button id="vf-zones-prev" class="btn btn-sm btn-light">Previous zones</button><button id="vf-zones-next" class="btn btn-sm btn-light">Next zones</button></div>
 <hr><h6>Violation history</h6><div id="vf-history" class="vf-history"></div><div class="d-flex justify-content-between"><button id="vf-history-prev" class="btn btn-sm btn-light">Previous</button><button id="vf-history-next" class="btn btn-sm btn-light">Next</button></div>
 <a class="btn btn-outline-primary btn-sm mt-3" href="{{ route('admin.gps-devices.index') }}">Open live fleet map →</a></aside>
 <div class="vf-map" id="vf-map"></div>
</div>
@endsection
