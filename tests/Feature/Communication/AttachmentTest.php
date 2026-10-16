<?php

namespace Tests\Feature\Communication;

use App\Events\MessageSent;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentTest extends TestCase
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

    public function test_uploading_a_valid_image_creates_a_message_and_attachment_and_broadcasts(): void
    {
        Storage::fake('local');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Event::fake([MessageSent::class]);
        Sanctum::actingAs($a);

        $response = $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg', 400, 300),
            'caption' => 'Look at this',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'image');
        $response->assertJsonPath('data.body', 'Look at this');
        $response->assertJsonCount(1, 'data.attachments');

        $this->assertDatabaseHas('message_attachments', [
            'original_name' => 'photo.jpg',
            'type' => 'image',
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('malware.exe', 10),
        ])->assertUnprocessable();
    }

    public function test_audio_webm_recordings_are_classified_as_audio(): void
    {
        Storage::fake('local');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);

        $response = $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('voice-note.webm', 64, 'audio/webm'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'audio');
        $response->assertJsonPath('data.attachments.0.type', 'audio');

        $this->assertDatabaseHas('message_attachments', [
            'original_name' => 'voice-note.webm',
            'type' => 'audio',
        ]);
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('local');
        config(['chat.max_upload_kb' => 10]);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);

        $this->postJson("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('document.pdf', 50),
        ])->assertUnprocessable();
    }

    public function test_a_non_member_cannot_upload_or_read_attachments(): void
    {
        Storage::fake('local');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $stranger = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $upload = $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated();

        $attachmentUuid = $upload->json('data.attachments.0.uuid');

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/conversations/{$conversation->uuid}/attachments")->assertForbidden();
        $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->image('photo2.jpg'),
        ])->assertForbidden();
        $this->get("/api/v1/attachments/{$attachmentUuid}")->assertForbidden();
        $this->get("/api/v1/attachments/{$attachmentUuid}/thumbnail")->assertForbidden();
    }

    public function test_a_member_can_stream_the_original_and_thumbnail(): void
    {
        Storage::fake('local');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $upload = $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg', 400, 300),
        ])->assertCreated();

        $attachmentUuid = $upload->json('data.attachments.0.uuid');

        Sanctum::actingAs($b);
        $this->get("/api/v1/attachments/{$attachmentUuid}")->assertOk();
        $this->get("/api/v1/attachments/{$attachmentUuid}/thumbnail")->assertOk();
    }

    public function test_shared_media_index_filters_by_type(): void
    {
        Storage::fake('local');

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->connect($a, $b);
        $conversation = app(ConversationService::class)->createPrivateConversation($a, $b);

        Sanctum::actingAs($a);
        $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated();
        $this->post("/api/v1/conversations/{$conversation->uuid}/attachments", [
            'file' => UploadedFile::fake()->create('document.pdf', 50),
        ])->assertCreated();

        $imagesOnly = $this->getJson("/api/v1/conversations/{$conversation->uuid}/attachments?type=image");
        $imagesOnly->assertOk();
        $imagesOnly->assertJsonCount(1, 'data');
        $imagesOnly->assertJsonPath('data.0.type', 'image');

        $all = $this->getJson("/api/v1/conversations/{$conversation->uuid}/attachments");
        $all->assertJsonCount(2, 'data');
    }
}
