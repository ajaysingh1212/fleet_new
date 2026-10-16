<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Interfaces\Repositories\UserLicenseRepositoryInterface;
use App\Models\LicensePlan;
use App\Models\LicenseTransfer;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LicenseService
{
    public function __construct(
        protected UserLicenseRepositoryInterface $licenses,
        protected ActivityLogService $activityLogService,
    ) {}

    public function purchase(User $user, LicensePlan $plan): UserLicense
    {
        return $this->createLicense($user, $plan, PaymentStatus::Pending);
    }

    public function issueForAdmin(User $user, LicensePlan $plan): UserLicense
    {
        if ($plan->is_free) {
            return $this->claimFree($user, $plan);
        }

        return $this->createLicense($user, $plan, PaymentStatus::Paid);
    }

    public function claimFree(User $user, LicensePlan $plan): UserLicense
    {
        if (! $plan->is_free || (float) $plan->price !== 0.0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'license_plan_id' => 'This plan is not a free license.',
            ]);
        }

        return DB::transaction(function () use ($user, $plan): UserLicense {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($this->hasFreeLicenseHistory($user)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'license_plan_id' => 'You have already claimed your free license.',
                ]);
            }

            return $this->createLicense($user, $plan, PaymentStatus::Paid, true, $user->id);
        });
    }

    public function hasFreeLicenseHistory(User $user): bool
    {
        return UserLicense::withTrashed()->where('is_free_claim', true)->where(function ($query) use ($user): void {
            $query->where('free_claimed_by_user_id', $user->id)
                ->orWhere('user_id', $user->id);
        })->exists()
            || LicenseTransfer::query()
                ->where('to_user_id', $user->id)
                ->whereHas('license', fn ($query) => $query->where('is_free_claim', true))
                ->exists();
    }

    protected function createLicense(User $user, LicensePlan $plan, PaymentStatus $paymentStatus, bool $isFreeClaim = false, ?int $freeClaimedByUserId = null): UserLicense
    {
        $license = $this->licenses->create([
            'user_id' => $user->id,
            'license_plan_id' => $plan->id,
            'is_free_claim' => $isFreeClaim,
            'free_claimed_by_user_id' => $freeClaimedByUserId,
            'license_number' => strtoupper(Str::random(12)),
            'purchase_date' => now(),
            'activation_date' => null,
            'expiry_date' => null,
            'status' => LicenseStatus::Pending,
            'payment_status' => $paymentStatus,
            'invoice_number' => $paymentStatus === PaymentStatus::Paid ? 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)) : null,
            'order_number' => 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
        ]);

        $this->activityLogService->log($user, $paymentStatus === PaymentStatus::Paid ? 'license.issued' : 'license.purchase.started', $license, [
            'plan' => $plan->name,
        ]);

        return $license;
    }

    public function activateForUse(UserLicense $license, string $usageType = 'tracking'): UserLicense
    {
        if ($license->payment_status !== PaymentStatus::Paid) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tracker_user_id' => 'This license has not been paid.',
            ]);
        }

        $license->update([
            'status' => LicenseStatus::Active,
            'usage_type' => $license->usage_type ?? $usageType,
            'activation_date' => $license->activation_date ?? now(),
            'expiry_date' => $license->expiry_date ?? $this->expiryFrom($license->plan, now()),
        ]);

        $this->activityLogService->log($license->user, 'license.activated', $license, [
            'license_number' => $license->license_number,
        ]);

        return $license;
    }

    public function activateForSelf(UserLicense $license, User $user): UserLicense
    {
        return DB::transaction(function () use ($license, $user): UserLicense {
            $locked = UserLicense::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->user_id !== (int) $user->id
                || $locked->status !== LicenseStatus::Pending
                || $locked->payment_status !== PaymentStatus::Paid
                || $locked->assigned_tracked_user_id !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'license' => 'This license is no longer available for personal use.',
                ]);
            }

            $locked->assigned_tracked_user_id = $user->id;
            $locked->save();

            return $this->activateForUse($locked, 'self');
        });
    }

    public function expiryFrom(LicensePlan $plan, \Illuminate\Support\Carbon $activationDate): ?\Illuminate\Support\Carbon
    {
        if ($plan->type->value === 'lifetime') {
            return null;
        }

        return $activationDate->copy()->addDays($plan->duration_in_days);
    }

    public function extend(UserLicense $license, int $days): UserLicense
    {
        $base = $license->expiry_date && $license->expiry_date->isFuture() ? $license->expiry_date : now();

        $license->update([
            'expiry_date' => $base->copy()->addDays($days),
            'status' => LicenseStatus::Active,
        ]);

        $this->activityLogService->log(User::query()->find(Auth::id()), 'license.extended', $license, [
            'license_number' => $license->license_number,
            'days' => $days,
        ]);

        return $license;
    }

    public function cancel(UserLicense $license): UserLicense
    {
        $license->update(['status' => LicenseStatus::Cancelled]);

        $this->activityLogService->log(User::query()->find(Auth::id()), 'license.cancelled', $license, [
            'license_number' => $license->license_number,
        ]);

        return $license;
    }
}
