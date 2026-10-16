<?php

namespace App\Http\Controllers\User;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ManagedTrackedUserStoreRequest;
use App\Http\Requests\User\TrackingFriendRequestStoreRequest;
use App\Http\Requests\User\TrackingRelationStoreRequest;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Services\TrackingRelationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TrackingRelationController extends Controller
{
    public function __construct(protected TrackingRelationService $relations) {}

    public function index(): View
    {
        $user = User::query()->findOrFail(Auth::id());

        return view('user.tracking-relations.index', [
            'relations' => $user->trackedUsers()
                ->with(['trackedUser', 'userLicense.plan'])
                ->where('status', UserStatus::Active)
                ->latest()
                ->paginate(15),
            'incomingRequests' => $user->trackerRelations()
                ->with(['trackerUser', 'userLicense.plan'])
                ->where('status', UserStatus::Pending)
                ->latest()
                ->get(),
            'sentRequests' => $user->trackedUsers()
                ->with(['trackedUser', 'userLicense.plan'])
                ->where('status', UserStatus::Pending)
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        $user = User::query()->findOrFail(Auth::id());

        return view('user.tracking-relations.create', [
            'users' => User::query()->whereKeyNot($user->id)->orderBy('name')->get(),
            'availableLicenseCount' => $user->userLicenses()
                ->where('payment_status', PaymentStatus::Paid)
                ->whereIn('status', [LicenseStatus::Pending, LicenseStatus::Active])
                ->whereNull('assigned_tracked_user_id')
                ->count(),
        ]);
    }

    public function store(TrackingRelationStoreRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail(Auth::id());
        $this->relations->create([
            'tracker_user_id' => $user->id,
            'tracked_user_id' => $request->integer('tracked_user_id'),
            'relationship_name' => $request->validated('relationship_name'),
            'status' => UserStatus::Active->value,
            'created_by' => $user->id,
        ]);

        return redirect()->route('my-tracking.index')->with('status', 'Tracking relation created.');
    }

    public function storeManaged(ManagedTrackedUserStoreRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail(Auth::id());
        $this->relations->createManagedUser($user, $request->validated());

        return redirect()->route('my-tracking.index')->with('status', 'User account created and tracking activated.');
    }

    public function sendRequest(TrackingFriendRequestStoreRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail(Auth::id());
        $identifier = trim($request->validated('identifier'));
        $tracked = User::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $tracked || $tracked->is($user)) {
            return back()->withErrors(['identifier' => 'No matching user was found.'])->withInput();
        }

        $existing = TrackingRelation::query()
            ->where('tracker_user_id', $user->id)
            ->where('tracked_user_id', $tracked->id)
            ->exists();

        if ($existing) {
            return back()->withErrors(['identifier' => 'A tracking request or relation already exists for this user.'])->withInput();
        }

        $this->relations->requestExisting($user, $tracked, $request->validated('relationship_name'));

        return redirect()->route('my-tracking.index')->with('status', 'Tracking request sent. You can track this user after they accept it.');
    }

    public function accept(TrackingRelation $trackingRelation): RedirectResponse
    {
        $this->relations->accept($trackingRelation, User::query()->findOrFail(Auth::id()));

        return redirect()->route('my-tracking.index')->with('status', 'Tracking request accepted.');
    }

    public function requestBack(TrackingRelation $trackingRelation): RedirectResponse
    {
        $user = User::query()->findOrFail(Auth::id());

        abort_unless(
            (int) $trackingRelation->tracked_user_id === (int) $user->id
            && $trackingRelation->status === UserStatus::Active,
            403,
        );

        $tracker = $trackingRelation->trackerUser()->firstOrFail();

        if ($user->trackedUsers()->where('tracked_user_id', $tracker->id)->exists()) {
            return back()->withErrors(['relation' => 'A reverse tracking request or relation already exists.']);
        }

        $this->relations->requestExisting($user, $tracker, $trackingRelation->relationship_name ?: 'Friend');

        return back()->with('status', "Tracking request sent to {$tracker->name}.");
    }

    public function reject(TrackingRelation $trackingRelation): RedirectResponse
    {
        $this->relations->reject($trackingRelation, User::query()->findOrFail(Auth::id()));

        return redirect()->route('my-tracking.index')->with('status', 'Tracking request rejected.');
    }

    public function destroy(TrackingRelation $trackingRelation): RedirectResponse
    {
        abort_unless((int) $trackingRelation->tracker_user_id === (int) Auth::id(), 403);
        $wasPending = $trackingRelation->status === UserStatus::Pending;
        $this->relations->delete($trackingRelation);

        return redirect()->route('my-tracking.index')->with('status', $wasPending
            ? 'Tracking request cancelled. The reserved license is available again.'
            : 'Tracking relation removed. The assigned license remains bound to this person.');
    }
}
