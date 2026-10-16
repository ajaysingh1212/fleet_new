import L from 'leaflet';
import { Modal } from 'bootstrap';
import { RoutePlayback } from './route-playback';
import { geofenceContains } from './map-geometry';

function istToday() {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en', {
        timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date()).map(({ type, value }) => [type, value]));
    return `${parts.year}-${parts.month}-${parts.day}`;
}
function dayOffset(day, offset) {
    const date = new Date(day + 'T12:00:00Z');
    date.setUTCDate(date.getUTCDate() + offset);
    return date.toISOString().slice(0, 10);
}

export class RouteHistory {
    constructor(people) {
        this.people = people;
        this.root = document.getElementById('route-history-modal');
        this.modal = new Modal(this.root);
        this.from = document.getElementById('route-history-from');
        this.to = document.getElementById('route-history-to');
        this.preset = document.getElementById('route-history-preset');
        this.status = document.getElementById('route-history-status');
        this.download = document.getElementById('route-history-download');
        this.sequence = 0;
        this.userId = null;
        this.playButton = document.getElementById('route-replay-play');
        this.restartButton = document.getElementById('route-replay-restart');
        this.seek = document.getElementById('route-replay-seek');
        this.speed = document.getElementById('route-replay-speed');
        this.location = document.getElementById('route-replay-location');
        this.replayTime = document.getElementById('route-replay-time');
        this.coordinates = document.getElementById('route-replay-coordinates');
        this.follow = document.getElementById('route-replay-follow');
        this.timeFormat = new Intl.DateTimeFormat('en-IN', {
            timeZone: 'Asia/Kolkata', dateStyle: 'short', timeStyle: 'medium',
        });
        this.playback = new RoutePlayback({
            onFrame: (frame) => this.renderPlayback(frame),
            onState: (running) => {
                const label = running ? 'Pause' : 'Play';
                this.playButton.title = label;
                this.playButton.setAttribute('aria-label', label);
                this.playButton.setAttribute('aria-pressed', String(running));
                this.playButton.querySelector('i').className = running ? 'fa-solid fa-pause' : 'fa-solid fa-play';
            },
        });
        this.playButton.addEventListener('click', () => {
            if (this.playback.running) this.playback.pause();
            else this.playback.play();
        });
        this.restartButton.addEventListener('click', () => this.playback.seek(0));
        this.seek.addEventListener('input', () => this.playback.seek(Number(this.seek.value)));
        this.speed.addEventListener('change', () => this.playback.setSpeed(Number(this.speed.value)));
        this.resetPlayback();
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-route-history]');
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();
            const id = Number(button.dataset.routeHistory);
            if (!this.people.get(id)?.canRouteHistory) return;
            this.userId = id;
            document.getElementById('route-history-title').textContent = this.people.get(id).name + ' - Route History';
            const today = istToday();
            for (const input of [this.from, this.to]) {
                input.min = dayOffset(today, -29);
                input.max = today;
            }
            this.from.value = today;
            this.to.value = today;
            this.preset.value = 'today';
            this.modal.show();
        });
        this.root.addEventListener('shown.bs.modal', () => {
            if (!this.map) {
                this.map = L.map('route-history-map', { preferCanvas: true }).setView([20.5937, 78.9629], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
                }).addTo(this.map);
                this.layer = L.layerGroup().addTo(this.map);
            }
            this.map.invalidateSize();
            this.load();
        });
        this.root.addEventListener('hidden.bs.modal', () => {
            this.resetPlayback();
            this.controller?.abort();
            this.sequence++;
            this.layer?.clearLayers();
            this.userId = null;
            this.disableDownload();
        });
        this.root.addEventListener('hide.bs.modal', () => {
            this.playback.pause();
            this.controller?.abort();
            this.sequence++;
        });
        this.preset.addEventListener('change', () => {
            if (this.preset.value === 'custom') return;
            const today = istToday();
            this.to.value = this.preset.value === 'yesterday' ? dayOffset(today, -1) : today;
            this.from.value = this.preset.value === 'last_30_days' ? dayOffset(today, -29)
                : this.preset.value === 'last_7_days' ? dayOffset(today, -6) : this.to.value;
            this.load();
        });
        for (const input of [this.from, this.to]) input.addEventListener('change', () => {
            this.preset.value = 'custom';
            this.controller?.abort();
            this.sequence++;
            this.resetPlayback();
            this.disableDownload();
        });
        document.getElementById('route-history-load').addEventListener('click', () => this.load());
        this.download.addEventListener('click', (event) => {
            if (this.download.getAttribute('aria-disabled') === 'true') event.preventDefault();
        });
        this.disableDownload();
    }
    resetPlayback() {
        this.playback.setPoints([]);
        if (this.replayMarker && this.layer) this.layer.removeLayer(this.replayMarker);
        this.replayMarker = null;
        this.lastLabelUpdate = null;
        for (const input of [this.playButton, this.restartButton, this.seek, this.speed]) input.disabled = true;
        this.seek.value = '0';
        this.location.textContent = 'No route selected';
        this.replayTime.textContent = '';
        this.coordinates.textContent = '';
    }
    renderPlayback(frame) {
        if (!frame || !this.layer) return;
        const position = [frame.latitude, frame.longitude];
        if (!this.replayMarker) {
            this.replayMarker = L.marker(position, { zIndexOffset: 1000, icon: L.divIcon({
                className: 'route-replay-marker',
                html: '<i class="fa-solid fa-location-arrow"></i>',
                iconSize: [32, 32], iconAnchor: [16, 16],
            }) }).addTo(this.layer);
        } else this.replayMarker.setLatLng(position);
        this.seek.value = String(frame.position);
        if (this.follow.checked && !this.map.getBounds().pad(-0.15).contains(position)) {
            this.map.panTo(position, { animate: false });
        }
        const now = performance.now();
        if (this.lastLabelUpdate !== null && now - this.lastLabelUpdate < 100 && this.playback.running) return;
        this.lastLabelUpdate = now;
        const names = (this.people.get(this.userId)?.geofences ?? [])
            .filter((zone) => geofenceContains(zone, frame.latitude, frame.longitude))
            .map((zone) => zone.name);
        this.location.textContent = names.length ? names.join(', ') : 'Outside named geofences';
        this.replayTime.textContent = this.timeFormat.format(new Date(frame.recorded_at)) + ' IST';
        this.coordinates.textContent = frame.latitude.toFixed(5) + ', ' + frame.longitude.toFixed(5);
        this.seek.setAttribute('aria-valuetext', this.replayTime.textContent);
    }
    disableDownload() {
        this.download.removeAttribute('href');
        this.download.classList.add('disabled');
        this.download.setAttribute('aria-disabled', 'true');
    }
    checkAccess() {
        if (this.userId && !this.people.get(this.userId)?.canRouteHistory) {
            this.controller?.abort();
            this.sequence++;
            this.resetPlayback();
            this.layer?.clearLayers();
            this.disableDownload();
            this.modal.hide();
        }
    }
    async load() {
        if (!this.userId || !this.people.get(this.userId)?.canRouteHistory) return;
        this.controller?.abort();
        this.resetPlayback();
        this.controller = new AbortController();
        const sequence = ++this.sequence;
        this.layer?.clearLayers();
        this.disableDownload();
        if (!this.from.checkValidity() || !this.to.checkValidity() || this.from.value > this.to.value) {
            this.status.textContent = 'Select a valid date range within the last 30 days.';
            return;
        }
        const params = { from: this.from.value, to: this.to.value };
        const id = this.userId;
        this.status.textContent = 'Loading route...';
        try {
            const points = [];
            let lastPage = 1;
            let total = 0;
            for (let page = 1; page <= lastPage; page++) {
                const { data } = await window.axios.get('/live-map/route-history/' + id, {
                    params: { ...params, page }, signal: this.controller.signal, timeout: 15000,
                });
                if (sequence !== this.sequence) return;
                points.push(...data.data);
                lastPage = data.meta.last_page;
                total = data.meta.total;
                this.status.textContent = `Loading route: ${points.length} / ${total} points`;
            }
            if (!points.length) {
                this.status.textContent = 'No saved locations for these dates.';
                return;
            }
            let segment = [];
            let previous = null;
            const draw = () => {
                if (segment.length > 1) L.polyline(segment, { color: '#167d72', weight: 4 }).addTo(this.layer);
                segment = [];
            };
            for (const point of points) {
                if (previous && (point.tracking_session_id !== previous.tracking_session_id
                    || new Date(point.recorded_at) - new Date(previous.recorded_at) > 1800000)) draw();
                segment.push([point.latitude, point.longitude]);
                previous = point;
            }
            draw();
            const start = points[0];
            const end = points[points.length - 1];
            L.circleMarker([start.latitude, start.longitude], { radius: 7, color: '#198754' })
                .bindTooltip('Start').addTo(this.layer);
            L.circleMarker([end.latitude, end.longitude], { radius: 7, color: '#dc3545' })
                .bindTooltip('End').addTo(this.layer);
            this.map.fitBounds(L.latLngBounds(points.map((p) => [p.latitude, p.longitude])).pad(0.12), { maxZoom: 17 });
            const format = (value) => new Date(value).toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' });
            this.status.textContent = `${points.length} saved points | ${format(start.recorded_at)} to ${format(end.recorded_at)} IST`;
            this.seek.max = String(points.length - 1);
            this.seek.disabled = points.length < 2;
            this.playButton.disabled = points.length < 2;
            this.restartButton.disabled = points.length < 2;
            this.speed.disabled = points.length < 2;
            this.playback.setPoints(points);
            this.download.href = '/live-map/route-history/' + id + '/download?' + new URLSearchParams(params);
            this.download.classList.remove('disabled');
            this.download.setAttribute('aria-disabled', 'false');
        } catch (error) {
            if (sequence !== this.sequence || error.code === 'ERR_CANCELED') return;
            const status = error.response?.status;
            this.status.textContent = status === 401 ? 'Session expired. Sign in again.'
                : status === 403 ? 'You no longer have access to this person.'
                : error.response?.data?.message ?? 'Could not load route history.';
        }
    }
}
