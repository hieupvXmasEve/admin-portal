<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Jobs\DispatchSingleOutboxEventJob;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Services\PermissionService;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

const TEST_SEND_CSRF = 'test-send-csrf';

beforeEach(function () {
    Cache::flush();

    // Replicate A-batch middleware setup
    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);

    session([
        '_token' => TEST_SEND_CSRF,
        'current_campus_id' => $campus->id,
    ]);

    // Clear rate limiter hits before each test so tests are isolated
    RateLimiter::clear('notification-template-test-send');
});

function makeTestSendSuperAdmin(): User
{
    $user = User::factory()->create(['email' => 'admin-test@example.com']);
    $campus = app('campus');
    $role = Role::where('code', 'super_admin')->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(PermissionService::class)->clearUserPermissionsCache($user);
    RateLimiter::clear('notification-template-test-send:'.$user->id);

    return $user;
}

function makeTestSendTemplate(): NotificationEmailTemplate
{
    $campus = app('campus');

    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Reminder for {{student_name}}', 'body_html' => '<p>Balance: {{balance_formatted}}</p>'],
    );
}

// (a) Super Admin POST /test-send → 200; job dispatched with [TEST] prefix in subject.
it('super admin can send a test email and a job is dispatched with TEST prefix', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Bus::fake();

    $user = makeTestSendSuperAdmin();
    $template = makeTestSendTemplate();

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', TEST_SEND_CSRF)
        ->postJson(
            route('api.admin.notification-templates.test-send', ['template' => $template->id]),
            ['subject' => 'Hello {{student_name}}', 'body_html' => '<p>Balance: {{balance_formatted}}</p>'],
        );

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.sent_to', $user->email);
    $response->assertJsonPath('data.queued', true);

    // Verify the outbox row was created and the job was dispatched via PublishDomainEventAction
    Bus::assertDispatched(DispatchSingleOutboxEventJob::class);

    $outbox = NotificationEventOutbox::query()
        ->where('event_name', 'notification.test_send_requested')
        ->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['rendered_email']['rendered_subject'])->toStartWith('[TEST] ')
        ->and($outbox->payload['type_key'])->toBe($template->type_key->value);
});

// (b) 6th send within 1 minute → 429 rate limit response.
it('returns 429 after 5 test-send requests within one minute', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Bus::fake();

    $user = makeTestSendSuperAdmin();
    $template = makeTestSendTemplate();

    $payload = ['subject' => 'Hello {{student_name}}', 'body_html' => '<p>Body</p>'];
    $routeParams = ['template' => $template->id];

    // 5 successful requests
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->withHeader('X-CSRF-TOKEN', TEST_SEND_CSRF)
            ->postJson(route('api.admin.notification-templates.test-send', $routeParams), $payload)
            ->assertOk();
    }

    // 6th request must be rate-limited
    $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', TEST_SEND_CSRF)
        ->postJson(route('api.admin.notification-templates.test-send', $routeParams), $payload)
        ->assertStatus(429);
});

// (c) Non-super-admin → 403.
it('non-super-admin receives 403 when attempting to send a test email', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create(); // no super_admin role
    $template = makeTestSendTemplate();

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', TEST_SEND_CSRF)
        ->postJson(
            route('api.admin.notification-templates.test-send', ['template' => $template->id]),
            ['subject' => 'Test', 'body_html' => '<p>Test</p>'],
        );

    $response->assertForbidden();
});
