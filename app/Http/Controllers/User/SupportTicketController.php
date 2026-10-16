<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicketStoreRequest;
use App\Models\SupportTicket;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(): View
    {
        return view('user.support.index', [
            'tickets' => auth()->user()->supportTickets()->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('user.support.create');
    }

    public function store(SupportTicketStoreRequest $request): RedirectResponse
    {
        $ticket = SupportTicket::create($request->validated() + [
            'user_id' => auth()->id(),
            'ticket_number' => 'TCK-'.strtoupper(Str::random(8)),
            'status' => 'open',
        ]);

        $this->activityLogService->log(auth()->user(), 'support_ticket.created', $ticket);

        return redirect()->route('support.show', $ticket)->with('status', 'Support ticket submitted successfully.');
    }

    public function show(SupportTicket $supportTicket): View
    {
        $this->authorize('view', $supportTicket);

        return view('user.support.show', ['ticket' => $supportTicket]);
    }
}
