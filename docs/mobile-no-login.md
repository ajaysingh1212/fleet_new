# Mobile GPS without user login

This is the recommended mobile authentication flow. The older token/login examples in the other integration guides remain legacy-compatible, but login and a Sanctum token are not required for registered devices.

## Register once on the server

Run from the tracking project, using an existing active tracked user, their valid assigned/owned licence, and the device IMEI:

```powershell
php artisan gps:device-register 10 VALID-LICENSE 123456789012345
```

The command returns `user_id`, `license_number`, `imei`, and a random 64-character `device_key`. Provision these into the mobile app securely. Only the SHA-256 key hash is stored on the server. Keep the key secret; user IDs, licence numbers and IMEIs alone are identifiers, not secure authentication. Registration is an operator action, not an unauthenticated mobile endpoint. It neither logs the user in nor marks them online.

To replace a lost/compromised key, run the same command with `--rotate`. The old key immediately stops working. Revoking a registered device through the existing device controls also invalidates its key.

## Location payload (code 02)

```json
{
  "user_id": 10,
  "license_number": "VALID-LICENSE",
  "imei": "123456789012345",
  "device_key": "REPLACE_WITH_THE_64_CHARACTER_REGISTRATION_KEY",
  "packet_id": "48b01c26-d121-4f48-a6c8-8ba0abf94882",
  "recorded_at": "2026-10-03T12:00:00Z",
  "source_type": "android",
  "latitude": 28.6139,
  "longitude": 77.2090,
  "accuracy": 8,
  "speed": 1.5,
  "heading": 90,
  "battery_level": 85,
  "network_type": "wifi",
  "is_gps_enabled": true
}
```

Replace the sample timestamp with current UTC time and generate a fresh UUID for each new packet. For retries keep the same UUID and identical payload. The server accepts timestamps up to five minutes old or 30 seconds ahead. Speed is meters/second, accuracy is meters, heading is degrees, latitude/longitude are decimal degrees. If the OS cannot provide an IMEI, register and send a stable installation UUID instead; do not generate a different UUID every launch.

Every packet includes `user_id`, `license_number`, `imei`, `device_key`, `packet_id`, `recorded_at`. Do NOT include `token` in this mode. The server verifies the active user, exact registered device, its secret key, and the bound usable licence on every packet.

| Hex code | Meaning | Additional fields |
| --- | --- | --- |
| 01 | Hello / reconnect | Optional battery/network/GPS status |
| 02 | GPS location | source_type, latitude, longitude and optional telemetry |
| 03 | Heartbeat | Optional battery/network/GPS status |
| 04 | Stop tracking / offline | No location required |
| 80 | Server success ACK | ok, user_id, packet_id, server_time; location ACK also includes saved, reason, live_cached |
| 81 | Server rejection | Error details |

Send heartbeat every 30 seconds while tracking. Presence becomes offline after 90 seconds without activity. Code 04 stops tracking but keeps the registered key; a subsequent valid hello, heartbeat or location resumes without login. It is not permanent device revocation.

## Hex frame and transport

The TG1 frame has not changed:

1. Bytes `54 47`: ASCII TG header.
2. Byte `01`: protocol version.
3. One byte: message code, e.g. `02` for location.
4. Four bytes: unsigned big-endian length of the UTF-8 JSON payload, in bytes.
5. UTF-8 JSON bytes.
6. Four bytes: CRC32b of all preceding frame bytes, big-endian.

Convert the whole binary frame to ASCII hexadecimal and append a real LF newline byte (not the literal characters backslash+n). Send over a persistent TCP socket; read newline-delimited hex replies and decode using the same frame format. Existing `docs/examples/MobileGpsProtocol.kt` can encode/decode this JSON unchanged. CRC detects corruption; it does not replace authentication or TLS.

Local listener:

```powershell
php artisan gps:listen --host=127.0.0.1 --port=9000
```

`127.0.0.1` works only on the server computer, not a physical phone. For a phone, deploy/bind the listener to a reachable LAN/public server and use its actual IP or DNS name and configured port. Non-loopback listeners require the existing TLS certificate/key options. Do not send credentials over unencrypted public TCP. No public server IP or certificate is provisioned by this change.

Run the listener in a visible terminal to see CONNECTED, RECEIVED, SAVED, LIVE_CACHED or REJECTED records. Device keys are never included in those logs.

## Database references

`gps_locations.user_id` is the tracked user. `gps_locations.device_session_id` is an internal foreign key to the registered device row, NOT a browser session or a requirement to log in. That row uses `session_id` as the IMEI/installation ID; keeping the reference preserves device-specific history and revocation. `tracking_session_id` groups tracking history and is also not login authentication.

Location fields remain: id, uuid, user_id, device_session_id, tracking_session_id, source_type, latitude, longitude, accuracy, speed, bearing, heading, altitude, battery_level, network_type, signal_strength, provider, is_mock, recorded_at, created_at.

Admin-configured movement radius controls database history saves; accepted newer fixes still update the live map when below that radius. A successful ACK can therefore have `saved: false` and `live_cached: true`.
