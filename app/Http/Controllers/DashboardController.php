<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = User::query()->findOrFail(Auth::id());

        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Manager'])) {
            return view('dashboards.admin', [
                'stats' => [
                    'totalUsers' => User::count(),
                    'activeUsers' => User::where('status', 'active')->count(),
                    'managers' => User::role('Manager')->count(),
                    'admins' => User::role('Admin')->count() + User::role('Super Admin')->count(),
                    'licenses' => UserLicense::count(),
                    'activeLicenses' => UserLicense::where('status', 'active')->count(),
                    'expiredLicenses' => UserLicense::where('status', 'expired')->count(),
                    'revenue' => UserLicense::query()->where('payment_status', 'paid')->count(),
                    'todaysLogins' => ActivityLog::where('event', 'auth.login')->whereDate('logged_at', today())->count(),
                    'todaysRegistrations' => User::whereDate('created_at', today())->count(),
                    'latestActivities' => ActivityLog::with('user')->latest('logged_at')->take(10)->get(),
                ],
            ]);
        }

        $activeLicense = $user->userLicenses()->with('plan')->where('status', 'active')->latest('expiry_date')->first();
        $demoTrackingLicenses = $user->trackerRelations()
            ->with(['trackerUser', 'userLicense.plan'])
            ->where('status', 'active')
            ->get()
            ->filter(fn ($relation) => $relation->userLicense?->is_free_claim);

        return view('dashboards.user', [
            'activeLicense' => $activeLicense,
            'demoTrackingLicenses' => $demoTrackingLicenses,
            'stats' => [
                'recentActivity' => $user->failedLogins()->latest('attempted_at')->take(5)->get(),
                'supportTickets' => SupportTicket::where('user_id', $user->id)->count(),
                'notifications' => $user->notifications()->latest()->take(5)->get(),
                'loginHistory' => $user->failedLogins()->latest('attempted_at')->take(5)->get(),
                'devices' => $user->userLicenses()->count(),
                'availableLicenses' => $user->userLicenses()->where('status', 'pending')->where('payment_status', 'paid')->count(),
                'trackedUsers' => $user->trackedUsers()->where('status', 'active')->count(),
            ],
        ]);
    }
}
