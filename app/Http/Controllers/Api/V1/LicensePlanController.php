<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LicensePlanStoreRequest;
use App\Http\Resources\LicensePlanResource;
use App\Interfaces\Repositories\LicensePlanRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LicensePlanController extends Controller
{
    public function __construct(
        protected LicensePlanRepositoryInterface $licensePlans,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return LicensePlanResource::collection($this->licensePlans->paginate());
    }

    public function store(LicensePlanStoreRequest $request): LicensePlanResource
    {
        $plan = $this->licensePlans->create($request->validated());

        return new LicensePlanResource($plan);
    }
}
