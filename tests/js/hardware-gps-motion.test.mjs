import {test} from 'node:test';
import assert from 'node:assert/strict';
import {interpolatePosition, fleetState} from '../../resources/js/hardware-gps-motion.js';

test('measured movement interpolates smoothly and crosses the date line by the short path', () => {
    assert.deepEqual(interpolatePosition([25, 85], [26, 86], 0), [25, 85]);
    assert.deepEqual(interpolatePosition([25, 85], [26, 86], 0.5), [25.5, 85.5]);
    assert.deepEqual(interpolatePosition([25, 85], [26, 86], 2), [26, 86]);
    assert.equal(interpolatePosition([0, 179], [0, -179], 0.5)[1], -180);
});

test('fresh GPS and ACC distinguish moving, idle, parked, unknown and stale data', () => {
    const now = Date.parse('2026-10-17T08:00:00Z');
    const p = {authorization: 'active', last_packet_at: new Date(now).toISOString(), recorded_at: new Date(now).toISOString(), speed: 0, acc: true};
    assert.equal(fleetState(p, now), 'idle');
    assert.equal(fleetState({...p, acc: false}, now), 'parked');
    assert.equal(fleetState({...p, speed: 40}, now), 'moving');
    assert.equal(fleetState({...p, acc: null}, now), 'stopped');
    assert.equal(fleetState({...p, recorded_at: null}, now), 'awaiting_gps');
    assert.equal(fleetState({...p, recorded_at: new Date(now - 200000).toISOString()}, now), 'stale_gps');
    assert.equal(fleetState(p, now + 200000), 'offline');
    assert.equal(fleetState({...p, authorization: 'blocked'}, now), 'blocked');
});
