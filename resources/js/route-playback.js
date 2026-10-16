export function sampleRoute(points, position) {
    if (!points.length) return null;
    const bounded = Math.max(0, Math.min(points.length - 1, position));
    const index = Math.floor(bounded);
    const next = points[Math.min(index + 1, points.length - 1)];
    const point = points[index];
    const ratio = bounded - index;
    const startTime = Date.parse(point.recorded_at);
    const endTime = Date.parse(next.recorded_at);
    const gap = point.tracking_session_id !== next.tracking_session_id || endTime - startTime > 1800000;
    const progress = gap ? 0 : ratio;
    return {
        ...point, index, position: bounded, gap,
        latitude: Number(point.latitude) + (Number(next.latitude) - Number(point.latitude)) * progress,
        longitude: Number(point.longitude) + (Number(next.longitude) - Number(point.longitude)) * progress,
        recorded_at: new Date(startTime + (endTime - startTime) * progress).toISOString(),
    };
}

export class RoutePlayback {
    constructor({ onFrame, onState, request = (callback) => requestAnimationFrame(callback),
        cancel = (id) => cancelAnimationFrame(id) }) {
        Object.assign(this, { onFrame, onState, request, cancel });
        this.points = [];
        this.position = 0;
        this.speed = 1;
        this.running = false;
    }
    setPoints(points) {
        this.pause();
        this.points = points;
        this.position = 0;
        this.onFrame(sampleRoute(points, 0));
    }
    setSpeed(speed) {
        if (![1, 2, 4, 8].includes(speed)) throw new RangeError('Invalid replay speed');
        this.speed = speed;
    }
    seek(position) {
        this.pause();
        this.position = Number.isFinite(position) ? Math.max(0, Math.min(this.points.length - 1, position)) : 0;
        this.onFrame(sampleRoute(this.points, this.position));
    }
    play() {
        if (this.running || this.points.length < 2) return;
        if (this.position >= this.points.length - 1) this.position = 0;
        this.running = true;
        this.lastTime = null;
        this.onState(true);
        this.onFrame(sampleRoute(this.points, this.position));
        this.frameId = this.request((time) => this.tick(time));
    }
    tick(time) {
        if (!this.running) return;
        // Use the existing replay cadence, scaled by speed, without fast-forwarding background-tab time.
        const elapsed = this.lastTime === null ? 0 : Math.min(250, Math.max(0, time - this.lastTime));
        this.lastTime = time;
        this.position = Math.min(this.points.length - 1, this.position + elapsed * this.speed / 600);
        if (this.position >= this.points.length - 1) this.pause();
        this.onFrame(sampleRoute(this.points, this.position));
        if (this.running) this.frameId = this.request((next) => this.tick(next));
    }
    pause() {
        if (this.frameId !== undefined) this.cancel(this.frameId);
        this.frameId = undefined;
        this.running = false;
        this.lastTime = null;
        this.onState(false);
    }
}
