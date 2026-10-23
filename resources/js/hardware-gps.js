import L from 'leaflet';
import axios from 'axios';
import 'leaflet/dist/leaflet.css';
import {fleetState, fleetPresentation} from './hardware-gps-motion.js';
import {HardwareMarkerMotion} from './hardware-marker-motion.js';

const colors = {moving: '#16803c', idle: '#c77c00', parked: '#2563eb', stopped: '#2563eb', offline: '#64748b', stale_gps: '#a16207', awaiting_gps: '#a16207', no_gps_fix: '#a16207'};
const label = value => String(value ?? 'Unknown').replaceAll('_', ' ');
const car = '<path d="M5 15l2-6h10l2 6M4 15h16v6H4zM7 21v3m10-3v3M8 12h8"/>';
const bike = '<circle cx="6" cy="20" r="4"/><circle cx="22" cy="20" r="4"/><path d="M6 20l6-10 5 10H6m11 0l3-13h-4m-8 3h6m6-3h3"/>';
const point = p => Number.isFinite(p.latitude) && Number.isFinite(p.longitude) && Math.abs(p.latitude) <= 90 && Math.abs(p.longitude) <= 180;
const key = p => p.device_id == null ? `vehicle:${p.vehicle_id}` : `device:${p.device_id}`;

export function initHardwareGps() {
    const container = document.getElementById('hardware-gps-map');
    if (!container) return;
    const map = L.map(container).setView([20.59, 78.96], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap contributors', maxZoom: 19}).addTo(map);
    const vehicles = new Map(), markers = new Map(), animations = new Map(), appearances = new Map();
    const roster = document.getElementById('hardware-fleet-list');
    const status = document.getElementById('hardware-live-status');
    let stopped = false, fitted = false, polling = false;
    const icon = p => L.divIcon({className: 'fleet-marker', iconSize: [38, 38], iconAnchor: [19, 19],
        html: `<div style="background:${colors[fleetState(p)] ?? '#64748b'};border:3px solid white;border-radius:50%;width:38px;height:38px;box-shadow:0 2px 7px #0006;display:grid;place-items:center"><svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${['bike', 'motorcycle', 'motorbike'].includes(p.vehicle_type) ? bike : car}</svg></div>`});
    const popup = p => {
        const node = document.createElement('div');
        const display = fleetPresentation(p);
        for (const text of [p.registration ?? p.name, `Driver: ${p.driver_name ?? 'Not assigned'}`,
            `Connection: ${display.connection}`, `Movement: ${display.movement}`, `GPS: ${display.gps}`,
            `Last GPS speed: ${p.speed ?? 'Unknown'} km/h`, `ACC: ${display.acc}`,
            ...(p.acc_reported_at ? [`ACC report time: ${new Date(p.acc_reported_at).toLocaleString()}`] : []),
            `GPS time: ${p.recorded_at ? new Date(p.recorded_at).toLocaleString() : 'Not received'}`, `Coordinates: ${p.latitude}, ${p.longitude}`]) {
            const line = document.createElement('div'); line.textContent = text; node.append(line);
        }
        return node;
    };
    const renderRoster = () => {
        const devices = [...vehicles.values()].filter(p => p.device_id != null);
        const counts = {registered: devices.length, online: 0, offline: 0, inactive: 0, moving: 0, stopped: 0};
        for (const p of devices) {
            const state = fleetState(p);
            if (p.authorization !== 'active') counts.inactive++;
            else if (state === 'offline') counts.offline++;
            else counts.online++;
            if (state === 'moving') counts.moving++;
            if (['idle', 'parked', 'stopped'].includes(state)) counts.stopped++;
        }
        document.querySelectorAll('[data-fleet-stat]').forEach(node => {
            if (counts[node.dataset.fleetStat] !== undefined) node.textContent = counts[node.dataset.fleetStat];
        });
        roster.replaceChildren();
        for (const [id, p] of vehicles) {
            const display = fleetPresentation(p);
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'list-group-item list-group-item-action';
            button.textContent = `${p.registration ?? p.name} · ${display.connection} · ${display.movement} · GPS: ${display.gps} · ${p.driver_name ?? 'No driver'}`;
            button.style.borderLeft = `5px solid ${colors[fleetState(p)] ?? '#64748b'}`;
            button.addEventListener('click', () => { const marker = markers.get(id); if (marker) { map.setView(marker.getLatLng(), 16); marker.openPopup(); } });
            roster.append(button);
        }
    };
    const fit = () => {
        const points = [...vehicles.values()].filter(point).map(p => [p.latitude, p.longitude]);
        if (points.length) { map.fitBounds(points, {maxZoom: 16, padding: [35, 35]}); fitted = true; }
    };
    const update = incoming => {
        const id = key(incoming), old = vehicles.get(id);
        const p = {...old, ...incoming};
        if (old?.recorded_at && incoming.recorded_at && old.vehicle_id === incoming.vehicle_id && Date.parse(incoming.recorded_at) < Date.parse(old.recorded_at)) {
            for (const field of ['latitude', 'longitude', 'recorded_at', 'speed', 'heading', 'acc']) p[field] = old[field];
        }
        if (old?.vehicle_id === incoming.vehicle_id && Date.parse(old?.acc_reported_at) > Date.parse(incoming.acc_reported_at)) {
            for (const field of ['acc', 'acc_reported', 'acc_reported_at', 'acc_source']) p[field] = old[field];
        }
        vehicles.set(id, p);
        if (old && old.vehicle_id !== p.vehicle_id) {
            animations.get(id)?.stop(); animations.delete(id); appearances.delete(id);
            markers.get(id)?.remove(); markers.delete(id);
        }
        if (point(p)) {
            let marker = markers.get(id);
            const newer = !old || Date.parse(p.recorded_at) > Date.parse(old.recorded_at);
            const appearance = `${fleetState(p)}:${p.vehicle_type}`;
            if (!marker) {
                marker = L.marker([p.latitude, p.longitude], {icon: icon(p)}).addTo(map);
                // Updating an open popup must not pan the map on every GPS packet.
                marker.bindPopup(popup(p), {autoPan: false});
                const title = document.createElement('span'); title.textContent = p.registration ?? p.name;
                marker.bindTooltip(title, {direction: 'top'}); markers.set(id, marker);
                appearances.set(id, appearance);
                const motion = new HardwareMarkerMotion(marker);
                motion.timestamp = Date.parse(p.recorded_at);
                animations.set(id, motion);
            } else {
                if (appearances.get(id) !== appearance) {
                    marker.setIcon(icon(p)); appearances.set(id, appearance);
                }
                if (marker.isPopupOpen() || newer || !old || ['acc', 'state', 'last_packet_at', 'speed', 'acc_reported_at'].some(field => old[field] !== p[field])) {
                    marker.setPopupContent(popup(p));
                }
                if (newer) {
                    animations.get(id).push([p.latitude, p.longitude], Date.parse(p.recorded_at));
                }
            }
        } else {
            animations.get(id)?.stop(); animations.delete(id); appearances.delete(id);
            markers.get(id)?.remove(); markers.delete(id);
        }
        const row = document.querySelector(`[data-hardware-device="${Number(p.device_id)}"]`);
        if (row) {
            row.querySelector('[data-speed]').textContent = p.speed ?? 'Unknown';
            row.querySelector('[data-fix]').textContent = p.recorded_at ? new Date(p.recorded_at).toLocaleString() : 'No valid fix';
            row.querySelector('[data-communication]').textContent = p.last_packet_at ? new Date(p.last_packet_at).toLocaleString() : 'Never';
            const display = fleetPresentation(p);
            row.querySelector('[data-connection]').textContent = `${display.connection} · ${display.movement}`;
        }
    };
    JSON.parse(container.dataset.positions).forEach(update); renderRoster(); fit();
    document.getElementById('hardware-fit-fleet').addEventListener('click', fit);
    const poll = async () => {
        if (stopped || polling) return;
        polling = true;
        try {
            const {data} = await axios.get(container.dataset.fleetUrl, {timeout: 5000});
            if (stopped) return;
            const current = new Set(data.devices.map(key));
            for (const id of vehicles.keys()) if (!current.has(id)) {
                animations.get(id)?.stop(); animations.delete(id); appearances.delete(id);
                markers.get(id)?.remove(); markers.delete(id); vehicles.delete(id);
            }
            data.devices.forEach(update); renderRoster(); if (!fitted) fit();
            status.textContent = `Fleet updated ${new Date().toLocaleTimeString()} · ${vehicles.size} registered trackers / vehicles · live updates every second`;
        } catch { status.textContent = 'Live refresh unavailable; showing last received positions. Retrying…'; }
        finally { polling = false; }
    };
    if (window.Echo) window.Echo.private('admin.gps.hardware').listen('.hardware.location.updated', p => {
        if (!stopped) { update(p); renderRoster(); if (!fitted) fit(); }
    });
    const timer = window.setInterval(poll, 1000); poll();
    window.addEventListener('pagehide', () => {
        stopped = true; clearInterval(timer); animations.forEach(motion => motion.stop());
        window.Echo?.leave('admin.gps.hardware'); map.remove();
    }, {once: true});
}
