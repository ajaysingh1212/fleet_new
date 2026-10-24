import {test} from 'node:test';
import assert from 'node:assert/strict';
import {interpolatePosition, fleetState, fleetPresentation, mergeFleetUpdate} from '../../resources/js/hardware-gps-motion.js';

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

test('a newer ignition OFF heartbeat overrides the previous moving fix immediately', () => {
    const now = Date.parse('2026-10-23T09:00:00Z');
    const p = {last_packet_at: new Date(now).toISOString(), recorded_at: new Date(now - 10000).toISOString(),
        acc_reported_at: new Date(now).toISOString(), acc_reported: false, speed: 25};
    assert.equal(fleetState(p, now), 'parked');
    assert.equal(fleetPresentation(p, now).movement, 'Parked');
});

test('GPS expires independently of a live heartbeat instead of labeling a 139-second-old fix Fresh', () => {
    const now = Date.parse('2026-10-23T09:00:00Z');
    const p = {authorization: 'active', last_packet_at: new Date(now).toISOString(),
        recorded_at: new Date(now - 139000).toISOString(), speed: 25, acc_reported: true,
        acc_reported_at: new Date(now).toISOString()};
    assert.equal(fleetState(p, now), 'stale_gps');
    const display = fleetPresentation(p, now);
    assert.equal(display.connection, 'Online');
    assert.equal(display.movement, 'Last reported moving');
    assert.match(display.gps, /Delayed.*139s old/);
});

test('an explicitly closed device socket stays Offline despite recent or replayed packets', () => {
    const now = Date.parse('2026-10-23T10:00:00Z');
    const p = {device_id: 3, vehicle_id: null, authorization: 'active',
        last_packet_at: new Date(now).toISOString(), recorded_at: new Date(now - 1800000).toISOString(),
        speed: 19, connection_connected: false, connection_observed_at: new Date(now).toISOString()};
    assert.equal(fleetState(p, now), 'offline');
    assert.equal(fleetPresentation(p, now).connection, 'Offline');
    const old = {...p, connection_connected: true, connection_observed_at: new Date(now - 1000).toISOString()};
    assert.equal(fleetPresentation(mergeFleetUpdate(p, old), now).connection, 'Offline');
    const reconnected = {...p, connection_connected: true, connection_observed_at: new Date(now + 1000).toISOString()};
    assert.equal(fleetPresentation(mergeFleetUpdate(p, reconnected), now + 1000).connection, 'Online');
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

test('fresh ignition OFF shows parked even with an old or absent fix, without hiding GPS age', () => {
    const now = Date.parse('2026-10-23T07:30:00Z');
    const p = {authorization: 'active', last_packet_at: new Date(now).toISOString(),
        recorded_at: new Date(now - 1200000).toISOString(), speed: 0,
        acc_reported: false, acc_reported_at: new Date(now).toISOString(), gps_positioned_reported: false};
    assert.equal(fleetState(p, now), 'parked');
    assert.equal(fleetPresentation(p, now).movement, 'Parked');
    assert.match(fleetPresentation(p, now).gps, /No GPS fix/);
    assert.equal(fleetState({...p, recorded_at: null, speed: null}, now), 'parked');
    assert.equal(fleetState({...p, acc_reported_at: new Date(now - 181000).toISOString()}, now), 'no_gps_fix');
    assert.equal(fleetState({...p, recorded_at: new Date(now).toISOString(), speed: 20, gps_positioned_reported: true}, now), 'moving');
});

test('a late HTTP snapshot cannot erase or rewind a not-yet-persisted live position', () => {
    const live = {device_id: 1, vehicle_id: 3, latitude: 26, longitude: 85,
        recorded_at: '2026-10-23T08:00:00Z', last_packet_at: '2026-10-23T08:00:01.123Z',
        live_received_at: '2026-10-23T08:00:01.123Z', live_revision: 9, observed_at: '2026-10-23T08:00:01.200Z',
        acc_reported: false, acc_reported_at: '2026-10-23T08:00:01.123Z'};
    const db = {device_id: 1, vehicle_id: 3, name: 'Renamed vehicle', latitude: 24, longitude: 84,
        recorded_at: '2026-10-23T07:59:00Z', last_packet_at: '2026-10-23T07:59:01Z',
        observed_at: '2026-10-23T08:00:02Z'};
    assert.equal(mergeFleetUpdate(live, db).latitude, 26);
    assert.equal(mergeFleetUpdate(db, live).latitude, 26);
    assert.equal(mergeFleetUpdate(live, db).name, 'Renamed vehicle');
    assert.equal(mergeFleetUpdate(live, {...db, latitude: null, longitude: null, recorded_at: null, last_packet_at: null}).latitude, 26);
    assert.equal(mergeFleetUpdate(live, {...db, vehicle_id: 4}).latitude, 24);
});
