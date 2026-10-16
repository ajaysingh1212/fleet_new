import './bootstrap';

const OFFLINE_QUEUE_KEY = 'tracker.gps.offline_queue';
const HEARTBEAT_SECONDS = 100;
const FLUSH_RETRY_MS = 30000;

/**
 * Adaptive distance table from the Phase 2 spec: standing gets no
 * distance-triggered updates (heartbeat only), and required movement grows
 * with speed so a car/highway trip doesn't spam a point every few meters.
 */
function adaptiveThresholdForSpeed(speedMps) {
    if (speedMps === null || speedMps < 0.3) return null; // standing
    if (speedMps < 2) return 5; // walking
    if (speedMps < 8) return 10; // bike / running
    if (speedMps < 20) return 25; // car
    return 50; // highway
}

function haversineMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function writeQueue(queue) {
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
}

export class GpsWatcher {
    constructor({ onStatus } = {}) {
        this.onStatus = onStatus || (() => {});
        this.watchId = null;
        this.distanceFilterMeters = 25;
        this.lastSentAt = null;
        this.lastSentPosition = null;
        this.flushTimer = null;
        this.permissionStatus = null;
        this.batteryLevel = null;
        this.offlineSince = null;
        this.gpsDisabled = false;
    }

    async start() {
        if (!('geolocation' in navigator)) {
            this.onStatus({ state: 'unsupported', message: 'This browser does not support geolocation.' });
            return;
        }

        try {
            const { data } = await window.axios.get('/api/v1/me');
            this.distanceFilterMeters = data.distance_filter_meters ?? 25;
        } catch (e) {
            // Keep the default; the server-side optimizer is authoritative anyway.
        }

        this._watchBattery();
        this._watchPermission();
        this._checkAutomation();

        this.watchId = navigator.geolocation.watchPosition(
            (position) => this._handlePosition(position),
            (error) => this._handleError(error),
            { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
        );

        document.addEventListener('visibilitychange', this._onVisibilityChange);
        window.addEventListener('online', this._onOnline);
        window.addEventListener('offline', this._onOffline);
        window.addEventListener('beforeunload', this._onBeforeUnload);

        this.flushTimer = window.setInterval(() => this._flushQueue(), FLUSH_RETRY_MS);

        // Not "sharing" yet — that's only true once a position actually
        // arrives (see _handlePosition/_send). Saying "Sharing" here, before
        // the browser has even resolved a GPS fix, is misleading when the
        // fix is slow, denied, or never arrives.
        this.onStatus({ state: 'waiting' });
    }

    stop() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }

        document.removeEventListener('visibilitychange', this._onVisibilityChange);
        window.removeEventListener('online', this._onOnline);
        window.removeEventListener('offline', this._onOffline);
        window.removeEventListener('beforeunload', this._onBeforeUnload);

        if (this.flushTimer) {
            window.clearInterval(this.flushTimer);
            this.flushTimer = null;
        }

        this.onStatus({ state: 'stopped' });
    }

    _watchBattery() {
        if (!navigator.getBattery) return;

        navigator.getBattery().then((battery) => {
            const update = () => { this.batteryLevel = Math.round(battery.level * 100); };
            update();
            battery.addEventListener('levelchange', update);
        }).catch(() => {});
    }

    /**
     * `navigator.webdriver` is the one honest, standardized signal a browser
     * exposes about being remote-controlled (Selenium/Puppeteer/Playwright
     * etc.) — the closest browser-side equivalent to "developer mode", since
     * there is no web API that reads a device's OS-level Developer Options.
     */
    _checkAutomation() {
        if (navigator.webdriver) {
            this._reportDiagnostic('automation_detected', { reason: 'navigator.webdriver is true' });
        }
    }

    _watchPermission() {
        if (!navigator.permissions?.query) return;

        navigator.permissions.query({ name: 'geolocation' }).then((status) => {
            this.permissionStatus = status;
            this._reportDiagnostic(status.state === 'granted' ? 'permission_granted' : 'permission_revoked');

            status.onchange = () => {
                this._reportDiagnostic(status.state === 'granted' ? 'permission_granted' : 'permission_revoked');
            };
        }).catch(() => {});
    }

    _handlePosition(position) {
        if (this.gpsDisabled) {
            this.gpsDisabled = false;
            this._reportDiagnostic('gps_enabled');
        }

        const { latitude, longitude, speed, heading, accuracy, altitude } = position.coords;
        const now = Date.now();

        const secondsSinceLastSend = this.lastSentAt ? (now - this.lastSentAt) / 1000 : Infinity;

        // Send live fixes independently of the server's history-save radius.
        const shouldSend = secondsSinceLastSend >= 3;

        if (!shouldSend) {
            this.onStatus({ state: 'idle', accuracy, skipped: true });
            return;
        }

        if (accuracy !== null && accuracy !== undefined && accuracy > 100) {
            this._reportDiagnostic('poor_accuracy', { latitude, longitude });
        }

        const packet = {
            device_id: null,
            source_type: 'browser',
            latitude,
            longitude,
            accuracy,
            speed,
            bearing: heading,
            heading,
            altitude,
            battery_level: this.batteryLevel,
            network_type: navigator.connection?.effectiveType ?? null,
            is_mock: false,
            recorded_at: new Date(position.timestamp).toISOString(),
        };

        this.lastSentAt = now;
        this.lastSentPosition = { lat: latitude, lng: longitude };

        this._send(packet);
    }

    _send(packet) {
        if (!navigator.onLine) {
            this._enqueue(packet);
            this.onStatus({ state: 'queued-offline' });
            return;
        }

        window.axios.post('/api/v1/gps/locations', packet)
            .then((response) => {
                this.onStatus({ state: 'sent', accepted: response.data.accepted });
            })
            .catch(() => {
                this._enqueue(packet);
                this.onStatus({ state: 'queued-error' });
            });
    }

    _enqueue(packet) {
        const queue = readQueue();
        queue.push(packet);
        writeQueue(queue);
    }

    _flushQueue() {
        const queue = readQueue();

        if (queue.length === 0 || !navigator.onLine) {
            return;
        }

        window.axios.post('/api/v1/gps/locations/batch', { locations: queue })
            .then(() => {
                writeQueue([]);
                this.onStatus({ state: 'flushed', count: queue.length });
            })
            .catch(() => {
                // Leave the queue intact; try again on the next timer tick or online event.
            });
    }

    _handleError(error) {
        if (error.code === error.PERMISSION_DENIED) {
            this._reportDiagnostic('permission_revoked');
        } else {
            this.gpsDisabled = true;
            this._reportDiagnostic('gps_disabled', { reason: error.message });
        }

        this.onStatus({ state: 'error', message: error.message });
    }

    _reportDiagnostic(eventType, extra = {}) {
        window.axios.post('/api/v1/gps/diagnostics', {
            event_type: eventType,
            network_type: navigator.connection?.effectiveType ?? null,
            battery_level: this.batteryLevel,
            ...extra,
        }).catch(() => {});
    }

    _onVisibilityChange = () => {
        this._reportDiagnostic(document.hidden ? 'browser_hidden' : 'browser_visible');
    };

    // A POST can't reach the server while offline, so the "went offline" moment is
    // remembered client-side and only reported (with its real timestamp) once the
    // connection — and therefore the ability to send it — comes back.
    _onOnline = () => {
        if (this.offlineSince) {
            this._reportDiagnostic('internet_off', { occurred_at: this.offlineSince });
            this.offlineSince = null;
        }

        this._reportDiagnostic('internet_on');
        this._flushQueue();
    };

    _onOffline = () => {
        this.offlineSince = new Date().toISOString();
    };

    _onBeforeUnload = () => {
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify({ event_type: 'browser_closed' })], { type: 'application/json' });
            navigator.sendBeacon('/api/v1/gps/diagnostics', blob);
        }
    };
}

window.GpsWatcher = GpsWatcher;

const STATUS_LABELS = {
    waiting: 'Waiting for GPS fix…',
    idle: 'Sharing (no movement yet)',
    sent: 'Sharing',
    'queued-offline': 'Sharing (offline, queued)',
    'queued-error': 'Sharing (retrying)',
    flushed: 'Sharing',
    stopped: 'Stopped',
    error: 'Error — check location permission',
    unsupported: 'Not supported',
};

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('location-sharing-toggle');

    if (!toggle) return;

    const toggleLabel = document.getElementById('location-sharing-toggle-label');
    const statusEl = document.getElementById('location-sharing-status');
    const accuracyEl = document.getElementById('location-sharing-accuracy');
    const lastSentEl = document.getElementById('location-sharing-last-sent');

    let watcher = null;
    let sharing = false;
    let stopRequested = false;

    const applyStatus = ({ state, accuracy }) => {
        if (statusEl && !stopRequested) statusEl.textContent = STATUS_LABELS[state] ?? state;
        if (accuracy !== undefined && accuracy !== null && accuracyEl) {
            accuracyEl.textContent = `${Math.round(accuracy)} m`;
        }
        if ((state === 'sent' || state === 'flushed') && lastSentEl) {
            lastSentEl.textContent = new Date().toLocaleTimeString();
        }
    };

    function startSharing() {
        watcher = new GpsWatcher({ onStatus: applyStatus });
        watcher.start();
        sharing = true;
        stopRequested = false;
        toggle.disabled = false;
        if (toggleLabel) toggleLabel.textContent = 'Request to stop sharing';
    }

    // Sharing starts the moment this page loads — once on, the tracked person
    // can only *request* to stop (see below); they can't unilaterally kill it.
    startSharing();

    toggle.addEventListener('click', async () => {
        if (!sharing) {
            startSharing();

            return;
        }

        if (stopRequested) return;

        stopRequested = true;
        toggle.disabled = true;
        if (toggleLabel) toggleLabel.textContent = 'Waiting for tracker approval…';
        if (statusEl) statusEl.textContent = 'Stop requested — waiting for approval';

        try {
            await window.axios.post('/api/v1/location-sharing/stop-request');
        } catch (e) {
            stopRequested = false;
            toggle.disabled = false;
            if (toggleLabel) toggleLabel.textContent = 'Request to stop sharing';
            if (statusEl) statusEl.textContent = STATUS_LABELS.sent;
        }
    });

    if (window.__trackerUserId && window.Echo) {
        window.Echo.private(`App.Models.User.${window.__trackerUserId}`)
            .listen('.location-share.stop-resolved', (payload) => {
                stopRequested = false;
                toggle.disabled = false;

                if (payload.status === 'approved') {
                    watcher?.stop();
                    sharing = false;
                    if (toggleLabel) toggleLabel.textContent = 'Start Sharing';
                    if (statusEl) statusEl.textContent = 'Stopped';
                    window.Swal?.fire({
                        toast: true, position: 'top-end', timer: 5000, showConfirmButton: false,
                        icon: 'success', title: `${payload.resolved_by_name} approved — sharing stopped.`,
                    });
                } else {
                    if (toggleLabel) toggleLabel.textContent = 'Request to stop sharing';
                    if (statusEl) statusEl.textContent = STATUS_LABELS.sent;
                    window.Swal?.fire({
                        toast: true, position: 'top-end', timer: 5000, showConfirmButton: false,
                        icon: 'warning', title: `${payload.resolved_by_name} denied the request — still sharing.`,
                    });
                }
            });
    }
});
