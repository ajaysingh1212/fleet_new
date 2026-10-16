<?php

namespace App\Services;

use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Events\AttachmentUploaded;
use App\Events\MessageDeleted;
use App\Events\MessageDelivered;
use App\Events\MessageReactionUpdated;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageRead;
use App\Models\MessageStar;
use App\Models\MessageUserDelete;
use App\Models\User;
use App\Notifications\MessageMentionNotification;
use App\Notifications\MessageReactionNotification;
use App\Notifications\NewMessageNotification;

class MessageService
{
    public function send(Conversation $conversation, User $sender, string $body, ?Message $replyTo = null, ?Message $forwardFrom = null, array $metadata = []): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'type' => MessageType::Text,
            'body' => $body,
            'reply_to_message_id' => $replyTo?->id,
            'forwarded_from_message_id' => $forwardFrom?->id,
            'metadata' => $metadata ?: null,
        ]);

        broadcast(new MessageSent($message));
        $this->notifyOthers($conversation, $message);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $attachmentAttributes  Output of AttachmentService::store().
     */
    public function sendWithAttachment(Conversation $conversation, User $sender, array $attachmentAttributes, ?string $caption, ?Message $replyTo = null): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'type' => $attachmentAttributes['type'],
            'body' => $caption,
            'reply_to_message_id' => $replyTo?->id,
        ]);

        $attachment = $message->attachments()->create($attachmentAttributes);

        AttachmentUploaded::dispatch($attachment);

        broadcast(new MessageSent($message));
        $this->notifyOthers($conversation, $message);

        return $message;
    }

    /**
     * Group-membership/rename notices ("Alice added Bob") rendered inline in
     * the thread. Reuses MessageSent — the frontend just styles
     * type === 'system' as a centered pill instead of a bubble.
     */
    public function system(Conversation $conversation, User $actor, string $body): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $actor->id,
            'type' => MessageType::System,
            'body' => $body,
        ]);

        broadcast(new MessageSent($message));

        return $message;
    }

    public function edit(Message $message, string $body): Message
    {
        $message->update([
            'body' => $body,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        broadcast(new MessageUpdated($message));

        return $message;
    }

    public function deleteForEveryone(Message $message): void
    {
        $message->delete();

        broadcast(new MessageDeleted($message));
    }

    public function deleteForMe(Message $message, User $user): void
    {
        MessageUserDelete::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id],
            ['deleted_at' => now()],
        );
    }

    public function pin(Message $message): Message
    {
        $message->update(['is_pinned' => true]);
        broadcast(new MessageUpdated($message));

        return $message;
    }

    public function unpin(Message $message): Message
    {
        $message->update(['is_pinned' => false]);
        broadcast(new MessageUpdated($message));

        return $message;
    }

    public function star(Message $message, User $user): Message
    {
        MessageStar::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $user->id,
        ]);

        return $message->fresh();
    }

    public function unstar(Message $message, User $user): Message
    {
        MessageStar::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->delete();

        return $message->fresh();
    }

    /**
     * Toggle: reacting with the same emoji the user already used removes it.
     */
    public function react(Message $message, User $user, string $emoji): void
    {
        $existing = MessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->first();
        $added = false;

        if ($existing && $existing->emoji === $emoji) {
            $existing->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $user->id],
                ['emoji' => $emoji],
            );
            $added = true;
        }

        broadcast(new MessageReactionUpdated($message));

        if ($added && $message->sender_id !== $user->id) {
            $message->sender->notify(new MessageReactionNotification($message, $user, $emoji));
        }
    }

    public function removeReaction(Message $message, User $user): void
    {
        MessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->delete();

        broadcast(new MessageReactionUpdated($message));
    }

    /**
     * "Delivered" = this user's browser received the message broadcast at
     * all, even if the conversation isn't the open one — distinct from
     * markRead(), which only fires once the thread is actually viewed.
     */
    public function markDelivered(Conversation $conversation, User $user): void
    {
        $messages = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $user->id)->whereNotNull('delivered_at'))
            ->get(['id', 'uuid']);

        if ($messages->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($messages as $message) {
            $read = MessageRead::firstOrNew(['message_id' => $message->id, 'user_id' => $user->id]);
            $read->delivered_at = $now;
            $read->save();
        }

        broadcast(new MessageDelivered($conversation, $user->id, $messages->pluck('uuid')->all(), $now->toIso8601String()));
    }

    public function markRead(Conversation $conversation, User $user, ?int $upToMessageId = null): void
    {
        $query = $conversation->messages()->where('sender_id', '!=', $user->id);

        if ($upToMessageId) {
            $query->where('id', '<=', $upToMessageId);
        }

        $messages = $query->get(['id']);

        if ($messages->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($messages as $message) {
            $read = MessageRead::firstOrNew(['message_id' => $message->id, 'user_id' => $user->id]);
            $read->delivered_at ??= $now;
            $read->read_at = $now;
            $read->save();
        }

        $latestId = $upToMessageId ?? $messages->max('id');

        $member = $conversation->members()->where('user_id', $user->id)->first();

        if ($member && ($member->last_read_message_id === null || $latestId > $member->last_read_message_id)) {
            $member->update(['last_read_message_id' => $latestId]);
        }

        broadcast(new MessagesRead($conversation, $user->id, $upToMessageId, $now->toIso8601String()));

        // Keep the bell's unread count in sync with the chat's own read
        // receipts instead of tracking two independent "unread" states for
        // the same messages.
        $user->unreadNotifications()
            ->where('data->conversation_uuid', $conversation->uuid)
            ->get()
            ->each->markAsRead();
    }

    /**
     * @return array<int, int> Member user ids mentioned via "@FirstName".
     */
    private function extractMentionedMemberIds(Conversation $conversation, string $body): array
    {
        if (! preg_match_all('/@(\w+)/', $body, $matches)) {
            return [];
        }

        $mentionedNames = array_map('strtolower', $matches[1]);

        return $conversation->members()
            ->with('user')
            ->get()
            ->filter(fn ($member) => in_array(strtolower(explode(' ', $member->user->name)[0]), $mentionedNames, true))
            ->pluck('user_id')
            ->all();
    }

    private function notifyOthers(Conversation $conversation, Message $message): void
    {
        $mentionedIds = $conversation->type === ConversationType::Group && $message->body
            ? $this->extractMentionedMemberIds($conversation, $message->body)
            : [];

        $conversation->members()
            ->where('user_id', '!=', $message->sender_id)
            ->with('user')
            ->get()
            ->each(function ($member) use ($mentionedIds, $message) {
                if (in_array($member->user_id, $mentionedIds, true)) {
                    $member->user->notify(new MessageMentionNotification($message));
                } else {
                    $member->user->notify(new NewMessageNotification($message));
                }
            });
    }
}
