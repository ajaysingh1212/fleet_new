<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use App\Services\ActivityLogService;
use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenLogoutController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected UserPresenceService $presenceService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            DeviceSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', $token->name)
                ->update(['is_current' => false, 'logged_out_at' => now()]);

            $token->delete();
        }

        $this->activityLogService->log($user, 'auth.token_logout', $user);
        $this->presenceService->broadcastChange($user->id);

        return response()->json(['message' => 'Logged out.']);
    }
}
