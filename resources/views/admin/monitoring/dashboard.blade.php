@extends('layouts.app')

@section('page-eyebrow', 'Phase 4')
@section('page-title', 'Analytics Dashboard')

@section('content')
    <style>
        .fleet-analytics{--fa-ink:#152b45;--fa-muted:#71839a;--fa-teal:#00a79d;color:var(--fa-ink)}.fa-hero{border:0;border-radius:24px;color:#fff;background:radial-gradient(circle at 85% 10%,#30d2c044,transparent 28%),linear-gradient(120deg,#11253f,#173e5a 65%,#087d80);box-shadow:0 18px 45px #183b5620}.fa-hero h2{font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800}.fa-hero p{color:#d1e3ec}.fa-vehicle-select{max-width:440px;background:#fff;border:0;border-radius:12px;padding:12px}.fa-range-select{max-width:220px;background:#fff;border:0;border-radius:12px;padding:12px}.fa-stat{border:1px solid #e8eef3;border-radius:18px;background:linear-gradient(145deg,#fff,#f7fbfd);box-shadow:0 8px 22px #17314c08;transition:transform .2s}.fa-stat:hover{transform:translateY(-3px)}.fa-stat small{color:#71839a}.fa-stat strong{display:block;font-size:1.65rem;font-weight:800;letter-spacing:-.04em;margin-top:8px}.fa-stat i{color:#079e99;font-size:1.1rem}.fa-bar{height:9px;border-radius:9px;background:#e9f0f4;overflow:hidden}.fa-bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#00a79d,#56d9b8)}.fa-empty{border:1px dashed #bfd1dc;border-radius:18px;padding:28px;color:#71839a;text-align:center}
    </style>
    <div class="fleet-analytics" data-vehicle-options="{{ $vehicles->toJson() }}">
    <div class="card fa-hero mb-4"><div class="card-body p-4 p-lg-5 d-flex flex-wrap justify-content-between align-items-center gap-4"><div><div class="text-uppercase small fw-bold mb-2" style="letter-spacing:.16em;color:#70e7d8">Fleet intelligence</div><h2 class="mb-2">Vehicle Analytics</h2><p class="mb-0">Movement, utilization and GPS performance across your assigned fleet.</p></div><div class="d-flex flex-wrap gap-3"><div><label class="small fw-bold mb-2" for="analytics-vehicle">Choose vehicle</label><select class="form-select fa-vehicle-select" id="analytics-vehicle"><option value="">All accessible vehicles</option></select></div><div><label class="small fw-bold mb-2" for="analytics-range">Time window</label><select class="form-select fa-range-select" id="analytics-range"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="last_7_days">Last 7 days</option><option value="last_30_days" selected>Last 30 days</option></select></div></div></div></div>
    <div id="analytics-summary" class="row g-3 mb-4"></div>
    <div class="card tracker-surface-card"><div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center"><div><h3 class="tracker-card-title mb-1">Fleet movement</h3><p class="tracker-card-subtitle mb-0">Distance recorded today</p></div><span class="badge rounded-pill text-bg-light" id="analytics-fleet-count">Loading fleet</span></div><div class="card-body" id="analytics-fleet"></div></div>
    </div>
    <div class="row g-3 mb-4 d-none">
        @foreach (['Today Distance', 'Working Hours', 'Idle Time', 'Trips', 'Geofence Visits', 'GPS Accuracy'] as $label)
            <div class="col-md-4 col-xl-2">
                <div class="card tracker-surface-card h-100">
                    <div class="card-body">
                        <p class="tracker-card-subtitle mb-1">{{ $label }}</p>
                        <h3 class="tracker-card-title mb-0" data-dashboard-card="{{ Str::slug($label, '_') }}">--</h3>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent"><h3 class="tracker-card-title mb-0">Movement Trends</h3></div>
                <div class="card-body"><canvas id="movement-chart" height="120"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card tracker-surface-card">
                <div class="card-header border-0 bg-transparent"><h3 class="tracker-card-title mb-0">Leaderboards</h3></div>
                <div class="card-body" id="leaderboard-list"></div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', async () => {
            const picker=document.getElementById('analytics-vehicle'),range=document.getElementById('analytics-range'),root=document.getElementById('analytics-summary'),fleet=document.getElementById('analytics-fleet');
            JSON.parse(document.querySelector('.fleet-analytics').dataset.vehicleOptions).forEach(v=>picker.add(new Option(`${v.registration_number} · ${v.name}`,v.id)));
            const safe=(v)=>String(v??'—');
            async function load(){const params={preset:range.value};if(picker.value)params.vehicle_id=picker.value;const {data}=await axios.get('/api/v1/dashboard-analytics',{params});const d=data.data;const rows=d.vehicles??[];
                const selected=d.selected;const cards=selected?[selected]:rows;root.replaceChildren();
                if(!cards.length){root.innerHTML='<div class="col-12"><div class="fa-empty"><i class="fa-solid fa-truck-fast fs-3 d-block mb-2"></i>No assigned vehicles or movement data available.</div></div>';fleet.replaceChildren();document.getElementById('analytics-fleet-count').textContent='0 vehicles';return;}
                const metrics=[['Distance today','total_distance_km','km','fa-route'],['Moving time','moving_seconds','time','fa-stopwatch'],['Idle time','idle_seconds','time','fa-pause'],['Average speed','average_speed_kmh','km/h','fa-gauge-high'],['Peak speed','maximum_speed_kmh','km/h','fa-bolt'],['GPS points','point_count','','fa-satellite-dish']];
                const aggregate=cards.length===1?cards[0]:{total_distance_km:cards.reduce((s,v)=>s+(v.total_distance_km||0),0),moving_seconds:cards.reduce((s,v)=>s+(v.moving_seconds||0),0),idle_seconds:cards.reduce((s,v)=>s+(v.idle_seconds||0),0),point_count:cards.reduce((s,v)=>s+(v.point_count||0),0),average_speed_kmh:null,maximum_speed_kmh:Math.max(0,...cards.map(v=>v.maximum_speed_kmh||0))};
                metrics.forEach(([label,key,unit,icon])=>{let value=aggregate[key];if(key.endsWith('_seconds'))value=`${(value/3600).toFixed(1)} h`;else if(unit)value=`${safe(value)} ${unit}`;const col=document.createElement('div');col.className='col-6 col-lg-4 col-xxl-2';col.innerHTML=`<div class="fa-stat h-100 p-3"><i class="fa-solid ${icon}"></i><small class="d-block mt-3">${label}</small><strong>${safe(value)}</strong></div>`;root.append(col);});
                document.querySelector('.card.tracker-surface-card .tracker-card-subtitle').textContent=`DISTANCE · ${range.options[range.selectedIndex].text.toUpperCase()}`;document.getElementById('analytics-fleet-count').textContent=`${rows.length||1} vehicle${(rows.length||1)===1?'':'s'}`;fleet.replaceChildren();cards.forEach(v=>{const row=document.createElement('div');row.className='py-3 border-bottom';const title=document.createElement('div');title.className='d-flex justify-content-between gap-3 mb-2';const name=document.createElement('strong');name.textContent=`${v.registration_number} · ${v.name}`;const dist=document.createElement('span');dist.className='fw-bold';dist.textContent=`${safe(v.total_distance_km)} km`;title.append(name,dist);const bar=document.createElement('div');bar.className='fa-bar';const fill=document.createElement('span');fill.style.width=`${Math.min(100,Math.max(3,(v.total_distance_km||0)*4))}%`;bar.append(fill);row.append(title,bar);fleet.append(row);});
            }
            picker.addEventListener('change',load);range.addEventListener('change',load);try{await load()}catch{root.innerHTML='<div class="col-12"><div class="fa-empty">Could not load vehicle analytics.</div></div>';}
        });
    </script>
@endsection
