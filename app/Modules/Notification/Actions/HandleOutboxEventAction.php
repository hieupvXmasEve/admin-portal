<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Domain\Contracts\NotificationIntent;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Policies\PolicyResolver;
use App\Modules\Notification\Support\EventIntentMapper;
use App\Modules\Notification\Support\NotificationAuditLogger;
use App\Modules\Notification\Support\NotificationMetrics;
use App\Modules\Notification\Support\RecipientResolver;
use App\Shared\Contracts\Finance\DngPaymentNotificationContextReader;
use Illuminate\Support\Facades\Log;

class HandleOutboxEventAction
{
    public function __construct(
        private readonly EventIntentMapper $intentMapper,
        private readonly PolicyResolver $policyResolver,
        private readonly RecipientResolver $recipientResolver,
        private readonly PersistIntentAction $persistIntentAction,
        private readonly NotificationAuditLogger $auditLogger,
        private readonly NotificationMetrics $metrics,
        private readonly EmailContentRegistry $emailContentRegistry,
        private readonly DngPaymentNotificationContextReader $dngPaymentNotificationContexts,
    ) {}

    public function run(NotificationEventOutbox $outbox): int
    {
        $envelope = DomainEventEnvelope::fromArray([
            'event_id' => $outbox->event_id,
            'event_name' => $outbox->event_name,
            'event_version' => $outbox->event_version,
            'occurred_at' => $outbox->occurred_at,
            'aggregate_type' => $outbox->aggregate_type,
            'aggregate_id' => $outbox->aggregate_id,
            'campus_id' => $outbox->campus_id,
            'actor_user_id' => $outbox->actor_user_id,
            'payload' => $outbox->payload,
        ]);

        $intents = $this->intentMapper->map($envelope);
        if ($intents === []) {
            $this->markDispatched($outbox);

            return 0;
        }

        $queuedDeliveries = 0;

        foreach ($intents as $intent) {
            $policyDecision = $this->policyResolver->decide($intent, $envelope->eventName, $envelope->campusId);
            $allowChannels = $policyDecision['allow_channels'];
            if ($allowChannels === []) {
                continue;
            }

            $resolved = $this->recipientResolver->resolve($intent->recipientTargets, $envelope->campusId);

            foreach ($resolved['unresolved'] as $unresolved) {
                $this->auditLogger->unresolvedRecipient([
                    'event_id' => $envelope->eventId,
                    'event_name' => $envelope->eventName,
                    'campus_id' => $envelope->campusId,
                    'target_type' => $unresolved['type'],
                    'target_id' => $unresolved['id'],
                    'reason' => $unresolved['reason'],
                ]);

                $this->metrics->increment('notification_recipient_unresolved_total', [
                    'event_name' => $envelope->eventName,
                    'campus_id' => $envelope->campusId,
                    'target_type' => $unresolved['type'],
                ]);
            }

            if ($resolved['resolved_recipients'] === []) {
                continue;
            }

            $renderedEmail = $this->buildRenderedEmail($intent, $envelope);

            $deliveries = $this->persistIntentAction->run(
                $envelope,
                $intent,
                $resolved['resolved_recipients'],
                $allowChannels,
                $renderedEmail,
            );

            foreach ($deliveries as $delivery) {
                SendNotificationDeliveryJob::dispatch($delivery->id);
                $queuedDeliveries++;
            }
        }

        $this->markDispatched($outbox);

        return $queuedDeliveries;
    }

    /**
     * Build rendered email content for a notification intent.
     *
     * Option α short-circuit: if the envelope's payload already carries a
     * pre-rendered email (rendered_subject + rendered_html), return those
     * values verbatim without consulting the EmailContentRegistry. This path
     * is taken when the test-send button emits a notification.test_send_requested
     * event that bundles the admin's draft content directly in the payload.
     *
     * Guard conditions for Option α: payload.rendered_email must be an array,
     * rendered_subject and rendered_html must both be non-empty strings. Empty
     * strings, null values, missing keys, and non-array payload entries all fall
     * through to the registry path. The guard is symmetric: it rejects any form
     * of "blank" subject or html rather than delivering a silent empty-subject email.
     *
     * Fallback (all existing callers — Finance reminders, DNG events, etc.):
     * when payload.rendered_email is absent or fails the guard, resolve via
     * registry as before.
     *
     * -------------------------------------------------------------------------
     * CALLER-SANITIZE CONTRACT
     * -------------------------------------------------------------------------
     * Callers MUST sanitize `rendered_html` before placing it in the envelope.
     * The handler is a passthrough, not a sanitizer. RenderedEmailChannelAdapter
     * will deliver the HTML verbatim to the mail driver.
     *
     * - HTML body: apply `\Mews\Purifier\Facades\Purifier::clean($html, 'email_body')`
     *   before constructing the outbox payload. Never place raw request input
     *   directly into payload.rendered_email.rendered_html.
     *
     * - Subject: reject CRLF sequences at the FormRequest / validator layer:
     *   `'subject' => ['string', 'not_regex:/[\r\n]/']`. The handler does NOT
     *   strip header-injection sequences — relying on driver-level rejection is
     *   brittle across mail driver swaps.
     *
     * For registry-resolved content (the else branch below),
     * DbEmailContentProvider::htmlBody() already escapes per-variable via
     * htmlspecialchars() — no additional Purifier call is needed on that path.
     *
     * Critical patterns: F3 CRLF SMTP guard, P1 pre-rendered envelope primitive.
     * -------------------------------------------------------------------------
     *
     * @return array{rendered_subject?: string, rendered_html?: string, rendered_text?: string|null}
     */
    private function buildRenderedEmail(NotificationIntent $intent, DomainEventEnvelope $envelope): array
    {
        $preRendered = $envelope->payload['rendered_email'] ?? null;

        if (is_array($preRendered)
            && isset($preRendered['rendered_subject'], $preRendered['rendered_html'])
            && is_string($preRendered['rendered_subject']) && $preRendered['rendered_subject'] !== ''
            && is_string($preRendered['rendered_html']) && $preRendered['rendered_html'] !== '') {
            return [
                'rendered_subject' => $preRendered['rendered_subject'],
                'rendered_html' => $preRendered['rendered_html'],
                'rendered_text' => $preRendered['rendered_text'] ?? null,
            ];
        }

        if (! $this->emailContentRegistry->has($intent->typeKey)) {
            return [];
        }

        try {
            $data = $this->buildEmailData($intent, $envelope);
            $provider = $this->emailContentRegistry->resolve($intent->typeKey);

            return [
                'rendered_subject' => $provider->subject($data),
                'rendered_html' => $provider->htmlBody($data),
                'rendered_text' => $provider->textBody($data),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to render email content, falling back to old path', [
                'type_key' => $intent->typeKey,
                'event_name' => $envelope->eventName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build the data array for email content rendering.
     * Performs DB queries here so EmailContentProvider stays query-free.
     *
     * @return array<string, mixed>
     */
    private function buildEmailData(NotificationIntent $intent, DomainEventEnvelope $envelope): array
    {
        $data = $intent->data;
        $data['campus_id'] = $envelope->campusId;

        $requestId = $data['dng_payment_request_id'] ?? null;

        if ($requestId !== null) {
            $context = $this->dngPaymentNotificationContexts->find((int) $requestId);

            if ($context !== null) {
                $data['student_name'] = $context->studentName !== '' ? $context->studentName : ($data['student_name'] ?? '');
                $data['student_code'] = $context->studentCode;
                $data['semester_code'] = $context->semesterCode;
                $data['program_name'] = $context->programName;
                $data['invoice_code'] = $context->invoiceCode;
                $data['amount_formatted'] = $context->amountFormatted;
                $data['due_date'] = $context->dueDate;
            }
        }

        return $data;
    }

    private function markDispatched(NotificationEventOutbox $outbox): void
    {
        $outbox->forceFill([
            'status' => NotificationOutboxStatus::Dispatched,
            'dispatched_at' => now(),
            'last_error' => null,
            'next_retry_at' => null,
        ])->save();
    }
}
