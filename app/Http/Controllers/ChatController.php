<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Services\ChatAuthorizationService;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected ChatAuthorizationService $chatAuthorization,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = ConversationResource::collection($this->conversationService->myConversations($user))
            ->resolve($request);

        $contacts = $this->chatAuthorization->communicableUsers($user)
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'avatar' => $contact->avatar,
                'avatar_url' => $contact->avatar ? asset('storage/'.$contact->avatar) : null,
                'department' => $contact->department,
                'designation' => $contact->designation,
                'company' => $contact->company,
            ])
            ->values();

        return view('chats.index', [
            'conversations' => $conversations,
            'contacts' => $contacts,
        ]);
    }
}
