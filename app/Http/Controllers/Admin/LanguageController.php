<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LanguageRequest;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Language::class);

        return view('admin.languages.index', [
            'languages' => Language::orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Language::class);

        return view('admin.languages.create');
    }

    public function store(LanguageRequest $request): RedirectResponse
    {
        $this->authorize('create', Language::class);

        $data = $request->validated();

        if (! empty($data['is_default'])) {
            Language::query()->update(['is_default' => false]);
        }

        Language::create($data);

        return redirect()->route('admin.languages.index')->with('status', 'Language created successfully.');
    }

    public function edit(Language $language): View
    {
        $this->authorize('update', $language);

        return view('admin.languages.edit', ['language' => $language]);
    }

    public function update(LanguageRequest $request, Language $language): RedirectResponse
    {
        $this->authorize('update', $language);

        $data = $request->validated();

        if (! empty($data['is_default'])) {
            Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
        }

        $language->update($data);

        return redirect()->route('admin.languages.index')->with('status', 'Language updated successfully.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        $this->authorize('delete', $language);

        $language->delete();

        return redirect()->route('admin.languages.index')->with('status', 'Language deleted successfully.');
    }
}
