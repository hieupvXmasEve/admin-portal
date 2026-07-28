<?php

declare(strict_types=1);

use App\Modules\Academic\Delivery\Support\Grading\MetropoliaV2Calculator;

function v2Calculator(): MetropoliaV2Calculator
{
    return app(MetropoliaV2Calculator::class);
}

function cloudScheme(): array
{
    return [
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => '(LAB + QUIZ + EXAM / 2 - 40) / 10',
        'rounding_stage' => 'after_total',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'components' => [
            ['code' => 'LAB', 'label' => 'Labs'],
            ['code' => 'QUIZ', 'label' => 'Quizzes'],
            ['code' => 'EXAM', 'label' => 'Final Exam'],
        ],
    ];
}

it('computes the exact source formula for a balanced profile', function () {
    $result = v2Calculator()->calculate(['LAB' => 30, 'QUIZ' => 30, 'EXAM' => 30], cloudScheme());

    // (30 + 30 + 15 - 40) / 10 = 3.5 → rounds to 4.
    expect($result->gradeBreakdown['fg_raw'])->toEqualWithDelta(3.5, 0.001)
        ->and($result->finalGrade)->toBe('4')
        ->and($result->passed)->toBeTrue();
});

it('matches the source formula on a skewed profile the approximation got wrong', function () {
    // Exam 100, labs/quizzes 0 → (0 + 0 + 50 - 40) / 10 = 1.0 → grade 1.
    // The old affine approximation read this as grade 4.
    $result = v2Calculator()->calculate(['LAB' => 0, 'QUIZ' => 0, 'EXAM' => 100], cloudScheme());

    expect($result->finalGrade)->toBe('1')
        ->and($result->passed)->toBeTrue();
});

it('treats missing component scores as zero', function () {
    $result = v2Calculator()->calculate(['EXAM' => 100], cloudScheme());

    // LAB and QUIZ default to 0 → same as the skewed case above.
    expect($result->finalGrade)->toBe('1');
});

it('clamps results into the configured grade range', function () {
    $high = v2Calculator()->calculate(['LAB' => 100, 'QUIZ' => 100, 'EXAM' => 100], cloudScheme());
    $low = v2Calculator()->calculate(['LAB' => 0, 'QUIZ' => 0, 'EXAM' => 0], cloudScheme());

    expect($high->finalGrade)->toBe('5')
        ->and($low->finalGrade)->toBe('0')
        ->and($low->passed)->toBeFalse();
});

it('computes the health technology offset formula exactly', function () {
    $scheme = [
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => '(ASSIGNMENT - 40) / 10',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'components' => [['code' => 'ASSIGNMENT', 'label' => 'Assignments']],
    ];

    expect(v2Calculator()->calculate(['ASSIGNMENT' => 90], $scheme)->finalGrade)->toBe('5')
        ->and(v2Calculator()->calculate(['ASSIGNMENT' => 50], $scheme)->finalGrade)->toBe('1')
        ->and(v2Calculator()->calculate(['ASSIGNMENT' => 40], $scheme)->passed)->toBeFalse();
});

it('fails the course when a pass requirement gate is not met', function () {
    $scheme = [
        'engine' => 'metropolia_v2',
        'version' => 1,
        'scale' => '0-5',
        'formula' => 'HTML_CSS * 2 / 100 + EXAM * 3 / 100',
        'clamp_min' => 0,
        'clamp_max' => 5,
        'pass_requirements' => [
            ['code' => 'HTML_CSS', 'min_pct' => 40],
            ['code' => 'JS_ASSIGNMENT', 'min_pct' => 40],
        ],
        'components' => [
            ['code' => 'HTML_CSS', 'label' => 'HTML + CSS'],
            ['code' => 'JS_ASSIGNMENT', 'label' => 'JavaScript Assignments'],
            ['code' => 'EXAM', 'label' => 'Exam'],
        ],
    ];

    // Strong grade, but JS assignment gate (40%) unmet → course fails.
    $result = v2Calculator()->calculate(
        ['HTML_CSS' => 100, 'JS_ASSIGNMENT' => 10, 'EXAM' => 100],
        $scheme,
    );

    expect($result->passed)->toBeFalse()
        ->and($result->finalGrade)->toBe('0')
        ->and($result->gradeBreakdown['gate_failures'])->not->toBeEmpty();
});

it('exposes an auditable metropolia_v2 breakdown', function () {
    $breakdown = v2Calculator()->calculate(['LAB' => 30, 'QUIZ' => 30, 'EXAM' => 30], cloudScheme())->gradeBreakdown;

    expect($breakdown['engine'])->toBe('metropolia_v2')
        ->and($breakdown['formula'])->toBe('(LAB + QUIZ + EXAM / 2 - 40) / 10')
        ->and($breakdown)->toHaveKeys(['scale', 'components', 'fg_raw', 'fg_rounded', 'gates_passed', 'gate_failures', 'final_grade']);
});
