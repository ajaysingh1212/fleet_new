<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Models\LicensePlan;
use App\Models\LicenseTransaction;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicensePaymentService
{
    public function __construct(
        protected LicenseService $licenses,
        protected PaymentGatewayService $gateway,
    ) {}

    public function beginPurchase(User $user, LicensePlan $plan): array
    {
        if ($plan->is_free) {
            return ['license' => $this->licenses->claimFree($user, $plan)];
        }

        if ((float) $plan->price <= 0) {
            throw ValidationException::withMessages(['license_plan_id' => 'Paid plans must have a price greater than zero.']);
        }

        return $this->begin($user, $plan, $this->licenses->purchase($user, $plan), 'purchase', (float) $plan->price);
    }

    public function beginRenewal(User $user, UserLicense $license): array
    {
        if ((int) $license->user_id !== (int) $user->id) {
            abort(403);
        }

        $license->loadMissing('plan');
        if (! $license->assigned_tracked_user_id || $license->plan->type->value === 'lifetime' || $license->status === LicenseStatus::Cancelled) {
            throw ValidationException::withMessages(['license' => 'This license cannot be renewed.']);
        }

        if ((float) $license->plan->renewal_price <= 0) {
            throw ValidationException::withMessages(['license' => 'This license does not have a paid renewal price.']);
        }

        return $this->begin($user, $license->plan, $license, 'renewal', (float) $license->plan->renewal_price);
    }

    public function complete(LicenseTransaction $transaction, string $providerPaymentId): UserLicense
    {
        return DB::transaction(function () use ($transaction, $providerPaymentId): UserLicense {
            $transaction = LicenseTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $license = UserLicense::query()->whereKey($transaction->user_license_id)->lockForUpdate()->firstOrFail();

            if ($transaction->status === PaymentStatus::Paid) {
                return $license;
            }

            if ($transaction->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => 'This payment transaction is no longer pending.']);
            }

            if ($transaction->type === 'purchase') {
                $license->update([
                    'payment_status' => PaymentStatus::Paid,
                    'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                ]);
            } else {
                $base = $license->expiry_date && $license->expiry_date->isFuture() ? $license->expiry_date : now();
                $license->update([
                    'payment_status' => PaymentStatus::Paid,
                    'status' => LicenseStatus::Active,
                    'expiry_date' => $base->copy()->addDays($transaction->plan->duration_in_days),
                ]);
            }

            $transaction->update([
                'status' => PaymentStatus::Paid,
                'provider_payment_id' => $providerPaymentId,
            ]);

            return $license->fresh();
        });
    }

    public function findPendingPayUTransaction(string $providerOrderId): ?LicenseTransaction
    {
        return LicenseTransaction::query()
            ->where('gateway', 'payu')
            ->where('provider_order_id', $providerOrderId)
            ->where('status', PaymentStatus::Pending)
            ->first();
    }

    protected function begin(User $user, LicensePlan $plan, UserLicense $license, string $type, float $amount): array
    {
        $transaction = LicenseTransaction::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'user_license_id' => $license->id,
            'license_plan_id' => $plan->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => 'INR',
            'gateway' => $this->gateway->gateway(),
            'environment' => $this->gateway->environment(),
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $checkout = $this->gateway->start($transaction);
        } catch (\Throwable $exception) {
            $transaction->update(['status' => PaymentStatus::Failed]);
            throw ValidationException::withMessages(['payment' => 'Could not start payment. Check the selected gateway credentials and try again.']);
        }

        return compact('license', 'transaction', 'checkout');
    }
}