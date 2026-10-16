# Folder Structure

- `app/Actions`: reserved for transactional use-case actions.
- `app/DTO`: request/response data transfer objects.
- `app/Enums`: domain enums for statuses, themes, and license types.
- `app/Helpers`: global application helper functions.
- `app/Http`: controllers, middleware, requests, and API resources.
- `app/Interfaces`: repository contracts and cross-layer abstractions.
- `app/Jobs`: queued tasks for audit, email, notifications, and activity work.
- `app/Models`: Eloquent models for users, licensing, settings, audit, and tracking.
- `app/Observers`: audit and lifecycle observers.
- `app/Policies`: authorization policies.
- `app/Repositories`: persistence implementations behind interfaces.
- `app/Services`: business services for logging, licensing, and settings.
- `database/migrations`: normalized schema for auth, settings, permissions, license management, and notifications.
- `database/seeders`: production baseline roles, permissions, settings, and license plans.
- `resources/views`: Blade views for AdminLTE dashboards and auth screens.
