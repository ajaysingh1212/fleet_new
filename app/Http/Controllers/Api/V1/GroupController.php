<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddGroupMemberRequest;
use App\Http\Requests\Api\V1\CreateGroupRequest;
use App\Http\Requests\Api\V1\UpdateGroupRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
    ) {}

    public function store(CreateGroupRequest $request): ConversationResource
    {
        $conversation = $this->conversationService->createGroup(
            $request->user(),
            $request->only(['name', 'description']),
            $request->validated('member_ids'),
        );

        return new ConversationResource($conversation->load('latestMessage'));
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json([
            'data' => [
                'uuid' => $conversation->uuid,
                'name' => $conversation->name,
                'description' => $conversation->description,
                'avatar' => $conversation->avatar,
                'owner_id' => $conversation->owner_id,
                'members' => $conversation->members()->with('user')->get()->map(fn ($member) => [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'avatar' => $member->user->avatar,
                    'avatar_url' => $member->user->avatar ? asset('storage/'.$member->user->avatar) : null,
                    'role' => $member->role->value,
                    'joined_at' => $member->joined_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function update(UpdateGroupRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        if ($request->has('name')) {
            $this->conversationService->renameGroup($conversation, $request->user(), $request->validated('name'));
        }

        if ($request->has('description')) {
            $this->conversationService->updateDescription($conversation, $request->user(), $request->validated('description') ?? '');
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->conversationService->deleteGroup($conversation, $request->user());

        return response()->json(['deleted' => true]);
    }

    public function addMember(AddGroupMemberRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $newMember = User::findOrFail($request->validated('user_id'));

        $this->conversationService->addMember($conversation, $request->user(), $newMember);

        return response()->json(['success' => true]);
    }

    public function removeMember(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->conversationService->removeMember($conversation, $request->user(), $user);

        return response()->json(['success' => true]);
    }

    public function promote(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->conversationService->promoteToAdmin($conversation, $request->user(), $user);

        return response()->json(['success' => true]);
    }

    public function demote(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->conversationService->demoteToMember($conversation, $request->user(), $user);

        return response()->json(['success' => true]);
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->conversationService->leaveGroup($conversation, $request->user());

        return response()->json(['success' => true]);
    }
}
