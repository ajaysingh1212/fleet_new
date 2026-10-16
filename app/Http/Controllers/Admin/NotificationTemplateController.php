<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationTemplateRequest;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.notification-templates.index', [
            'templates' => NotificationTemplate::orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.notification-templates.create');
    }

    public function store(NotificationTemplateRequest $request): RedirectResponse
    {
        NotificationTemplate::create($request->validated());

        return redirect()->route('admin.notification-templates.index')->with('status', 'Notification template created successfully.');
    }

    public function edit(NotificationTemplate $notification_template): View
    {
        return view('admin.notification-templates.edit', ['template' => $notification_template]);
    }

    public function update(NotificationTemplateRequest $request, NotificationTemplate $notification_template): RedirectResponse
    {
        $notification_template->update($request->validated());

        return redirect()->route('admin.notification-templates.index')->with('status', 'Notification template updated successfully.');
    }

    public function destroy(NotificationTemplate $notification_template): RedirectResponse
    {
        $notification_template->delete();

        return redirect()->route('admin.notification-templates.index')->with('status', 'Notification template deleted successfully.');
    }
}
