<?php

namespace Tests\Feature\Communication;

use App\Enums\ConversationMemberRole;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Services\ChatAuthorizationService;
use App\Services\ConversationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConversationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function connect(User $tracker, User $tracked): void
    {
        TrackingRelation::create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => 'Test Relation',
            'status' => 'active',
        ]);
    }

    public function test_tracking_connected_users_can_open_a_private_conversation(): void
    {
        $tracker = User::factory()->create();
        $tracked = User::factory()->create();
        $this->connect($tracker, $tracked);

        $conversation = app(ConversationService::class)->createPrivateConversation($tracker, $tracked);

        $this->assertTrue($conversation->hasMember($tracker));
        $this->assertTrue($conversation->hasMember($tracked));
    }

    public function test_unconnected_users_cannot_open_a_private_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(ConversationService::class)->createPrivateConversation($a, $b);
    }

    public function test_group_creator_can_only_add_users_they_are_connected_to(): void
    {
        $owner = User::factory()->create();
        $trackedA = User::factory()->create();
        $trackedB = User::factory()->create();
        $stranger = User::factory()->create();

        $this->connect($owner, $trackedA);
        $this->connect($owner, $trackedB);

        $group = app(ConversationService::class)->createGroup(
            $owner,
            ['name' => 'Field Team'],
            [$trackedA->id, $trackedB->id],
        );

        $this->assertTrue($group->hasMember($owner));
        $this->assertTrue($group->hasMember($trackedA));
        $this->assertTrue($group->hasMember($trackedB));

        $this->expectException(ValidationException::class);

        app(ConversationService::class)->createGroup(
            $owner,
            ['name' => 'Should Fail'],
            [$stranger->id],
        );
    }

    public function test_a_plain_member_cannot_perform_owner_or_admin_actions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $newcomer = User::factory()->create();

        $this->connect($owner, $member);
        $this->connect($owner, $newcomer);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$member->id]);

        $this->expectException(ValidationException::class);

        app(ConversationService::class)->addMember($group, $member, $newcomer);
    }

    public function test_the_group_owner_can_add_a_connected_member(): void
    {
        $owner = User::factory()->create();
        $existing = User::factory()->create();
        $newMember = User::factory()->create();

        $this->connect($owner, $existing);
        $this->connect($owner, $newMember);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$existing->id]);

        app(ConversationService::class)->addMember($group, $owner, $newMember);

        $this->assertTrue($group->fresh()->hasMember($newMember));
    }

    public function test_an_admin_role_can_message_anyone_without_a_tracking_relation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $anyone = User::factory()->create();

        $conversation = app(ConversationService::class)->createPrivateConversation($admin, $anyone);

        $this->assertTrue($conversation->hasMember($admin));
        $this->assertTrue($conversation->hasMember($anyone));
    }

    public function test_a_stranger_cannot_message_an_admin_without_a_tracking_relation(): void
    {
        // The admin-bypass is actor-only: an admin can reach out to anyone,
        // but that doesn't flip around into "anyone can freely reach an
        // admin" just because the admin happens to be the target.
        $this->seed(RoleAndPermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(ConversationService::class)->createPrivateConversation($stranger, $admin);
    }

    public function test_a_stranger_cannot_add_an_admin_to_a_group_without_a_tracking_relation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->expectException(ValidationException::class);

        app(ConversationService::class)->createGroup($owner, ['name' => 'Should Fail'], [$admin->id]);
    }

    public function test_can_communicate_reflects_either_direction_of_the_relation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($b, $a);

        $this->assertTrue(app(ChatAuthorizationService::class)->canCommunicate($a, $b));
        $this->assertTrue(app(ChatAuthorizationService::class)->canCommunicate($b, $a));
    }

    public function test_group_owner_role_is_recorded_correctly(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $this->connect($owner, $member);

        $group = app(ConversationService::class)->createGroup($owner, ['name' => 'Team'], [$member->id]);

        $this->assertSame(ConversationMemberRole::Owner, $group->members()->where('user_id', $owner->id)->first()->role);
        $this->assertSame(ConversationMemberRole::Member, $group->members()->where('user_id', $member->id)->first()->role);
    }
}
