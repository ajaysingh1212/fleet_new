import test from 'node:test';
import assert from 'node:assert/strict';
import {timestamp, speedColor, isGap, playbackTimeline, playbackIndex, measuredDistance} from '../../resources/js/history-playback.mjs';
import {historyPdf} from '../../resources/js/history-pdf.mjs';
const point = (seconds, overrides = {}) => ({id: seconds, gps_device_id: 1, latitude: 25, longitude: 85 + seconds / 10000, speed: 40, recorded_at: new Date(Date.UTC(2026, 9, 23, 10, 0, seconds)).toISOString(), ...overrides});
const points = [point(0), point(16), point(32)];
for (const rate of [2, 4, 8, 16]) test(`recorded playback advances at ${rate}x`, () => {
    const timeline = playbackTimeline(points);
    assert.equal(playbackIndex(timeline, 16000 / rate * rate), 1);
    assert.equal(playbackIndex(timeline, 32000 / rate * rate), 2);
    assert.equal(playbackIndex(timeline, 15999), 0);
});
test('GPS gaps and tracker changes get a discrete transition and no invented distance', () => {
    const route = [point(0), point(16), point(800), point(816, {gps_device_id: 2})];
    assert.deepEqual(playbackTimeline(route), [0, 16000, 17000, 18000]);
    assert.equal(isGap(route[1], route[2]), true);
    assert.equal(isGap(route[2], route[3]), true);
    assert.equal(measuredDistance(route), measuredDistance(route.slice(0, 2)));
});
test('empty, singleton, duplicate GPS times and unknown speeds are supported', () => {
    assert.deepEqual(playbackTimeline([]), []);
    assert.deepEqual(playbackTimeline([points[0]]), [0]);
    assert.deepEqual(playbackTimeline([points[0], points[0]]), [0, 1]);
    assert.equal(measuredDistance([]), 0);
    assert.equal(speedColor(null), '#94a3b8');
    assert.equal(timestamp('2026-10-23 10:00:00'), Date.parse('2026-10-23T10:00:00Z'));
});
test('speed bands agree at boundaries', () => {
    assert.equal(speedColor(40), '#10b981');
    assert.equal(speedColor(41), '#3b82f6');
    assert.equal(speedColor(80), '#3b82f6');
    assert.equal(speedColor(81), '#f59e0b');
});
test('PDF retains all records, escapes text and has valid byte offsets on every page', () => {
    const rows = Array.from({length: 151}, (_, i) => [`record-${i}`, '25.000000', '85.000000', '20', 'ON']);
    const pdf = historyPdf({title: 'Car (BR01) \\ fleet', range: '23 Oct 2026 IST', route: rows, points, truncated: true, summary: {points: 151, max_speed: 20}, alerts: [['Depot', 'Outside', '23 Oct', 'Open alert', '-']]});
    assert.ok(pdf.startsWith('%PDF-1.4'));
    assert.ok(pdf.includes('Car \\(BR01\\) \\\\ fleet'));
    for (let i = 0; i < rows.length; i++) assert.ok(pdf.includes(`(record-${i})`));
    assert.match(pdf, /the tables contain all report records/);
    const xrefOffset = Number(pdf.match(/startxref\n(\d+)/)[1]);
    assert.equal(pdf.slice(xrefOffset, xrefOffset + 4), 'xref');
    const offsets = [...pdf.matchAll(/(\d{10}) 00000 n/g)].map(m => Number(m[1]));
    offsets.forEach((offset, i) => assert.ok(pdf.slice(offset).startsWith(`${i + 1} 0 obj`)));
    assert.ok(Number(pdf.match(/\/Count (\d+)/)[1]) > 3);
});
test('empty reports still create a usable PDF', () => {
    const pdf = historyPdf({title: 'No data', range: 'Today', route: [], alerts: []});
    assert.match(pdf, /No records in this period/);
    assert.ok(!pdf.includes('NaN'));
});
