<?php

declare(strict_types=1);

use App\Models\ScholarshipDefinition;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;

function adjustmentResolverDefinition(string $type, float $amount): ScholarshipDefinition
{
    return new ScholarshipDefinition(['type' => $type, 'amount' => $amount]);
}

function adjustmentResolverAdjustment(string $originalType, float $adjusted): ScholarshipSemesterAdjustment
{
    return new ScholarshipSemesterAdjustment([
        'original_type' => $originalType,
        'adjusted_amount' => $adjusted,
    ]);
}

it('passes through to resolve when no adjustment exists', function () {
    $resolver = new ScholarshipDiscountResolver;
    $definition = adjustmentResolverDefinition('percentage', 30);

    expect($resolver->resolveAdjusted($definition, 20_000_000, null))->toBe(6_000_000.0);
});

it('computes a reduced percentage adjustment', function () {
    $resolver = new ScholarshipDiscountResolver;
    $definition = adjustmentResolverDefinition('percentage', 30);
    $adjustment = adjustmentResolverAdjustment('percentage', 15);

    expect($resolver->resolveAdjusted($definition, 20_000_000, $adjustment))->toBe(3_000_000.0);
});

it('computes a reduced fixed_amount adjustment as a flat value', function () {
    $resolver = new ScholarshipDiscountResolver;
    $definition = adjustmentResolverDefinition('fixed_amount', 10_000_000);
    $adjustment = adjustmentResolverAdjustment('fixed_amount', 4_000_000);

    expect($resolver->resolveAdjusted($definition, 20_000_000, $adjustment))->toBe(4_000_000.0);
});

it('returns zero for a full suspension', function () {
    $resolver = new ScholarshipDiscountResolver;
    $definition = adjustmentResolverDefinition('percentage', 30);
    $adjustment = adjustmentResolverAdjustment('percentage', 0);

    expect($resolver->resolveAdjusted($definition, 20_000_000, $adjustment))->toBe(0.0);
});

it('never exceeds the unadjusted resolution even if the snapshot is larger', function () {
    $resolver = new ScholarshipDiscountResolver;
    // Definition now grants 10%, snapshot claims 50% — unadjusted resolution wins as cap.
    $definition = adjustmentResolverDefinition('percentage', 10);
    $adjustment = adjustmentResolverAdjustment('percentage', 50);

    expect($resolver->resolveAdjusted($definition, 20_000_000, $adjustment))->toBe(2_000_000.0);
});

it('clamps to the base amount and floors at zero', function () {
    $resolver = new ScholarshipDiscountResolver;
    $definition = adjustmentResolverDefinition('fixed_amount', 50_000_000);
    $adjustment = adjustmentResolverAdjustment('fixed_amount', 30_000_000);

    expect($resolver->resolveAdjusted($definition, 15_000_000, $adjustment))->toBe(15_000_000.0)
        ->and($resolver->resolveAdjusted($definition, 0, $adjustment))->toBe(0.0)
        ->and($resolver->resolveAdjusted($definition, -5, $adjustment))->toBe(0.0);
});
