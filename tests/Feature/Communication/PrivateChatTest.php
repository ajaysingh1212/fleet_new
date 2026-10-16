<?php

namespace Tests\Feature\Communication;

use App\Events\MessageSent;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\ConversationMember;
use App\Models\LicensePlan;
use App\Models\Message;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateChatTest extends TestCase
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

    public function test_sending_a_message_persists_and_broadcasts(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);

        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Event::fake([MessageSent::class]);
        Sanctum::actingAs($a);

        $response = $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", [
            'body' => 'Hello there',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Hello there']);

        Event::assertDispatched(MessageSent::class, fn ($event) => $event->message->body === 'Hello there');
    }

    public function test_a_non_member_cannot_view_or_send_messages(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $stranger = User::factory()->create();
        $this->connect($a, $b);

        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/messages", ['body' => 'hi'])->assertForbidden();
    }

    public function test_only_the_sender_can_edit_or_delete_for_everyone(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id, 'body' => 'Original']);

        Sanctum::actingAs($b);
        $this->patchJson("/api/v1/messages/{$message->uuid}", ['body' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/v1/messages/{$message->uuid}")->assertForbidden();

        Sanctum::actingAs($a);
        $this->patchJson("/api/v1/messages/{$message->uuid}", ['body' => 'Edited'])->assertOk();
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'body' => 'Edited', 'is_edited' => true]);

        $this->deleteJson("/api/v1/messages/{$message->uuid}")->assertOk();
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_delete_for_me_only_hides_the_message_for_that_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id]);

        Sanctum::actingAs($b);
        $this->deleteJson("/api/v1/messages/{$message->uuid}/for-me")->assertOk();

        $this->assertDatabaseHas('message_user_deletes', ['message_id' => $message->id, 'user_id' => $b->id]);

        $bResponse = $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages");
        $bResponse->assertJsonMissing(['uuid' => $message->uuid]);

        Sanctum::actingAs($a);
        $aResponse = $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages");
        $aResponse->assertJsonFragment(['uuid' => $message->uuid]);
    }

    public function test_reacting_twice_with_the_same_emoji_removes_it(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id]);

        Sanctum::actingAs($b);

        $this->postJson("/api/v1/messages/{$message->uuid}/react", ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseHas('message_reactions', ['message_id' => $message->id, 'user_id' => $b->id, 'emoji' => '👍']);

        $this->postJson("/api/v1/messages/{$message->uuid}/react", ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseMissing('message_reactions', ['message_id' => $message->id, 'user_id' => $b->id]);
    }

    public function test_mark_read_sets_read_at_and_updates_last_read_message_id(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id]);

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/conversations/{$conversation->uuid}/read")->assertOk();

        $this->assertDatabaseHas('message_reads', ['message_id' => $message->id, 'user_id' => $b->id]);

        $member = ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $b->id)->first();
        $this->assertSame($message->id, $member->last_read_message_id);
    }

    public function test_conversation_list_reflects_unread_count(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $a->id]);

        Sanctum::actingAs($b);

        $response = $this->getJson('/api/v1/conversations');
        $response->assertOk();
        $response->assertJsonFragment(['uuid' => $conversation->uuid, 'unread_count' => 2]);
    }

    public function test_user_can_star_and_filter_starred_messages(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id,
            'body' => 'Important note',
        ]);

        Sanctum::actingAs($b);

        $this->postJson("/api/v1/messages/{$message->uuid}/star")->assertOk();
        $this->assertDatabaseHas('message_stars', ['message_id' => $message->id, 'user_id' => $b->id]);

        $response = $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages?starred_only=1");
        $response->assertOk()->assertJsonFragment(['uuid' => $message->uuid]);

        $this->deleteJson("/api/v1/messages/{$message->uuid}/star")->assertOk();
        $this->assertDatabaseMissing('message_stars', ['message_id' => $message->id, 'user_id' => $b->id]);
    }

    public function test_messages_endpoint_can_filter_by_search_and_pinned_state(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        $pinned = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id,
            'body' => 'Needle message',
            'is_pinned' => true,
        ]);

        $other = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id,
            'body' => 'Haystack message',
            'is_pinned' => false,
        ]);

        Sanctum::actingAs($b);

        $search = $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages?q=Needle");
        $search->assertOk()->assertJsonFragment(['uuid' => $pinned->uuid])->assertJsonMissing(['uuid' => $other->uuid]);

        $pinnedOnly = $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages?pinned_only=1");
        $pinnedOnly->assertOk()->assertJsonFragment(['uuid' => $pinned->uuid])->assertJsonMissing(['uuid' => $other->uuid]);
    }
}
