import './bootstrap';
import { Modal } from 'bootstrap';
import { mountReportCharts } from './report-charts';

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('report-modal');
    if (!modalEl || document.getElementById('report-user') === null) return;

    const modal = new Modal(modalEl);
    let refreshTimer = null;
    let reportCharts = null;

    document.getElementById('report-preset').addEventListener('change', (event) => {
        document.getElementById('report-custom-range').classList.toggle('d-none', event.target.value !== 'custom');
    });

    function currentParams() {
        const params = {
            user_id: document.getElementById('report-user').value,
            type: document.getElementById('report-type').value,
            preset: document.getElementById('report-preset').value,
        };

        if (params.preset === 'custom') {
            params.from = document.getElementById('report-from').value;
            params.to = document.getElementById('report-to').value;
        }

        const minSpeed = document.getElementById('report-min-speed').value;
        const maxSpeed = document.getElementById('report-max-speed').value;
        if (minSpeed !== '') params.min_speed = minSpeed;
        if (maxSpeed !== '') params.max_speed = maxSpeed;

        return params;
    }

    function renderCards(analytics, attendance, timeline, heatmap, report) {
        const internetOutages = report.device_diagnostics?.internet_outages ?? [];
        const gpsOutages = report.device_diagnostics?.gps_outages ?? [];
        const internetDownMinutes = Math.round(internetOutages.reduce((sum, o) => sum + o.duration_seconds, 0) / 60);

        const cards = [
            ['Distance', `${analytics.total_distance_km ?? 0} km`],
            ['Moving', `${Math.round((analytics.moving_seconds ?? 0) / 60)} min`],
            ['Idle', `${Math.round((analytics.idle_seconds ?? 0) / 60)} min`],
            ['Attendance', attendance.status ?? '—'],
            ['Work Hours', `${attendance.working_hours ?? 0} h`],
            ['Timeline', `${timeline.length} events`],
            ['Heat Zones', `${heatmap.summary?.clusters ?? 0}`],
            ['Max Speed', `${analytics.maximum_speed_mps ?? '—'} m/s`],
            ['Internet Outages', `${internetOutages.length} (${internetDownMinutes} min down)`],
            ['GPS Outages', `${gpsOutages.length}`],
            ['Geofence Events', `${report.geofence_activity?.length ?? 0}`],
        ];

        document.getElementById('report-cards').innerHTML = cards.map(([label, value]) => `
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

    function renderDiagnostics(deviceDiagnostics) {
        const rows = deviceDiagnostics?.events ?? [];
        const tbody = document.getElementById('report-diagnostics');

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

    function renderGeofenceActivity(activity) {
        const tbody = document.getElementById('report-geofence');

        tbody.innerHTML = (activity ?? []).length
            ? activity.map((event) => `
                <tr>
                    <td>${event.geofence_name ?? '—'}</td>
                    <td>${event.type}</td>
                    <td>${new Date(event.occurred_at).toLocaleString()}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="3" class="text-muted">No geofence activity in this range.</td></tr>';
    }

    function renderMenu(tables) {
        document.getElementById('report-menu').innerHTML = (tables ?? []).map((table) => `
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

    async function loadReport() {
        const params = currentParams();
        const output = document.getElementById('report-output');
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

        renderCards(analytics.data, attendance.data, timeline.data, heatmap.data, report.data);
        renderMenu(report.data.phase4_tables);
        renderDiagnostics(report.data.device_diagnostics);
        renderGeofenceActivity(report.data.geofence_activity);

        output.textContent = JSON.stringify({
            report: report.data,
            analytics: analytics.data,
            attendance: attendance.data,
            timeline: timeline.data.slice(0, 25),
            heatmap: heatmap.data.summary,
        }, null, 2);

        const personName = document.getElementById('report-user').selectedOptions[0]?.textContent ?? 'Employee';
        document.getElementById('report-modal-title').textContent = `${personName} — ${document.getElementById('report-type').selectedOptions[0]?.textContent}`;
        document.getElementById('report-export-csv').href = `/api/v1/exports?format=csv&${new URLSearchParams(params).toString()}`;

        const chartPayload = { report: report.data, analytics: analytics.data, attendance: attendance.data, timeline: timeline.data, heatmap: heatmap.data };

        if (!reportCharts) {
            const root = document.getElementById('report-chart-root');
            const contentBlock = document.getElementById('report-content-block');

            if (contentBlock) contentBlock.classList.remove('d-none');

            reportCharts = mountReportCharts('report', root, contentBlock, chartPayload);
        } else {
            reportCharts.render(chartPayload);
        }
    }

    document.getElementById('report-generate').addEventListener('click', () => {
        loadReport();
        modal.show();

        clearInterval(refreshTimer);
        refreshTimer = setInterval(loadReport, 30000);
    });

    modalEl.addEventListener('hidden.bs.modal', () => clearInterval(refreshTimer));
});
