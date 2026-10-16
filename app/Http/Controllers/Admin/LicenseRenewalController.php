<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseTransaction;
use Illuminate\View\View;

class LicenseRenewalController extends Controller
{
    public function index(): View
    {
        return view('admin.license-renewals.index', [
            'renewals' => LicenseTransaction::query()
                ->with(['user', 'license', 'plan'])
                ->where('type', 'renewal')
                ->latest()
                ->paginate(20),
        ]);
    }
}