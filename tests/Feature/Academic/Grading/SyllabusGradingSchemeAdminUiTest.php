<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

const SYLLABUS_TEST_CSRF = 'syllabus-test-csrf-token';

beforeEach(function () {
    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => SYLLABUS_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

/**
 * Grant the given syllabus permissions to every user by mocking the lazy
 * PermissionService the `can:` gate resolves through.
 */
function grantSyllabusPermissions(array $permissions): void
{
    $service = Mockery::mock(PermissionService::class);
    $service->shouldReceive('getUserPermissions')->andReturn($permissions);

    app()->forgetInstance(PermissionService::class);
    app()->instance(PermissionService::class, $service);
}

/**
 * Act as a fresh user and attach the CSRF token the web middleware expects.
 */
function actingAsSyllabusAdmin()
{
    return actingAs(User::factory()->create())
        ->withHeader('X-CSRF-TOKEN', SYLLABUS_TEST_CSRF);
}

function metropoliaV1Scheme(): array
{
    return [
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => '0-5',
        'components' => [
            [
                'code' => 'EXAM',
                'label' => 'Exam',
                'conversion' => ['type' => 'linear', 'min_pct' => 40, 'max_pct' => 88, 'min_grade' => 1, 'max_grade' => 5],
            ],
        ],
    ];
}

it('previews a metropolia_v1 scheme without saving it', function () {
    grantSyllabusPermissions(['view_syllabus', 'edit_syllabus']);

    actingAsSyllabusAdmin()
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => metropoliaV1Scheme(),
            'component_scores' => ['EXAM' => 88],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.final_grade', '5')
        ->assertJsonPath('data.scale', '0-5')
        ->assertJsonPath('data.passed', true);
});

it('previews a metropolia_v2 formula scheme exactly', function () {
    grantSyllabusPermissions(['edit_syllabus']);

    $scheme = [
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => '(LAB + QUIZ + EXAM / 2 - 40) / 10',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'components' => [
            ['code' => 'LAB', 'label' => 'Labs'],
            ['code' => 'QUIZ', 'label' => 'Quizzes'],
            ['code' => 'EXAM', 'label' => 'Final Exam'],
        ],
    ];

    actingAsSyllabusAdmin()
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => $scheme,
            'component_scores' => ['LAB' => 0, 'QUIZ' => 0, 'EXAM' => 100],
        ])
        ->assertOk()
        ->assertJsonPath('data.final_grade', '1'); // exact formula, not the old approximation
});

it('rejects a malformed grading scheme preview with 422', function () {
    grantSyllabusPermissions(['edit_syllabus']);

    actingAsSyllabusAdmin()
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => ['engine' => 'metropolia_v1'],
            'component_scores' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('forbids preview without the edit_syllabus permission', function () {
    grantSyllabusPermissions(['view_syllabus']);

    actingAsSyllabusAdmin()
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => metropoliaV1Scheme(),
            'component_scores' => ['EXAM' => 88],
        ])
        ->assertForbidden();
});

it('lists the available grading engines', function () {
    grantSyllabusPermissions(['view_syllabus']);

    $response = actingAsSyllabusAdmin()
        ->getJson('/api/syllabus-templates/grading-scheme/options')
        ->assertOk()
        ->assertJsonPath('success', true);

    $values = collect($response->json('data.engines'))->pluck('value')->all();
    expect($values)->toContain('default', 'metropolia_v1', 'metropolia_v2');
});

it('saves a custom grading scheme through the store endpoint', function () {
    grantSyllabusPermissions(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    actingAsSyllabusAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'Programming',
            'grading_scheme' => metropoliaV1Scheme(),
        ])
        ->assertCreated();

    $template = SyllabusTemplate::query()->where('title', 'Programming')->firstOrFail();
    expect($template->grading_scheme['engine'])->toBe('metropolia_v1');
});

it('clears a grading scheme back to default through the update endpoint', function () {
    grantSyllabusPermissions(['edit_syllabus', 'view_syllabus']);
    $template = SyllabusTemplate::factory()->create(['grading_scheme' => metropoliaV1Scheme()]);

    actingAsSyllabusAdmin()
        ->putJson("/syllabus-templates/{$template->id}", [
            'title' => $template->title,
            'grading_scheme' => null,
        ])
        ->assertOk();

    expect($template->fresh()->grading_scheme)->toBeNull();
});
