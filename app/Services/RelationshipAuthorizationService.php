<?php

namespace App\Services;

use App\Models\TrackingRelation;
use App\Models\User;

class RelationshipAuthorizationService
{
    public function canAdminViewUser(User $actor, User $target): bool
    {
        return $actor->hasRole('Super Admin');
    }

    public function canViewUser(User $actor, User $target): bool
    {
        return $actor->is($target)
            || $this->canAdminViewUser($actor, $target)
            || $this->hasActiveTrackingPermission($actor, $target);
    }

    public function canTrackUser(User $actor, User $target): bool
    {
        return $this->canAdminViewUser($actor, $target)
            || $this->hasActiveTrackingPermission($actor, $target);
    }

    public function canViewDiagnostics(User $actor, User $target): bool
    {
        return $actor->is($target)
            || $this->canAdminViewUser($actor, $target)
            || $this->hasActiveTrackingPermission($actor, $target);
    }

    public function canViewLicense(User $actor, User $target): bool
    {
        return $actor->is($target)
            || $this->canAdminViewUser($actor, $target)
            || $this->hasActiveTrackingPermission($actor, $target);
    }

    public function canCommunicate(User $actor, User $target): bool
    {
        return $actor->is($target)
            || $this->hasActiveRelationship($actor, $target);
    }

    public function canViewHistory(User $actor, User $target): bool
    {
        return $this->canViewDiagnostics($actor, $target);
    }

    private function hasActiveRelationship(User $actor, User $target): bool
    {
        return TrackingRelation::query()
            ->usableForTracking()
            ->where(function ($query) use ($actor, $target) {
                $query->where(function ($query) use ($actor, $target) {
                    $query->where('tracker_user_id', $actor->id)
                        ->where('tracked_user_id', $target->id);
                })->orWhere(function ($query) use ($actor, $target) {
                    $query->where('tracker_user_id', $target->id)
                        ->where('tracked_user_id', $actor->id);
                });
            })
            ->exists();
    }

    private function hasActiveTrackingPermission(User $actor, User $target): bool
    {
        return TrackingRelation::query()
            ->usableForTracking()
            ->where('tracker_user_id', $actor->id)
            ->where('tracked_user_id', $target->id)
            ->exists();
    }
}
