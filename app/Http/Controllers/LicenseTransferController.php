<?php

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Models\LicensePlan;
use App\Models\LicenseTransfer;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\LicenseService;
use App\Services\LicenseTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LicenseTransferController extends Controller
{
    public function __construct(
        protected LicenseTransferService $transfers,
        protected LicenseService $licenses,
    ) {}

    public function index(): View
    {
        $user = User::query()->findOrFail(Auth::id());
        $isAdmin = $this->isAdmin($user);

        return view('license-transfers.index', [
            'isAdmin' => $isAdmin,
            'sourceUser' => $isAdmin ? null : $user,
            'availableLicenses' => $isAdmin ? collect() : $this->availableLicenses($user),
            'transferHistory' => $isAdmin
                ? LicenseTransfer::query()->with(['license.plan', 'toUser'])->where('transferred_by_user_id', $user->id)->latest('transferred_at')->take(10)->get()
                : $user->licenseTransfersSent()->with(['license.plan', 'toUser'])->latest('transferred_at')->take(10)->get(),
        ]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
            'mode' => ['nullable', 'in:source,recipient'],
            'source_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $actor = User::query()->findOrFail(Auth::id());
        $isAdmin = $this->isAdmin($actor);
        $mode = $isAdmin ? ($data['mode'] ?? 'recipient') : 'recipient';

        $query = User::query()
            ->where(function ($query) use ($data): void {
                $query->where('email', 'like', '%'.$data['q'].'%')
                    ->orWhere('phone', 'like', '%'.$data['q'].'%');
            })
            ->when($mode === 'source', function ($query): void {
                $query->whereHas('userLicenses', fn ($licenses) => $this->scopeAvailable($licenses));
            })
            ->when($mode === 'recipient', function ($query) use ($actor, $isAdmin, $data): void {
                $sourceId = $isAdmin ? (int) ($data['source_user_id'] ?? $actor->id) : $actor->id;
                $query->whereKeyNot($sourceId);
            })
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json($query->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'department' => $user->department,
            'designation' => $user->designation,
            'company' => $user->company,
            'premium' => $this->transfers->hasPremiumLicense($user),
            'license_count' => $user->userLicenses()->where('payment_status', PaymentStatus::Paid)->count(),
            'demo_license_count' => $user->userLicenses()->where('is_free_claim', true)->count(),
            'free_demo_used' => $this->licenses->hasFreeLicenseHistory($user),
            'available_license_count' => $this->availableLicenses($user)->count(),
        ]));
    }

    public function available(Request $request): JsonResponse
    {
        $actor = User::query()->findOrFail(Auth::id());
        $isAdmin = $this->isAdmin($actor);
        $sourceId = $isAdmin ? $request->integer('source_user_id') : $actor->id;
        abort_unless($sourceId > 0, 422, 'Select a source user first.');
        $source = User::query()->findOrFail($sourceId);
        abort_unless($isAdmin || (int) $source->id === (int) $actor->id, 403);

        return response()->json($this->availableLicenses($source)->map(fn (UserLicense $license): array => [
            'uuid' => $license->uuid,
            'number' => $license->license_number,
            'plan' => $license->plan?->name,
            'type' => $license->plan?->type?->label(),
            'duration_days' => $license->plan?->duration_in_days,
            'is_demo' => $license->is_free_claim,
            'expiry' => $license->expiry_date?->toIso8601String(),
        ]));
    }

    public function useForSelf(Request $request, UserLicense $userLicense): RedirectResponse
    {
        $user = User::query()->findOrFail(Auth::id());
        $this->licenses->activateForSelf($userLicense, $user);

        return redirect()->route('my-licenses.index')->with('status', 'License is now active for your own use.');
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = User::query()->findOrFail(Auth::id());
        $isAdmin = $this->isAdmin($actor);
        $data = $request->validate([
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
            'license_uuid' => ['required', 'uuid', 'exists:user_licenses,uuid'],
            'source_user_id' => [$isAdmin ? 'nullable' : 'prohibited', 'integer', 'exists:users,id'],
            'password' => [$isAdmin ? 'nullable' : 'required', 'string'],
        ]);

        if (! $isAdmin && ! Hash::check($data['password'], $actor->password)) {
            return back()->withErrors(['password' => 'The password you entered is incorrect.'])->withInput($request->except('password'));
        }

        $source = $isAdmin && ! empty($data['source_user_id'])
            ? User::query()->findOrFail($data['source_user_id'])
            : $actor;
        $recipient = User::query()->findOrFail($data['recipient_user_id']);
        $license = UserLicense::query()->where('uuid', $data['license_uuid'])->firstOrFail();

        $this->transfers->transfer($license, $source, $recipient, $actor, $isAdmin);

        return redirect()->route('license-transfers.index')->with('status', 'License transferred successfully.');
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Super Admin']);
    }

    private function availableLicenses(User $user)
    {
        return $user->userLicenses()
            ->with('plan')
            ->where('payment_status', PaymentStatus::Paid)
            ->where('status', LicenseStatus::Pending)
            ->whereNull('assigned_tracked_user_id')
            ->whereNull('activation_date')
            ->whereNull('expiry_date')
            ->whereNull('usage_type')
            ->latest('purchase_date')
            ->get();
    }

    private function scopeAvailable($query): void
    {
        $query->where('payment_status', PaymentStatus::Paid)
            ->where('status', LicenseStatus::Pending)
            ->whereNull('assigned_tracked_user_id')
            ->whereNull('activation_date')
            ->whereNull('expiry_date')
            ->whereNull('usage_type');
    }
}