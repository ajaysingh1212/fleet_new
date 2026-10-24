// One WebSocket connection; subscribe only to the bounded map page. HTTP is
// used for reconciliation and bounded recovery when delivery goes silent.
export class HardwareFleetStream {
    constructor({echo, fetchSnapshot, onSnapshot, onUpdate, onStatus, debounce = 300, recoveryMs = 5000}) {
        Object.assign(this, {echo, fetchSnapshot, onSnapshot, onUpdate, onStatus, debounce});
        this.channels = new Map();
        this.watchedCount = 0;
        this.after = 0;
        this.generation = 0;
        this.disposed = false;
        this.syncTimer = null;
        this.controller = null;
        this.loading = false;
        this.lastReconciled = Date.now();
        // A healthy socket does not prove a broadcast worker is delivering.
        // Recover the current bounded page without overlapping/aborting a load.
        this.recoveryTimer = setInterval(() => {
            if (!this.disposed && !this.loading && this.watchedCount
                && Date.now() - this.lastReconciled >= recoveryMs) this.load();
        }, recoveryMs);
        this.recoveryTimer.unref?.();
        this.connection = echo?.connector?.pusher?.connection;
        this.stateChanged = ({current}) => {
            if (current !== 'connected') this.onStatus('disconnected');
        };
        this.connection?.bind('state_change', this.stateChanged);
    }

    watch(devices) {
        const wanted = new Set(devices.filter(p => p.device_id != null).map(p => Number(p.device_id)));
        this.watchedCount = wanted.size;
        for (const [id, name] of this.channels) if (!wanted.has(id)) {
            this.echo?.leave(name);
            this.channels.delete(id);
        }
        if (!this.echo) { this.onStatus('unavailable'); return; }
        for (const id of wanted) {
            if (this.channels.has(id)) continue;
            const name = `admin.gps.hardware.device.${id}`;
            this.channels.set(id, name);
            this.echo.private(name)
                .listen('.hardware.location.updated', p => {
                    if (!this.disposed && this.channels.has(Number(p.device_id))) {
                        this.onUpdate(p);
                        this.onStatus('live');
                    }
                })
                .subscribed(() => {
                    if (this.disposed) return;
                    this.onStatus('live');
                    // Covers packets sent during authorization or a reconnect.
                    clearTimeout(this.syncTimer);
                    this.syncTimer = setTimeout(() => this.load(this.after), this.debounce);
                })
                .error(() => this.onStatus('unauthorized'));
        }
        if (!wanted.size) this.onStatus('empty');
    }

    async load(after = this.after) {
        if (this.disposed) return;
        clearTimeout(this.syncTimer);
        const generation = ++this.generation;
        this.loading = true;
        this.controller?.abort();
        this.controller = new AbortController();
        this.onStatus('syncing');
        try {
            const data = await this.fetchSnapshot(after, this.controller.signal);
            if (this.disposed || generation !== this.generation) return;
            this.after = after;
            this.lastReconciled = Date.now();
            this.onSnapshot(data);
            this.watch(data.devices);
            this.onStatus(this.connection?.state === 'connected' ? 'live' : 'disconnected');
        } catch (error) {
            if (!this.disposed && generation === this.generation && error?.name !== 'AbortError' && error?.code !== 'ERR_CANCELED') {
                this.onStatus('error');
            }
        } finally {
            if (generation === this.generation) this.loading = false;
        }
    }

    dispose() {
        this.disposed = true;
        ++this.generation;
        clearTimeout(this.syncTimer);
        clearInterval(this.recoveryTimer);
        this.controller?.abort();
        this.connection?.unbind('state_change', this.stateChanged);
        for (const name of this.channels.values()) this.echo?.leave(name);
        this.channels.clear();
    }
}
