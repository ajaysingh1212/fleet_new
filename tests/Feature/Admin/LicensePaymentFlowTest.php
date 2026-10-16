<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Models\LicensePlan;
use App\Models\LicenseTransaction;
use App\Models\User;
use App\Services\LicensePaymentService;
use App\Services\LicenseService;
use App\Services\PaymentGatewayService;
use App\Services\SettingsService;
use App\Services\TrackingRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LicensePaymentFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_paid_license_stays_unusable_until_server_verified_payment_then_starts_on_first_relation(): void
    {
        $user = $this->actingAsPlainUser();
        $this->seedSettings();
        app(SettingsService::class)->set('payments', 'razorpay_test_key_id', 'test_key', 'secret');
        app(SettingsService::class)->set('payments', 'razorpay_test_key_secret', 'test_secret', 'secret');
        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['id' => 'order_test_1'], 200),
            'https://api.razorpay.com/v1/payments/pay_test_1' => Http::response([
                'id' => 'pay_test_1',
                'order_id' => 'order_test_1',
                'status' => 'captured',
                'amount' => 4900,
            ], 200),
        ]);
        $plan = $this->makePlan();

        $checkout = $this->post(route('my-licenses.purchase'), ['license_plan_id' => $plan->id]);
        $checkout->assertOk()->assertViewIs('user.licenses.checkout');

        $transaction = LicenseTransaction::query()->firstOrFail();
        $license = $transaction->license;
        $this->assertSame(PaymentStatus::Pending, $license->payment_status);
        $this->assertNull($license->expiry_date);
        $this->assertSame('order_test_1', $transaction->provider_order_id);

        $paymentId = 'pay_test_1';
        $signature = hash_hmac('sha256', 'order_test_1|'.$paymentId, 'test_secret');
        $this->post(route('my-licenses.payment-verify', $transaction->uuid), [
            'razorpay_order_id' => 'order_test_1',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ])->assertRedirect(route('my-licenses.index'));

        $this->assertSame(PaymentStatus::Paid, $license->fresh()->payment_status);
        $this->assertSame(LicenseStatus::Pending, $license->fresh()->status);
        $this->assertNull($license->fresh()->expiry_date);

        $trackedUser = User::factory()->create();
        $this->post(route('my-tracking.store'), [
            'tracked_user_id' => $trackedUser->id,
            'relationship_name' => 'Field Manager',
        ])->assertRedirect(route('my-tracking.index'));

        $this->assertSame(LicenseStatus::Active, $license->fresh()->status);
        $this->assertSame($trackedUser->id, $license->fresh()->assigned_tracked_user_id);
        $activatedLicense = $license->fresh();
        $this->assertTrue($activatedLicense->expiry_date->equalTo($activatedLicense->activation_date->copy()->addDays(30)));
    }

    public function test_free_license_can_only_be_claimed_once_per_account(): void
    {
        $user = $this->actingAsPlainUser();
        $plan = $this->makePlan(['name' => 'Free Trial', 'price' => 0, 'renewal_price' => 0, 'is_free' => true]);

        $this->post(route('my-licenses.purchase'), ['license_plan_id' => $plan->id])
            ->assertRedirect(route('my-tracking.create'));
        $license = $user->userLicenses()->firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $license->payment_status);
        $this->assertNull($license->activation_date);

        $this->from(route('my-licenses.plans'))
            ->post(route('my-licenses.purchase'), ['license_plan_id' => $plan->id])
            ->assertRedirect(route('my-licenses.plans'))
            ->assertSessionHasErrors('license_plan_id');
        $this->assertSame(1, $user->userLicenses()->count());
    }

    public function test_successful_renewal_is_applied_once_and_extends_from_current_expiry(): void
    {
        $user = $this->actingAsPlainUser();
        $plan = $this->makePlan();
        $license = app(LicenseService::class)->issueForAdmin($user, $plan);
        $trackedUser = User::factory()->create();
        app(TrackingRelationService::class)->create([
            'tracker_user_id' => $user->id,
            'tracked_user_id' => $trackedUser->id,
            'relationship_name' => 'Field Manager',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $license->update(['expiry_date' => now()->addDays(10)]);
        $originalExpiry = $license->fresh()->expiry_date;

        $transaction = LicenseTransaction::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'user_license_id' => $license->id,
            'license_plan_id' => $plan->id,
            'type' => 'renewal',
            'amount' => $plan->renewal_price,
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'environment' => 'test',
            'status' => PaymentStatus::Pending,
        ]);

        app(LicensePaymentService::class)->complete($transaction, 'pay_renewal');
        $renewedExpiry = $license->fresh()->expiry_date;
        $this->assertTrue($renewedExpiry->equalTo($originalExpiry->copy()->addDays(30)));

        app(LicensePaymentService::class)->complete($transaction, 'pay_duplicate');
        $this->assertTrue($license->fresh()->expiry_date->equalTo($renewedExpiry));
        $this->assertSame('pay_renewal', $transaction->fresh()->provider_payment_id);
    }

    public function test_admin_can_review_renewal_transactions_in_their_own_menu(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $license = app(LicenseService::class)->issueForAdmin($user, $plan);
        LicenseTransaction::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'user_license_id' => $license->id,
            'license_plan_id' => $plan->id,
            'type' => 'renewal',
            'amount' => $plan->renewal_price,
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'environment' => 'test',
            'status' => PaymentStatus::Paid,
            'provider_order_id' => 'renew_order_1',
            'provider_payment_id' => 'renew_payment_1',
        ]);

        $this->get(route('admin.license-renewals.index'))
            ->assertOk()
            ->assertSee('License Renewals')
            ->assertSee($user->name)
            ->assertSee('renew_order_1');
    }

    public function test_payu_callback_must_match_its_reverse_hash_and_amount(): void
    {
        $user = $this->actingAsPlainUser();
        $this->seedSettings();
        $settings = app(SettingsService::class);
        $settings->set('payments', 'payu_test_merchant_key', 'merchant_key', 'secret');
        $settings->set('payments', 'payu_test_salt', 'merchant_salt', 'secret');
        $plan = $this->makePlan();
        $license = app(LicenseService::class)->purchase($user, $plan);
        $transaction = LicenseTransaction::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'user_license_id' => $license->id,
            'license_plan_id' => $plan->id,
            'type' => 'purchase',
            'amount' => 49,
            'currency' => 'INR',
            'gateway' => 'payu',
            'environment' => 'test',
            'status' => PaymentStatus::Pending,
            'provider_order_id' => 'PAYU_TXN_001',
        ]);
        $payload = [
            'txnid' => 'PAYU_TXN_001',
            'status' => 'success',
            'amount' => '49.00',
            'email' => $user->email,
            'firstname' => $user->name,
            'productinfo' => 'License purchase',
            'mihpayid' => 'payu_payment_1',
        ];
        $payload['hash'] = hash('sha512', implode('|', [
            'merchant_salt', 'success', '', '', '', '', '', '', '', '', '', $payload['email'],
            $payload['firstname'], $payload['productinfo'], $payload['amount'], $payload['txnid'], 'merchant_key',
        ]));

        $this->assertSame('payu_payment_1', app(PaymentGatewayService::class)->verify($transaction, $payload));
        $payload['amount'] = '99.00';
        $this->assertNull(app(PaymentGatewayService::class)->verify($transaction, $payload));
    }

    private function makePlan(array $overrides = []): LicensePlan
    {
        return LicensePlan::create(array_merge([
            'uuid' => Str::uuid(),
            'name' => 'Monthly License',
            'type' => 'monthly',
            'duration_in_days' => 30,
            'price' => 49,
            'renewal_price' => 29,
            'is_free' => false,
            'status' => 'active',
            'display_order' => 1,
        ], $overrides));
    }
}