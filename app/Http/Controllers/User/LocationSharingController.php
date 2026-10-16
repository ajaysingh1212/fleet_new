<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LocationSharingController extends Controller
{
    public function index(): View
    {
        return view('location-sharing.index');
    }
}
