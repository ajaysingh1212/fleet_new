# Mobile GPS Protocol TG1

For new mobile apps use [the no-login registered-device guide](mobile-no-login.md).
It replaces the login/token steps below with user_id, licence, IMEI and a provisioned device_key. The token examples below describe the optional legacy flow; TG1 framing is unchanged.

The tracking listener accepts authenticated, newline-delimited hexadecimal TG1 frames.
This is a new mobile protocol, not GT06 or another hardware vendor's protocol.

## Running the services

Run these commands from the tracking project in separate terminals:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
php artisan queue:work
php artisan reverb:start
php artisan schedule:work
php artisan gps:listen --host=127.0.0.1 --port=9000
```

The last command accepts local TCP only. For a phone outside this machine, use a
TLS proxy forwarding to 127.0.0.1:9000, or bind the listener with a valid certificate:

```powershell
php artisan gps:listen --host=0.0.0.0 --port=9000 --cert=C:/certs/fullchain.pem --key=C:/certs/privkey.pem
```

Use your server's reachable DNS name/IP and port 9000 in the mobile app. The
certificate must match that name/IP. 0.0.0.0 is a bind address, not an app destination.
127.0.0.1 on a phone refers to the phone, not the server. A production server needs
the chosen port allowed in its firewall and, if relevant, router forwarding.
No public IP, firewall rules, certificates or remote deployment are provisioned
by these code changes.

The listener runs on Windows without pcntl. It supports 64 simultaneous clients,
partial/coalesced TCP reads, partial writes, a 75-second idle timeout, and
120 incoming packets/minute/source IP. Shared cache (database or Redis) is required
between web, worker, scheduler and listener; do not use the array cache driver in
production. Burst traffic from many phones behind one NAT may require increasing
the documented source-IP limit.

## Authentication and authorization

1. Login over HTTPS using existing POST /api/v1/auth/login with email, password,
   device_id, device_name and platform. device_id must match the identifier sent
   in the packet's imei field. Retain the returned Sanctum token securely.
2. Use the tracked person's own login/token, never the tracker/admin token.
3. Supply an active, paid, unexpired license assigned to that tracked person,
   or their own unassigned license for self tracking.
4. Send code 01, then codes 02/03 as needed. Every packet is independently
   authenticated; a successful handshake does not bypass later checks.
5. Device revocation, deleted/revoked/expired tokens, blocked accounts, expired
   licenses and licenses assigned to a different person are rejected.

IMEI is an identifier, not a password. Android 10+ restricts access to hardware
IMEI for ordinary apps. Where the platform does not expose it, generate an
installation UUID, retain it in private app storage and send that UUID in the
imei field and as login device_id. It is an app identifier, not a hardware IMEI.
See [Android identifier guidance](https://developer.android.com/identity/user-data-ids).
Obtain the user's location permission before collecting GPS data.

## Frame layout

All multi-byte integers are unsigned, big endian. Build the binary frame, convert
every byte to two hexadecimal ASCII characters, then append one ASCII LF (0A).
The LF is outside the hexadecimal data. Upper/lowercase hex and CRLF line endings
are accepted. Do not insert spaces or a 0x prefix.

| Binary offset | Bytes | Meaning |
| --- | --- | --- |
| 0 | 2 | Magic TG: 54 47 |
| 2 | 1 | Protocol version: 01 |
| 3 | 1 | Message code |
| 4 | 4 | UTF-8 JSON payload byte length |
| 8 | N | UTF-8 JSON payload, maximum 16384 bytes |
| 8+N | 4 | CRC32 IEEE / PHP crc32b of header + JSON |

CRC32 detects transmission errors. Authentication comes from the bearer token;
confidentiality comes from TLS. Hex encoding does not encrypt data.

| Code | Direction | Meaning |
| --- | --- | --- |
| 01 | App to server | Authenticated login/handshake and device status |
| 02 | App to server | GPS location, speed, heading, battery and network |
| 03 | App to server | Heartbeat/status without creating a location-history row |
| 04 | App to server | Explicit logout; ends this device session and deletes its token |
| 80 | Server to app | Acknowledgement |
| 81 | Server to app | Error |

## Payload fields

Every app packet requires:

| Field | Type | Meaning |
| --- | --- | --- |
| imei | string | 15-digit IMEI or retained installation UUID; never a JSON number |
| license_number | string | Existing license number assigned to this tracked person |
| token | string | Token returned by HTTPS login for this same device/person |
| packet_id | UUID string | Unique ID per packet, retained unchanged when retrying |
| recorded_at | ISO-8601 string | UTC measurement/heartbeat timestamp |

Code 02 additionally requires source_type (android, ios, browser, desktop or api),
latitude (-90..90) and longitude (-180..180).
Optional fields: accuracy in meters, speed in meters/second, bearing and heading
in degrees 0..360, altitude in meters, battery_level 0..100, network_type (max 20
characters), signal_strength 0..100, provider (max 30 characters), is_mock boolean,
and tracking_session_id UUID from a previous acknowledgement.

Code 01/03 accepts battery_level, network_type and is_gps_enabled boolean.
Unknown JSON fields are not persisted. Device session ID is resolved from the
authenticated imei, so a payload device_id cannot redirect the write.

Example location JSON before hex encoding (replace all placeholders and timestamp):

```json
{
  "imei": "123456789012345",
  "license_number": "YOUR-ASSIGNED-LICENSE",
  "token": "TOKEN-FROM-HTTPS-LOGIN",
  "packet_id": "2d222222-3333-4444-8555-666666666666",
  "recorded_at": "2026-10-01T12:00:00Z",
  "source_type": "android",
  "latitude": 28.6139,
  "longitude": 77.209,
  "accuracy": 8,
  "speed": 4.2,
  "bearing": 90,
  "heading": 90,
  "altitude": 220,
  "battery_level": 85,
  "network_type": "4g",
  "signal_strength": 70,
  "provider": "gps",
  "is_mock": false
}
```

Send fresh packets; timestamps over five minutes old or over 30 seconds in the
future are rejected by this live listener. Synchronize the phone clock. For
historical offline uploads use existing HTTPS POST /api/v1/gps/locations/batch;
offline uploads do not rewind the live map.

## Frequency, retries and replies

Send location updates every 3-5 seconds while sharing; send a heartbeat every
30 seconds even while stationary/no GPS fix is available. Do not wait until the
history radius is crossed to send locations: server-side filtering handles that.
Idle sockets close after 75 seconds; reconnect, send code 01 and continue.

For codes 01/02/03, retry the exact same packet with the same packet_id until
acknowledged, with exponential backoff. Deduplication is retained for one day.
Reusing an ID with different validated data returns packet_id_conflict. Replaying
an expired-timestamp packet is rejected even if previously acknowledged. Logout
invalidates its token, so code 04 cannot be retried with that token; login again
if its acknowledgement is lost.

For a complete app-development walkthrough and exact hex vectors, see
[Mobile app integration guide](mobile-app-integration-hinglish.md).

Code 80 JSON includes packet_id, ok, request code, server_time and
distance_filter_meters. Location replies also include saved, reason and
tracking_session_id and live_cached. saved=false/reason=throttled means live data was
received but movement was below the history radius; it is not a transport failure.
reason=stale means the fix predates the latest stored fix.

The listener prints structured terminal logs for CONNECTED, RECEIVED, SAVED,
LIVE_CACHED, HEARTBEAT, DUPLICATE, LOGOUT and REJECTED. Logs include allowed
identity/telemetry fields but never tokens or raw credential frames.

Code 81 JSON includes ok=false and reason:
invalid_hex, invalid_header, invalid_length, invalid_checksum, invalid_json,
invalid_payload, unknown_code, invalid_fields, unauthorized, device_revoked,
invalid_license, packet_id_conflict, rate_limited or server_error.
Transport-level invalid frames may not contain a trustworthy packet_id; keep
one unacknowledged request at a time. Never print/log outgoing credential packets.

## Map, presence and location history

The live map uses private GPS/presence broadcasts and a 3-second authorized
snapshot fallback, so it still updates when the WebSocket service disconnects.
Marker changes animate over one second. Only licensed, authorized tracking
relationships are included; a person is removed when that relationship/license
ceases to authorize access.

Online means an active, non-revoked device session with activity within 90
seconds. Explicit logout broadcasts immediately. Silent disconnects become
offline after that grace window; the scheduler broadcasts expiry at 10-second
intervals and map polling independently recomputes status.

Admin/Super Admin can set Settings > System > Location Save Radius Meters.
Minimum is 1 meter, maximum 65535. First fix is saved, then only fixes at least
that distance from the last stored fix are saved. Time/bearing/speed changes
alone no longer bypass the radius. When no global setting exists, the existing
per-user distance preference (default 25m) applies. Live positions are cached
separately for one day and are not historical records.

Regular users with tracking-workspace access can open Geofences, create their
own zones and assign them only to people they are authorized to track.
They cannot edit another user's zones or assign a zone to an unrelated person.

## Reference implementations

- Android Kotlin encoder/TLS client: examples/MobileGpsProtocol.kt
- PHP local client: ../tools/mobile-gps-client.php

The Android sample demonstrates wire encoding and transport; it does not provide
the mobile app's login UI, background location service or permission screens.
Reference APIs: [PHP socket server](https://www.php.net/stream-socket-server)
and [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum).
