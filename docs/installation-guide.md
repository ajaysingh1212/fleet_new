# Installation Guide

## Requirements

- PHP 8.4 compatible runtime
- Composer 2.x
- Node.js 20+
- MySQL 8+
- Redis

## Setup

1. Install backend dependencies with `composer install`.
2. Install frontend dependencies with `npm install`.
3. Copy `.env.example` to `.env` and set MySQL, Redis, mail, queue, and app URL values.
4. Generate the application key with `php artisan key:generate`.
5. Run migrations and baseline seeders with `php artisan migrate --seed`.
6. Link public storage with `php artisan storage:link`.
7. Build assets with `npm run build`.

## Development

Use `composer run dev` to run the app server, queue listener, logs, and Vite watcher together.
