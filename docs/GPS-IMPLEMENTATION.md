# Hardware GPS gateway implementation

## Scope and existing project inspection

Inspected Laravel 12 dependencies, admin/API/broadcast routes, commands, models,
migrations, location DTO/services/jobs/events, policies, providers, Redis/Horizon
configuration, AdminLTE Blade layout, Leaflet maps and Echo/Reverb integration.

The existing `gps_locations`, `device_status`, `device_sessions` and
`tracking_sessions` implement licensed **user/mobile tracking**, with mandatory
user/device-session foreign keys and mobile source enums. They are preserved.
There was no hardware GPS registry or vehicle table. The new hardware domain
uses `gps_devices`, `vehicles`, `gps_device_assignments`,
`gps_connection_sessions`, `gps_packet_diagnostics`, `gps_device_events` and
`gps_device_locations`. This avoids fake user accounts, fake mobile sessions,
nullable changes across existing mobile consumers, and changes to licensing.
The current device points directly to its latest valid fix. Protocol registry
and device configuration are code configuration / JSON fields, respectively;
no duplicate registry/configuration/command tables were added.

Implementation sequence: preserve mobile behavior; add hardware/vehicle schema;
build independently registered adapters and common DTO; add fail-closed cached
authentication and queued persistence; connect admin forms/private broadcasts;
test fixtures, permissions, storage, transport and deployment operations.

## Usage and authorization

Open `/admin/gps-devices` as an Admin or Super Admin with the existing
`manage device sessions` permission. The same policy protects all hardware
routes, diagnostics, private documents and the `admin.gps.hardware` broadcast
channel. This application has global administrators, not a company tenant
boundary. Vehicle `company` is descriptive metadata, not an access-control key.
Ordinary users/managers are denied; do not expose this global channel as a
tenant channel without introducing a real tenant key and query scopes.

Register an existing or new vehicle, record the exact device model, identifier,
protocol/version and configuration, then activate it. For the implemented
protocols the identifier is a 15-digit IMEI; other identifier formats require
an adapter and corresponding registration validation. An optional IMEI must
match the identifier. Status supports active, inactive, maintenance and blocked.
An existing vehicle can also be edited through `/admin/vehicles`.

One tracker can hold a vehicle assignment at a time, including while inactive.
Unassign the previous tracker explicitly before assigning another. The unique
current vehicle FK and transaction/row locks enforce this stronger constraint.
Assignment changes append installation history and clear the current position.
Devices are retained rather than deleted to preserve history.

Configuration is **recorded only**, never reported as delivered/applied. No
model-specific remote commands are enabled. The form clearly labels unknown
capabilities. Unsupported protocol/transport records can be saved inactive;
activation requires a supported version, TCP/9000 and an exact model. X1
requires model X1 and UTC. Teltonika IO values remain model-specific hex values;
they are not guessed to mean ignition, fuel or battery percentage.

PDF/JPEG/PNG documents use the private local disk and authorized downloads.
Raw packets are never stored; diagnostic and location records use SHA-256 packet
references. Optional pending diagnostics contain limited identifiers, addresses,
byte counts, hashes and reason categories. Review leads to an inactive
registration form, never automatic authorization. No SIM/APN/raw telemetry is
broadcast. Changes use the existing audit observer/job. Route requests are
limited to 60/minute per authenticated caller; uploads are limited to five
files of 5 MB per submission. Keep an operational disk quota for private uploads.

## Compatibility report

| Family/version | Implementation and evidence | Limits / release gate |
|---|---|---|
| Existing TG1 | Preserved authenticated mobile protocol and TLS test | `gps:listen-mobile`; compatibility forwarding from `gps:listen --cert` or `--mobile` |
| Teltonika Codec 8 | Login, length framing, CRC-16/ARC, record counts, signed GPS, fixed-size IO; official sample verified | Exact model/firmware device acceptance still required; zero-fix records acknowledged but excluded from live storage |
| Teltonika Codec 8E | Same plus 2-byte IO identifiers/counts and variable-size IO parsing | Simulated structure tests only; real captures required; Codec16/12/13/14 unsupported |
| Concox X1 V1.1 | Narrow GT06-family adapter; short frames, X1 A001 login, 13 heartbeat, 34 GPS/alarm; CRC-16/X-25; simulated fixtures | **Partial / experimental**; manufacturer detailed table includes odometer omitted from summary. Must resolve layout with real capture before deploying X1. No guessed ACK for 34; only documented login/heartbeat ACKs |
| GT06 classic v1.8.1 | Classic 01 login, 12 position and 13 heartbeat; manufacturer login fixture and user's BT50 login capture verified | Only these documented layouts; 22/26/7979 and other variants rejected; BT50 position compatibility awaits live capture |
| Mictrack BT50 | Explicit unsupported registry entry | Await exact BT50 official manual and captures. Found B50 documentation is not proof of BT50 equivalence |
| GPS103/TK103 | Unsupported entry | Await exact variant's official manual/captures |
| JT/T 808 | Unsupported entry | Await versioned specification, identifier/authentication/segmentation fixtures |
| H02 | Unsupported entry | Await exact manufacturer framing/format/checksum fixtures |
| UDP / remote commands | Not implemented | No UDP listener/firewall or command history is fabricated |

Primary references:

- [Teltonika data sending protocols](https://wiki.teltonika-gps.com/view/Teltonika_Data_Sending_Protocols)
- [Teltonika server FAQ with official Codec8 sample](https://wiki.teltonika-gps.com/view/Help_with_Server_FAQ)
- [Manufacturer-authored Concox X1 V1.1 manual, hosted by Traccar](https://www.traccar.org/protocol/5023-gt06/X1-GPRS%20Communication%20Protocol%20V1.1.pdf)
- [Mictrack official protocol catalogue](https://help.mictrack.com/articles/protocols/)

Protocol `detect` uses documented leading bytes; ambiguous/incomplete detection
waits, then fails closed on timeout. X1 login additionally requires its model
identifier A001. Registry class names are in `config/gps.php`; implement
`Protocol`, register the class and supported versions, and add official fixtures
without editing the listener. `Location` carries validated UTC GPS fields and
nullable unsupported fields. Packet/job envelopes add device, vehicle, IMEI,
protocol version, received timestamp and packet reference. Hardware speeds are
km/h; the existing mobile speed convention is untouched.

## Processing and operational guarantees

The nonblocking TCP select loop has per-client buffers, maximum input/output
sizes, 256 concurrent clients, 32 per source IP, 10-second unauthenticated
deadline, 180-second idle expiry, 1 MiB/600 reads per connection per minute,
64 frames per read, 128 records per Teltonika frame, and bounded frame lengths.
Tune limits after measuring real carrier NAT and device reporting patterns.

There is no SQL in the socket loop. A dedicated Redis connection has bounded
I/O timeouts, while existing Redis/Horizon/mobile connections are unchanged.
The allowlist is refreshed after admin saves, every minute by the scheduler,
and by `gps:sync-devices`. Missing/expired entries fail closed. Database row
locks serialize publication of the current device state; Redis locks are not
required for administration. No SQL fallback runs inside socket handling.
IMEI allowlisting is identification, not proof
against spoofing: use private carrier APN/VPN/source restrictions for hardware
whose protocols lack cryptographic authentication. TG1 retains its TLS/key path.

All valid registrations and edits, including active status and deactivation,
are saved in SQL even when Redis is unavailable. A single warning reports
pending gateway synchronization. Uploaded documents remain attached to successful
registrations. Business validation still rejects invalid data and assignment
conflicts, and each error is rendered once by the shared layout.

On an outage, Redis may retain an older authorization snapshot. It can admit a
packet into the queue until synchronization/expiry, but workers recheck SQL
device status, identifier, protocol/version and vehicle assignment **before**
creating sessions, storing locations or broadcasting. Thus a blocked device or
old identifier cannot publish live tracking through a stale cache. The scheduled
sync republishes current SQL state after Redis recovers. Device status describes
the administrative setting; it does not guarantee gateway connectivity.

For `Predis ... tcp://127.0.0.1:6379` errors, verify a Redis server is actually
running at `REDIS_HOST`/`REDIS_PORT`. Installing the Predis PHP package does not
install a Redis server. On the Ubuntu VM:

```bash
sudo systemctl enable --now redis-server
redis-cli ping
php artisan gps:sync-devices
php artisan gps:status
```

On Windows, use your chosen local Redis installation/container/WSL environment,
then verify `redis-cli ping` returns `PONG`. Device management works while
Redis is unavailable; hardware ingestion continues to require Redis. Do not change the
gateway to a file/array cache to hide connection failures. If connection settings
change, rebuild Laravel's config cache before synchronization.

The gateway pushes a validated packet to Redis before ACK and closes without
ACK on queue overload/failure. Configure Redis AOF, memory reservation and
`noeviction`; successful enqueue does not imply disk fsync or MySQL commit.
The 10,000-job watermark bounds ingress; already accepted packets, workers and
broadcast jobs can overshoot it slightly. Alert before the watermark and on
failed jobs. Concox 34 has no documented ACK/retry guarantee.

Workers recheck device status/protocol/version/vehicle, then take a DB row lock.
The `(device, packet SHA-256, record index)` key makes exact retransmissions
idempotent. Historical fixes are retained without overwriting a newer latest
fix. Invalid ranges/times/checksums or unsupported versions never reach live
tracking. Valid no-fix records can receive documented receipt ACKs but are not
stored as valid fixes. Jobs retry five times; failed jobs require operational
review. A retried post-commit broadcast enqueue can repeat a broadcast; the UI
ignores old/equal GPS timestamps. Private Reverb events are queued after commit.

Session close jobs handle consumption before login jobs. Abrupt process death
cannot record every FIN; last communication and timeout are authoritative for
online/offline. Retention marks stale sessions terminal and purges old metadata.
Defaults retain diagnostics seven days, location/event/session history 90 days,
and current latest fixes plus installation history indefinitely. Purges use
1,000-row batches. Plan partitioning/archival separately for measured volume.

Dashboard summary refreshes every 30 seconds; positions update through Reverb.
The map displays the current paginated/filter result (25 devices), with search
by vehicle/IMEI/name and protocol/status filters. Location history is paginated
at 50 rows. Dates display in the authenticated user's timezone; history filter
inputs are explicitly UTC. Moving means known speed above 1.8 km/h; missing
speed/fix is not counted as stopped.

## Installation and Ubuntu / Google Cloud deployment

The local MySQL incident described in `GPS-DATABASE-INCIDENT.md` must be resolved
from a backup before using the affected local application. The following
deployment steps target a backed-up deployment database, not data recovery.
No cloud resources or firewall changes have been applied from this workspace.

On Ubuntu, install PHP 8.2+ CLI/FPM with MySQL, mbstring, XML, curl, zip, intl and
pcntl, Redis, Composer, Node/npm and Nginx. Existing Laravel services remain.
Use `/var/www/fleet` below or edit both service files to the real path.

```bash
cd /var/www/fleet
composer install --no-dev --optimize-autoloader
npm ci
# Merge docs/deployment/gps.env.example into the existing .env; preserve secrets.
# Record a verified database backup before schema changes.
npm run build
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan gps:protocols
php artisan gps:sync-devices
sudo install -m 0644 docs/deployment/fleet-gps-tcp.service /etc/systemd/system/
sudo install -m 0644 docs/deployment/fleet-gps-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now fleet-gps-tcp fleet-gps-worker
```

These are two new services; do not also start a second gateway/worker through
Supervisor or Horizon. Existing default/mobile workers are preserved. The new
worker consumes **gps-redis / gps-hardware**, Redis DB 2 by default. It must not
be confused with the existing redis/default queue. SIGTERM stops the gateway
select loop, closes sockets and queues terminal sessions. systemd restarts on
crash and journals output. Workers finish their current job before stopping.

Keep the existing scheduler's single cron entry (add only if absent):

```cron
* * * * * cd /var/www/fleet && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Refresh allowlist every minute; run retention daily. Install a health monitor that
runs `gps:status` and alerts on nonzero exit, stale heartbeat, backlog and failed
jobs. For deployment reloads:

```bash
sudo systemctl restart fleet-gps-tcp fleet-gps-worker
php artisan reverb:restart
php artisan gps:status
php artisan queue:failed
sudo systemctl status fleet-gps-tcp fleet-gps-worker --no-pager
sudo journalctl -u fleet-gps-tcp -u fleet-gps-worker -n 100 --no-pager
sudo ss -ltnp | grep ':9000'
```

`GPS_PORT` documents the deployment port; `gps:listen` defaults explicitly to
9000. `--port` can override it for isolated tests. Do not move TG1 mobile clients
onto the raw hardware port. Existing `gps:listen --cert=... --key=...` calls are
forwarded to the original TLS mobile listener. Run it on a **different** port
(for example 9443) if both operate on the same address:

```bash
php artisan gps:listen-mobile --host=0.0.0.0 --port=9443 --cert=/path/fullchain.pem --key=/path/privkey.pem
```

For Google Cloud, use a reserved external address / DNS record and select the
intended project, VPC, VM and zone. Replace every placeholder before running:

```bash
gcloud compute instances add-tags VM_NAME --zone=ZONE --tags=gps-tracker
gcloud compute firewall-rules create fleet-gps-tcp-9000 \
  --network=VPC_NAME --direction=INGRESS --action=ALLOW \
  --rules=tcp:9000 --source-ranges=CARRIER_CIDR --target-tags=gps-tracker
sudo ufw allow from CARRIER_CIDR to any port 9000 proto tcp
```

Use the carrier/VPN egress CIDRs whenever available. `0.0.0.0/0` permits public
trackers where no stable carrier ranges exist, but increases spoofing/DoS
exposure. Before enabling UFW, preserve the VM's actual SSH administration rule
and HTTPS/HTTP requirements. Review existing broad VPC rules; adding a narrow
rule does not override another broad allow. Do not open UDP/9000, MySQL/3306,
Redis/6379 or the internal Reverb service port to the internet. Bind local MySQL
and Redis to loopback, use Redis authentication/noeviction/AOF, or restrict
private managed services to the application subnet. Neither MySQL nor Redis
needs a public firewall rule for trackers.

Keep Reverb running through the existing persistent manager, bind its service
to 127.0.0.1:8080 (or the existing configured internal port), and proxy WSS/HTTPS
through the site's TLS Nginx vhost. Use the same credentials on PHP and Vite.
Add these locations to the existing TLS vhost, preserving application routing:

```nginx
location ^~ /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 120s;
}
location ^~ /apps/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Reverb's configured allowed origins must match your application origin; it is
not a replacement for the private broadcast authorization callback. Configure
TLS certificates and normal Laravel Nginx/PHP-FPM hosting through your existing
deployment. Review [Google firewall CLI](https://docs.cloud.google.com/sdk/gcloud/reference/compute/firewall-rules/create)
and [Laravel Reverb deployment](https://laravel.com/docs/12.x/reverb).

## Verification commands and evidence

```bash
php artisan test --compact --filter='HardwareGps|RegisteredGpsDevice|MobileGps'
php tools/gps-mysql-tests.php
# Only against a local test Redis instance (unique per-run key prefix):
GPS_NETWORK_TESTS=1 php artisan test --compact --filter=HardwareGpsNetworkTest
npm run build
php artisan test --compact
```

On Windows use `npm.cmd run build`; for the opt-in network test set
`$env:GPS_NETWORK_TESTS='1'` in PowerShell first. The MySQL runner requires
CREATE/DROP DATABASE privileges and uses its own random database. The test class
checks the **effective** connection/database before RefreshDatabase can migrate;
normal hardware tests only accept in-memory SQLite. It never intentionally
uses or resets the configured application database. See the incident report
for the corrected initial runner failure.

Real Redis/TCP tests use the actual listener process, fragmented login,
coalesced AVL packets, exact ACKs, reconnect and process restart. They do not
consume the queued packets or claim live-device/MySQL integration. The normal
suite checks real SQL persistence, private Reverb channel authorization and
safe event payloads without publishing test data to a live Reverb server.

No real device captures, live Reverb browser acceptance, load testing or Google
Cloud deployment has been performed. Those remain release acceptance gates;
this implementation is not a claim of universal or fully certified production
compatibility.

## Windows live TCP diagnosis (2026-10-16 follow-up)

The user supplied a real BT50 login capture matching classic GT06, so the
earlier no-captures statement above now has that specific exception. The packet
identifies IMEI `864540081540460`, and its CRC validates. The Python test listener
only receives bytes; it sends no login ACK. Manufacturer documentation describes
reconnection when login is not acknowledged. This is a likely explanation of
the repeated login packets; actual next-packet behavior needs live observation.

When Redis is unavailable, explicitly run the diagnostic mode:

```powershell
cd D:\fleet_new
php artisan gps:listen --host=0.0.0.0 --port=9000 --diagnostic --diagnostic-imei=864540081540460
```

This is a temporary diagnostic listener, **not** production ingestion. Only the
explicit diagnostic IMEI receives the documented GT06 login/heartbeat ACKs.
Unlisted IMEIs get no ACK, and unknown packets receive no guessed replies.
No SQL, Redis, location persistence, queue or broadcast access occurs. Output
shows CONNECTED, DATA_RECEIVED, LOGIN, ACK_SENT, HEARTBEAT, GPS_LOCATION,
GPS_NO_FIX, PACKET_NOT_DECODED, DISCONNECTED and a status summary every 15s.
Times use IST. A TCP connection or login alone is not proof of GPS fixes.

Readable terminal output is the default (`--format=pretty`). It displays packet
framing, declared/received lengths, protocol type, serial number, CRC, bounded
raw HEX and payload HEX for the explicitly allowed device. GPS records include
latitude/longitude, speed in km/h, satellites, heading, GPS UTC time, available
cell-tower fields, fix validity and a map link for valid fixes. The summary
separately retains the last valid fix. Missing fields display "Not provided";
no-fix records are not presented as confirmed positions. Heartbeats show ACC,
charging, GPS tracking enabled, power/GSM levels, status bits and language code.
Levels are not converted to invented voltages or battery percentages.

Extended 94/0A packets with the exact identifier layout show IMEI, encoded IMSI
and encoded ICCID, with optional documented door/air-con bits. These fields
contain no coordinates; encoding/padding is preserved rather than guessed.
This diagnostic interpretation follows section 5.6 of the
[manufacturer S20 V2.1 protocol](https://www.traccar.org/protocol/5023-gt06/S20WanWayProtocol2021.03.22V21.pdf),
whose layout matches the observed packet. It does not certify all BT50 firmware
or enable extra production adapters/ACKs. Unsupported types remain raw/unknown.
SIM identifiers appear only in the requested local diagnostics, not production
broadcasts. Treat the terminal/log as private diagnostics.

Append `--format=json` for scripts that need the earlier structured stream.
Both formats have real loopback TCP process tests. Laravel listener commands
also acquire a local OS file lock for the selected port to prevent duplicate
PHP listeners sharing TCP/9000 on Windows. Stop the current listener with Ctrl+C
before starting another.

`tools/start-gps-live.ps1` opens the same diagnostic mode for this workspace,
waits until port 9000 is free, and mirrors terminal output into
`storage/logs/gps-live-diagnostic.log`. Stop the Python test listener using
Ctrl+C first. Only one listener may bind that port. Keep the existing TCP
tunnel pointed at this Windows host's port 9000. No new tunnel or firewall rule
is created by the script. The visible terminal must remain open while testing.
