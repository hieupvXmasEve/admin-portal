<?php

declare(strict_types=1);

use App\Modules\Academic\Delivery\Support\Grading\MetropoliaV1Calculator;

// Helpers
function programmingScheme(): array
{
    return [
        'engine' => 'metropolia_v1',
        'scale' => '0-5',
        'components' => [
            ['code' => 'ASSIGNMENT', 'gate' => ['min_pct' => 40], 'conversion' => null],
            [
                'code' => 'EXAM',
                'gate' => ['min_pct' => 40],
                'conversion' => ['type' => 'linear', 'min_pct' => 40, 'max_pct' => 88, 'min_grade' => 1, 'max_grade' => 5],
            ],
        ],
    ];
}

function mathsScheme(): array
{
    return [
        'engine' => 'metropolia_v1',
        'scale' => '0-5',
        'components' => [
            [
                'code' => 'ASSIGNMENT',
                'conversion' => ['type' => 'threshold', 'steps' => [
                    ['min_pct' => 55, 'grade' => 1],
                    ['min_pct' => 70, 'grade' => 2],
                ]],
            ],
            [
                'code' => 'EXAM',
                'conversion' => ['type' => 'threshold', 'steps' => [
                    ['min_pct' => 50, 'grade' => 1],
                    ['min_pct' => 70, 'grade' => 2],
                    ['min_pct' => 85, 'grade' => 3],
                ]],
            ],
        ],
    ];
}

function databaseScheme(): array
{
    return [
        'engine' => 'metropolia_v1',
        'scale' => 'pass_fail',
        'components' => [
            [
                'code' => 'ASSIGNMENT',
                'conversion' => ['type' => 'pass_fail', 'min_pct' => 80],
            ],
        ],
    ];
}

function projectScheme(): array
{
    return [
        'engine' => 'metropolia_v1',
        'scale' => '0-5',
        'components' => [
            [
                'code' => 'PROJECT_PRESENTATION',
                'conversion' => ['type' => 'direct', 'max_grade' => 5],
            ],
        ],
    ];
}

// ─── Linear conversion ───────────────────────────────────────────────────────

test('linear: 88% exam → grade 5 (maximum)', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 50.0, 'EXAM' => 88.0],
        programmingScheme()
    );
    expect($result->finalGrade)->toBe('5')
        ->and($result->passed)->toBeTrue()
        ->and($result->gradePoints)->toBe(5.0);
});

test('linear: 40% exam → grade 1 (minimum passing)', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 50.0, 'EXAM' => 40.0],
        programmingScheme()
    );
    expect($result->finalGrade)->toBe('1')
        ->and($result->passed)->toBeTrue();
});

test('linear: 64% exam → grade 3 (midpoint)', function () {
    // midpoint: (64-40)/(88-40)*(5-1)+1 = 24/48*4+1 = 3.0
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 50.0, 'EXAM' => 64.0],
        programmingScheme()
    );
    expect((int) $result->gradePoints)->toBe(3);
});

test('linear: exam below gate fails course despite assignment passing', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 50.0, 'EXAM' => 30.0],
        programmingScheme()
    );
    expect($result->passed)->toBeFalse()
        ->and($result->gradeBreakdown['gate_failures'])->not->toBeEmpty();
});

test('linear: assignment fails gate → course fails', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 35.0, 'EXAM' => 88.0],
        programmingScheme()
    );
    expect($result->passed)->toBeFalse();
});

// ─── Threshold conversion ────────────────────────────────────────────────────

test('threshold: maths assignment 70%+ → grade 2', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 72.0, 'EXAM' => 85.0],
        mathsScheme()
    );
    // Assignment → 2, Exam → 3, FG = 5
    expect($result->finalGrade)->toBe('5')
        ->and($result->passed)->toBeTrue();
});

test('threshold: maths assignment 55-69% → grade 1', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 60.0, 'EXAM' => 50.0],
        mathsScheme()
    );
    // Assignment → 1, Exam → 1, FG = 2
    expect($result->finalGrade)->toBe('2');
});

test('threshold: below all steps → grade 0 → fail', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 40.0, 'EXAM' => 40.0],
        mathsScheme()
    );
    expect($result->passed)->toBeFalse()
        ->and($result->finalGrade)->toBe('0');
});

// ─── Pass/Fail scale ─────────────────────────────────────────────────────────

test('pass_fail: 80%+ assignments → P', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(['ASSIGNMENT' => 85.0], databaseScheme());

    expect($result->finalGrade)->toBe('P')
        ->and($result->passed)->toBeTrue()
        ->and($result->finalPercentage)->toBeNull()
        ->and($result->gradeBreakdown['components']['ASSIGNMENT']['converted_grade'])->toBe('P')
        ->and($result->gradeBreakdown['components']['ASSIGNMENT']['requirement_status'])->toBe('passed');
});

test('pass_fail: below 80% assignments → F', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(['ASSIGNMENT' => 70.0], databaseScheme());

    expect($result->finalGrade)->toBe('F')
        ->and($result->passed)->toBeFalse()
        ->and($result->gradeBreakdown['components']['ASSIGNMENT']['converted_grade'])->toBe('F')
        ->and($result->gradeBreakdown['components']['ASSIGNMENT']['requirement_status'])->toBe('failed');
});

// ─── Direct conversion ───────────────────────────────────────────────────────

test('direct: 100% presentation → grade 5', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(['PROJECT_PRESENTATION' => 100.0], projectScheme());

    expect($result->finalGrade)->toBe('5')
        ->and($result->passed)->toBeTrue();
});

test('direct: 60% presentation → grade 3', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(['PROJECT_PRESENTATION' => 60.0], projectScheme());

    expect((int) $result->gradePoints)->toBe(3);
});

// ─── grade_breakdown audit ───────────────────────────────────────────────────

test('grade_breakdown contains engine and component details', function () {
    $calculator = app(MetropoliaV1Calculator::class);
    $result = $calculator->calculate(
        ['ASSIGNMENT' => 50.0, 'EXAM' => 64.0],
        programmingScheme()
    );

    expect($result->gradeBreakdown['engine'])->toBe('metropolia_v1')
        ->and($result->gradeBreakdown['components'])->toHaveKeys(['ASSIGNMENT', 'EXAM'])
        ->and($result->gradeBreakdown['gates_passed'])->toBeTrue();
});
