export function distanceMeters(lat1, lng1, lat2, lng2) {
    const radius = 6371000;
    const toRadians = (value) => value * Math.PI / 180;
    const latDelta = toRadians(lat2 - lat1);
    const lngDelta = toRadians(lng2 - lng1);
    const a = Math.sin(latDelta / 2) ** 2
        + Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(lngDelta / 2) ** 2;

    return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function geofenceContains(geofence, lat, lng) {
    if (geofence.type === 'circle') {
        return distanceMeters(lat, lng, Number(geofence.center_lat), Number(geofence.center_lng)) <= Number(geofence.radius_meters);
    }

    const points = geofence.points ?? [];
    if (points.length < 3) return false;

    let inside = false;
    for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
        const yi = Number(points[i].latitude);
        const xi = Number(points[i].longitude);
        const yj = Number(points[j].latitude);
        const xj = Number(points[j].longitude);
        const intersects = ((yi > lat) !== (yj > lat))
            && (lng < ((xj - xi) * (lat - yi)) / ((yj - yi) || Number.EPSILON) + xi);
        if (intersects) inside = !inside;
    }

    return inside;
}
