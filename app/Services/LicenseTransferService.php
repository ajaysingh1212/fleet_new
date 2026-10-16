<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Models\LicenseTransfer;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicenseTransferService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected LicenseService $licenses,
    ) {}

    public function transfer(UserLicense $license, User $from, User $to, User $actor, bool $adminTransfer = false): LicenseTransfer
    {
        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages(['recipient' => 'Choose a different recipient.']);
        }

        return DB::transaction(function () use ($license, $from, $to, $actor, $adminTransfer): LicenseTransfer {
            User::query()->whereKey($from->id)->lockForUpdate()->firstOrFail();
            User::query()->whereKey($to->id)->lockForUpdate()->firstOrFail();
            $locked = UserLicense::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->user_id !== (int) $from->id
                || $locked->status !== LicenseStatus::Pending
                || $locked->payment_status !== PaymentStatus::Paid
                || $locked->activation_date !== null
                || $locked->expiry_date !== null
                || $locked->assigned_tracked_user_id !== null
                || $locked->usage_type !== null) {
                throw ValidationException::withMessages(['license' => 'Only an unused license can be transferred.']);
            }

            if (! $adminTransfer && $this->hasPremiumLicense($to)) {
                throw ValidationException::withMessages(['recipient' => 'This user already has a premium license.']);
            }

            if ($locked->is_free_claim && $this->licenses->hasFreeLicenseHistory($to)) {
                throw ValidationException::withMessages(['recipient' => 'This user already has a free demo license.']);
            }

            $locked->update(['user_id' => $to->id]);

            $transfer = LicenseTransfer::create([
                'uuid' => Str::uuid(),
                'user_license_id' => $locked->id,
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'transferred_by_user_id' => $actor->id,
                'admin_transfer' => $adminTransfer,
                'transferred_at' => now(),
            ]);

            $this->activityLogService->log($actor, 'license.transferred', $locked, [
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'admin_transfer' => $adminTransfer,
            ]);

            return $transfer;
        });
    }

    public function hasPremiumLicense(User $user): bool
    {
        return $user->userLicenses()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('is_free_claim', false)
            ->whereIn('status', [LicenseStatus::Pending, LicenseStatus::Active])
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
            ->exists();
    }
}