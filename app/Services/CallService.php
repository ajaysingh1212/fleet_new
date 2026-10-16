<?php

namespace App\Services;

use App\Enums\CallParticipantStatus;
use App\Enums\CallStatus;
use App\Enums\CallType;
use App\Enums\ConversationType;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallParticipantLeft;
use App\Events\CallParticipantUpdated;
use App\Events\CallRejected;
use App\Events\IncomingCall;
use App\Models\CallParticipant;
use App\Models\CallSession;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\MissedCallNotification;
use Illuminate\Validation\ValidationException;

class CallService
{
    /**
     * A client that never explicitly hangs up (closed tab, lost connection,
     * crashed) leaves its call stuck in "ringing" forever. Without this, that
     * phantom call would make the busy check below report the callee (or
     * even the original caller, next time they receive a call) as
     * permanently busy even though nobody is actually on a call anymore.
     */
    private const RING_TIMEOUT_SECONDS = 45;

    /**
     * A client that accepted a call and then had its tab closed/crashed
     * without ever hitting "leave" (the frontend's pagehide handler is
     * best-effort — it can't fire for every crash/force-quit scenario)
     * leaves the session stuck "ongoing" forever, which permanently marks
     * both participants as busy for every future call between anyone.
     * Genuine calls in this app are short; anything still "ongoing" after
     * hours is abandoned, not a real long conversation.
     */
    private const STALE_ONGOING_HOURS = 4;

    public function initiate(Conversation $conversation, User $initiator, CallType $type): CallSession
    {
        if ($conversation->type === ConversationType::Group) {
            return $this->initiateGroupCall($conversation, $initiator, $type);
        }

        $callee = $conversation->otherParticipant($initiator);

        if (! $callee) {
            throw ValidationException::withMessages([
                'conversation' => 'This conversation has no other participant to call.',
            ]);
        }

        $this->reapStaleRingingCalls([$initiator->id, $callee->id]);
        $this->reapAbandonedOngoingCalls([$initiator->id, $callee->id]);

        $calleeOnAnotherCall = CallParticipant::where('user_id', $callee->id)
            ->where('status', CallParticipantStatus::Joined)
            ->whereHas('callSession', fn ($query) => $query->where('status', CallStatus::Ongoing))
            ->exists();

        $calleeAlreadyRinging = CallParticipant::where('user_id', $callee->id)
            ->where('status', CallParticipantStatus::Invited)
            ->whereHas('callSession', fn ($query) => $query->where('status', CallStatus::Ringing))
            ->exists();

        // Already mid-conversation elsewhere: still ring (call waiting), so
        // the callee can hold their current call and pick this one up —
        // only a second *unanswered* incoming call is rejected outright,
        // since the UI can't reasonably stack more than one of those.
        $calleeBusy = $calleeAlreadyRinging && ! $calleeOnAnotherCall;
        $isCallWaiting = $calleeOnAnotherCall;

        $session = CallSession::create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'status' => $calleeBusy ? CallStatus::Busy : CallStatus::Ringing,
            'initiator_id' => $initiator->id,
            'ended_at' => $calleeBusy ? now() : null,
            'ended_reason' => $calleeBusy ? 'busy' : null,
        ]);

        $session->participants()->create([
            'user_id' => $initiator->id,
            'status' => CallParticipantStatus::Joined,
            'joined_at' => now(),
            'is_video_enabled' => $type === CallType::Video,
        ]);

        $session->participants()->create([
            'user_id' => $callee->id,
            'status' => $calleeBusy ? CallParticipantStatus::Declined : CallParticipantStatus::Invited,
            'is_video_enabled' => $type === CallType::Video,
        ]);

        if (! $calleeBusy) {
            broadcast(new IncomingCall($session, $callee->id, $isCallWaiting));
        }

        return $session;
    }

    /**
     * Group calls are a conversation-scoped "room" rather than a per-callee
     * ring: everyone gets invited, anyone can join while it's active, and it
     * only ends once the last joined participant leaves. Mesh WebRTC (every
     * participant connects directly to every other one) — not an SFU — so
     * this stays practical for small/medium groups, not massive ones.
     */
    private function initiateGroupCall(Conversation $conversation, User $initiator, CallType $type): CallSession
    {
        $this->reapStaleRingingCalls([$initiator->id]);
        $this->reapAbandonedOngoingCalls([$initiator->id]);

        $existingActive = $conversation->callSessions()
            ->whereIn('status', [CallStatus::Ringing, CallStatus::Ongoing])
            ->latest()
            ->first();

        if ($existingActive) {
            $this->accept($existingActive, $initiator);

            return $existingActive->refresh();
        }

        $session = CallSession::create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'status' => CallStatus::Ringing,
            'initiator_id' => $initiator->id,
        ]);

        $session->participants()->create([
            'user_id' => $initiator->id,
            'status' => CallParticipantStatus::Joined,
            'joined_at' => now(),
            'is_video_enabled' => $type === CallType::Video,
        ]);

        $otherMembers = $conversation->participants()->where('users.id', '!=', $initiator->id)->get();

        foreach ($otherMembers as $member) {
            $session->participants()->create([
                'user_id' => $member->id,
                'status' => CallParticipantStatus::Invited,
                'is_video_enabled' => $type === CallType::Video,
            ]);

            broadcast(new IncomingCall($session, $member->id));
        }

        return $session;
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function reapStaleRingingCalls(array $userIds): void
    {
        CallSession::query()
            ->where('status', CallStatus::Ringing)
            ->where('created_at', '<', now()->subSeconds(self::RING_TIMEOUT_SECONDS))
            ->whereHas('participants', fn ($query) => $query->whereIn('user_id', $userIds))
            ->get()
            ->each(function (CallSession $stale) {
                $stale->update([
                    'status' => CallStatus::Missed,
                    'ended_at' => now(),
                    'ended_reason' => 'missed',
                ]);

                $stale->participants()
                    ->whereIn('status', [CallParticipantStatus::Invited, CallParticipantStatus::Joined])
                    ->update(['status' => CallParticipantStatus::Missed]);

                broadcast(new CallEnded($stale, 'missed'));
            });
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function reapAbandonedOngoingCalls(array $userIds): void
    {
        CallSession::query()
            ->where('status', CallStatus::Ongoing)
            ->where('updated_at', '<', now()->subHours(self::STALE_ONGOING_HOURS))
            ->whereHas('participants', fn ($query) => $query->whereIn('user_id', $userIds))
            ->get()
            ->each(function (CallSession $stale) {
                $stale->update([
                    'status' => CallStatus::Ended,
                    'ended_at' => now(),
                    'ended_reason' => 'hangup',
                ]);

                $stale->participants()
                    ->where('status', CallParticipantStatus::Joined)
                    ->update(['status' => CallParticipantStatus::Left, 'left_at' => now()]);

                broadcast(new CallEnded($stale, 'hangup'));
            });
    }

    public function accept(CallSession $call, User $user): void
    {
        // Whoever a fresh joiner needs to connect to over WebRTC — captured
        // before this user is marked Joined, so it excludes themselves.
        $existingParticipants = $call->participants()
            ->with('user')
            ->where('status', CallParticipantStatus::Joined)
            ->where('user_id', '!=', $user->id)
            ->get()
            ->map(fn (CallParticipant $participant) => [
                'id' => $participant->user_id,
                'name' => $participant->user->name,
            ])
            ->values()
            ->all();

        $call->participants()->where('user_id', $user->id)->update([
            'status' => CallParticipantStatus::Joined,
            'joined_at' => now(),
        ]);

        if ($call->status === CallStatus::Ringing) {
            $call->update(['status' => CallStatus::Ongoing, 'started_at' => now()]);
        }

        broadcast(new CallAccepted($call, $user->id, $existingParticipants));
    }

    public function reject(CallSession $call, User $user): void
    {
        $call->loadMissing('conversation');

        $call->participants()->where('user_id', $user->id)->update([
            'status' => CallParticipantStatus::Declined,
        ]);

        if ($call->conversation->type === ConversationType::Group) {
            // One person declining a group invite doesn't hang up on anyone
            // else — `.call.rejected` just tells the room that invitee said
            // no. The room only actually ends (a distinct `.call.ended`) once
            // nobody is left joined or still ringing.
            broadcast(new CallRejected($call, $user->id));

            $stillActive = $call->participants()
                ->whereIn('status', [CallParticipantStatus::Joined, CallParticipantStatus::Invited])
                ->exists();

            if ($stillActive) {
                return;
            }

            $call->update([
                'status' => CallStatus::Ended,
                'ended_at' => now(),
                'ended_reason' => 'declined',
            ]);

            broadcast(new CallEnded($call, 'declined'));

            return;
        }

        $call->update([
            'status' => CallStatus::Ended,
            'ended_at' => now(),
            'ended_reason' => 'declined',
        ]);

        broadcast(new CallRejected($call, $user->id));
    }

    /**
     * In a private call, either side leaving ends the whole thing. In a
     * group call it's a room: the call only ends once the last joined
     * participant leaves — everyone else just sees that one tile drop.
     */
    public function leave(CallSession $call, User $user): void
    {
        $call->loadMissing('conversation');

        $call->participants()->where('user_id', $user->id)->update([
            'status' => CallParticipantStatus::Left,
            'left_at' => now(),
        ]);

        if ($call->conversation->type === ConversationType::Group) {
            $anyoneStillJoined = $call->participants()->where('status', CallParticipantStatus::Joined)->exists();

            if ($anyoneStillJoined) {
                broadcast(new CallParticipantLeft($call, $user->id));

                return;
            }
        }

        $call->update([
            'status' => CallStatus::Ended,
            'ended_at' => now(),
            'ended_reason' => 'hangup',
        ]);

        broadcast(new CallEnded($call, 'hangup'));
    }

    /**
     * Caller-driven timeout path: whoever's still waiting gives up after
     * ~30s of no answer. In a private call that's the one callee; in a group
     * call it's every invitee who hasn't responded yet — the call only ends
     * outright if nobody joined at all.
     */
    public function markMissed(CallSession $call): void
    {
        $call->loadMissing('conversation');

        if ($call->conversation->type === ConversationType::Group) {
            if ($call->status !== CallStatus::Ringing) {
                return;
            }

            $stillInvited = $call->participants()->with('user')->where('status', CallParticipantStatus::Invited)->get();

            $call->update([
                'status' => CallStatus::Missed,
                'ended_at' => now(),
                'ended_reason' => 'missed',
            ]);

            $call->participants()
                ->where('status', CallParticipantStatus::Invited)
                ->update(['status' => CallParticipantStatus::Missed]);

            broadcast(new CallEnded($call, 'missed'));

            $stillInvited->each(fn (CallParticipant $participant) => $participant->user->notify(new MissedCallNotification($call)));

            return;
        }

        $callee = $call->participants()->with('user')->where('user_id', '!=', $call->initiator_id)->first();

        $call->update([
            'status' => CallStatus::Missed,
            'ended_at' => now(),
            'ended_reason' => 'missed',
        ]);

        $callee?->update(['status' => CallParticipantStatus::Missed]);

        broadcast(new CallEnded($call, 'missed'));

        if ($callee) {
            $callee->user->notify(new MissedCallNotification($call));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateParticipantState(CallSession $call, User $user, array $attributes): void
    {
        $participant = $call->participants()->where('user_id', $user->id)->firstOrFail();
        $participant->update(array_intersect_key($attributes, array_flip(['is_muted', 'is_video_enabled', 'is_on_hold'])));

        broadcast(new CallParticipantUpdated($participant));
    }
}
