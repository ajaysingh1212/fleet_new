import {test} from 'node:test';
import assert from 'node:assert/strict';
import {interpolatePosition, fleetState, fleetPresentation} from '../../resources/js/hardware-gps-motion.js';

test('measured movement interpolates smoothly and crosses the date line by the short path', () => {
    assert.deepEqual(interpolatePosition([25, 85], [26, 86], 0), [25, 85]);
    assert.deepEqual(interpolatePosition([25, 85], [26, 86], 0.5), [25.5, 85.5]);
    assert.deepEqual(interpolatePosition([25, 85], [26, 86], 2), [26, 86]);
    assert.equal(interpolatePosition([0, 179], [0, -179], 0.5)[1], -180);
});

test('live connection remains online with delayed GPS; movement is explicitly historical', () => {
    const now = Date.parse('2026-10-22T08:20:00Z');
    const p = {authorization: 'active', last_packet_at: new Date(now).toISOString(),
        recorded_at: new Date(now - 300000).toISOString(), speed: 6, acc: false,
        acc_reported: false, acc_reported_at: new Date(now - 240000).toISOString()};
    const display = fleetPresentation(p, now);
    assert.equal(display.connection, 'Online');
    assert.equal(display.movement, 'Last reported moving');
    assert.equal(display.gps, 'Delayed · 300s old');
    assert.equal(display.acc, 'Unknown (last report OFF)');
    assert.equal(fleetPresentation(p, now + 181000).connection, 'Offline');
});

test('fresh measured movement remains moving when the device reports ignition OFF', () => {
    const now = Date.parse('2026-10-22T08:20:00Z');
    const p = {last_packet_at: new Date(now).toISOString(), recorded_at: new Date(now).toISOString(),
        acc_reported_at: new Date(now).toISOString(), acc_reported: false, speed: 6};
    const display = fleetPresentation(p, now);
    assert.equal(display.connection, 'Online');
    assert.equal(display.movement, 'Moving');
    assert.equal(display.acc, 'OFF (device report; movement detected)');
    assert.equal(fleetPresentation({...p, authorization: 'blocked'}, now).connection, 'blocked');
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
