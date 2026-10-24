import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/hardware-gps-history.css';
import {timestamp, speedColor, isGap, playbackTimeline, playbackIndex, measuredDistance} from './history-playback.mjs';
import {historyPdf} from './history-pdf.mjs';

const time = value => {
    if (!value) return '—';
    const normalized = /^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/.test(value) ? value.replace(' ', 'T') + 'Z' : value;
    return new Date(normalized).toLocaleString('en-IN', {timeZone: 'Asia/Kolkata'});
};

export function initHardwareGpsHistory() {
    const root = document.getElementById('hardware-history');
    if (!root) return;
    const el = name => document.getElementById(`history-${name}`);
    const form = document.getElementById('gps-history-form');
    let map, layers, marker, chartCursor, searchTimer, searchController, reportController, searchRevision = 0, reportRevision = 0, selectedParams;
    let points = [], timeline = [], currentIndex = -1, elapsed = 0, frame, lastFrame, playing = false, reportData, exportController;
    const pages = {route_page: 1, geofence_page: 1};
    const message = (text, error = false) => {
        el('message').hidden = !text;
        el('message').className = `alert ${error ? 'alert-danger' : 'alert-info'}`;
        el('message').textContent = text;
    };
    const resetReport = () => {
        ++reportRevision;
        pause(); exportController?.abort(); reportData = null;
        reportController?.abort();
        selectedParams = null;
        el('results').hidden = true;
        el('load').disabled = false;
    };
    const readiness = () => {
        const custom = el('preset').value === 'custom';
        el('custom').hidden = !custom;
        el('from').required = el('to').required = custom;
        el('from').disabled = el('to').disabled = !custom;
        el('to').min = el('from').value;
        const datesReady = !custom || (el('from').value && el('to').value && el('to').value >= el('from').value);
        el('period').disabled = !el('target').value;
        el('report-choice').disabled = !el('target').value || !datesReady;
        el('report-choice').hidden = false;
        message(!el('target').value ? 'Select a vehicle or IMEI to get started.'
            : !datesReady ? 'Select From and To dates. To must be on or after From.' : 'Select your report and click View report.');
    };
    const search = async () => {
        const revision = ++searchRevision;
        searchController?.abort();
        searchController = new AbortController();
        el('search-status').textContent = 'Searching…';
        try {
            const {data} = await axios.get(root.dataset.targetsUrl, {params: {kind: el('kind').value, q: el('search').value.trim()}, signal: searchController.signal, timeout: 10000});
            if (revision !== searchRevision) return;
            el('target').replaceChildren(...data.data.map(item => new Option(item.label, item.value)));
            el('target').selectedIndex = -1;
            el('search-status').textContent = data.has_more ? 'Showing 50 matches. Type more to narrow your search.'
                : data.data.length ? `${data.data.length} matches. Select one to continue.` : 'No matching vehicles or trackers.';
            readiness();
        } catch (error) {
            if (revision === searchRevision && !axios.isCancel(error)) el('search-status').textContent = 'Could not load vehicles. Change the search to retry.';
        }
    };
    const invalidateSearch = () => {
        clearTimeout(searchTimer); ++searchRevision; searchController?.abort();
        el('target').replaceChildren(); resetReport(); readiness();
    };
    el('kind').addEventListener('change', () => {
        invalidateSearch(); el('search').value = '';
        el('search').placeholder = el('kind').value === 'device' ? 'Search IMEI, tracker or registration' : 'Search registration or vehicle name';
        search();
    });
    el('search').addEventListener('input', () => { invalidateSearch(); searchTimer = setTimeout(search, 300); });
    for (const name of ['target', 'preset', 'from', 'to']) el(name).addEventListener('change', () => { resetReport(); readiness(); });
    form.querySelectorAll('[name="history-report"]').forEach(input => input.addEventListener('change', () => { resetReport(); readiness(); }));
    const rows = (name, data, values, empty) => {
        const body = el(`${name}-rows`);
        body.replaceChildren();
        if (!data.length) {
            const row = body.insertRow(), cell = row.insertCell();
            cell.colSpan = 5; cell.className = 'text-center text-muted py-4'; cell.textContent = empty;
        }
        for (const item of data) {
            const row = body.insertRow();
            for (const value of values(item)) row.insertCell().textContent = value ?? '—';
        }
    };
    const pagination = (name, data) => {
        const node = el(`${name}-pages`), previous = document.createElement('button'), next = document.createElement('button'), label = document.createElement('span');
        for (const button of [previous, next]) { button.type = 'button'; button.className = 'btn tracker-outline-btn btn-sm'; }
        previous.textContent = 'Previous'; next.textContent = 'Next';
        previous.disabled = data.current_page <= 1; next.disabled = data.current_page >= data.last_page;
        label.className = 'small text-muted'; label.textContent = `${data.total} records · Page ${data.current_page} of ${data.last_page}`;
        previous.onclick = () => { pages[`${name}_page`] = data.current_page - 1; load(); };
        next.onclick = () => { pages[`${name}_page`] = data.current_page + 1; load(); };
        node.replaceChildren(previous, label, next);
    };
    const pause = () => {
        playing = false; cancelAnimationFrame(frame); lastFrame = null;
        el('play').innerHTML = '<i class="fa-solid fa-play" aria-hidden="true"></i> Play';
        el('play').setAttribute('aria-label', 'Play route');
    };
    const showPoint = (index, follow = false) => {
        if (!points.length) return;
        currentIndex = Math.max(0, Math.min(points.length - 1, index));
        const p = points[currentIndex], speed = p.speed;
        el('current-speed').textContent = speed == null ? '—' : Math.round(speed);
        const maximum = Math.max(160, Math.ceil(Number(reportData?.route?.summary.max_speed ?? 0) / 40) * 40);
        const percent = Math.min(100, Math.max(0, Number(speed ?? 0) / maximum * 100));
        el('gauge-arc').style.strokeDasharray = `${percent} 100`;
        el('gauge-arc').style.stroke = speedColor(speed);
        el('gauge-needle').setAttribute('transform', `rotate(${percent * 1.8} 120 120)`);
        el('gauge-mid').textContent = maximum / 2; el('gauge-max').textContent = maximum;
        el('current-time').textContent = time(p.recorded_at);
        el('current-location').textContent = `${p.latitude.toFixed(6)}, ${p.longitude.toFixed(6)}`;
        el('current-point').textContent = `${currentIndex + 1} of ${points.length}`;
        el('motion').textContent = speed == null ? 'Speed unavailable' : speed > 0 ? 'Moving' : 'Stationary';
        el('progress').value = currentIndex;
        el('playback-time').textContent = time(p.recorded_at);
        el('playback-state').textContent = currentIndex && isGap(points[currentIndex - 1], p) ? 'GPS gap / tracker change skipped' : currentIndex === points.length - 1 ? 'End of route' : playing ? 'Playing · measured GPS time' : 'Paused';
        if (marker) marker.setLatLng([p.latitude, p.longitude]);
        if (follow && map && !map.getBounds().contains([p.latitude, p.longitude])) map.panTo([p.latitude, p.longitude]);
        if (chartCursor) {chartCursor.setAttribute('x1', points.length > 1 ? currentIndex / (points.length - 1) * 1000 : 0); chartCursor.setAttribute('x2', chartCursor.getAttribute('x1'));}
        el('route-rows').querySelectorAll('tr[data-point]').forEach(row => row.classList.toggle('is-selected', row.dataset.point === String(p.id)));
    };
    const seek = index => { pause(); elapsed = timeline[index] ?? 0; showPoint(index, true); };
    const tick = now => {
        if (!playing) return;
        if (lastFrame != null) elapsed += (now - lastFrame) * Number(el('playback-speed').value);
        lastFrame = now;
        const index = playbackIndex(timeline, elapsed);
        if (index !== currentIndex) showPoint(index, true);
        const a = points[index], b = points[index + 1];
        if (marker && b && !isGap(a, b)) {
            const fraction = Math.max(0, Math.min(1, (elapsed - timeline[index]) / (timeline[index + 1] - timeline[index])));
            marker.setLatLng([a.latitude + (b.latitude - a.latitude) * fraction, a.longitude + (b.longitude - a.longitude) * fraction]);
        }
        if (elapsed >= timeline.at(-1)) {pause(); el('playback-state').textContent = 'Playback complete'; return;}
        frame = requestAnimationFrame(tick);
    };
    el('play').addEventListener('click', () => {
        if (playing) {pause(); el('playback-state').textContent = 'Paused'; return;}
        if (currentIndex === points.length - 1) {elapsed = 0; showPoint(0);}
        playing = true; lastFrame = null;
        el('play').innerHTML = '<i class="fa-solid fa-pause" aria-hidden="true"></i> Pause';
        el('play').setAttribute('aria-label', 'Pause route');
        el('playback-state').textContent = 'Playing · measured GPS time'; frame = requestAnimationFrame(tick);
    });
    el('restart').addEventListener('click', () => seek(0));
    el('progress').addEventListener('input', () => seek(Number(el('progress').value)));
    document.addEventListener('visibilitychange', () => {if (document.hidden) pause();});
    const renderChart = () => {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg'); svg.setAttribute('viewBox', '0 0 1000 110'); svg.setAttribute('preserveAspectRatio', 'none');
        const max = Math.max(10, ...points.map(p => Number(p.speed ?? 0)));
        // Break the chart at gaps and unavailable speed samples too.
        let d = '';
        points.forEach((p, i) => {if (p.speed == null) return; const x = points.length > 1 ? i / (points.length - 1) * 1000 : 0, y = 95 - p.speed / max * 80;
            d += `${!i || points[i - 1].speed == null || isGap(points[i - 1], p) ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)} `;
        });
        const path = document.createElementNS(ns, 'path'); path.setAttribute('d', d); path.setAttribute('fill', 'none'); path.setAttribute('stroke', '#0d9488'); path.setAttribute('stroke-width', '2'); path.setAttribute('vector-effect', 'non-scaling-stroke'); svg.append(path);
        chartCursor = document.createElementNS(ns, 'line'); chartCursor.setAttribute('y1', '5'); chartCursor.setAttribute('y2', '103'); chartCursor.setAttribute('stroke', '#173d52'); chartCursor.setAttribute('stroke-dasharray', '4 4'); svg.append(chartCursor);
        el('speed-chart').replaceChildren(svg);
        svg.addEventListener('click', event => {if (!points.length) return; const box = svg.getBoundingClientRect(); seek(Math.max(0, Math.min(points.length - 1, Math.round((event.clientX - box.left) / box.width * (points.length - 1)))));});
    };
    const drawRoute = route => {
        pause(); points = route.map_points.filter(p => Number.isFinite(p.latitude) && Number.isFinite(p.longitude) && Math.abs(p.latitude) <= 90 && Math.abs(p.longitude) <= 180);
        timeline = playbackTimeline(points); currentIndex = -1; elapsed = 0;
        el('play').disabled = points.length < 2; el('restart').disabled = el('progress').disabled = !points.length;
        el('progress').max = Math.max(0, points.length - 1); el('progress').value = 0;
        el('route-map').hidden = !points.length;
        renderChart();
        if (!points.length) {
            layers?.clearLayers(); marker = null;
            for (const name of ['current-speed', 'current-time', 'current-location', 'current-point']) el(name).textContent = '—';
            el('motion').textContent = 'No GPS positions'; el('playback-time').textContent = 'No route recorded'; el('playback-state').textContent = 'No data';
            el('gauge-arc').style.strokeDasharray = '0 100'; el('gauge-needle').setAttribute('transform', 'rotate(0 120 120)'); return;
        }
        if (!map) {
            map = L.map(el('route-map')).setView([20.59, 78.96], 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap contributors', maxZoom: 19}).addTo(map);
            layers = L.layerGroup().addTo(map);
        }
        layers.clearLayers();
        const popup = (p, label) => {const node = document.createElement('div'); node.textContent = `${label} · ${time(p.recorded_at)} · ${p.speed ?? '—'} km/h`; return node;};
        points.forEach((p, i) => {
            if (i && !isGap(points[i - 1], p)) L.polyline([[points[i - 1].latitude, points[i - 1].longitude], [p.latitude, p.longitude]], {color: speedColor(p.speed), weight: 5, opacity: .85}).addTo(layers).bindPopup(popup(p, 'Recorded speed')).on('click', () => seek(i));
            L.circleMarker([p.latitude, p.longitude], {radius: 3, weight: 0, fillColor: speedColor(p.speed), fillOpacity: .8}).addTo(layers).bindPopup(popup(p, 'GPS point')).on('click', () => seek(i));
        });
        for (const [p, label, color] of [[points[0], 'Start', '#16803c'], [points.at(-1), 'End', '#dc3545']]) L.circleMarker([p.latitude, p.longitude], {radius: 7, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1}).addTo(layers).bindPopup(popup(p, label));
        marker = L.marker([points[0].latitude, points[0].longitude], {icon: L.divIcon({className: 'journey-vehicle-marker', html: '<i class="fa-solid fa-car" aria-hidden="true"></i>', iconSize: [34, 34], iconAnchor: [17, 17]}), zIndexOffset: 1000}).addTo(layers);
        showPoint(0);
        requestAnimationFrame(() => {map.invalidateSize(); map.fitBounds(points.map(p => [p.latitude, p.longitude]), {padding: [30, 30], maxZoom: 16});});
    };
    const summaryCards = route => {
        const stats = el('route-summary'); stats.replaceChildren();
        const duration = route.summary.first_at && route.summary.last_at ? Math.max(0, timestamp(route.summary.last_at) - timestamp(route.summary.first_at)) : 0;
        const cards = [['GPS records', Number(route.summary.points).toLocaleString('en-IN'), 'points'], ['Maximum speed', route.summary.max_speed == null ? '—' : Math.round(route.summary.max_speed), 'km/h'], ['Recorded window', `${Math.floor(duration / 3600000)}h ${Math.floor(duration % 3600000 / 60000)}m`, 'first to last'], [route.map_truncated ? 'Mapped distance (partial)' : 'Mapped distance', measuredDistance(route.map_points).toFixed(2), 'km · gaps excluded']];
        for (const [label, value, unit] of cards) {const card = document.createElement('div'); card.className = 'journey-stat'; const caption = document.createElement('span'), number = document.createElement('strong'), suffix = document.createElement('small'); caption.textContent = label; number.textContent = value; suffix.textContent = unit; number.append(suffix); card.append(caption, number); stats.append(card);}
    };
    const decorateRouteRows = records => {
        [...el('route-rows').rows].forEach((row, i) => {
            const p = records[i]; if (!p) return; row.dataset.point = p.id;
            const selectPoint = () => {
                const index = points.findIndex(point => point.id === p.id);
                if (index >= 0) seek(index);
                else {pause(); const node = document.createElement('div'); node.textContent = `${time(p.recorded_at)} · ${p.speed ?? '—'} km/h`; map?.setView([p.latitude, p.longitude], 16); L.popup().setLatLng([p.latitude, p.longitude]).setContent(node).openOn(map); message('This record is outside the first 5,000 playback points. Narrow the date range to replay it.');}
            };
            const button = document.createElement('button'); button.type = 'button'; button.className = 'journey-point-link'; button.textContent = row.cells[0].textContent; button.onclick = event => {event.stopPropagation(); selectPoint();}; row.cells[0].replaceChildren(button); row.onclick = selectPoint;
            const speed = document.createElement('span'); speed.className = 'journey-speed-pill'; speed.textContent = `${p.speed ?? '—'} km/h`; speed.style.color = speedColor(p.speed); row.cells[3].replaceChildren(speed);
            row.cells[4].className = 'journey-ignition'; row.cells[4].style.color = p.telemetry?.acc === true ? '#087f8c' : '#687d91';
        });
    };
    const load = async () => {
        if (!selectedParams) return;
        pause(); exportController?.abort();
        const revision = ++reportRevision;
        reportController?.abort(); reportController = new AbortController();
        el('load').disabled = true;
        el('results').hidden = true;
        message('Loading history…');
        try {
            const {data} = await axios.get(root.dataset.reportUrl, {params: {...selectedParams, ...pages}, signal: reportController.signal, timeout: 20000});
            if (revision !== reportRevision) return;
            reportData = data; el('export-status').textContent = '';
            el('result-title').textContent = `${data.target.label} · ${selectedParams.report === 'all' ? 'All Reports' : selectedParams.report === 'route' ? 'Route History' : 'Geofence Report'}`;
            el('result-range').textContent = `${time(data.range.from)} — ${time(data.range.to)} IST`;
            el('results').hidden = false;
            el('route-section').hidden = !data.route;
            el('geofence-section').hidden = !data.geofence;
            if (data.route) {
                const route = data.route;
                summaryCards(route);
                el('map-warning').hidden = !route.map_truncated;
                el('map-warning').textContent = 'Map and playback show the first 5,000 GPS points. Narrow the date range for the complete route map. All records remain available in the table pages.';
                rows('route', route.records.data, p => [time(p.recorded_at), p.latitude.toFixed(6), p.longitude.toFixed(6), p.speed,
                    p.telemetry?.acc === true ? 'ON' : p.telemetry?.acc === false ? 'OFF' : 'Unknown'], 'No GPS positions recorded in this period.');
                pagination('route', route.records); drawRoute(route); decorateRouteRows(route.records.data);
            }
            if (data.geofence) {
                const labels = {outside: 'Outside boundary', overspeed: 'Overspeed', underspeed: 'Underspeed'};
                rows('geofence', data.geofence.data, a => [a.geofence_name ?? 'Geofence removed', labels[a.type] ?? a.type, time(a.started_at),
                    a.ended_at ? time(a.ended_at) : 'Open alert', `${a.speed ?? '—'} / ${a.limit ?? '—'}`], 'No geofence alerts in this period.');
                pagination('geofence', data.geofence);
            }
            message('');
        } catch (error) {
            if (revision !== reportRevision || axios.isCancel(error)) return;
            message(Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || 'Could not load history. Please try again.', true);
        } finally { if (revision === reportRevision) el('load').disabled = false; }
    };
    el('download').addEventListener('click', async () => {
        if (!reportData || !selectedParams) return;
        const snapshot = reportData, params = {...selectedParams}, revision = reportRevision;
        exportController?.abort(); const controller = new AbortController(); exportController = controller;
        el('download').disabled = true; el('export-status').textContent = 'Preparing complete report…';
        try {
            const collect = async kind => {
                const first = kind === 'route' ? snapshot.route?.records : snapshot.geofence;
                if (!first) return null;
                // Fetch from page one even if the on-screen table is on another page.
                const records = [];
                for (let page = 1; page <= first.last_page; page++) {
                    if (controller.signal.aborted || revision !== reportRevision) throw new DOMException('Report changed', 'AbortError');
                    el('export-status').textContent = `Preparing ${kind} records · page ${page} of ${first.last_page}…`;
                    if (page === first.current_page) records.push(...first.data);
                    else {
                        const {data} = await axios.get(root.dataset.reportUrl, {params: {...params, report: kind, records_only: 1, [`${kind}_page`]: page}, signal: controller.signal, timeout: 20000});
                        records.push(...(kind === 'route' ? data.route.records.data : data.geofence.data));
                    }
                }
                return records;
            };
            const route = await collect('route'), alerts = await collect('geofence');
            if (controller.signal.aborted || revision !== reportRevision) return;
            const labels = {outside: 'Outside boundary', overspeed: 'Overspeed', underspeed: 'Underspeed'};
            const pdfTime = value => value ? new Date(timestamp(value)).toLocaleString('en-GB', {timeZone: 'Asia/Kolkata', hour12: false}) : '-';
            const pdf = historyPdf({title: el('result-title').textContent, range: `${pdfTime(snapshot.range.from)} - ${pdfTime(snapshot.range.to)} IST`,
                summary: snapshot.route?.summary, points: snapshot.route?.map_points, truncated: snapshot.route?.map_truncated,
                route: route?.map(p => [pdfTime(p.recorded_at), p.latitude.toFixed(6), p.longitude.toFixed(6), p.speed ?? '-', p.telemetry?.acc === true ? 'ON' : p.telemetry?.acc === false ? 'OFF' : 'Unknown']),
                alerts: alerts?.map(a => [a.geofence_name ?? 'Geofence removed', labels[a.type] ?? a.type, pdfTime(a.started_at), a.ended_at ? pdfTime(a.ended_at) : 'Open alert', `${a.speed ?? '-'} / ${a.limit ?? '-'}`])});
            const url = URL.createObjectURL(new Blob([pdf], {type: 'application/pdf'}));
            const link = document.createElement('a'); link.href = url; link.download = `GPS-History-${snapshot.target.label.replace(/[^a-zA-Z0-9_-]/g, '_')}.pdf`; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
            el('export-status').textContent = 'PDF downloaded with all report records.';
        } catch (error) {
            if (!controller.signal.aborted && !axios.isCancel(error)) el('export-status').textContent = 'PDF could not be prepared. Please try again.';
        } finally {
            if (exportController === controller) {el('download').disabled = false; if (controller.signal.aborted) el('export-status').textContent = '';}
        }
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity() || !el('target').value) return;
        selectedParams = {target: el('target').value, preset: el('preset').value, report: form.querySelector('[name="history-report"]:checked').value};
        if (selectedParams.preset === 'custom') Object.assign(selectedParams, {from: el('from').value, to: el('to').value});
        pages.route_page = pages.geofence_page = 1;
        load();
    });
    window.addEventListener('pagehide', () => {
        pause(); exportController?.abort();
        clearTimeout(searchTimer); ++searchRevision; ++reportRevision;
        searchController?.abort(); reportController?.abort(); map?.remove();
    }, {once: true});
    readiness(); search();
}
