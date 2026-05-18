<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Inertia controller for editing per-campus notification email templates.
 *
 * Authorization: NotificationTemplatePolicy (B3) — super_admin only.
 * No delete or create — edit-only per CONTEXT.md D4.
 */
class NotificationTemplateController extends Controller
{
    /**
     * List all notification email templates (all campuses, all type_keys).
     */
    public function index(): Response
    {
        $this->authorize('viewAny', NotificationEmailTemplate::class);

        $templates = NotificationEmailTemplate::with(['campus', 'updatedBy'])
            ->orderBy('campus_id')
            ->orderBy('type_key')
            ->paginate(20);

        return Inertia::render('Admin/NotificationTemplate/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the editor for a single notification email template.
     */
    public function edit(NotificationEmailTemplate $template): Response
    {
        $this->authorize('view', $template);

        return Inertia::render('Admin/NotificationTemplate/Edit', [
            'template' => $template->load('campus'),
            'variables' => $template->type_key->availableVariables(),
        ]);
    }

    /**
     * Persist edits to subject + body_html and return to the edit page with a
     * flash message. Uses the same FormRequest as the JSON API endpoint so
     * authorization, variable allow-list, and Purifier wiring stay shared.
     *
     * Inertia v3 flash via Inertia::flash() per CLAUDE.md Inertia v3 rules —
     * NOT the legacy ->with('success', ...).
     */
    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationEmailTemplate $template,
    ): RedirectResponse {
        $template->update(
            $request->validated() + ['updated_by_user_id' => $request->user()->id],
        );

        Inertia::flash('success', 'Template saved successfully.');

        return back();
    }
}
