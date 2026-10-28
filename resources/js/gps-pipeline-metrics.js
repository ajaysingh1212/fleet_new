// Bounded, local-only diagnostics. No coordinates, credentials or measurements
// are transmitted. Server-to-browser timings assume synchronized clocks.
const samples = [], seen = new Set();
export function browserReceived(p) {
    if (!p.trace_id || !Number.isFinite(Date.parse(p.received_at))) return p;
    return {...p, _gpsTiming: {at: Date.now(), tick: performance.now(), trace: p.trace_id}};
}
export function markerDrawn(p) {
    const timing = p?._gpsTiming;
    if (!timing || timing.trace !== p.trace_id || seen.has(timing.trace)) return;
    seen.add(timing.trace);
    if (seen.size > 2000) seen.delete(seen.values().next().value);
    const sample = {trace_id: timing.trace, device_id: p.device_id, browser_received_at: timing.at,
        marker_at: Date.now(), server_received_at: Date.parse(p.received_at),
        event_to_marker_ms: performance.now() - timing.tick, queue_delay_ms: p.queue_delay_ms ?? null};
    sample.server_to_browser_ms = sample.browser_received_at - sample.server_received_at;
    sample.server_to_marker_ms = sample.marker_at - sample.server_received_at;
    samples.push(sample); if (samples.length > 1000) samples.shift();
    globalThis.window?.dispatchEvent(new CustomEvent('gps:marker-updated', {detail: sample}));
}
if (globalThis.window) window.__gpsPipelineMetrics = {samples, clocksMustBeSynchronized: true};
