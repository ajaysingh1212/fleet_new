<?php

namespace Tests\Feature\Communication;

use App\Enums\ConversationMemberRole;
use App\Events\ConversationDeleted;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    private function connect(User $tracker, User $tracked): void
    {
        TrackingRelation::firstOrCreate(
            ['tracker_user_id' => $tracker->id, 'tracked_user_id' => $tracked->id],
            ['relationship_name' => 'Test Relation', 'status' => 'active'],
        );
    }

    /**
     * @param  array<int, User>  $members
     */
    private function makeGroup(User $owner, array $members): Conversation
    {
        foreach ($members as $member) {
            $this->connect($owner, $member);
        }

        return app(ConversationService::class)->createGroup(
            $owner,
            ['name' => 'Team'],
            array_map(fn (User $member) => $member->id, $members),
        );
    }

    public function test_creating_a_group_via_http_rejects_an_unconnected_member(): void
    {
        $owner = User::factory()->create();
        $connected = User::factory()->create();
        $stranger = User::factory()->create();
        $this->connect($owner, $connected);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/groups', [
            'name' => 'Team',
            'member_ids' => [$connected->id, $stranger->id],
        ])->assertUnprocessable();
    }

    public function test_a_plain_member_cannot_manage_the_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $newcomer = User::factory()->create();
        $group = $this->makeGroup($owner, [$member]);
        $this->connect($owner, $newcomer);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/groups/{$group->uuid}/members", ['user_id' => $newcomer->id])->assertUnprocessable();
        $this->deleteJson("/api/v1/groups/{$group->uuid}/members/{$owner->id}")->assertUnprocessable();
        $this->patchJson("/api/v1/groups/{$group->uuid}", ['name' => 'Hacked'])->assertUnprocessable();
    }

    public function test_an_admin_can_add_and_remove_members_and_rename_but_not_promote_or_delete(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $newcomer = User::factory()->create();
        $group = $this->makeGroup($owner, [$admin, $member]);
        $this->connect($owner, $newcomer);

        $group->members()->where('user_id', $admin->id)->update(['role' => ConversationMemberRole::Admin]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/groups/{$group->uuid}/members", ['user_id' => $newcomer->id])->assertOk();
        $this->assertTrue($group->fresh()->hasMember($newcomer));

        $this->deleteJson("/api/v1/groups/{$group->uuid}/members/{$member->id}")->assertOk();
        $this->assertFalse($group->fresh()->hasMember($member));

        $this->patchJson("/api/v1/groups/{$group->uuid}", ['name' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $group->fresh()->name);

        $this->postJson("/api/v1/groups/{$group->uuid}/members/{$newcomer->id}/promote")->assertUnprocessable();
        $this->deleteJson("/api/v1/groups/{$group->uuid}")->assertUnprocessable();
    }

    public function test_only_the_owner_can_promote_demote_and_delete(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->makeGroup($owner, [$member]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/groups/{$group->uuid}/members/{$member->id}/promote")->assertOk();
        $this->assertSame(ConversationMemberRole::Admin, $group->members()->where('user_id', $member->id)->first()->role);

        $this->postJson("/api/v1/groups/{$group->uuid}/members/{$member->id}/demote")->assertOk();
        $this->assertSame(ConversationMemberRole::Member, $group->members()->where('user_id', $member->id)->first()->role);

        $this->deleteJson("/api/v1/groups/{$group->uuid}")->assertOk();
        $this->assertSoftDeleted('conversations', ['id' => $group->id]);
    }

    public function test_the_owner_cannot_be_removed_and_cannot_leave(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $group = $this->makeGroup($owner, [$admin]);
        $group->members()->where('user_id', $admin->id)->update(['role' => ConversationMemberRole::Admin]);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/groups/{$group->uuid}/members/{$owner->id}")->assertUnprocessable();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/groups/{$group->uuid}/leave")->assertUnprocessable();
    }

    public function test_a_non_owner_can_leave(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = $this->makeGroup($owner, [$member]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/groups/{$group->uuid}/leave")->assertOk();

        $this->assertFalse($group->fresh()->hasMember($member));
    }

    public function test_membership_changes_create_system_messages_and_broadcast_conversation_updated(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $newcomer = User::factory()->create();
        $this->connect($owner, $member);
        $this->connect($owner, $newcomer);

        Event::fake([ConversationUpdated::class]);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$member->id]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/groups/{$group->uuid}/members", ['user_id' => $newcomer->id])->assertOk();

        Event::assertDispatched(ConversationUpdated::class);

        $this->assertGreaterThanOrEqual(
            2,
            Message::where('conversation_id', $group->id)->where('type', 'system')->count(),
        );
    }

    public function test_deleting_a_group_dispatches_conversation_deleted(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner, []);

        Event::fake([ConversationDeleted::class]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/groups/{$group->uuid}")->assertOk();

        Event::assertDispatched(ConversationDeleted::class, fn ($event) => $event->conversation->id === $group->id);
    }

    public function test_conversation_list_includes_both_private_and_group_conversations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $this->connect($owner, $member);

        $private = app(ConversationService::class)->createPrivateConversation($owner, $member);
        $group = $this->makeGroup($owner, [$member]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertOk();
        $response->assertJsonFragment(['uuid' => $private->uuid]);
        $response->assertJsonFragment(['uuid' => $group->uuid]);
    }
}
