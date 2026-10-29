<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\DeviceModelController;
use App\Http\Controllers\Admin\DeviceSessionController;
use App\Http\Controllers\Admin\GeofenceController;
use App\Http\Controllers\Admin\GeoLookupController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\GpsDeviceController;
use App\Http\Controllers\Admin\GpsDeviceCommandController;
use App\Http\Controllers\Admin\HardwareGpsHistoryController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LicensePlanController;
use App\Http\Controllers\Admin\LicenseRenewalController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TrackingRelationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserLicenseController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleGeofenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('gps-devices/{gpsDevice}/commands', [GpsDeviceCommandController::class, 'index'])
        ->middleware(['can:manage,App\Models\GpsDevice', 'throttle:60,1,gps-command-read'])->name('gps-devices.commands.index');
    Route::post('gps-devices/{gpsDevice}/commands', [GpsDeviceCommandController::class, 'store'])
        ->middleware(['can:manage,App\Models\GpsDevice', 'throttle:6,1,gps-command-write'])->name('gps-devices.commands.store');
    Route::middleware(['can:viewAny,App\Models\GpsDevice', 'throttle:60,1'])->group(function () {
        Route::get('monitoring/history', [HardwareGpsHistoryController::class, 'index'])->name('monitoring.history');
        Route::get('monitoring/history/targets', [HardwareGpsHistoryController::class, 'targets'])->name('monitoring.history.targets');
        Route::get('monitoring/history/data', [HardwareGpsHistoryController::class, 'data'])->name('monitoring.history.data');
        Route::get('vehicle-geofences/targets', [VehicleGeofenceController::class, 'targets'])->name('vehicle-geofences.targets');
        Route::delete('vehicle-geofences/{id}', [VehicleGeofenceController::class, 'destroy'])->whereNumber('id')->name('vehicle-geofences.destroy');
        Route::get('vehicle-geofences/vehicles', [VehicleGeofenceController::class, 'vehicles'])->name('vehicle-geofences.vehicles');
        Route::get('vehicle-geofences/alerts', [VehicleGeofenceController::class, 'alerts'])->name('vehicle-geofences.alerts');
        Route::get('vehicle-geofences', [VehicleGeofenceController::class, 'index'])->name('vehicle-geofences.index');
        Route::post('vehicle-geofences', [VehicleGeofenceController::class, 'store'])->name('vehicle-geofences.store');
        Route::put('vehicle-geofences/{id}', [VehicleGeofenceController::class, 'update'])->whereNumber('id')->name('vehicle-geofences.update');
        Route::patch('vehicle-geofences/{id}/status', [VehicleGeofenceController::class, 'status'])->whereNumber('id')->name('vehicle-geofences.status');
        Route::get('gps-devices/diagnostics', [GpsDeviceController::class, 'diagnostics'])->name('gps-devices.diagnostics');
        Route::get('gps-devices/fleet', [GpsDeviceController::class, 'fleet'])->name('gps-devices.fleet');
        Route::get('gps-devices/{gpsDevice}/documents/{index}', [GpsDeviceController::class, 'document'])->whereNumber('index')->name('gps-devices.document');
        Route::resource('gps-devices', GpsDeviceController::class)->except('destroy')->parameters(['gps-devices' => 'gpsDevice']);
        Route::get('vehicles/{vehicle}/documents/{index}', [VehicleController::class, 'document'])->whereNumber('index')->name('vehicles.document');
        Route::get('vehicles/from-device/{gpsDevice}/edit', [VehicleController::class, 'fromDevice'])->name('vehicles.from-device');
        Route::post('vehicles/from-device/{gpsDevice}', [VehicleController::class, 'attachDevice'])->name('vehicles.attach-device');
        Route::resource('vehicles', VehicleController::class);
        Route::resource('device-models', DeviceModelController::class)->except('show')->parameters(['device-models' => 'deviceModel']);
    });
    Route::middleware('permission:manage users')->group(function () {
        Route::get('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore')->where('id', '[0-9]+');
        Route::resource('users', UserController::class);
    });

    Route::middleware('permission:manage roles')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    Route::middleware('permission:manage permissions')->group(function () {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    });

    Route::middleware('permission:manage license plans')->group(function () {
        Route::resource('license-plans', LicensePlanController::class)
            ->except(['show'])
            ->parameters(['license-plans' => 'licensePlan']);
    });

    Route::middleware('permission:manage user licenses')->group(function () {
        Route::get('license-renewals', [LicenseRenewalController::class, 'index'])->name('license-renewals.index');
        Route::post('user-licenses/{userLicense}/extend', [UserLicenseController::class, 'extend'])->name('user-licenses.extend');
        Route::post('user-licenses/{userLicense}/cancel', [UserLicenseController::class, 'cancel'])->name('user-licenses.cancel');
        Route::resource('user-licenses', UserLicenseController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['user-licenses' => 'userLicense']);
    });

    Route::middleware('permission:manage tracking relations')->group(function () {
        Route::resource('tracking-relations', TrackingRelationController::class)
            ->except(['show'])
            ->parameters(['tracking-relations' => 'trackingRelation']);
    });

    Route::get('geofences', [GeofenceController::class, 'index'])
        ->middleware('permission:manage geofences|manage tracked geofences|use tracking workspace')->name('geofences.index');

    Route::middleware('permission:manage geofences|manage tracked geofences')->group(function () {
        Route::get('monitoring/dashboard', [MonitoringController::class, 'dashboard'])->name('monitoring.dashboard');
        Route::get('monitoring/replay', [MonitoringController::class, 'replay'])->name('monitoring.replay');
    });

    // Open to every authenticated user: MonitoringReportAccessService scopes the
    // visible users/reports to admins (see all tracked) vs regular trackers (see only their own).
    Route::get('monitoring/reports', [MonitoringController::class, 'reports'])->name('monitoring.reports');

    Route::middleware('permission:manage settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:manage countries')->group(function () {
        Route::resource('countries', CountryController::class)->except(['show']);
    });

    Route::middleware('permission:manage states')->group(function () {
        Route::resource('states', StateController::class)->except(['show']);
    });

    Route::middleware('permission:manage cities')->group(function () {
        Route::resource('cities', CityController::class)->except(['show']);
    });

    Route::middleware('permission:manage languages')->group(function () {
        Route::resource('languages', LanguageController::class)->except(['show']);
    });

    Route::middleware(['permission:manage users|manage cities|manage states'])->group(function () {
        Route::get('geo/states/{country}', [GeoLookupController::class, 'states'])->name('geo.states');
        Route::get('geo/cities/{state}', [GeoLookupController::class, 'cities'])->name('geo.cities');
    });

    Route::middleware('permission:view activity logs')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    Route::middleware('permission:view audit logs')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });

    Route::middleware('permission:manage device sessions')->group(function () {
        Route::get('device-sessions', [DeviceSessionController::class, 'index'])->name('device-sessions.index');
        Route::post('device-sessions/{deviceSession}/revoke', [DeviceSessionController::class, 'revoke'])->name('device-sessions.revoke');
    });

    Route::middleware('permission:manage notification templates')->group(function () {
        Route::resource('notification-templates', NotificationTemplateController::class)->except(['show']);
        Route::get('notification-logs', [NotificationLogController::class, 'index'])->name('notification-logs.index');
    });

    Route::middleware('permission:manage support tickets')->group(function () {
        Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::put('support-tickets/{supportTicket}/respond', [SupportTicketController::class, 'respond'])->name('support-tickets.respond');
    });

    Route::middleware(['permission:manage users|manage license plans|manage user licenses|manage settings|view activity logs'])->group(function () {
        Route::get('search', [GlobalSearchController::class, 'index'])->name('search');
    });
});
