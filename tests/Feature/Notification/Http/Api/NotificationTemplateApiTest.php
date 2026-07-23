<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Services\PermissionService;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * CSRF token used across PUT tests.
 * Placed in session and matched in X-CSRF-TOKEN header to satisfy VerifyCsrfToken.
 */
const API_TEST_CSRF = 'test-csrf-token';

beforeEach(function () {
    Cache::flush();

    // API admin routes use ['web', 'auth'] middleware. The web stack includes:
    //  - CheckCampusSelected (redirects to campus selection when no campus in session)
    //  - HandleInertiaRequests (calls PermissionService + app('campus'))
    // Seed the campus singleton and session so campus-redirect is bypassed.
    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);

    // Put a known CSRF token into the session so PUT requests can provide a
    // matching X-CSRF-TOKEN header. Also set current_campus_id to bypass
    // CheckCampusSelected redirect.
    session([
        '_token' => API_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);

});

/**
 * Create a super_admin user for API controller tests.
 *
 * Uses the same DB-level role assignment pattern as B3/B5 tests.
 */
function makeApiSuperAdmin(): User
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

    app(PermissionService::class)->clearUserPermissionsCache($user);

    return $user;
}

/**
 * Create a NotificationEmailTemplate for API controller tests.
 *
 * CampusObserver auto-provisions on Campus::create, so use updateOrCreate.
 */
function makeApiTemplate(): NotificationEmailTemplate
{
    $campus = app('campus');

    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Hello {{student_name}}', 'body_html' => '<p>Balance: {{balance_formatted}}</p>'],
    );
}

// (a) Super Admin PUT /api/v1/admin/notification-templates/{id} with valid payload
//     → 200; row updated; updated_by_user_id set; ApiResponse envelope.
it('super admin can update a notification template via PUT', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeApiSuperAdmin();
    $template = makeApiTemplate();

    $newSubject = 'Updated subject for {{student_name}}';
    $newBody = '<p>New body: {{balance_formatted}}</p>';

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', API_TEST_CSRF)
        ->putJson(
            route('api.admin.notification-templates.update', ['template' => $template->id]),
            ['subject' => $newSubject, 'body_html' => $newBody],
        );

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.template.subject', $newSubject);

    $template->refresh();
    expect($template->subject)->toBe($newSubject)
        ->and($template->updated_by_user_id)->toBe($user->id);
});

// (b) PUT with unknown {{var}} in body_html → 422 (B5 validator rejects it).
it('returns 422 when body_html contains an unknown template variable', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeApiSuperAdmin();
    $template = makeApiTemplate();

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', API_TEST_CSRF)
        ->putJson(
            route('api.admin.notification-templates.update', ['template' => $template->id]),
            ['subject' => 'Hello {{student_name}}', 'body_html' => '<p>{{unknown_var}}</p>'],
        );

    $response->assertUnprocessable();
});

// (c) Super Admin GET /api/v1/admin/notification-templates/variables/payment_reminder
//     → 200; payload matches enum's availableVariables() shape.
it('returns available variables for a valid type_key', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeApiSuperAdmin();
    $response = $this->actingAs($user)
        ->withSession(['current_campus_id' => app('campus')->id])
        ->getJson(route('api.admin.notification-templates.variables', ['type_key' => 'payment_reminder']));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.variables.student_name.label', 'Student name');
    $response->assertJsonPath('data.variables.balance_formatted.label', 'Outstanding balance (formatted)');
});
