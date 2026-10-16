<?php

namespace Tests\Feature\Communication;

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallParticipantLeft;
use App\Events\CallParticipantUpdated;
use App\Events\CallRejected;
use App\Events\IncomingCall;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\CallSession;
use App\Models\LicensePlan;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use App\Notifications\MissedCallNotification;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    private function connect(User $tracker, User $tracked): void
    {
        $license = UserLicense::create([
            'user_id' => $tracker->id,
            'assigned_tracked_user_id' => $tracked->id,
            'license_plan_id' => LicensePlan::firstOrCreate(
                ['name' => 'Test Daily'],
                ['type' => LicenseType::Daily, 'duration_in_days' => 1, 'price' => 1, 'renewal_price' => 1, 'is_free' => false, 'status' => UserStatus::Active],
            )->id,
            'license_number' => 'TEST-'.str()->uuid(),
            'purchase_date' => now(),
            'activation_date' => now(),
            'expiry_date' => now()->addDay(),
            'status' => LicenseStatus::Active,
            'payment_status' => PaymentStatus::Paid,
        ]);

        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'user_license_id' => $license->id,
            'relationship_name' => 'Test Relation',
            'status' => 'active',
        ]);
    }

    public function test_initiating_a_call_in_a_private_conversation_creates_session_and_broadcasts(): void
    {
        Event::fake([IncomingCall::class]);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $response = $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'video']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', ['conversation_id' => $conversation->id, 'status' => 'ringing']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $a->id, 'status' => 'joined']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $b->id, 'status' => 'invited']);

        Event::assertDispatched(IncomingCall::class, fn ($event) => $event->calleeId === $b->id);
    }

    public function test_initiating_a_group_call_invites_every_other_member(): void
    {
        Event::fake([IncomingCall::class]);

        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->connect($owner, $memberA);
        $this->connect($owner, $memberB);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$memberA->id, $memberB->id]);

        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'ringing');

        $this->assertDatabaseHas('call_participants', ['user_id' => $owner->id, 'status' => 'joined']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $memberA->id, 'status' => 'invited']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $memberB->id, 'status' => 'invited']);

        Event::assertDispatched(IncomingCall::class, fn ($event) => $event->calleeId === $memberA->id);
        Event::assertDispatched(IncomingCall::class, fn ($event) => $event->calleeId === $memberB->id);
    }

    public function test_multiple_members_can_join_a_group_call_without_ending_it_for_others(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->connect($owner, $memberA);
        $this->connect($owner, $memberB);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$memberA->id, $memberB->id]);

        Sanctum::actingAs($owner);
        $call = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice'])->json('data');

        Event::fake([CallAccepted::class]);
        Sanctum::actingAs($memberA);
        $this->postJson("/api/v1/calls/{$call['uuid']}/accept")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ongoing']);
        Event::assertDispatched(CallAccepted::class, fn ($event) => $event->userId === $memberA->id && $event->existingParticipants === [
            ['id' => $owner->id, 'name' => $owner->name],
        ]);

        Sanctum::actingAs($memberB);
        $this->postJson("/api/v1/calls/{$call['uuid']}/accept")->assertOk();

        $this->assertDatabaseHas('call_participants', ['user_id' => $memberA->id, 'status' => 'joined']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $memberB->id, 'status' => 'joined']);
        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ongoing']);
    }

    public function test_one_member_leaving_a_group_call_does_not_end_it_for_the_others(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->connect($owner, $memberA);
        $this->connect($owner, $memberB);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$memberA->id, $memberB->id]);

        Sanctum::actingAs($owner);
        $call = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($memberA);
        $this->postJson("/api/v1/calls/{$call['uuid']}/accept")->assertOk();

        Event::fake([CallParticipantLeft::class]);
        $this->postJson("/api/v1/calls/{$call['uuid']}/leave")->assertOk();

        $this->assertDatabaseHas('call_participants', ['user_id' => $memberA->id, 'status' => 'left']);
        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ongoing']);
        Event::assertDispatched(CallParticipantLeft::class, fn ($event) => $event->userId === $memberA->id);

        Event::fake([CallEnded::class]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/calls/{$call['uuid']}/leave")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ended', 'ended_reason' => 'hangup']);
        Event::assertDispatched(CallEnded::class);
    }

    public function test_declining_a_group_call_invite_does_not_end_it_for_others_still_ringing(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->connect($owner, $memberA);
        $this->connect($owner, $memberB);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$memberA->id, $memberB->id]);

        Sanctum::actingAs($owner);
        $call = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice'])->json('data');

        Event::fake([CallRejected::class]);
        Sanctum::actingAs($memberA);
        $this->postJson("/api/v1/calls/{$call['uuid']}/reject")->assertOk();

        $this->assertDatabaseHas('call_participants', ['user_id' => $memberA->id, 'status' => 'declined']);
        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ringing']);
        Event::assertDispatched(CallRejected::class);
    }

    public function test_starting_a_call_while_one_is_already_active_joins_the_existing_room(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->connect($owner, $memberA);
        $this->connect($owner, $memberB);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$memberA->id, $memberB->id]);

        Sanctum::actingAs($owner);
        $call = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($memberA);
        $duplicate = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice']);

        $duplicate->assertCreated();
        $duplicate->assertJsonPath('data.uuid', $call['uuid']);
        $duplicate->assertJsonPath('data.status', 'ongoing');

        $this->assertDatabaseHas('call_participants', ['call_session_id' => CallSession::where('uuid', $call['uuid'])->value('id'), 'user_id' => $memberA->id, 'status' => 'joined']);
        $this->assertDatabaseCount('call_sessions', 1);
    }

    public function test_a_group_call_that_nobody_answers_ends_missed_and_notifies_every_invitee(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->connect($owner, $memberA);
        $this->connect($owner, $memberB);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$memberA->id, $memberB->id]);

        Sanctum::actingAs($owner);
        $call = $this->postJson("/api/v1/conversations/{$group->uuid}/calls", ['type' => 'voice'])->json('data');

        Notification::fake();
        $this->postJson("/api/v1/calls/{$call['uuid']}/missed")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'missed']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $memberA->id, 'status' => 'missed']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $memberB->id, 'status' => 'missed']);
        Notification::assertSentTo($memberA, MissedCallNotification::class);
        Notification::assertSentTo($memberB, MissedCallNotification::class);
    }

    public function test_calling_a_busy_callee_auto_resolves_to_busy_without_ringing(): void
    {
        Event::fake([IncomingCall::class]);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->connect($a, $b);
        $this->connect($c, $b);

        $conversationAB = app(ConversationService::class)->createPrivateConversation($a, $b);
        $conversationCB = app(ConversationService::class)->createPrivateConversation($c, $b);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/conversations/{$conversationAB->uuid}/calls", ['type' => 'voice'])->assertCreated();

        Event::fake([IncomingCall::class]);
        Sanctum::actingAs($c);
        $response = $this->postJson("/api/v1/conversations/{$conversationCB->uuid}/calls", ['type' => 'voice']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'busy');
        Event::assertNotDispatched(IncomingCall::class);
    }

    public function test_accept_reject_and_leave_transition_status_and_broadcast(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $call = $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'voice'])->json('data');

        Event::fake([CallAccepted::class]);
        Sanctum::actingAs($b);
        $this->postJson("/api/v1/calls/{$call['uuid']}/accept")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ongoing']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $b->id, 'status' => 'joined']);
        Event::assertDispatched(CallAccepted::class);

        Event::fake([CallEnded::class]);
        $this->postJson("/api/v1/calls/{$call['uuid']}/leave")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ended', 'ended_reason' => 'hangup']);
        Event::assertDispatched(CallEnded::class);
    }

    public function test_rejecting_a_call_ends_it_and_broadcasts(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $call = $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'voice'])->json('data');

        Event::fake([CallRejected::class]);
        Sanctum::actingAs($b);
        $this->postJson("/api/v1/calls/{$call['uuid']}/reject")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ended', 'ended_reason' => 'declined']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $b->id, 'status' => 'declined']);
        Event::assertDispatched(CallRejected::class);
    }

    public function test_marking_missed_ends_the_session_and_notifies_the_callee(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $call = $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'voice'])->json('data');

        Notification::fake();
        $this->postJson("/api/v1/calls/{$call['uuid']}/missed")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'missed']);
        $this->assertDatabaseHas('call_participants', ['user_id' => $b->id, 'status' => 'missed']);
        Notification::assertSentTo($b, MissedCallNotification::class);
    }

    public function test_a_non_member_cannot_initiate_accept_or_view_a_call(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $stranger = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $call = $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'voice'])->assertForbidden();
        $this->postJson("/api/v1/calls/{$call['uuid']}/accept")->assertForbidden();
        $this->getJson("/api/v1/conversations/{$conversation->uuid}/calls")->assertForbidden();
    }

    public function test_call_history_endpoint_returns_calls_for_the_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/calls", ['type' => 'video'])->assertCreated();

        $history = $this->getJson("/api/v1/conversations/{$conversation->uuid}/calls");
        $history->assertOk();
        $history->assertJsonCount(1, 'data');
        $history->assertJsonPath('data.0.type', 'video');
    }

    public function test_a_stale_abandoned_ringing_call_does_not_permanently_block_future_calls(): void
    {
        Event::fake([IncomingCall::class]);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->connect($a, $b);
        $this->connect($c, $b);

        $conversationAB = app(ConversationService::class)->createPrivateConversation($a, $b);
        $conversationCB = app(ConversationService::class)->createPrivateConversation($c, $b);

        Sanctum::actingAs($a);
        $call = $this->postJson("/api/v1/conversations/{$conversationAB->uuid}/calls", ['type' => 'voice'])->json('data');

        // Simulate A closing the tab before their 30s client-side timeout
        // ever fires the "missed" endpoint — the session is left stuck at
        // "ringing" with no natural way to resolve itself.
        CallSession::where('uuid', $call['uuid'])->update(['created_at' => now()->subMinutes(5)]);

        Sanctum::actingAs($c);
        $response = $this->postJson("/api/v1/conversations/{$conversationCB->uuid}/calls", ['type' => 'voice']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'missed']);
        Event::assertDispatched(IncomingCall::class, fn ($event) => $event->calleeId === $b->id);
    }

    public function test_an_abandoned_ongoing_call_does_not_permanently_block_future_calls(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->connect($a, $b);
        $this->connect($c, $b);

        $conversationAB = app(ConversationService::class)->createPrivateConversation($a, $b);
        $conversationCB = app(ConversationService::class)->createPrivateConversation($c, $b);

        Sanctum::actingAs($a);
        $call = $this->postJson("/api/v1/conversations/{$conversationAB->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/calls/{$call['uuid']}/accept")->assertOk();

        // Simulate both tabs being closed without ever hitting "leave" —
        // the session is stuck "ongoing" forever with no natural way to
        // resolve itself, which would otherwise mark B as permanently busy.
        CallSession::where('uuid', $call['uuid'])->update(['updated_at' => now()->subHours(5)]);

        Sanctum::actingAs($c);
        $response = $this->postJson("/api/v1/conversations/{$conversationCB->uuid}/calls", ['type' => 'voice']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'ringing');

        $this->assertDatabaseHas('call_sessions', ['uuid' => $call['uuid'], 'status' => 'ended', 'ended_reason' => 'hangup']);
        $this->assertDatabaseHas('call_participants', [
            'call_session_id' => CallSession::where('uuid', $call['uuid'])->value('id'),
            'user_id' => $b->id,
            'status' => 'left',
        ]);
    }

    public function test_calling_someone_already_in_an_ongoing_call_rings_as_call_waiting_instead_of_busy(): void
    {
        Event::fake([IncomingCall::class]);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->connect($a, $b);
        $this->connect($c, $b);

        $conversationAB = app(ConversationService::class)->createPrivateConversation($a, $b);
        $conversationCB = app(ConversationService::class)->createPrivateConversation($c, $b);

        Sanctum::actingAs($a);
        $firstCall = $this->postJson("/api/v1/conversations/{$conversationAB->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/calls/{$firstCall['uuid']}/accept")->assertOk();

        Event::fake([IncomingCall::class]);
        Sanctum::actingAs($c);
        $response = $this->postJson("/api/v1/conversations/{$conversationCB->uuid}/calls", ['type' => 'voice']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'ringing');
        Event::assertDispatched(IncomingCall::class, fn ($event) => $event->calleeId === $b->id && $event->isCallWaiting === true);
    }

    public function test_holding_the_current_call_and_accepting_the_waiting_call_broadcasts_hold_state(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->connect($a, $b);
        $this->connect($c, $b);

        $conversationAB = app(ConversationService::class)->createPrivateConversation($a, $b);
        $conversationCB = app(ConversationService::class)->createPrivateConversation($c, $b);

        Sanctum::actingAs($a);
        $firstCall = $this->postJson("/api/v1/conversations/{$conversationAB->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/calls/{$firstCall['uuid']}/accept")->assertOk();

        Sanctum::actingAs($c);
        $waitingCall = $this->postJson("/api/v1/conversations/{$conversationCB->uuid}/calls", ['type' => 'voice'])->json('data');

        Sanctum::actingAs($b);

        Event::fake([CallParticipantUpdated::class]);
        $this->patchJson("/api/v1/calls/{$firstCall['uuid']}", ['is_on_hold' => true])->assertOk();

        $this->assertDatabaseHas('call_participants', [
            'user_id' => $b->id,
            'is_on_hold' => true,
        ]);
        Event::assertDispatched(CallParticipantUpdated::class);

        Event::fake([CallAccepted::class]);
        $this->postJson("/api/v1/calls/{$waitingCall['uuid']}/accept")->assertOk();

        $this->assertDatabaseHas('call_sessions', ['uuid' => $waitingCall['uuid'], 'status' => 'ongoing']);
        $this->assertDatabaseHas('call_sessions', ['uuid' => $firstCall['uuid'], 'status' => 'ongoing']);
    }
}
