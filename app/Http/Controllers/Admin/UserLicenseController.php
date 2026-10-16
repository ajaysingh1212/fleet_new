<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignUserLicenseRequest;
use App\Models\LicensePlan;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserLicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', UserLicense::class);

        $query = UserLicense::with(['user', 'plan', 'assignedTrackedUser'])->latest();

        if ($request->filled('user')) {
            $query->where('user_id', $request->integer('user'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('admin.user-licenses.index', [
            'licenses' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['user', 'status']),
            'statuses' => LicenseStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', UserLicense::class);

        return view('admin.user-licenses.create', [
            'users' => User::orderBy('name')->get(),
            'plans' => LicensePlan::where('status', 'active')->orderBy('display_order')->get(),
        ]);
    }

    public function store(AssignUserLicenseRequest $request): RedirectResponse
    {
        $this->authorize('create', UserLicense::class);

        $user = User::findOrFail($request->validated('user_id'));
        $plan = LicensePlan::findOrFail($request->validated('license_plan_id'));

        $this->licenseService->issueForAdmin($user, $plan);

        return redirect()->route('admin.user-licenses.index')->with('status', 'License assigned successfully.');
    }

    public function show(UserLicense $userLicense): View
    {
        $this->authorize('view', $userLicense);

        $userLicense->load(['user', 'plan', 'assignedTrackedUser']);

        return view('admin.user-licenses.show', ['license' => $userLicense]);
    }

    public function extend(Request $request, UserLicense $userLicense): RedirectResponse
    {
        $this->authorize('update', $userLicense);

        $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);

        $this->licenseService->extend($userLicense, $request->integer('days'));

        return back()->with('status', 'License extended.');
    }

    public function cancel(UserLicense $userLicense): RedirectResponse
    {
        $this->authorize('update', $userLicense);

        $this->licenseService->cancel($userLicense);

        return back()->with('status', 'License cancelled.');
    }
}
