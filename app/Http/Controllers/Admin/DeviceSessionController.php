<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeviceSessionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', DeviceSession::class);

        return view('admin.device-sessions.index', [
            'sessions' => DeviceSession::with('user')->latest('last_activity_at')->paginate(20),
        ]);
    }

    public function revoke(DeviceSession $deviceSession): RedirectResponse
    {
        $this->authorize('revoke', $deviceSession);

        if ($deviceSession->session_id) {
            DB::table('sessions')->where('id', $deviceSession->session_id)->delete();
        }

        $deviceSession->update(['is_current' => false, 'logged_out_at' => now(),
            'tracking_key_hash' => null, 'tracking_revoked_at' => now()]);

        return back()->with('status', 'Device session revoked successfully.');
    }
}
