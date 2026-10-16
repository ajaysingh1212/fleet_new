<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\Auth\TokenLoginController;
use App\Http\Controllers\Api\V1\Auth\TokenLogoutController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DiagnosticController;
use App\Http\Controllers\Api\V1\GeofenceAssignmentController;
use App\Http\Controllers\Api\V1\GeofenceController;
use App\Http\Controllers\Api\V1\GpsLocationController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\LicensePlanController;
use App\Http\Controllers\Api\V1\LocationShareController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MessageReactionController;
use App\Http\Controllers\Api\V1\Monitoring\AnalyticsController;
use App\Http\Controllers\Api\V1\Monitoring\AttendanceController;
use App\Http\Controllers\Api\V1\Monitoring\DashboardController as MonitoringDashboardController;
use App\Http\Controllers\Api\V1\Monitoring\ExportController;
use App\Http\Controllers\Api\V1\Monitoring\HeatmapController;
use App\Http\Controllers\Api\V1\Monitoring\HistoryController;
use App\Http\Controllers\Api\V1\Monitoring\ReplayController;
use App\Http\Controllers\Api\V1\Monitoring\ReportController;
use App\Http\Controllers\Api\V1\Monitoring\StatisticsController;
use App\Http\Controllers\Api\V1\Monitoring\TimelineController;
use App\Http\Controllers\Api\V1\RouteProposalController;
use App\Http\Controllers\Api\V1\UserLicenseController;
use App\Http\Controllers\User\LicenseController as UserLicensePaymentController;
use Illuminate\Support\Facades\Route;

Route::post('payments/payu/callback', [UserLicensePaymentController::class, 'payuCallback'])->name('payments.payu.callback');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', TokenLoginController::class)->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', AuthenticatedUserController::class)->name('me');
        Route::post('auth/logout', TokenLogoutController::class)->name('auth.logout');
        Route::apiResource('license-plans', LicensePlanController::class)->only(['index', 'store']);
        Route::apiResource('user-licenses', UserLicenseController::class)->only(['index', 'store']);
        Route::post('gps/locations', [GpsLocationController::class, 'store'])->name('gps.locations.store');
        Route::post('gps/heartbeat', fn () => response()->noContent())->name('gps.heartbeat');
        Route::post('gps/locations/batch', [GpsLocationController::class, 'batchStore'])->name('gps.locations.batch');
        Route::post('gps/diagnostics', [DiagnosticController::class, 'store'])->name('gps.diagnostics.store');
        Route::get('users/{user}/diagnostics', [DiagnosticController::class, 'show'])->name('users.diagnostics.show');

        Route::post('location-sharing/stop-request', [LocationShareController::class, 'requestStop'])->name('location-sharing.stop-request');
        Route::post('location-sharing/stop-requests/{stopRequest:uuid}/respond', [LocationShareController::class, 'respond'])->name('location-sharing.stop-requests.respond');

        Route::get('conversations/contacts', [ConversationController::class, 'contacts'])->name('conversations.contacts');
        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
        Route::get('conversations/{conversation:uuid}/messages', [MessageController::class, 'index'])->name('conversations.messages.index');
        Route::post('conversations/{conversation:uuid}/messages', [MessageController::class, 'store'])->name('conversations.messages.store');
        Route::post('conversations/{conversation:uuid}/delivered', [MessageController::class, 'markDelivered'])->name('conversations.delivered');
        Route::post('conversations/{conversation:uuid}/read', [MessageController::class, 'markRead'])->name('conversations.read');
        Route::get('conversations/{conversation:uuid}/attachments', [AttachmentController::class, 'index'])->name('conversations.attachments.index');
        Route::post('conversations/{conversation:uuid}/attachments', [AttachmentController::class, 'store'])->name('conversations.attachments.store');

        Route::get('attachments/{attachment:uuid}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::get('attachments/{attachment:uuid}/thumbnail', [AttachmentController::class, 'thumbnail'])->name('attachments.thumbnail');

        Route::patch('messages/{message:uuid}', [MessageController::class, 'update'])->name('messages.update');
        Route::delete('messages/{message:uuid}', [MessageController::class, 'destroy'])->name('messages.destroy');
        Route::delete('messages/{message:uuid}/for-me', [MessageController::class, 'destroyForMe'])->name('messages.destroy-for-me');
        Route::post('messages/{message:uuid}/pin', [MessageController::class, 'pin'])->name('messages.pin');
        Route::delete('messages/{message:uuid}/pin', [MessageController::class, 'unpin'])->name('messages.unpin');
        Route::post('messages/{message:uuid}/star', [MessageController::class, 'star'])->name('messages.star');
        Route::delete('messages/{message:uuid}/star', [MessageController::class, 'unstar'])->name('messages.unstar');
        Route::post('messages/{message:uuid}/cancel-live-location', [MessageController::class, 'cancelLiveLocation'])->name('messages.cancel-live-location');
        Route::post('messages/{message:uuid}/react', [MessageReactionController::class, 'store'])->name('messages.react');
        Route::delete('messages/{message:uuid}/react', [MessageReactionController::class, 'destroy'])->name('messages.unreact');

        Route::get('conversations/{conversation:uuid}/route-proposal', [RouteProposalController::class, 'show'])->name('conversations.route-proposal.show');
        Route::post('conversations/{conversation:uuid}/route-proposal', [RouteProposalController::class, 'store'])->name('conversations.route-proposal.store');
        Route::patch('route-proposals/{routeProposal:uuid}', [RouteProposalController::class, 'update'])->name('route-proposals.update');
        Route::post('route-proposals/{routeProposal:uuid}/accept', [RouteProposalController::class, 'accept'])->name('route-proposals.accept');
        Route::post('route-proposals/{routeProposal:uuid}/reject', [RouteProposalController::class, 'reject'])->name('route-proposals.reject');
        Route::delete('route-proposals/{routeProposal:uuid}', [RouteProposalController::class, 'destroy'])->name('route-proposals.destroy');

        Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
        Route::get('groups/{conversation:uuid}', [GroupController::class, 'show'])->name('groups.show');
        Route::patch('groups/{conversation:uuid}', [GroupController::class, 'update'])->name('groups.update');
        Route::delete('groups/{conversation:uuid}', [GroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('groups/{conversation:uuid}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
        Route::delete('groups/{conversation:uuid}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.remove');
        Route::post('groups/{conversation:uuid}/members/{user}/promote', [GroupController::class, 'promote'])->name('groups.members.promote');
        Route::post('groups/{conversation:uuid}/members/{user}/demote', [GroupController::class, 'demote'])->name('groups.members.demote');
        Route::post('groups/{conversation:uuid}/leave', [GroupController::class, 'leave'])->name('groups.leave');

        Route::get('webrtc/ice-servers', [CallController::class, 'iceServers'])->name('webrtc.ice-servers');
        Route::get('conversations/{conversation:uuid}/calls', [CallController::class, 'index'])->name('conversations.calls.index');
        Route::post('conversations/{conversation:uuid}/calls', [CallController::class, 'store'])->name('conversations.calls.store');
        Route::post('calls/{call:uuid}/accept', [CallController::class, 'accept'])->name('calls.accept');
        Route::post('calls/{call:uuid}/reject', [CallController::class, 'reject'])->name('calls.reject');
        Route::post('calls/{call:uuid}/leave', [CallController::class, 'leave'])->name('calls.leave');
        Route::post('calls/{call:uuid}/missed', [CallController::class, 'missed'])->name('calls.missed');
        Route::patch('calls/{call:uuid}', [CallController::class, 'update'])->name('calls.update');

        Route::get('geofences/export', [GeofenceController::class, 'export'])->name('geofences.export');
        Route::get('geofences/trackable-users', [GeofenceController::class, 'trackableUsers'])->name('geofences.trackable-users');
        Route::post('geofences/import', [GeofenceController::class, 'import'])->name('geofences.import');
        Route::get('geofences', [GeofenceController::class, 'index'])->name('geofences.index');
        Route::post('geofences', [GeofenceController::class, 'store'])->name('geofences.store');
        Route::get('geofences/{geofence:uuid}', [GeofenceController::class, 'show'])->name('geofences.show');
        Route::patch('geofences/{geofence:uuid}', [GeofenceController::class, 'update'])->name('geofences.update');
        Route::delete('geofences/{geofence:uuid}', [GeofenceController::class, 'destroy'])->name('geofences.destroy');
        Route::post('geofences/{geofence:uuid}/duplicate', [GeofenceController::class, 'duplicate'])->name('geofences.duplicate');
        Route::post('geofences/{geofence:uuid}/activate', [GeofenceController::class, 'activate'])->name('geofences.activate');
        Route::post('geofences/{geofence:uuid}/deactivate', [GeofenceController::class, 'deactivate'])->name('geofences.deactivate');
        Route::post('geofences/{geofence:uuid}/archive', [GeofenceController::class, 'archive'])->name('geofences.archive');
        Route::post('geofences/{geofence:uuid}/restore', [GeofenceController::class, 'restore'])->name('geofences.restore');
        Route::get('geofences/{geofence:uuid}/events', [GeofenceController::class, 'events'])->name('geofences.events');

        Route::get('geofence-assignments', [GeofenceAssignmentController::class, 'index'])->name('geofence-assignments.index');
        Route::post('geofence-assignments', [GeofenceAssignmentController::class, 'store'])->name('geofence-assignments.store');
        Route::get('geofence-assignments/{geofenceAssignment:uuid}', [GeofenceAssignmentController::class, 'show'])->name('geofence-assignments.show');
        Route::patch('geofence-assignments/{geofenceAssignment:uuid}', [GeofenceAssignmentController::class, 'update'])->name('geofence-assignments.update');
        Route::delete('geofence-assignments/{geofenceAssignment:uuid}', [GeofenceAssignmentController::class, 'destroy'])->name('geofence-assignments.destroy');
        Route::get('geofence-assignments/{geofenceAssignment:uuid}/runs', [GeofenceAssignmentController::class, 'runs'])->name('geofence-assignments.runs');

        Route::get('history', HistoryController::class)->name('history.index');
        Route::get('replay', ReplayController::class)->name('replay.show');
        Route::get('analytics', AnalyticsController::class)->name('analytics.index');
        Route::get('attendance', AttendanceController::class)->name('attendance.index');
        Route::get('timeline', TimelineController::class)->name('timeline.index');
        Route::get('heatmap', HeatmapController::class)->name('heatmap.index');
        Route::get('dashboard-analytics', MonitoringDashboardController::class)->name('dashboard-analytics.index');
        Route::get('statistics', StatisticsController::class)->name('statistics.index');
        Route::get('reports', ReportController::class)->name('reports.index');
        Route::get('exports', ExportController::class)->name('exports.download');
    });
});
