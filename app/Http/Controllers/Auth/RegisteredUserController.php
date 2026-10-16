<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ThemeMode;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'employee_id' => 'EMP-'.str_pad((string) (User::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString() ?: null,
            'password' => Hash::make($request->string('password')->toString()),
            'status' => UserStatus::Active,
            'theme' => ThemeMode::Light,
            'timezone' => config('app.timezone'),
        ]);

        Role::findOrCreate('User', 'web');
        $user->assignRole('User');

        event(new Registered($user));

        Auth::login($user);

        $this->activityLogService->log($user, 'auth.register', $user);

        return redirect(route('dashboard', absolute: false));
    }
}
