import {test} from 'node:test';
import assert from 'node:assert/strict';
import {HardwareFleetStream} from '../../resources/js/hardware-fleet-stream.js';

function harness(fetchSnapshot = async () => ({devices: [{device_id: 1}], meta: {}}), options = {}) {
    const subscriptions = new Map(), left = [], updates = [], snapshots = [], statuses = [];
    const connection = {state: 'connected', bind() {}, unbind() {}};
    const echo = {connector: {pusher: {connection}}, leave: name => left.push(name), private(name) {
        const channel = {listen(event, callback) { this.receive = callback; return this; },
            subscribed(callback) { this.ready = callback; return this; }, error(callback) { this.failed = callback; return this; }};
        subscriptions.set(name, channel); return channel;
    }};
    const stream = new HardwareFleetStream({echo, fetchSnapshot, debounce: 0,
        onSnapshot: data => snapshots.push(data), onUpdate: p => updates.push(p), onStatus: s => statuses.push(s), ...options});
    return {stream, subscriptions, left, updates, snapshots, statuses};
}

test('only visible device channels are watched, events update one device, page navigation leaves old channels', async () => {
    const h = harness(async () => ({devices: [{device_id: 2}], meta: {}}));
    h.stream.watch([{device_id: 1}, {device_id: null, vehicle_id: 9}]);
    assert.equal(h.subscriptions.size, 1);
    const old = h.subscriptions.get('admin.gps.hardware.device.1');
    old.receive({device_id: 1, speed: 0});
    assert.equal(h.updates.length, 1);
    await h.stream.load(1);
    assert.deepEqual(h.left, ['admin.gps.hardware.device.1']);
    old.receive({device_id: 1, speed: 50});
    assert.equal(h.updates.length, 1);
    assert.ok(h.subscriptions.has('admin.gps.hardware.device.2'));
    h.stream.dispose();
});

test('subscription and reconnect reconcile once before the recovery interval', async () => {
    let requests = 0;
    const h = harness(async () => { requests++; return {devices: [{device_id: 1}, {device_id: 2}], meta: {}}; });
    h.stream.watch([{device_id: 1}, {device_id: 2}]);
    assert.equal(requests, 0);
    for (const c of h.subscriptions.values()) c.ready();
    await new Promise(resolve => setTimeout(resolve, 15));
    assert.equal(requests, 1);
    await new Promise(resolve => setTimeout(resolve, 30));
    assert.equal(requests, 1);
    for (const c of h.subscriptions.values()) c.ready();
    await new Promise(resolve => setTimeout(resolve, 15));
    assert.equal(requests, 2);
    h.stream.dispose();
});

test('recovery finds a missed update despite other socket events, without overlapping loads; dispose stops recovery', async () => {
    let requests = 0, resolveSnapshot;
    const h = harness(() => { requests++; return new Promise(resolve => { resolveSnapshot = resolve; }); }, {recoveryMs: 15});
    h.stream.watch([{device_id: 1}, {device_id: 2}]);
    const active = h.subscriptions.get('admin.gps.hardware.device.2');
    const traffic = setInterval(() => active.receive({device_id: 2}), 3);
    try {
        await new Promise(resolve => setTimeout(resolve, 65));
        assert.equal(requests, 1);
        assert.ok(h.updates.length > 0);
        resolveSnapshot({devices: [{device_id: 1}, {device_id: 2}], meta: {}});
        await new Promise(resolve => setTimeout(resolve, 0));
        assert.equal(h.snapshots.length, 1);
        h.stream.dispose();
        await new Promise(resolve => setTimeout(resolve, 40));
        assert.equal(requests, 1);
    } finally { clearInterval(traffic); h.stream.dispose(); }
});

test('late snapshot responses cannot restore an old page and cleanup stops further updates', async () => {
    const pending = [];
    const h = harness(() => new Promise(resolve => pending.push(resolve)));
    const first = h.stream.load(0), second = h.stream.load(5);
    pending[1]({devices: [{device_id: 6}], meta: {}}); await second;
    pending[0]({devices: [{device_id: 1}], meta: {}}); await first;
    assert.equal(h.snapshots.length, 1);
    assert.equal(h.snapshots[0].devices[0].device_id, 6);
    h.stream.dispose();
    h.subscriptions.get('admin.gps.hardware.device.6').receive({device_id: 6});
    assert.equal(h.updates.length, 0);
});

test('snapshot recovery remains available when Echo is unavailable', async () => {
    let requests = 0;
    const h = harness(async () => { requests++; return {devices: [{device_id: 1}], meta: {}}; }, {echo: null, recoveryMs: 15});
    h.stream.watch([{device_id: 1}]);
    try {
        await new Promise(resolve => setTimeout(resolve, 55));
        assert.ok(requests >= 1);
        assert.equal(h.snapshots.length, requests);
    } finally { h.stream.dispose(); }
});
