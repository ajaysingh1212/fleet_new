import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/gps-watcher.js',
                'resources/js/live-map.js',
                'resources/js/private-chat.js',
                'resources/js/geofence-manager.js',
                'resources/js/report-page.js',
                'resources/js/field-task-form.js',
                'resources/js/field-task-show.js',
            ],
            refresh: true,
        }),
    ],
});
