<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReactRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\Request;

class MessageReactionController extends Controller
{
    private const EAGER_LOAD = ['conversation', 'sender', 'reactions', 'reads'];

    public function __construct(
        protected MessageService $messageService,
    ) {}

    public function store(ReactRequest $request, Message $message): MessageResource
    {
        $this->authorize('react', $message);

        $this->messageService->react($message, $request->user(), $request->validated('emoji'));

        return new MessageResource($message->fresh()->load(self::EAGER_LOAD));
    }

    public function destroy(Request $request, Message $message): MessageResource
    {
        $this->authorize('react', $message);

        $this->messageService->removeReaction($message, $request->user());

        return new MessageResource($message->fresh()->load(self::EAGER_LOAD));
    }
}
