<?php

use App\Http\Controllers\LicenseTransferController;
use App\Http\Controllers\User\DeviceSessionController;
use App\Http\Controllers\User\LicenseController;
use App\Http\Controllers\User\LocationSharingController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\SettingsController;
use App\Http\Controllers\User\SupportTicketController;
use App\Http\Controllers\User\TrackingRelationController;
use App\Http\Controllers\User\FieldTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('my-licenses', [LicenseController::class, 'index'])->name('my-licenses.index');
    Route::get('my-licenses/plans', [LicenseController::class, 'plans'])->name('my-licenses.plans');
    Route::post('my-licenses/purchase', [LicenseController::class, 'purchase'])->name('my-licenses.purchase');
    Route::post('my-licenses/{userLicense}/renew', [LicenseController::class, 'renew'])->name('my-licenses.renew');
    Route::post('my-licenses/{userLicense}/use-for-self', [LicenseTransferController::class, 'useForSelf'])->name('my-licenses.use-for-self');
    Route::get('my-licenses/payment/{transaction:uuid}/return', [LicenseController::class, 'paymentReturn'])->name('my-licenses.payment-return');
    Route::post('my-licenses/payment/{transaction:uuid}/verify', [LicenseController::class, 'verifyRazorpay'])->name('my-licenses.payment-verify');

    Route::middleware('permission:use tracking workspace')->group(function () {
        Route::get('my-tracking', [TrackingRelationController::class, 'index'])->name('my-tracking.index');
        Route::get('my-tracking/create', [TrackingRelationController::class, 'create'])->name('my-tracking.create');
        Route::post('my-tracking', [TrackingRelationController::class, 'store'])->name('my-tracking.store');
        Route::post('my-tracking/managed-users', [TrackingRelationController::class, 'storeManaged'])->name('my-tracking.managed-users.store');
        Route::post('my-tracking/requests', [TrackingRelationController::class, 'sendRequest'])->name('my-tracking.requests.store');
        Route::post('my-tracking/{trackingRelation}/accept', [TrackingRelationController::class, 'accept'])->name('my-tracking.requests.accept');
        Route::post('my-tracking/{trackingRelation}/request-back', [TrackingRelationController::class, 'requestBack'])->name('my-tracking.requests.back');
        Route::delete('my-tracking/{trackingRelation}/reject', [TrackingRelationController::class, 'reject'])->name('my-tracking.requests.reject');
        Route::delete('my-tracking/{trackingRelation}', [TrackingRelationController::class, 'destroy'])->name('my-tracking.destroy');
    });

    Route::middleware('permission:manage tasks')->group(function () {
        Route::get('tasks/export', [FieldTaskController::class, 'export'])->name('tasks.export');
        Route::get('tasks/{task}/report', [FieldTaskController::class, 'report'])->name('tasks.report');
        Route::get('tasks/{task}/export', [FieldTaskController::class, 'exportTask'])->name('tasks.export-task');
        Route::resource('tasks', FieldTaskController::class);
    });

    Route::get('license-transfers', [LicenseTransferController::class, 'index'])->name('license-transfers.index');
    Route::get('license-transfers/search-users', [LicenseTransferController::class, 'searchUsers'])->name('license-transfers.search-users');
    Route::get('license-transfers/available', [LicenseTransferController::class, 'available'])->name('license-transfers.available');
    Route::post('license-transfers', [LicenseTransferController::class, 'store'])->name('license-transfers.store');

    Route::get('my-location', [LocationSharingController::class, 'index'])->name('my-location.index');

    Route::get('my-devices', [DeviceSessionController::class, 'index'])->name('my-devices.index');
    Route::post('my-devices/revoke-others', [DeviceSessionController::class, 'revokeOthers'])->name('my-devices.revoke-others');
    Route::post('my-devices/{deviceSession}/revoke', [DeviceSessionController::class, 'revoke'])->name('my-devices.revoke');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('support/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show');

    Route::get('user-settings', [SettingsController::class, 'edit'])->name('user-settings.edit');
    Route::put('user-settings', [SettingsController::class, 'update'])->name('user-settings.update');
});
