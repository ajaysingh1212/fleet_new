import L from 'leaflet';
import axios from 'axios';
import 'leaflet/dist/leaflet.css';
import {fleetState, fleetPresentation, mergeFleetUpdate} from './hardware-gps-motion.js';
import {HardwareMarkerMotion} from './hardware-marker-motion.js';
import {HardwareFleetStream} from './hardware-fleet-stream.js';
import {initFleetConsole} from './fleet-console.js';
import {warningLabel} from './fleet-geofence-alerts.js';
import {browserReceived, markerDrawn} from './gps-pipeline-metrics.js';
import {vehicleIcon} from './vehicle-icons.js';
import {createDeviceCommandDialog} from './gps-device-commands.js';

const colors = {moving: '#16803c', idle: '#c77c00', parked: '#2563eb', stopped: '#2563eb', offline: '#64748b', stale_gps: '#a16207', awaiting_gps: '#a16207', no_gps_fix: '#a16207'};
const car = '<path d="M5 15l2-6h10l2 6M4 15h16v6H4zM7 21v3m10-3v3M8 12h8"/>';
const bike = '<circle cx="6" cy="20" r="4"/><circle cx="22" cy="20" r="4"/><path d="M6 20l6-10 5 10H6m11 0l3-13h-4m-8 3h6m6-3h3"/>';
const point = p => Number.isFinite(p.latitude) && Number.isFinite(p.longitude) && Math.abs(p.latitude) <= 90 && Math.abs(p.longitude) <= 180;
const key = p => p.device_id == null ? `vehicle:${p.vehicle_id}` : `device:${p.device_id}`;

export function initHardwareGps() {
    const container = document.getElementById('hardware-gps-map');
    if (!container) return;
    const map = L.map(container).setView([20.59, 78.96], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap contributors', maxZoom: 19}).addTo(map);
    const vehicles = new Map(), markers = new Map(), animations = new Map(), appearances = new Map(), rosterRows = new Map();
    const roster = document.getElementById('hardware-fleet-list');
    const status = document.getElementById('hardware-live-status');
    const commandDialog = createDeviceCommandDialog();
    let stopped = false, fitted = false, renderFrame = null, consoleUi = null, selectedVehicle = null;
    let meta = JSON.parse(container.dataset.meta);
    const cursors = [0];
    let liveState = 'connecting', navigation = null;
    const pending = new Map();
    const describeStream = () => {
        const messages = {connecting: 'Connecting to live updates', live: 'WebSocket connected', disconnected: 'WebSocket disconnected - reconnecting; last received data shown', unauthorized: 'Live subscription denied - sign in again', unavailable: 'WebSocket unavailable', syncing: 'Synchronizing fleet', error: 'Synchronization failed - use Sync now', empty: 'No matching trackers'};
        status.textContent = `${messages[liveState] ?? liveState} | ${vehicles.size} loaded (maximum ${meta.limit}) | Updates arrive when devices report`;
        document.getElementById('hardware-page-next').disabled = !meta.has_more || liveState === 'syncing';
        document.getElementById('hardware-page-prev').disabled = cursors.length < 2 || liveState === 'syncing';
    };
    const icon = p => L.divIcon({className: `fleet-marker${consoleUi?.hasAlerts(p.device_id) ? (consoleUi.getAlerts(p.device_id).every(a=>a.historical) ? ' has-violation violation-historical' : ' has-violation') : ''}`, iconSize: [38, 38], iconAnchor: [19, 19],
        html: `<div class="fleet-marker-body" style="background:${colors[fleetState(p)] ?? '#64748b'};border:3px solid white;border-radius:50%;width:38px;height:38px;box-shadow:0 2px 7px #0006;display:grid;place-items:center;color:white;font-size:19px"><i class="fa-solid ${vehicleIcon(p)}" aria-hidden="true"></i></div>`});
    const popup = p => {
        const node = document.createElement('div');
        const display = fleetPresentation(p);
        for (const text of [p.registration ?? p.name,
            ...(consoleUi?.getAlerts(p.device_id) ?? []).map(a => `${a.historical ? 'GPS WARNING' : 'ALERT'}: ${a.label ?? warningLabel(a.type)} - ${a.geofence_name} - ${a.detail ?? 'Violation recorded'}`), `Driver: ${p.driver_name ?? 'Not assigned'}`,
            `Connection: ${display.connection}`, `Movement: ${display.movement}`, `GPS: ${display.gps}`,
            `Last GPS speed: ${p.speed ?? 'Unknown'} km/h`, `ACC: ${display.acc}`,
            `Power: ${display.power}`,
            `TCP link: ${display.tcp}`,
            ...(p.queue_delay_ms != null ? [`Server queue delay: ${p.queue_delay_ms} ms`] : []),
            ...(p.sensors?.gps_retransmission === true ? ['Device is uploading a stored GPS fix'] : []),
            ...(p.acc_reported_at ? [`ACC report time: ${new Date(p.acc_reported_at).toLocaleString()}`] : []),
            `GPS time: ${p.recorded_at ? new Date(p.recorded_at).toLocaleString() : 'Not received'}`,
            ...(p.received_at ? [`Location received by server: ${new Date(p.received_at).toLocaleString()}`,
                `GPS age on arrival: ${Math.max(0, Math.round((Date.parse(p.received_at) - Date.parse(p.recorded_at))/1000))}s`] : []),
            `Coordinates: ${p.latitude}, ${p.longitude}`]) {
            const line = document.createElement('div'); line.textContent = text; node.append(line);
        }
        if (p.vehicle_id) { const profile=document.createElement('a'); profile.href=`/admin/vehicles/${Number(p.vehicle_id)}`; profile.textContent='Vehicle details'; node.append(profile, document.createElement('br')); }
        if (p.device_id && p.protocol === 'pt06') {
            const commands = document.createElement('button'); commands.type = 'button'; commands.className = 'fleet-command-button';
            commands.textContent = 'Device commands'; commands.onclick = () => commandDialog.open(p); node.append(commands, document.createElement('br'));
        }
        if(p.device_id){const manage=document.createElement('a');manage.href=`/admin/gps-devices/${Number(p.device_id)}/edit`;manage.textContent='Manage tracker / assign vehicle';node.append(manage);}
        return node;
    };
    const renderRoster = () => {
        const devices = [...vehicles.values()].filter(p => p.device_id != null);
        const counts = {online: 0, offline: 0, inactive: 0, moving: 0, stopped: 0, parked: 0};
        for (const p of devices) {
            const state = fleetState(p);
            if (p.authorization !== 'active') counts.inactive++;
            else if (state === 'offline') counts.offline++;
            else counts.online++;
            if (state === 'moving') counts.moving++;
            if (['idle', 'parked', 'stopped'].includes(state)) counts.stopped++;
            if (state === 'parked') counts.parked++;
        }
        consoleUi?.refresh();
        document.querySelectorAll('[data-fleet-stat]').forEach(node => {
            if (counts[node.dataset.fleetStat] !== undefined) node.textContent = counts[node.dataset.fleetStat];
        });
    };
    const renderRow = (id, p) => {
        const display = fleetPresentation(p);
        let button = rosterRows.get(id);
        if (!button) {
            button = document.createElement('article');
            button.className = 'list-group-item';
            button.innerHTML = '<button type="button" class="fleet-device-select" aria-expanded="false"><span class="fleet-device-title"></span><span class="fleet-device-meta"></span><span class="fleet-device-state"></span><span class="fleet-device-alert"></span><span class="fleet-device-action">View vehicle details</span></button><div class="fleet-device-details" hidden></div>';
            const detailsId = `fleet-details-${id.replace(':', '-')}`;
            button.querySelector('.fleet-device-details').id = detailsId;
            button.querySelector('.fleet-device-select').setAttribute('aria-controls', detailsId);
            const commandButton = document.createElement('button'); commandButton.type = 'button'; commandButton.className = 'fleet-command-button';
            commandButton.innerHTML = '<i class="fa-solid fa-terminal" aria-hidden="true"></i> Commands';
            commandButton.setAttribute('aria-label', `Device commands for ${p.registration ?? p.name}`);
            commandButton.onclick = () => commandDialog.open(vehicles.get(id));
            button.insertBefore(commandButton, button.querySelector('.fleet-device-details'));
            button.querySelector('.fleet-device-select').addEventListener('click', () => {
                selectedVehicle = selectedVehicle === id ? null : id;
                for (const [rowId, vehicle] of vehicles) renderRow(rowId, vehicle);
                const marker = markers.get(id);
                if (selectedVehicle === id && marker) { map.setView(marker.getLatLng(), 16); marker.openPopup(); }
            });
            rosterRows.set(id, button); roster.append(button);
        }
        const fields = {'.fleet-device-title':p.registration ?? p.name, '.fleet-device-meta':`${p.imei ?? 'Unassigned tracker'} · ${p.driver_name ?? 'No driver'}`,
            '.fleet-device-state':`${display.connection} · ${display.movement} · ${display.power} · ${p.speed ?? 'Unknown'} km/h`,
            '.fleet-device-alert':(consoleUi?.getAlerts(p.device_id) ?? []).map(a=>`${a.label ?? warningLabel(a.type)}: ${a.geofence_name}${a.historical ? ' - Last known location, GPS pending' : ''}`).join(' | ')};
        for(const [selector,value] of Object.entries(fields)){const node=button.querySelector(selector);if(node.textContent!==value)node.textContent=value;}
        button.title=`GPS: ${display.gps}`;
        button.querySelector('.fleet-command-button').hidden = !p.device_id || p.protocol !== 'pt06';
        button.style.borderLeft = `4px solid ${consoleUi?.hasAlerts(p.device_id) ? '#ef4444' : colors[fleetState(p)] ?? '#64748b'}`;
        const expanded = selectedVehicle === id;
        button.querySelector('.fleet-device-select').setAttribute('aria-expanded', String(expanded));
        button.querySelector('.fleet-device-action').textContent = expanded ? 'Hide vehicle details' : 'View vehicle details';
        const details = button.querySelector('.fleet-device-details');
        details.hidden = !expanded;
        if (expanded) {
            const date = value => value ? new Date(value).toLocaleString() : 'Not received';
            const rows = [['Vehicle', p.registration ?? 'Not assigned'], ['Type', p.vehicle_type ?? 'Unknown'],
                ['Driver', p.driver_name ?? 'Not assigned'], ['IMEI', p.imei ?? 'No tracker assigned'],
                ['Model', p.model ?? 'Unknown'], ['Protocol', p.protocol ?? 'Unknown'],
                ['Connection', display.connection], ['GPS', display.gps], ['ACC', display.acc],
                ['Power', display.power],
                ['Last communication', date(p.last_packet_at)], ['Last GPS fix', date(p.recorded_at)],
                ['Location', point(p) ? `${p.latitude}, ${p.longitude}` : p.gps_positioned_reported === false
                    ? 'Device is online but reports no GPS fix. Place it under open sky; use STATUS or prepare LJDW (wake GPS) in Commands.'
                    : 'No valid GPS fix received yet. Vehicle details remain available.']];
            const signature = JSON.stringify([rows, p.vehicle_id, p.device_id]);
            if (details.dataset.signature !== signature) {
                details.dataset.signature = signature;
                const list = document.createElement('dl');
                for (const [label, value] of rows) {
                    const term = document.createElement('dt'), description = document.createElement('dd');
                    term.textContent = label; description.textContent = value; list.append(term, description);
                }
                details.replaceChildren(list);
                for (const [target, label] of [[p.vehicle_id ? `/admin/vehicles/${Number(p.vehicle_id)}` : null, 'Full vehicle profile'],
                    [p.device_id ? `/admin/gps-devices/${Number(p.device_id)}` : null, 'Tracker details & history']]) {
                    if (!target) continue;
                    const link = document.createElement('a'); link.href = target; link.textContent = label; details.append(link);
                }
            }
        }
    };
    const fit = () => {
        const points = [...vehicles.values()].filter(point).map(p => [p.latitude, p.longitude]);
        if (points.length) { map.fitBounds(points, {maxZoom: 16, padding: [35, 35]}); fitted = true; }
    };
    const update = incoming => {
        const id = key(incoming), old = vehicles.get(id);
        const p = mergeFleetUpdate(old, incoming);
        vehicles.set(id, p);
        consoleUi?.observe(p);
        if (old && old.vehicle_id !== p.vehicle_id) {
            animations.get(id)?.stop(); animations.delete(id); appearances.delete(id);
            markers.get(id)?.remove(); markers.delete(id);
        }
        if (point(p)) {
            let marker = markers.get(id);
            const newer = !old || Date.parse(p.recorded_at) > Date.parse(old.recorded_at);
            const appearance = `${fleetState(p)}:${vehicleIcon(p)}:${(consoleUi?.getAlerts(p.device_id)??[]).map(a=>`${a.rule_id}:${a.type}:${a.historical}`).join(',')}`;
            if (!marker) {
                marker = L.marker([p.latitude, p.longitude], {icon: icon(p)}).addTo(map);
                // Updating an open popup must not pan the map on every GPS packet.
                marker.bindPopup(popup(p), {autoPan: false});
                marker.on('popupopen',()=>marker.setPopupContent(popup(vehicles.get(id))));
                const title = document.createElement('span'); title.textContent = p.registration ?? p.name;
                marker.bindTooltip(title, {direction: 'top'}); markers.set(id, marker);
                appearances.set(id, appearance);
                const motion = new HardwareMarkerMotion(marker);
                motion.timestamp = Date.parse(p.recorded_at);
                animations.set(id, motion);
                requestAnimationFrame(() => markerDrawn(p));
            } else {
                if (appearances.get(id) !== appearance) {
                    marker.setIcon(icon(p)); appearances.set(id, appearance);
                }
                if (marker.isPopupOpen() || newer || !old || ['acc', 'state', 'last_packet_at', 'speed', 'acc_reported_at'].some(field => old[field] !== p[field])) {
                    marker.setPopupContent(popup(p));
                }
                if (newer) {
                    animations.get(id).push([p.latitude, p.longitude], Date.parse(p.recorded_at), {onFrame: () => markerDrawn(p)});
                }
            }
        } else {
            animations.get(id)?.stop(); animations.delete(id); appearances.delete(id);
            markers.get(id)?.remove(); markers.delete(id);
        }
        renderRow(id, p);
        const row = document.querySelector(`[data-hardware-device="${Number(p.device_id)}"]`);
        if (row) {
            row.querySelector('[data-speed]').textContent = p.speed ?? 'Unknown';
            row.querySelector('[data-fix]').textContent = p.recorded_at ? new Date(p.recorded_at).toLocaleString() : 'No valid fix';
            row.querySelector('[data-communication]').textContent = p.last_packet_at ? new Date(p.last_packet_at).toLocaleString() : 'Never';
            const display = fleetPresentation(p);
            row.querySelector('[data-connection]').textContent = `${display.connection} · ${display.movement}`;
        }
    };
    const remove = id => {
        animations.get(id)?.stop(); animations.delete(id); appearances.delete(id);
        markers.get(id)?.remove(); markers.delete(id); vehicles.delete(id);
        rosterRows.get(id)?.remove(); rosterRows.delete(id); pending.delete(id);
    };
    const flush = () => {
        renderFrame = null;
        if (stopped) return;
        for (const p of pending.values()) update(p);
        pending.clear(); renderRoster(); if (!fitted) fit(); describeStream();
    };
    const queueUpdate = p => {
        const id = key(p), previous = pending.get(id);
        if (!previous?.observed_at || !p.observed_at || p.observed_at >= previous.observed_at) pending.set(id, p);
        if (renderFrame === null) renderFrame = requestAnimationFrame(flush);
    };
    const initial = JSON.parse(container.dataset.positions);
    initial.forEach(update); renderRoster(); fit();
    const filters = JSON.parse(container.dataset.filters);
    const stream = new HardwareFleetStream({
        echo: window.Echo,
        fetchSnapshot: async (after, signal) => (await axios.get(container.dataset.fleetUrl, {params: {...filters, after}, signal, timeout: 10000})).data,
        onSnapshot: data => {
            const current = new Set(data.devices.map(key));
            for (const id of vehicles.keys()) if (!current.has(id)) remove(id);
            if (navigation === 'next') cursors.push(data.meta.after);
            if (navigation === 'previous') cursors.pop();
            navigation = null;
            data.devices.forEach(update); meta = data.meta; renderRoster(); if (!fitted) fit(); consoleUi?.loadActive(); consoleUi?.loadZones();
        },
        onUpdate: p => queueUpdate(browserReceived(p)),
        onStatus: state => { if (state === 'error') navigation = null; liveState = state; describeStream(); },
    });
    consoleUi = initFleetConsole({map, vehicles,
        redraw: deviceId => {const p=vehicles.get(`device:${deviceId}`);if(p)queueUpdate(p);},
        onSearch: values => {Object.assign(filters,values);navigation=null;cursors.splice(0,cursors.length,0);fitted=false;stream.load(0);}});
    stream.watch(initial); describeStream();
    document.getElementById('hardware-fit-fleet').addEventListener('click', fit);
    document.getElementById('hardware-sync').addEventListener('click', () => stream.load());
    document.getElementById('hardware-page-next').addEventListener('click', () => {
        if (meta.next_after != null) { navigation = 'next'; stream.load(meta.next_after); }
    });
    document.getElementById('hardware-page-prev').addEventListener('click', () => {
        if (cursors.length > 1) { navigation = 'previous'; stream.load(cursors.at(-2)); }
    });
    // Local clock only: expire offline/ignition labels even when no packet is
    // arriving. This does not make HTTP requests or rebuild the map/list.
    const timer = window.setInterval(() => {
        for (const p of vehicles.values()) queueUpdate(p);
    }, 1000);
    window.addEventListener('pagehide', () => {
        stopped = true; clearInterval(timer); stream.dispose(); consoleUi?.dispose(); commandDialog.dispose();
        if (renderFrame !== null) cancelAnimationFrame(renderFrame);
        animations.forEach(motion => motion.stop()); map.remove();
    }, {once: true});
}
