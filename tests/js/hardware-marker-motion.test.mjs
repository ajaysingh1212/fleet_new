import {test} from 'node:test';
import assert from 'node:assert/strict';
import {HardwareMarkerMotion} from '../../resources/js/hardware-marker-motion.js';

function setup(position = [25, 85]) {
    let time = 0, callback = null;
    const marker = {
        position,
        getLatLng() { return {lat: this.position[0], lng: this.position[1]}; },
        setLatLng(p) { this.position = p; },
    };
    const motion = new HardwareMarkerMotion(marker, {
        now: () => time,
        request: next => { callback = next; return 1; },
        cancel: () => { callback = null; },
    });
    return {marker, motion, tick(nextTime) {
        time = nextTime;
        const next = callback; callback = null; next?.(time);
    }};
}

test('frames approach the measured target monotonically without overshooting or snapping', () => {
    const {marker, motion, tick} = setup();
    motion.push([25.001, 85.002], 1);
    assert.deepEqual(marker.position, [25, 85]);
    let previous = 25;
    for (let t = 0; t <= 1000; t += 10) {
        tick(t);
        assert.ok(marker.position[0] >= previous - 1e-12);
        assert.ok(marker.position[0] <= 25.001 + 1e-12);
        previous = marker.position[0];
    }
    assert.ok(Math.abs(marker.position[0] - 25.001) < 1e-12);
    assert.equal(motion.frame, null);
});

test('retargeting preserves position and velocity while updates keep arriving', () => {
    const {marker, motion, tick} = setup();
    motion.push([25.001, 85.002], 1);
    tick(400);
    const before = motion.sample(400);
    const visible = [...marker.position];
    motion.push([25.002, 85.004], 2);
    assert.deepEqual(marker.position, visible);
    assert.deepEqual(motion.sample(400), before);
    tick(416);
    assert.ok(marker.position[0] > visible[0]);
    tick(800);
    assert.ok(Math.abs(marker.position[0] - 25.002) < 1e-12);
});

test('duplicates and old fixes never rewind or restart the current animation', () => {
    const {motion, tick} = setup();
    motion.push([25.001, 85.001], 100);
    tick(400);
    const segment = motion.segment;
    motion.push([24, 84], 99);
    motion.push([24, 84], 100);
    motion.push([NaN, 84], 101);
    assert.equal(motion.segment, segment);
});

test('a burst retargets immediately to the newest fix without a playback backlog', () => {
    const {marker, motion, tick} = setup();
    for (let i = 1; i <= 200; i++) motion.push([25 + i/100000, 85], i);
    tick(400);
    assert.ok(Math.abs(marker.position[0] - 25.002) < 1e-12);
    assert.equal(motion.frame, null);
    tick(10000);
    assert.ok(Math.abs(marker.position[0] - 25.002) < 1e-12);
});

test('long gaps have bounded catch-up time and removal cancels animation', () => {
    const {motion, tick} = setup();
    motion.push([25.001, 85], 1);
    tick(60000);
    motion.push([25.002, 85], 2);
    assert.equal(motion.segment.duration, 1600);
    motion.stop();
    assert.equal(motion.frame, null);
    assert.equal(motion.segment, null);
});

test('crossing the date line follows the short path', () => {
    const {marker, motion, tick} = setup([0, 179]);
    motion.push([0, -179], 1);
    tick(500);
    assert.ok(Math.abs(marker.position[1] - 180) < 1e-12);
    tick(1000);
    assert.equal(marker.position[1], 181);
});
