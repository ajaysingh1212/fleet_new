<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSettingsRequest;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('user.settings.edit', [
            'languages' => Language::active()->orderBy('name')->get(),
        ]);
    }

    public function update(UserSettingsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('user-settings.edit')->with('status', 'Preferences updated successfully.');
    }
}
