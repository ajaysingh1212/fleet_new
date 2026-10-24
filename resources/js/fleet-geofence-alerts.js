// Live map evaluation does not wait for SQL history. Old fixes are explicitly historical.
export const warningLabel = type => ({outside: 'Outside geofence', overspeed: 'Speed limit exceeded', underspeed: 'Below minimum speed'}[type] ?? type);
export function containsLocation(zone, latitude, longitude) {
    if (zone.type === 'circle') {
        const rad = n => n * Math.PI / 180;
        const dlat = rad(latitude - Number(zone.center_lat)), dlng = rad(longitude - Number(zone.center_lng));
        const a = Math.sin(dlat / 2) ** 2 + Math.cos(rad(latitude)) * Math.cos(rad(Number(zone.center_lat))) * Math.sin(dlng / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a))) <= Number(zone.radius_meters);
    }
    const points = zone.points ?? [];
    if (points.length < 3) return null;
    let inside = false;
    for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
        const a = points[i], b = points[j];
        // Include points on an edge, consistently with a circle's boundary.
        const cross = (latitude - a.latitude) * (b.longitude - a.longitude) - (longitude - a.longitude) * (b.latitude - a.latitude);
        if (Math.abs(cross) < 1e-12 && latitude >= Math.min(a.latitude, b.latitude) && latitude <= Math.max(a.latitude, b.latitude)
            && longitude >= Math.min(a.longitude, b.longitude) && longitude <= Math.max(a.longitude, b.longitude)) return true;
        if ((Number(a.longitude) > longitude) !== (Number(b.longitude) > longitude)
            && latitude < (Number(b.latitude) - Number(a.latitude)) * (longitude - Number(a.longitude)) / (Number(b.longitude) - Number(a.longitude)) + Number(a.latitude)) inside = !inside;
    }
    return inside;
}
export function liveGeofenceWarnings(device, zones, confirmed = [], now = Date.now()) {
    if (!device || device.authorization !== 'active') return [];
    const applicable = zones.filter(z => z.status === 'active' && (z.gps_device_id != null
        ? Number(z.gps_device_id) === Number(device.device_id)
        : device.vehicle_id != null && Number(z.vehicle_id) === Number(device.vehicle_id)));
    const stamp = Date.parse(device.recorded_at);
    const fresh = Number.isFinite(stamp) && now - stamp <= Number(device.idle_seconds ?? 180) * 1000
        && stamp <= now + 60000 && device.gps_positioned_reported !== false;
    const hasPoint = Number.isFinite(device.latitude) && Number.isFinite(device.longitude);
    const warnings = [];
    const seen = new Set();
    for (const zone of applicable) {
        seen.add(Number(zone.rule_id));
        const add = (type, limit = null) => {
            const saved = confirmed.find(a => Number(a.rule_id) === Number(zone.rule_id) && a.type === type);
            warnings.push({ ...saved, rule_id: zone.rule_id, gps_device_id: device.device_id, vehicle_id: device.vehicle_id,
                type, geofence_name: zone.name, speed: device.speed, limit, recorded_at: device.recorded_at,
                historical: !fresh, pending: !saved, label: warningLabel(type),
                detail: !fresh ? 'Last known location ? GPS update pending' : (saved ? 'Violation recorded' : 'Live GPS warning ? history updating') });
        };
        if (hasPoint && containsLocation(zone, device.latitude, device.longitude) === false) add('outside');
        // Never issue a new speed warning from an old speed measurement.
        if (fresh && device.speed != null) {
            if (zone.max_speed_kmh != null && device.speed > Number(zone.max_speed_kmh)) add('overspeed', Number(zone.max_speed_kmh));
            else if (zone.min_speed_kmh != null && device.speed > 0 && device.speed < Number(zone.min_speed_kmh)) add('underspeed', Number(zone.min_speed_kmh));
        }
        if (!fresh || device.speed == null) {
            for (const saved of confirmed.filter(a => Number(a.rule_id) === Number(zone.rule_id) && a.type !== 'outside')) {
                warnings.push({...saved, historical: true, label: warningLabel(saved.type), detail: 'Last reported violation ? awaiting GPS / speed update'});
            }
        }
    }
    for (const saved of confirmed.filter(a => !seen.has(Number(a.rule_id)))) {
        warnings.push({...saved, historical: !fresh, label: warningLabel(saved.type), detail: 'Last reported violation'});
    }
    return warnings;
}
