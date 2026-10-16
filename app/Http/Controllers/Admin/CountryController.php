<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CountryRequest;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Country::class);

        return view('admin.countries.index', [
            'countries' => Country::withCount('states')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Country::class);

        return view('admin.countries.create');
    }

    public function store(CountryRequest $request): RedirectResponse
    {
        $this->authorize('create', Country::class);

        Country::create($request->validated());

        return redirect()->route('admin.countries.index')->with('status', 'Country created successfully.');
    }

    public function edit(Country $country): View
    {
        $this->authorize('update', $country);

        return view('admin.countries.edit', ['country' => $country]);
    }

    public function update(CountryRequest $request, Country $country): RedirectResponse
    {
        $this->authorize('update', $country);

        $country->update($request->validated());

        return redirect()->route('admin.countries.index')->with('status', 'Country updated successfully.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        $this->authorize('delete', $country);

        $country->delete();

        return redirect()->route('admin.countries.index')->with('status', 'Country deleted successfully.');
    }
}
