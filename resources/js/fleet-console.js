import axios from 'axios';
import L from 'leaflet';
import {liveGeofenceWarnings, warningLabel} from './fleet-geofence-alerts.js';
export function initFleetConsole({map, vehicles, redraw, onSearch}) {
    const $=id=>document.getElementById(id), stage=$('fleet-map-stage'), drawer=$('fleet-vehicle-drawer');
    if(!stage||!drawer)return null;
    const controls=stage.querySelector('.fleet-map-controls>div:last-child');
    for(const id of ['hardware-fit-fleet','hardware-sync']){const b=$(id);b.className='fleet-map-control';b.textContent=id==='hardware-sync'?'Sync':'Fit';controls.prepend(b);}
    for(const id of ['hardware-page-prev','hardware-page-next'])$('fleet-group-controls').append($(id));
    const status=$('hardware-live-status');status.classList.add('fleet-live-console-status');stage.append(status);
    const zones=L.layerGroup().addTo(map), alerts=new Map(), alertVersions=new Map();let zoneRules=[], zoneVersion=0;let cardPage=1, historyPage=1, timer, stopped=false, request=0;
    const date=v=>v?new Date(v.replace(' ','T')+(/(?:Z|[+-]\d\d:\d\d)$/.test(v)?'':'Z')).toLocaleString():'Still active';
    const fingerprints=new Map();
    function observe(p) {
        const warnings=getAlerts(p.device_id), signature=warnings.map(a=>`${a.rule_id}:${a.type}:${a.historical}`).sort().join('|');
        if (signature && fingerprints.get(p.device_id)!==signature) {
            window.fleetToast?.(`${p.registration??p.imei??'Tracker'}: ${warnings.map(a=>a.historical?`${a.label} - GPS update pending`:a.label).join(', ')}`, 'bad');
        }
        fingerprints.set(p.device_id,signature);
    }
    function renderCurrent() {
        const entries=[...vehicles.values()].flatMap(p=>getAlerts(p.device_id).map(a=>({p,a})));
        const banner=$('fleet-warning-banner');
        banner.hidden=!entries.length;
        banner.classList.toggle('historical',entries.length>0&&entries.every(({a})=>a.historical));
        banner.textContent=entries.length?`${entries.length} warning${entries.length>1?'s':''} ? ${entries[0].p.registration??entries[0].p.imei??'Tracker'}: ${entries[0].a.label}${entries[0].a.historical?' ? Last known location, GPS update pending':''} ? View details`:'';
        const current=$('fleet-current-warnings');current.replaceChildren();
        for (const {p,a} of entries) {
            const row=document.createElement('button');row.type='button';row.className=`fleet-current-warning${a.historical?' historical':''}`;
            for (const [tag,value] of [['strong',`${p.registration??p.imei??'Tracker'} ? ${a.label}`],['div',a.geofence_name],['small',a.detail],['small',`GPS: ${date(a.recorded_at??p.recorded_at)}${a.limit!=null?' ? '+(a.speed??'Unknown')+' / '+a.limit+' km/h':''}`]]) {
                const el=document.createElement(tag);el.textContent=value;row.append(el);
            }
            row.onclick=()=>{if(Number.isFinite(p.latitude)&&Number.isFinite(p.longitude))map.setView([p.latitude,p.longitude],16);};
            current.append(row);
        }
    }
    function open(show){drawer.inert=!show;drawer.setAttribute('aria-hidden',String(!show));stage.classList.toggle('vehicles-open',show);$('fleet-vehicles-toggle').setAttribute('aria-expanded',String(show));if(!show)$('fleet-vehicles-toggle').focus();}
    drawer.inert=true;drawer.setAttribute('aria-hidden','true');
    $('fleet-vehicles-toggle').onclick=()=>open(!stage.classList.contains('vehicles-open'));
    $('fleet-vehicles-close').onclick=()=>open(false);
    const openAlerts=show=>{stage.classList.toggle('alerts-open',show);$('fleet-alert-drawer').inert=!show;$('fleet-alert-drawer').setAttribute('aria-hidden',String(!show));};openAlerts(false);
    const escape=e=>{if(e.key==='Escape'){open(false);openAlerts(false);}};document.addEventListener('keydown',escape);
    $('fleet-show-devices').onclick=()=>{const page=stage.closest('.fleet-page');page.classList.toggle('devices-open');if(page.classList.contains('devices-open'))page.querySelector('.fleet-table-wrap').scrollIntoView({behavior:'smooth'});};
    $('fleet-fullscreen').onclick=()=>document.fullscreenElement?document.exitFullscreen():stage.requestFullscreen?.();
    const resize=()=>map.invalidateSize();document.addEventListener('fullscreenchange',resize);
    function paginate(){const rows=[...$('hardware-fleet-list').children];cardPage=Math.min(cardPage,Math.max(1,Math.ceil(rows.length/10)));
        rows.forEach((r,i)=>{r.hidden=i<(cardPage-1)*10||i>=cardPage*10;});$('fleet-card-page').textContent=rows.length?`${(cardPage-1)*10+1}–${Math.min(cardPage*10,rows.length)} of ${rows.length}`:'No matching vehicles';
        $('fleet-card-prev').disabled=cardPage<=1;$('fleet-card-next').disabled=cardPage*10>=rows.length;
        $('fleet-alert-count').textContent=[...vehicles.values()].reduce((n,p)=>n+(getAlerts(p.device_id).length),0);
        renderCurrent();
    }
    $('fleet-card-prev').onclick=()=>{cardPage--;paginate();};$('fleet-card-next').onclick=()=>{cardPage++;paginate();};
    const search=()=>{clearTimeout(timer);timer=setTimeout(()=>{cardPage=1;onSearch({search:$('fleet-card-search').value,status:$('fleet-card-status').value});},350);};
    $('fleet-card-search').oninput=search;$('fleet-card-status').onchange=search;
    async function loadZones(){const version=++zoneVersion,ids=[...new Set([...vehicles.values()].map(p=>p.vehicle_id).filter(Boolean))];if(!vehicles.size){zoneRules=[];zones.clearLayers();return;}try{let page=1,last=1,items=[];do{const{data}=await axios.get('/admin/vehicle-geofences',{params:{vehicle_ids:ids,device_ids:[...vehicles.values()].map(p=>p.device_id).filter(Boolean),per_page:250,page}});items.push(...data.data);last=data.last_page;page++;}while(page<=last);if(stopped||version!==zoneVersion)return;zoneRules=items;zones.clearLayers();for(const z of items){if(z.status!=='active')continue;const shape=z.type==='circle'?L.circle([z.center_lat,z.center_lng],{radius:z.radius_meters,color:'#2563eb',weight:2,fillOpacity:.08}):L.polygon(z.points.map(p=>[p.latitude,p.longitude]),{color:'#2563eb',weight:2,fillOpacity:.08});const label=document.createElement('span');label.textContent=`${z.name} - ${z.registration??z.imei??'Tracker'}`;shape.bindTooltip(label).addTo(zones);}
        for(const p of vehicles.values()){observe(p);redraw(p.device_id);}paginate();
        }catch{$('fleet-alert-status').textContent='Geofence rules unavailable. Use Sync to retry.';}}
    const getAlerts=id=>{const p=vehicles.get(`device:${id}`);return liveGeofenceWarnings(p,zoneRules,(alerts.get(Number(id))??[]).filter(a=>Number(a.vehicle_id)===Number(p?.vehicle_id)));};
    async function loadActive(){const version=++request, versions=new Map(alertVersions), ids=[...vehicles.values()].map(p=>p.device_id).filter(Boolean);for(const id of fingerprints.keys())if(!ids.includes(id))fingerprints.delete(id);for(const id of alerts.keys())if(!ids.includes(id)){alerts.delete(id);alertVersions.delete(id);}if(!ids.length)return;
        try{let page=1,last=1,received=new Map();do{const{data}=await axios.get('/admin/vehicle-geofences/alerts',{params:{active:1,device_ids:ids,per_page:250,page}});last=data.last_page;for(const a of data.data){const id=Number(a.gps_device_id);if(!received.has(id))received.set(id,[]);received.get(id).push(a);}page++;}while(page<=last);
            if(stopped||version!==request)return;for(const id of ids){if((alertVersions.get(Number(id))??0)===(versions.get(Number(id))??0))alerts.set(Number(id),received.get(Number(id))??[]);redraw(id);}paginate();
        }catch{$('fleet-alert-status').textContent='Alert history unavailable. Use refresh to retry.';}
    }
    async function history(){try{const{data}=await axios.get('/admin/vehicle-geofences/alerts',{params:{page:historyPage}});if(stopped)return;const list=$('fleet-alert-history');list.replaceChildren();
        for(const a of data.data){const row=document.createElement('article');row.className='fleet-alert-entry';for(const[tag,value]of[['strong',`${a.registration_number??a.imei} · ${warningLabel(a.type)}`],['div',a.geofence_name],['small',`${date(a.started_at)} → ${date(a.ended_at)}`],['div',`${a.speed??'—'} km/h${a.limit!==null?' · limit '+a.limit:''}`]]){const el=document.createElement(tag);el.textContent=value;row.append(el);}list.append(row);}
        if(!data.data.length)list.textContent='No confirmed violations recorded yet. Current GPS warnings appear above.';$('fleet-alert-prev').disabled=historyPage<=1;$('fleet-alert-next').disabled=!data.next_page_url;$('fleet-alert-status').textContent='Times shown in your local timezone.';
    }catch{$('fleet-alert-status').textContent='Could not load history. Try again.';}}
    $('fleet-warning-banner').onclick=()=>{openAlerts(true);history();};
    $('fleet-alerts-toggle').onclick=()=>{openAlerts(!stage.classList.contains('alerts-open'));if(stage.classList.contains('alerts-open'))history();};$('fleet-alerts-close').onclick=()=>openAlerts(false);
    $('fleet-alert-prev').onclick=()=>{historyPage--;history();};$('fleet-alert-next').onclick=()=>{historyPage++;history();};
    window.Echo?.private('admin.gps.alerts').listen('.vehicle.geofence.alert',p=>{if(stopped||!vehicles.has(`device:${p.device_id}`))return;const stamp=p.observed_at??p.occurred_at;if(stamp&&(alertVersions.get(Number(p.device_id))??'')>stamp)return;alertVersions.set(Number(p.device_id),stamp);alerts.set(Number(p.device_id),p.active_alerts);redraw(p.device_id);paginate();
        observe(vehicles.get(`device:${p.device_id}`));
        if(stage.classList.contains('alerts-open'))history();});
    const alertChannel=window.Echo?.private('admin.gps.alerts');
    let ruleTimer;
    alertChannel?.listen('.vehicle.geofence.rules.changed',()=>{clearTimeout(ruleTimer);ruleTimer=setTimeout(()=>{loadZones();loadActive();if(stage.classList.contains('alerts-open'))history();},100);});
    alertChannel?.subscribed(()=>{loadZones();loadActive();});
    loadActive();loadZones();paginate();
    return {refresh:paginate,observe,loadActive,loadZones,hasAlerts:id=>getAlerts(id).length>0,getAlerts,dispose(){stopped=true;clearTimeout(timer);clearTimeout(ruleTimer);document.removeEventListener('keydown',escape);document.removeEventListener('fullscreenchange',resize);window.Echo?.leave('admin.gps.alerts');zones.remove();}};
}
