# Validation — 2026-10-16

Live-listener follow-up: **61 passed, 1 opt-in Redis network test skipped,
279 assertions**. A real TCP subprocess diagnostic test verifies fragmented
login, documented ACK and simulated location handling without Redis/SQL.
The user-supplied BT50 login capture verifies checksum/identifier decoding.
Live external tracker observations are separately recorded in
`GPS-LIVE-CHECK.md`; login/heartbeat and extended status packets arrived, while
no valid position packet had arrived at the recorded observation time.

Latest registration follow-up: device creation and updates no longer acquire
Redis registry locks. Database writes succeed for all valid statuses during a
gateway outage, and authorization sync retries through the scheduler every
minute. Tests now cover blocked/renamed devices being rejected by SQL worker
authorization even with an older active cache, current-state synchronization,
retained uploads, and validation errors appearing only once. These replace the
earlier tests that expected active registration/edits to be blocked by Redis.
All checks for this follow-up use in-memory SQLite; no application migration
or database reset was run.

Latest targeted result: **54 passed, 1 opt-in Redis network test skipped,
249 assertions**. Blade compilation and whitespace checks also pass.

Redis-outage follow-up: the hardware + registered/mobile GPS regression run
now passes **51 tests (225 assertions)** with the opt-in Redis network test
skipped. Six new tests cover inactive registration during a Redis outage,
rejected activation/edits, retained input, safe warnings, upload cleanup,
post-commit synchronization failure and registry lock contention. Blade
compilation and `git diff --check` pass. These tests use in-memory SQLite and
simulated Redis failures; no application database migration was run for this fix.
The following table records the earlier complete implementation checks.

| Check | Result |
|---|---|
| Hardware feature tests, SQLite in memory | 25 passed |
| Hardware feature tests, verified random MySQL database | 25 passed, 90 assertions; database removed afterward |
| Hardware + existing registered/mobile GPS tests | 45 passed, 1 opt-in Redis network test skipped, 190 assertions |
| Entire final PHPUnit suite | 205 passed, 28 failed, 1 skipped, 801 assertions |
| Entire unmodified Git HEAD suite in isolated source directory | 180 passed, same 28 failures |
| Frontend production build (`npm.cmd run build`) | Passed; existing bundle-size advisory remains |
| Blade compilation (`php artisan view:cache`) | Passed |
| Changed/new PHP syntax checks | 43 files passed |
| `git diff --check` | Passed |
| `gps:protocols` | Lists implemented versions and unsupported families explicitly |

The 28 baseline failures are in communication/attachment/group/notification
tests and two broadcast authorization cases. Their fixtures create active
tracking relations without the license requirements enforced by the existing
`usableForTracking()` scope. They reproduce on unmodified HEAD and were not
fixed by weakening existing authorization. The baseline source copy and vendor
junctions were removed after comparison.

Redis was not listening on local port 6379. The opt-in real Redis/TCP test is
therefore unexecuted; no claim is made that socket/restart behavior has been
verified against a live Redis server in this environment. Reconnect, framing
and session behavior pass the gateway integration tests using synchronous
queued handlers, while the existing TG1 TLS socket process test passes.

Live hardware, firmware-specific IO mappings, cloud firewall changes, systemd
operation on Ubuntu, live WSS browser delivery and production load are not
verified here. Use the acceptance steps and compatibility limits in
`GPS-IMPLEMENTATION.md`.

The destructive initial MySQL runner failure is recorded separately in
`GPS-DATABASE-INCIDENT.md`. Passing isolated tests do not restore the affected
local application database. Recovery remains outstanding pending a backup.
