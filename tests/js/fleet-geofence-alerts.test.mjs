import {test} from 'node:test';
import assert from 'node:assert/strict';
import {containsLocation, liveGeofenceWarnings} from '../../resources/js/fleet-geofence-alerts.js';
const now = Date.parse('2026-10-23T10:00:00Z');
const zone = {rule_id: 1, gps_device_id: 3, vehicle_id: null, name: 'Depot', status: 'active', type: 'circle', center_lat: 25.6091868, center_lng: 85.1443434, radius_meters: 38, min_speed_kmh: 10, max_speed_kmh: 100};
const device = {device_id: 3, vehicle_id: null, authorization: 'active', latitude: 25.609695, longitude: 85.1480428, speed: 0, recorded_at: new Date(now).toISOString(), idle_seconds: 180};

test('live outside and speed warnings render before any SQL alert exists, recovery clears immediately', () => {
    const warnings = liveGeofenceWarnings({...device, speed: 120}, [zone], [], now);
    assert.deepEqual(warnings.map(a => a.type), ['outside', 'overspeed']);
    assert.ok(warnings.every(a => a.pending && !a.historical));
    assert.equal(warnings[1].limit, 100);
    const confirmed = warnings.map((a, id) => ({...a, id: id + 1, started_at: new Date(now).toISOString()}));
    const recovered = {...device, latitude: Number(zone.center_lat), longitude: Number(zone.center_lng), speed: 30};
    assert.deepEqual(liveGeofenceWarnings(recovered, [zone], confirmed, now), []);
});

test('actual parked tracker position shows historical boundary warning with no invented speed violation or start time', () => {
    const old = {...device, speed: 120, recorded_at: '2026-10-23T06:49:31Z', gps_positioned_reported: false};
    const warnings = liveGeofenceWarnings(old, [zone], [], now);
    assert.equal(warnings.length, 1);
    assert.equal(warnings[0].type, 'outside');
    assert.equal(warnings[0].historical, true);
    assert.match(warnings[0].detail, /GPS update pending/);
    assert.equal(warnings[0].started_at, undefined);
    assert.ok(liveGeofenceWarnings({...device, gps_positioned_reported: false}, [zone], [], now)[0].historical);
});

test('minimum speed ignores parking, unknown speed retains last reported violation, fresh speed clears it', () => {
    const inside = {...device, latitude: Number(zone.center_lat), longitude: Number(zone.center_lng)};
    assert.deepEqual(liveGeofenceWarnings(inside, [zone], [], now), []);
    assert.equal(liveGeofenceWarnings({...inside, speed: 5}, [zone], [], now)[0].type, 'underspeed');
    const saved = [{rule_id: 1, type: 'overspeed', limit: 100, speed: 120}];
    const unknown = liveGeofenceWarnings({...inside, speed: null}, [zone], saved, now);
    assert.equal(unknown.length, 1); assert.equal(unknown[0].historical, true);
    assert.deepEqual(liveGeofenceWarnings({...inside, speed: 30}, [zone], saved, now), []);
});

test('device targets follow IMEI assignment, vehicle rules follow their vehicle and inactive rules do not warn', () => {
    assert.equal(liveGeofenceWarnings({...device, vehicle_id: 7}, [zone], [], now).length, 1);
    assert.equal(liveGeofenceWarnings({...device, device_id: 4}, [zone], [], now).length, 0);
    const vehicleRule = {...zone, gps_device_id: null, vehicle_id: 7};
    assert.equal(liveGeofenceWarnings(device, [vehicleRule], [], now).length, 0);
    assert.equal(liveGeofenceWarnings({...device, vehicle_id: 7}, [vehicleRule], [], now).length, 1);
    assert.deepEqual(liveGeofenceWarnings(device, [{...zone, status: 'inactive'}], [], now), []);
    assert.deepEqual(liveGeofenceWarnings({...device, authorization: 'inactive'}, [zone], [], now), []);
});

test('circle and polygon edges count as inside; missing coordinates cannot create boundary warnings', () => {
    assert.equal(containsLocation(zone, Number(zone.center_lat), Number(zone.center_lng)), true);
    assert.equal(containsLocation(zone, device.latitude, device.longitude), false);
    const polygon = {...zone, type: 'rectangle', points: [{latitude: 0, longitude: 0}, {latitude: 0, longitude: 1}, {latitude: 1, longitude: 1}, {latitude: 1, longitude: 0}]};
    assert.equal(containsLocation(polygon, .5, .5), true);
    assert.equal(containsLocation(polygon, 0, .5), true);
    assert.equal(containsLocation(polygon, 2, 2), false);
    assert.deepEqual(liveGeofenceWarnings({...device, latitude: null, longitude: null}, [zone], [], now), []);
});
