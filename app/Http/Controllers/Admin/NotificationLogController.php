<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationLogController extends Controller
{
    public function index(): View
    {
        return view('admin.notification-logs.index', [
            'notifications' => DatabaseNotification::with('notifiable')->latest()->paginate(20),
        ]);
    }
}
