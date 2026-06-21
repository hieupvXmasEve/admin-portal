<?php

declare(strict_types=1);

use App\Modules\Academic\Support\Grading\DefaultWeightedPercentageCalculator;
use App\Modules\Academic\Support\Grading\GradingCalculatorResolver;
use App\Modules\Academic\Support\Grading\MetropoliaV1Calculator;
use App\Modules\Academic\Support\Grading\MetropoliaV2Calculator;

test('resolver returns default calculator for null scheme', function () {
    $resolver = app(GradingCalculatorResolver::class);
    $calculator = $resolver->resolve(null);
    expect($calculator)->toBeInstanceOf(DefaultWeightedPercentageCalculator::class);
});

test('resolver returns default calculator for default_weighted_percentage engine', function () {
    $resolver = app(GradingCalculatorResolver::class);
    $calculator = $resolver->resolve(['engine' => 'default_weighted_percentage']);
    expect($calculator)->toBeInstanceOf(DefaultWeightedPercentageCalculator::class);
});

test('resolver returns metropolia calculator for metropolia_v1 engine', function () {
    $resolver = app(GradingCalculatorResolver::class);
    $calculator = $resolver->resolve(['engine' => 'metropolia_v1', 'scale' => '0-5', 'components' => []]);
    expect($calculator)->toBeInstanceOf(MetropoliaV1Calculator::class);
});

test('resolver returns metropolia v2 calculator for metropolia_v2 engine', function () {
    $resolver = app(GradingCalculatorResolver::class);
    $calculator = $resolver->resolve(['engine' => 'metropolia_v2', 'scale' => '0-5', 'formula' => '0', 'components' => []]);
    expect($calculator)->toBeInstanceOf(MetropoliaV2Calculator::class);
});

test('resolver throws for unknown engine', function () {
    $resolver = app(GradingCalculatorResolver::class);
    expect(fn () => $resolver->resolve(['engine' => 'unknown_engine']))
        ->toThrow(InvalidArgumentException::class);
});
