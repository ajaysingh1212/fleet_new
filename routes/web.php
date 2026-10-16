<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveMapController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/live-map', [LiveMapController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('live-map.index');

Route::get('/live-map/snapshot', [LiveMapController::class, 'snapshot'])
    ->middleware(['auth', 'verified'])->name('live-map.snapshot');

Route::post('/live-map/marker/{user}', [\App\Http\Controllers\MapMarkerController::class, 'update'])
    ->middleware(['auth', 'verified'])->name('live-map.marker.update');
Route::get('/live-map/marker/{user}/image', [\App\Http\Controllers\MapMarkerController::class, 'image'])
    ->middleware(['auth', 'verified'])->name('live-map.marker.image');

Route::get('/live-map/route-history/{user}/download', [\App\Http\Controllers\RouteHistoryController::class, 'download'])
    ->middleware(['auth', 'verified'])->name('live-map.route-history.download');
Route::get('/live-map/route-history/{user}', [\App\Http\Controllers\RouteHistoryController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('live-map.route-history');

Route::post('/live-map/self-tracking', [LiveMapController::class, 'toggleSelfTracking'])
    ->middleware(['auth', 'verified'])
    ->name('live-map.self-tracking');

Route::get('/chats', [ChatController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('chats.index');

Route::post('/presence/heartbeat', fn () => response()->noContent())
    ->middleware(['auth', 'verified'])
    ->name('presence.heartbeat');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/user.php';
