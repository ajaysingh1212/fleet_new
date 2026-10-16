import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapRoot = document.getElementById('task-builder-map');
if (mapRoot) {
    const map = L.map(mapRoot).setView([20.5937, 78.9629], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    const list = document.getElementById('task-stop-list');
    const data = JSON.parse(document.getElementById('task-stops-data').textContent || '[]');
    let stops = data.map((stop) => ({ ...stop, marker: null }));

    const icon = (number) => L.divIcon({ className: 'task-numbered-marker', html: `<span>${number}</span>`, iconSize: [34, 40], iconAnchor: [17, 40] });
    const rebuildMarkers = () => {
        stops.forEach((stop, index) => {
            if (stop.marker) map.removeLayer(stop.marker);
            stop.marker = L.marker([stop.latitude, stop.longitude], { icon: icon(index + 1), draggable: true }).addTo(map);
            stop.marker.on('dragend', (event) => {
                const point = event.target.getLatLng(); stop.latitude = point.lat; stop.longitude = point.lng; render();
            });
        });
        if (stops.length) map.fitBounds(stops.map((s) => [s.latitude, s.longitude]), { padding: [35, 35], maxZoom: 16 });
    };
    const esc = (value = '') => String(value ?? '').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    const render = () => {
        list.innerHTML = stops.map((stop, i) => `<div class="task-stop-editor" data-index="${i}"><div class="task-stop-editor-head"><span class="task-stop-number">${i + 1}</span><strong>Stop ${i + 1}</strong><span class="ms-auto d-flex gap-1"><button type="button" class="btn btn-sm tracker-outline-btn" data-move="up" title="Move up" ${i===0?'disabled':''}><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="btn btn-sm tracker-outline-btn" data-move="down" title="Move down" ${i===stops.length-1?'disabled':''}><i class="fa-solid fa-arrow-down"></i></button><button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Remove"><i class="fa-solid fa-trash"></i></button></span></div><div class="row g-2"><div class="col-7"><input class="form-control form-control-sm" data-key="title" value="${esc(stop.title || '')}" placeholder="Stop title" required></div><div class="col-5"><input type="datetime-local" class="form-control form-control-sm" data-key="expected_at" value="${esc(stop.expected_at || '')}"></div><div class="col-8"><input class="form-control form-control-sm" data-key="address" value="${esc(stop.address || '')}" placeholder="Address / landmark"></div><div class="col-4"><div class="input-group input-group-sm"><input type="number" min="25" max="1000" class="form-control" data-key="radius_meters" value="${stop.radius_meters || document.querySelector('[name=arrival_radius_meters]').value || 100}"><span class="input-group-text">m</span></div></div><div class="col-12"><textarea class="form-control form-control-sm" data-key="description" placeholder="Work details">${esc(stop.description || '')}</textarea></div></div><input type="hidden" name="stops[${i}][title]" value="${esc(stop.title || '')}"><input type="hidden" name="stops[${i}][description]" value="${esc(stop.description || '')}"><input type="hidden" name="stops[${i}][address]" value="${esc(stop.address || '')}"><input type="hidden" name="stops[${i}][latitude]" value="${stop.latitude}"><input type="hidden" name="stops[${i}][longitude]" value="${stop.longitude}"><input type="hidden" name="stops[${i}][expected_at]" value="${esc(stop.expected_at || '')}"><input type="hidden" name="stops[${i}][radius_meters]" value="${stop.radius_meters || 100}"></div>`).join('') || '<div class="task-stop-empty"><i class="fa-solid fa-location-dot"></i><span>Click anywhere on the map to add the first stop.</span></div>';
    };
    map.on('click', ({ latlng }) => { stops.push({ title: `Stop ${stops.length + 1}`, description: '', address: '', latitude: latlng.lat.toFixed(7), longitude: latlng.lng.toFixed(7), expected_at: '', radius_meters: document.querySelector('[name=arrival_radius_meters]').value || 100 }); rebuildMarkers(); render(); });
    list.addEventListener('input', (event) => { const row=event.target.closest('[data-index]'); if(!row||!event.target.dataset.key)return; const i=Number(row.dataset.index); stops[i][event.target.dataset.key]=event.target.value; row.querySelector(`[name="stops[${i}][${event.target.dataset.key}]"]`).value=event.target.value; });
    list.addEventListener('click', (event) => { const row=event.target.closest('[data-index]'); if(!row)return; const i=Number(row.dataset.index); if(event.target.closest('[data-remove]')) { if(stops[i].marker)map.removeLayer(stops[i].marker); stops.splice(i,1); rebuildMarkers(); render(); } const move=event.target.closest('[data-move]')?.dataset.move; if(move){const j=move==='up'?i-1:i+1; [stops[i],stops[j]]=[stops[j],stops[i]]; rebuildMarkers(); render();} });
    document.getElementById('task-use-location').addEventListener('click', () => navigator.geolocation?.getCurrentPosition(({coords}) => map.setView([coords.latitude, coords.longitude], 16)));
    document.getElementById('field-task-form').addEventListener('submit', (event) => { if(!stops.length){event.preventDefault(); window.Swal?.fire({icon:'warning',title:'Add at least one route stop'});} });
    rebuildMarkers(); render();
}
