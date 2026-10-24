# Monitoring GPS History

Open `/admin/monitoring/history`. Hardware GPS administrator authorization also
protects its target search and report endpoints. The mobile/user history API is
unchanged.

1. Choose Vehicle or IMEI / GPS device and search/select a matching target.
2. Select Today, Yesterday, Last 7 days, Last 30 days, or Custom date. Custom
   displays required From and To date/time fields. All filters and displayed
   times use IST; GPS queries convert these boundaries to UTC. The To minute is
   inclusive. Presets use calendar days in IST, including today.
3. Choose Route History, Geofence Report, or All Reports, then View report.

Vehicle history follows the vehicle assignment saved with each GPS record,
including points from previously assigned trackers. IMEI history follows the
selected tracker even if it has no vehicle assigned. Location history uses GPS
measurement time rather than packet arrival time.

Route History displays a measured route map, start/end markers, point count,
maximum recorded speed, and paginated position/ignition records. Separate
trackers and gaps over five minutes are not joined into continuous lines.
The map is bounded to the first 5,000 points, with an explicit warning when the
selected range exceeds that limit; every table record is still paginated.

Geofence Report displays recorded boundary, overspeed and underspeed alert
episodes that overlap the selected period, including episodes that began earlier
and were still open during the period. These are recorded alerts, not reconstructed
entry/exit events. Open episodes are labelled Open alert. All Reports combines
the route and geofence results with independent pagination (50 rows per page).
Empty states distinguish missing GPS records from missing geofence alerts.

Existing retention policies still apply. No synthetic history is inserted.

## Journey explorer and PDF

The redesigned history page includes speed-colored route segments and selectable
GPS points, a recorded speedometer, a speed profile, and a selectable activity
log. Play/pause, restart and the timeline operate at 2x, 4x, 8x or 16x measured
GPS time. Long reporting gaps (over five minutes) and tracker changes use a short
discrete transition; they do not create a route line or contribute to mapped
distance. The gauge displays the selected sample's measured speed, not a speed
inferred from animated movement. Unknown speed remains unavailable.

The map and playback are limited to the first 5,000 GPS samples. The interface
labels this explicitly and marks mapped distance as partial when truncated.
PDF download fetches every page of each selected report, independent of the
currently displayed table page. It includes a route schematic, summary and the
complete GPS / geofence tables, with repeated headings and page numbers.
Changing filters or loading another page cancels an in-progress export and
stops playback. Downloads are generated locally without an external PDF service.
