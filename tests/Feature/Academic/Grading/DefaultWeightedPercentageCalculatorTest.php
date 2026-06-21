<?php

declare(strict_types=1);

use App\Modules\Academic\Support\Grading\DefaultWeightedPercentageCalculator;

test('passing student gets correct grade and passed=true', function () {
    $calculator = app(DefaultWeightedPercentageCalculator::class);
    $result = $calculator->calculate(['__weighted_average__' => 75.0], null);

    expect($result->finalPercentage)->toBe(75.0)
        ->and($result->passed)->toBeTrue()
        ->and($result->finalGrade)->toBe('B+')
        ->and($result->gradePoints)->toBe(3.0)
        ->and($result->gradeBreakdown['engine'])->toBe('default_weighted_percentage');
});

test('failing student below 60 gets passed=false', function () {
    $calculator = app(DefaultWeightedPercentageCalculator::class);
    $result = $calculator->calculate(['__weighted_average__' => 55.0], null);

    expect($result->passed)->toBeFalse()
        ->and($result->finalGrade)->toBe('C');
});

test('zero score returns failed result', function () {
    $calculator = app(DefaultWeightedPercentageCalculator::class);
    $result = $calculator->calculate(['__weighted_average__' => 0.0], null);

    expect($result->passed)->toBeFalse()
        ->and($result->finalGrade)->toBe('F')
        ->and($result->gradePoints)->toBe(0.0);
});

test('missing weighted average defaults to zero', function () {
    $calculator = app(DefaultWeightedPercentageCalculator::class);
    $result = $calculator->calculate([], null);

    expect($result->finalPercentage)->toBe(0.0)
        ->and($result->passed)->toBeFalse();
});
