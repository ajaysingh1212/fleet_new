// Animate between measured positions without extrapolating a route or location.
const communicationAt = p => Math.max(Date.parse(p.last_packet_at) || 0, Date.parse(p.connection_last_packet_at) || 0);
export function mergeFleetUpdate(old, incoming) {
    if (!old || old.vehicle_id !== incoming.vehicle_id) return incoming;
    const p = {...old, ...incoming};
    if (old.connection_observed_at && (!incoming.connection_observed_at
        || Date.parse(incoming.connection_observed_at) < Date.parse(old.connection_observed_at))) {
        p.connection_connected = old.connection_connected;
        p.connection_observed_at = old.connection_observed_at;
        p.connection_last_packet_at = old.connection_last_packet_at;
    }
    // A DB snapshot may be generated later while persistence is still behind
    // the live stream. Keep the live telemetry, but accept current metadata.
    const incomingLiveClock = Date.parse(incoming.live_received_at ?? incoming.last_packet_at);
    const liveBehind = old.live_received_at && (!Number.isFinite(incomingLiveClock) || incomingLiveClock < Date.parse(old.live_received_at));
    if (liveBehind) {
        for (const field of ['latitude', 'longitude', 'recorded_at', 'received_at', 'speed', 'heading',
            'last_packet_at', 'acc', 'acc_reported', 'acc_reported_at', 'acc_source',
            'gps_positioned_reported', 'gps_reported_at', 'gps_fresh', 'gps_state',
            'live_revision', 'live_received_at', 'altitude', 'satellites', 'sensors', 'heartbeat_sensors', 'heartbeat_reported_at']) p[field] = old[field];
    } else if (old.observed_at && incoming.observed_at && incoming.observed_at < old.observed_at
        && !(Date.parse(incoming.live_received_at) > (Date.parse(old.live_received_at ?? old.last_packet_at) || 0))) return old;
    if (Date.parse(incoming.recorded_at) < Date.parse(old.recorded_at)) {
        for (const field of ['latitude', 'longitude', 'recorded_at', 'received_at', 'speed', 'heading', 'altitude', 'satellites', 'sensors']) p[field] = old[field];
    }
    if (Date.parse(incoming.acc_reported_at) < Date.parse(old.acc_reported_at)) {
        for (const field of ['acc', 'acc_reported', 'acc_reported_at', 'acc_source']) p[field] = old[field];
    }
    return p;
}

export function interpolatePosition(from, to, progress) {
    const t = Math.max(0, Math.min(1, progress));
    const delta = ((to[1] - from[1] + 540) % 360) - 180;
    return [from[0] + (to[0] - from[0]) * t, ((from[1] + delta * t + 540) % 360) - 180];
}

export function fleetState(p, now = Date.now()) {
    if (p.authorization && p.authorization !== 'active') return p.authorization;
    if (p.state === 'unassigned') return 'unassigned';
    const threshold = (p.idle_seconds ?? 180) * 1000;
    const gpsThreshold = (p.gps_fresh_seconds ?? 15) * 1000;
    const communication = communicationAt(p);
    if (!communication || now - communication > threshold) return 'offline';
    const fix = Date.parse(p.recorded_at);
    const accTime = Date.parse(p.acc_reported_at ?? p.recorded_at);
    const acc = Number.isFinite(accTime) && now - accTime <= threshold ? (p.acc_reported ?? p.acc) : null;
    const fresh = Number.isFinite(fix) && now - fix <= gpsThreshold && p.gps_positioned_reported !== false;
    if (acc === false && accTime > fix) return 'parked';
    if (fresh && p.speed != null && p.speed > 1.8) return 'moving';
    if (acc === false) return 'parked';
    if (!Number.isFinite(fix)) return 'awaiting_gps';
    if (p.gps_positioned_reported === false) return 'no_gps_fix';
    if (now - fix > gpsThreshold) return 'stale_gps';
    if (p.speed == null) return 'unknown';
    if (p.speed > 1.8) return 'moving';
    return acc === true ? 'idle' : 'stopped';
}

// Connectivity is independent of GPS age and ignition. A delayed speed is history.
export function fleetPresentation(p, now = Date.now()) {
    const threshold = (p.idle_seconds ?? 180)*1000;
    const gpsThreshold = (p.gps_fresh_seconds ?? 15)*1000;
    const age = now - Date.parse(p.recorded_at);
    const communication = communicationAt(p);
    const connection = p.authorization && p.authorization !== 'active' ? p.authorization
        : p.state === 'unassigned' ? 'Unassigned'
        : communication > 0 && now - communication <= threshold ? 'Online' : 'Offline';
    const fresh = Number.isFinite(age) && age <= gpsThreshold && p.gps_positioned_reported !== false;
    const moving = p.speed != null && p.speed > 1.8;
    const state = fleetState(p, now);
    const movement = state === 'parked' ? 'Parked' : state === 'idle' ? 'Idle'
        : state === 'moving' ? 'Moving' : state === 'stopped' ? 'Stopped'
            : p.speed == null ? 'Movement unknown' : (moving ? 'Last reported moving' : 'Last reported stopped');
    const gps = !Number.isFinite(age) ? 'Awaiting location'
        : `${p.gps_positioned_reported === false ? 'No GPS fix' : age > gpsThreshold ? 'Delayed' : 'Fresh'} · ${Math.max(0, Math.floor(age/1000))}s old`;
    const accTime = Date.parse(p.acc_reported_at ?? p.recorded_at);
    const reportedAcc = p.acc_reported ?? p.acc;
    const accFresh = reportedAcc != null && Number.isFinite(accTime) && now - accTime <= threshold;
    const acc = accFresh ? `${reportedAcc ? 'ON' : 'OFF'} (device report${!reportedAcc && moving && fresh ? '; movement detected' : ''})`
        : reportedAcc == null ? 'Unknown' : `Unknown (last report ${reportedAcc ? 'ON' : 'OFF'})`;
    const tcp = p.connection_connected === true ? 'Connected' : p.connection_connected === false ? 'Disconnected / reconnecting' : 'Unknown';
    return {connection, movement, gps, acc, tcp};
}
