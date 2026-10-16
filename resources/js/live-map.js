import './bootstrap';
import { Modal } from 'bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { mountReportCharts } from './report-charts';
import { RouteHistory } from './route-history';
import { geofenceContains } from './map-geometry';
import { LiveMarkerMotion } from './live-marker-motion';
import '../css/live-map.css';

const LIGHT_TILES = {
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
};

const DARK_TILES = {
    // Use the same key-free provider in dark mode. The dark appearance is
    // applied with CSS so the map does not depend on a paid tile API key.
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
};

const DEFAULT_CENTER = [20.5937, 78.9629];

const markerOptions = JSON.parse(document.getElementById('map-marker-options')?.textContent ?? '{}');

function markerContent(initial, preference) {
    if (preference?.kind === 'other' && preference.imageUrl) {
        const image = document.createElement('img');
        image.src = preference.imageUrl;
        image.alt = 'Custom map icon';
        image.style.cssText = 'width:38px;height:38px;object-fit:contain;border-radius:4px';
        return image.outerHTML;
    }
    if (preference?.kind && preference.kind !== 'person') {
        return `<i class="fa-solid ${markerOptions[preference.kind]?.[1] ?? 'fa-car'}" aria-hidden="true"></i>`;
    }
    return initial;
}

function markerState(person) {
    return person.licenseExpired ? 'expired' : !person.isOnline ? 'offline'
        : person.movementStatus === 'moving' ? 'moving' : 'idle';
}

function markerIcon(initial, isOnline, isSelf = false, preference = null, movement = 'idle') {
    const classes = ['tracker-map-marker', `tracker-map-marker-${isOnline ? movement : 'offline'}`, isSelf ? 'tracker-map-marker-self' : ''];

    return L.divIcon({
        className: classes.filter(Boolean).join(' '),
        html: `<span class="tracker-map-marker-arrow"></span><span class="tracker-map-marker-dot">${markerContent(initial, preference)}</span>`,
        iconSize: [52, 52],
        iconAnchor: [26, 26],
        popupAnchor: [0, -28],
    });
}

function formatLastSeen(iso) {
    if (!iso) return 'Never';

    const diffSeconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);

    if (diffSeconds < 60) return `${diffSeconds}s ago`;
    if (diffSeconds < 3600) return `${Math.round(diffSeconds / 60)}m ago`;

    return `${Math.round(diffSeconds / 3600)}h ago`;
}

function formatSpeed(speedMps) {
    if (speedMps === null || speedMps === undefined) return '—';

    const kmh = speedMps * 3.6;

    return `${kmh.toFixed(1)} km/h`;
}

function movementLabel(status) {
    return status === 'moving' ? 'Moving' : 'Idle';
}

function complianceClass(status) {
    if (status === 'visited') return 'tracker-status-active';
    if (status === 'missed') return 'tracker-status-danger';

    return 'tracker-status-pending';
}


class LiveMap {
    constructor(containerId, people) {
        this.people = new Map(people.map((person) => [person.id, person]));
        this.markers = new Map();
        this._bindMarkerEditor();
        this.reportModalEl = document.getElementById('live-map-report-modal');
        this.reportModal = this.reportModalEl ? new Modal(this.reportModalEl) : null;
        this.reportUserId = null;
        this.reportCharts = null;

        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const tiles = isDark ? DARK_TILES : LIGHT_TILES;

        document.getElementById(containerId)?.classList.toggle('tracker-live-map-dark', isDark);

        this.map = L.map(containerId, { zoomControl: true }).setView(DEFAULT_CENTER, 5);
        L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: 19 }).addTo(this.map);

        this.geofenceLayer = L.layerGroup().addTo(this.map);

        const withLocation = people.filter((p) => p.lat !== null && p.lng !== null);

        withLocation.forEach((person) => this._placeMarker(person));

        if (withLocation.length > 0) {
            const bounds = L.latLngBounds(withLocation.map((p) => [p.lat, p.lng]));
            this.map.fitBounds(bounds.pad(0.3));
        }

        people.forEach((person) => this._subscribe(person));
        people.forEach((person) => this._updateGeofenceState(person));
        this._renderGeofenceAlerts();
        this._bindReportModal();
        this.routeHistory = new RouteHistory(this.people);
        this._pollSnapshot();
    }

    _refreshMarkerIcon(person) {
        const marker = this.markers.get(person.id);
        if (!marker) return;
        marker.setIcon(markerIcon(person.name.charAt(0).toUpperCase(), person.isOnline, person.isSelf, person.marker, person.movementStatus));
        const arrow = marker.getElement()?.querySelector('.tracker-map-marker-arrow');
        if (arrow && person.bearing != null) arrow.style.transform = `translateX(-50%) rotate(${person.bearing}deg)`;
    }

    _bindMarkerEditor() {
        const element = document.getElementById('map-marker-modal');
        if (!element) return;
        const modal = new Modal(element);
        const form = document.getElementById('map-marker-form');
        const kind = document.getElementById('map-marker-kind');
        const image = document.getElementById('map-marker-image');
        const error = document.getElementById('map-marker-error');
        const save = document.getElementById('map-marker-save');
        let selectedId = null;
        let previewUrl = null;
        const preview = () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = image.files[0] ? URL.createObjectURL(image.files[0]) : null;
            document.getElementById('map-marker-upload').classList.toggle('d-none', kind.value !== 'other');
            const person = this.people.get(selectedId);
            document.getElementById('map-marker-preview').innerHTML = markerContent(
                person?.name.charAt(0).toUpperCase() ?? '',
                { kind: kind.value, imageUrl: previewUrl ?? person?.marker?.imageUrl },
            );
        };
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-marker-edit]');
            if (!button) return;
            selectedId = Number(button.dataset.markerEdit);
            const person = this.people.get(selectedId);
            if (!person) return;
            form.reset();
            kind.value = person.marker?.kind ?? 'person';
            document.getElementById('map-marker-title').textContent = `${person.name} - Map icon`;
            error.textContent = '';
            preview();
            modal.show();
        });
        kind.addEventListener('change', preview);
        image.addEventListener('change', preview);
        element.addEventListener('hidden.bs.modal', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const personId = selectedId;
            const data = new FormData();
            data.append('kind', kind.value);
            if (kind.value === 'other' && image.files[0]) data.append('image', image.files[0]);
            save.disabled = true;
            error.textContent = '';
            try {
                const response = await window.axios.post(`/live-map/marker/${personId}`, data);
                const person = this.people.get(personId);
                if (person) {
                    person.marker = response.data;
                    this._refreshMarkerIcon(person);
                }
                modal.hide();
            } catch (failure) {
                error.textContent = Object.values(failure.response?.data?.errors ?? {}).flat().join(' ')
                    || 'Could not save map icon. Please try again.';
            } finally {
                save.disabled = false;
            }
        });
    }

    _placeMarker(person) {
        const marker = L.marker([person.lat, person.lng], {
            icon: markerIcon(person.name.charAt(0).toUpperCase(), person.isOnline, person.isSelf, person.marker, person.movementStatus),
        }).addTo(this.map);

        marker.bindPopup(this._popupHtml(person));
        marker.on('click', () => this._showGeofencesFor(person));
        this.markers.set(person.id, marker);
    }

    _showGeofencesFor(person) {
        this.geofenceLayer.clearLayers();

        const geofences = person.geofences ?? [];

        if (!geofences.length) return;

        const shapes = [];

        geofences.forEach((geofence) => {
            let shape;

            if (geofence.type === 'circle') {
                shape = L.circle([geofence.center_lat, geofence.center_lng], {
                    radius: geofence.radius_meters,
                    color: geofence.color,
                    fillOpacity: 0.12,
                });
            } else if (geofence.points?.length >= 3) {
                shape = L.polygon(geofence.points.map((point) => [point.latitude, point.longitude]), {
                    color: geofence.color,
                    fillOpacity: 0.12,
                });
            }

            if (!shape) return;

            const label = [
                geofence.sequence ? `#${geofence.sequence}` : null,
                geofence.name,
                geofence.route_label ? `Route: ${geofence.route_label}` : null,
                `Status: ${geofence.compliance_status}`,
            ].filter(Boolean).join(' | ');

            shape.bindTooltip(label, { sticky: true });
            shape.bindPopup(`
                <div class="tracker-map-popup">
                    <strong>${geofence.name}</strong>
                    <div class="tracker-map-popup-badges">
                        <span class="tracker-status-pill ${complianceClass(geofence.compliance_status)}">${geofence.compliance_status}</span>
                    </div>
                    <div>Route: ${geofence.route_label || 'Default'}</div>
                    <div>Order: ${geofence.sequence ?? '-'}</div>
                    <div>Routine: ${geofence.schedule_type}</div>
                    <div>Window: ${geofence.window_start || '-'} to ${geofence.window_end || '-'}</div>
                    <div>${geofence.compliance_status === 'missed' ? 'Pending/bypassed checkpoint: user did not visit this location in the expected window.' : ''}</div>
                </div>
            `);
            shape.addTo(this.geofenceLayer);
            shapes.push(shape);
        });

        if (shapes.length) {
            const bounds = shapes.reduce((acc, shape) => (acc ? acc.extend(shape.getBounds()) : L.latLngBounds(shape.getBounds().getSouthWest(), shape.getBounds().getNorthEast())), null);
            this.map.fitBounds(bounds.pad(0.3));
        }
    }

    _popupHtml(person) {
        const statusClass = person.isOnline ? 'tracker-status-active' : 'tracker-status-muted';
        const statusLabel = person.isOnline ? 'Online' : 'Offline';

        return `
            <div class="tracker-map-popup">
                <strong>${person.name}${person.isSelf ? ' (You)' : ''}</strong>
                <div class="tracker-map-popup-badges">
                    <span class="tracker-status-pill ${statusClass}">${statusLabel}</span>
                    ${person.isOnline ? `<span class="tracker-status-pill ${person.movementStatus === 'moving' ? 'tracker-status-active' : 'tracker-status-pending'}">${movementLabel(person.movementStatus)}</span>` : ''}
                </div>
                <div>Speed: ${formatSpeed(person.speed)}</div>
                <div>Battery: ${person.battery !== null && person.battery !== undefined ? person.battery + '%' : '—'}</div>
                <div>GPS: ${person.gpsEnabled === null || person.gpsEnabled === undefined ? '—' : (person.gpsEnabled ? 'On' : 'Off')}</div>
                <div>Internet: ${person.internetEnabled === null || person.internetEnabled === undefined ? '—' : (person.internetEnabled ? 'On' : 'Off')}</div>
                <div>Network: ${person.networkType || '—'}</div>
                <div>Last location: ${formatLastSeen(person.lastSeen)}</div>
                <div>Assigned geofences: ${(person.geofences ?? []).length}</div>
                ${person.canRouteHistory ? `<button type="button" class="btn btn-sm tracker-outline-btn" data-route-history="${person.id}" title="Route history" aria-label="Route history"><i class="fa-solid fa-route"></i></button>` : ''}
                ${person.isSelf ? `<div>Tracked by: ${person.trackedBy?.length ? person.trackedBy.join(', ') : 'No one right now'}</div>` : ''}
            </div>
        `;
    }

    _subscribe(person) {
        if (!window.Echo || person.licenseExpired) return;
        const channel = window.Echo.private(`App.Models.User.${person.id}`);

        channel.listen('.gps.location.updated', (payload) => this._onLocationUpdate(person.id, payload));
        channel.listen('.presence.changed', (payload) => this._onPresenceChange(person.id, payload));
        channel.listen('.geofence.event', (payload) => this._onGeofenceEvent(person, payload));
        channel.listen('.geofence.overspeed', (payload) => this._onGeofenceOverspeed(person, payload));
    }

    _toast(icon, title) {
        window.Swal?.fire({
            toast: true,
            position: 'top-end',
            timer: 6000,
            timerProgressBar: true,
            showConfirmButton: false,
            icon,
            title,
        });
    }

    _onGeofenceEvent(person, payload) {
        const who = person.isSelf ? 'You' : person.name;
        const verb = payload.type === 'entered' ? 'entered' : 'exited';

        this._toast('info', `${who} ${verb} ${payload.geofence.name}`);

        const geofence = (person.geofences ?? []).find((item) => item.uuid === payload.geofence.uuid);
        if (geofence) geofence.is_outside = payload.type === 'exited';
        this._applyGeofenceWarning(person);
        this._renderGeofenceAlerts();
    }

    _onGeofenceOverspeed(person, payload) {
        const who = person.isSelf ? 'You are' : `${person.name} is`;

        this._toast('warning', `${who} overspeeding in ${payload.geofence.name}: ${payload.speed_kmh} km/h (limit ${payload.limit_kmh} km/h)`);
    }

    _onLocationUpdate(personId, payload) {
        const person = this.people.get(personId);

        if (!person || person.licenseExpired) return;
        if (person.lastSeen && new Date(payload.recorded_at) <= new Date(person.lastSeen)) return;

        const sampleInterval = new Date(payload.recorded_at).getTime() - new Date(person.lastSeen).getTime();
        const to = { lat: payload.latitude, lng: payload.longitude };

        Object.assign(person, {
            lat: payload.latitude,
            lng: payload.longitude,
            speed: payload.speed,
            bearing: payload.bearing,
            battery: payload.battery_level,
            gpsEnabled: payload.is_gps_enabled ?? person.gpsEnabled,
            internetEnabled: payload.is_internet_enabled ?? person.internetEnabled,
            networkType: payload.network_type ?? person.networkType,
            movementStatus: payload.movement_status,
            isOnline: true,
            lastSeen: payload.recorded_at,
        });

        this._updateGeofenceState(person);

        let marker = this.markers.get(personId);

        if (!marker) {
            marker = L.marker([to.lat, to.lng], { icon: markerIcon(person.name.charAt(0).toUpperCase(), true, person.isSelf, person.marker, person.movementStatus) }).addTo(this.map);
            marker.bindPopup(this._popupHtml(person));
            marker.on('click', () => this._showGeofencesFor(person));
            this.markers.set(personId, marker);
        } else {
            marker._gpsMotion ??= new LiveMarkerMotion(marker);
            marker._gpsMotion.push(to, sampleInterval);
            marker.setPopupContent(this._popupHtml(person));
            this._setMarkerOnline(marker, true, person.movementStatus);
        }

        if (payload.bearing !== null) {
            const arrow = marker.getElement()?.querySelector('.tracker-map-marker-arrow');
            if (arrow) arrow.style.transform = `translateX(-50%) rotate(${payload.bearing}deg)`;
        }

        this._updateListRow(personId, person);
        this._renderGeofenceAlerts();
    }

    _updateGeofenceState(person) {
        if (person.lat === null || person.lat === undefined || person.lng === null || person.lng === undefined) return;

        (person.geofences ?? []).forEach((geofence) => {
            geofence.is_outside = !geofenceContains(geofence, Number(person.lat), Number(person.lng));
        });
        this._applyGeofenceWarning(person);
    }

    _applyGeofenceWarning(person) {
        const outside = (person.geofences ?? []).some((geofence) => geofence.is_outside);
        const row = document.querySelector(`[data-person-row="${person.id}"]`);
        row?.classList.toggle('tracker-map-person-row-warning', outside);
        row?.querySelector('[data-field="geofence-warning"]')?.classList.toggle('d-none', !outside);
        this.markers.get(person.id)?.getElement()?.classList.toggle('tracker-map-marker-warning', outside);
    }

    _renderGeofenceAlerts() {
        const root = document.getElementById('live-map-geofence-alerts');
        if (!root) return;

        const alerts = [];
        this.people.forEach((person) => {
            const outside = (person.geofences ?? []).filter((geofence) => geofence.is_outside);
            if (outside.length) alerts.push({ person, outside });
        });

        root.classList.toggle('d-none', alerts.length === 0);
        root.innerHTML = alerts.map(({ person, outside }) => `
            <div class="tracker-live-map-alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><strong>${person.name} is outside the assigned geofence</strong><span>${outside.map((item) => item.name).join(', ')}</span></div>
            </div>
        `).join('');
    }

    _onPresenceChange(personId, payload) {
        const person = this.people.get(personId);

        if (!person) return;

        Object.assign(person, {
            isOnline: payload.is_online,
            lastActivity: payload.last_activity_at,
        });

        const marker = this.markers.get(personId);

        if (marker) {
            this._setMarkerOnline(marker, payload.is_online, person.movementStatus);
            marker.setPopupContent(this._popupHtml(person));
        }

        this._updateListRow(personId, person);
    }

    async _pollSnapshot() {
        try {
            const { data } = await window.axios.get('/live-map/snapshot', { timeout: 8000 });
            const allowed = new Set(data.people.map((person) => person.id));
            for (const [id] of this.people) {
                if (!allowed.has(id)) {
                    const marker = this.markers.get(id);
                    if (marker) {
                        marker._gpsMotion?.stop();
                        this.map.removeLayer(marker);
                    }
                    this.markers.delete(id);
                    this.people.delete(id);
                    window.Echo?.leave(`App.Models.User.${id}`);
                    document.querySelector(`[data-person-row="${id}"]`)?.remove();
                }
            }
            data.people.forEach((snapshot) => {
                if (!this.people.has(snapshot.id)) {
                    this.people.set(snapshot.id, { ...snapshot, lastSeen: null });
                    this._appendPersonRow(snapshot);
                    this._subscribe(snapshot);
                }
                const person = this.people.get(snapshot.id);
                if (JSON.stringify(person.marker) !== JSON.stringify(snapshot.marker)) {
                    person.marker = snapshot.marker;
                    this._refreshMarkerIcon(person);
                }
                const wasExpired = person.licenseExpired;
                person.licenseExpired = snapshot.licenseExpired ?? false;
                person.canReport = snapshot.canReport ?? true;
                person.renewUrl = snapshot.renewUrl;
                if (person.licenseExpired) {
                    const marker = this.markers.get(snapshot.id);
                    marker?._gpsMotion?.stop();
                    if (marker) this.map.removeLayer(marker);
                    this.markers.delete(snapshot.id);
                    window.Echo?.leave(`App.Models.User.${snapshot.id}`);
                    Object.assign(person, { lat: null, lng: null, lastSeen: null, speed: null, geofences: [] });
                } else if (wasExpired) {
                    this._subscribe(person);
                }
                person.geofences = snapshot.geofences;
                person.canRouteHistory = snapshot.canRouteHistory;
                document.querySelector(`[data-person-row="${snapshot.id}"] [data-route-history]`)?.classList.toggle('d-none', !snapshot.canRouteHistory);
                if (snapshot.lat !== null && snapshot.lng !== null) {
                    this._onLocationUpdate(snapshot.id, {
                        latitude: snapshot.lat, longitude: snapshot.lng,
                        speed: snapshot.speed, bearing: snapshot.bearing,
                        battery_level: snapshot.battery, network_type: snapshot.networkType,
                        is_gps_enabled: snapshot.gpsEnabled,
                        is_internet_enabled: snapshot.internetEnabled,
                        movement_status: snapshot.movementStatus, recorded_at: snapshot.lastSeen,
                    });
                }
                Object.assign(person, {
                    battery: snapshot.battery, gpsEnabled: snapshot.gpsEnabled,
                    internetEnabled: snapshot.internetEnabled, networkType: snapshot.networkType,
                });
                this._onPresenceChange(snapshot.id, {
                    is_online: snapshot.isOnline, last_activity_at: snapshot.lastActivity,
                });
                this._updateGeofenceState(person);
            });
            this._renderGeofenceAlerts();
            this.routeHistory?.checkAccess();
            const count = document.getElementById('live-map-people-count');
            if (count) count.textContent = `${this.people.size} visible`;
            document.querySelector('#live-map-people-list .tracker-empty-state')
                ?.classList.toggle('d-none', this.people.size > 0);
        } catch (error) {
            if ([401, 403, 419].includes(error.response?.status)) return;
        }
        this.pollTimer = window.setTimeout(() => this._pollSnapshot(), 3000);
    }

    _setMarkerOnline(marker, isOnline, movement = 'idle') {
        const el = marker.getElement();
        if (!isOnline) marker._gpsMotion?.stop();

        el?.classList.toggle('tracker-map-marker-offline', !isOnline);
        el?.classList.toggle('tracker-map-marker-moving', isOnline && movement === 'moving');
        el?.classList.toggle('tracker-map-marker-idle', isOnline && movement !== 'moving');
        el?.setAttribute('aria-label', !isOnline ? 'Offline' : movement === 'moving' ? 'Moving' : 'Stationary');
    }

    _appendPersonRow(person) {
        const list = document.getElementById('live-map-people-list');
        if (!list) return;
        const row = document.createElement('div');
        row.className = 'tracker-map-person-row';
        row.classList.toggle('tracker-map-person-row-self', person.isSelf);
        row.dataset.personRow = person.id;
        row.setAttribute('role', 'button');
        const avatar = document.createElement('div');
        avatar.className = 'tracker-avatar-sm';
        avatar.textContent = person.name.charAt(0).toUpperCase();
        const copy = document.createElement('div');
        copy.className = 'tracker-map-person-copy';
        const name = document.createElement('strong');
        name.textContent = person.name;
        copy.append(name);
        for (const field of ['status', 'movement', 'last-seen', 'geofence-warning']) {
            const label = document.createElement('span');
            label.dataset.field = field;
            label.className = field === 'status' ? 'tracker-status-pill' : 'text-muted small';
            if (field === 'geofence-warning') {
                label.className = 'tracker-status-pill tracker-status-danger d-none';
                label.textContent = 'Outside geofence';
            }
            copy.append(label);
        }
        const report = document.createElement('button');
        let history;
        if (person.canRouteHistory) {
            history = document.createElement('button');
            history.type = 'button';
            history.className = 'btn btn-sm tracker-outline-btn';
            history.dataset.routeHistory = person.id;
            history.title = 'Route history';
            history.setAttribute('aria-label', 'Route history');
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-route';
            history.append(icon);
        }
        report.type = 'button';
        report.className = 'btn btn-sm tracker-outline-btn tracker-live-report-btn';
        report.dataset.reportUser = person.id;
        report.title = 'Open reports';
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-chart-pie';
        report.append(icon);
        row.append(avatar, copy, report);
        if (history) row.append(history);
        list.append(row);
    }

    _updateListRow(personId, person) {
        const row = document.querySelector(`[data-person-row="${personId}"]`);

        if (!row) return;
        row.dataset.state = markerState(person);
        const avatar = row.querySelector('.tracker-avatar-sm');
        if (avatar) avatar.innerHTML = markerContent(person.name.charAt(0).toUpperCase(), person.marker);
        if (!row.querySelector('[data-marker-edit]')) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-sm tracker-outline-btn';
            edit.dataset.markerEdit = personId;
            edit.title = 'Edit map icon';
            edit.setAttribute('aria-label', 'Edit map icon');
            edit.innerHTML = '<i class="fa-solid fa-car-side"></i>';
            row.append(edit);
        }

        let expired = row.querySelector('[data-field="license-expired"]');
        let renew = row.querySelector('[data-license-renew]');
        if (!expired) {
            expired = document.createElement('span');
            expired.dataset.field = 'license-expired';
            expired.className = 'tracker-status-pill tracker-status-danger';
            expired.textContent = 'License expired';
            renew = document.createElement('a');
            renew.dataset.licenseRenew = '';
            renew.className = 'btn btn-sm tracker-outline-btn';
            renew.innerHTML = '<i class="fa-solid fa-rotate"></i> Renew';
            row.querySelector('.tracker-map-person-copy').append(expired, renew);
        }
        expired.classList.toggle('d-none', !person.licenseExpired);
        renew.classList.toggle('d-none', !person.licenseExpired);
        renew.href = person.renewUrl ?? '#';
        row.querySelector('[data-report-user]')?.classList.toggle('d-none', person.canReport === false);

        const statusEl = row.querySelector('[data-field="status"]');

        if (statusEl) {
            statusEl.replaceChildren(document.createTextNode(person.isOnline ? 'Online' : 'Offline'));
            statusEl.classList.toggle('tracker-status-active', person.isOnline);
            statusEl.classList.toggle('tracker-status-muted', !person.isOnline);
        }

        const movementEl = row.querySelector('[data-field="movement"]');

        if (movementEl) {
            if (person.isOnline && person.lat !== null && person.lat !== undefined) {
                movementEl.replaceChildren(document.createTextNode(`${movementLabel(person.movementStatus)} · ${formatSpeed(person.speed)}`));
                movementEl.classList.remove('d-none');
            } else {
                movementEl.classList.add('d-none');
            }
        }

        row.querySelector('[data-field="last-seen"]')?.replaceChildren(
            document.createTextNode(person.lat !== null && person.lat !== undefined ? formatLastSeen(person.lastSeen) : (person.isOnline ? 'No location shared yet' : 'Never logged in')),
        );

        if (row.dataset.gpsBound) return;
        row.dataset.gpsBound = 'true';
        row.addEventListener('click', (event) => {
            if (event.target.closest('button, a')) return;
            this._showGeofencesFor(person);

            if (person.lat === null || person.lat === undefined) return;

            this.map.flyTo(this.markers.get(personId)?.getLatLng() ?? [person.lat, person.lng], 15);
            this.markers.get(personId)?.openPopup();
        });
    }

    _bindReportModal() {
        document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-report-user]');
                if (!button) return;
                event.stopPropagation();
                this.reportUserId = Number(button.dataset.reportUser);
                const person = this.people.get(this.reportUserId);
                document.getElementById('live-map-report-title').textContent = `${person?.name ?? 'Employee'} Reports`;
                this.reportModal?.show();
                this._loadReport();
        });

        document.getElementById('live-report-load')?.addEventListener('click', () => this._loadReport());
        document.getElementById('live-report-type')?.addEventListener('change', () => this._loadReport());
        document.getElementById('live-report-preset')?.addEventListener('change', () => this._loadReport());
    }

    async _loadReport() {
        if (!this.reportUserId) return;

        const params = {
            user_id: this.reportUserId,
            type: document.getElementById('live-report-type')?.value ?? 'employee_daily',
            preset: document.getElementById('live-report-preset')?.value ?? 'today',
        };

        const minSpeed = document.getElementById('live-report-min-speed')?.value;
        const maxSpeed = document.getElementById('live-report-max-speed')?.value;
        if (minSpeed !== '') params.min_speed = minSpeed;
        if (maxSpeed !== '') params.max_speed = maxSpeed;

        const output = document.getElementById('live-report-output');
        output.textContent = 'Loading realtime report...';

        let report; let analytics; let attendance; let timeline; let heatmap;

        try {
            [{ data: report }, { data: analytics }, { data: attendance }, { data: timeline }, { data: heatmap }] = await Promise.all([
                window.axios.get('/api/v1/reports', { params }),
                window.axios.get('/api/v1/analytics', { params }),
                window.axios.get('/api/v1/attendance', { params }),
                window.axios.get('/api/v1/timeline', { params }),
                window.axios.get('/api/v1/heatmap', { params }),
            ]);
        } catch (error) {
            output.textContent = `Could not load report: ${error.response?.data?.message ?? error.message}`;

            return;
        }

        this._renderReportCards(analytics.data, attendance.data, timeline.data, heatmap.data, report.data);
        this._renderReportMenu(report.data.phase4_tables ?? []);
        this._renderDiagnostics(report.data.device_diagnostics ?? { events: [] });
        this._renderGeofenceActivity(report.data.geofence_activity ?? []);

        output.textContent = JSON.stringify({
            report: report.data,
            analytics: analytics.data,
            attendance: attendance.data,
            timeline: timeline.data.slice(0, 25),
            heatmap: heatmap.data.summary,
        }, null, 2);

        const chartPayload = { report: report.data, analytics: analytics.data, attendance: attendance.data, timeline: timeline.data, heatmap: heatmap.data };

        if (!this.reportCharts) {
            const root = document.getElementById('live-report-chart-root');
            const contentBlock = document.getElementById('live-report-content-block');

            if (contentBlock) contentBlock.classList.remove('d-none');

            this.reportCharts = mountReportCharts('live-report', root, contentBlock, chartPayload);
        } else {
            this.reportCharts.render(chartPayload);
        }
    }

    _renderReportCards(analytics, attendance, timeline, heatmap, report) {
        const internetOutages = report.device_diagnostics?.internet_outages ?? [];
        const gpsOutages = report.device_diagnostics?.gps_outages ?? [];
        const internetDownMinutes = Math.round(internetOutages.reduce((sum, o) => sum + o.duration_seconds, 0) / 60);

        const cards = [
            ['Distance', `${analytics.total_distance_km} km`],
            ['Moving', `${Math.round((analytics.moving_seconds ?? 0) / 60)} min`],
            ['Idle', `${Math.round((analytics.idle_seconds ?? 0) / 60)} min`],
            ['Attendance', attendance.status],
            ['Work Hours', `${attendance.working_hours} h`],
            ['Timeline', `${timeline.length} events`],
            ['Heat Zones', `${heatmap.summary?.clusters ?? 0}`],
            ['Max Speed', `${analytics.maximum_speed_mps ?? '—'} m/s`],
            ['Internet Outages', `${internetOutages.length} (${internetDownMinutes} min down)`],
            ['GPS Outages', `${gpsOutages.length}`],
            ['Geofence Events', `${report.geofence_activity?.length ?? 0}`],
        ];

        document.getElementById('live-report-cards').innerHTML = cards.map(([label, value]) => `
            <div class="col-md-3">
                <div class="card tracker-surface-card h-100">
                    <div class="card-body">
                        <p class="tracker-card-subtitle mb-1">${label}</p>
                        <h4 class="tracker-card-title mb-0">${value}</h4>
                    </div>
                </div>
            </div>
        `).join('');
    }

    _renderDiagnostics(deviceDiagnostics) {
        const rows = deviceDiagnostics.events ?? [];
        const tbody = document.getElementById('live-report-diagnostics');

        if (!tbody) return;

        tbody.innerHTML = rows.length
            ? rows.map((event) => `
                <tr>
                    <td>${event.label}</td>
                    <td>${new Date(event.occurred_at).toLocaleString()}</td>
                    <td>${event.battery_level !== null && event.battery_level !== undefined ? event.battery_level + '%' : '—'}</td>
                    <td>${event.network_type || '—'}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="4" class="text-muted">No diagnostic events in this range.</td></tr>';
    }

    _renderGeofenceActivity(activity) {
        const tbody = document.getElementById('live-report-geofence');

        if (!tbody) return;

        tbody.innerHTML = activity.length
            ? activity.map((event) => `
                <tr>
                    <td>${event.geofence_name ?? '—'}</td>
                    <td>${event.type}</td>
                    <td>${new Date(event.occurred_at).toLocaleString()}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="3" class="text-muted">No geofence activity in this range.</td></tr>';
    }

    _renderReportMenu(tables) {
        document.getElementById('live-report-menu').innerHTML = tables.map((table) => `
            <div class="col-md-6 col-xl-4">
                <div class="card tracker-surface-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>${table.label}</strong>
                            <span class="tracker-status-pill tracker-status-active">${table.records}</span>
                        </div>
                        <p class="tracker-card-subtitle mb-1">${table.description}</p>
                        <div class="small text-muted">Formats: ${table.formats.join(', ')}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('live-map');

    if (!container) return;

    const dataEl = document.getElementById('live-map-data');
    const people = dataEl ? JSON.parse(dataEl.textContent) : [];

    new LiveMap('live-map', people);

});
