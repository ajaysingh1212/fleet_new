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

// Connectivity is independent of GPS age and ignition. A delayed speed is history.
export function fleetPresentation(p, now = Date.now()) {
    const threshold = (p.idle_seconds ?? 180)*1000;
    const age = now - Date.parse(p.recorded_at);
    const connection = p.authorization && p.authorization !== 'active' ? p.authorization
        : p.state === 'unassigned' ? 'Unassigned'
        : Number.isFinite(Date.parse(p.last_packet_at)) && now - Date.parse(p.last_packet_at) <= threshold
            ? 'Online' : 'Offline';
    const fresh = Number.isFinite(age) && age <= threshold && p.gps_positioned_reported !== false;
    const moving = p.speed != null && p.speed > 1.8;
    const movement = p.speed == null ? 'Movement unknown'
        : fresh && connection === 'Online' ? (moving ? 'Moving' : 'Stopped')
            : (moving ? 'Last reported moving' : 'Last reported stopped');
    const gps = !Number.isFinite(age) ? 'Awaiting location'
        : `${p.gps_positioned_reported === false ? 'No GPS fix' : age > threshold ? 'Delayed' : 'Fresh'} · ${Math.max(0, Math.floor(age/1000))}s old`;
    const accTime = Date.parse(p.acc_reported_at ?? p.recorded_at);
    const reportedAcc = p.acc_reported ?? p.acc;
    const accFresh = reportedAcc != null && Number.isFinite(accTime) && now - accTime <= threshold;
    const acc = accFresh ? `${reportedAcc ? 'ON' : 'OFF'} (device report${!reportedAcc && moving && fresh ? '; movement detected' : ''})`
        : reportedAcc == null ? 'Unknown' : `Unknown (last report ${reportedAcc ? 'ON' : 'OFF'})`;
    return {connection, movement, gps, acc};
}
