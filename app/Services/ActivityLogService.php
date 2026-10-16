<?php

namespace App\Services;

use App\Interfaces\Repositories\ActivityLogRepositoryInterface;
use App\Models\User;

class ActivityLogService
{
    public function __construct(
        protected ActivityLogRepositoryInterface $activityLogs,
    ) {}

    public function log(?User $user, string $event, ?object $subject = null, array $properties = []): void
    {
        $this->activityLogs->create([
            'user_id' => $user?->id,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject && method_exists($subject, 'getKey') ? $subject->getKey() : null,
            'properties' => $properties,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'device' => substr((string) request()?->userAgent(), 0, 255),
            'country' => null,
            'logged_at' => now(),
        ]);
    }
}
