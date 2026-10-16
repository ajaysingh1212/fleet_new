<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserLicenseStoreRequest;
use App\Http\Resources\UserLicenseResource;
use App\Models\LicensePlan;
use App\Models\User;
use App\Services\LicensePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserLicenseController extends Controller
{
    public function __construct(
        protected LicensePaymentService $licensePayments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserLicenseResource::collection(
            $request->user()->userLicenses()->with('plan')->latest()->paginate()
        );
    }

    public function store(UserLicenseStoreRequest $request): JsonResponse
    {
        $plan = LicensePlan::query()->where('status', 'active')->findOrFail($request->integer('license_plan_id'));
        $user = User::query()->findOrFail($request->user()->id);
        $result = $this->licensePayments->beginPurchase($user, $plan);

        return response()->json([
            'license' => new UserLicenseResource($result['license']->load('plan')),
            'checkout' => $result['checkout'] ?? null,
        ], 201);
    }
}
