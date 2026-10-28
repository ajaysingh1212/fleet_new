export const stateName = feature => feature.properties.shapeName.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
export const hasPosition = p => Number.isFinite(p?.latitude) && Number.isFinite(p?.longitude)
    && Math.abs(p.latitude) <= 90 && Math.abs(p.longitude) <= 180;

function inRing(ring, x, y) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [ax, ay] = ring[i], [bx, by] = ring[j];
        const cross = (x - ax) * (by - ay) - (y - ay) * (bx - ax);
        if (Math.abs(cross) < 1e-10 && x >= Math.min(ax, bx) && x <= Math.max(ax, bx)
            && y >= Math.min(ay, by) && y <= Math.max(ay, by)) return true;
        if ((ay > y) !== (by > y) && x < (bx - ax) * (y - ay) / (by - ay) + ax) inside = !inside;
    }
    return inside;
}
export function insideFeature(feature, lat, lng) {
    const polygons = feature.geometry.type === 'Polygon' ? [feature.geometry.coordinates] : feature.geometry.coordinates;
    return polygons.some(rings => inRing(rings[0], lng, lat) && !rings.slice(1).some(r => inRing(r, lng, lat)));
}

// Place labels inside the largest land polygon, including irregular coastal states.
export function labelPosition(feature) {
    const polygons = feature.geometry.type === 'Polygon' ? [feature.geometry.coordinates] : feature.geometry.coordinates;
    const area = ring => Math.abs(ring.reduce((sum, p, i) => {
        const next = ring[(i + 1) % ring.length];
        return sum + p[0] * next[1] - next[0] * p[1];
    }, 0));
    const ring = [...polygons].sort((a, b) => area(b[0]) - area(a[0]))[0][0];
    const xs = ring.map(p => p[0]), ys = ring.map(p => p[1]);
    const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys);
    const center = [(minY + maxY) / 2, (minX + maxX) / 2];
    if (insideFeature(feature, ...center)) return center;
    const candidates = [];
    for (let x = 1; x < 12; x++) for (let y = 1; y < 12; y++) {
        const p = [minY + (maxY - minY) * y / 12, minX + (maxX - minX) * x / 12];
        if (insideFeature(feature, ...p)) candidates.push(p);
    }
    candidates.sort((a, b) => (a[0] - center[0]) ** 2 + (a[1] - center[1]) ** 2
        - (b[0] - center[0]) ** 2 - (b[1] - center[1]) ** 2);
    return candidates[0] ?? [ring[0][1], ring[0][0]];
}
