# Local Laravel + GPS startup

Run `php artisan serve` from the project directory. In `APP_ENV=local`, this
starts the HTTP development server and the registered-device GPS TCP receiver
on `GPS_HOST` / `GPS_PORT` (defaults: `0.0.0.0:9000`). No Python listener is
needed. Stop `C:\gps-test\test_listener.py` before starting Laravel because
only one listener can own port 9000.

The receiver uses the same PT06 diagnostic/persistence mode as
`tools/start-gps-realtime.ps1`. Packet
output goes to `storage/logs/gps-serve.log`. Stopping this serve command stops
its receiver too. A receiver startup failure prevents HTTP startup; a receiver
crash also stops this development server and prints an error.

In local development, this also starts the GPS queue worker pool and Reverb
WebSocket server so received packets reach the live map and history. Existing
worker pools and WebSocket listeners are reused and left running on exit.
Processes started by this command stop with it. Their output is written to
`storage/logs/gps-serve-workers.log` and `storage/logs/gps-serve-reverb.log`.
Set `GPS_SERVE_REALTIME=false` only if those services are managed separately.

If a receiver is already managed separately (for example by
`tools/start-gps-realtime.ps1`), use `php artisan serve --without-gps`.
Set `GPS_SERVE_AUTOSTART=false` to disable automatic startup, or explicitly
enable it with `GPS_SERVE_AUTOSTART=true` in another environment. Production
continues to use the deployment services rather than the development server.
Restart the command after changing GPS bind settings.
