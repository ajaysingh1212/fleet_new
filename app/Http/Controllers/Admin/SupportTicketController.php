<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupportTicketRespondRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupportTicket::class);

        $query = SupportTicket::with(['user', 'assignee'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        return view('admin.support-tickets.index', [
            'tickets' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'priority']),
        ]);
    }

    public function show(SupportTicket $supportTicket): View
    {
        $this->authorize('view', $supportTicket);

        return view('admin.support-tickets.show', [
            'ticket' => $supportTicket->load(['user', 'assignee']),
            'staff' => User::role(['Super Admin', 'Admin', 'Manager'])->orderBy('name')->get(),
        ]);
    }

    public function respond(SupportTicketRespondRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $this->authorize('respond', $supportTicket);

        $data = $request->validated();
        $data['resolved_at'] = $data['status'] === 'resolved' ? now() : $supportTicket->resolved_at;

        $supportTicket->update($data);

        $this->activityLogService->log(auth()->user(), 'support_ticket.responded', $supportTicket, ['status' => $data['status']]);

        return redirect()->route('admin.support-tickets.show', $supportTicket)->with('status', 'Ticket updated successfully.');
    }
}
