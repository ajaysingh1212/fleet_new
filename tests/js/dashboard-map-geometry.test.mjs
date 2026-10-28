import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {stateName, insideFeature, labelPosition, hasPosition} from '../../resources/js/dashboard-map-geometry.js';

const map = JSON.parse(readFileSync(new URL('../../public/maps/india-states.geojson', import.meta.url), 'utf8'));
test('all 36 states and territories have interior labels and readable names', () => {
    assert.equal(map.features.length, 36);
    assert.equal(new Set(map.features.map(stateName)).size, 36);
    for (const state of map.features) assert.ok(insideFeature(state, ...labelPosition(state)), stateName(state));
    assert.ok(map.features.some(f => stateName(f) === 'Ladakh'));
    assert.ok(map.features.some(f => stateName(f) === 'Telangana'));
});
test('real coordinates resolve to their states and invalid fixes stay unlocated', () => {
    for (const [lat, lng, name] of [[25.6, 85.1, 'Bihar'], [19.076, 72.8777, 'Maharashtra'], [12.9716, 77.5946, 'Karnataka']]) {
        assert.equal(stateName(map.features.find(f => insideFeature(f, lat, lng))), name);
    }
    assert.equal(hasPosition({latitude: null, longitude: null}), false);
    assert.equal(hasPosition({latitude: 91, longitude: 70}), false);
    assert.equal(hasPosition({latitude: 25.6, longitude: 85.1}), true);
});
test('polygon holes and multipolygon islands are respected', () => {
    const state = {geometry: {type: 'MultiPolygon', coordinates: [[[[0,0],[10,0],[10,10],[0,10],[0,0]], [[3,3],[7,3],[7,7],[3,7],[3,3]]], [[[20,20],[21,20],[21,21],[20,21],[20,20]]]]}};
    assert.equal(insideFeature(state, 5, 5), false);
    assert.equal(insideFeature(state, 1, 1), true);
    assert.equal(insideFeature(state, 20.5, 20.5), true);
});
