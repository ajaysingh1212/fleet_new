import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export function initHardwareGps() {
    const container = document.getElementById('hardware-gps-map');
    if (!container) return;
    const map = L.map(container).setView([20.59, 78.96], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);
    const markers = new Map();
    const times = new Map();
    const ids = new Set([...document.querySelectorAll('[data-hardware-device]')].map(row => Number(row.dataset.hardwareDevice)));
    const update = (p) => {
        if (!ids.has(p.device_id) || !Number.isFinite(p.latitude) || !Number.isFinite(p.longitude)) return;
        const time = Date.parse(p.recorded_at);
        if (times.has(p.device_id) && time <= times.get(p.device_id)) return;
        times.set(p.device_id, time);
        let marker = markers.get(p.device_id);
        if (!marker) {
            marker = L.circleMarker([p.latitude, p.longitude], {radius: 8}).addTo(map);
            marker.bindTooltip(`Device ${p.device_id}`);
            markers.set(p.device_id, marker);
        } else marker.setLatLng([p.latitude, p.longitude]);
        const row = document.querySelector(`[data-hardware-device="${p.device_id}"]`);
        row.querySelector('[data-speed]').textContent = p.speed ?? 'Unknown';
        row.querySelector('[data-fix]').textContent = new Date(time).toLocaleString();
        row.querySelector('[data-communication]').textContent = new Date(p.received_at).toLocaleString();
        row.querySelector('[data-connection]').textContent = Date.now() - Date.parse(p.received_at) <= 180000 ? 'online' : 'offline';
    };
    const positions = JSON.parse(container.dataset.positions);
    positions.forEach(update);
    if (positions.length) map.fitBounds(positions.map(p => [p.latitude, p.longitude]), {maxZoom: 15, padding: [25, 25]});
    const status = document.getElementById('hardware-live-status');
    if (window.Echo) {
        window.Echo.private('admin.gps.hardware')
            .subscribed(() => { status.textContent = 'Live updates connected. Map shows this page; summary refreshes every 30 seconds.'; })
            .error(() => { status.textContent = 'Live updates unavailable; retrying. Dashboard refreshes every 30 seconds.'; })
            .listen('.hardware.location.updated', update);
    }
    // Refresh status and counts even when devices go silent (no location event).
    window.setTimeout(() => window.location.reload(), 30000);
    window.addEventListener('pagehide', () => { window.Echo?.leave('admin.gps.hardware'); map.remove(); }, {once: true});
}
