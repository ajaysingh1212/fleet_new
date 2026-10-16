<?php

namespace Tests\Feature\Admin;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Models\LicensePlan;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\LicenseTransferService;
use App\Services\TrackingRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LicenseTransferTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_transfer_page_shows_user_search_and_admin_source_selection(): void
    {
        $this->actingAsPlainUser();
        $this->get(route('license-transfers.index'))
            ->assertOk()
            ->assertSee('Transfer an unused license')
            ->assertSee('Search by email or phone')
            ->assertDontSee('License owner');

        $this->actingAsSuperAdmin();
        $this->get(route('license-transfers.index'))
            ->assertOk()
            ->assertSee('License owner')
            ->assertSee('Grant a new license');
    }

    public function test_user_search_matches_email_or_phone_and_returns_license_summary(): void
    {
        $this->actingAsPlainUser();
        $recipient = User::factory()->create([
            'name' => 'Searchable Recipient',
            'email' => 'recipient.lookup@example.test',
            'phone' => '9876501234',
        ]);

        $this->get(route('license-transfers.search-users', ['q' => 'recipient.lookup', 'mode' => 'recipient']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $recipient->id,
                'name' => 'Searchable Recipient',
                'email' => 'recipient.lookup@example.test',
                'phone' => '9876501234',
                'premium' => false,
                'license_count' => 0,
                'demo_license_count' => 0,
            ]);

        $this->get(route('license-transfers.search-users', ['q' => '987650', 'mode' => 'recipient']))
            ->assertOk()
            ->assertJsonFragment(['id' => $recipient->id]);
    }

    public function test_user_can_transfer_an_unused_license_after_password_confirmation(): void
    {
        $sender = $this->actingAsPlainUser();
        /** @var User $recipient */
        $recipient = User::factory()->create();
        $license = app(LicenseService::class)->issueForAdmin($sender, $this->makePlan());

        $this->post(route('license-transfers.store'), [
            'recipient_user_id' => $recipient->id,
            'license_uuid' => $license->uuid,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');
        $this->assertSame($sender->id, $license->fresh()->user_id);

        $this->post(route('license-transfers.store'), [
            'recipient_user_id' => $recipient->id,
            'license_uuid' => $license->uuid,
            'password' => 'password',
        ])->assertRedirect(route('license-transfers.index'));

        $this->assertSame($recipient->id, $license->fresh()->user_id);
        $this->assertDatabaseHas('license_transfers', [
            'user_license_id' => $license->id,
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
        ]);
        $this->assertFalse($license->fresh()->is_free_claim);
    }

    public function test_user_cannot_transfer_to_an_existing_premium_account(): void
    {
        $sender = $this->actingAsPlainUser();
        $recipient = User::factory()->create();
        $license = app(LicenseService::class)->issueForAdmin($sender, $this->makePlan());
        app(LicenseService::class)->issueForAdmin($recipient, $this->makePlan(['name' => 'Recipient Premium']));

        $this->from(route('license-transfers.index'))
            ->post(route('license-transfers.store'), [
                'recipient_user_id' => $recipient->id,
                'license_uuid' => $license->uuid,
                'password' => 'password',
            ])
            ->assertRedirect(route('license-transfers.index'))
            ->assertSessionHasErrors('recipient');

        $this->assertSame($sender->id, $license->fresh()->user_id);
        $this->assertDatabaseCount('license_transfers', 0);
    }

    public function test_admin_can_transfer_between_any_two_users_without_source_password(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $source = User::factory()->create();
        $recipient = User::factory()->create();
        $license = app(LicenseService::class)->issueForAdmin($source, $this->makePlan());

        $this->post(route('license-transfers.store'), [
            'source_user_id' => $source->id,
            'recipient_user_id' => $recipient->id,
            'license_uuid' => $license->uuid,
        ])->assertRedirect(route('license-transfers.index'));

        $this->assertSame($recipient->id, $license->fresh()->user_id);
        $this->assertSame($admin->id, $license->fresh()->transfers()->firstOrFail()->transferred_by_user_id);
    }

    public function test_free_claim_remains_used_by_original_user_after_transfer_and_self_use_shows_demo_expiry(): void
    {
        $owner = $this->actingAsPlainUser();
        /** @var User $recipient */
        $recipient = User::factory()->create();
        $freePlan = $this->makePlan([
            'name' => 'Daily Demo',
            'type' => 'daily',
            'duration_in_days' => 1,
            'price' => 0,
            'renewal_price' => 0,
            'is_free' => true,
        ]);
        $freeLicense = app(LicenseService::class)->claimFree($owner, $freePlan);

        $this->post(route('license-transfers.store'), [
            'recipient_user_id' => $recipient->id,
            'license_uuid' => $freeLicense->uuid,
            'password' => 'password',
        ])->assertRedirect(route('license-transfers.index'));

        $this->assertSame($owner->id, $freeLicense->fresh()->free_claimed_by_user_id);
        $this->assertSame($recipient->id, $freeLicense->fresh()->user_id);

        $this->actingAs($recipient);
        $this->from(route('my-licenses.plans'))
            ->post(route('my-licenses.purchase'), ['license_plan_id' => $freePlan->id])
            ->assertSessionHasErrors('license_plan_id');

        /** @var User $nextRecipient */
        $nextRecipient = User::factory()->create();
        $this->post(route('license-transfers.store'), [
            'recipient_user_id' => $nextRecipient->id,
            'license_uuid' => $freeLicense->uuid,
            'password' => 'password',
        ])->assertRedirect(route('license-transfers.index'));

        $this->actingAs($nextRecipient);
        $this->post(route('my-licenses.use-for-self', $freeLicense))->assertRedirect(route('my-licenses.index'));
        $this->assertSame(LicenseStatus::Active, $freeLicense->fresh()->status);
        $this->assertSame('self', $freeLicense->fresh()->usage_type);
        $this->assertSame($nextRecipient->id, $freeLicense->fresh()->assigned_tracked_user_id);
        $this->assertTrue($freeLicense->fresh()->expiry_date->equalTo($freeLicense->fresh()->activation_date->copy()->addDay()));

        $this->actingAs($owner)
            ->from(route('my-licenses.plans'))
            ->post(route('my-licenses.purchase'), ['license_plan_id' => $freePlan->id])
            ->assertSessionHasErrors('license_plan_id');
    }

    public function test_only_an_unused_license_can_be_transferred(): void
    {
        $sender = $this->actingAsPlainUser();
        $recipient = User::factory()->create();
        $license = app(LicenseService::class)->issueForAdmin($sender, $this->makePlan());
        $license->update([
            'status' => LicenseStatus::Active,
            'payment_status' => PaymentStatus::Paid,
            'assigned_tracked_user_id' => $sender->id,
            'usage_type' => 'self',
            'activation_date' => now(),
            'expiry_date' => now()->addDays(1),
        ]);

        $this->from(route('license-transfers.index'))
            ->post(route('license-transfers.store'), [
                'recipient_user_id' => $recipient->id,
                'license_uuid' => $license->uuid,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('license');

        $this->assertSame($sender->id, $license->fresh()->user_id);
    }

    public function test_tracked_user_sees_free_demo_status_and_expiry_on_dashboard(): void
    {
        $tracker = $this->actingAsPlainUser();
        /** @var User $trackedUser */
        $trackedUser = User::factory()->create();
        $freePlan = $this->makePlan([
            'name' => 'Tracked Demo',
            'type' => 'daily',
            'duration_in_days' => 1,
            'price' => 0,
            'renewal_price' => 0,
            'is_free' => true,
        ]);
        $license = app(LicenseService::class)->claimFree($tracker, $freePlan);

        app(TrackingRelationService::class)->create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $trackedUser->id,
            'relationship_name' => 'Team member',
            'status' => 'active',
            'created_by' => $tracker->id,
        ]);

        $this->actingAs($trackedUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Free demo license')
            ->assertSee($tracker->name)
            ->assertSee($license->fresh()->expiry_date->format('d M Y H:i'));
    }

    private function makePlan(array $overrides = []): LicensePlan
    {
        return LicensePlan::create(array_merge([
            'uuid' => Str::uuid(),
            'name' => 'Daily Plan',
            'type' => 'daily',
            'duration_in_days' => 1,
            'price' => 9,
            'renewal_price' => 7,
            'is_free' => false,
            'status' => 'active',
            'display_order' => 1,
        ], $overrides));
    }
}