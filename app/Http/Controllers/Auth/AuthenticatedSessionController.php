<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\DeviceSession;
use App\Models\FailedLogin;
use App\Services\ActivityLogService;
use App\Services\UserPresenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected UserPresenceService $presenceService,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            FailedLogin::create([
                'user_id' => null,
                'email' => $request->string('email')->toString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => 'Invalid credentials',
                'attempted_at' => now(),
            ]);

            throw $exception;
        }

        $request->session()->regenerate();

        $request->user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_activity_at' => now(),
        ])->saveQuietly();

        DeviceSession::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'session_id' => $request->session()->getId(),
            ],
            [
                'device_name' => substr((string) $request->userAgent(), 0, 100),
                'platform' => php_uname('s'),
                'browser' => substr((string) $request->userAgent(), 0, 100),
                'ip_address' => $request->ip(),
                'last_login_at' => now(),
                'last_activity_at' => now(),
                'is_current' => true,
            ],
        );

        $this->activityLogService->log($request->user(), 'auth.login', $request->user());
        $this->presenceService->broadcastChange($request->user()->id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->activityLogService->log($user, 'auth.logout', $user);

            DeviceSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', $request->session()->getId())
                ->update([
                    'logged_out_at' => now(),
                    'is_current' => false,
                ]);

            $this->presenceService->broadcastChange($user->id);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
