// Shared chart dashboard for the Phase 4 report modals (Live Map per-user report
// and the standalone Reports & Export Center page). Both pages fetch the same
// /api/v1/{reports,analytics,attendance,timeline,heatmap} shape, so the tab
// markup + Chart.js wiring lives here once and each page just calls
// mountReportCharts(container, payload).

// Palette: fixed categorical order (never cycled/re-ordered per series) plus the
// status pair, taken from the validated default in the dataviz skill reference.
const PALETTE = {
    light: {
        series: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'],
        good: '#0ca30c',
        critical: '#d03b3b',
        ink: '#0b0b0b',
        secondaryInk: '#52514e',
        muted: '#898781',
        grid: '#e1e0d9',
        axis: '#c3c2b7',
        surface: '#fcfcfb',
    },
    dark: {
        series: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'],
        good: '#0ca30c',
        critical: '#e66767',
        ink: '#ffffff',
        secondaryInk: '#c3c2b7',
        muted: '#898781',
        grid: '#2c2c2a',
        axis: '#383835',
        surface: '#1a1a19',
    },
};

function isDarkMode() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

function palette() {
    return isDarkMode() ? PALETTE.dark : PALETTE.light;
}

function baseScaleOptions(p) {
    return {
        ticks: { color: p.muted, font: { size: 11 } },
        grid: { color: p.grid },
        border: { color: p.axis },
    };
}

function baseChartOptions(p, extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 900, easing: 'easeOutQuart' },
        plugins: {
            legend: { labels: { color: p.secondaryInk, usePointStyle: true, boxWidth: 8 } },
            tooltip: {
                backgroundColor: p.surface,
                titleColor: p.ink,
                bodyColor: p.secondaryInk,
                borderColor: p.grid,
                borderWidth: 1,
                padding: 10,
                cornerRadius: 8,
            },
        },
        ...extra,
    };
}

/**
 * Fake-3D drop-shadow plugin for the pie/doughnut — a real WebGL 3D pie would
 * need a new charting engine; this gets the tilted/lit look from CSS + a canvas
 * shadow instead, on top of the Chart.js library already in this app.
 */
const shadowPlugin = {
    id: 'softShadow',
    beforeDatasetsDraw(chart) {
        const { ctx } = chart;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.35)';
        ctx.shadowBlur = 18;
        ctx.shadowOffsetY = 10;
    },
    afterDatasetsDraw(chart) {
        chart.ctx.restore();
    },
};

function minutes(seconds) {
    return Math.round((seconds ?? 0) / 60);
}

function clampPercent(value, max) {
    if (!Number.isFinite(value) || max <= 0) return 0;

    return Math.max(0, Math.min(100, (value / max) * 100));
}

/**
 * Buckets speed samples into hourly OHLC candles (open = first reading in the
 * hour, close = last, high/low = extremes) — a legitimate reading of real speed
 * telemetry, not a financial OHLC series.
 */
function hourlyCandles(historySample) {
    const buckets = new Map();

    (historySample ?? [])
        .filter((point) => point.speed !== null && point.speed !== undefined)
        .sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at))
        .forEach((point) => {
            const t = new Date(point.recorded_at);
            const key = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')} ${String(t.getHours()).padStart(2, '0')}:00`;

            if (!buckets.has(key)) buckets.set(key, []);
            buckets.get(key).push(point.speed * 3.6); // m/s -> km/h, easier to read
        });

    return Array.from(buckets.entries()).map(([label, speeds]) => ({
        label,
        open: speeds[0],
        close: speeds[speeds.length - 1],
        high: Math.max(...speeds),
        low: Math.min(...speeds),
    }));
}

const TAB_TEMPLATE = (idPrefix) => `
    <ul class="nav nav-tabs tracker-nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#${idPrefix}-pane-pie" type="button">Pie</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#${idPrefix}-pane-radar" type="button">Radar</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#${idPrefix}-pane-bar" type="button">Bar</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#${idPrefix}-pane-candle" type="button">Candlestick</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#${idPrefix}-pane-wave" type="button">Wave</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#${idPrefix}-pane-content" type="button">Content</button></li>
    </ul>
    <div class="tab-content tracker-chart-tab-content">
        <div class="tab-pane fade show active" id="${idPrefix}-pane-pie">
            <div class="tracker-chart-3d"><canvas id="${idPrefix}-chart-pie"></canvas></div>
        </div>
        <div class="tab-pane fade" id="${idPrefix}-pane-radar">
            <div class="tracker-chart-box"><canvas id="${idPrefix}-chart-radar"></canvas></div>
        </div>
        <div class="tab-pane fade" id="${idPrefix}-pane-bar">
            <div class="tracker-chart-box"><canvas id="${idPrefix}-chart-bar"></canvas></div>
        </div>
        <div class="tab-pane fade" id="${idPrefix}-pane-candle">
            <div class="tracker-chart-box"><canvas id="${idPrefix}-chart-candle"></canvas></div>
        </div>
        <div class="tab-pane fade" id="${idPrefix}-pane-wave">
            <div class="tracker-chart-box"><canvas id="${idPrefix}-chart-wave"></canvas></div>
        </div>
        <div class="tab-pane fade" id="${idPrefix}-pane-content"></div>
    </div>
`;

export class ReportCharts {
    constructor(idPrefix) {
        this.idPrefix = idPrefix;
        this.charts = {};
    }

    /**
     * Builds the tab markup once. `contentEl` (already-rendered cards/tables/JSON
     * from the page's own report renderer) is moved under the "Content" tab so
     * nothing existing is lost — charts are additive.
     */
    mount(container, contentEl) {
        container.innerHTML = TAB_TEMPLATE(this.idPrefix);

        const contentPane = document.getElementById(`${this.idPrefix}-pane-content`);

        if (contentEl && contentPane) {
            contentPane.appendChild(contentEl);
        }

        container.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => this._resizeVisible());
        });
    }

    _resizeVisible() {
        Object.values(this.charts).forEach((chart) => chart.resize());
    }

    _canvas(name) {
        return document.getElementById(`${this.idPrefix}-chart-${name}`);
    }

    _destroy(name) {
        this.charts[name]?.destroy();
    }

    render({ analytics, attendance, timeline, heatmap, report }) {
        const p = palette();

        this._renderPie(p, analytics);
        this._renderRadar(p, analytics, attendance, report);
        this._renderBar(p, report);
        this._renderCandle(p, report);
        this._renderWave(p, report, timeline);
    }

    _renderPie(p, analytics) {
        this._destroy('pie');
        const canvas = this._canvas('pie');
        if (!canvas) return;

        const data = [minutes(analytics.moving_seconds), minutes(analytics.idle_seconds), minutes(analytics.stopped_time_seconds)];
        const hasData = data.some((v) => v > 0);

        this.charts.pie = new window.Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['Moving', 'Idle', 'Stopped'],
                datasets: [{
                    data: hasData ? data : [1, 1, 1],
                    backgroundColor: [p.series[0], p.series[1], p.series[2]],
                    borderColor: p.surface,
                    borderWidth: 2,
                    hoverOffset: 10,
                }],
            },
            options: baseChartOptions(p, {
                animation: { animateRotate: true, animateScale: true, duration: 1100, easing: 'easeOutQuart' },
                cutout: '55%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: p.secondaryInk, usePointStyle: true, boxWidth: 8 } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => hasData ? `${ctx.label}: ${ctx.parsed} min` : `${ctx.label}: no activity yet`,
                        },
                    },
                },
            }),
            plugins: [shadowPlugin],
        });
    }

    _renderRadar(p, analytics, attendance, report) {
        this._destroy('radar');
        const canvas = this._canvas('radar');
        if (!canvas) return;

        const values = [
            clampPercent(analytics.total_distance_km ?? 0, 50),
            clampPercent((analytics.average_speed_mps ?? 0) * 3.6, 80),
            clampPercent((analytics.maximum_speed_mps ?? 0) * 3.6, 120),
            clampPercent(analytics.average_accuracy ? Math.max(0, 100 - analytics.average_accuracy) : 0, 100),
            clampPercent(attendance.working_hours ?? 0, 10),
            clampPercent((report.geofence_activity ?? []).length, 10),
        ];

        this.charts.radar = new window.Chart(canvas, {
            type: 'radar',
            data: {
                labels: ['Distance', 'Avg Speed', 'Max Speed', 'Accuracy', 'Work Hours', 'Geofence Visits'],
                datasets: [{
                    label: 'Today',
                    data: values,
                    backgroundColor: `${p.series[0]}33`,
                    borderColor: p.series[0],
                    pointBackgroundColor: p.series[0],
                    borderWidth: 2,
                }],
            },
            options: baseChartOptions(p, {
                plugins: { legend: { display: false } },
                scales: {
                    r: {
                        min: 0,
                        max: 100,
                        ticks: { display: false },
                        grid: { color: p.grid },
                        angleLines: { color: p.grid },
                        pointLabels: { color: p.secondaryInk, font: { size: 11 } },
                    },
                },
            }),
        });
    }

    _renderBar(p, report) {
        this._destroy('bar');
        const canvas = this._canvas('bar');
        if (!canvas) return;

        const counts = {};
        (report.device_diagnostics?.events ?? []).forEach((event) => {
            counts[event.label] = (counts[event.label] ?? 0) + 1;
        });

        const labels = Object.keys(counts);
        const values = Object.values(counts);

        this.charts.bar = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['No diagnostic events'],
                datasets: [{
                    label: 'Events',
                    data: labels.length ? values : [0],
                    backgroundColor: p.series[0],
                    borderRadius: 4,
                    maxBarThickness: 36,
                }],
            },
            options: baseChartOptions(p, {
                plugins: { legend: { display: false } },
                scales: {
                    x: { ...baseScaleOptions(p), ticks: { ...baseScaleOptions(p).ticks, autoSkip: false, maxRotation: 30 } },
                    y: { ...baseScaleOptions(p), beginAtZero: true, ticks: { ...baseScaleOptions(p).ticks, precision: 0 } },
                },
            }),
        });
    }

    _renderCandle(p, report) {
        this._destroy('candle');
        const canvas = this._canvas('candle');
        if (!canvas) return;

        const candles = hourlyCandles(report.history_sample);
        const hasData = candles.length > 0;
        const rows = hasData ? candles : [{ label: 'No speed samples', open: 0, close: 0, high: 0, low: 0 }];

        this.charts.candle = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((c) => c.label),
                datasets: [{
                    label: 'Speed range (km/h)',
                    data: rows.map((c) => [c.low, c.high]),
                    backgroundColor: rows.map((c) => (c.close >= c.open ? p.good : p.critical)),
                    borderRadius: 3,
                    maxBarThickness: 22,
                }],
            },
            options: baseChartOptions(p, {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...baseChartOptions(p).plugins.tooltip,
                        callbacks: {
                            label: (ctx) => {
                                const c = rows[ctx.dataIndex];
                                return hasData
                                    ? [`Open ${c.open.toFixed(1)} · Close ${c.close.toFixed(1)}`, `High ${c.high.toFixed(1)} · Low ${c.low.toFixed(1)} km/h`]
                                    : 'No speed samples in this range';
                            },
                        },
                    },
                },
                scales: {
                    x: { ...baseScaleOptions(p), ticks: { ...baseScaleOptions(p).ticks, maxRotation: 30 } },
                    y: { ...baseScaleOptions(p), beginAtZero: true, title: { display: true, text: 'km/h', color: p.muted } },
                },
            }),
        });
    }

    _renderWave(p, report, timeline) {
        this._destroy('wave');
        const canvas = this._canvas('wave');
        if (!canvas) return;

        const speedPoints = (report.history_sample ?? [])
            .filter((point) => point.speed !== null && point.speed !== undefined)
            .sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at));

        let labels; let values; let seriesLabel; let unit;

        if (speedPoints.length) {
            labels = speedPoints.map((point) => new Date(point.recorded_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            values = speedPoints.map((point) => point.speed * 3.6);
            seriesLabel = 'Speed';
            unit = 'km/h';
        } else {
            // Fall back to battery level over time when there's no speed telemetry yet
            // (e.g. someone stationary all day) — still a real, live-updating series.
            const withBattery = (timeline ?? []).filter((event) => event.battery_level !== null && event.battery_level !== undefined);
            labels = withBattery.map((event) => new Date(event.occurred_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            values = withBattery.map((event) => event.battery_level);
            seriesLabel = 'Battery';
            unit = '%';
        }

        const hasData = values.length > 0;

        this.charts.wave = new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: hasData ? labels : ['—'],
                datasets: [{
                    label: `${seriesLabel} (${unit})`,
                    data: hasData ? values : [0],
                    borderColor: p.series[0],
                    backgroundColor: `${p.series[0]}22`,
                    fill: true,
                    tension: 0.4,
                    pointRadius: hasData ? 3 : 0,
                    pointHoverRadius: 6,
                    borderWidth: 2,
                }],
            },
            options: baseChartOptions(p, {
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    x: baseScaleOptions(p),
                    y: { ...baseScaleOptions(p), title: { display: true, text: unit, color: p.muted } },
                },
            }),
        });
    }
}

export function mountReportCharts(idPrefix, container, contentEl, payload) {
    const charts = new ReportCharts(idPrefix);
    charts.mount(container, contentEl);
    charts.render(payload);

    return charts;
}
