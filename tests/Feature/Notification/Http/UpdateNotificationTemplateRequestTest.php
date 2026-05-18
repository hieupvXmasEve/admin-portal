<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Http\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Standalone FormRequest invocation without needing A2's routes.
 *
 * Pattern from BR-B5: bind route param manually so B5 can be tested
 * before A2 declares the real {template} route.
 *
 * The user resolver is set explicitly on the request instance. This is
 * the correct pattern for testing FormRequest::authorize() in isolation —
 * the resolver closure captures $user by reference so authorize() sees
 * the exact model instance with its DB-backed relationships intact.
 *
 * @param  array<string, mixed>  $payload
 */
function invokeRequest(array $payload, User $user, NotificationEmailTemplate $template): UpdateNotificationTemplateRequest
{
    $request = UpdateNotificationTemplateRequest::create('/dummy', 'PUT', $payload);

    // Force JSON expectation so ValidationException throws cleanly instead of
    // trying to build a redirect URL (we have no HTTP redirector in this test).
    $request->headers->set('Accept', 'application/json');

    // Capture $user by value — authorize() will see this exact instance
    $request->setUserResolver(static function () use ($user): User {
        return $user;
    });

    // setParameter() returns void in Laravel's Route — must build the Route,
    // mutate it, then explicitly return it. Single-expression arrow-fn returns null.
    $request->setRouteResolver(static function () use ($request, $template): Route {
        $route = new Route('PUT', '/dummy', []);
        $route->bind($request);
        $route->setParameter('template', $template);

        return $route;
    });

    return $request
        ->setContainer(app())
        ->setRedirector(app('redirect'));
}

/**
 * Helper: create a User with the super_admin role.
 */
function makeSuperAdminForRequest(): User
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
 * Helper: provision a NotificationEmailTemplate for payment_reminder.
 *
 * CampusObserver may auto-create one; use updateOrCreate to avoid unique violations.
 */
function makeTemplateForRequest(): NotificationEmailTemplate
{
    $campus = Campus::factory()->create();

    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Hello {{student_name}}', 'body_html' => '<p>Your balance is {{balance_formatted}}</p>'],
    );
}

// (a) Valid request with allow-listed variables only → passes validation,
//     body_html in request contains purified output (no stripping on safe HTML).
it('passes when subject and body_html use only allow-listed variables', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeSuperAdminForRequest();
    $template = makeTemplateForRequest();

    $payload = [
        'subject' => 'Reminder for {{student_name}} ({{semester_code}})',
        'body_html' => '<p>Balance: {{balance_formatted}}</p>',
    ];

    $request = invokeRequest($payload, $user, $template);

    // Must not throw
    $request->validateResolved();

    // body_html survives the purifier round-trip (simple safe fragment)
    expect($request->input('body_html'))->toContain('Balance:');
    expect($request->input('body_html'))->not->toContain('<script');
});

// (b) body_html with {{unknown_var}} → ValidationException, error on body_html mentioning the var name.
it('rejects body_html containing an unknown template variable', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeSuperAdminForRequest();
    $template = makeTemplateForRequest();

    $payload = [
        'subject' => 'Reminder for {{student_name}}',
        'body_html' => '<p>Amount: {{unknown_var}}</p>',
    ];

    $request = invokeRequest($payload, $user, $template);

    try {
        $request->validateResolved();
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('body_html');
        expect(implode(' ', $e->errors()['body_html']))->toContain('unknown_var');
    }
});

// (c) subject with {{unknown_var}} → ValidationException, error on subject mentioning the var name.
it('rejects subject containing an unknown template variable', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeSuperAdminForRequest();
    $template = makeTemplateForRequest();

    $payload = [
        'subject' => 'Hi {{unknown_var}}',
        'body_html' => '<p>Balance: {{balance_formatted}}</p>',
    ];

    $request = invokeRequest($payload, $user, $template);

    try {
        $request->validateResolved();
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('subject');
        expect(implode(' ', $e->errors()['subject']))->toContain('unknown_var');
    }
});

// (d) body_html with <script> tag → after passedValidation, script tag is stripped.
it('strips script tags from body_html via purifier in passedValidation', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeSuperAdminForRequest();
    $template = makeTemplateForRequest();

    $payload = [
        'subject' => 'Reminder for {{student_name}}',
        'body_html' => '<p>Safe content</p><script>alert(1)</script>',
    ];

    $request = invokeRequest($payload, $user, $template);

    // validateResolved triggers rules() + withValidator() + passedValidation()
    $request->validateResolved();

    expect(strtolower($request->input('body_html')))->not->toContain('<script');
    expect($request->input('body_html'))->toContain('Safe content');
});

// (e) Non-super-admin user → authorize() returns false → AuthorizationException.
it('denies authorization for a user without the super_admin role', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create(); // regular user, no super_admin role
    $template = makeTemplateForRequest();

    $payload = [
        'subject' => 'Reminder for {{student_name}}',
        'body_html' => '<p>Balance: {{balance_formatted}}</p>',
    ];

    $request = invokeRequest($payload, $user, $template);

    expect(fn () => $request->validateResolved())
        ->toThrow(AuthorizationException::class);
});
