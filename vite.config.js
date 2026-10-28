import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: Number(process.env.GPS_AUDIT_VITE_PORT ?? 5173),
        hmr: {
            host: 'localhost',
            port: Number(process.env.GPS_AUDIT_VITE_PORT ?? 5173),
        },
    },
    plugins: [
        laravel({
            hotFile: process.env.GPS_AUDIT_HOT_FILE ?? 'public/hot',
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/gps-watcher.js',
                'resources/js/live-map.js',
                'resources/js/private-chat.js',
                'resources/js/geofence-manager.js',
                'resources/js/vehicle-geofences.js',
                'resources/js/report-page.js',
                'resources/js/field-task-form.js',
                'resources/js/field-task-show.js',
            ],
            refresh: true,
        }),
    ],
});
