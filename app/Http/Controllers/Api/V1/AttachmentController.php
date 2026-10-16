<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadAttachmentRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageAttachmentView;
use App\Services\AttachmentService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentController extends Controller
{
    private const EAGER_LOAD = ['conversation', 'sender', 'replyTo.sender', 'attachments'];

    public function __construct(
        protected AttachmentService $attachmentService,
        protected MessageService $messageService,
    ) {}

    public function store(UploadAttachmentRequest $request, Conversation $conversation): MessageResource
    {
        $this->authorize('create', [Message::class, $conversation]);

        $replyTo = $this->resolveReplyTo($conversation, $request->validated('reply_to_message_id'));

        $attributes = $this->attachmentService->store($request->file('file'), $conversation->uuid);
        $attributes['view_once'] = $request->boolean('view_once');

        $message = $this->messageService->sendWithAttachment(
            $conversation,
            $request->user(),
            $attributes,
            $request->validated('caption'),
            $replyTo,
        );

        return new MessageResource($message->load(self::EAGER_LOAD));
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $query = MessageAttachment::query()
            ->with('message')
            ->whereHas('message', fn ($q) => $q->where('conversation_id', $conversation->id));

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        $attachments = $query->latest()->get()->map(fn (MessageAttachment $attachment) => $this->attachmentPayload($attachment));

        return response()->json(['data' => $attachments->values()]);
    }

    public function show(Request $request, MessageAttachment $attachment)
    {
        $attachment->loadMissing('message.conversation');

        $this->authorize('view', $attachment->message->conversation);

        if ($attachment->openedBy($request->user())) {
            abort(410, 'This view-once attachment has already been opened.');
        }

        if ($attachment->view_once && $attachment->message->sender_id !== $request->user()->id) {
            MessageAttachmentView::firstOrCreate(
                ['message_attachment_id' => $attachment->id, 'user_id' => $request->user()->id],
                ['opened_at' => now()],
            );
        }

        $disposition = $attachment->type === MessageType::Document ? 'attachment' : 'inline';

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
        ], $disposition);
    }

    public function thumbnail(Request $request, MessageAttachment $attachment)
    {
        $attachment->loadMissing('message.conversation');

        $this->authorize('view', $attachment->message->conversation);

        abort_unless($attachment->thumbnail_path, 404);
        abort_if($attachment->openedBy($request->user()), 410, 'This view-once attachment has already been opened.');

        return Storage::disk($attachment->disk)->response($attachment->thumbnail_path, null, [
            'Content-Type' => 'image/jpeg',
        ]);
    }

    private function attachmentPayload(MessageAttachment $attachment): array
    {
        $opened = $attachment->openedBy(request()->user());

        return [
            'uuid' => $attachment->uuid,
            'type' => $attachment->type->value,
            'original_name' => $attachment->original_name,
            'size' => $attachment->size,
            'view_once' => $attachment->view_once,
            'opened' => $opened,
            'url' => $opened ? null : route('api.v1.attachments.show', $attachment->uuid),
            'thumbnail_url' => $opened || ! $attachment->thumbnail_path ? null : route('api.v1.attachments.thumbnail', $attachment->uuid),
            'created_at' => $attachment->created_at->toIso8601String(),
        ];
    }

    private function resolveReplyTo(Conversation $conversation, ?string $uuid): ?Message
    {
        if (! $uuid) {
            return null;
        }

        $message = Message::where('uuid', $uuid)->where('conversation_id', $conversation->id)->first();

        if (! $message) {
            throw ValidationException::withMessages([
                'reply_to_message_id' => 'That message does not belong to this conversation.',
            ]);
        }

        return $message;
    }
}
