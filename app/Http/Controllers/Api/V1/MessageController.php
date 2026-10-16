<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Requests\Api\V1\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    private const EAGER_LOAD = ['conversation', 'sender', 'replyTo.sender', 'forwardedFrom.sender', 'reactions', 'reads', 'attachments', 'stars'];

    public function __construct(
        protected MessageService $messageService,
    ) {}

    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $query = $conversation->messages()
            ->withTrashed()
            ->whereDoesntHave('userDeletes', fn ($q) => $q->where('user_id', $user->id))
            ->with(self::EAGER_LOAD)
            ->withExists(['stars as is_starred' => fn ($q) => $q->where('user_id', $user->id)]);

        if ($search = $request->string('q')->toString()) {
            $query->where('body', 'like', '%'.$search.'%');
        }

        if ($request->boolean('pinned_only')) {
            $query->where('is_pinned', true);
        }

        if ($request->boolean('starred_only')) {
            $query->whereHas('stars', fn ($q) => $q->where('user_id', $user->id));
        }

        $messages = $query->orderBy('created_at')->paginate(50);

        return MessageResource::collection($messages);
    }

    public function store(SendMessageRequest $request, Conversation $conversation): MessageResource
    {
        $this->authorize('create', [Message::class, $conversation]);

        $replyTo = $this->resolveReplyTo($conversation, $request->validated('reply_to_message_id'));
        $forwardFrom = $this->resolveForwardSource($request->user(), $request->validated('forwarded_from_message_id'));

        $message = $this->messageService->send(
            $conversation,
            $request->user(),
            $request->validated('body'),
            $replyTo,
            $forwardFrom,
            $request->validated('metadata') ?? [],
        );

        return new MessageResource($message->load(self::EAGER_LOAD));
    }

    public function update(UpdateMessageRequest $request, Message $message): MessageResource
    {
        $this->authorize('update', $message);

        $message = $this->messageService->edit($message, $request->validated('body'));

        return new MessageResource($message->load(self::EAGER_LOAD));
    }

    public function destroy(Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $this->messageService->deleteForEveryone($message);

        return response()->json(['deleted' => true]);
    }

    public function destroyForMe(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message->conversation);

        $this->messageService->deleteForMe($message, $request->user());

        return response()->json(['deleted' => true]);
    }

    public function pin(Message $message): MessageResource
    {
        $this->authorize('view', $message->conversation);

        return new MessageResource($this->messageService->pin($message)->load(self::EAGER_LOAD));
    }

    public function unpin(Message $message): MessageResource
    {
        $this->authorize('view', $message->conversation);

        return new MessageResource($this->messageService->unpin($message)->load(self::EAGER_LOAD));
    }

    public function star(Request $request, Message $message): MessageResource
    {
        $this->authorize('view', $message->conversation);

        return new MessageResource($this->messageService->star($message, $request->user())->load(self::EAGER_LOAD)->loadExists([
            'stars as is_starred' => fn ($q) => $q->where('user_id', $request->user()->id),
        ]));
    }

    public function unstar(Request $request, Message $message): MessageResource
    {
        $this->authorize('view', $message->conversation);

        return new MessageResource($this->messageService->unstar($message, $request->user())->load(self::EAGER_LOAD)->loadExists([
            'stars as is_starred' => fn ($q) => $q->where('user_id', $request->user()->id),
        ]));
    }

    public function cancelLiveLocation(Request $request, Message $message): MessageResource
    {
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_unless(($message->metadata['kind'] ?? null) === 'live_location', 422, 'This is not a live location message.');

        $metadata = $message->metadata ?? [];
        $metadata['cancelled_at'] = now()->toIso8601String();

        $message->update(['metadata' => $metadata]);
        broadcast(new \App\Events\MessageUpdated($message));

        return new MessageResource($message->load(self::EAGER_LOAD));
    }

    public function markDelivered(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->messageService->markDelivered($conversation, $request->user());

        return response()->json(['success' => true]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->messageService->markRead($conversation, $request->user(), $request->integer('up_to_message_id') ?: null);

        return response()->json(['success' => true]);
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

    private function resolveForwardSource(User $user, ?string $uuid): ?Message
    {
        if (! $uuid) {
            return null;
        }

        $message = Message::where('uuid', $uuid)
            ->whereHas('conversation.members', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $message) {
            throw ValidationException::withMessages([
                'forwarded_from_message_id' => 'You do not have access to that message.',
            ]);
        }

        return $message;
    }
}
