# Registered hardware fleet

Run only this additive, repeatable seeder (no migrations or database reset):

```powershell
php artisan db:seed --class=HardwareFleetSeeder --force
```

| Vehicle | Type | IMEI | Driver | Provider |
|---|---|---|---|---|
| BR52C6695 | bike | 864540081540460 | Ajay Kumar | Airtel |
| BR28Z4291 | car | 861128068689493 | Ajay Kumar | Airtel |

BT50 is the first device's user-reported model. The second tracker is identified
as PT06-compatible from its packets; its manufacturer/exact commercial model and
SIM telephone number were not supplied. Unknown details remain empty. Existing
extra details/documents and newer live positions survive reseeding. Conflicting
tracker assignments are rejected, not silently displaced.

The car's initial position is the actual user-provided 22 packet captured at
2026-10-17 12:13:11 IST, recorded 12:12:47 IST: 25.6096500, 85.1478667.
Its original times and source are retained; it is not seeded as a fresh position.
No coordinates were supplied for the bike, so it appears in the fleet roster
without an invented marker until its first valid fix.

## Local live tracking

Stop the old port-9000 listener and run:

```powershell
php artisan gps:listen --diagnostic --persist --registered --diagnostic-protocol=pt06 --format=pretty
```

`--registered` accepts newly registered active trackers without changing the
command or restarting. Unknown and inactive identifiers receive no ACK.
The explicit `--persist` mode checks each device's active registration, protocol
and version in SQL, then journals the packet before ACK. With live-first mode,
the live worker publishes decoded state before the separate history worker saves
packet events, communication times and valid positions. It uses no Redis locally; queued WebSocket events update
the map through Reverb and a dedicated broadcast worker (see GPS-REALTIME.md). SQL errors cause disconnect
without ACK. It is a bounded local troubleshooting mode, with registration and queue SQL in
its loop; production deployments should continue using the Redis gateway/worker.
With the default configuration, diagnostics without `--persist` remain observation-only.

For a local terminal that must always update the map, set
`GPS_DIAGNOSTIC_PERSIST=true` in the local `.env` and restart the listener.
This applies to `gps:listen --diagnostic`, including restarts without `--persist`.
Only active registrations with matching protocol/version can be stored.
Use `--observe-only` to explicitly disable storage for a diagnostic session.
The default setting remains false; `gps:diagnose` itself remains observation-only
unless passed `--persist`. Database storage does not enable file logging.

`tools/start-gps-live.ps1` uses this storage mode and mirrors output to
`storage/logs/gps-live-diagnostic.log`. `php tools/gps-fleet-status.php` provides
a read-only snapshot of the configured application database.

## Admin map

Open `/admin/gps-devices`. The map loads at most 250 matching devices,
independent of the table's 25-row pagination. Search/protocol/status filters
apply to both. Next/Previous navigates the bounded map pages; Sync now discovers
registration changes. The authenticated endpoint and private per-device Reverb
channels return only display telemetry, excluding SIM details and configuration.
See [GPS-REALTIME.md](GPS-REALTIME.md) for startup, delivery and scaling details.

Bike and car markers show registration, driver, coordinates and fix time. The
map does not reload or recenter on every packet. Device changes arrive through WebSockets; there is no periodic fleet polling.
Markers use frame-based Hermite interpolation with 0.4–1.6 second catch-up,
retaining their position and compatible velocity when a newer fix arrives.
There is no playback queue or extrapolation beyond the latest measured fix.
Unchanged reports do not rebuild marker icons, and popup updates do not pan the map.
Old retransmissions cannot rewind a marker. Reassignment clears old
vehicle markers and never borrows an earlier vehicle's fix.

States: moving (>1.8 km/h); idle (stationary with ACC on); parked (ACC off);
stopped (ACC unknown); unknown speed; awaiting GPS; no GPS fix; stale GPS; offline;
inactive/maintenance/blocked; unassigned vehicle. A heartbeat newer than the GPS
measurement supplies ignition status even when the old fix was retransmitted.
Communication freshness and GPS freshness expire separately after the configured
idle timeout (180 seconds by default). Last known coordinates remain visible
with their timestamp and stale/offline status.

The roster, table and popup show connection separately from movement and GPS age.
Recent packets keep a tracker Online even when its measured GPS point is delayed.
Delayed speed is labelled Last reported moving/stopped rather than current motion.
ACC carries its own source and report timestamp; expired ignition is Unknown with
the last reported value shown separately. Movement never fabricates an ACC-ON bit.
Expired heartbeat fix flags do not override current GPS evidence.

Validation: isolated SQLite hardware tests cover idempotent seeding, actual capture
decoding, assignment, local persistence, revoked registrations, fleet pagination,
endpoint privacy/authorization and status transitions. Node tests cover interpolation
and movement/freshness states; Vite builds the frontend bundle.
