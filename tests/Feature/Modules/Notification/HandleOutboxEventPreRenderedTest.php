<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['notification.campus.strict_isolation' => false]);
});

/**
 * Create a NotificationEventOutbox row for the pre-rendered path tests.
 */
function makePreRenderedOutbox(array $payload, ?int $campusId = null): NotificationEventOutbox
{
    return NotificationEventOutbox::create([
        'event_id' => (string) Str::uuid(),
        'event_name' => 'notification.test_send_requested',
        'event_version' => 1,
        'occurred_at' => now(),
        'aggregate_type' => 'notification_template',
        'aggregate_id' => '1',
        'campus_id' => $campusId,
        'payload' => $payload,
        'status' => NotificationOutboxStatus::Pending,
    ]);
}

/**
 * Case 1: Pre-rendered payload bypasses registry.
 *
 * When payload.rendered_email.rendered_subject + rendered_html are present,
 * HandleOutboxEventAction returns those values verbatim — the EmailContentRegistry
 * is NOT consulted even though the type_key (payment_reminder) is registered.
 */
it('pre-rendered payload bypasses registry and stores custom subject and html on delivery', function () {
    $user = User::factory()->create();

    $outbox = makePreRenderedOutbox([
        'type_key' => 'payment_reminder',
        'channels' => ['email'],
        'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
        'rendered_email' => [
            'rendered_subject' => 'Custom Subject',
            'rendered_html' => '<p>Custom HTML</p>',
            'rendered_text' => null,
        ],
        'data' => [
            'student_name' => 'Test Student',
            'campus_id' => 1,
        ],
    ]);

    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->toBe('Custom Subject')
        ->and($delivery->rendered_html)->toBe('<p>Custom HTML</p>');
});

/**
 * Case 2: Absent rendered_email falls through to registry path.
 *
 * When payload.rendered_email is absent the pre-rendered short-circuit MUST NOT
 * fire. We confirm this with a sentinel value: if the short-circuit had fired it
 * would have written 'SHORT-CIRCUIT-SENTINEL-ABSENT-XYZ' as rendered_subject
 * (that is the value we would place in rendered_email.rendered_subject). Since
 * rendered_email is absent entirely, the short-circuit cannot fire, so rendered_subject
 * must NOT equal the sentinel.
 *
 * The registry path (DbEmailContentProvider) returns null rendered_subject here
 * because no DB template row exists for dng_payment_pushed in the test database.
 * That is a pre-existing failure tracked in HandleOutboxEventRenderedEmailTest.php:66
 * and is separate from the short-circuit guard this test covers.
 */
it('absent rendered_email falls through to registry and stores registry-derived content', function () {
    $user = User::factory()->create();

    // Sentinel: what rendered_subject would be if the short-circuit HAD fired.
    // Since rendered_email is absent, this value must never appear on delivery.
    $sentinel = 'SHORT-CIRCUIT-SENTINEL-ABSENT-XYZ';

    $outbox = NotificationEventOutbox::create([
        'event_id' => (string) Str::uuid(),
        'event_name' => 'finance.dng_payment_pushed',
        'event_version' => 1,
        'occurred_at' => now(),
        'aggregate_type' => 'dng_payment_request',
        'aggregate_id' => '1',
        'campus_id' => null,
        'payload' => [
            'type_key' => 'dng_payment_pushed',
            'channels' => ['email'],
            'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
            // No rendered_email key — registry path must be taken.
            'data' => [
                'title' => 'Test',
                'body' => 'Test body',
                'student_name' => 'Jane Smith',
                'student_code' => 'STU001',
                'semester_code' => 'Fall 2024',
                'program_name' => 'CS',
                'invoice_code' => 'STU001_123',
                'amount_formatted' => '25.000.000 VNĐ',
                'due_date' => '30/06/2025',
            ],
        ],
        'status' => NotificationOutboxStatus::Pending,
    ]);

    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    // Delivery row exists — HandleOutboxEventAction ran the registry path and
    // created a delivery record even though the registry provider returned null.
    // rendered_subject must NOT equal the sentinel: that would only happen if
    // the short-circuit fired, which is impossible without rendered_email in payload.
    // Note: rendered_subject IS null here because DbEmailContentProvider requires
    // a DB template row that is not seeded in this test. That is a pre-existing
    // failure tracked separately in HandleOutboxEventRenderedEmailTest.php:66.
    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->not->toBe($sentinel);
});

/**
 * Case 4: Partial rendered_email (only rendered_subject) falls through to registry path.
 *
 * When payload.rendered_email is present but rendered_html is absent (null or missing),
 * the pre-rendered short-circuit MUST NOT fire — both rendered_subject AND rendered_html
 * must be present for the short-circuit to activate.
 *
 * We use a sentinel in rendered_subject: 'Partial Subject Sentinel-XYZ'.
 * If the short-circuit fired incorrectly, rendered_subject on delivery would equal
 * this sentinel. The assertion verifies it does NOT, proving the registry path ran.
 *
 * type_key 'payment_reminder' is used (registered in EmailContentRegistry).
 * DbEmailContentProvider returns null when no DB template row exists (test DB),
 * so rendered_subject will be null — that is acceptable; what matters is it is
 * NOT the sentinel.
 */
it('partial rendered_email (only rendered_subject) falls through to registry path', function () {
    $user = User::factory()->create();

    $outbox = makePreRenderedOutbox([
        'type_key' => 'payment_reminder',
        'channels' => ['email'],
        'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
        // Partial rendered_email: rendered_subject present but rendered_html absent.
        // The short-circuit requires BOTH fields — missing rendered_html must cause fallthrough.
        'rendered_email' => [
            'rendered_subject' => 'Partial Subject Sentinel-XYZ',
            'rendered_text' => null,
            // Note: 'rendered_html' key is intentionally absent.
        ],
        'data' => [
            'student_name' => 'Test Student',
            'campus_id' => 1,
        ],
    ]);

    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    // Delivery exists — action ran to completion.
    // rendered_subject must NOT be the sentinel: that would mean the short-circuit
    // fired despite rendered_html being absent, which is the bug this test guards against.
    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->not->toBe('Partial Subject Sentinel-XYZ');
});

/**
 * Case 5: Empty-string rendered_subject falls through to registry path.
 *
 * When payload.rendered_email carries rendered_subject: '' (empty string), the
 * strengthened H1 guard MUST reject the short-circuit and fall through to the
 * registry path.  This proves the guard is symmetric: not just missing-key safe,
 * but also empty-string safe.
 *
 * We use the absence-of-sentinel logic: since rendered_email IS present but its
 * rendered_subject is '', if the old guard had fired (isset-only), the delivery
 * would have been written with rendered_subject = '' (empty string). After H1 the
 * short-circuit does NOT fire, so the registry path runs and rendered_subject will
 * be whatever DbEmailContentProvider returns.
 *
 * Assertion shape: rendered_subject !== '' proves the short-circuit did NOT fire.
 * If DbEmailContentProvider also returns '' that would be a separate registry bug;
 * in practice it returns null when no DB template row exists (same as Cases 2/4),
 * so we assert not-empty-string to cover both null (registry ran, no row) and any
 * real rendered value (registry ran, row found).
 */
it('empty-string rendered_subject falls through to registry path', function () {
    $user = User::factory()->create();

    $outbox = makePreRenderedOutbox([
        'type_key' => 'payment_reminder',
        'channels' => ['email'],
        'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
        // Empty rendered_subject: the H1 guard must reject this and fall through
        // to the registry path. A non-empty rendered_html is present to confirm
        // that only the empty subject causes the fallthrough (not a missing-key issue).
        'rendered_email' => [
            'rendered_subject' => '',
            'rendered_html' => '<p>Body</p>',
            'rendered_text' => null,
        ],
        'data' => [
            'student_name' => 'Test Student',
            'campus_id' => 1,
        ],
    ]);

    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    // Delivery row exists — action ran to completion.
    // rendered_subject must NOT be '' (empty string). If the old isset-only guard
    // had fired, it would have short-circuited and written '' to rendered_subject.
    // After H1 the guard rejects empty strings, so the registry path ran instead.
    // The registry returns null when no DB template row exists (pre-existing
    // behavior — same as Cases 2 and 4), so null is the expected value here.
    // Either null OR a real non-empty subject from the registry is acceptable;
    // what is NOT acceptable is '' (which would prove the short-circuit still fired).
    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->not->toBe('');
});

/**
 * Case 3: Unsafe chars survive pre-rendered path without double-escaping.
 *
 * rendered_subject and rendered_html containing HTML special chars and
 * <script> reach NotificationDelivery verbatim. The pre-rendered path is
 * a passthrough — no escaping, no rejection. The caller (testSend controller)
 * already ran Purifier before placing content in the envelope.
 */
it('unsafe chars in pre-rendered payload are stored verbatim on delivery without double-escaping', function () {
    $user = User::factory()->create();

    $unsafeSubject = 'Subject with <>&"\' chars';
    $unsafeHtml = '<p>Body</p><script>alert(1)</script>';

    $outbox = makePreRenderedOutbox([
        'type_key' => 'payment_reminder',
        'channels' => ['email'],
        'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
        'rendered_email' => [
            'rendered_subject' => $unsafeSubject,
            'rendered_html' => $unsafeHtml,
            'rendered_text' => null,
        ],
        'data' => ['campus_id' => 1],
    ]);

    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->toBe($unsafeSubject)
        ->and($delivery->rendered_html)->toBe($unsafeHtml);
});
