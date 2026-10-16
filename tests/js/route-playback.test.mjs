import test from 'node:test';
import assert from 'node:assert/strict';
import { RoutePlayback, sampleRoute } from '../../resources/js/route-playback.js';
import { geofenceContains } from '../../resources/js/map-geometry.js';

const points = [
    { latitude: 25, longitude: 85, recorded_at: '2026-10-03T10:00:00Z', tracking_session_id: 1 },
    { latitude: 25.01, longitude: 85.01, recorded_at: '2026-10-03T10:00:10Z', tracking_session_id: 1 },
    { latitude: 25.02, longitude: 85.02, recorded_at: '2026-10-03T10:00:20Z', tracking_session_id: 1 },
];
function fixture() {
    const callbacks = new Map();
    let id = 0;
    let frame;
    const engine = new RoutePlayback({
        onFrame: (value) => { frame = value; }, onState: () => {},
        request: (callback) => { callbacks.set(++id, callback); return id; },
        cancel: (handle) => callbacks.delete(handle),
    });
    engine.setPoints(points);
    return { engine, frame: () => frame, count: () => callbacks.size, step(time) {
        const [handle, callback] = callbacks.entries().next().value;
        callbacks.delete(handle);
        callback(time);
    } };
}
test('marker interpolates coordinates and recorded time', () => {
    const frame = sampleRoute(points, 0.5);
    assert.ok(Math.abs(frame.latitude - 25.005) < 1e-10);
    assert.ok(Math.abs(frame.longitude - 85.005) < 1e-10);
    assert.equal(frame.recorded_at, '2026-10-03T10:00:05.000Z');
});
test('no fabricated travel across session boundaries or offline gaps', () => {
    const split = [{ ...points[0] }, { ...points[1], tracking_session_id: 2 }];
    assert.equal(sampleRoute(split, 0.5).latitude, points[0].latitude);
    const gap = [{ ...points[0] }, { ...points[1], recorded_at: '2026-10-03T11:00:00Z' }];
    assert.equal(sampleRoute(gap, 0.5).longitude, points[0].longitude);
});
for (const speed of [1, 2, 4, 8]) test('replay advances at ' + speed + 'x', () => {
    const { engine, step } = fixture();
    engine.setSpeed(speed);
    engine.play();
    step(0);
    step(60);
    assert.equal(engine.position, speed / 10);
});
test('pause cancels animation and seek/restart remains exact', () => {
    const { engine, step, count, frame } = fixture();
    engine.play();
    step(0);
    step(60);
    engine.pause();
    assert.equal(count(), 0);
    engine.seek(1.5);
    assert.equal(frame().latitude, 25.015);
    engine.seek(0);
    assert.equal(frame().latitude, 25);
});
test('changing speed mid-play works without starting duplicate loops', () => {
    const { engine, step, count } = fixture();
    engine.play();
    engine.play();
    assert.equal(count(), 1);
    step(0);
    step(60);
    engine.setSpeed(4);
    step(120);
    assert.equal(engine.position, 0.5);
});
test('end stops playback and play restarts from the beginning', () => {
    const { engine, step, count } = fixture();
    engine.setSpeed(8);
    engine.play();
    step(0);
    step(250);
    assert.equal(engine.position, 2);
    assert.equal(engine.running, false);
    assert.equal(count(), 0);
    engine.play();
    assert.equal(engine.position, 0);
});
test('empty/single-point routes do not animate, reset cancels old frames', () => {
    const { engine, count } = fixture();
    engine.play();
    engine.setPoints([]);
    engine.play();
    assert.equal(count(), 0);
    engine.setPoints([points[0]]);
    engine.play();
    assert.equal(count(), 0);
});
test('invalid speeds are rejected', () => {
    const { engine } = fixture();
    assert.throws(() => engine.setSpeed(0), RangeError);
    assert.throws(() => engine.setSpeed(3), RangeError);
});
test('local geofence labels change with movement, without any network lookup', () => {
    const home = { name: 'Home', type: 'circle', center_lat: 25, center_lng: 85, radius_meters: 100 };
    const office = { name: 'Office', type: 'circle', center_lat: 25.01, center_lng: 85.01, radius_meters: 100 };
    const label = (point) => [home, office].filter((zone) => geofenceContains(zone, point.latitude, point.longitude)).map((zone) => zone.name);
    assert.deepEqual(label(sampleRoute(points, 0)), ['Home']);
    assert.deepEqual(label(sampleRoute(points, 0.5)), []);
    assert.deepEqual(label(sampleRoute(points, 1)), ['Office']);
    const polygon = { type: 'polygon', points: [
        { latitude: 24.99, longitude: 84.99 }, { latitude: 25.01, longitude: 84.99 },
        { latitude: 25.01, longitude: 85.01 }, { latitude: 24.99, longitude: 85.01 },
    ] };
    assert.equal(geofenceContains(polygon, 25, 85), true);
    assert.equal(geofenceContains(polygon, 30, 90), false);
    assert.equal(geofenceContains({ type: 'polygon', points: [] }, 25, 85), false);
});
