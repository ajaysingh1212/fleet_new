<?php

return [
    [
        'label' => 'Main Menu',
        'icon' => 'fa-solid fa-gauge-high',
        'items' => [
            ['title' => 'Dashboard', 'icon' => 'fa-solid fa-gauge-high', 'route' => 'dashboard', 'description' => 'Overview and daily status'],
        ],
    ],
    [
        'label' => 'Administration',
        'icon' => 'fa-solid fa-user-shield',
        'items' => [
            ['title' => 'Users', 'icon' => 'fa-solid fa-users', 'route' => 'admin.users.index', 'permission' => 'manage users', 'description' => 'Manage employee records'],
            ['title' => 'Roles', 'icon' => 'fa-solid fa-user-shield', 'route' => 'admin.roles.index', 'permission' => 'manage roles', 'description' => 'Access levels and scopes'],
            ['title' => 'Permissions', 'icon' => 'fa-solid fa-key', 'route' => 'admin.permissions.index', 'permission' => 'manage permissions', 'description' => 'Granular access control'],
        ],
    ],
    [
        'label' => 'Licensing',
        'icon' => 'fa-solid fa-id-card-clip',
        'items' => [
            ['title' => 'License Plans', 'icon' => 'fa-solid fa-id-card', 'route' => 'admin.license-plans.index', 'permission' => 'manage license plans', 'description' => 'Packages and pricing'],
            ['title' => 'User Licenses', 'icon' => 'fa-solid fa-file-invoice', 'route' => 'admin.user-licenses.index', 'permission' => 'manage user licenses', 'description' => 'Assigned and active plans'],
            ['title' => 'License Renewals', 'icon' => 'fa-solid fa-rotate', 'route' => 'admin.license-renewals.index', 'permission' => 'manage user licenses', 'description' => 'Renewal payments and history'],
            ['title' => 'License Transfer', 'icon' => 'fa-solid fa-right-left', 'route' => 'license-transfers.index', 'description' => 'Transfer unused licenses securely'],
            ['title' => 'Tracking Relations', 'icon' => 'fa-solid fa-diagram-project', 'route' => 'admin.tracking-relations.index', 'permission' => 'manage tracking relations', 'description' => 'Tracker and tracked pairs'],
        ],
    ],
    [
        'label' => 'System',
        'icon' => 'fa-solid fa-gears',
        'items' => [
            ['title' => 'Settings', 'icon' => 'fa-solid fa-gears', 'route' => 'admin.settings.edit', 'permission' => 'manage settings', 'description' => 'Global configuration'],
            ['title' => 'Countries', 'icon' => 'fa-solid fa-earth-americas', 'route' => 'admin.countries.index', 'permission' => 'manage countries', 'description' => 'Geo reference data'],
            ['title' => 'States', 'icon' => 'fa-solid fa-map', 'route' => 'admin.states.index', 'permission' => 'manage states', 'description' => 'Geo reference data'],
            ['title' => 'Cities', 'icon' => 'fa-solid fa-city', 'route' => 'admin.cities.index', 'permission' => 'manage cities', 'description' => 'Geo reference data'],
            ['title' => 'Languages', 'icon' => 'fa-solid fa-language', 'route' => 'admin.languages.index', 'permission' => 'manage languages', 'description' => 'Localization options'],
        ],
    ],
    [
        'label' => 'Monitoring',
        'icon' => 'fa-solid fa-chart-line',
        'items' => [
            ['title' => 'Geofences', 'icon' => 'fa-solid fa-draw-polygon', 'route' => 'admin.geofences.index', 'permission' => 'use tracking workspace', 'description' => 'Zones and exit alerts for tracked people'],
            ['title' => 'Analytics', 'icon' => 'fa-solid fa-chart-simple', 'route' => 'admin.monitoring.dashboard', 'permission' => 'manage geofences', 'description' => 'KPIs, trends, leaderboards'],
            ['title' => 'GPS History', 'icon' => 'fa-solid fa-route', 'route' => 'admin.monitoring.history', 'permission' => 'manage geofences', 'description' => 'History filters and timeline'],
            ['title' => 'Route Replay', 'icon' => 'fa-solid fa-play', 'route' => 'admin.monitoring.replay', 'permission' => 'manage geofences', 'description' => 'Smooth playback and route stats'],
            ['title' => 'Reports', 'icon' => 'fa-solid fa-file-export', 'route' => 'admin.monitoring.reports', 'description' => 'Reports and export center'],
            ['title' => 'Activity Logs', 'icon' => 'fa-solid fa-clock-rotate-left', 'route' => 'admin.activity-logs.index', 'permission' => 'view activity logs', 'description' => 'System and login timeline'],
            ['title' => 'Audit Logs', 'icon' => 'fa-solid fa-list-check', 'route' => 'admin.audit-logs.index', 'permission' => 'view audit logs', 'description' => 'Old vs new value trail'],
            ['title' => 'Device Sessions', 'icon' => 'fa-solid fa-display', 'route' => 'admin.device-sessions.index', 'permission' => 'manage device sessions', 'description' => 'Active logins across users'],
        ],
    ],
    [
        'label' => 'Communication',
        'icon' => 'fa-solid fa-comments',
        'items' => [
            ['title' => 'Notification Templates', 'icon' => 'fa-solid fa-envelope-open-text', 'route' => 'admin.notification-templates.index', 'permission' => 'manage notification templates', 'description' => 'Reusable message templates'],
            ['title' => 'Notification Log', 'icon' => 'fa-regular fa-bell', 'route' => 'admin.notification-logs.index', 'permission' => 'manage notification templates', 'description' => 'Delivered notifications'],
            ['title' => 'Support Tickets', 'icon' => 'fa-solid fa-headset', 'route' => 'admin.support-tickets.index', 'permission' => 'manage support tickets', 'description' => 'Respond to open tickets'],
        ],
    ],
    [
        'label' => 'My Workspace',
        'icon' => 'fa-solid fa-briefcase',
        'items' => [
            ['title' => 'Profile', 'icon' => 'fa-solid fa-user-gear', 'route' => 'profile.edit', 'description' => 'Account and security'],
            ['title' => 'Live Map', 'icon' => 'fa-solid fa-map-location-dot', 'route' => 'live-map.index', 'description' => 'Realtime location of tracked people'],
            ['title' => 'Tasks', 'icon' => 'fa-solid fa-list-check', 'route' => 'tasks.index', 'permission' => 'manage tasks', 'description' => 'Assign and complete field work', 'badge' => 'assigned_tasks'],
            ['title' => 'Private Chats', 'icon' => 'fa-solid fa-comments', 'route' => 'chats.index', 'description' => 'Direct messages with your team', 'badge' => 'unread_messages'],
            ['title' => 'Share My Location', 'icon' => 'fa-solid fa-location-crosshairs', 'route' => 'my-location.index', 'description' => 'Broadcast this browser\'s position'],
            ['title' => 'Friends & Tracking', 'icon' => 'fa-solid fa-user-group', 'route' => 'my-tracking.index', 'permission' => 'use tracking workspace', 'description' => 'Requests, friends and tracking access', 'badge' => 'pending_tracking_requests'],
            ['title' => 'My Licenses', 'icon' => 'fa-solid fa-id-badge', 'route' => 'my-licenses.index', 'description' => 'Assigned and available licenses'],
            ['title' => 'My Devices', 'icon' => 'fa-solid fa-mobile-screen-button', 'route' => 'my-devices.index', 'description' => 'Sessions and sign-ins'],
            ['title' => 'Notifications', 'icon' => 'fa-regular fa-bell', 'route' => 'notifications.index', 'description' => 'Alerts and updates', 'badge' => 'unread_notifications'],
            ['title' => 'Support', 'icon' => 'fa-solid fa-headset', 'route' => 'support.index', 'description' => 'Tickets and help'],
            ['title' => 'My Settings', 'icon' => 'fa-solid fa-sliders', 'route' => 'user-settings.edit', 'description' => 'Personal preferences'],
        ],
    ],
];
