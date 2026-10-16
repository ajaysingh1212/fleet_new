<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TrackingRelationRequest;
use App\Models\TrackingRelation;
use App\Models\User;
use App\Services\TrackingRelationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrackingRelationController extends Controller
{
    public function __construct(
        protected TrackingRelationService $trackingRelationService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', TrackingRelation::class);

        return view('admin.tracking-relations.index', [
            'relations' => TrackingRelation::with(['trackerUser', 'trackedUser'])->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TrackingRelation::class);

        return view('admin.tracking-relations.create', $this->formOptions());
    }

    public function store(TrackingRelationRequest $request): RedirectResponse
    {
        $this->authorize('create', TrackingRelation::class);

        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $this->trackingRelationService->create($data);

        return redirect()->route('admin.tracking-relations.index')->with('status', 'Tracking relation created successfully.');
    }

    public function edit(TrackingRelation $trackingRelation): View
    {
        $this->authorize('update', $trackingRelation);

        return view('admin.tracking-relations.edit', $this->formOptions() + ['relation' => $trackingRelation]);
    }

    public function update(TrackingRelationRequest $request, TrackingRelation $trackingRelation): RedirectResponse
    {
        $this->authorize('update', $trackingRelation);

        $trackingRelation->update($request->safe()->only(['relationship_name', 'status']) + ['updated_by' => auth()->id()]);

        return redirect()->route('admin.tracking-relations.index')->with('status', 'Tracking relation updated successfully.');
    }

    public function destroy(TrackingRelation $trackingRelation): RedirectResponse
    {
        $this->authorize('delete', $trackingRelation);

        $this->trackingRelationService->delete($trackingRelation);

        return redirect()->route('admin.tracking-relations.index')->with('status', 'Tracking relation removed successfully.');
    }

    protected function formOptions(): array
    {
        return [
            'users' => User::orderBy('name')->get(),
            'statuses' => UserStatus::cases(),
        ];
    }
}
