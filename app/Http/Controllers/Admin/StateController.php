<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StateRequest;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', State::class);

        return view('admin.states.index', [
            'states' => State::with('country')->withCount('cities')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', State::class);

        return view('admin.states.create', ['countries' => Country::active()->orderBy('name')->get()]);
    }

    public function store(StateRequest $request): RedirectResponse
    {
        $this->authorize('create', State::class);

        State::create($request->validated());

        return redirect()->route('admin.states.index')->with('status', 'State created successfully.');
    }

    public function edit(State $state): View
    {
        $this->authorize('update', $state);

        return view('admin.states.edit', ['state' => $state, 'countries' => Country::active()->orderBy('name')->get()]);
    }

    public function update(StateRequest $request, State $state): RedirectResponse
    {
        $this->authorize('update', $state);

        $state->update($request->validated());

        return redirect()->route('admin.states.index')->with('status', 'State updated successfully.');
    }

    public function destroy(State $state): RedirectResponse
    {
        $this->authorize('delete', $state);

        $state->delete();

        return redirect()->route('admin.states.index')->with('status', 'State deleted successfully.');
    }
}
