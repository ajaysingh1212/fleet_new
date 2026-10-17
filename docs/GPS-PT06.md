# PT06 V02 listener

Source: user-supplied `PT06 new Protocol2022.4.27.V2 p.pdf`, 43 physical PDF pages.
Adapter: `pt06`; registration version: `2022.4.27-v2`. This is a separate
profile from classic GT06 v1.8.1 and Concox X1. Select this profile in the
device registration for production reception. Existing records are not silently
reconfigured. Legacy IMEI-only logins are resolved against the active registration.

## Implemented inbound layouts

| ID | Manual section | Decoded fields | Reply |
|---|---|---|---|
| 01 | 5.1, PDF pp. 8–10; Appendix C | BCD IMEI, optional model, timezone sign/hours/minutes, language | Serial-matched ACK |
| 22 | 5.2, pp. 11–14 | UTC date, satellites, signed coordinates, km/h, heading, fix, differential flag, MCC/MNC/LAC/cell, ACC, reserved reporting mode, retransmission, optional mileage in metres | None specified |
| 26 | 5.3, pp. 14–20 | GPS and cell fields, relay, charging, ACC, defense, terminal alarm, voltage level, GSM level, alarm code, language | Serial-matched ACK |
| 13 | 5.4, pp. 20–24 | Terminal flags, power/GSM levels, language; FX external supply voltage using extension byte / 10 | Serial-matched ACK |
| 24 | 5.5, pp. 25–27 | UTC date, timing advance, MCC/MNC, count, five fixed tower slots with LAC/cell/RSSI, reserved bytes | None specified |
| 94/0A | 5.6, p. 28 | IMEI, encoded IMSI/ICCID, optional door/air-conditioning bits | None specified |
| 21 | 6.2, p. 30 | Server flag and ASCII or UTF-16BE response | None specified |
| 2A | 7, pp. 36–37 | GPS, 21-byte phone field preserved verbatim and in HEX, language | Requires address service; no fabricated address |

Framing accepts `7878` one-byte length and `7979` two-byte length. Both validate
declared length, CRLF and CRC-ITU/X25 before decoding. Short and extended packets
can be fragmented across TCP reads or coalesced in one read. Outbound commands
such as relay control, reboot, phone configuration and fences are not sent.

The GPS scale is unsigned raw / 1,800,000, with hemisphere signs from course/status.
GPS timestamps are UTC regardless of login timezone. A heartbeat's GPS-positioned
flag contains no coordinates. LBS measurements contain no coordinates either:
a tower lookup service would be required. Invalid fixes are shown explicitly in
diagnostics and are excluded from production latest-position/history storage.
Power level 0–6 is an ordinal level, not percentage or measured battery voltage.
Only FX heartbeat encoding reports measured external supply voltage.

## Manual limitations

* Section 4.3 names 27 (timezone) and 2C (Wi-Fi), but supplies no payload layout.
  Their raw type/payload is preserved and reported as unsupported, without ACK.
* General-information subtypes other than 0A are named without field encodings.
  They are reported with subtype, raw bytes and an explicit undecoded status.
* Appendix B includes older 15/12/16 examples. Those are not substituted for
  detailed 21/22/26 layouts; unknown variants remain visible as raw data.
* Model identifiers 0053 and 0054 have named capabilities. Other model codes
  (including the sample's 005A) are preserved without inventing model capabilities.
* Only the explicit diagnostic IMEI receives ACKs. Production requires an active
  registered device. Statements in the manual do not override this allowlist.

## Terminal

```powershell
cd D:\fleet_new
php artisan gps:listen --host=0.0.0.0 --port=9000 --diagnostic --diagnostic-protocol=pt06 "--diagnostic-imei=864540081540460,861128068689493" --format=pretty
```

Stop the previous port-9000 listener with Ctrl+C before starting this command.
Comma-separated IMEIs allow up to 32 explicit devices. Each has its own heartbeat,
SIM, position and packet counters; summaries never borrow another device's fix.
Other identifiers are rejected without ACK. This flag is diagnostic-only and does
not create or activate production database registrations.
The current build prints `decoded-device-summary-v2` at startup. A terminal
still showing `GPS tracking enabled` with no PT06 profile/summary banner is
running older code and must be restarted. PHP socket processes retain their
loaded classes until restart.

`DEVICE STATUS` combines the last received heartbeat, SIM identifiers and
position, without assigning coordinates to packets that contain none. Periodic
status distinguishes the allowlisted device's TCP connection from other peers
and labels retained values as last reported. In the user's 2026-10-17 capture,
only 01, 13 and 94/0A arrive: power 1 is extremely low, GSM 4 is strong, GPS is
not positioned, ACC and charging are off. This does not establish why the
device has no fix or why it closes the TCP connection. It supplies no latitude,
longitude, satellite count, speed or cell-tower measurements.

`tools/start-gps-live.ps1` now enables explicit local fleet storage and mirrors
terminal output to `storage/logs/gps-live-diagnostic.log`; see GPS-FLEET.md.
Default diagnostic mode uses no Redis or database and does not store/broadcast
positions. `--persist --registered` enables SQL storage for active registrations
and admin-map polling without Redis. Production mode still requires Redis and
the hardware queue worker described in GPS-IMPLEMENTATION.md.

## Validation and generated files

Appendix B packet examples are fixtures in `tests/Fixtures/gps/pt06`. Tests check
their exact ACKs, UTC coordinates, alarm and heartbeat fields, plus simulated
mileage/hemisphere/voltage/LBS/SIM/text/address variants, rejection paths, real TCP
fragments/coalescing and registered-profile persistence in an isolated test DB.
Sample coordinates are test data, not a live device position.

The temporary PDF.js tool installation under `tmp/pdf-tools` caused the untracked
`.bcmap` files. It has been deleted; `/tmp/` and listener lock files are ignored.
The listener does not install PDF tools or generate PDF pages. The source PDF
in Downloads is unchanged.
