import test from 'node:test';
import assert from 'node:assert/strict';
import { LiveMarkerMotion } from '../../resources/js/live-marker-motion.js';

test('default animation APIs retain the browser receiver', () => {
    const originalRequest = globalThis.requestAnimationFrame;
    const originalCancel = globalThis.cancelAnimationFrame;
    let callback;
    let cancelled;
    globalThis.requestAnimationFrame = function (next) {
        assert.equal(this, globalThis);
        callback = next;
        return 42;
    };
    globalThis.cancelAnimationFrame = function (frame) {
        assert.equal(this, globalThis);
        cancelled = frame;
    };
    try {
        const marker = {
            getLatLng: () => ({ lat: 0, lng: 0 }),
            setLatLng: () => {},
        };
        const motion = new LiveMarkerMotion(marker);
        motion.push({ lat: 1, lng: 1 }, 3000);
        callback(0);
        assert.equal(motion.frame, 42);
        motion.stop();
        assert.equal(cancelled, 42);
    } finally {
        if (originalRequest === undefined) delete globalThis.requestAnimationFrame;
        else globalThis.requestAnimationFrame = originalRequest;
        if (originalCancel === undefined) delete globalThis.cancelAnimationFrame;
        else globalThis.cancelAnimationFrame = originalCancel;
    }
});

function setup() {
    let time = 0;
    let callback;
    const marker = {
        position: { lat: 0, lng: 0 },
        getLatLng() { return this.position; },
        setLatLng([lat, lng]) { this.position = { lat, lng }; },
    };
    const motion = new LiveMarkerMotion(marker, {
        now: () => time,
        request: (next) => { callback = next; return 1; },
        cancel: () => { callback = null; },
    });
    return { marker, motion, tick(value) {
        time = value;
        const next = callback;
        callback = null;
        next?.(time);
    } };
}

test('updates queue without interrupting movement and stop at confirmed position', () => {
    const { marker, motion, tick } = setup();
    motion.push({ lat: 3, lng: 6 }, 3000);
    tick(0);
    tick(1500);
    assert.ok(marker.position.lat > 0 && marker.position.lat < 3);
    tick(3000);
    const before = marker.position.lat;
    motion.push({ lat: 6, lng: 12 }, 3000);
    assert.equal(marker.position.lat, before);
    tick(3500);
    assert.ok(marker.position.lat > before);
    tick(3900);
    assert.equal(marker.position.lat, 3);
    tick(3916);
    tick(5000);
    assert.ok(marker.position.lat > 3 && marker.position.lat < 6);
    tick(9000);
    assert.deepEqual(marker.position, { lat: 6, lng: 12 });
    assert.equal(motion.frame, null);
});

test('bounds burst queue and cancels removed markers', () => {
    const { motion } = setup();
    for (let i = 0; i < 20; i++) motion.push({ lat: i, lng: i }, 3000);
    assert.equal(motion.queue.length, 4);
    assert.equal(motion.queue.at(-1).lat, 19);
    motion.stop();
    assert.equal(motion.frame, null);
    assert.equal(motion.queue.length, 0);
});
