// Animate between measured positions without extrapolating a route or location.
export function interpolatePosition(from, to, progress) {
    const t = Math.max(0, Math.min(1, progress));
    const delta = ((to[1] - from[1] + 540) % 360) - 180;
    return [from[0] + (to[0] - from[0]) * t, ((from[1] + delta * t + 540) % 360) - 180];
}

export function fleetState(p, now = Date.now()) {
    if (p.authorization && p.authorization !== 'active') return p.authorization;
    if (p.state === 'unassigned') return 'unassigned';
    const threshold = (p.idle_seconds ?? 180) * 1000;
    const communication = Date.parse(p.last_packet_at);
    if (!Number.isFinite(communication) || now - communication > threshold) return 'offline';
    const fix = Date.parse(p.recorded_at);
    if (!Number.isFinite(fix)) return 'awaiting_gps';
    if (p.gps_positioned_reported === false) return 'no_gps_fix';
    if (now - fix > threshold) return 'stale_gps';
    if (p.speed == null) return 'unknown';
    if (p.speed > 1.8) return 'moving';
    return p.acc === true ? 'idle' : p.acc === false ? 'parked' : 'stopped';
}
