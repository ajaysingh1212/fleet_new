<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\MapMarkerPreference;
use App\Models\User;
use App\Services\RelationshipAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MapMarkerController extends Controller
{
    private function authorizeTarget(Request $request, User $user): void
    {
        $viewer = $request->user();
        abort_unless($viewer->is($user)
            || app(RelationshipAuthorizationService::class)->canAdminViewUser($viewer, $user)
            || $viewer->trackedUsers()->where('tracked_user_id', $user->id)
                ->where('status', UserStatus::Active)->exists(), 403);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(MapMarkerPreference::OPTIONS))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $preference = MapMarkerPreference::firstOrNew([
            'viewer_user_id' => $request->user()->id, 'tracked_user_id' => $user->id,
        ]);
        if ($data['kind'] === 'other' && ! $request->hasFile('image') && ! $preference->image_path) {
            throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'Choose an image for Other.']);
        }
        $oldPath = $preference->image_path;
        $preference->kind = $data['kind'];
        if ($data['kind'] !== 'other') $preference->image_path = null;
        elseif ($request->hasFile('image')) {
            $preference->image_path = $request->file('image')->store('map-markers', 'local');
        }
        $preference->save();
        if ($oldPath && $oldPath !== $preference->image_path) Storage::disk('local')->delete($oldPath);

        return response()->json($preference->markerData());
    }

    public function image(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        $preference = MapMarkerPreference::where('viewer_user_id', $request->user()->id)
            ->where('tracked_user_id', $user->id)->firstOrFail();
        abort_unless($preference->image_path && Storage::disk('local')->exists($preference->image_path), 404);

        return Storage::disk('local')->response($preference->image_path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
