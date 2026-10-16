<?php

namespace App\Services;

use App\Enums\LocationShareStopRequestStatus;
use App\Enums\UserStatus;
use App\Events\LocationShareStopRequested;
use App\Events\LocationShareStopRequestPrompt;
use App\Events\LocationShareStopResolved;
use App\Models\LocationShareStopRequest;
use App\Models\User;
use App\Notifications\LocationShareStopRequestNotification;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LocationShareLockService
{
    /**
     * A tracked user cannot stop sharing unilaterally — this opens a request
     * that every active tracker sees (as an Allow/Deny prompt, on whatever
     * page they're on) and only stops once one of them approves it.
     */
    public function requestStop(User $requester): LocationShareStopRequest
    {
        // Superseding any request already in flight avoids the requester's tab
        // ending up with two live prompts racing each other.
        LocationShareStopRequest::query()
            ->where('user_id', $requester->id)
            ->where('status', LocationShareStopRequestStatus::Pending)
            ->update(['status' => LocationShareStopRequestStatus::Denied, 'resolved_at' => now()]);

        $stopRequest = LocationShareStopRequest::create([
            'user_id' => $requester->id,
            'status' => LocationShareStopRequestStatus::Pending,
        ]);

        broadcast(new LocationShareStopRequested($stopRequest));

        $trackerIds = $requester->trackerRelations()->where('status', UserStatus::Active)->pluck('tracker_user_id');

        User::query()->whereIn('id', $trackerIds)->get()->each(function (User $tracker) use ($stopRequest, $requester) {
            // Persisted copy for the bell/history — fine to lag if a queue
            // worker isn't running. The prompt itself goes out separately,
            // straight over the wire, so it never depends on one.
            $tracker->notify(new LocationShareStopRequestNotification($stopRequest, $requester));

            broadcast(new LocationShareStopRequestPrompt($stopRequest, $tracker->id, $requester->name));
        });

        return $stopRequest;
    }

    public function respond(LocationShareStopRequest $stopRequest, User $tracker, bool $approve): LocationShareStopRequest
    {
        if ($stopRequest->status !== LocationShareStopRequestStatus::Pending) {
            throw new HttpException(409, 'This request was already resolved.');
        }

        $isActiveTracker = $tracker->trackedUsers()
            ->where('tracked_user_id', $stopRequest->user_id)
            ->where('status', UserStatus::Active)
            ->exists();

        abort_unless($isActiveTracker || $tracker->hasAnyRole(['Super Admin', 'Admin', 'Manager']), 403);

        $stopRequest->update([
            'status' => $approve ? LocationShareStopRequestStatus::Approved : LocationShareStopRequestStatus::Denied,
            'resolved_by' => $tracker->id,
            'resolved_at' => now(),
        ]);

        broadcast(new LocationShareStopResolved($stopRequest->fresh(), $tracker->name));

        return $stopRequest;
    }
}
