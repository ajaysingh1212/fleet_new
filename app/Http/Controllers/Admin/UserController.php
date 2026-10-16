<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\State;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = $this->users->paginateForAdmin($request->only(['search', 'role', 'status', 'department']), 15);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'statuses' => UserStatus::cases(),
            'filters' => $request->only(['search', 'role', 'status', 'department']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', $this->formOptions());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->safe()->except(['roles']);
        $data['password'] = Hash::make($request->validated('password'));

        $user = User::create($data);
        $user->syncRoles($request->validated('roles'));

        $this->activityLogService->log(User::query()->find(Auth::id()), 'user.created', $user, ['created_user' => $user->email]);

        return redirect()->route('admin.users.index')->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('roles', 'country', 'state', 'city', 'language');

        return view('admin.users.show', [
            'user' => $user,
            'licenses' => $user->userLicenses()->with('plan')->latest()->take(5)->get(),
            'licenseCount' => $user->userLicenses()->count(),
            'availableLicenseCount' => $user->userLicenses()->where('payment_status', 'paid')->where('status', 'pending')->whereNull('assigned_tracked_user_id')->count(),
            'devices' => $user->deviceSessions()->latest('last_activity_at')->take(5)->get(),
            'activities' => ActivityLog::where('user_id', $user->id)->latest('logged_at')->take(10)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('roles');

        return view('admin.users.edit', $this->formOptions() + ['user' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->safe()->except(['roles', 'password']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->fill($data);

        $rolesChanged = $user->getRoleNames()->sort()->values()->all() !== collect($request->validated('roles'))->sort()->values()->all();

        if ($rolesChanged) {
            $this->authorize('updateRoles', $user);
            $user->syncRoles($request->validated('roles'));
        }

        $user->save();

        $this->activityLogService->log(User::query()->find(Auth::id()), 'user.updated', $user);

        return redirect()->route('admin.users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->update(['status' => UserStatus::Deleted]);
        $user->delete();

        $this->activityLogService->log(User::query()->find(Auth::id()), 'user.deleted', $user);

        return redirect()->route('admin.users.index')->with('status', 'User moved to trash.');
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $user);

        $user->restore();
        $user->update(['status' => UserStatus::Active]);

        $this->activityLogService->log(User::query()->find(Auth::id()), 'user.restored', $user);

        return redirect()->route('admin.users.index')->with('status', 'User restored successfully.');
    }

    protected function formOptions(): array
    {
        return [
            'roles' => Role::orderBy('name')->get(),
            'countries' => Country::active()->orderBy('name')->get(),
            'states' => State::active()->orderBy('name')->get(),
            'cities' => City::active()->orderBy('name')->get(),
            'languages' => Language::active()->orderBy('name')->get(),
            'statuses' => UserStatus::cases(),
        ];
    }
}
