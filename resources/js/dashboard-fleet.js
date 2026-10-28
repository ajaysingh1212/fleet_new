import L from 'leaflet';
import axios from 'axios';
import {Offcanvas} from 'bootstrap';
import 'leaflet/dist/leaflet.css';
import '../css/dashboard-fleet.css';
import {HardwareMarkerMotion} from './hardware-marker-motion.js';
import {fleetState, fleetPresentation, mergeFleetUpdate} from './hardware-gps-motion.js';
import {liveGeofenceWarnings, warningLabel} from './fleet-geofence-alerts.js';
import {fleetKey, fleetLabel, streetDetailZoom} from './dashboard-fleet-model.js';
import {browserReceived, markerDrawn} from './gps-pipeline-metrics.js';
import {vehicleIcon} from './vehicle-icons.js';
import {stateName, insideFeature, hasPosition, labelPosition} from './dashboard-map-geometry.js';

const make = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text != null) node.textContent = text;
    if (className) node.className = className;
    return node;
};
const date = value => value ? new Date(value).toLocaleString() : 'Not reported';

export function initDashboardFleet() {
    const root = document.getElementById('fleet-dashboard');
    if (!root) return;
    const byId = id => document.getElementById(id);
    // Keep every overlay inside the stage so panels also work in native
    // fullscreen. Existing account information remains in its original view.
    root.append(byId('dashboard-information-tab'));
    const supportModal = byId('supportModal');
    if (supportModal) root.append(supportModal);
    const panels = [...root.querySelectorAll('.offcanvas')];
    const dockButtons = [...root.querySelectorAll('[data-bs-toggle="offcanvas"]')];
    for (const panel of panels) {
        panel.addEventListener('show.bs.offcanvas', () => {
            panels.filter(other => other !== panel && (other.classList.contains('show') || other.classList.contains('showing')))
                .forEach(other => Offcanvas.getOrCreateInstance(other).hide());
            dockButtons.forEach(button => button.setAttribute('aria-expanded', String(button.getAttribute('aria-controls') === panel.id)));
        });
        panel.addEventListener('hidden.bs.offcanvas', () => {
            dockButtons.filter(button => button.getAttribute('aria-controls') === panel.id)
                .forEach(button => button.setAttribute('aria-expanded', 'false'));
        });
    }
    const sizeStage = () => {
        const height = Math.max(240, window.innerHeight - root.getBoundingClientRect().top);
        root.style.setProperty('--fleet-stage-height', `${height}px`);
    };
    sizeStage();
    const map = L.map('dashboard-india-map', {zoomSnap: 0.25, minZoom: 3, maxZoom: 19, attributionControl: true});
    map.createPane('state-relief'); map.getPane('state-relief').style.zIndex = '210';
    map.createPane('state-labels'); map.getPane('state-labels').style.zIndex = '350';
    map.getPane('state-labels').style.pointerEvents = 'none';
    const indiaBounds = L.latLngBounds([[6.4, 67.5], [37.4, 98.4]]);
    const indiaView = () => map.fitBounds(indiaBounds, {padding: [18, 18]});
    indiaView();
    map.attributionControl.addAttribution('States: <a href="https://geodataviewer.com/datasets/boundaries/india-states-boundaries/">DataMeet / geoBoundaries</a> · <a href="https://creativecommons.org/licenses/by/2.5/in/">CC BY 2.5 IN</a>');
    const roads = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'});
    const zonesLayer = L.layerGroup().addTo(map);
    const stateLabels = L.layerGroup().addTo(map);
    let vehicles = new Map(), zones = [], confirmed = [], features = [], stateLayers = [], selected = null;
    let stopped = false, loading = false, controller = null, socketLive = false, lastSync = null;
    let statusFilter = 'all', stateFilter = '', search = '', roadsVisible = false, roadMode = 'auto';
    let boundaries = null;
    const markers = new Map(), motions = new Map(), rows = new Map(), channels = new Map();
    const stateByVehicle = new Map(), warnings = new Map();
    const echo = window.Echo;
    const admin = root.dataset.admin === '1';
    const message = text => {byId('fleet-map-message').textContent = text; byId('fleet-map-message').hidden = !text;};
    const color = p => warnings.get(fleetKey(p))?.length ? '#ef5454' : fleetState(p) === 'moving' ? '#21a778'
        : ['Online', 'Reconnecting'].includes(fleetPresentation(p).connection) ? '#e5a43a' : '#8293a5';
    const locate = p => {
        if (!hasPosition(p)) return '';
        return features.find(f => insideFeature(f, p.latitude, p.longitude))?.properties.shapeName ?? '';
    };
    const visible = p => (!stateFilter || (stateFilter === '__unlocated' ? !hasPosition(p) : stateByVehicle.get(fleetKey(p)) === stateFilter))
        && (!search || [p.registration, p.name, p.imei, p.driver_name].some(v => String(v ?? '').toLowerCase().includes(search)))
        && (statusFilter === 'all' || (statusFilter === 'moving' && fleetState(p) === 'moving')
            || (statusFilter === 'offline' && fleetPresentation(p).connection !== 'Online')
            || (statusFilter === 'alerts' && warnings.get(fleetKey(p))?.length));

    function detail(p) {
        const node = make('div');
        node.append(make('span', 'VEHICLE TELEMETRY', 'fleet-eyebrow'), make('h3', fleetLabel(p)));
        const presentation = fleetPresentation(p);
        const grid = make('dl', null, 'fleet-telemetry-grid');
        const fields = {'Vehicle': p.name, 'IMEI': p.imei, 'Driver': p.driver_name,
            'Connection': presentation.connection, 'Movement': presentation.movement,
            'Last GPS speed': p.speed == null ? 'Not reported' : `${p.speed} km/h`,
            'Ignition / ACC': presentation.acc, 'GPS quality': presentation.gps,
            'Satellites': p.satellites, 'Altitude': p.altitude == null ? 'Not reported' : `${p.altitude} m`,
            'State / territory': features.find(f => f.properties.shapeName === stateByVehicle.get(fleetKey(p))) ? stateName(features.find(f => f.properties.shapeName === stateByVehicle.get(fleetKey(p)))) : 'Location not available in state layer',
            'Coordinates': hasPosition(p) ? `${p.latitude.toFixed(6)}, ${p.longitude.toFixed(6)}` : 'Awaiting first GPS fix',
            'GPS timestamp': date(p.recorded_at), 'Server received': date(p.received_at),
            'Last communication': date(p.last_packet_at), 'Ignition report': date(p.acc_reported_at),
            'TCP link': presentation.tcp,
            'Device delivery delay': p.received_at && p.recorded_at ? `${Math.max(0, Math.round((Date.parse(p.received_at) - Date.parse(p.recorded_at)) / 1000))} s` : 'Not reported',
            'Server queue delay': p.queue_delay_ms == null ? 'Not reported' : `${p.queue_delay_ms} ms`,
            'Protocol / model': [p.protocol, p.model].filter(Boolean).join(' / ')};
        for (const [label, value] of Object.entries(fields)) {
            const item = make('div'); item.append(make('dt', label), make('dd', value ?? 'Not reported')); grid.append(item);
        }
        node.append(grid);
        for (const [title, values, time] of [['GPS sensor data', p.sensors, p.recorded_at], ['Heartbeat sensor data', p.heartbeat_sensors, p.heartbeat_reported_at]]) {
            if (!values || !Object.keys(values).length) continue;
            const sensors = make('details'); sensors.append(make('summary', `${title} · ${date(time)}`));
            const sensorGrid = make('dl', null, 'fleet-telemetry-grid');
            for (const [key, value] of Object.entries(values)) {
                const item = make('div'); item.append(make('dt', key.replaceAll('_', ' ')),
                    make('dd', value === null ? 'Not reported' : typeof value === 'object' ? JSON.stringify(value) : String(value)));
                sensorGrid.append(item);
            }
            sensors.append(sensorGrid); node.append(sensors);
        }
        for (const alert of warnings.get(fleetKey(p)) ?? []) node.append(make('div',
            `${alert.historical ? 'Last reported warning' : 'Geofence alert'}: ${alert.label ?? warningLabel(alert.type)} · ${alert.geofence_name} · ${alert.historical ? 'Awaiting fresh GPS data' : alert.pending ? 'Live GPS warning; history updating' : 'Violation recorded'}`,
            `fleet-detail-alert${alert.historical ? ' is-historical' : ''}`));
        return node;
    }
    function drawZones(p) {
        zonesLayer.clearLayers();
        if (!p) return;
        for (const zone of zones.filter(z => z.status === 'active' && (z.gps_device_id != null
            ? Number(z.gps_device_id) === Number(p.device_id) : Number(z.vehicle_id) === Number(p.vehicle_id)))) {
            const style = {color: zone.color ?? '#4a9b89', weight: 2, fillOpacity: 0.12, dashArray: '6 4'};
            const shape = zone.type === 'circle' ? L.circle([Number(zone.center_lat), Number(zone.center_lng)], {...style, radius: Number(zone.radius_meters)})
                : L.polygon(zone.points.map(point => [Number(point.latitude), Number(point.longitude)]), style);
            shape.bindTooltip(make('span', zone.name)).addTo(zonesLayer);
        }
    }
    function select(p) {
        selected = fleetKey(p);
        if (hasPosition(p)) {roadMode = 'auto'; map.setView([p.latitude, p.longitude], 18); markers.get(fleetKey(p))?.openPopup();}
        drawZones(p); render();
        Offcanvas.getOrCreateInstance(byId('fleet-details-drawer')).show();
    }
    function render() {
        const list = [...vehicles.values()];
        const counts = {total: list.length, online: 0, reconnecting: 0, moving: 0, offline: 0, alerts: 0};
        const states = new Map();
        warnings.clear();
        for (const p of list) {
            warnings.set(fleetKey(p), liveGeofenceWarnings(p, zones, confirmed.filter(a => Number(a.gps_device_id) === Number(p.device_id))));
            const connection = fleetPresentation(p).connection;
            if (connection === 'Online') {counts.online++; if (p.connection_connected === false) counts.reconnecting++;}
            else if (connection === 'Reconnecting') counts.reconnecting++;
            else counts.offline++;
            if (fleetState(p) === 'moving') counts.moving++;
            counts.alerts += warnings.get(fleetKey(p)).length;
            const state = stateByVehicle.get(fleetKey(p));
            if (state) states.set(state, (states.get(state) ?? 0) + 1);
        }
        root.querySelectorAll('[data-dashboard-stat]').forEach(el => el.textContent = counts[el.dataset.dashboardStat]);
        const shown = list.filter(visible);
        const shownIds = new Set(shown.map(p => fleetKey(p)));
        for (const [id, row] of rows) row.hidden = !shownIds.has(id);
        for (const p of list) {
            let row = rows.get(fleetKey(p));
            if (!row) {
                row = make('button', null, 'fleet-dashboard-vehicle'); row.type = 'button';
                row.append(make('strong'), make('small'), make('small', null, 'fleet-row-alert'));
                row.addEventListener('click', () => {const latest = vehicles.get(fleetKey(p)); if (latest) select(latest);});
                rows.set(fleetKey(p), row); byId('dashboard-fleet-roster').append(row);
            }
            row.hidden = !shownIds.has(fleetKey(p));
            row.setAttribute('aria-pressed', String(selected === fleetKey(p)));
            row.style.borderLeft = `3px solid ${color(p)}`;
            row.querySelector('strong').textContent = fleetLabel(p);
            const presentation = fleetPresentation(p);
            row.querySelector('small').textContent = `${p.imei ? `IMEI ${p.imei} · ` : ''}${presentation.connection} · ${presentation.movement} · ${p.speed ?? '—'} km/h`;
            row.querySelector('.fleet-row-alert').textContent = warnings.get(fleetKey(p)).length
                ? `${warnings.get(fleetKey(p)).length} geofence warning(s)` : !hasPosition(p) ? 'Awaiting GPS location' : '';
            const marker = markers.get(fleetKey(p));
            if (marker) {
                if (shownIds.has(fleetKey(p)) && !map.hasLayer(marker)) marker.addTo(map);
                if (!shownIds.has(fleetKey(p)) && map.hasLayer(marker)) marker.remove();
                const appearance = `${color(p)}:${vehicleIcon(p)}:${fleetState(p) === 'moving'}:${warnings.get(fleetKey(p)).length > 0}`;
                if (marker.dashboardAppearance !== appearance) {
                    marker.setIcon(L.divIcon({className: `fleet-dashboard-marker${fleetState(p) === 'moving' ? ' is-moving' : ''}${warnings.get(fleetKey(p)).length ? ' has-warning' : ''}`,
                        iconSize: [32, 32], iconAnchor: [16, 16], html: `<div class="fleet-dashboard-marker-body" style="--vehicle-color:${color(p)}"><i class="fa-solid ${vehicleIcon(p)}" aria-hidden="true"></i></div>`}));
                    marker.dashboardAppearance = appearance;
                }
                if (marker.isPopupOpen()) updatePopup(marker, p);
            }
        }
        let empty = byId('dashboard-fleet-roster').querySelector('.fleet-empty');
        if (!empty) {empty = make('p', null, 'fleet-empty'); byId('dashboard-fleet-roster').prepend(empty);}
        empty.hidden = shown.length > 0;
        empty.textContent = list.length ? 'No vehicles match these filters.' : admin ? 'No registered vehicles yet.' : 'No vehicles assigned. Ask your admin to assign your vehicle to your account.';
        byId('fleet-visible-count').textContent = `${shown.length} shown / ${list.length} total`;
        const missing = list.filter(p => !hasPosition(p)).length;
        byId('fleet-unlocated').textContent = `${list.length - missing} with GPS location · ${missing} awaiting GPS. Select “Awaiting GPS location” to view unlocated vehicles.`;
        for (const state of stateLayers) {
            const count = states.get(state.feature.properties.shapeName) ?? 0;
            state.count.textContent = count ? `${count} vehicle${count === 1 ? '' : 's'}` : '';
            state.path.setStyle({fillColor: count ? '#a9d8c7' : '#d6e5df', fillOpacity: roadsVisible ? 0 : 0.93, opacity: roadsVisible ? 0.35 : 1});
            state.path.getTooltip()?.setContent(make('span', `${stateName(state.feature)} · ${count} vehicle(s)`));
        }
        const p = vehicles.get(selected);
        if (p) {
            const panel = byId('fleet-vehicle-detail');
            const open = panel.dataset.vehicle === String(fleetKey(p)) ? [...panel.querySelectorAll('details')].map(el => el.open) : [];
            const content = detail(p);
            content.querySelectorAll('details').forEach((el, index) => el.open = open[index] ?? false);
            panel.dataset.vehicle = String(fleetKey(p)); panel.replaceChildren(content);
        }
        else if (selected != null) {selected = null; zonesLayer.clearLayers(); byId('fleet-vehicle-detail').replaceChildren(make('p', 'Select a vehicle to see its telemetry.'));}
    }
    function updatePopup(marker, p) {
        const node = make('div'); const presentation = fleetPresentation(p);
        node.append(make('strong', fleetLabel(p)), make('div', `${presentation.connection} · ${presentation.movement}`),
            make('div', `Last GPS speed: ${p.speed ?? 'Unknown'} km/h`), make('div', `GPS: ${date(p.recorded_at)}`));
        for (const a of warnings.get(fleetKey(p)) ?? []) node.append(make('div', `${a.historical ? 'Historical warning' : 'Alert'}: ${a.label ?? warningLabel(a.type)} · ${a.geofence_name}`));
        marker.setPopupContent(node);
    }
    function update(incoming) {
        const previous = vehicles.get(fleetKey(incoming));
        const p = previous?.device_id === incoming.device_id ? mergeFleetUpdate(previous, incoming) : incoming;
        vehicles.set(fleetKey(p), p);
        if (!previous || previous.latitude !== p.latitude || previous.longitude !== p.longitude) stateByVehicle.set(fleetKey(p), locate(p));
        if (!hasPosition(p)) {
            motions.get(fleetKey(p))?.stop(); motions.delete(fleetKey(p));
            markers.get(fleetKey(p))?.remove(); markers.delete(fleetKey(p)); return;
        }
        let marker = markers.get(fleetKey(p));
        if (!marker) {
            marker = L.marker([p.latitude, p.longitude], {keyboard: true, title: fleetLabel(p),
                icon: L.divIcon({className: 'fleet-dashboard-marker', html: '', iconSize: [32, 32], iconAnchor: [16, 16]})});
            marker.bindPopup(make('span', fleetLabel(p)), {autoPan: false});
            marker.bindTooltip(make('span', fleetLabel(p)), {direction: 'top', offset: [0, -18]});
            marker.on('click', () => {const current = vehicles.get(fleetKey(p)); if (current) select(current);});
            markers.set(fleetKey(p), marker); motions.set(fleetKey(p), new HardwareMarkerMotion(marker));
            motions.get(fleetKey(p)).timestamp = Date.parse(p.recorded_at);
            requestAnimationFrame(() => markerDrawn(p));
        } else if (Date.parse(p.recorded_at) > Date.parse(previous?.recorded_at)) {
            if (fleetState(p) === 'moving' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches)
                motions.get(fleetKey(p)).push([p.latitude, p.longitude], Date.parse(p.recorded_at), {onFrame: () => markerDrawn(p)});
            else {motions.get(fleetKey(p)).stop(); marker.setLatLng([p.latitude, p.longitude]); motions.get(fleetKey(p)).timestamp = Date.parse(p.recorded_at); requestAnimationFrame(() => markerDrawn(p));}
        }
    }
    function onLocation(p) {
        if (stopped || !vehicles.has(fleetKey(p)) || vehicles.get(fleetKey(p)).device_id !== p.device_id) return;
        update(browserReceived(p)); render();
    }
    function onAlert(payload) {
        if (stopped || ![...vehicles.values()].some(p => Number(p.device_id) === Number(payload.device_id))) return;
        // Reconcile authoritative active episodes instead of replaying queued alerts.
        sync();
    }
    function subscribe() {
        if (!echo) return;
        const wanted = admin && root.dataset.hardwareAccess === '1'
            ? new Set([...vehicles.values()].filter(p => p.device_id != null).map(p => `admin.gps.hardware.device.${p.device_id}`))
            : new Set([`dashboard.fleet.${root.dataset.user}`]);
        if (admin && root.dataset.hardwareAccess === '1') wanted.add('admin.gps.alerts');
        for (const [name] of channels) if (!wanted.has(name)) {echo.leave(name); channels.delete(name);}
        for (const name of wanted) if (!channels.has(name)) {
            const channel = echo.private(name).listen('.hardware.location.updated', onLocation).listen('.vehicle.geofence.alert', onAlert)
                .subscribed(() => {socketLive = true; connectionStatus(); sync();})
                .error(() => {socketLive = false; connectionStatus();});
            channels.set(name, channel);
        }
    }
    function connectionStatus() {
        byId('fleet-connection').textContent = !lastSync ? 'Connecting to fleet…' : socketLive
            ? 'Live GPS · auto-sync every 2s' : `Auto-sync every 2s · ${lastSync.toLocaleTimeString()}`;
    }
    async function sync() {
        if (stopped || loading) return;
        loading = true; controller = new AbortController();
        byId('fleet-refresh').disabled = true;
        try {
            const all = [], allZones = [], allAlerts = []; let after = 0, kind = 'vehicles';
            do {
                const {data} = await axios.get(root.dataset.url, {params: {after, kind}, signal: controller.signal, timeout: 15000});
                all.push(...data.vehicles); allZones.push(...data.zones); allAlerts.push(...data.alerts);
                const next = data.meta.has_more ? data.meta.next_after : null;
                const nextKind = data.meta.next_kind ?? kind;
                if (next !== null && nextKind === kind && next <= after) throw new Error('Invalid fleet cursor');
                after = next;
                kind = nextKind;
            } while (after !== null && !stopped);
            if (stopped) return;
            const keep = new Set(all.map(fleetKey));
            for (const id of vehicles.keys()) if (!keep.has(id)) {
                motions.get(id)?.stop(); motions.delete(id); markers.get(id)?.remove(); markers.delete(id);
                rows.get(id)?.remove(); rows.delete(id); vehicles.delete(id); stateByVehicle.delete(id);
            }
            zones = [...new Map(allZones.map(z => [z.rule_id, z])).values()]; confirmed = allAlerts;
            all.forEach(update); drawZones(vehicles.get(selected)); render(); subscribe();
            lastSync = new Date(); connectionStatus();
            if (byId('fleet-map-message').dataset.kind === 'sync') {message(''); delete byId('fleet-map-message').dataset.kind;}
        } catch (error) {
            if (!stopped && error.code !== 'ERR_CANCELED') {
                byId('fleet-connection').textContent = 'Sync interrupted · retrying';
                message('Fleet updates are unavailable. Last received data is shown; retrying automatically.'); byId('fleet-map-message').dataset.kind = 'sync';
            }
        } finally {loading = false; byId('fleet-refresh').disabled = false;}
    }
    async function loadMap() {
        try {
            const {data} = await axios.get('/maps/india-states.geojson', {timeout: 20000});
            if (stopped) return;
            features = data.features;
            const sorted = [...features].sort((a, b) => stateName(a).localeCompare(stateName(b)));
            sorted.forEach(f => {const option = make('option', stateName(f)); option.value = f.properties.shapeName; byId('fleet-state-filter').append(option);});
            const layer = L.geoJSON(data, {pane: 'state-relief', style: {color: '#f4faf8', weight: 1.3, fillColor: '#d6e5df', fillOpacity: 0.93},
                onEachFeature(feature, path) {
                    const label = make('div', stateName(feature)); const count = make('b'); label.append(count);
                    L.marker(labelPosition(feature), {pane: 'state-labels', interactive: false, keyboard: false,
                        icon: L.divIcon({className: 'fleet-state-label', html: label, iconSize: [88, 34], iconAnchor: [44, 17]})}).addTo(stateLabels);
                    path.bindTooltip(make('span', stateName(feature)));
                    path.on('click', () => {byId('fleet-state-filter').value = feature.properties.shapeName; changeState();});
                    stateLayers.push({feature, path, count});
                }}).addTo(map);
            boundaries = layer;
            for (const p of vehicles.values()) stateByVehicle.set(fleetKey(p), locate(p));
            // Include islands and the complete national outline on first open.
            indiaBounds.extend(layer.getBounds()); indiaView(); updateMapDetail(); render();
        } catch {if (!stopped) message('State boundaries could not load. Vehicle positions remain available. Reload this page to retry.');}
    }
    function changeState() {
        stateFilter = byId('fleet-state-filter').value;
        const state = stateLayers.find(s => s.feature.properties.shapeName === stateFilter);
        byId('fleet-map-title').textContent = stateFilter === '__unlocated' ? 'Awaiting GPS · No coordinates received'
            : state ? `${stateName(state.feature)} · Last known vehicle positions` : 'India · All states & territories';
        roadMode = 'auto';
        if (state) {
            const points = [...vehicles.values()].filter(p => visible(p) && hasPosition(p)).map(p => [p.latitude, p.longitude]);
            if (points.length) map.fitBounds(points, {padding: [55, 55], maxZoom: 17});
            else map.fitBounds(state.path.getBounds(), {padding: [25, 25], maxZoom: 11});
        } else indiaView();
        updateMapDetail();
        render();
    }
    byId('fleet-state-filter').addEventListener('change', changeState);
    byId('fleet-search').addEventListener('input', event => {search = event.target.value.trim().toLowerCase(); render();});
    root.querySelectorAll('[data-fleet-filter]').forEach(button => button.addEventListener('click', () => {
        statusFilter = button.dataset.fleetFilter; root.querySelectorAll('[data-fleet-filter]').forEach(b => b.setAttribute('aria-pressed', String(b === button))); render();
    }));
    byId('fleet-india-view').addEventListener('click', () => {byId('fleet-state-filter').value = ''; changeState();});
    byId('fleet-fit').addEventListener('click', () => {
        const points = [...vehicles.values()].filter(p => visible(p) && hasPosition(p)).map(p => [p.latitude, p.longitude]);
        roadMode = 'auto';
        if (points.length) map.fitBounds(points, {padding: [55, 55], maxZoom: 18}); else indiaView();
    });
    byId('fleet-refresh').addEventListener('click', sync);
    function updateMapDetail() {
        roadsVisible = roadMode === 'streets' || streetDetailZoom(map.getZoom());
        if (roadsVisible && !map.hasLayer(roads)) roads.addTo(map);
        if (!roadsVisible && map.hasLayer(roads)) roads.remove();
        if (roadsVisible) stateLabels.remove(); else if (!map.hasLayer(stateLabels)) stateLabels.addTo(map);
        // At city/street scale, polygons and relief must not cover roads or
        // buildings, or intercept double-click zoom/panning gestures.
        if (map.getZoom() >= 10) boundaries?.remove();
        else if (boundaries && !map.hasLayer(boundaries)) boundaries.addTo(map);
        byId('dashboard-india-map').classList.toggle('is-street-detail', roadsVisible);
        byId('fleet-roads').setAttribute('aria-pressed', String(roadsVisible));
        const layerLabel = roadsVisible ? 'India overview' : 'Street map';
        byId('fleet-roads').setAttribute('aria-label', layerLabel);
        byId('fleet-roads').title = layerLabel; render();
    }
    map.on('zoomend', updateMapDetail);
    byId('fleet-roads').addEventListener('click', () => {
        if (roadsVisible) {roadMode = 'auto'; byId('fleet-state-filter').value = ''; changeState();}
        else {roadMode = 'streets'; updateMapDetail();}
    });
    const resize = () => map.invalidateSize();
    const resizeStage = () => {sizeStage(); resize();};
    window.addEventListener('resize', resizeStage);
    const fullscreenChange = () => {
        resizeStage();
        const expanded = document.fullscreenElement === root;
        byId('fleet-fullscreen').setAttribute('aria-label', expanded ? 'Exit full screen' : 'Enter full screen');
        byId('fleet-fullscreen').title = expanded ? 'Exit full screen' : 'Full screen';
        byId('fleet-fullscreen').querySelector('i').className = `fa-solid ${expanded ? 'fa-compress' : 'fa-expand'}`;
    };
    document.addEventListener('fullscreenchange', fullscreenChange);
    byId('fleet-fullscreen').addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === root) await document.exitFullscreen();
            else await root.requestFullscreen();
        } catch {message('Full screen is unavailable in this browser. The map still fills the dashboard.');}
    });
    const observer = new ResizeObserver(resize); observer.observe(byId('dashboard-india-map'));
    const header = document.querySelector('.tracker-topbar');
    const headerObserver = new ResizeObserver(resizeStage);
    if (header) headerObserver.observe(header);
    const socket = echo?.connector?.pusher?.connection;
    const socketState = ({current}) => {socketLive = current === 'connected' && channels.size > 0; connectionStatus();};
    socket?.bind('state_change', socketState);
    const refreshTimer = setInterval(sync, 2000);
    const clockTimer = setInterval(() => {if (vehicles.size) render();}, 1000);
    loadMap(); sync();
    window.addEventListener('pagehide', () => {
        stopped = true; controller?.abort(); clearInterval(refreshTimer); clearInterval(clockTimer);
        socket?.unbind('state_change', socketState); observer.disconnect();
        headerObserver.disconnect(); window.removeEventListener('resize', resizeStage);
        document.removeEventListener('fullscreenchange', fullscreenChange);
        panels.forEach(panel => Offcanvas.getInstance(panel)?.dispose());
        channels.forEach((_, name) => echo?.leave(name)); motions.forEach(motion => motion.stop()); map.remove();
    }, {once: true});
}
