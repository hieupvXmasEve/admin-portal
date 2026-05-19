<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Admin JSON API controller for notification email templates.
 *
 * Authorization: UpdateNotificationTemplateRequest::authorize() handles update;
 * variables() uses $this->authorize('viewAny') directly.
 * preview() and testSend() use $this->authorize('preview'/'testSend') (B3 policy).
 */
class NotificationTemplateController extends Controller
{
    /**
     * Update subject + body_html for a template.
     *
     * Authorization is checked inside UpdateNotificationTemplateRequest::authorize().
     * Purification of body_html runs inside FormRequest::passedValidation() (B2).
     */
    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationEmailTemplate $template,
    ): JsonResponse {
        $template->update(
            $request->validated() + ['updated_by_user_id' => $request->user()->id],
        );

        return ApiResponse::success(['template' => $template->fresh()]);
    }

    /**
     * Return the allowed variable list for a given type_key.
     *
     * @param  string  $type_key  The raw enum value string, e.g. "payment_reminder"
     */
    public function variables(string $type_key): JsonResponse
    {
        $this->authorize('viewAny', NotificationEmailTemplate::class);

        $case = NotificationTemplateTypeKey::tryFrom($type_key);

        if ($case === null) {
            return ApiResponse::error('Unknown template type key.', [], Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::success(['variables' => $case->availableVariables()]);
    }

    /**
     * Render a draft template with sample variable values and return the output.
     *
     * Accepts an unsaved draft (subject + body_html from the editor) and renders
     * it using the same HasTemplateRendering path as production. The transient
     * model is never persisted — it only carries the draft content for rendering.
     *
     * BR-C-batch resolution: NotificationEmailTemplate::render() works on an
     * in-memory model because it only reads $this->subject / $this->body_html
     * via the trait — no DB read required.
     */
    public function preview(Request $request, NotificationEmailTemplate $template): JsonResponse
    {
        $this->authorize('preview', $template);

        $validator = Validator::make($request->all(), [
            'subject' => ['nullable', 'string', 'max:500'],
            'body_html' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid draft data.', $validator->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $draftSubject = (string) $request->input('subject', $template->subject);
        $draftBody = (string) $request->input('body_html', $template->body_html);

        // Sanitize the draft body BEFORE render — preview path must mirror the
        // save path's Purifier wiring (B5 sanitizes in passedValidation). Without
        // this, a super-admin could POST raw <script>/<iframe> and have it
        // rendered into the preview iframe / leaked via active img tags. The
        // sandbox iframe blocks script exec but not network beacons, so the
        // sanitizer is the actual defense.
        $draftBody = Purifier::clean($draftBody, 'email_body');

        // Build {name => sample} map from enum's availableVariables()
        $sampleVariables = collect($template->type_key->availableVariables())
            ->map(fn (array $meta) => $meta['sample'])
            ->all();

        // Render using a transient (non-persisted) model — same code path as production
        $transient = new NotificationEmailTemplate([
            'campus_id' => $template->campus_id,
            'type_key' => $template->type_key->value,
            'subject' => $draftSubject,
            'body_html' => $draftBody,
        ]);

        $rendered = $transient->render($sampleVariables);

        return ApiResponse::success([
            'rendered_subject' => $rendered['subject'],
            'rendered_html' => $rendered['html'],
        ]);
    }

    /**
     * Render a draft template and send it to the authenticated admin's own email.
     *
     * Rate-limited to 5 per minute via the 'notification-template-test-send' limiter
     * registered in AppServiceProvider.
     */
    public function testSend(Request $request, NotificationEmailTemplate $template): JsonResponse
    {
        $this->authorize('testSend', $template);

        $validator = Validator::make($request->all(), [
            'subject' => ['nullable', 'string', 'max:500', 'not_regex:/[\r\n]/'],
            'body_html' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid draft data.', $validator->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $draftSubject = (string) $request->input('subject', $template->subject);
        $draftBody = (string) $request->input('body_html', $template->body_html);

        // Same sanitization parity as preview() above — draft body MUST run
        // through Purifier before going out as an email. The save path through
        // UpdateNotificationTemplateRequest::passedValidation() handles this for
        // persisted edits; this is the sibling endpoint that previously skipped it.
        $draftBody = Purifier::clean($draftBody, 'email_body');

        $sampleVariables = collect($template->type_key->availableVariables())
            ->map(fn (array $meta) => $meta['sample'])
            ->all();

        $transient = new NotificationEmailTemplate([
            'campus_id' => $template->campus_id,
            'type_key' => $template->type_key->value,
            'subject' => $draftSubject,
            'body_html' => $draftBody,
        ]);

        $rendered = $transient->render($sampleVariables);

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'notification.test_send_requested',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'notification_template',
            aggregateId: (string) $template->id,
            campusId: $template->campus_id,
            actorUserId: $request->user()->id,
            payload: [
                'type_key' => $template->type_key->value,
                'channels' => ['email'],
                'recipient_targets' => [['type' => 'user', 'id' => $request->user()->id]],
                'rendered_email' => [
                    'rendered_subject' => '[TEST] '.$rendered['subject'],
                    'rendered_html' => $rendered['html'],
                    'rendered_text' => null,
                ],
                'data' => $sampleVariables,
            ],
        );

        app(PublishDomainEventAction::class)->run($envelope);

        return ApiResponse::success(['sent_to' => $request->user()->email, 'queued' => true]);
    }
}
