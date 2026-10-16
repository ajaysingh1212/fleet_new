<?php

namespace Tests\Feature\Communication;

use App\Models\LicensePlan;
use App\Models\Message;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Notifications\DiagnosticAlertNotification;
use App\Notifications\GroupInviteNotification;
use App\Notifications\MessageMentionNotification;
use App\Notifications\MessageReactionNotification;
use App\Notifications\NewMessageNotification;
use App\Notifications\TrackingRequestNotification;
use App\Services\ConversationService;
use App\Services\LicenseService;
use App\Services\TrackingRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private function connect(User $tracker, User $tracked): void
    {
        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Test Relation',
            'status' => 'active',
        ]);
    }

    public function test_sending_a_message_notifies_the_other_member(): void
    {
        Notification::fake();

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['body' => 'Hello'])->assertCreated();

        Notification::assertSentTo($b, NewMessageNotification::class);
        Notification::assertNotSentTo($a, NewMessageNotification::class);
    }

    public function test_mentioning_a_member_sends_mention_not_new_message_to_them(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['name' => 'Alice Owner']);
        $mentioned = User::factory()->create(['name' => 'Bob Mentioned']);
        $other = User::factory()->create(['name' => 'Carol Other']);
        $this->connect($owner, $mentioned);
        $this->connect($owner, $other);

        $group = app(ConversationService::class)->createGroup(
            $owner,
            ['name' => 'Team'],
            [$mentioned->id, $other->id],
        );

        Notification::fake();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/conversations/{$group->uuid}/messages", ['body' => 'Hey @Bob check this out'])
            ->assertCreated();

        Notification::assertSentTo($mentioned, MessageMentionNotification::class);
        Notification::assertNotSentTo($mentioned, NewMessageNotification::class);
        Notification::assertSentTo($other, NewMessageNotification::class);
    }

    public function test_reacting_to_someone_elses_message_notifies_the_sender_once(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id]);

        Notification::fake();
        Sanctum::actingAs($b);

        $this->postJson("/api/v1/messages/{$message->uuid}/react", ['emoji' => '👍'])->assertOk();
        Notification::assertSentTo($a, MessageReactionNotification::class);

        // Removing the reaction (same emoji again) must not notify again.
        $this->postJson("/api/v1/messages/{$message->uuid}/react", ['emoji' => '👍'])->assertOk();
        Notification::assertSentToTimes($a, MessageReactionNotification::class, 1);
    }

    public function test_adding_a_member_sends_group_invite_at_creation_and_later(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $initialMember = User::factory()->create();
        $laterMember = User::factory()->create();
        $this->connect($owner, $initialMember);
        $this->connect($owner, $laterMember);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$initialMember->id]);
        Notification::assertSentTo($initialMember, GroupInviteNotification::class);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/groups/{$group->uuid}/members", ['user_id' => $laterMember->id])->assertOk();
        Notification::assertSentTo($laterMember, GroupInviteNotification::class);
    }

    public function test_creating_a_tracking_relation_notifies_the_tracked_user(): void
    {
        $this->seedSettings();

        $plan = LicensePlan::create([
            'uuid' => Str::uuid(),
            'name' => 'Tracking Plan',
            'type' => 'yearly',
            'duration_in_days' => 365,
            'price' => 99,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        app(LicenseService::class)->issueForAdmin($tracker, $plan);

        Notification::fake();

        app(TrackingRelationService::class)->create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Field Manager',
            'status' => 'active',
        ]);

        Notification::assertSentTo($tracked, TrackingRequestNotification::class);
    }

    public function test_a_concerning_diagnostic_event_notifies_trackers_but_a_benign_one_does_not(): void
    {
        Notification::fake();

        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        $this->connect($tracker, $tracked);

        Sanctum::actingAs($tracked);

        $this->postJson('/api/v1/gps/diagnostics', ['event_type' => 'internet_on'])->assertCreated();
        Notification::assertNotSentTo($tracker, DiagnosticAlertNotification::class);

        $this->postJson('/api/v1/gps/diagnostics', ['event_type' => 'poor_accuracy'])->assertCreated();
        Notification::assertSentTo($tracker, DiagnosticAlertNotification::class);
    }

    public function test_reading_a_conversation_marks_pending_message_notifications_read(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['body' => 'Hello'])->assertCreated();

        $this->assertSame(1, $b->fresh()->unreadNotifications()->count());

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/read")->assertOk();

        $this->assertSame(0, $b->fresh()->unreadNotifications()->count());
    }

    public function test_notification_index_filters_by_category_and_status(): void
    {
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        $this->connect($tracker, $tracked);

        $a = User::factory()->create();
        $this->connect($a, $tracker);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $tracker);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['body' => 'Hi'])->assertCreated();

        $this->actingAs($tracker);
        $trackingOnly = $this->get('/notifications?category=tracking');
        $trackingOnly->assertOk();

        $messageOnly = $this->get('/notifications?category=message');
        $messageOnly->assertOk();

        $unreadOnly = $this->get('/notifications?status=unread');
        $unreadOnly->assertOk();
    }
}
