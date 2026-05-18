<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Services\PermissionService;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const PREVIEW_TEST_CSRF = 'preview-test-csrf';

beforeEach(function () {
    // Replicate A-batch middleware setup:
    // - Campus singleton bypasses CheckCampusSelected
    // - Session carries CSRF token + current_campus_id
    // - PermissionService mock bypasses HandleInertiaRequests expectations
    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);

    session([
        '_token' => PREVIEW_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn([]);
    $this->app->singleton(PermissionService::class, fn () => $permissionService);
});

function makePreviewSuperAdmin(): User
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

function makePreviewTemplate(): NotificationEmailTemplate
{
    $campus = Campus::factory()->create();

    return NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Reminder for {{student_name}}', 'body_html' => '<p>Balance: {{balance_formatted}}</p>'],
    );
}

// (a) Super Admin POST /preview with draft → 200; rendered_html substitutes sample variables.
it('super admin can preview a notification template draft and gets sample variable substitution', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makePreviewSuperAdmin();
    $template = makePreviewTemplate();

    $draftSubject = 'Hello {{student_name}}';
    $draftBody = '<p>Dear {{student_name}}, your balance is {{balance_formatted}}.</p>';

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', PREVIEW_TEST_CSRF)
        ->postJson(
            route('api.admin.notification-templates.preview', ['template' => $template->id]),
            ['subject' => $draftSubject, 'body_html' => $draftBody],
        );

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $renderedHtml = $response->json('data.rendered_html');
    $renderedSubject = $response->json('data.rendered_subject');

    // student_name sample = 'Nguyễn Văn A'
    expect($renderedHtml)->toContain('Nguyễn Văn A');
    expect($renderedSubject)->toContain('Nguyễn Văn A');
    // Placeholders should be replaced, not left as-is
    expect($renderedHtml)->not->toContain('{{student_name}}');
    expect($renderedSubject)->not->toContain('{{student_name}}');
});

// (b) Render path parity: controller output is byte-equal to direct transient model render.
//     XSS safety is verified by asserting renderContentEscaped is used for html body.
it('preview rendered_html is byte-equal to direct transient model render and escapes html in body', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makePreviewSuperAdmin();
    $template = makePreviewTemplate();

    $draftBody = '<p>Dear {{student_name}}, balance: {{balance_formatted}}.</p>';
    $draftSubject = 'For {{student_name}}';

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', PREVIEW_TEST_CSRF)
        ->postJson(
            route('api.admin.notification-templates.preview', ['template' => $template->id]),
            ['subject' => $draftSubject, 'body_html' => $draftBody],
        );

    $response->assertOk();

    // Build expected output using same render path (transient model + render())
    $typeKey = NotificationTemplateTypeKey::from('payment_reminder');
    $sampleVariables = collect($typeKey->availableVariables())
        ->map(fn (array $meta) => $meta['sample'])
        ->all();

    $transient = new NotificationEmailTemplate([
        'campus_id' => $template->campus_id,
        'type_key' => 'payment_reminder',
        'subject' => $draftSubject,
        'body_html' => $draftBody,
    ]);

    $expected = $transient->render($sampleVariables);

    // Byte-exact parity check
    expect($response->json('data.rendered_html'))->toBe($expected['html']);
    expect($response->json('data.rendered_subject'))->toBe($expected['subject']);

    // XSS safety: renderContentEscaped escapes variable values in html body.
    // Verify directly via transient model render with tainted values.
    $tainted = ['student_name' => '<script>alert(1)</script>', 'balance_formatted' => '&', 'semester_code' => '', 'invoice_code' => '', 'due_date' => ''];
    $xssTransient = new NotificationEmailTemplate([
        'campus_id' => $template->campus_id,
        'type_key' => 'payment_reminder',
        'subject' => $draftSubject,
        'body_html' => '<p>Hello {{student_name}}</p>',
    ]);
    $xssResult = $xssTransient->render($tainted);
    // Critical-patterns.md: must contain escaped chars, never raw markup
    expect($xssResult['html'])->toContain('&lt;script&gt;');
    expect($xssResult['html'])->not->toContain('<script>alert');
});

// (c) Non-super-admin → 403.
it('non-super-admin receives 403 when attempting to preview a notification template', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create(); // no super_admin role
    $template = makePreviewTemplate();

    $response = $this->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', PREVIEW_TEST_CSRF)
        ->postJson(
            route('api.admin.notification-templates.preview', ['template' => $template->id]),
            ['subject' => 'Test', 'body_html' => '<p>Test</p>'],
        );

    $response->assertForbidden();
});
