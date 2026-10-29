# PT06 device command panel

GPS Devices → Vehicles → Commands opens the catalog and the most recent 20
requests/replies for that tracker. Query buttons send their displayed command.
Configure/Prepare action fills the editor; review the exact command and confirm
before sending a setting or action. Replies are plain text, including errors;
`replied` is not a claim that the device accepted the requested setting.

The catalog includes all 39 groups in the supplied `PT06 All SMS Command.pdf`
(four pages, whose internal heading is **EV02 SMS Command**). This mismatch is
retained here: support for each command on a particular PT06-compatible firmware
is not assumed. Ranges absent from the manual are explicitly labeled as
application limits. Protocol-only movement/geofence commands with ambiguous
syntax are not invented. The list is maintained in `config/pt06_commands.php`.

Transport uses the authenticated PT06 connection in `gps:diagnose --persist`
(`gps:listen --diagnostic --persist --registered`, including `artisan serve`).
The separate Redis production gateway does not advertise this command transport;
its devices cannot enqueue commands through this feature yet.

Run the additive `2026_10_29_000001_create_gps_device_commands_table.php` migration
and restart the receiver after deploying. No SMS provider is used. A missing
TCP connection disables sending; commands do not wait indefinitely for an
offline device. A reconnect replaces older sockets for the same IMEI.

Each request records its administrator, UUID, device, authenticated session,
exact command, timestamps and actual reply. Device row locks serialize requests;
UUID retries do not duplicate sends. A request expires after 30 seconds before
dispatch; after a complete socket write, it waits 60 seconds for a reply. Partial
write/disconnect/timeout never triggers an automatic retry. Access uses the
existing administrator-only hardware policy and separate read/write rate limits.
Relay power-cut additionally requires fresh stationary GPS and ignition OFF,
checked both when queued and immediately before dispatch.

Outgoing 0x80 uses CRC-ITU/X25, a unique 32-bit server flag (command ID), and a
16-bit serial. The supplied protocol manual's section 6.1 table mentions two
language bytes, but its Appendix B CRC-valid command fixture omits them. The
encoder now follows section 6.1 with the English language field (0002), outside the command length but inside packet length and CRC. The legacy Appendix B form remains covered by an explicit encoder test. Section 6.2 replies (0x21) support ASCII and
UTF-16BE. Results are matched by flag, device and TCP session; another device's
response cannot complete the request. HTML in replies is never rendered as HTML.

For BR52C6695, the observed 29 October 2026 heartbeat reports GSM strong, charging,
ACC OFF and GPS not positioned. Login/heartbeat/SIM packets contain no coordinates.
Online indicates communication, not a satellite fix. STATUS#, WHERE#, SENDS# and
TIMER# can inspect the tracker; LJDW# is the documented GPS wake action. A returned
text location is not silently promoted to a fresh validated position on the map.

Live verification: STATUS# was fully written to BR52C6695's socket on 29 October 2026 at 13:07:09 IST. The peer closed at 13:07:13 without a command response, so its history correctly records failed/unknown execution. Real hardware reply compatibility remains unconfirmed; decoder and matching tests pass.

Follow-up: the user confirmed PT06. Both STATUS# and VERSION# with the section 6.1 language field were fully dispatched, but the device disconnected without replying. The language-field correction alone did not resolve the hardware issue. Even without commands, this device reconnects about 65 seconds after login. Firmware/version via SMS or vendor confirmation is required before selecting another command variant; do not claim commands are confirmed working or automatically resend previous requests.
