<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CityRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CityController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', City::class);

        return view('admin.cities.index', [
            'cities' => City::with(['country', 'state'])->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', City::class);

        return view('admin.cities.create', $this->formOptions());
    }

    public function store(CityRequest $request): RedirectResponse
    {
        $this->authorize('create', City::class);

        City::create($request->validated());

        return redirect()->route('admin.cities.index')->with('status', 'City created successfully.');
    }

    public function edit(City $city): View
    {
        $this->authorize('update', $city);

        return view('admin.cities.edit', $this->formOptions() + ['city' => $city]);
    }

    public function update(CityRequest $request, City $city): RedirectResponse
    {
        $this->authorize('update', $city);

        $city->update($request->validated());

        return redirect()->route('admin.cities.index')->with('status', 'City updated successfully.');
    }

    public function destroy(City $city): RedirectResponse
    {
        $this->authorize('delete', $city);

        $city->delete();

        return redirect()->route('admin.cities.index')->with('status', 'City deleted successfully.');
    }

    protected function formOptions(): array
    {
        return [
            'countries' => Country::active()->orderBy('name')->get(),
            'states' => State::active()->orderBy('name')->get(),
        ];
    }
}
