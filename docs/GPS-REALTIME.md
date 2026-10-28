# Realtime fleet map

The map uses WebSockets for immediate updates and reconciles its bounded page
every two seconds to recover dropped broadcasts even while the socket remains
connected. Reconciliation never overlaps an in-flight request, retains newer
live telemetry, and stops when the page is disposed. It also works when Echo
is unavailable. Initial HTML contains
at most 250 matching devices. The authenticated fleet endpoint uses a bounded
ID cursor (`after`) and the same search/protocol/status filters as the table.
Next/Previous replaces the loaded map page. Untracked vehicles can fill spare
slots on the first page. Registered/pending totals are fleet-wide; counters
labelled Loaded describe only the current map page.

Each loaded device subscribes to `private-admin.gps.hardware.device.{id}` over
one Echo/Reverb connection. Channel authorization requires the existing GPS
administrator policy and an existing device. Browsers receive only subscribed
devices, not the global fleet's traffic. Heartbeat, login and location changes
all publish; a parked device does not need a fresh position to update its status.

The default live-first path is:

1. Decode and authorize the packet, then durably enqueue its normalized input
   before sending the tracker ACK. This is an input journal, not a GPS history write.
2. The live worker rechecks the registration, updates a small current-state cache
   and schedules a separate, per-device unique broadcast on `gps-broadcasts`.
   Broadcast delivery reads the latest authorized cache state, coalescing obsolete
   queued updates. Slow Reverb requests cannot block position projection. Warm live updates
   do not read location/history tables. The initial cache seed may read the last
   stored state; administrative authorization is still checked in SQL.
3. After scheduling the broadcast, enqueue the original packet on `gps-persist`.
   A separate worker writes deduplicated location/event history and does not
   broadcast it again. Browser rendering is never a storage prerequisite.

`HardwarePacketLive` is queued on `gps-broadcasts`, outside both the TCP
read loop and live projection worker. Reverb calls have a bounded timeout. If Reverb or the
live cache is unavailable, history is still queued. If history enqueue fails,
the durable input job retries; SQL uniqueness guards repeated deliveries.
Failed jobs must be monitored/replayed if retries are exhausted.

Broadcast scheduling precedes scheduling the history write. This does not guarantee
that every browser physically paints before SQL commits: browsers/network links
can be slow or closed. No browser acknowledgement gates storage. This separation
removes GPS history write/read latency from the map's live update path while
retaining history when no map is open.

Reconnect snapshots merge SQL with the live cache while storage catches up.
The browser retains newer live receipt/GPS/ACC timestamps even if a later HTTP
response contains older persisted state, coalesces incoming updates per device
per animation frame, and patches existing rows/markers. It does not replace the
roster, recenter the map or rebuild icons for unchanged state. A one-second local
clock expires freshness labels without making network requests. Successful
subscriptions/reconnections reconcile one snapshot to cover delivery gaps.
Sync now is an additional explicit recovery action; socket failures show a
connection warning while bounded reconciliation continues.

## Parking versus GPS

Availability uses the last authenticated packet (180 seconds by default),
independently of receiver-owned TCP presence. A TCP close updates socket state
immediately but does not instantly mark a recently reporting device Offline.
Multiple sessions are tracked independently; an old close cannot disconnect a
newer session. An open socket does not extend packet freshness indefinitely.
GPS age remains separate: an online tracker may upload old GPS history.

## Local Windows operation

Run `tools/start-gps-realtime.ps1` (also called by `start-gps-map.ps1`). After code
changes use `tools/start-gps-realtime.ps1 -Restart`: it verifies saved PIDs and
start times, restarts only workspace-managed PHP services, and archives their
previous logs before starting replacements. It starts:

- Reverb on `127.0.0.1:8080` using the existing application credentials.
- A low-latency database input worker for `gps-live` (100 ms idle wait).
- An independent database history worker for `gps-persist`.
- A dedicated `gps-broadcasts` worker for current-state publication.
- The registered PT06 diagnostic receiver on TCP 9000 when that port is free.

The script records its process IDs/start times and avoids starting duplicate
managed services. It keeps existing port listeners; it does not kill someone
else's process. Restart existing receiver/worker processes after code changes.
Logs are `storage/logs/gps-{reverb,broadcast-worker,receiver}.log` and matching
`-error.log` files. Services are background processes, not Windows services;
run the script again after a PC restart. It does not change router/firewall rules.

`GPS_LIVE_FIRST=false` selects the legacy SQL-first path.
`GPS_LOCAL_BROADCAST=false` explicitly disables broadcasts for local diagnostic
persistence. Observation-only diagnostics still never store or broadcast.
`GPS_LIVE_CONNECTION` selects the local durable input/history connection.
`GPS_LIVE_CACHE_STORE` selects the current-state cache (file locally; use a shared
Redis store such as `gps-redis` on production nodes).
`GPS_BROADCAST_CONNECTION` selects the legacy broadcast queue connection (defaults to
`QUEUE_CONNECTION`, currently database locally). The production Redis ingestion
queue remains separate; changing it is not required for local WebSockets.

## 100,000-device deployment boundary

Bounding the browser and restricting subscriptions removes the full-fleet
polling bottleneck. It does **not** certify this PC or a single listener for
100,000 simultaneous GPS connections. The diagnostic receiver is a bounded
local troubleshooting process with registration/queue SQL and a 32-client limit. The
existing production gateway is Redis-backed and has a 256-client bound per
process; Windows/stream-select also impose practical descriptor limits.

A deployment at that size needs sharded ingestion nodes with tested connection
limits, dedicated Redis/cache and ingestion/broadcast workers, indexed/partitioned
history retention, supervised process recovery, monitoring of queue lag and
memory, and load tests using representative GPS packet rates and dashboard users.
Use a clustered overview/read model for fleet-wide movement totals rather than
loading every marker into one browser. Reverb can be scaled across nodes with
central Redis behind a WebSocket load balancer; use TLS and suitable event-loop
and OS connection limits. See the [official Reverb production guide](https://laravel.com/docs/12.x/reverb#running-reverb-in-production).

## Verification

PHP tests exercise bounded/filtered cursor snapshots, private channel permission
checks, heartbeat broadcasts without coordinates, parked/stale/offline transitions,
and out-of-order heartbeat handling. Live-first tests additionally verify live
payloads before history exists, cache-backed reconnects, no warm history queries,
revocation checks, transport/cache failure isolation and retryable history enqueue. Node tests exercise subscriptions, reconnect
reconciliation, recovery of missed broadcasts without overlapping requests, old-page response rejection, cleanup,
movement/freshness and marker interpolation. The frontend is built with Vite.
The local queue-to-Reverb-to-authenticated-private-WebSocket path was also checked
using the real state of IMEI 865167041931701; no simulated location was inserted.

A real-device check received a private WebSocket event at 2026-10-23 13:16:38 IST
while SQL last_packet_at was still 13:15:33 IST. Starting the separate persistence
worker then stored the queued history. No synthetic location was inserted.

GPS freshness now uses `GPS_FRESH_SECONDS` (15 seconds by default), independently
of the 180-second communication timeout. A 139-second-old location is Delayed,
not Fresh, even while heartbeat traffic keeps the device Online. The popup shows
location receipt time and GPS age on arrival to distinguish upstream backlog
from browser delivery. Freshness labels update once a second locally.

## Adaptive local workers and connection diagnostics

`tools/start-gps-realtime.ps1` now starts `php artisan gps:workers` instead of
three fixed workers. Each queue keeps one warm worker; the supervisor checks
backlog every two seconds and adds up to four workers per queue (twenty queued
jobs per worker, or a second worker if more than one job has waited two seconds).
Burst workers stop when empty. Normal workers retire between jobs after five
minutes or 500 jobs and are replaced. History remains deduplicated; per-device
live-cache clocks and locks reject out-of-order updates. No extra GPS fixes or
remote device commands are created by autoscaling.

Use `php artisan gps:workers --once` to inspect demand without spawning processes.
`php tools/gps-runtime-status.php <IMEI>` reports queue age, failed GPS job count,
receiver state, processing delay, and worker pool status without credentials.
The running pool status is in `storage/framework/gps-workers-status.json`.
Use `tools/start-gps-realtime.ps1 -Restart -KeepReverb` to reload receiver/workers
without interrupting the shared WebSocket server. `-WorkersOnly -Restart` keeps
the TCP receiver running as well.

Receiver presence records authenticated receive time separately from queued
history, so a persistence backlog cannot make an active receiver look offline.
A confirmed closed socket has `connection_connected=false`. Availability
remains based on the last authenticated packet; TCP reconnecting is separate.
`GPS_SOCKET_IDLE_SECONDS=0` disables authenticated socket idle closure;
unauthenticated login still has a deadline. Freshness is based on device
GPS time, never on job completion time. `Device delivery delay` and
`Server queue delay` distinguish cellular/device buffering from server backlog.
TCP listeners enable `tcp_nodelay`, and ACKs are sent in the same read-loop pass.

Observed on 28 October 2026 for the reported tracker: no queued or failed GPS
jobs, live jobs around 12–30 ms, repeated `peer_closed`, and retransmitted GPS
fixes several minutes older than receipt. Old incoming fixes and peer closure
were observed without a queue backlog. EOF alone does not establish why the
peer closed or rule out an ACK/protocol issue.

References: [Laravel workers and job timeouts](https://laravel.com/docs/12.x/queues),
[PHP TCP stream context options](https://www.php.net/manual/en/context.socket.php).


## Measured audit and operations

See [the measured audit](GPS-REALTIME-AUDIT-2026-10-28.md) for fixes, latency
distributions, limitations, deployment and rollback. Run `php artisan gps:health
--imei=<15-digit-imei> --probe-broadcast` for receiver heartbeat, bounded device
state, queues, worker pool and signed publish acceptance. Browser samples stay
local at `window.__gpsPipelineMetrics`. Pipeline phase logs contain metadata,
not raw packets or secrets. `GPS_TRACE_ENABLED=false` disables phase logs.
`php artisan gps:workers --stop` lets active GPS workers finish safely.
Windows PHP here has no PCNTL; hard job interruption needs a suitable production
runtime. Linux service examples are under `deploy/gps/`.
