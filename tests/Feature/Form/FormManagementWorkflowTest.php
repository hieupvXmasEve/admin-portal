<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormTarget;
use App\Models\FormVersion;
use App\Models\Role;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->csrfToken = 'form-management-csrf';

    session([
        'current_campus_id' => $this->campus->id,
        '_token' => $this->csrfToken,
    ]);
    app()->singleton('campus', fn (): Campus => $this->campus);

    bindFormManagementPermissions(['view_form', 'edit_form']);
});

function bindFormManagementPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
}

function createManagedForm(object $context, array $attributes = []): Form
{
    return Form::create(array_merge([
        'code' => 'FORM-'.uniqid('', false),
        'type' => 'survey',
        'title' => 'Student feedback',
        'status' => 'draft',
        'created_by' => $context->user->id,
    ], $attributes));
}

function createManagedVersion(Form $form, bool $published = false): FormVersion
{
    return FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => $published,
        'effective_from' => now()->subDay(),
    ]);
}

function createManagedTarget(Form $form, FormVersion $version, ?Campus $campus, array $attributes = []): FormTarget
{
    return FormTarget::create(array_merge([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $campus?->id,
        'scope_type' => 'global',
        'status' => 'active',
        'start_at' => now()->subDay(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => false,
    ], $attributes));
}

it('filters the form index by type, status, search, and target campus', function (): void {
    $matchingForm = createManagedForm($this, [
        'type' => 'survey',
        'status' => 'active',
        'title' => 'Campus satisfaction survey',
    ]);
    $matchingVersion = createManagedVersion($matchingForm, published: true);
    createManagedTarget($matchingForm, $matchingVersion, $this->campus);

    $globalForm = createManagedForm($this, [
        'type' => 'survey',
        'status' => 'active',
        'title' => 'Campus global survey',
    ]);
    $globalVersion = createManagedVersion($globalForm, published: true);
    createManagedTarget($globalForm, $globalVersion, null);

    $otherCampus = Campus::factory()->create();
    $foreignForm = createManagedForm($this, [
        'type' => 'survey',
        'status' => 'active',
        'title' => 'Campus foreign survey',
    ]);
    $foreignVersion = createManagedVersion($foreignForm, published: true);
    createManagedTarget($foreignForm, $foreignVersion, $otherCampus);

    $differentTypeForm = createManagedForm($this, [
        'type' => 'query',
        'status' => 'active',
        'title' => 'Campus support query',
    ]);
    $differentTypeVersion = createManagedVersion($differentTypeForm, published: true);
    createManagedTarget($differentTypeForm, $differentTypeVersion, $this->campus);

    actingAs($this->user)
        ->get(route('forms.admin.index', [
            'type' => 'survey',
            'status' => 'active',
            'search' => 'Campus',
            'campus_id' => $this->campus->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/Index')
            ->where('filters.type', 'survey')
            ->where('filters.status', 'active')
            ->where('filters.search', 'Campus')
            ->where('filters.campus_id', (string) $this->campus->id)
            ->where('forms', function ($forms) use ($matchingForm, $globalForm, $foreignForm, $differentTypeForm): bool {
                $ids = collect($forms)->pluck('id');

                return $ids->contains($matchingForm->id)
                    && $ids->contains($globalForm->id)
                    && ! $ids->contains($foreignForm->id)
                    && ! $ids->contains($differentTypeForm->id);
            })
        );
});

it('renders form show and edit pages with the managed structure', function (): void {
    $form = createManagedForm($this);
    $version = createManagedVersion($form, published: true);
    $version->questions()->create([
        'code' => 'Q1',
        'text' => 'How was your experience?',
        'type' => 'short_text',
        'order_index' => 1,
    ]);
    createManagedTarget($form, $version, $this->campus);

    actingAs($this->user)
        ->get(route('forms.admin.show', $form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/Show')
            ->where('form.id', $form->id)
            ->where('statistics.total_responses', 0)
        );

    actingAs($this->user)
        ->get(route('forms.admin.edit', $form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/Edit')
            ->where('form.id', $form->id)
            ->has('questionTypes.short_text')
        );
});

it('creates a complete form and normalizes an all-campus target', function (): void {
    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.store'), [
            '_token' => $this->csrfToken,
            'code' => 'FORM-NEW',
            'type' => 'survey',
            'title' => 'New survey',
            'publish_immediately' => true,
            'questions' => [[
                'code' => 'Q1',
                'text' => 'How was your class?',
                'type' => 'short_text',
            ]],
            'targets' => [[
                'campus_id' => 'all',
                'scope_type' => 'global',
                'start_at' => now()->subHour()->toDateTimeString(),
                'submission_limit_per_user' => 1,
            ]],
        ])
        ->assertRedirect();

    $form = Form::where('code', 'FORM-NEW')->firstOrFail();

    expect($form->status)->toBe('draft');
    expect($form->versions()->where('is_published', true)->exists())->toBeTrue();
    expect($form->latestPublishedVersion->questions)->toHaveCount(1);
    expect($form->targets()->firstOrFail()->campus_id)->toBeNull();
});

it('updates a form by publishing a new structural version', function (): void {
    $form = createManagedForm($this);
    createManagedVersion($form, published: true);

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->put(route('forms.admin.update', $form), [
            '_token' => $this->csrfToken,
            'title' => 'Updated survey',
            'questions' => [[
                'code' => 'Q2',
                'text' => 'What should improve?',
                'type' => 'long_text',
            ]],
        ])
        ->assertRedirect(route('forms.admin.show', $form));

    expect($form->fresh()->title)->toBe('Updated survey');
    expect($form->versions()->count())->toBe(2);
    expect($form->versions()->where('is_published', true)->value('version_no'))->toBe(2);
});

it('clones a form when the legacy code and title fields are submitted', function (): void {
    $form = createManagedForm($this);
    $version = createManagedVersion($form, published: true);
    $version->questions()->create([
        'code' => 'Q1',
        'text' => 'Original question',
        'type' => 'short_text',
        'order_index' => 1,
    ]);

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->post(route('forms.admin.clone', $form), [
            '_token' => $this->csrfToken,
            'code' => 'FORM-CLONE',
            'title' => 'Cloned survey',
        ])
        ->assertRedirect();

    $clone = Form::where('code', 'FORM-CLONE')->firstOrFail();

    expect($clone->title)->toBe('Cloned survey');
    expect($clone->status)->toBe('draft');
    expect($clone->versions()->firstOrFail()->questions)->toHaveCount(1);
});

it('publishes a version before activating its form', function (): void {
    $form = createManagedForm($this);
    $version = createManagedVersion($form);

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->post(route('forms.admin.version.publish', [$form, $version]), [
            '_token' => $this->csrfToken,
        ])
        ->assertRedirect();

    expect($version->fresh()->is_published)->toBeTrue();

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->post(route('forms.admin.activate', $form), [
            '_token' => $this->csrfToken,
        ])
        ->assertRedirect();

    expect($form->fresh()->status)->toBe('active');
});

it('does not publish a version that belongs to another form', function (): void {
    $form = createManagedForm($this);
    $otherForm = createManagedForm($this);
    $foreignVersion = createManagedVersion($otherForm);

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->post(route('forms.admin.version.publish', [
            'form' => $form,
            'version' => $foreignVersion,
        ]), [
            '_token' => $this->csrfToken,
        ])
        ->assertNotFound();

    expect($foreignVersion->fresh()->is_published)->toBeFalse();
});

it('archives, restores, and soft deletes forms without responses', function (): void {
    $archivableForm = createManagedForm($this, ['status' => 'active']);
    $deletableForm = createManagedForm($this);

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->post(route('forms.admin.archive', $archivableForm), [
            '_token' => $this->csrfToken,
        ])
        ->assertRedirect();

    expect($archivableForm->fresh()->status)->toBe('archived');

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->post(route('forms.admin.restore', $archivableForm), [
            '_token' => $this->csrfToken,
        ])
        ->assertRedirect();

    expect($archivableForm->fresh()->status)->toBe('draft');

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->delete(route('forms.admin.destroy', $deletableForm), [
            '_token' => $this->csrfToken,
        ])
        ->assertRedirect(route('forms.admin.index'));

    $this->assertSoftDeleted('forms', ['id' => $deletableForm->id]);
});

it('creates a target directly on a form for an admin at the selected campus', function (): void {
    $adminRole = Role::factory()->create([
        'name' => 'Administrator',
        'code' => 'admin',
    ]);
    $this->user->campusRoles()->attach($adminRole, ['campus_id' => $this->campus->id]);

    $form = createManagedForm($this, ['status' => 'active']);
    $version = createManagedVersion($form, published: true);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.targets.store', $form), [
            '_token' => $this->csrfToken,
            'form_version_id' => $version->id,
            'campus_id' => $this->campus->id,
            'scope_type' => 'global',
            'start_at' => now()->subHour()->toDateTimeString(),
            'submission_limit_per_user' => 2,
            'status' => 'active',
            'is_mandatory' => true,
        ])
        ->assertRedirect();

    $target = $form->targets()->sole();

    expect($target->form_version_id)->toBe($version->id)
        ->and($target->campus_id)->toBe($this->campus->id)
        ->and($target->scope_type)->toBe('global')
        ->and($target->submission_limit_per_user)->toBe(2)
        ->and($target->is_mandatory)->toBeTrue();
});

it('creates, activates, and closes a campus-scoped form run', function (): void {
    $form = createManagedForm($this, ['status' => 'active']);
    $version = createManagedVersion($form, published: true);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->postJson(route('forms.admin.runs.store'), [
            '_token' => $this->csrfToken,
            'form_id' => $form->id,
            'form_version_id' => $version->id,
            'scope_type' => 'course',
            'scope_id' => 999,
            'start_at' => now()->subHour()->toDateTimeString(),
            'is_mandatory' => false,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $target = FormTarget::query()->sole();

    expect($target->scope_type)->toBe('global');
    expect($target->campus_id)->toBe($this->campus->id);
    expect($target->status)->toBe('draft');

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->postJson(route('forms.admin.runs.activate', $target), [
            '_token' => $this->csrfToken,
        ])
        ->assertOk();

    expect($target->fresh()->status)->toBe('active');

    actingAs($this->user)
        ->withSession(['_token' => $this->csrfToken])
        ->postJson(route('forms.admin.runs.close', $target), [
            '_token' => $this->csrfToken,
        ])
        ->assertOk();

    expect($target->fresh()->status)->toBe('closed');
});

it('requires a form target version to belong to the submitted form', function (): void {
    $form = createManagedForm($this, ['status' => 'active']);
    $version = createManagedVersion($form, published: true);
    $otherForm = createManagedForm($this, ['status' => 'active']);
    $foreignVersion = createManagedVersion($otherForm, published: true);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->postJson(route('forms.admin.runs.store'), [
            '_token' => $this->csrfToken,
            'form_id' => $form->id,
            'form_version_id' => $foreignVersion->id,
            'scope_type' => 'global',
            'start_at' => now()->subHour()->toDateTimeString(),
            'is_mandatory' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'form_version_id')
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');

    expect(FormTarget::count())->toBe(0)
        ->and($version->form_id)->toBe($form->id);
});

it('does not activate or close a form run from another campus', function (): void {
    $form = createManagedForm($this, ['status' => 'active']);
    $version = createManagedVersion($form, published: true);
    $foreignCampus = Campus::factory()->create();
    $foreignTarget = createManagedTarget($form, $version, $foreignCampus, ['status' => 'draft']);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->postJson(route('forms.admin.runs.activate', $foreignTarget), [
            '_token' => $this->csrfToken,
        ])
        ->assertNotFound();

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->postJson(route('forms.admin.runs.close', $foreignTarget), [
            '_token' => $this->csrfToken,
        ])
        ->assertNotFound();

    expect($foreignTarget->fresh()->status)->toBe('draft')
        ->and($foreignTarget->fresh()->campus_id)->toBe($foreignCampus->id);
});

it('filters form runs by scope and status within the selected campus', function (): void {
    $form = createManagedForm($this, ['status' => 'active']);
    $version = createManagedVersion($form, published: true);
    $currentCampusRun = createManagedTarget($form, $version, $this->campus);
    $globalRun = createManagedTarget($form, $version, null);
    $otherCampus = Campus::factory()->create();
    $foreignCampusRun = createManagedTarget($form, $version, $otherCampus);
    $closedRun = createManagedTarget($form, $version, $this->campus, ['status' => 'closed']);
    $departmentRun = createManagedTarget($form, $version, $this->campus, [
        'scope_type' => 'department',
        'scope_id' => Department::factory()->create()->id,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.runs.index', [
            'scope_type' => 'global',
            'status' => 'active',
            'per_page' => 5,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Runs/Index')
            ->where('filters.scope_type', 'global')
            ->where('filters.status', 'active')
            ->where('filters.per_page', '5')
            ->where('runs.data', function ($runs) use ($currentCampusRun, $globalRun, $foreignCampusRun, $closedRun, $departmentRun): bool {
                $ids = collect($runs)->pluck('id');

                return $ids->contains($currentCampusRun->id)
                    && $ids->contains($globalRun->id)
                    && ! $ids->contains($foreignCampusRun->id)
                    && ! $ids->contains($closedRun->id)
                    && ! $ids->contains($departmentRun->id);
            })
        );
});

it('rejects invalid form-run pagination before loading the table', function (): void {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.runs.index', ['per_page' => 101]))
        ->assertSessionHasErrors('per_page');
});
