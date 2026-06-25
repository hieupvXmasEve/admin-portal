<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Support\Grading\GradingSchemeValidator;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

const S007_CSRF = 's007-metropolia-builder-csrf';

beforeEach(function () {
    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => S007_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

function s007Grant(array $permissions): void
{
    $service = Mockery::mock(PermissionService::class);
    $service->shouldReceive('getUserPermissions')->andReturn($permissions);

    app()->forgetInstance(PermissionService::class);
    app()->instance(PermissionService::class, $service);
}

function s007ActingAsAdmin()
{
    return actingAs(User::factory()->create())
        ->withHeader('X-CSRF-TOKEN', S007_CSRF);
}

/** Cloud Computing v2 formula scheme: variables LAB, QUIZ, EXAM. */
function s007V2Scheme(): array
{
    return [
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
}

/** Single-component v1 linear scheme referencing EXAM. */
function s007V1Scheme(): array
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

it('persists assessment component codes when saving a metropolia scheme', function () {
    s007Grant(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    s007ActingAsAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'Cloud Computing',
            'grading_scheme' => s007V2Scheme(),
            'assessment_components' => [
                ['name' => 'Labs', 'weight' => 50, 'type' => 'assignment', 'code' => 'LAB'],
                ['name' => 'Quizzes', 'weight' => 30, 'type' => 'quiz', 'code' => 'QUIZ'],
                ['name' => 'Final Exam', 'weight' => 20, 'type' => 'exam', 'code' => 'EXAM'],
            ],
        ])
        ->assertCreated();

    $template = SyllabusTemplate::query()->where('title', 'Cloud Computing')->firstOrFail();

    expect($template->assessmentComponents->pluck('code')->sort()->values()->all())
        ->toBe(['EXAM', 'LAB', 'QUIZ']);
});

it('rejects a scheme whose component code is not a declared assessment code', function () {
    s007Grant(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    s007ActingAsAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'Mismatch',
            'grading_scheme' => s007V2Scheme(), // references LAB, QUIZ, EXAM
            'assessment_components' => [
                ['name' => 'Labs', 'weight' => 100, 'type' => 'assignment', 'code' => 'LAB'],
                // QUIZ and EXAM are missing
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['field' => 'grading_scheme']);
});

it('requires a code on each assessment component when a custom scheme is present', function () {
    s007Grant(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    s007ActingAsAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'No Code',
            'grading_scheme' => s007V1Scheme(),
            'assessment_components' => [
                ['name' => 'Exam', 'weight' => 100, 'type' => 'exam'], // no code
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['field' => 'assessment_components.0.code']);
});

it('rejects duplicate assessment component codes', function () {
    s007Grant(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    s007ActingAsAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'Dupe',
            'grading_scheme' => s007V1Scheme(),
            'assessment_components' => [
                ['name' => 'Exam A', 'weight' => 50, 'type' => 'exam', 'code' => 'EXAM'],
                ['name' => 'Exam B', 'weight' => 50, 'type' => 'exam', 'code' => 'EXAM'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['field' => 'assessment_components.1.code']);
});

it('rejects an invalid scheme contract on save', function () {
    s007Grant(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    $scheme = [
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => 'LAB + FOO', // FOO is not a declared scheme component
        'components' => [
            ['code' => 'LAB', 'label' => 'Labs'],
        ],
    ];

    s007ActingAsAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'Invalid Formula',
            'grading_scheme' => $scheme,
            'assessment_components' => [
                ['name' => 'Labs', 'weight' => 100, 'type' => 'assignment', 'code' => 'LAB'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['field' => 'grading_scheme']);
});

it('still saves a default-weighted template without component codes', function () {
    s007Grant(['create_syllabus', 'edit_syllabus', 'view_syllabus']);
    $unit = Unit::factory()->create();

    s007ActingAsAdmin()
        ->postJson('/syllabus-templates', [
            'unit_id' => $unit->id,
            'title' => 'Default Weighted',
            // no grading_scheme => default path
            'assessment_components' => [
                ['name' => 'Homework', 'weight' => 100, 'type' => 'assignment'],
            ],
        ])
        ->assertCreated();

    $template = SyllabusTemplate::query()->where('title', 'Default Weighted')->firstOrFail();
    expect($template->grading_scheme)->toBeNull();
});

// Mirrors resources/js/pages/syllabus/components/grading-scheme-examples.ts so
// the guide-modal "Áp dụng ví dụ" presets are guaranteed runnable/savable.
dataset('guideModalExamples', [
    'software1-programming' => [[
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => '0-5',
        'components' => [
            ['code' => 'ASSIGNMENT', 'label' => 'Assignments', 'gate' => ['min_pct' => 40]],
            ['code' => 'EXAM', 'label' => 'Exam', 'gate' => ['min_pct' => 40], 'conversion' => ['type' => 'linear', 'min_pct' => 40, 'max_pct' => 88, 'min_grade' => 1, 'max_grade' => 5]],
        ],
    ]],
    'database-pass-fail' => [[
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => 'pass_fail',
        'components' => [
            ['code' => 'ASSIGNMENT', 'label' => 'Assignments', 'conversion' => ['type' => 'pass_fail', 'min_pct' => 80]],
        ],
    ]],
    'maths-physics-threshold' => [[
        'engine' => 'metropolia_v1',
        'version' => 1,
        'scale' => '0-5',
        'components' => [
            ['code' => 'ASSIGNMENT', 'label' => 'Assignments', 'conversion' => ['type' => 'threshold', 'steps' => [['min_pct' => 55, 'grade' => 1], ['min_pct' => 70, 'grade' => 2]]]],
            ['code' => 'EXAM', 'label' => 'Final Exam', 'conversion' => ['type' => 'threshold', 'steps' => [['min_pct' => 50, 'grade' => 1], ['min_pct' => 70, 'grade' => 2], ['min_pct' => 85, 'grade' => 3]]]],
        ],
    ]],
    'cloud-computing-formula' => [[
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => '(0.5*LAB + 0.3*QUIZ + 0.1*EXAM - 40) / 10',
        'rounding_stage' => 'after_total',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'components' => [
            ['code' => 'LAB', 'label' => 'Labs'],
            ['code' => 'QUIZ', 'label' => 'Quizzes'],
            ['code' => 'EXAM', 'label' => 'Final Exam'],
        ],
    ]],
    'health-technology-formula' => [[
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => '(ASSIGNMENT - 40) / 10',
        'rounding_stage' => 'after_total',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'components' => [['code' => 'ASSIGNMENT', 'label' => 'Assignments']],
    ]],
]);

it('keeps every guide-modal example runnable through the runtime validator', function (array $scheme) {
    expect(app(GradingSchemeValidator::class)->validate($scheme))->toBe([]);
})->with('guideModalExamples');

it('computes the corrected Cloud formula on raw component percentages', function () {
    s007Grant(['edit_syllabus']);

    $scheme = [
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => '(0.5*LAB + 0.3*QUIZ + 0.1*EXAM - 40) / 10',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'components' => [
            ['code' => 'LAB', 'label' => 'Labs'],
            ['code' => 'QUIZ', 'label' => 'Quizzes'],
            ['code' => 'EXAM', 'label' => 'Final Exam'],
        ],
    ];

    // (0.5*80 + 0.3*70 + 0.1*60 - 40)/10 = (40 + 21 + 6 - 40)/10 = 2.7 -> 3
    s007ActingAsAdmin()
        ->postJson('/api/syllabus-templates/grading-scheme/preview', [
            'grading_scheme' => $scheme,
            'component_scores' => ['LAB' => 80, 'QUIZ' => 70, 'EXAM' => 60],
        ])
        ->assertOk()
        ->assertJsonPath('data.final_grade', '3')
        ->assertJsonPath('data.passed', true);
});

it('persists component codes through the update endpoint', function () {
    s007Grant(['edit_syllabus', 'view_syllabus']);
    $template = SyllabusTemplate::factory()->create();

    s007ActingAsAdmin()
        ->putJson("/syllabus-templates/{$template->id}", [
            'title' => $template->title,
            'grading_scheme' => s007V1Scheme(),
            'assessment_components' => [
                ['name' => 'Exam', 'weight' => 100, 'type' => 'exam', 'code' => 'EXAM'],
            ],
        ])
        ->assertOk();

    expect($template->fresh()->assessmentComponents->pluck('code')->all())
        ->toBe(['EXAM']);
});
