# Production Checklist

- Configure `APP_ENV=production`, `APP_DEBUG=false`, and a valid `APP_URL`.
- Configure MySQL and Redis credentials.
- Set queue worker supervision for queued jobs.
- Configure mail transport before enabling email verification and password reset in production.
- Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
- Build assets with `npm run build`.
- Configure HTTPS at the web server or load balancer.
- Review support email, support phone, registration mode, and license slot return settings after seeding.
- Create the first privileged account and assign `Super Admin`.
