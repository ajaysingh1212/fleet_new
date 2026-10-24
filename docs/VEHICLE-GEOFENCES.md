# Vehicle geofences and fleet console

Administrators use Monitoring > Geofences to draw a circle, polygon or rectangle,
use the original map/sidebar/details form, select Vehicle / IMEI in the former
Tracked user field, and set optional minimum/maximum speeds in km/h. The selector
searches registration, tracker name or IMEI with 30 trackers per page. Linked
trackers show the vehicle registration and IMEI; unassigned trackers can be
selected by IMEI without creating a vehicle. New rules follow that tracker even
if its vehicle assignment changes. Existing vehicle-based rules still follow
the vehicle. Existing tracked-user APIs and routines remain separate for
non-hardware users. Vehicle/IMEI monitoring is always on while a rule is active.

Each vehicle rule owns independent boundary/speed state. Fresh valid GPS fixes
are evaluated by the history worker after the live map publish, preserving the
live-first pipeline. Heartbeats, invalid fixes, pre-rule timestamps, old fixes
and out-of-order/duplicate measurements do not fabricate new violations.

Outside, overspeed and underspeed are independent episodes: first detection
records a start time, coordinates, measured speed and configured speed limit.
Further violating packets keep the episode active without creating duplicate
rows. Recovery closes it; a later breach creates a new episode. Minimum speed
applies only above zero, so parking does not create underspeed alerts. Missing
speed does not imply recovery. Editing/pausing a rule closes its current alerts
and resets its evaluation state; resuming waits for a new valid measurement.

Alerts publish on the administrator-only `admin.gps.alerts` private channel.
Loaded vehicles receive persistent red marker/card highlighting and a toast;
the bell opens paginated history for the fleet. History includes vehicle or IMEI, zone, type, start/end and speed/limit.
An unresolved alert is the last reported violation, not proof of current
location when communication/GPS is stale. GPS freshness stays visible separately.

The fleet map fills the workspace and has a fullscreen control. Vehicle cards
are initially hidden; the car icon opens the left drawer. Search filters the
bounded server snapshot without reloading the page, cards paginate ten at a
time, and Previous/Next devices navigates the next server group. Manage devices
reveals the existing registry table; tracker popups link to assignment/editing.
The separate Vehicles page has searchable, twelve-card pagination.

Edit details, change the target, edit the shape, activate/deactivate, archive,
duplicate and delete remain in the original list menu. Deletion soft-deletes
the zone, closes active episodes and retains the violation history.

Migration: `2026_10_23_000001_create_vehicle_geofence_monitoring.php` adds three
tables without replacing user geofences or GPS history. Restart the persistence
and broadcast workers after deployment (`tools/start-gps-realtime.ps1` starts
missing managed workers). The additive migration
`2026_10_23_000002_allow_imei_geofence_targets.php` allows nullable vehicle IDs
and adds a tracker target to rules, preserving existing rules and history. No real or simulated geofences are created automatically.

Live map warnings evaluate each WebSocket position against the loaded rules
before the SQL history worker finishes. Fresh boundary/speed warnings pulse red;
a last known position outside a boundary pulses amber with GPS update pending.
Old measurements never invent a new speeding episode or a crossing timestamp.
The warning banner and current-warning list remain visible independently of
recorded history. Recovery clears the current warning on the next measured fix.
Rule mutations publish `vehicle.geofence.rules.changed` on `admin.gps.alerts`;
open maps reconcile rules and current alerts, including after reconnect. There
is no periodic HTTP poll for warnings. The local 15-second clock only expires
GPS freshness; it does not fetch data.
