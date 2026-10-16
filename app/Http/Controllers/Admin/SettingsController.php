<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsUpdateRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected ActivityLogService $activityLogService,
    ) {}

    public function edit(): View
    {
        $this->authorize('viewAny', Setting::class);

        Setting::firstOrCreate(['group' => 'system', 'key' => 'location_save_radius_meters'], [
            'value' => 25, 'type' => 'integer', 'is_public' => false,
            'description' => 'Location history save radius in meters',
        ]);

        $groups = Setting::orderBy('key')->get()->groupBy('group');

        return view('admin.settings.edit', ['groups' => $groups]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        $this->authorize('update', new Setting);

        $group = $request->validated('group');
        $groupSettings = Setting::where('group', $group)->where('type', '!=', 'file')->get();

        foreach ($groupSettings as $setting) {
            if ($setting->key === 'location_save_radius_meters' && ! $request->has('values.location_save_radius_meters')) {
                continue;
            }
            if ($setting->type === 'secret' && blank($request->input("values.{$setting->key}"))) {
                continue;
            }

            $value = match ($setting->type) {
                'boolean' => $request->boolean("values.{$setting->key}"),
                'integer' => (int) $request->input("values.{$setting->key}", 0),
                'json' => json_decode((string) $request->input("values.{$setting->key}", '{}'), true) ?? [],
                default => $request->input("values.{$setting->key}"),
            };

            $this->settings->set($group, $setting->key, $value, $setting->type, $setting->is_public, $setting->description);
        }

        if ($group === 'site' && $request->hasFile('logo')) {
            $this->storeBrandingFile($request, 'logo', 'site');
        }

        if ($group === 'site' && $request->hasFile('favicon')) {
            $this->storeBrandingFile($request, 'favicon', 'site');
        }

        $this->activityLogService->log(User::query()->find(Auth::id()), 'settings.updated', null, ['group' => $group]);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings updated successfully.');
    }

    protected function storeBrandingFile(SettingsUpdateRequest $request, string $key, string $group): void
    {
        $existing = Setting::where('group', $group)->where('key', $key)->first();

        if ($existing && is_string($existing->value)) {
            Storage::disk('public')->delete($existing->value);
        }

        $path = $request->file($key)->store('branding', 'public');

        $this->settings->set($group, $key, $path, 'file', true);
    }
}
