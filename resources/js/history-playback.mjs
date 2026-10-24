export const timestamp = value => Date.parse(/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/.test(value) ? value.replace(' ', 'T') + 'Z' : value);
export const speedColor = speed => speed == null ? '#94a3b8' : speed > 80 ? '#f59e0b' : speed > 40 ? '#3b82f6' : '#10b981';
export const isGap = (a, b) => a.gps_device_id !== b.gps_device_id || timestamp(b.recorded_at) - timestamp(a.recorded_at) > 300000;
// Playback uses measured time; long gaps and tracker changes have a short, discrete transition.
export function playbackTimeline(points) {
    let elapsed = 0;
    return points.map((point, i) => {
        if (i) elapsed += isGap(points[i - 1], point) ? 1000 : Math.max(1, timestamp(point.recorded_at) - timestamp(points[i - 1].recorded_at));
        return elapsed;
    });
}
export function playbackIndex(timeline, elapsed) {
    let low = 0, high = timeline.length - 1;
    while (low < high) {
        const mid = Math.ceil((low + high) / 2);
        if (timeline[mid] <= elapsed) low = mid; else high = mid - 1;
    }
    return low;
}
export function measuredDistance(points) {
    let km = 0;
    const rad = n => n * Math.PI / 180;
    for (let i = 1; i < points.length; i++) {
        const a = points[i - 1], b = points[i];
        if (isGap(a, b)) continue;
        const h = Math.sin(rad(b.latitude - a.latitude) / 2) ** 2 + Math.cos(rad(a.latitude)) * Math.cos(rad(b.latitude)) * Math.sin(rad(b.longitude - a.longitude) / 2) ** 2;
        km += 6371 * 2 * Math.asin(Math.sqrt(Math.min(1, h)));
    }
    return km;
}
