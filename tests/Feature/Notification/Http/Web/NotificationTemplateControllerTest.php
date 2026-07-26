<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;

const WEB_TEST_CSRF = 'web-test-csrf-token';

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => WEB_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

/**
 * Create a super_admin user for HTTP controller tests.
 *
 * Uses the same DB-level role assignment pattern as PolicyTest and
 * UpdateNotificationTemplateRequestTest (B3/B5).
 */
function makeWebSuperAdmin(): User
{
    $user = User::factory()->create();
    $campus = app('campus');
    $role = Role::where('code', 'super_admin')->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    return $user;
}

/**
 * Create a NotificationEmailTemplate for web controller tests.
 *
 * CampusObserver auto-provisions on Campus::create, so use updateOrCreate.
 */
function makeWebTemplate(): NotificationEmailTemplate
{
    $campus = app('campus');

    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Hello {{student_name}}', 'body_html' => '<p>Balance: {{balance_formatted}}</p>'],
    );
}

// (a) Super Admin GET /admin/notification-templates → 200; Inertia component = Admin/NotificationTemplate/Index.
it('super admin can access the notification templates index page', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeWebSuperAdmin();

    $response = $this->actingAs($user)
        ->get(route('admin.notification-templates.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/NotificationTemplate/Index')
        ->has('templates')
    );
});

// (b) Super Admin GET /admin/notification-templates/{id}/edit → 200; Inertia component = Admin/NotificationTemplate/Edit;
//     props contain the variables for that type.
it('super admin can access the edit page and receives template variables', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeWebSuperAdmin();
    $template = makeWebTemplate();

    $response = $this->actingAs($user)
        ->get(route('admin.notification-templates.edit', ['template' => $template->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/NotificationTemplate/Edit')
        ->has('template')
        ->has('variables')
        ->where('variables.student_name.label', 'Student name')
    );
});

// (c) Non-super-admin user → 403 on both routes.
it('non-super-admin user is forbidden from accessing notification template routes', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $regularUser = User::factory()->create(); // no super_admin role
    $template = makeWebTemplate();

    $this->actingAs($regularUser)
        ->get(route('admin.notification-templates.index'))
        ->assertForbidden();

    $this->actingAs($regularUser)
        ->get(route('admin.notification-templates.edit', ['template' => $template->id]))
        ->assertForbidden();
});

// (d) Super Admin Inertia PUT /admin/notification-templates/{id} → 302 back +
//     row updated + Inertia::flash('success', ...) lands in session.
//     Regression guard for review finding F1: Edit.vue uses Inertia useForm.put()
//     which REQUIRES a valid Inertia response (not a raw JSON API response).
it('super admin can update a template via the Inertia web route and gets a flash message', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeWebSuperAdmin();
    $template = makeWebTemplate();

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', WEB_TEST_CSRF)
        ->from(route('admin.notification-templates.edit', ['template' => $template->id]))
        ->put(route('admin.notification-templates.update', ['template' => $template->id]), [
            'subject' => 'Updated subject for {{student_name}}',
            'body_html' => '<p>Updated body for {{student_name}}</p>',
        ]);

    // Inertia web PUT returns back() — should redirect to the edit page.
    $response->assertRedirect(route('admin.notification-templates.edit', ['template' => $template->id]));

    // Row was updated.
    $template->refresh();
    expect($template->subject)->toBe('Updated subject for {{student_name}}');
    expect($template->body_html)->toContain('Updated body for {{student_name}}');
    expect($template->updated_by_user_id)->toBe($user->id);

    // Inertia::flash('success', ...) stores the message under Inertia's
    // FLASH_DATA session key (not Laravel's plain session bag). The Vue side
    // reads it off page.props on the next page-data sync.
    $flashData = session(SessionKey::FLASH_DATA, []);
    expect($flashData)->toMatchArray(['success' => 'Template saved successfully.']);
});

// (e) Non-super-admin PUT → 403 (FormRequest authorize() denies).
it('non-super-admin user is forbidden from updating templates via web route', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $regularUser = User::factory()->create();
    $template = makeWebTemplate();

    $this->actingAs($regularUser)
        ->withHeader('X-CSRF-TOKEN', WEB_TEST_CSRF)
        ->put(route('admin.notification-templates.update', ['template' => $template->id]), [
            'subject' => 'attempt',
            'body_html' => '<p>attempt</p>',
        ])
        ->assertForbidden();
});
