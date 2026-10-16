<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%'.$request->string('auditable_type').'%');
        }

        if ($request->filled('user')) {
            $query->where('user_id', $request->integer('user'));
        }

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['event', 'auditable_type', 'user']),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit-logs.show', ['log' => $auditLog]);
    }
}
