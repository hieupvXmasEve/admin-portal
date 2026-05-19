<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Channels\EmailChannelAdapter;
use App\Modules\Notification\Channels\RealtimeChannelAdapter;
use App\Modules\Notification\Channels\RenderedEmailChannelAdapter;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Models\NotificationMessage;
use App\Services\EmailService;
use App\Services\PermissionService;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'notification.campus.strict_isolation' => false,
        // Disable auto-dispatch so DispatchSingleOutboxEventJob does not run;
        // tests run HandleOutboxEventAction synchronously.
        'notification.outbox.push_enabled' => false,
    ]);

    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);

    session([
        '_token' => 'outbox-emit-csrf',
        'current_campus_id' => $campus->id,
    ]);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn([]);
    $this->app->singleton(PermissionService::class, fn () => $permissionService);

    RateLimiter::clear('notification-template-test-send');
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a super_admin user with a campus_user_roles entry on the given campus.
 * The user must share the same campus as the template so RecipientResolver
 * resolves them when campusId is non-null.
 */
function makeOutboxEmitSuperAdmin(Campus $campus): User
{
    $user = User::factory()->create(['email' => 'outbox-admin@example.com']);
    $role = Role::where('code', 'super_admin')->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function makeOutboxEmitTemplate(Campus $campus): NotificationEmailTemplate
{
    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        [
            'subject' => 'Reminder for {{student_name}}',
            'body_html' => '<p>Balance: {{balance_formatted}}</p>',
        ],
    );
}

/**
 * Run SendNotificationDeliveryJob synchronously with an EmailService spy injected.
 * Uses the global app() helper so this works in Pest closure-based tests.
 * Returns the spy so callers can assert invocations.
 */
function runDeliveryJobWithSpy(int $deliveryId): EmailService
{
    // Create a real EmailLog row so the FK on notification_deliveries.email_log_id
    // is satisfied when SendNotificationDeliveryJob writes the result back.
    $emailLog = EmailLog::create([
        'recipient' => 'test@example.com',
        'subject' => '[TEST] stub',
        'status' => EmailLog::STATUS_SENT,
    ]);

    $spy = Mockery::spy(EmailService::class);
    $spy->shouldReceive('sendSingleEmail')
        ->andReturn($emailLog);

    // Bind the spy and clear cached adapter so constructor injection picks it up.
    app()->instance(EmailService::class, $spy);
    app()->forgetInstance(RenderedEmailChannelAdapter::class);

    app(SendNotificationDeliveryJob::class, ['deliveryId' => $deliveryId])->handle(
        app(EmailChannelAdapter::class),
        app(RenderedEmailChannelAdapter::class),
        app(RealtimeChannelAdapter::class),
    );

    return $spy;
}

// ---------------------------------------------------------------------------
// Main parity test — ≥2 row fixtures (Critical Pattern #7)
// ---------------------------------------------------------------------------

/**
 * Full pipeline for two distinct test-sends:
 *   endpoint → outbox row → HandleOutboxEventAction → delivery row →
 *   SendNotificationDeliveryJob → EmailService::sendSingleEmail called once per send.
 *
 * Critical Pattern #1: unsafe chars (<>&"', <script>) in both draft payloads.
 * Critical Pattern #7: endpoint called twice, producing two independent outbox rows.
 */
it('test-send emits outbox event and delivery pipeline carries pre-rendered draft content', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Queue::fake(); // prevent background job dispatch; we run handler manually

    // Shared campus ensures RecipientResolver resolves the admin user when
    // campusId is non-null (line 42 of RecipientResolver: campus_user_roles check).
    $sharedCampus = Campus::factory()->create();
    $user = makeOutboxEmitSuperAdmin($sharedCampus);
    $template = makeOutboxEmitTemplate($sharedCampus);

    // -------------------------------------------------------------------------
    // First test-send — unsafe chars in subject (Critical Pattern #1)
    // -------------------------------------------------------------------------
    $draftSubject1 = 'Reminder <>&"\' — First';
    $draftBody1 = '<p>Balance: {{balance_formatted}}</p><b>First send</b>';

    $response1 = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', 'outbox-emit-csrf')
        ->postJson(
            route('api.admin.notification-templates.test-send', ['template' => $template->id]),
            ['subject' => $draftSubject1, 'body_html' => $draftBody1],
        );

    $response1->assertOk();
    $response1->assertJsonPath('success', true);
    $response1->assertJsonPath('data.sent_to', $user->email);
    $response1->assertJsonPath('data.queued', true);

    // Outbox row must carry the new event_name and pre-rendered payload
    $outbox1 = NotificationEventOutbox::query()
        ->where('event_name', 'notification.test_send_requested')
        ->latest('id')
        ->first();

    expect($outbox1)->not->toBeNull()
        ->and($outbox1->payload['rendered_email']['rendered_subject'])->toStartWith('[TEST] ')
        ->and($outbox1->payload['rendered_email']['rendered_html'])->toContain('First send');

    // -------------------------------------------------------------------------
    // Second test-send — <script> in body (Critical Pattern #1 + #7)
    // -------------------------------------------------------------------------
    $draftSubject2 = '<script>alert(1)</script> Second reminder';
    $draftBody2 = '<p>Second send body with &amp; entities</p>';

    $response2 = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', 'outbox-emit-csrf')
        ->postJson(
            route('api.admin.notification-templates.test-send', ['template' => $template->id]),
            ['subject' => $draftSubject2, 'body_html' => $draftBody2],
        );

    $response2->assertOk();
    $response2->assertJsonPath('data.queued', true);

    $outbox2 = NotificationEventOutbox::query()
        ->where('event_name', 'notification.test_send_requested')
        ->latest('id')
        ->first();

    // Two distinct outbox rows were created (Critical Pattern #7)
    expect($outbox2->id)->not->toBe($outbox1->id);
    expect($outbox2->payload['rendered_email']['rendered_subject'])->toStartWith('[TEST] ');

    // -------------------------------------------------------------------------
    // Run handler synchronously for first send — verify message + delivery rows
    // -------------------------------------------------------------------------
    $outbox1->forceFill(['status' => NotificationOutboxStatus::Pending])->save();
    app(HandleOutboxEventAction::class)->run($outbox1->fresh());

    $message1 = NotificationMessage::query()
        ->where('event_id', $outbox1->event_id)
        ->first();

    expect($message1)->not->toBeNull();

    $delivery1 = NotificationDelivery::query()
        ->where('message_id', $message1->id)
        ->where('channel', 'email')
        ->first();

    expect($delivery1)->not->toBeNull()
        ->and($delivery1->rendered_subject)->toStartWith('[TEST] ')
        ->and($delivery1->rendered_html)->toContain('First send');

    // -------------------------------------------------------------------------
    // Run handler synchronously for second send — verify independent delivery row
    // -------------------------------------------------------------------------
    $outbox2->forceFill(['status' => NotificationOutboxStatus::Pending])->save();
    app(HandleOutboxEventAction::class)->run($outbox2->fresh());

    $message2 = NotificationMessage::query()
        ->where('event_id', $outbox2->event_id)
        ->first();

    expect($message2)->not->toBeNull()
        ->and($message2->id)->not->toBe($message1->id);

    $delivery2 = NotificationDelivery::query()
        ->where('message_id', $message2->id)
        ->where('channel', 'email')
        ->first();

    expect($delivery2)->not->toBeNull()
        ->and($delivery2->rendered_subject)->toStartWith('[TEST] ');

    // -------------------------------------------------------------------------
    // Run SendNotificationDeliveryJob for first delivery — assert EmailService called once
    // -------------------------------------------------------------------------
    $spy1 = runDeliveryJobWithSpy($delivery1->id);
    $spy1->shouldHaveReceived('sendSingleEmail')->once();

    expect($delivery1->fresh()->status->value)->toBe('sent');

    // -------------------------------------------------------------------------
    // Run SendNotificationDeliveryJob for second delivery — assert EmailService called once
    // -------------------------------------------------------------------------
    $spy2 = runDeliveryJobWithSpy($delivery2->id);
    $spy2->shouldHaveReceived('sendSingleEmail')->once();

    expect($delivery2->fresh()->status->value)->toBe('sent');
});

// ---------------------------------------------------------------------------
// Authorisation guard
// ---------------------------------------------------------------------------

it('non-super-admin receives 403 on test-send outbox path', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $template = makeOutboxEmitTemplate(Campus::factory()->create());

    $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', 'outbox-emit-csrf')
        ->postJson(
            route('api.admin.notification-templates.test-send', ['template' => $template->id]),
            ['subject' => 'Test', 'body_html' => '<p>Test</p>'],
        )
        ->assertForbidden();
});
