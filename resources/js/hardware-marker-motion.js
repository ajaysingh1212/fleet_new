// One measured target at a time: no playback queue or predicted GPS positions.
export class HardwareMarkerMotion {
    constructor(marker, {now = () => performance.now(),
        request = callback => globalThis.requestAnimationFrame(callback),
        cancel = frame => globalThis.cancelAnimationFrame(frame)} = {}) {
        Object.assign(this, {marker, now, request, cancel});
        this.frame = null;
        this.segment = null;
        this.timestamp = -Infinity;
        this.lastArrival = null;
    }

    sample(time) {
        if (!this.segment) {
            const p = this.marker.getLatLng();
            return {position: [p.lat, p.lng], velocity: [0, 0]};
        }
        const {from, to, velocity, start, duration} = this.segment;
        const t = Math.min(1, Math.max(0, (time - start) / duration));
        // Monotone Hermite interpolation preserves velocity on ordinary retargets
        // and arrives at the measured point without overshoot.
        const position = from.map((value, i) => (2*t**3 - 3*t**2 + 1)*value
            + (t**3 - 2*t**2 + t)*duration*velocity[i] + (-2*t**3 + 3*t**2)*to[i]);
        const speed = from.map((value, i) => ((6*t**2 - 6*t)*value
            + (3*t**2 - 4*t + 1)*duration*velocity[i] + (-6*t**2 + 6*t)*to[i]) / duration);
        return {position, velocity: speed};
    }

    push(position, timestamp) {
        if (!Number.isFinite(timestamp) || timestamp <= this.timestamp
            || !position.every(Number.isFinite)) return;
        const time = this.now();
        const current = this.sample(time);
        const gap = this.lastArrival === null ? 1000 : time - this.lastArrival;
        // Cap catch-up time even for old batches; never replay a growing backlog.
        const duration = Math.max(400, Math.min(1600, gap));
        const to = [...position];
        to[1] = current.position[1] + ((to[1] - current.position[1] + 540) % 360) - 180;
        const velocity = current.velocity.map((v, i) => {
            const delta = to[i] - current.position[i];
            return Math.sign(v) === Math.sign(delta)
                ? Math.sign(delta)*Math.min(Math.abs(v), 3*Math.abs(delta)/duration) : 0;
        });
        this.segment = {from: current.position, to, velocity, start: time, duration};
        this.timestamp = timestamp;
        this.lastArrival = time;
        if (this.frame === null) this.frame = this.request(next => this.step(next));
    }

    step(time) {
        this.frame = null;
        if (!this.segment) return;
        this.marker.setLatLng(this.sample(time).position);
        if (time - this.segment.start < this.segment.duration) {
            this.frame = this.request(next => this.step(next));
        } else this.segment = null;
    }

    stop() {
        if (this.frame !== null) this.cancel(this.frame);
        this.frame = null;
        this.segment = null;
    }
}
