<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeviceSessionController extends Controller
{
    public function index(): View
    {
        return view('user.devices.index', [
            'sessions' => auth()->user()->deviceSessions()->latest('last_activity_at')->paginate(10),
        ]);
    }

    public function revoke(Request $request, DeviceSession $deviceSession): RedirectResponse
    {
        $this->authorize('revoke', $deviceSession);

        if ($deviceSession->session_id && $deviceSession->session_id !== $request->session()->getId()) {
            DB::table('sessions')->where('id', $deviceSession->session_id)->delete();
        }

        $deviceSession->update(['is_current' => false, 'logged_out_at' => now(),
            'tracking_key_hash' => null, 'tracking_revoked_at' => now()]);

        return back()->with('status', 'Device signed out successfully.');
    }

    public function revokeOthers(Request $request): RedirectResponse
    {
        $currentSessionId = $request->session()->getId();

        $others = auth()->user()->deviceSessions()
            ->where('session_id', '!=', $currentSessionId)
            ->where('is_current', true)
            ->get();

        foreach ($others as $session) {
            DB::table('sessions')->where('id', $session->session_id)->delete();
            $session->update(['is_current' => false, 'logged_out_at' => now(),
                'tracking_key_hash' => null, 'tracking_revoked_at' => now()]);
        }

        return back()->with('status', 'All other devices have been signed out.');
    }
}
