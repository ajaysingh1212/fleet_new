@extends('layouts.app')
@section('page-title', 'Live GPS Device Diagnostics')

@section('content')
{{-- ============================================================
     Live GPS Diagnostics Console
     - Sirf authorized admin ke liye, transient telemetry viewer
     - Raw packet / ACK data kabhi SQL, browser storage ya logs me save nahi hota
     ============================================================ --}}
<div id="gps-live-diagnostics"
     class="gld"
     data-url="{{ route('admin.gps-devices.diagnostics.live') }}"
     data-stream-url="{{ route('admin.gps-devices.diagnostics.stream') }}"
     data-buffer="{{ config('gps.live_diagnostic_buffer') }}">

  {{-- ===== Header ===== --}}
  <header class="gld-header">
    <div class="gld-title">
      <span class="gld-pulse" data-pulse aria-hidden="true"></span>
      <div>
        <h1 class="gld-h1">Live Device Registry &amp; Diagnostics</h1>
        <p class="gld-sub">
          Short-lived, authorized telemetry viewer. Raw packet aur ACK data
          SQL, browser storage ya logs me <strong>kabhi store nahi hota</strong>.
        </p>
      </div>
    </div>
    <div class="gld-actions">
      <span class="gld-badge gld-badge--idle" data-connection role="status" aria-live="polite">Connecting…</span>
      <button type="button" class="gld-btn" data-pause aria-pressed="false">⏸ Pause</button>
      <button type="button" class="gld-btn" data-clear>🧹 Clear</button>
    </div>
  </header>

  {{-- ===== KPI strip ===== --}}
  <section class="gld-kpis" aria-label="Fleet summary">
    <div class="gld-kpi"><span class="gld-kpi-label">Total devices</span><strong class="gld-kpi-value" data-kpi="total">—</strong></div>
    <div class="gld-kpi gld-kpi--ok"><span class="gld-kpi-label">Abhi online</span><strong class="gld-kpi-value" data-kpi="online">—</strong></div>
    <div class="gld-kpi gld-kpi--info"><span class="gld-kpi-label">GPS fix mila</span><strong class="gld-kpi-value" data-kpi="fix">—</strong></div>
    <div class="gld-kpi gld-kpi--warn"><span class="gld-kpi-label">Power connected</span><strong class="gld-kpi-value" data-kpi="power">—</strong></div>
  </section>

  {{-- ===== Toolbar ===== --}}
  <section class="gld-toolbar">
    <div class="gld-field">
      <label for="gps-diagnostic-device">Registered device</label>
      <select id="gps-diagnostic-device" class="form-select form-select-sm">
        <option value="">Saare registered devices</option>
      </select>
    </div>
    <div class="gld-health" data-health aria-live="polite">
      <span class="gld-chip gld-chip--muted">Runtime health check ho raha hai…</span>
    </div>
  </section>

  {{-- ===== Device table ===== --}}
  <section class="gld-panel">
    <div class="table-responsive">
      <table class="table gld-table align-middle mb-0">
        <thead>
          <tr>
            <th>Device / Assignment</th>
            <th>Protocol / Model</th>
            <th>Communication</th>
            <th>Telemetry</th>
            <th>GPS / Power</th>
            <th>Last packet</th>
          </tr>
        </thead>
        <tbody data-rows>
          <tr><td colspan="6" class="gld-empty">Current state ka wait ho raha hai…</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  {{-- ===== Packet console ===== --}}
  <section class="gld-panel gld-console-wrap">
    <div class="gld-console-head">
      <div>
        <h2 class="gld-h2">Live packet lifecycle</h2>
        <small class="gld-sub">Exact transient raw HEX aur ACK bytes · administrator access zaroori hai</small>
      </div>
      <div class="gld-console-tools">
        <input type="search" class="form-control form-control-sm" placeholder="Filter: event, IMEI, hex…" data-filter aria-label="Packet filter">
        <label class="gld-check"><input type="checkbox" data-autoscroll checked> Auto-scroll</label>
        <span class="gld-count" data-count>0 events</span>
      </div>
    </div>
    <div class="gld-console" data-events tabindex="0" aria-live="off">
      <div class="gld-empty">Packets ka wait ho raha hai…</div>
    </div>
  </section>
</div>

<style>
  #gps-live-diagnostics{
    --gld-bg:#f6f8fb;--gld-card:#fff;--gld-line:#e3e8ef;--gld-text:#1c2536;--gld-muted:#6b778c;
    --gld-ok:#16a34a;--gld-warn:#d97706;--gld-bad:#dc2626;--gld-info:#2563eb;--gld-accent:#4f46e5;
    --gld-console:#0b1220;--gld-console-text:#d6e2ff;
    color:var(--gld-text);font-size:.9rem
  }
  #gps-live-diagnostics .gld-header{display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;align-items:flex-start;padding:1.1rem 1.25rem;border-radius:14px;background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#4338ca 100%);color:#fff;margin-bottom:1rem;box-shadow:0 8px 24px -12px rgba(49,46,129,.6)}
  #gps-live-diagnostics .gld-title{display:flex;gap:.85rem;align-items:flex-start}
  #gps-live-diagnostics .gld-h1{font-size:1.15rem;font-weight:700;margin:0 0 .15rem;letter-spacing:.2px}
  #gps-live-diagnostics .gld-header .gld-sub{color:rgba(255,255,255,.75);margin:0;max-width:62ch}
  #gps-live-diagnostics .gld-sub{color:var(--gld-muted);margin:0}
  #gps-live-diagnostics .gld-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
  #gps-live-diagnostics .gld-btn{border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.1);color:#fff;border-radius:8px;padding:.3rem .75rem;font-size:.8rem;transition:.15s}
  #gps-live-diagnostics .gld-btn:hover{background:rgba(255,255,255,.22)}
  #gps-live-diagnostics .gld-btn[aria-pressed="true"]{background:#fbbf24;border-color:#fbbf24;color:#1c1917}
  #gps-live-diagnostics .gld-badge{padding:.3rem .7rem;border-radius:999px;font-size:.75rem;font-weight:600}
  #gps-live-diagnostics .gld-badge--idle{background:#e5e7eb;color:#374151}
  #gps-live-diagnostics .gld-badge--ok{background:#dcfce7;color:#166534}
  #gps-live-diagnostics .gld-badge--warn{background:#fef3c7;color:#92400e}
  #gps-live-diagnostics .gld-pulse{width:12px;height:12px;border-radius:50%;background:#9ca3af;margin-top:.45rem;flex:none}
  #gps-live-diagnostics .gld-pulse.is-live{background:#4ade80;animation:gld-pulse 1.6s infinite}
  @keyframes gld-pulse{0%{box-shadow:0 0 0 0 rgba(74,222,128,.7)}70%{box-shadow:0 0 0 10px rgba(74,222,128,0)}100%{box-shadow:0 0 0 0 rgba(74,222,128,0)}}

  #gps-live-diagnostics .gld-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin-bottom:1rem}
  #gps-live-diagnostics .gld-kpi{background:var(--gld-card);border:1px solid var(--gld-line);border-left:4px solid var(--gld-accent);border-radius:12px;padding:.7rem .9rem;display:flex;flex-direction:column}
  #gps-live-diagnostics .gld-kpi--ok{border-left-color:var(--gld-ok)}
  #gps-live-diagnostics .gld-kpi--info{border-left-color:var(--gld-info)}
  #gps-live-diagnostics .gld-kpi--warn{border-left-color:var(--gld-warn)}
  #gps-live-diagnostics .gld-kpi-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.6px;color:var(--gld-muted)}
  #gps-live-diagnostics .gld-kpi-value{font-size:1.5rem;line-height:1.2;font-variant-numeric:tabular-nums}

  #gps-live-diagnostics .gld-toolbar{display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;margin-bottom:1rem}
  #gps-live-diagnostics .gld-field{min-width:260px}
  #gps-live-diagnostics .gld-field label{display:block;font-size:.75rem;font-weight:600;color:var(--gld-muted);margin-bottom:.25rem;text-transform:uppercase;letter-spacing:.5px}
  #gps-live-diagnostics .gld-health{display:flex;flex-wrap:wrap;gap:.4rem;flex:1}
  #gps-live-diagnostics .gld-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;font-weight:500;border:1px solid var(--gld-line);background:var(--gld-card)}
  #gps-live-diagnostics .gld-chip::before{content:"";width:7px;height:7px;border-radius:50%;background:#9ca3af}
  #gps-live-diagnostics .gld-chip--ok::before{background:var(--gld-ok)}
  #gps-live-diagnostics .gld-chip--warn::before{background:var(--gld-warn)}
  #gps-live-diagnostics .gld-chip--bad::before{background:var(--gld-bad)}
  #gps-live-diagnostics .gld-chip--muted{color:var(--gld-muted)}

  #gps-live-diagnostics .gld-panel{background:var(--gld-card);border:1px solid var(--gld-line);border-radius:14px;overflow:hidden;margin-bottom:1rem}
  #gps-live-diagnostics .gld-table thead th{background:var(--gld-bg);font-size:.7rem;text-transform:uppercase;letter-spacing:.6px;color:var(--gld-muted);border-bottom:1px solid var(--gld-line);white-space:nowrap}
  #gps-live-diagnostics .gld-table td{padding:.7rem .75rem;vertical-align:top}
  #gps-live-diagnostics .gld-table tbody tr:hover{background:#fafbff}
  #gps-live-diagnostics .gld-name{font-weight:600}
  #gps-live-diagnostics .gld-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.78rem;color:var(--gld-muted)}
  #gps-live-diagnostics .gld-empty{padding:1.2rem;text-align:center;color:var(--gld-muted)}
  #gps-live-diagnostics .gld-pill{display:inline-block;padding:.1rem .55rem;border-radius:999px;font-size:.72rem;font-weight:600}
  #gps-live-diagnostics .gld-pill--ok{background:#dcfce7;color:#166534}
  #gps-live-diagnostics .gld-pill--bad{background:#fee2e2;color:#991b1b}
  #gps-live-diagnostics .gld-pill--warn{background:#fef3c7;color:#92400e}
  #gps-live-diagnostics .gld-pill--info{background:#dbeafe;color:#1e40af}
  #gps-live-diagnostics .gld-pill--muted{background:#e5e7eb;color:#374151}

  #gps-live-diagnostics .gld-console-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.75rem;align-items:center;padding:.85rem 1rem;border-bottom:1px solid var(--gld-line)}
  #gps-live-diagnostics .gld-h2{font-size:1rem;font-weight:700;margin:0}
  #gps-live-diagnostics .gld-console-tools{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center}
  #gps-live-diagnostics .gld-console-tools input[type=search]{width:240px}
  #gps-live-diagnostics .gld-check{display:flex;align-items:center;gap:.35rem;margin:0;font-size:.8rem;color:var(--gld-muted)}
  #gps-live-diagnostics .gld-count{font-size:.75rem;color:var(--gld-muted);font-variant-numeric:tabular-nums}
  #gps-live-diagnostics .gld-console{height:360px;overflow:auto;background:var(--gld-console);color:var(--gld-console-text);padding:.5rem .75rem;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.78rem}
  #gps-live-diagnostics .gld-console .gld-empty{color:#7c8aa8}
  #gps-live-diagnostics .gld-event{border-bottom:1px dashed rgba(255,255,255,.1);padding:.5rem 0}
  #gps-live-diagnostics .gld-event[hidden]{display:none}
  #gps-live-diagnostics .gld-event-head{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.3rem}
  #gps-live-diagnostics .gld-tag{padding:.05rem .5rem;border-radius:6px;font-weight:700;font-size:.7rem;text-transform:uppercase;letter-spacing:.4px}
  #gps-live-diagnostics .gld-tag--ok{background:rgba(74,222,128,.18);color:#86efac}
  #gps-live-diagnostics .gld-tag--info{background:rgba(96,165,250,.18);color:#93c5fd}
  #gps-live-diagnostics .gld-tag--warn{background:rgba(251,191,36,.18);color:#fcd34d}
  #gps-live-diagnostics .gld-tag--bad{background:rgba(248,113,113,.2);color:#fca5a5}
  #gps-live-diagnostics .gld-tag--muted{background:rgba(148,163,184,.2);color:#cbd5e1}
  #gps-live-diagnostics .gld-event-meta{color:#7c8aa8}
  #gps-live-diagnostics .gld-event pre{margin:.2rem 0 .4rem;color:var(--gld-console-text);white-space:pre-wrap;word-break:break-all;background:rgba(255,255,255,.04);padding:.5rem;border-radius:8px}
  #gps-live-diagnostics .gld-copy{border:1px solid rgba(255,255,255,.25);background:transparent;color:#cbd5e1;border-radius:6px;font-size:.7rem;padding:.1rem .5rem;margin-right:.35rem}
  #gps-live-diagnostics .gld-copy:hover{background:rgba(255,255,255,.12)}
  @media (max-width:576px){#gps-live-diagnostics .gld-console-tools input[type=search]{width:100%}}
</style>

<script>
(() => {
  const root = document.getElementById('gps-live-diagnostics');
  if (!root) return;

  /* ---------- DOM refs ---------- */
  const $ = s => root.querySelector(s);
  const sel = $('#gps-diagnostic-device'), rows = $('[data-rows]'), health = $('[data-health]'),
        badge = $('[data-connection]'), pulse = $('[data-pulse]'), box = $('[data-events]'),
        auto = $('[data-autoscroll]'), pauseBtn = $('[data-pause]'), filterInput = $('[data-filter]'),
        countEl = $('[data-count]');

  /* ---------- State (sirf memory me, koi storage nahi) ---------- */
  const MAX = Math.max(100, Math.min(1000, Number(root.dataset.buffer) || 500));
  let stopped = false, paused = false, last = null, gen = 0, total = 0;
  let stateTimer = null, streamTimer = null;
  const known = new Map();

  /* ---------- Helpers ---------- */
  const esc = v => String(v ?? '—').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const fmt = v => v ? new Date(v).toLocaleString() : 'Kabhi nahi';
  const ago = v => {
    if (!v) return 'Kabhi nahi';
    const s = Math.max(0, Math.round((Date.now() - Date.parse(v)) / 1000));
    if (s < 60) return `${s}s pehle`;
    if (s < 3600) return `${Math.floor(s / 60)}m pehle`;
    if (s < 86400) return `${Math.floor(s / 3600)}h pehle`;
    return `${Math.floor(s / 86400)}d pehle`;
  };
  const pill = (text, tone) => `<span class="gld-pill gld-pill--${tone}">${esc(text)}</span>`;
  const chip = (text, tone) => `<span class="gld-chip gld-chip--${tone}">${esc(text)}</span>`;
  const setKpi = (k, v) => { const n = $(`[data-kpi="${k}"]`); if (n) n.textContent = v; };
  const setBadge = (text, tone) => {
    badge.textContent = text;
    badge.className = `gld-badge gld-badge--${tone}`;
    pulse.classList.toggle('is-live', tone === 'ok');
  };
  const tone = name => {
    const n = String(name || '').toLowerCase();
    if (/(error|fail|reject|invalid|crc|timeout)/.test(n)) return 'bad';
    if (/(disconnect|close|drop)/.test(n)) return 'warn';
    if (/(ack|reply|response)/.test(n)) return 'info';
    if (/(connect|login|accept|valid)/.test(n)) return 'ok';
    return 'muted';
  };
  const healthTone = s => {
    const v = String(s ?? '').toLowerCase();
    if (['ok', 'up', 'running', 'connected', 'healthy'].includes(v)) return 'ok';
    if (v === 'not_required') return 'muted';
    if (['down', 'error', 'failed', 'unreachable'].includes(v)) return 'bad';
    return 'warn';
  };

  /* ---------- Packet console ---------- */
  const updateCount = () => { countEl.textContent = `${box.querySelectorAll('.gld-event').length} / ${MAX} events`; };

  const applyFilter = () => {
    const q = filterInput.value.trim().toLowerCase();
    box.querySelectorAll('.gld-event').forEach(n => { n.hidden = q !== '' && !n.dataset.search.includes(q); });
  };

  const resetConsole = () => {
    box.replaceChildren();
    const d = document.createElement('div');
    d.className = 'gld-empty';
    d.textContent = 'Packets ka wait ho raha hai…';
    box.append(d);
    updateCount();
  };

  const buildEvent = x => {
    const n = document.createElement('article');
    n.className = 'gld-event';
    n.dataset.search = (`${x.event} ${x.imei ?? ''} ${JSON.stringify(x.fields ?? {})}`).toLowerCase();

    const head = document.createElement('div');
    head.className = 'gld-event-head';
    const tag = document.createElement('span');
    tag.className = `gld-tag gld-tag--${tone(x.event)}`;
    tag.textContent = x.event ?? 'event';
    const meta = document.createElement('span');
    meta.className = 'gld-event-meta';
    meta.textContent = `${fmt(x.at)} · ${x.imei ?? 'connection'}`;
    head.append(tag, meta);

    const pre = document.createElement('pre');
    pre.textContent = JSON.stringify(x.fields, null, 2);
    n.append(head, pre);

    // Location packets carry decoded fixes separately from the packet events.
    // Surface measured coordinates in the console while keeping raw JSON too.
    const locations = x.fields?.decoded?.locations;
    if (Array.isArray(locations) && locations.length) {
      const fixes = document.createElement('div');
      fixes.className = 'gld-event-meta';
      fixes.textContent = locations.map(location => {
        const lat = Number(location.latitude), lon = Number(location.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return 'Coordinates unavailable';
        return `GPS ${lat.toFixed(6)}, ${lon.toFixed(6)}${location.gps_fix === false ? ' (NO GPS FIX)' : ''}`;
      }).join(' · ');
      n.insertBefore(fixes, pre);
    }

    ['raw_hex', 'ack_hex'].forEach(k => {
      if (!x.fields?.[k]) return;
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'gld-copy';
      b.textContent = `Copy ${k}`;
      b.onclick = async () => {
        try { await navigator.clipboard?.writeText(x.fields[k]); b.textContent = 'Copy ho gaya ✓'; }
        catch { b.textContent = 'Copy fail'; }
        setTimeout(() => { b.textContent = `Copy ${k}`; }, 1400);
      };
      n.append(b);
    });
    return n;
  };

  const addEvents = list => {
    if (!list.length) return;
    box.querySelector(':scope > .gld-empty')?.remove();
    const frag = document.createDocumentFragment();
    list.forEach(x => frag.append(buildEvent(x)));
    box.append(frag);
    const items = box.querySelectorAll('.gld-event');
    for (let i = 0; i < items.length - MAX; i++) items[i].remove();
    applyFilter();
    updateCount();
    if (auto.checked) box.scrollTop = box.scrollHeight;
  };

  /* ---------- Device snapshot ---------- */
  const deviceRow = x => {
    const name = x.registration ?? x.name;
    const tcp = x.connection_connected === true ? pill('TCP connected', 'ok')
              : x.connection_connected === false ? pill('Reconnecting', 'warn') : pill('TCP unknown', 'muted');
    const acc = x.acc === true ? pill('ACC ON', 'ok') : x.acc === false ? pill('ACC OFF', 'muted') : pill('ACC unknown', 'muted');
    const power = x.charging_current === true ? pill('Charging', 'ok')
                : x.external_power_current === true ? pill('External power', 'info')
                : x.charging === true ? pill(`Last charging: ${fmt(x.power_reported_at)}`, 'warn')
                : pill('Power not reported', 'muted');
    const pos = x.latitude != null
      ? `<span class="gld-mono" style="color:inherit">${Number(x.latitude).toFixed(6)}, ${Number(x.longitude).toFixed(6)}</span>`
      : '<span class="text-muted">Valid fix nahi mila</span>';

    return `<tr>
      <td><div class="gld-name">${esc(name)}</div><div class="gld-mono">${esc(x.imei)}</div></td>
      <td>${esc(x.configured_protocol)} ${x.detected_protocol ? pill('Packet validated', 'ok') : pill('Configured', 'muted')}
          <div class="gld-mono mt-1">Model: ${esc(x.configured_model ?? 'Unknown / unverified')}</div></td>
      <td>${x.online ? pill('Online', 'ok') : pill('Offline', 'bad')}<div class="mt-1">${tcp}</div></td>
      <td><div>${esc(x.state)}</div><div class="mt-1">${acc} ${pill(`GPS: ${x.gps_state ?? '—'}`, 'info')}</div></td>
      <td>${pos}<div class="mt-1">${power}</div></td>
      <td><div>${esc(ago(x.last_packet_at))}</div><div class="gld-mono">${esc(fmt(x.last_packet_at))}</div></td>
    </tr>`;
  };

  const renderHealth = h => {
    const listenerOk = h.receiver?.updated_at && Date.now() - Date.parse(h.receiver.updated_at) < 15000;
    const lat = o => (o?.latency_ms != null ? ` · ${o.latency_ms}ms` : '');
    const parts = [
      chip(`TCP listener: ${listenerOk ? 'running' : 'stale'}`, listenerOk ? 'ok' : 'bad'),
      chip(`Database: ${h.database?.status}${lat(h.database)}`, healthTone(h.database?.status)),
      chip(`Redis: ${h.redis?.status === 'not_required' ? 'not required (local mode)' : `${h.redis?.status}${lat(h.redis)}`}`, healthTone(h.redis?.status)),
      chip(`Reverb: ${h.reverb?.status}`, healthTone(h.reverb?.status)),
      chip(`Failed GPS jobs: ${h.failed_gps_jobs ?? 'unknown'}`, Number(h.failed_gps_jobs) > 0 ? 'bad' : 'ok'),
      ...(h.queues ?? []).map(q => chip(`${q.name}: ${q.available ? `${q.depth} queued` : 'unknown'}`, q.available ? 'ok' : 'warn')),
    ];
    health.innerHTML = parts.join('');
  };

  const state = async () => {
    try {
      const u = new URL(root.dataset.url, location.origin);
      if (sel.value) u.searchParams.set('device_id', sel.value);
      const res = await fetch(u, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!res.ok) throw new Error(res.status);
      const d = await res.json();

      d.devices.forEach(x => {
        if (x.device_id && !known.has(x.device_id)) {
          known.set(x.device_id, x);
          const o = document.createElement('option');
          o.value = x.device_id;
          o.textContent = `${x.registration ?? x.name} · ${x.imei}`;
          sel.append(o);
        }
      });

      rows.innerHTML = d.devices.length ? d.devices.map(deviceRow).join('')
        : '<tr><td colspan="6" class="gld-empty">Koi matching device nahi mila.</td></tr>';

      setKpi('total', d.devices.length);
      setKpi('online', d.devices.filter(x => x.online).length);
      setKpi('fix', d.devices.filter(x => x.latitude != null).length);
      setKpi('power', d.devices.filter(x => x.charging_current === true || x.external_power_current === true).length);

      renderHealth(d.health);
      setBadge('Live snapshot connected', 'ok');
    } catch {
      setBadge('Reconnecting…', 'warn');
    } finally {
      if (!stopped) stateTimer = setTimeout(state, 5000);
    }
  };

  /* ---------- Live stream (pause ke baad resume bhi sahi chalta hai) ---------- */
  const stream = async () => {
    if (stopped) return;
    const g = gen;
    if (!paused) {
      try {
        const u = new URL(root.dataset.streamUrl, location.origin);
        if (sel.value) u.searchParams.set('device_id', sel.value);
        if (last) u.searchParams.set('after', last);
        const res = await fetch(u, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (res.ok) {
          const d = await res.json();
          if (g !== gen) return; // device change ho gaya, purana response ignore
          if (d.events?.length) {
            last = d.events.at(-1).id;
            addEvents(d.events);
          }
        }
      } catch { /* agli iteration me retry */ }
    }
    if (g === gen && !stopped) streamTimer = setTimeout(stream, 1000);
  };

  /* ---------- Controls ---------- */
  sel.onchange = () => {
    gen++; last = null;
    clearTimeout(stateTimer); clearTimeout(streamTimer);
    resetConsole();
    state(); stream();
  };

  pauseBtn.onclick = () => {
    paused = !paused;
    pauseBtn.textContent = paused ? '▶ Resume' : '⏸ Pause';
    pauseBtn.setAttribute('aria-pressed', String(paused));
  };

  $('[data-clear]').onclick = () => resetConsole(); // cursor same rehta hai, purane events wapas nahi aate
  filterInput.oninput = applyFilter;

  addEventListener('pagehide', () => {
    stopped = true;
    clearTimeout(stateTimer); clearTimeout(streamTimer);
  }, { once: true });

  resetConsole();
  state();
  stream();
})();
</script>
@endsection
