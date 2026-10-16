<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LicensePlan;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->string('q'));
        $results = [];

        if ($term !== '') {
            $user = $request->user();

            if ($user->can('manage users')) {
                $results['Users'] = User::query()
                    ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('employee_id', 'like', "%{$term}%"))
                    ->take(10)->get();
            }

            if ($user->can('manage license plans')) {
                $results['License Plans'] = LicensePlan::where('name', 'like', "%{$term}%")->take(10)->get();
            }

            if ($user->can('manage user licenses')) {
                $results['User Licenses'] = UserLicense::with('user')
                    ->where('license_number', 'like', "%{$term}%")
                    ->orWhere('invoice_number', 'like', "%{$term}%")
                    ->orWhere('order_number', 'like', "%{$term}%")
                    ->take(10)->get();
            }

            if ($user->can('manage settings')) {
                $results['Settings'] = Setting::where('key', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->take(10)->get();
            }

            if ($user->can('view activity logs')) {
                $results['Activity Logs'] = ActivityLog::where('event', 'like', "%{$term}%")->latest('logged_at')->take(10)->get();
            }
        }

        return view('admin.search.index', [
            'term' => $term,
            'results' => $results,
        ]);
    }
}
