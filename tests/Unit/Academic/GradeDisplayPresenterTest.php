<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Modules\Academic\Delivery\Support\Grading\Presenters\GradeDisplayPresenter;

/**
 * Build an in-memory AcademicRecord (no DB) with the given attributes so the
 * presenter can be exercised in isolation.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeRecord(array $attributes): AcademicRecord
{
    return (new AcademicRecord)->forceFill($attributes);
}

it('formats a Metropolia numeric 0-5 record into the display contract', function () {
    $record = makeRecord([
        'final_percentage' => 100,
        'final_letter_grade' => '5',
        'is_passed' => true,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => '0-5',
            'components' => [
                'EXAM' => ['score_pct' => 88, 'converted_grade' => 5, 'gate_met' => null],
            ],
            'fg_sum_raw' => 5.0,
            'fg_rounded' => 5,
            'gates_passed' => true,
            'gate_failures' => [],
            'final_grade' => '5',
        ],
    ]);

    $display = app(GradeDisplayPresenter::class)->present($record);

    expect($display['scheme_engine'])->toBe('metropolia_v1')
        ->and($display['scale'])->toBe('numeric_0_5')
        ->and($display['final_label'])->toBe('5')
        ->and($display['final_numeric'])->toBe(5)
        ->and($display['pass_status'])->toBe('passed')
        ->and($display['components'])->toHaveCount(1)
        ->and($display['components'][0])->toBe([
            'code' => 'EXAM',
            'label' => 'EXAM',
            'raw_percentage' => 88,
            'converted_grade' => 5,
            'requirement_status' => null,
        ]);
});

it('formats a Metropolia pass/fail record and derives requirement status from gates', function () {
    $record = makeRecord([
        'final_percentage' => null,
        'final_letter_grade' => 'F',
        'is_passed' => false,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => 'pass_fail',
            'components' => [
                'ATTENDANCE' => ['score_pct' => 30, 'converted_grade' => null, 'gate_met' => false],
            ],
            'gates_passed' => false,
            'gate_failures' => [['code' => 'ATTENDANCE', 'score' => 30, 'required' => 80]],
            'final_grade' => 'F',
        ],
    ]);

    $display = app(GradeDisplayPresenter::class)->present($record);

    expect($display['scale'])->toBe('pass_fail')
        ->and($display['final_label'])->toBe('F')
        ->and($display['final_numeric'])->toBeNull()
        ->and($display['pass_status'])->toBe('failed')
        ->and($display['components'][0]['requirement_status'])->toBe('failed');
});

it('falls back to default weighted percentage for records without a custom scheme', function () {
    $record = makeRecord([
        'final_percentage' => 72.5,
        'final_letter_grade' => 'B',
        'is_passed' => true,
        'grade_breakdown' => null,
    ]);

    $display = app(GradeDisplayPresenter::class)->present($record);

    expect($display['scheme_engine'])->toBe('default_weighted_percentage')
        ->and($display['scale'])->toBe('percentage')
        ->and($display['final_label'])->toBe('B')
        ->and($display['final_numeric'])->toBe(72.5)
        ->and($display['pass_status'])->toBe('passed')
        ->and($display['components'])->toBe([]);
});

it('never exposes internal calculation fields', function () {
    $record = makeRecord([
        'final_percentage' => 100,
        'final_letter_grade' => '5',
        'is_passed' => true,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => '0-5',
            'components' => ['EXAM' => ['score_pct' => 88, 'converted_grade' => 5, 'gate_met' => null]],
            'fg_sum_raw' => 5.0,
            'fg_rounded' => 5,
            'gates_passed' => true,
            'gate_failures' => [],
            'final_grade' => '5',
        ],
    ]);

    $display = app(GradeDisplayPresenter::class)->present($record);

    expect(array_keys($display))->toBe([
        'scheme_engine',
        'scale',
        'final_label',
        'final_numeric',
        'pass_status',
        'components',
    ]);
    expect($display)->not->toHaveKeys(['fg_sum_raw', 'fg_rounded', 'gates_passed', 'gate_failures']);
});

it('builds the same contract from raw parts (string JSON breakdown, int is_passed)', function () {
    $presenter = app(GradeDisplayPresenter::class);

    $display = $presenter->fromParts(
        breakdown: json_decode((string) json_encode([
            'engine' => 'metropolia_v1',
            'scale' => 'pass_fail',
            'final_grade' => 'P',
        ]), true),
        finalLetterGrade: 'P',
        finalPercentage: null,
        isPassed: true,
    );

    expect($display['scheme_engine'])->toBe('metropolia_v1')
        ->and($display['scale'])->toBe('pass_fail')
        ->and($display['final_label'])->toBe('P')
        ->and($display['pass_status'])->toBe('passed');
});

it('prefers the stored breakdown pass result over a stale top-level pass flag', function () {
    $presenter = app(GradeDisplayPresenter::class);

    $display = $presenter->fromParts(
        breakdown: [
            'engine' => 'metropolia_v1',
            'scale' => 'pass_fail',
            'final_grade' => 'P',
            'passed' => true,
        ],
        finalLetterGrade: 'P',
        finalPercentage: null,
        isPassed: false,
    );

    expect($display['pass_status'])->toBe('passed');
});
