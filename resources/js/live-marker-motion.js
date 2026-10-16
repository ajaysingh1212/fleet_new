export class LiveMarkerMotion {
    constructor(marker, { request = (callback) => globalThis.requestAnimationFrame(callback),
        cancel = (frame) => globalThis.cancelAnimationFrame(frame),
        now = () => performance.now() } = {}) {
        this.marker = marker;
        this.request = request;
        this.cancel = cancel;
        this.now = now;
        this.queue = [];
        this.interval = 3000;
        this.lastArrival = null;
        this.frame = null;
        this.segment = null;
    }

    push(position, sampleInterval) {
        const arrival = this.now();
        const gap = this.lastArrival === null ? sampleInterval : arrival - this.lastArrival;
        this.lastArrival = arrival;
        if (gap > 100 && gap < 30000) {
            this.interval = this.interval * 0.5 + gap * 0.5;
        }
        this.queue.push({ lat: Number(position.lat), lng: Number(position.lng) });
        // Bound latency after a burst or a background-tab pause.
        if (this.queue.length > 4) this.queue.splice(0, this.queue.length - 4);
        if (this.frame === null) this.frame = this.request((time) => this.step(time));
    }

    step(time) {
        this.frame = null;
        if (!this.segment && this.queue.length) {
            const current = this.marker.getLatLng();
            this.segment = {
                from: { lat: current.lat, lng: current.lng }, to: this.queue.shift(),
                start: time, duration: Math.max(250, Math.min(15000, this.interval * 1.3))
                    / Math.max(1, this.queue.length),
            };
        }
        if (!this.segment) return;
        const { from, to, start, duration } = this.segment;
        const progress = Math.min(1, Math.max(0, (time - start) / duration));
        this.marker.setLatLng([
            from.lat + (to.lat - from.lat) * progress,
            from.lng + (to.lng - from.lng) * progress,
        ]);
        if (progress === 1) this.segment = null;
        if (this.segment || this.queue.length) {
            this.frame = this.request((next) => this.step(next));
        }
    }

    stop() {
        if (this.frame !== null) this.cancel(this.frame);
        this.frame = null;
        this.segment = null;
        this.queue = [];
    }
}
