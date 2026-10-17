# Source fixtures

`device-*-20261017.hex` are the first login, heartbeat and SIM frames in the
user's terminal capture at 2026-10-17 11:53:44–47 IST. They contain no coordinate
fields. They report power level 1 (extremely low), GSM 4 (strong), terminal byte
00 (GPS not positioned, ACC/charging/defense off, normal alarm). IMSI and ICCID
remain encoded verbatim; trailing digits are not silently removed.

`pdf-*.hex` are verbatim compacted hexadecimal examples from Appendix B,
physical PDF page 40, of the user-provided `PT06 new Protocol2022.4.27.V2 p.pdf`.
Declared lengths and X25 CRCs validate without correction.

The login identifies **359097370008313**, model **005A**, timezone **+05:30**.
The location sample is UTC **2022-04-23 13:47:29**, latitude
**12.885258333333333**, longitude **74.879855**, speed **17 km/h**, heading
**78 degrees**, **12 satellites**, valid fix; no mileage field.
The alarm example reports code **13** (hex, demolition).

`pdf-server-command.hex` is an outbound reference example only. The listener
does not transmit it. Additional cases constructed by tests are explicitly
simulated variants and do not represent captured hardware traffic.
