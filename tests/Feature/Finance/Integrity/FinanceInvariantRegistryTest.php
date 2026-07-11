<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;

it('exposes all 16 invariants with stable codes and a {scope} token', function () {
    $invariants = app(FinanceInvariantRegistry::class)->all();

    $codes = array_map(fn ($i) => $i->code, $invariants);
    expect($codes)->toBe([
        'INV-1', 'INV-2', 'INV-3', 'INV-4', 'INV-5', 'INV-6', 'INV-7', 'INV-8',
        'INV-9', 'INV-10', 'INV-11', 'INV-12', 'INV-13', 'INV-14', 'INV-15', 'INV-16',
    ]);

    foreach ($invariants as $invariant) {
        expect($invariant->countSqlTemplate)->toContain('{scope}')
            ->and($invariant->sampleSqlTemplate)->toContain('{scope}')
            ->and($invariant->studentScopePredicate)->toContain('{ids}')
            ->and(in_array($invariant->severity, ['CRITICAL', 'HIGH'], true))->toBeTrue();
    }
});

it('finds an invariant by code and returns null for an unknown code', function () {
    $registry = app(FinanceInvariantRegistry::class);

    expect($registry->find('INV-1'))->not->toBeNull()
        ->and($registry->find('INV-1')->code)->toBe('INV-1')
        ->and($registry->find('INV-999'))->toBeNull();
});

it('includes credit applications in the negative-balance invariant', function (): void {
    $invariant = app(FinanceInvariantRegistry::class)->find('INV-8');

    expect($invariant)->not->toBeNull()
        ->and($invariant->label)->toContain('credit')
        ->and($invariant->countSqlTemplate)->toContain('credit_applications')
        ->and($invariant->sampleSqlTemplate)->toContain('credit_applications');
});
