# Live TCP check — 2026-10-16, IST

The existing Python test listener owned port 9000. Windows denied automated
termination; the user stopped it manually. A visible PowerShell terminal now
runs Laravel `gps:listen --diagnostic --diagnostic-imei=864540081540460` on
`0.0.0.0:9000`. The application process was verified in netstat. Existing tunnel
settings were left intact; no firewall changes were made.

Observed real external-device traffic after the switch:

- IMEI: 864540081540460; classic GT06 login 01, valid checksum.
- Documented serial-matching login ACK sent successfully.
- Heartbeat 13 received; ACK sent; ACC false, power level 3, GSM level 4.
- Extended 7979 type 94 packet, 38 bytes, valid checksum. Payload preserved only
  in the private diagnostic log; not interpreted as coordinates or ACKed blindly.
- 17:42:16 IST status: one current connection, two connections since restart,
  six reads, 142 received bytes, two logins, two heartbeats, zero location packets
  and zero valid fixes. Connection count varies as the tracker reconnects.

Log: `storage/logs/gps-live-diagnostic.log`. The terminal prints connection,
packet and periodic status events. Keep it open while testing. Ctrl+C stops it.

This is diagnostic observation with an explicit temporary IMEI allowlist. It
does not persist production locations or broadcast to the dashboard. Redis is
unavailable, so the normal queued production gateway was not started. The live
observations above are separate from loopback/simulated automated tests.

Validation after the changes: 61 GPS-related tests passed, one opt-in Redis test
skipped, 279 assertions. No application migrations/database reset were run.
