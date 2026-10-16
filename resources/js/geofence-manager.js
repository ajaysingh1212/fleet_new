import './bootstrap';
import { Modal } from 'bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';

const categories = JSON.parse(document.getElementById('geofence-categories-data')?.textContent ?? '[]');

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';

    return div.innerHTML;
}

class GeofenceManager {
    constructor() {
        this.mapEl = document.getElementById('geofence-map');
        if (!this.mapEl) return;

        this.listEl = document.getElementById('geofence-list');
        this.listEmptyEl = document.getElementById('geofence-list-empty');
        this.searchEl = document.getElementById('geofence-search');
        this.categoryFilterEl = document.getElementById('geofence-filter-category');
        this.statusFilterEl = document.getElementById('geofence-filter-status');
        this.detailsModalEl = document.getElementById('geofence-details-modal');
        this.detailsModal = new Modal(this.detailsModalEl);
        this.editBarEl = document.getElementById('geofence-edit-bar');
        this.drawHintEl = document.getElementById('geofence-draw-hint');

        this.assignmentModalEl = document.getElementById('geofence-assignment-modal');
        this.assignmentModal = new Modal(this.assignmentModalEl);
        this.runsModalEl = document.getElementById('geofence-runs-modal');
        this.runsModal = new Modal(this.runsModalEl);
        this.assignmentGeofence = null;
        this.users = [];

        this.geofences = new Map();
        this.layers = new Map();
        this.selectedUuid = null;
        this.pendingShape = null;
        this.savedFromModal = false;
        this.editingExisting = null;
        this.editingUuid = null;
        this.editingLayer = null;
        this.editToolbar = null;
        this.activeDrawHandler = null;

        this._populateCategoryOptions();
        this._initMap();
        this._bindEvents();
        this._loadGeofences();
        this._loadUsers();
    }

    _populateCategoryOptions() {
        const fill = (select) => {
            if (!select) return;

            categories.forEach((cat) => {
                const option = document.createElement('option');
                option.value = cat.value;
                option.textContent = cat.label;
                select.appendChild(option);
            });
        };

        fill(this.categoryFilterEl);
        fill(document.getElementById('geofence-category-input'));
    }

    _initMap() {
        this.map = L.map(this.mapEl).setView([20.5937, 78.9629], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(this.map);

        this.drawnItems = new L.FeatureGroup().addTo(this.map);

        this.drawControl = new L.Control.Draw({
            position: 'topright',
            draw: {
                circle: { shapeOptions: { color: '#38bdf8' } },
                polygon: { shapeOptions: { color: '#38bdf8' }, allowIntersection: false },
                rectangle: { shapeOptions: { color: '#38bdf8' } },
                polyline: false,
                marker: false,
                circlemarker: false,
            },
            edit: false,
        });

        this.map.on(L.Draw.Event.CREATED, (event) => this._onShapeDrawn(event));
    }

    _bindEvents() {
        document.querySelectorAll('[data-draw-type]').forEach((button) => {
            button.addEventListener('click', () => this._startDrawing(button.dataset.drawType));
        });

        this.searchEl?.addEventListener('input', () => this._renderList());
        this.categoryFilterEl?.addEventListener('change', () => this._loadGeofences());
        this.statusFilterEl?.addEventListener('change', () => this._loadGeofences());

        document.getElementById('geofence-details-save')?.addEventListener('click', () => this._saveDetails());
        document.getElementById('geofence-export-btn')?.addEventListener('click', () => this._exportGeofences());
        document.getElementById('geofence-import-btn')?.addEventListener('click', () => document.getElementById('geofence-import-input')?.click());
        document.getElementById('geofence-import-input')?.addEventListener('change', (event) => this._importGeofences(event));

        document.getElementById('geofence-edit-cancel')?.addEventListener('click', () => this._cancelShapeEdit());
        document.getElementById('geofence-edit-save')?.addEventListener('click', () => this._saveShapeEdit());

        document.getElementById('geofence-assignment-schedule-type')?.addEventListener('change', () => this._syncAssignmentScheduleFields());
        document.getElementById('geofence-create-schedule-type')?.addEventListener('change', () => this._syncCreateScheduleFields());
        document.getElementById('geofence-assignment-save')?.addEventListener('click', () => this._saveAssignment());

        this.detailsModalEl.addEventListener('hidden.bs.modal', () => {
            if (this.pendingShape && !this.savedFromModal) {
                this.drawnItems.removeLayer(this.pendingShape.layer);
            }

            this.pendingShape = null;
            this.savedFromModal = false;
        });
    }

    _startDrawing(type) {
        this.drawHintEl?.classList.remove('d-none');

        const handlerMap = { circle: 'Circle', polygon: 'Polygon', rectangle: 'Rectangle' };
        const Handler = L.Draw[handlerMap[type]];

        if (!Handler) return;

        this.activeDrawHandler?.disable();
        this.activeDrawHandler = new Handler(this.map, this.drawControl.options.draw[type]);
        this.activeDrawHandler.enable();
    }

    _onShapeDrawn(event) {
        this.drawHintEl?.classList.add('d-none');
        this.activeDrawHandler = null;

        const layer = event.layer;
        this.drawnItems.addLayer(layer);

        this.pendingShape = {
            type: event.layerType,
            layer,
            geometry: this._layerToGeometry(event.layerType, layer),
        };

        this._openDetailsModal();
    }

    _layerToGeometry(type, layer) {
        if (type === 'circle') {
            const center = layer.getLatLng();

            return { center_lat: center.lat, center_lng: center.lng, radius_meters: Math.round(layer.getRadius()) };
        }

        const latlngs = (layer.getLatLngs()[0] ?? []).map((point) => ({ latitude: point.lat, longitude: point.lng }));

        return { points: latlngs };
    }

    _openDetailsModal(existing = null) {
        document.getElementById('geofence-details-title').textContent = existing ? 'Edit Geofence' : 'New Geofence';
        document.getElementById('geofence-name-input').value = existing?.name ?? '';
        document.getElementById('geofence-description-input').value = existing?.description ?? '';
        document.getElementById('geofence-category-input').value = existing?.category ?? categories[0]?.value ?? 'custom';
        document.getElementById('geofence-color-input').value = existing?.color ?? '#38bdf8';
        document.getElementById('geofence-min-speed-input').value = existing?.min_speed_kmh ?? '';
        document.getElementById('geofence-max-speed-input').value = existing?.max_speed_kmh ?? '';
        document.getElementById('geofence-create-assignment-fields')?.classList.toggle('d-none', Boolean(existing));
        this._resetCreateAssignmentFields();

        const statusEl = document.getElementById('geofence-details-status');
        statusEl.classList.add('d-none');
        statusEl.textContent = '';

        this.editingExisting = existing;
        this.detailsModal.show();
    }

    async _saveDetails() {
        const name = document.getElementById('geofence-name-input').value.trim();
        const statusEl = document.getElementById('geofence-details-status');

        if (!name) {
            statusEl.textContent = 'Name is required.';
            statusEl.classList.remove('d-none');
            return;
        }

        const minSpeed = document.getElementById('geofence-min-speed-input').value;
        const maxSpeed = document.getElementById('geofence-max-speed-input').value;

        const payload = {
            name,
            description: document.getElementById('geofence-description-input').value.trim() || null,
            category: document.getElementById('geofence-category-input').value,
            color: document.getElementById('geofence-color-input').value,
            min_speed_kmh: minSpeed !== '' ? Number(minSpeed) : null,
            max_speed_kmh: maxSpeed !== '' ? Number(maxSpeed) : null,
        };

        try {
            if (this.editingExisting) {
                payload.type = this.editingExisting.type;

                if (this.editingExisting.type === 'circle') {
                    payload.center_lat = this.editingExisting.center_lat;
                    payload.center_lng = this.editingExisting.center_lng;
                    payload.radius_meters = this.editingExisting.radius_meters;
                } else {
                    payload.points = this.editingExisting.points;
                }

                await window.axios.patch(`/api/v1/geofences/${this.editingExisting.uuid}`, payload);
            } else {
                this._validateCreateAssignmentBeforeGeofence();
                payload.type = this.pendingShape.type;
                Object.assign(payload, this.pendingShape.geometry);

                const { data } = await window.axios.post('/api/v1/geofences', payload);
                await this._createInitialAssignment(data.data.uuid);
                this.drawnItems.removeLayer(this.pendingShape.layer);
            }

            this.savedFromModal = true;
            this.detailsModal.hide();
            await this._loadGeofences();
        } catch (error) {
            statusEl.textContent = error.response?.data?.message ?? 'Could not save the geofence. Please check the details.';
            statusEl.classList.remove('d-none');
        }
    }

    _validateCreateAssignmentBeforeGeofence() {
        const userId = Number(document.getElementById('geofence-create-user')?.value);
        const scheduleType = document.getElementById('geofence-create-schedule-type').value;
        const selectedDays = Array.from(document.getElementById('geofence-create-days')?.selectedOptions ?? []);
        const customDate = document.getElementById('geofence-create-date').value;

        if (!userId) {
            throw { response: { data: { message: 'Tracked user is required while creating a geofence.' } } };
        }

        if (['weekly', 'monthly', 'quarterly', 'yearly'].includes(scheduleType) && selectedDays.length === 0) {
            throw { response: { data: { message: 'Please choose routine days for this schedule.' } } };
        }

        if (scheduleType === 'custom_date' && !customDate) {
            throw { response: { data: { message: 'Please choose the custom geofence date.' } } };
        }
    }

    async _createInitialAssignment(geofenceUuid) {
        const userId = Number(document.getElementById('geofence-create-user')?.value);
        const statusEl = document.getElementById('geofence-details-status');

        const scheduleType = document.getElementById('geofence-create-schedule-type').value;
        const daysSelect = document.getElementById('geofence-create-days');
        const selectedDays = Array.from(daysSelect?.selectedOptions ?? []).map((option) => {
            return scheduleType === 'yearly' ? option.value : Number(option.value);
        });

        await window.axios.post('/api/v1/geofence-assignments', {
            geofence_uuid: geofenceUuid,
            user_id: userId,
            route_label: document.getElementById('geofence-create-route-label').value.trim() || null,
            sequence: document.getElementById('geofence-create-sequence').value ? Number(document.getElementById('geofence-create-sequence').value) : null,
            schedule_type: scheduleType,
            schedule_days: ['weekly', 'monthly', 'quarterly', 'yearly'].includes(scheduleType) ? selectedDays : null,
            schedule_date: scheduleType === 'custom_date' ? document.getElementById('geofence-create-date').value : null,
            window_start: document.getElementById('geofence-create-window-start').value || null,
            window_end: document.getElementById('geofence-create-window-end').value || null,
            alert_on_exit: document.getElementById('geofence-create-alert-exit').checked,
            alert_on_missed: document.getElementById('geofence-create-alert-missed').checked,
        });
    }

    async _loadGeofences() {
        const params = {};

        if (this.categoryFilterEl?.value) params.category = this.categoryFilterEl.value;
        if (this.statusFilterEl?.value) params.status = this.statusFilterEl.value;

        const { data } = await window.axios.get('/api/v1/geofences', { params });

        this.geofences.clear();
        this.drawnItems.clearLayers();
        this.layers.clear();

        data.data.forEach((geofence) => {
            this.geofences.set(geofence.uuid, geofence);
            this._renderShape(geofence);
        });

        this._renderList();

        if (this.drawnItems.getLayers().length) {
            this.map.fitBounds(this.drawnItems.getBounds(), { maxZoom: 15, padding: [40, 40] });
        }
    }

    _renderShape(geofence) {
        let layer;

        if (geofence.type === 'circle') {
            layer = L.circle([geofence.center_lat, geofence.center_lng], {
                radius: geofence.radius_meters,
                color: geofence.color,
                fillOpacity: 0.15,
            });
        } else {
            const latlngs = geofence.points.map((point) => [point.latitude, point.longitude]);

            if (latlngs.length < 3) return;

            layer = L.polygon(latlngs, { color: geofence.color, fillOpacity: 0.15 });
        }

        layer.bindTooltip(escapeHtml(geofence.name), { sticky: true });
        layer.on('click', () => this._selectGeofence(geofence.uuid));

        this.drawnItems.addLayer(layer);
        this.layers.set(geofence.uuid, layer);
    }

    _renderList() {
        const term = (this.searchEl?.value ?? '').toLowerCase();
        const rows = Array.from(this.geofences.values()).filter((g) => !term || g.name.toLowerCase().includes(term));

        this.listEmptyEl?.classList.toggle('d-none', rows.length > 0);
        this.listEl.replaceChildren();

        rows.forEach((geofence) => {
            const row = document.createElement('div');
            row.className = `tracker-geofence-row ${geofence.uuid === this.selectedUuid ? 'active' : ''}`;
            row.innerHTML = `
                <div class="tracker-geofence-row-swatch" style="background:${escapeHtml(geofence.color)}"></div>
                <div class="tracker-geofence-row-body">
                    <strong>${escapeHtml(geofence.name)}</strong>
                    <div class="text-muted small">${escapeHtml(geofence.category_label)} • ${escapeHtml(geofence.type)}</div>
                </div>
                <span class="tracker-status-pill ${geofence.status === 'active' ? 'tracker-status-active' : ''}">${escapeHtml(geofence.status)}</span>
                <div class="dropdown">
                    <button class="btn btn-sm tracker-chat-action-btn" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" data-action="edit-details">Edit details</button></li>
                        <li><button class="dropdown-item" type="button" data-action="edit-shape">Edit shape</button></li>
                        <li><button class="dropdown-item" type="button" data-action="assign">Assign to user</button></li>
                        <li><button class="dropdown-item" type="button" data-action="duplicate">Duplicate</button></li>
                        <li><button class="dropdown-item" type="button" data-action="toggle-status">${geofence.status === 'active' ? 'Deactivate' : 'Activate'}</button></li>
                        <li><button class="dropdown-item" type="button" data-action="archive-toggle">${geofence.status === 'archived' ? 'Restore' : 'Archive'}</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" type="button" data-action="delete">Delete</button></li>
                    </ul>
                </div>
            `;

            row.addEventListener('click', (event) => {
                if (event.target.closest('.dropdown')) return;
                this._selectGeofence(geofence.uuid);
            });

            row.querySelector('[data-action="edit-details"]').addEventListener('click', () => this._openDetailsModal(geofence));
            row.querySelector('[data-action="edit-shape"]').addEventListener('click', () => this._startShapeEdit(geofence));
            row.querySelector('[data-action="assign"]').addEventListener('click', () => this._openAssignmentModal(geofence));
            row.querySelector('[data-action="duplicate"]').addEventListener('click', () => this._duplicateGeofence(geofence));
            row.querySelector('[data-action="toggle-status"]').addEventListener('click', () => this._toggleActiveStatus(geofence));
            row.querySelector('[data-action="archive-toggle"]').addEventListener('click', () => this._toggleArchiveStatus(geofence));
            row.querySelector('[data-action="delete"]').addEventListener('click', () => this._deleteGeofence(geofence));

            this.listEl.appendChild(row);
        });
    }

    _selectGeofence(uuid) {
        this.selectedUuid = uuid;
        this._renderList();

        const layer = this.layers.get(uuid);

        if (layer) {
            this.map.fitBounds(layer.getBounds(), { maxZoom: 16 });
        }
    }

    async _toggleActiveStatus(geofence) {
        const action = geofence.status === 'active' ? 'deactivate' : 'activate';
        await window.axios.post(`/api/v1/geofences/${geofence.uuid}/${action}`);
        await this._loadGeofences();
    }

    async _toggleArchiveStatus(geofence) {
        const action = geofence.status === 'archived' ? 'restore' : 'archive';
        await window.axios.post(`/api/v1/geofences/${geofence.uuid}/${action}`);
        await this._loadGeofences();
    }

    async _duplicateGeofence(geofence) {
        await window.axios.post(`/api/v1/geofences/${geofence.uuid}/duplicate`);
        await this._loadGeofences();
    }

    async _deleteGeofence(geofence) {
        if (!confirm(`Delete "${geofence.name}"? This cannot be undone from here.`)) return;

        await window.axios.delete(`/api/v1/geofences/${geofence.uuid}`);
        await this._loadGeofences();
    }

    _startShapeEdit(geofence) {
        const layer = this.layers.get(geofence.uuid);
        if (!layer) return;

        this.editingUuid = geofence.uuid;
        this.editingLayer = layer;
        this.editBarEl?.classList.remove('d-none');

        this.editToolbar = new L.EditToolbar.Edit(this.map, { featureGroup: L.featureGroup([layer]) });
        this.editToolbar.enable();
    }

    _cancelShapeEdit() {
        this.editToolbar?.disable();
        this.editBarEl?.classList.add('d-none');
        this.editingUuid = null;
        this.editingLayer = null;
        this._loadGeofences();
    }

    async _saveShapeEdit() {
        if (!this.editingUuid || !this.editingLayer) return;

        const geofence = this.geofences.get(this.editingUuid);
        const geometry = this._layerToGeometry(geofence.type, this.editingLayer);

        this.editToolbar?.disable();
        this.editBarEl?.classList.add('d-none');

        await window.axios.patch(`/api/v1/geofences/${this.editingUuid}`, {
            name: geofence.name,
            description: geofence.description,
            category: geofence.category,
            color: geofence.color,
            type: geofence.type,
            ...geometry,
        });

        this.editingUuid = null;
        this.editingLayer = null;
        await this._loadGeofences();
    }

    async _exportGeofences() {
        const response = await window.axios.get('/api/v1/geofences/export');
        const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.download = 'geofences-export.json';
        link.click();

        URL.revokeObjectURL(url);
    }

    async _importGeofences(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        try {
            const text = await file.text();
            const parsed = JSON.parse(text);
            const geofences = parsed.geofences ?? parsed.data ?? [];

            if (!Array.isArray(geofences) || !geofences.length) {
                alert('This file does not contain any geofences to import.');
                return;
            }

            await window.axios.post('/api/v1/geofences/import', { geofences });
            await this._loadGeofences();
        } catch (error) {
            alert('Could not import this file. Please make sure it is a valid geofence export.');
        }
    }

    // ---------- Assignments (schedule + compliance) ----------

    async _loadUsers() {
        try {
            const { data } = await window.axios.get('/api/v1/geofences/trackable-users');
            this.users = data.data ?? [];

            const select = document.getElementById('geofence-assignment-user');
            select?.replaceChildren();
            this.users.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                select?.appendChild(option);
            });

            const createSelect = document.getElementById('geofence-create-user');
            createSelect?.replaceChildren();
            this.users.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                createSelect?.appendChild(option);
            });
        } catch (error) {
            this.users = [];
        }
    }

    async _openAssignmentModal(geofence) {
        this.assignmentGeofence = geofence;
        document.getElementById('geofence-assignment-title').textContent = `Assignments — ${geofence.name}`;
        document.getElementById('geofence-assignment-route-label').value = '';
        document.getElementById('geofence-assignment-window-start').value = '';
        document.getElementById('geofence-assignment-window-end').value = '';
        document.getElementById('geofence-assignment-schedule-type').value = 'daily';
        document.getElementById('geofence-assignment-alert-exit').checked = true;
        document.getElementById('geofence-assignment-alert-missed').checked = true;

        const statusEl = document.getElementById('geofence-assignment-status');
        statusEl.classList.add('d-none');
        statusEl.textContent = '';

        this._syncAssignmentScheduleFields();
        this.assignmentModal.show();
        await this._loadAssignmentsForGeofence(geofence);
    }

    _syncAssignmentScheduleFields() {
        const type = document.getElementById('geofence-assignment-schedule-type').value;
        const daysWrap = document.getElementById('geofence-assignment-days-wrap');
        const dateWrap = document.getElementById('geofence-assignment-date-wrap');
        const daysSelect = document.getElementById('geofence-assignment-days');
        const daysLabel = document.getElementById('geofence-assignment-days-label');

        daysWrap.classList.toggle('d-none', !['weekly', 'monthly', 'quarterly', 'yearly'].includes(type));
        dateWrap.classList.toggle('d-none', type !== 'custom_date');

        if (type === 'weekly') {
            daysLabel.textContent = 'Days of week';
            daysSelect.replaceChildren();
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach((label, index) => {
                const option = document.createElement('option');
                option.value = index + 1;
                option.textContent = label;
                daysSelect.appendChild(option);
            });
        } else if (type === 'monthly') {
            daysLabel.textContent = 'Days of month';
            daysSelect.replaceChildren();
            for (let day = 1; day <= 31; day++) {
                const option = document.createElement('option');
                option.value = day;
                option.textContent = day;
                daysSelect.appendChild(option);
            }
        } else if (type === 'quarterly') {
            daysLabel.textContent = 'Day in quarter-start month';
            daysSelect.replaceChildren();
            for (let day = 1; day <= 31; day++) {
                const option = document.createElement('option');
                option.value = day;
                option.textContent = day;
                daysSelect.appendChild(option);
            }
        } else if (type === 'yearly') {
            daysLabel.textContent = 'Yearly dates';
            daysSelect.replaceChildren();
            ['01-01', '04-01', '07-01', '10-01', '12-31'].forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                daysSelect.appendChild(option);
            });
        }
    }

    _syncCreateScheduleFields() {
        const type = document.getElementById('geofence-create-schedule-type').value;
        const daysWrap = document.getElementById('geofence-create-days-wrap');
        const dateWrap = document.getElementById('geofence-create-date-wrap');
        const daysSelect = document.getElementById('geofence-create-days');
        const daysLabel = document.getElementById('geofence-create-days-label');

        daysWrap.classList.toggle('d-none', !['weekly', 'monthly', 'quarterly', 'yearly'].includes(type));
        dateWrap.classList.toggle('d-none', type !== 'custom_date');
        daysSelect.replaceChildren();

        if (type === 'weekly') {
            daysLabel.textContent = 'Days of week';
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach((label, index) => {
                daysSelect.appendChild(new Option(label, index + 1));
            });
        } else if (['monthly', 'quarterly'].includes(type)) {
            daysLabel.textContent = type === 'monthly' ? 'Days of month' : 'Day in quarter-start month';
            for (let day = 1; day <= 31; day++) {
                daysSelect.appendChild(new Option(day, day));
            }
        } else if (type === 'yearly') {
            daysLabel.textContent = 'Yearly dates';
            ['01-01', '04-01', '07-01', '10-01', '12-31'].forEach((value) => daysSelect.appendChild(new Option(value, value)));
        }
    }

    _resetCreateAssignmentFields() {
        document.getElementById('geofence-create-route-label').value = '';
        document.getElementById('geofence-create-sequence').value = '';
        document.getElementById('geofence-create-schedule-type').value = 'daily';
        document.getElementById('geofence-create-date').value = '';
        document.getElementById('geofence-create-window-start').value = '';
        document.getElementById('geofence-create-window-end').value = '';
        document.getElementById('geofence-create-alert-exit').checked = true;
        document.getElementById('geofence-create-alert-missed').checked = true;
        this._syncCreateScheduleFields();
    }

    async _saveAssignment() {
        const geofence = this.assignmentGeofence;
        const statusEl = document.getElementById('geofence-assignment-status');
        if (!geofence) return;

        const scheduleType = document.getElementById('geofence-assignment-schedule-type').value;
        const daysSelect = document.getElementById('geofence-assignment-days');
        const selectedDays = Array.from(daysSelect.selectedOptions ?? []).map((option) => scheduleType === 'yearly' ? option.value : Number(option.value));

        const payload = {
            geofence_uuid: geofence.uuid,
            user_id: Number(document.getElementById('geofence-assignment-user').value),
            route_label: document.getElementById('geofence-assignment-route-label').value.trim() || null,
            schedule_type: scheduleType,
            schedule_days: ['weekly', 'monthly', 'quarterly', 'yearly'].includes(scheduleType) ? selectedDays : null,
            schedule_date: scheduleType === 'custom_date' ? document.getElementById('geofence-assignment-date').value : null,
            window_start: document.getElementById('geofence-assignment-window-start').value || null,
            window_end: document.getElementById('geofence-assignment-window-end').value || null,
            alert_on_exit: document.getElementById('geofence-assignment-alert-exit').checked,
            alert_on_missed: document.getElementById('geofence-assignment-alert-missed').checked,
        };

        if (!payload.user_id) {
            statusEl.textContent = 'Please choose a user.';
            statusEl.classList.remove('d-none');
            return;
        }

        try {
            await window.axios.post('/api/v1/geofence-assignments', payload);
            statusEl.classList.add('d-none');
            document.getElementById('geofence-assignment-route-label').value = '';
            await this._loadAssignmentsForGeofence(geofence);
        } catch (error) {
            statusEl.textContent = error.response?.data?.message ?? 'Could not save the assignment.';
            statusEl.classList.remove('d-none');
        }
    }

    async _loadAssignmentsForGeofence(geofence) {
        const { data } = await window.axios.get('/api/v1/geofence-assignments', { params: { geofence_uuid: geofence.uuid } });
        this._renderAssignmentList(data.data ?? []);
    }

    _renderAssignmentList(assignments) {
        const listEl = document.getElementById('geofence-assignment-list');
        const emptyEl = document.getElementById('geofence-assignment-empty');

        emptyEl.classList.toggle('d-none', assignments.length > 0);
        listEl.replaceChildren();

        assignments.forEach((assignment) => {
            const row = document.createElement('div');
            row.className = 'tracker-geofence-row';
            row.innerHTML = `
                <div class="tracker-geofence-row-body">
                    <strong>${escapeHtml(assignment.user.name)}</strong>
                    <div class="text-muted small">${escapeHtml(this._scheduleSummary(assignment))}${assignment.route_label ? ` • ${escapeHtml(assignment.route_label)}` : ''}</div>
                </div>
                <span class="tracker-status-pill ${assignment.status === 'active' ? 'tracker-status-active' : ''}">${escapeHtml(assignment.status)}</span>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm tracker-outline-btn" data-action="history" title="Compliance history"><i class="fa-solid fa-clock-rotate-left"></i></button>
                    <button type="button" class="btn btn-sm tracker-outline-btn" data-action="toggle" title="${assignment.status === 'active' ? 'Deactivate' : 'Activate'}"><i class="fa-solid fa-power-off"></i></button>
                    <button type="button" class="btn btn-sm tracker-outline-btn text-danger" data-action="remove" title="Remove"><i class="fa-solid fa-trash"></i></button>
                </div>
            `;

            row.querySelector('[data-action="history"]').addEventListener('click', () => this._openRunsModal(assignment));
            row.querySelector('[data-action="toggle"]').addEventListener('click', () => this._toggleAssignmentStatus(assignment));
            row.querySelector('[data-action="remove"]').addEventListener('click', () => this._deleteAssignment(assignment));

            listEl.appendChild(row);
        });
    }

    _scheduleSummary(assignment) {
        const windowText = assignment.window_start && assignment.window_end
            ? ` (${assignment.window_start.slice(0, 5)}–${assignment.window_end.slice(0, 5)})`
            : '';

        switch (assignment.schedule_type) {
            case 'weekly': {
                const names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                return `Weekly: ${(assignment.schedule_days ?? []).map((d) => names[d] ?? d).join(', ')}${windowText}`;
            }
            case 'monthly':
                return `Monthly: day ${(assignment.schedule_days ?? []).join(', ')}${windowText}`;
            case 'quarterly':
                return `Quarterly: day ${(assignment.schedule_days ?? []).join(', ')}${windowText}`;
            case 'yearly':
                return `Yearly: ${(assignment.schedule_days ?? []).join(', ')}${windowText}`;
            case 'custom_date':
                return `On ${assignment.schedule_date}${windowText}`;
            default:
                return `Daily${windowText}`;
        }
    }

    async _toggleAssignmentStatus(assignment) {
        await window.axios.patch(`/api/v1/geofence-assignments/${assignment.uuid}`, {
            route_label: assignment.route_label,
            schedule_type: assignment.schedule_type,
            schedule_days: assignment.schedule_days,
            schedule_date: assignment.schedule_date,
            window_start: assignment.window_start,
            window_end: assignment.window_end,
            alert_on_exit: assignment.alert_on_exit,
            alert_on_missed: assignment.alert_on_missed,
            status: assignment.status === 'active' ? 'inactive' : 'active',
        });

        await this._loadAssignmentsForGeofence(this.assignmentGeofence);
    }

    async _deleteAssignment(assignment) {
        if (!confirm(`Remove this assignment for ${assignment.user.name}?`)) return;

        await window.axios.delete(`/api/v1/geofence-assignments/${assignment.uuid}`);
        await this._loadAssignmentsForGeofence(this.assignmentGeofence);
    }

    async _openRunsModal(assignment) {
        const { data } = await window.axios.get(`/api/v1/geofence-assignments/${assignment.uuid}/runs`);
        const runs = data.data ?? [];

        const listEl = document.getElementById('geofence-runs-list');
        const emptyEl = document.getElementById('geofence-runs-empty');

        emptyEl.classList.toggle('d-none', runs.length > 0);
        listEl.replaceChildren();

        runs.forEach((run) => {
            const row = document.createElement('div');
            row.className = 'tracker-geofence-row';
            const statusClass = run.status === 'visited' ? 'tracker-status-active' : (run.status === 'missed' ? 'text-danger' : '');
            row.innerHTML = `
                <div class="tracker-geofence-row-body">
                    <strong>${escapeHtml(run.run_date)}</strong>
                    <div class="text-muted small">${run.visited_at ? `Visited at ${escapeHtml(new Date(run.visited_at).toLocaleTimeString())}` : (run.status === 'missed' ? 'Not visited — different route or no visit' : 'Pending')}</div>
                </div>
                <span class="tracker-status-pill ${statusClass}">${escapeHtml(run.status)}</span>
            `;
            listEl.appendChild(row);
        });

        this.runsModal.show();
    }
}

document.addEventListener('DOMContentLoaded', () => new GeofenceManager());
