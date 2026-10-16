<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = auth()->user()->notifications();

        if ($category = $request->string('category')->toString()) {
            $query->where('data->category', $category);
        }

        if ($request->string('status')->toString() === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->string('status')->toString() === 'read') {
            $query->whereNotNull('read_at');
        }

        return view('user.notifications.index', [
            'notifications' => $query->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
