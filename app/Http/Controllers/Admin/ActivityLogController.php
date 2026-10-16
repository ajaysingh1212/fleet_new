<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::with('user')->latest('logged_at');

        if ($request->filled('event')) {
            $query->where('event', 'like', '%'.$request->string('event').'%');
        }

        if ($request->filled('user')) {
            $query->where('user_id', $request->integer('user'));
        }

        if ($request->filled('from')) {
            $query->whereDate('logged_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('logged_at', '<=', $request->date('to'));
        }

        return view('admin.activity-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['event', 'user', 'from', 'to']),
        ]);
    }
}
