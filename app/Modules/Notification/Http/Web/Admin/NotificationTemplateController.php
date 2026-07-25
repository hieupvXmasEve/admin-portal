<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Inertia controller for editing per-campus notification email templates.
 *
 * Authorization: NotificationTemplatePolicy.
 * The canonical template catalog is edit-only: no create or delete.
 */
class NotificationTemplateController extends Controller
{
    /**
     * List notification email templates scoped to the current campus.
     *
     * Campus is resolved from the bound `campus` container singleton
     * (set by the campus-context middleware on every authenticated
     * web request). Templates from other campuses are not visible
     * here — admins working in a different campus must switch the
     * campus context to see/edit those templates.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', NotificationEmailTemplate::class);

        if (! app()->bound('campus') || app('campus')->id === null) {
            throw new \RuntimeException(
                'Campus context is required to list notification templates.'
            );
        }

        $campusId = (int) app('campus')->id;

        $templates = NotificationEmailTemplate::with(['campus', 'updatedBy'])
            ->where('campus_id', $campusId)
            ->orderBy('type_key')
            ->paginate(20);

        $existingKeys = NotificationEmailTemplate::query()
            ->where('campus_id', $campusId)
            ->pluck('type_key')
            ->map(fn ($key) => $key instanceof NotificationTemplateTypeKey ? $key->value : (string) $key)
            ->all();

        $missingTemplates = collect(NotificationTemplateTypeKey::cases())
            ->reject(fn (NotificationTemplateTypeKey $case) => in_array($case->value, $existingKeys, true))
            ->map(fn (NotificationTemplateTypeKey $case) => [
                'type_key' => $case->value,
                'variables' => $case->availableVariables(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/NotificationTemplate/Index', [
            'templates' => $templates,
            'missingTemplates' => $missingTemplates,
            'currentCampus' => app('campus')->only(['id', 'name', 'code']),
        ]);
    }

    /**
     * Create a blank notification email template for the current campus and
     * a chosen type_key, then redirect to the edit page. Used when admins
     * notice a missing template (e.g. installment_payment_reminder) and want
     * to author the content from scratch instead of running a seed/migration.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', NotificationEmailTemplate::class);

        if (! app()->bound('campus') || app('campus')->id === null) {
            throw new \RuntimeException(
                'Campus context is required to create a notification template.'
            );
        }

        $campusId = (int) app('campus')->id;

        $validated = $request->validate([
            'type_key' => [
                'required',
                'string',
                Rule::in(array_map(fn (NotificationTemplateTypeKey $c) => $c->value, NotificationTemplateTypeKey::cases())),
                Rule::unique('notification_email_templates', 'type_key')
                    ->where(fn ($query) => $query->where('campus_id', $campusId)),
            ],
        ]);

        $template = NotificationEmailTemplate::create([
            'campus_id' => $campusId,
            'type_key' => $validated['type_key'],
            'subject' => '',
            'body_html' => '',
            'updated_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('success', 'Template created. Fill in subject and body.');

        return redirect()->route('admin.notification-templates.edit', ['template' => $template->id]);
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
     * Inertia v3 flash uses Inertia::flash(), not the removed legacy
     * ->with('success', ...) pattern.
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
