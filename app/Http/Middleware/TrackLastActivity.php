<?php

namespace App\Http\Middleware;

use App\Models\DeviceSession;
use App\Services\UserPresenceService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TrackLastActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user()) {
            return $response;
        }

        $request->user()->forceFill([
            'last_activity_at' => now(),
        ])->saveQuietly();

        $sessionKey = $this->resolveSessionKey($request);

        if ($sessionKey === null) {
            return $response;
        }

        DeviceSession::query()
            ->where('user_id', $request->user()->id)
            ->where('session_id', $sessionKey)
            ->update([
                'last_activity_at' => now(),
                'is_current' => true,
            ]);

        if ($request->is('presence/heartbeat', 'api/v1/gps/heartbeat')) {
            app(UserPresenceService::class)->broadcastChange($request->user()->id);
        }

        return $response;
    }

    /**
     * Web/stateful requests are keyed by the cookie session id; bearer-token
     * API clients have no session at all, so fall back to the token's name
     * (the client's `device_id`, per how it was minted at login).
     */
    private function resolveSessionKey(Request $request): ?string
    {
        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        $token = $request->user()->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->exists) {
            return null;
        }

        return $token->name;
    }
}
