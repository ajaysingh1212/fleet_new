<?php

namespace App\Modules\Reports;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\RelationshipAuthorizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonitoringReportAccessService
{
    public function __construct(private readonly RelationshipAuthorizationService $authorization) {}

    public function visibleUsers(User $viewer): Collection
    {
        return $this->visibleUsersQuery($viewer)->orderBy('name')->get(['id', 'name']);
    }

    public function visibleUsersQuery(User $viewer): Builder
    {
        $query = User::query()
            ->where('id', '!=', $viewer->id);

        if ($this->authorization->canAdminViewUser($viewer, $viewer)) {
            return $query;
        }

        return $query->whereHas('trackerRelations', fn (Builder $relation) => $relation
            ->where('tracker_user_id', $viewer->id)
            ->usableForTracking());
    }

    public function filtersFor(Request $request): array
    {
        $filters = $request->all();
        $viewer = $request->user();
        // Everyone can always pull their own report, even though `visibleUsers()`
        // (the "choose a person" dropdown) intentionally excludes the viewer.
        $allowedIds = $this->visibleUsers($viewer)->pluck('id')->push($viewer->id)->all();

        if ($request->filled('user_id')) {
            abort_unless(in_array((int) $request->input('user_id'), $allowedIds, true), 403);
            $filters['user_id'] = (int) $request->input('user_id');

            return $filters;
        }

        $filters['allowed_user_ids'] = $allowedIds;

        return $filters;
    }
}
