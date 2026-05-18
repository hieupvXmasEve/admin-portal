<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper: create a User with the super_admin role at any campus.
 *
 * hasSystemRole() checks `campus_user_roles` via campusRoles() relationship
 * (User.php:271). We attach the super_admin role at a real campus so FK
 * constraints are satisfied.
 */
function makeSuperAdminUser(): User
{
    $user = User::factory()->create();
    $campus = Campus::factory()->create();
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

/**
 * Helper: create a NotificationEmailTemplate for policy assertions.
 *
 * CampusObserver auto-provisions a row per campus on Campus::create, so we
 * use updateOrCreate to avoid unique-key conflicts.
 */
function makeNotificationEmailTemplate(): NotificationEmailTemplate
{
    $campus = Campus::factory()->create();

    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Test subject', 'body_html' => '<p>Test body</p>'],
    );
}

// (a) Super-admin user can perform all 5 policy actions.
it('allows all 5 policy abilities for a super_admin user', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeSuperAdminUser();
    $template = makeNotificationEmailTemplate();

    expect(Gate::forUser($user)->allows('viewAny', NotificationEmailTemplate::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $template))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $template))->toBeTrue()
        ->and(Gate::forUser($user)->allows('preview', $template))->toBeTrue()
        ->and(Gate::forUser($user)->allows('testSend', $template))->toBeTrue();
});

// (b) Regular user (no super_admin role) is denied all 5 policy actions.
it('denies all 5 policy abilities for a user without super_admin role', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $template = makeNotificationEmailTemplate();

    expect(Gate::forUser($user)->allows('viewAny', NotificationEmailTemplate::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', $template))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $template))->toBeFalse()
        ->and(Gate::forUser($user)->allows('preview', $template))->toBeFalse()
        ->and(Gate::forUser($user)->allows('testSend', $template))->toBeFalse();
});

// (c) Unauthenticated (null) user — no controller routes exist yet (A1/A2),
//     so we test the Gate directly. Gate::forUser(null) returns false for any
//     policy that requires a typed User parameter (before() hook returns null,
//     policy method never receives a non-User value).
it('denies all abilities when user is unauthenticated', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $template = makeNotificationEmailTemplate();

    expect(Gate::forUser(null)->allows('update', $template))->toBeFalse()
        ->and(Gate::forUser(null)->allows('view', $template))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewAny', NotificationEmailTemplate::class))->toBeFalse();
});
