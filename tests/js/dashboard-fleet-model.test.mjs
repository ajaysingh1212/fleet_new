import test from 'node:test';
import assert from 'node:assert/strict';
import {fleetKey, fleetLabel, streetDetailZoom} from '../../resources/js/dashboard-fleet-model.js';

test('unregistered devices keep distinct markers and prefer IMEI over device name', () => {
    const a = {vehicle_id: null, device_id: 1, registration: null, imei: '864540081540460', name: 'Tracker A'};
    const b = {vehicle_id: null, device_id: 2, registration: '', imei: '861128068689493', name: 'Tracker B'};
    const untrackedVehicle = {vehicle_id: 1, device_id: null, registration: 'BR28Z4291'};
    assert.equal(new Map([a, b, untrackedVehicle].map(p => [fleetKey(p), p])).size, 3);
    assert.equal(fleetLabel(a), a.imei);
    assert.equal(fleetLabel(b), b.imei);
    assert.equal(fleetLabel({...a, registration: '   '}), a.imei);
    assert.equal(fleetLabel({...a, registration: 'BR28Z4291'}), 'BR28Z4291');
    assert.notEqual(fleetKey(a), fleetKey(untrackedVehicle));
});
test('zooming into a state automatically enables detailed streets all the way to vehicle scale', () => {
    assert.equal(streetDetailZoom(5), false);
    assert.equal(streetDetailZoom(7), true);
    assert.equal(streetDetailZoom(12), true);
    assert.equal(streetDetailZoom(18), true);
    assert.equal(streetDetailZoom(19), true);
});
