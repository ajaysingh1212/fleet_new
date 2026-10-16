<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Models\UserLicense;
use App\Notifications\TrackingRequestNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingRelationService
{
    public function __construct(
        protected SettingsService $settings,
        protected ActivityLogService $activityLogService,
    ) {}

    public function create(array $attributes): TrackingRelation
    {
        [$relation, $tracker] = DB::transaction(function () use ($attributes): array {
            $tracker = User::findOrFail($attributes['tracker_user_id']);
            $trackedUserId = (int) $attributes['tracked_user_id'];
            $trashed = TrackingRelation::withTrashed()
                ->where('tracker_user_id', $tracker->id)
                ->where('tracked_user_id', $trackedUserId)
                ->lockForUpdate()
                ->first();

            $license = $trashed?->user_license_id
                ? UserLicense::query()->whereKey($trashed->user_license_id)->lockForUpdate()->first()
                : null;

            if ($license && $license->expiry_date && $license->expiry_date->isPast()) {
                $license = null;
            }

            if (! $license) {
                $license = UserLicense::query()
                    ->where('user_id', $tracker->id)
                    ->where('payment_status', PaymentStatus::Paid)
                    ->whereIn('status', [LicenseStatus::Pending, LicenseStatus::Active])
                    ->where(function ($query) use ($trackedUserId): void {
                        $query->whereNull('assigned_tracked_user_id')
                            ->orWhere('assigned_tracked_user_id', $trackedUserId);
                    })
                    ->where(function ($query): void {
                        $query->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
                    })
                    ->orderByRaw('assigned_tracked_user_id IS NULL')
                    ->latest('purchase_date')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $license) {
                throw ValidationException::withMessages([
                    'tracker_user_id' => 'No valid license is available for this tracked user. Purchase a license to continue.',
                ]);
            }

            if ($license->assigned_tracked_user_id && (int) $license->assigned_tracked_user_id !== $trackedUserId) {
                throw ValidationException::withMessages([
                    'tracker_user_id' => 'This license is already assigned to another tracked user.',
                ]);
            }

            $license->assigned_tracked_user_id = $trackedUserId;
            if (($attributes['status'] ?? UserStatus::Active->value) === UserStatus::Active->value
                && $license->status === LicenseStatus::Pending) {
                app(LicenseService::class)->activateForUse($license, 'tracking');
            }
            $license->save();

            $attributes['user_license_id'] = $license->id;
            if ($trashed?->trashed()) {
                $trashed->restore();
                $trashed->update($attributes);
                $relation = $trashed;
            } elseif ($trashed) {
                throw ValidationException::withMessages([
                    'tracked_user_id' => 'A tracking relation already exists for this user.',
                ]);
            } else {
                $relation = TrackingRelation::create($attributes);
            }

            return [$relation, $tracker];
        });

        $this->activityLogService->log(User::query()->find(Auth::id()), 'tracking_relation.created', $relation, [
            'tracker' => $tracker->name,
        ]);

        User::find($attributes['tracked_user_id'])?->notify(new TrackingRequestNotification($relation, $tracker));

        return $relation;
    }

    public function createManagedUser(User $tracker, array $attributes): TrackingRelation
    {
        return DB::transaction(function () use ($tracker, $attributes): TrackingRelation {
            $tracked = User::create([
                'employee_id' => $attributes['employee_id'] ?? $this->nextEmployeeId(),
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'password' => Hash::make($attributes['password']),
                'department' => $attributes['department'] ?? null,
                'designation' => $attributes['designation'] ?? null,
                'company' => $attributes['company'] ?? null,
                'gender' => $attributes['gender'] ?? null,
                'dob' => $attributes['dob'] ?? null,
                'address' => $attributes['address'] ?? null,
                'timezone' => $tracker->timezone,
                'language_id' => $tracker->language_id,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'created_by' => $tracker->id,
            ]);
            $tracked->assignRole('User');

            return $this->create([
                'tracker_user_id' => $tracker->id,
                'tracked_user_id' => $tracked->id,
                'relationship_name' => $attributes['relationship_name'],
                'status' => UserStatus::Active->value,
                'created_by' => $tracker->id,
            ]);
        });
    }

    public function requestExisting(User $tracker, User $tracked, string $relationshipName): TrackingRelation
    {
        return $this->create([
            'tracker_user_id' => $tracker->id,
            'tracked_user_id' => $tracked->id,
            'relationship_name' => $relationshipName,
            'status' => UserStatus::Pending->value,
            'created_by' => $tracker->id,
        ]);
    }

    public function accept(TrackingRelation $relation, User $trackedUser): TrackingRelation
    {
        if ((int) $relation->tracked_user_id !== (int) $trackedUser->id) {
            abort(403);
        }

        $relation = DB::transaction(function () use ($relation, $trackedUser): TrackingRelation {
            $locked = TrackingRelation::query()->whereKey($relation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== UserStatus::Pending) {
                throw ValidationException::withMessages([
                    'relation' => 'This tracking request is no longer pending.',
                ]);
            }

            $license = UserLicense::query()->whereKey($locked->user_license_id)->lockForUpdate()->first();
            if (! $license || (int) $license->assigned_tracked_user_id !== (int) $locked->tracked_user_id) {
                throw ValidationException::withMessages([
                    'relation' => 'The license reserved for this request is no longer available.',
                ]);
            }

            if ($license->status === LicenseStatus::Pending) {
                app(LicenseService::class)->activateForUse($license, 'tracking');
            }

            $locked->forceFill([
                'status' => UserStatus::Active,
                'updated_by' => $trackedUser->id,
            ])->save();

            return $locked;
        });

        $this->activityLogService->log($trackedUser, 'tracking_relation.accepted', $relation, [
            'tracker' => $relation->trackerUser?->name,
        ]);

        return $relation;
    }

    public function reject(TrackingRelation $relation, User $trackedUser): void
    {
        if ((int) $relation->tracked_user_id !== (int) $trackedUser->id) {
            abort(403);
        }

        DB::transaction(function () use ($relation, $trackedUser): void {
            if ($relation->status !== UserStatus::Pending) {
                throw ValidationException::withMessages([
                    'relation' => 'This tracking request is no longer pending.',
                ]);
            }

            if ($relation->userLicense) {
                $relation->userLicense->forceFill([
                    'assigned_tracked_user_id' => null,
                    'usage_type' => null,
                    'activation_date' => null,
                    'expiry_date' => null,
                    'status' => LicenseStatus::Pending,
                ])->save();
            }

            $relation->forceFill(['updated_by' => $trackedUser->id])->save();
            $relation->delete();
        });

        $this->activityLogService->log($trackedUser, 'tracking_relation.rejected', null, [
            'tracker' => $relation->trackerUser?->name,
        ]);
    }

    public function delete(TrackingRelation $relation): void
    {
        DB::transaction(function () use ($relation): void {
            if ($relation->status === UserStatus::Pending && $relation->userLicense) {
                $relation->userLicense->forceFill([
                    'assigned_tracked_user_id' => null,
                    'usage_type' => null,
                    'activation_date' => null,
                    'expiry_date' => null,
                    'status' => LicenseStatus::Pending,
                ])->save();
            }

            $relation->delete();
        });

        $this->activityLogService->log(User::query()->find(Auth::id()), 'tracking_relation.deleted', null, [
            'relationship_name' => $relation->relationship_name,
            'license_id' => $relation->user_license_id,
        ]);
    }

    private function nextEmployeeId(): string
    {
        do {
            $employeeId = 'USR-'.Str::upper(Str::random(8));
        } while (User::withTrashed()->where('employee_id', $employeeId)->exists());

        return $employeeId;
    }
}
