import './bootstrap';
import { Modal } from 'bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.getElementById('task-live-map');
if (root) {
    const data = JSON.parse(document.getElementById('field-task-data').textContent);
    const map = L.map(root).setView(data.latest_location ? [data.latest_location.lat, data.latest_location.lng] : [data.stops[0].lat, data.stops[0].lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    const trafficUrl = document.documentElement.dataset.trafficTiles;
    if (trafficUrl) L.tileLayer(trafficUrl, { maxZoom: 20, opacity: 0.72 }).addTo(map);
    const stopLayers = new Map();
    const stopIcon = (stop) => L.divIcon({ className: `task-numbered-marker ${stop.status === 'completed' ? 'is-complete' : ''}`, html: `<span>${stop.status === 'completed' ? '✓' : stop.sequence}</span>`, iconSize: [34, 40], iconAnchor: [17, 40] });
    data.stops.forEach((stop) => {
        const marker = L.marker([stop.lat, stop.lng], { icon: stopIcon(stop) }).addTo(map).bindPopup(`<strong>${stop.title}</strong><br>${stop.address || ''}<br>Arrival radius: ${stop.radius} m`);
        const circle = L.circle([stop.lat, stop.lng], { radius: stop.radius, color: stop.status === 'completed' ? '#10b981' : '#2563eb', fillOpacity: .08, weight: 2 }).addTo(map);
        stopLayers.set(stop.uuid, { marker, circle });
    });
    let personMarker = null;
    const personIcon = L.divIcon({ className: 'task-person-marker', html: '<span><i class="fa-solid fa-location-arrow"></i></span>', iconSize: [42, 42], iconAnchor: [21, 21] });
    const fit = () => { const points=data.stops.map(s=>[s.lat,s.lng]); if(data.latest_location)points.push([data.latest_location.lat,data.latest_location.lng]); map.fitBounds(points,{padding:[50,50],maxZoom:16}); };
    const distance = (a,b) => { const R=6371e3,p1=a[0]*Math.PI/180,p2=b[0]*Math.PI/180,dp=(b[0]-a[0])*Math.PI/180,dl=(b[1]-a[1])*Math.PI/180,x=Math.sin(dp/2)**2+Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;return R*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x)); };
    const updateDistances = (lat,lng) => document.querySelectorAll('[data-stop]').forEach((row) => { const stop=data.stops.find(s=>s.uuid===row.dataset.stop); const meters=distance([lat,lng],[stop.lat,stop.lng]); row.querySelector('[data-distance]').textContent=meters<1000?`${Math.round(meters)} m from current location`:`${(meters/1000).toFixed(1)} km from current location`; });
    const movePerson = (payload, immediate=false) => {
        const target=L.latLng(Number(payload.latitude ?? payload.lat),Number(payload.longitude ?? payload.lng));
        if(!personMarker){personMarker=L.marker(target,{icon:personIcon,zIndexOffset:1000}).addTo(map); updateDistances(target.lat,target.lng); return;}
        const from=personMarker.getLatLng(), start=performance.now(), duration=immediate?0:1100;
        const frame=(now)=>{const p=duration?Math.min(1,(now-start)/duration):1,e=1-Math.pow(1-p,3);personMarker.setLatLng([from.lat+(target.lat-from.lat)*e,from.lng+(target.lng-from.lng)*e]);if(p<1)requestAnimationFrame(frame);else updateDistances(target.lat,target.lng);};requestAnimationFrame(frame);
    };
    if(data.latest_location) movePerson(data.latest_location,true);
    fit();

    const routePoints = [data.latest_location ? [data.latest_location.lng,data.latest_location.lat] : null, ...data.stops.filter(s=>s.status!=='completed').map(s=>[s.lng,s.lat])].filter(Boolean);
    if(routePoints.length>1) fetch(`https://router.project-osrm.org/route/v1/driving/${routePoints.map(p=>p.join(',')).join(';')}?overview=full&geometries=geojson`).then(r=>r.json()).then(result=>{const coords=result.routes?.[0]?.geometry?.coordinates;if(coords)L.polyline(coords.map(p=>[p[1],p[0]]),{color:'#2563eb',weight:5,opacity:.76}).addTo(map);}).catch(()=>L.polyline(routePoints.map(p=>[p[1],p[0]]),{color:'#2563eb',dashArray:'8 8'}).addTo(map));
    document.querySelectorAll('[data-stop]').forEach(row=>row.addEventListener('click',()=>{const stop=data.stops.find(s=>s.uuid===row.dataset.stop);map.flyTo([stop.lat,stop.lng],17);stopLayers.get(stop.uuid).marker.openPopup();}));

    const socketLabel=document.getElementById('task-socket-status');
    if(window.Echo){
        socketLabel.textContent='Realtime connected';
        window.Echo.private(`App.Models.User.${data.assignee_id}`).listen('.gps.location.updated',payload=>movePerson(payload)).listen('.field-task.updated',payload=>applyTaskUpdate(payload));
        if(data.viewer_id!==data.assignee_id) window.Echo.private(`App.Models.User.${data.viewer_id}`).listen('.field-task.updated',payload=>applyTaskUpdate(payload));
    } else socketLabel.textContent='Realtime unavailable';
    function applyTaskUpdate(payload){
        if(payload.task?.uuid!==data.uuid)return;
        document.getElementById('task-status').textContent=payload.task.status.replaceAll('_',' ');
        document.getElementById('task-completed-count').textContent=payload.task.completed_stops;
        payload.task.stops.forEach(update=>{const stop=data.stops.find(s=>s.uuid===update.uuid);if(!stop)return;stop.status=update.status;const row=document.querySelector(`[data-stop="${update.uuid}"]`);row?.classList.toggle('is-complete',update.status==='completed');if(row&&update.status==='completed')row.querySelector('.task-stop-number').textContent='✓';const layers=stopLayers.get(update.uuid);if(layers){layers.circle.setStyle({color:update.status==='completed'?'#10b981':'#2563eb'});layers.marker.setIcon(stopIcon(stop));}});
        if(payload.change==='stop_completed') window.Swal?.fire({toast:true,position:'top-end',timer:3500,showConfirmButton:false,icon:'success',title:'Task stop completed'});
    }

    let report=null,replayMap=null,replayMarker=null,replayTimer=null;
    const modal=new Modal(document.getElementById('task-report-modal'));
    const loadReport=()=>report?Promise.resolve(report):fetch(data.report_url,{headers:{Accept:'application/json'}}).then(r=>r.json()).then(r=>report=r.data);
    document.getElementById('task-route-history').addEventListener('click',()=>loadReport().then(showReplay));
    document.getElementById('task-device-diagnostics').addEventListener('click',()=>loadReport().then(showDiagnostics));
    function showReplay(value){document.getElementById('task-report-title').textContent='Route playback';document.getElementById('task-diagnostics-panel').classList.add('d-none');document.getElementById('task-replay-map').classList.remove('d-none');modal.show();setTimeout(()=>{if(!replayMap){replayMap=L.map('task-replay-map');L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(replayMap);}replayMap.eachLayer(layer=>{if(layer instanceof L.Polyline||layer instanceof L.Marker)replayMap.removeLayer(layer);});const pts=value.route.map(p=>[p.lat,p.lng]);if(!pts.length)return;L.polyline(pts,{color:'#0ea5e9',weight:5}).addTo(replayMap);replayMarker=L.marker(pts[0],{icon:personIcon}).addTo(replayMap);replayMap.fitBounds(pts,{padding:[30,30]});const range=document.getElementById('task-play-range');range.max=pts.length-1;range.value=0;range.oninput=()=>replayMarker.setLatLng(pts[Number(range.value)]);document.getElementById('task-play').onclick=()=>{clearInterval(replayTimer);let i=Number(range.value),speed=Number(document.getElementById('task-play-speed').value);replayTimer=setInterval(()=>{if(++i>=pts.length){clearInterval(replayTimer);return;}range.value=i;replayMarker.setLatLng(pts[i]);},Math.max(40,600/speed));};},250);}
    function showDiagnostics(value){document.getElementById('task-report-title').textContent='Device diagnostics';document.getElementById('task-replay-map').classList.add('d-none');const panel=document.getElementById('task-diagnostics-panel');panel.classList.remove('d-none');panel.querySelector('tbody').innerHTML=value.diagnostics.map(d=>`<tr><td>${d.type.replaceAll('_',' ')}</td><td>${new Date(d.occurred_at).toLocaleString()}</td><td>${d.battery??'--'}%</td><td>${d.network??'--'}</td><td>${d.reason??'--'}</td></tr>`).join('')||'<tr><td colspan="5" class="text-muted">No device interruptions recorded during this task.</td></tr>';modal.show();}
}
