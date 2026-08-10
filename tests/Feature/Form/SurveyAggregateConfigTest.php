<?php

declare(strict_types=1);

use App\Models\Answer;
use App\Models\Campus;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\FormSection;
use App\Models\FormTarget;
use App\Models\FormVersion;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFormAssignment;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HN']);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    bindAggregateConfigPermissions(['view_survey_results_aggregate']);
});

function bindAggregateConfigPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
}

/**
 * Seeds a survey with two rating questions (Q1, Q2), one non-rating numeric
 * question (Q3, type=number), and one submitted response with configurable
 * answer values. Returns the FormTarget.
 */
function seedAggregateConfigRun(object $context, array $answers, ?array $aggregateConfig = null): FormTarget
{
    $semester = Semester::factory()->active()->create();

    $form = Form::create([
        'code' => 'AGG-CONFIG-SURVEY',
        'type' => 'survey',
        'title' => 'Aggregate Config Survey',
        'status' => 'active',
        'created_by' => $context->user->id,
    ]);

    if ($aggregateConfig !== null) {
        $form->forceFill(['aggregate_config' => $aggregateConfig])->save();
    }

    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);

    $section = FormSection::create([
        'form_version_id' => $version->id,
        'title' => 'Ratings',
        'order_index' => 1,
    ]);

    $q1 = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $section->id,
        'code' => 'q1',
        'text' => 'Rating one',
        'type' => 'rating',
        'order_index' => 1,
    ]);

    $q2 = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $section->id,
        'code' => 'q2',
        'text' => 'Rating two',
        'type' => 'rating',
        'order_index' => 2,
    ]);

    $q3 = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $section->id,
        'code' => 'q3',
        'text' => 'Non-rating number',
        'type' => 'number',
        'order_index' => 3,
    ]);

    $target = FormTarget::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $context->campus->id,
        'semester_id' => (string) $semester->id,
        'scope_type' => 'global',
        'status' => 'closed',
        'start_at' => now()->subWeek(),
        'end_at' => now(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => true,
    ]);

    $student = Student::factory()->forCampus($context->campus)->create([
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);

    $response = FormResponse::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'form_target_id' => $target->id,
        'campus_id' => $context->campus->id,
        'target_scope_type' => 'global',
        'submitted_by_student_id' => $student->id,
        'anonymized' => true,
        'status' => 'submitted',
        'origin' => 'web',
        'submitted_at' => now(),
    ]);

    StudentFormAssignment::create([
        'student_id' => $student->id,
        'form_target_id' => $target->id,
        'status' => 'completed',
        'response_id' => $response->id,
        'completed_at' => now(),
    ]);

    $codeToQuestion = ['q1' => $q1, 'q2' => $q2, 'q3' => $q3];
    foreach ($answers as $code => $value) {
        Answer::create([
            'response_id' => $response->id,
            'question_id' => $codeToQuestion[$code]->id,
            'answer_number' => $value,
        ]);
    }

    return $target;
}

it('defaults to averaging all rating questions with 4/2 thresholds when no config exists', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5, 'q2' => 3, 'q3' => 99]);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overall.average', 4)
            ->where('overall.is_custom', false)
            ->where('overall.positive_min', 4)
            ->where('overall.negative_max', 2)
            ->where('overall.included_count', 2)
        );
});

it('restricts overall KPI to configured question codes and thresholds', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5, 'q2' => 1], [
        'overall' => [
            'question_codes' => ['q1'],
            'thresholds' => ['positive_min' => 5, 'negative_max' => 1],
        ],
    ]);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overall.average', 5)
            ->where('overall.is_custom', true)
            ->where('overall.positive_min', 5)
            ->where('overall.negative_max', 1)
            ->where('overall.included_count', 1)
            ->where('overall.positive_percent', 100)
        );
});

it('matches nothing when configured codes no longer resolve, surfacing zero included_count', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5], [
        'overall' => [
            'question_codes' => ['stale-code'],
            'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
        ],
    ]);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overall.average', 0)
            ->where('overall.included_count', 0)
        );
});

it('buckets a non-integer answer into neutral, pinning the range behavior change', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 3.5]);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('overall.positive_percent', 0)
            ->where('overall.neutral_percent', 100)
            ->where('overall.negative_percent', 0)
        );
});

it('rejects saving aggregate config without the configure_survey_aggregate permission', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5]);
    bindAggregateConfigPermissions([]);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.aggregate-config.update', $target->form), [
            '_token' => 'test-token',
            'overall' => [
                'question_codes' => ['q1'],
                'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
            ],
        ])
        ->assertForbidden();

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->delete(route('forms.admin.aggregate-config.destroy', $target->form), ['_token' => 'test-token'])
        ->assertForbidden();
});

it('saves and resets aggregate config for a user with configure_survey_aggregate permission', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5]);
    bindAggregateConfigPermissions(['configure_survey_aggregate']);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.aggregate-config.update', $target->form), [
            '_token' => 'test-token',
            'overall' => [
                'question_codes' => ['q1'],
                'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
            ],
        ])
        ->assertRedirect();

    expect($target->form->refresh()->aggregate_config['overall']['question_codes'])->toBe(['q1']);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.aggregate-config.update', $target->form), [
            '_token' => 'test-token',
            'overall' => [
                'question_codes' => ['q1', 'q1'],
                'thresholds' => ['positive_min' => 4, 'negative_max' => 2, 'junk' => 'xxx'],
                'evil' => ['a' => 1],
            ],
        ])
        ->assertRedirect();

    expect($target->form->refresh()->aggregate_config)->toBe([
        'overall' => [
            'question_codes' => ['q1'],
            'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
        ],
    ]);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->delete(route('forms.admin.aggregate-config.destroy', $target->form), ['_token' => 'test-token'])
        ->assertRedirect();

    expect($target->form->refresh()->aggregate_config)->toBeNull();
});

it('rejects an aggregate config with empty codes, non-rating codes, or inverted thresholds', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5]);
    bindAggregateConfigPermissions(['configure_survey_aggregate']);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.aggregate-config.update', $target->form), [
            '_token' => 'test-token',
            'overall' => [
                'question_codes' => [],
                'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
            ],
        ])
        ->assertSessionHasErrors('overall.question_codes');

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.aggregate-config.update', $target->form), [
            '_token' => 'test-token',
            'overall' => [
                'question_codes' => ['q3'],
                'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
            ],
        ])
        ->assertSessionHasErrors('overall.question_codes');

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.aggregate-config.update', $target->form), [
            '_token' => 'test-token',
            'overall' => [
                'question_codes' => ['q1'],
                'thresholds' => ['positive_min' => 2, 'negative_max' => 3],
            ],
        ])
        ->assertSessionHasErrors('overall.thresholds.positive_min');
});

it('does not let PUT forms.admin.update mass-assign aggregate_config', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5]);
    $form = $target->form;
    bindAggregateConfigPermissions(['edit_survey']);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.update', $form), [
            '_token' => 'test-token',
            'title' => 'Renamed via update',
            'aggregate_config' => [
                'overall' => [
                    'question_codes' => ['q1'],
                    'thresholds' => ['positive_min' => 4, 'negative_max' => 2],
                ],
            ],
        ])
        ->assertRedirect();

    expect($form->refresh()->title)->toBe('Renamed via update');
    expect($form->aggregate_config)->toBeNull();
});

it('blocks form create/update/destroy routes for a user missing the matching survey permission', function () {
    $target = seedAggregateConfigRun($this, ['q1' => 5]);
    bindAggregateConfigPermissions([]);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->put(route('forms.admin.update', $target->form), ['_token' => 'test-token', 'title' => 'x'])
        ->assertForbidden();

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->delete(route('forms.admin.destroy', $target->form), ['_token' => 'test-token'])
        ->assertForbidden();

    actingAs($this->user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('forms.admin.store'), ['_token' => 'test-token'])
        ->assertForbidden();
});
