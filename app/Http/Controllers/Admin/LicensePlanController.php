<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LicensePlanRequest;
use App\Interfaces\Repositories\LicensePlanRepositoryInterface;
use App\Models\LicensePlan;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LicensePlanController extends Controller
{
    public function __construct(
        protected LicensePlanRepositoryInterface $licensePlans,
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', LicensePlan::class);

        return view('admin.license-plans.index', [
            'plans' => $this->licensePlans->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LicensePlan::class);

        return view('admin.license-plans.create', $this->formOptions());
    }

    public function store(LicensePlanRequest $request): RedirectResponse
    {
        $this->authorize('create', LicensePlan::class);

        $plan = $this->licensePlans->create($request->validated());
        $this->activityLogService->log(auth()->user(), 'license_plan.created', $plan, ['plan' => $plan->name]);

        return redirect()->route('admin.license-plans.index')->with('status', 'License plan created successfully.');
    }

    public function edit(LicensePlan $licensePlan): View
    {
        $this->authorize('update', $licensePlan);

        return view('admin.license-plans.edit', $this->formOptions() + ['plan' => $licensePlan]);
    }

    public function update(LicensePlanRequest $request, LicensePlan $licensePlan): RedirectResponse
    {
        $this->authorize('update', $licensePlan);

        $licensePlan->update($request->validated());
        $this->activityLogService->log(auth()->user(), 'license_plan.updated', $licensePlan);

        return redirect()->route('admin.license-plans.index')->with('status', 'License plan updated successfully.');
    }

    public function destroy(LicensePlan $licensePlan): RedirectResponse
    {
        $this->authorize('delete', $licensePlan);

        $licensePlan->delete();
        $this->activityLogService->log(auth()->user(), 'license_plan.deleted', null, ['plan' => $licensePlan->name]);

        return redirect()->route('admin.license-plans.index')->with('status', 'License plan deleted successfully.');
    }

    protected function formOptions(): array
    {
        return [
            'types' => LicenseType::cases(),
            'statuses' => UserStatus::cases(),
        ];
    }
}
