<?php

declare(strict_types=1);

use App\Models\ScholarshipDefinition;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;

/**
 * FIN-04 / FIN-07: scholarship discount must always be capped at the eligible
 * charge amount, regardless of generation path.
 */
function makeScholarshipDef(string $type, float $amount): ScholarshipDefinition
{
    return new ScholarshipDefinition(['type' => $type, 'amount' => $amount]);
}

it('caps a fixed scholarship at the charge amount', function () {
    $resolver = new ScholarshipDiscountResolver;

    // fixed_amount larger than the charge must not exceed it (would go negative)
    expect($resolver->resolve(makeScholarshipDef('fixed_amount', 20_000_000), 15_000_000))
        ->toBe(15_000_000.0);
});

it('returns the fixed amount when it is below the charge', function () {
    $resolver = new ScholarshipDiscountResolver;

    expect($resolver->resolve(makeScholarshipDef('fixed_amount', 5_000_000), 15_000_000))
        ->toBe(5_000_000.0);
});

it('computes a percentage discount', function () {
    $resolver = new ScholarshipDiscountResolver;

    expect($resolver->resolve(makeScholarshipDef('percentage', 40), 45_000_000))
        ->toBe(18_000_000.0);
});

it('caps a percentage above 100 at the charge amount', function () {
    $resolver = new ScholarshipDiscountResolver;

    expect($resolver->resolve(makeScholarshipDef('percentage', 150), 10_000_000))
        ->toBe(10_000_000.0);
});

it('returns zero for a non-positive base amount', function () {
    $resolver = new ScholarshipDiscountResolver;

    expect($resolver->resolve(makeScholarshipDef('percentage', 40), 0))->toBe(0.0)
        ->and($resolver->resolve(makeScholarshipDef('fixed_amount', 5_000_000), -1))->toBe(0.0);
});
