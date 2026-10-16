<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocationShareStopRequest;
use App\Services\LocationShareLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationShareController extends Controller
{
    public function requestStop(Request $request, LocationShareLockService $service): JsonResponse
    {
        $stopRequest = $service->requestStop($request->user());

        return response()->json([
            'data' => [
                'uuid' => $stopRequest->uuid,
                'status' => $stopRequest->status->value,
            ],
        ], 201);
    }

    public function respond(Request $request, LocationShareStopRequest $stopRequest, LocationShareLockService $service): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['allow', 'deny'])],
        ]);

        $stopRequest = $service->respond($stopRequest, $request->user(), $data['decision'] === 'allow');

        return response()->json(['data' => ['status' => $stopRequest->status->value]]);
    }
}
