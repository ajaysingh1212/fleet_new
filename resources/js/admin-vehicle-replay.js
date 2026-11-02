import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { RoutePlayback } from './route-playback';

const $ = (id) => document.getElementById(id);
const mapElement = $('vehicle-replay-map');
if (mapElement) {
    let map = null;
    let routeLayer = null;
    let marker = null;
    const timeFormat = new Intl.DateTimeFormat('en-IN', {
        timeZone: 'Asia/Kolkata', dateStyle: 'medium', timeStyle: 'short',
    });
    const playback = new RoutePlayback({
        onFrame: (point) => {
            if (!point) return;
            const latLng = [point.latitude, point.longitude];
            if (!marker) marker = L.marker(latLng, { zIndexOffset: 1000, icon: L.divIcon({
                className: '', html: '<span class="rr-marker"><i class="fa-solid fa-car-side" aria-hidden="true"></i></span>',
                iconSize: [38, 38], iconAnchor: [19, 19],
            }) }).addTo(routeLayer);
            else marker.setLatLng(latLng);
            $('replay-time').textContent = `${timeFormat.format(new Date(point.recorded_at))} IST · ${point.speed == null ? '—' : `${(Number(point.speed) * 3.6).toFixed(1)} km/h`}`;
            $('replay-seek').value = point.position;
            if (point.gap) map.panTo(latLng, { animate: false });
        },
        onState: (running) => {
            $('replay-play').innerHTML = `<i class="fa-solid fa-${running ? 'pause' : 'play'}"></i>`;
            $('replay-play').setAttribute('aria-label', running ? 'Pause route' : 'Play route');
        },
    });

    $('replay-play').addEventListener('click', () => playback.running ? playback.pause() : playback.play());
    $('replay-restart').addEventListener('click', () => playback.seek(0));
    $('replay-seek').addEventListener('input', (event) => playback.seek(Number(event.target.value)));
    $('replay-speed').addEventListener('change', (event) => {
        $('replay-speed-control').value = event.target.value;
        playback.setSpeed(Number(event.target.value));
    });
    $('replay-speed-control').addEventListener('change', (event) => {
        $('replay-speed').value = event.target.value;
        playback.setSpeed(Number(event.target.value));
    });

    function showMessage(message, icon = 'fa-map-location-dot') {
        $('replay-result').hidden = true;
        const panel = $('replay-message');
        panel.hidden = false;
        panel.innerHTML = `<i class="fa-solid ${icon}"></i>${message}`;
    }

    $('replay-load').addEventListener('click', async () => {
        const vehicleId = $('replay-vehicle').value;
        if (!vehicleId) return showMessage('Choose a vehicle to build its route replay.', 'fa-car');
        const button = $('replay-load');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading route…';
        try {
            const { data } = await window.axios.get('/api/v1/replay', {
                params: { vehicle_id: vehicleId, preset: $('replay-preset').value },
            });
            const replay = data.data;
            const points = replay.points ?? [];
            if (!points.length) return showMessage('No GPS route points were recorded for this vehicle in the selected period.', 'fa-location-crosshairs');
            $('replay-message').hidden = true;
            $('replay-result').hidden = false;
            await new Promise((resolve) => requestAnimationFrame(resolve));
            if (!map) {
                map = L.map(mapElement, { zoomSnap: 0.25, minZoom: 3, maxZoom: 19, attributionControl: true });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
                }).addTo(map);
                routeLayer = L.layerGroup().addTo(map);
            }
            map.invalidateSize();
            routeLayer.clearLayers(); marker = null; playback.pause();
            const segments = [];
            let segment = [];
            points.forEach((point, index) => {
                if (index && Date.parse(point.recorded_at) - Date.parse(points[index - 1].recorded_at) > 1800000) {
                    if (segment.length > 1) segments.push(segment);
                    segment = [];
                }
                segment.push([point.latitude, point.longitude]);
            });
            if (segment.length > 1) segments.push(segment);
            segments.forEach((coords) => L.polyline(coords, { color: '#128c98', weight: 5, opacity: 0.86 }).addTo(routeLayer));
            const first = points[0]; const last = points.at(-1);
            L.circleMarker([first.latitude, first.longitude], { radius: 8, color: '#fff', weight: 2, fillColor: '#10a874', fillOpacity: 1 }).addTo(routeLayer).bindTooltip('Start');
            L.circleMarker([last.latitude, last.longitude], { radius: 8, color: '#fff', weight: 2, fillColor: '#e45252', fillOpacity: 1 }).addTo(routeLayer).bindTooltip('End');
            playback.setPoints(points);
            playback.setSpeed(Number($('replay-speed').value));
            $('replay-seek').max = String(points.length - 1);
            const bounds = L.latLngBounds(points.map((point) => [point.latitude, point.longitude]));
            map.fitBounds(bounds.pad(0.14), { maxZoom: 17 });
            $('replay-heading').textContent = `${replay.vehicle.registration_number} · ${replay.vehicle.name}`;
            $('replay-window').textContent = `${timeFormat.format(new Date(first.recorded_at))} – ${timeFormat.format(new Date(last.recorded_at))} IST`;
            $('replay-point-count').textContent = `${points.length.toLocaleString()} GPS points`;
            const stats = replay.statistics;
            const cards = [
                ['Distance', `${stats.total_distance_km} km`, 'fa-route'],
                ['Moving time', `${(stats.moving_seconds / 3600).toFixed(1)} h`, 'fa-stopwatch'],
                ['Average speed', `${stats.average_speed_kmh ?? '—'} km/h`, 'fa-gauge-high'],
                ['Maximum speed', `${stats.maximum_speed_kmh ?? '—'} km/h`, 'fa-bolt'],
            ];
            $('replay-stats').replaceChildren(...cards.map(([label, value, icon]) => {
                const col = document.createElement('div'); col.className = 'col-6 col-xl-3';
                const card = document.createElement('div'); card.className = 'rr-stat';
                const caption = document.createElement('span'); caption.innerHTML = `<i class="fa-solid ${icon}"></i>${label}`;
                const amount = document.createElement('strong'); amount.textContent = value;
                card.append(caption, amount); col.append(card); return col;
            }));
            requestAnimationFrame(() => map.invalidateSize());
        } catch (error) {
            showMessage(error.response?.status === 403 ? 'You do not have access to this vehicle.' : 'Could not load the route. Please try again.', 'fa-triangle-exclamation');
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-route me-2"></i>Build Replay';
        }
    });
}
