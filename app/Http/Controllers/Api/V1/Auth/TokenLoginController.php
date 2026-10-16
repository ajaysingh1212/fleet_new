<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TokenLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\DeviceSession;
use App\Services\ActivityLogService;
use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;

class TokenLoginController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected UserPresenceService $presenceService,
    ) {}

    public function __invoke(TokenLoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        DeviceSession::query()->updateOrCreate(
            ['user_id' => $user->id, 'session_id' => $request->string('device_id')->toString()],
            [
                'device_name' => $request->string('device_name')->toString() ?: null,
                'platform' => $request->string('platform')->toString() ?: null,
                'ip_address' => $request->ip(),
                'last_login_at' => now(),
                'last_activity_at' => now(),
                'is_current' => true,
                'logged_out_at' => null,
            ],
        );

        $token = $user->createToken($request->string('device_id')->toString());

        $this->activityLogService->log($user, 'auth.token_login', $user, [
            'device_id' => $request->string('device_id')->toString(),
        ]);
        $this->presenceService->broadcastChange($user->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }
}
