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
and version in SQL, then stores packet events, communication times and valid
positions before ACK. It uses no Redis or broadcasts; the admin map polls the
authenticated fleet endpoint every five seconds. SQL errors cause disconnect
without ACK. It is a bounded local troubleshooting mode, with blocking SQL in
its loop; production deployments should continue using the Redis gateway/worker.
Without `--persist`, diagnostics remain observation-only as before.

`tools/start-gps-live.ps1` uses this storage mode and mirrors output to
`storage/logs/gps-live-diagnostic.log`. `php tools/gps-fleet-status.php` provides
a read-only snapshot of the configured application database.

## Admin map

Open `/admin/gps-devices`. Its map/roster includes all registered trackers and
vehicles, independent of the table's 25-row pagination or table search filters.
New registrations appear on the next poll. The authenticated endpoint returns
only display telemetry, excluding SIM details and device configuration. Existing
private Reverb position events are also supported for the production gateway.

Bike and car markers show registration, driver, coordinates and fix time. The
map does not reload or recenter on every packet. Markers interpolate between
measured positions over 1–8 seconds; animation resumes from the current displayed
position when a newer fix arrives. It does not extrapolate motion beyond the
latest fix. Old retransmissions cannot rewind a marker. Reassignment clears old
vehicle markers and never borrows an earlier vehicle's fix.

States: moving (>1.8 km/h); idle (stationary with ACC on); parked (ACC off);
stopped (ACC unknown); unknown speed; awaiting GPS; no GPS fix; stale GPS; offline;
inactive/maintenance/blocked; unassigned vehicle. A heartbeat newer than the GPS
measurement supplies ignition status even when the old fix was retransmitted.
Communication freshness and GPS freshness expire separately after the configured
idle timeout (180 seconds by default). Last known coordinates remain visible
with their timestamp and stale/offline status.

Validation: isolated SQLite hardware tests cover idempotent seeding, actual capture
decoding, assignment, local persistence, revoked registrations, fleet pagination,
endpoint privacy/authorization and status transitions. Node tests cover interpolation
and movement/freshness states; Vite builds the frontend bundle.
