<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\User;
use App\Services\ChatAuthorizationService;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected ChatAuthorizationService $chatAuthorization,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ConversationResource::collection($this->conversationService->myConversations($request->user()));
    }

    public function contacts(Request $request): JsonResponse
    {
        $contacts = $this->chatAuthorization->communicableUsers($request->user());

        return response()->json([
            'data' => $contacts->map(fn (User $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'avatar' => $contact->avatar,
            ])->values(),
        ]);
    }

    public function store(StartConversationRequest $request): ConversationResource
    {
        $other = User::findOrFail($request->validated('user_id'));

        $conversation = $this->conversationService->createPrivateConversation($request->user(), $other);

        return new ConversationResource($conversation->load('latestMessage'));
    }
}
