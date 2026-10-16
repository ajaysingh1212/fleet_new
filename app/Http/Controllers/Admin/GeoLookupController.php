<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\JsonResponse;

class GeoLookupController extends Controller
{
    public function states(int $countryId): JsonResponse
    {
        return response()->json(
            State::active()->where('country_id', $countryId)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function cities(int $stateId): JsonResponse
    {
        return response()->json(
            City::active()->where('state_id', $stateId)->orderBy('name')->get(['id', 'name'])
        );
    }
}
