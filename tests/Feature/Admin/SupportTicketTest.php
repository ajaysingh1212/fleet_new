<?php

namespace Tests\Feature\Admin;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_plain_user_cannot_access_admin_support_queue(): void
    {
        $this->actingAsPlainUser();

        $this->get(route('admin.support-tickets.index'))->assertForbidden();
    }

    public function test_user_can_create_and_view_their_own_ticket(): void
    {
        $this->actingAsPlainUser();

        $storeResponse = $this->post(route('support.store'), [
            'subject' => 'Cannot log in',
            'message' => 'My account seems locked.',
            'priority' => 'high',
        ]);

        $storeResponse->assertSessionHasNoErrors();
        $ticket = SupportTicket::first();
        $this->assertNotNull($ticket);

        $this->get(route('support.show', $ticket))->assertOk();
    }

    public function test_user_cannot_view_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = SupportTicket::create([
            'uuid' => Str::uuid(),
            'user_id' => $owner->id,
            'ticket_number' => 'TCK-TEST01',
            'subject' => 'Private issue',
            'message' => 'Sensitive details',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAsPlainUser();

        $this->get(route('support.show', $ticket))->assertForbidden();
    }

    public function test_admin_can_respond_to_a_ticket(): void
    {
        $this->actingAsSuperAdmin();
        $owner = User::factory()->create();
        $ticket = SupportTicket::create([
            'uuid' => Str::uuid(),
            'user_id' => $owner->id,
            'ticket_number' => 'TCK-TEST02',
            'subject' => 'Billing question',
            'message' => 'Need clarification on invoice.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->put(route('admin.support-tickets.respond', $ticket), [
            'status' => 'resolved',
            'resolution' => 'Clarified via email.',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.support-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }
}
