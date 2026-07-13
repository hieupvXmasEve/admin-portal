<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;

it('exposes all 18 invariants with stable codes and a {scope} token', function () {
    $invariants = app(FinanceInvariantRegistry::class)->all();

    $codes = array_map(fn ($i) => $i->code, $invariants);
    expect($codes)->toBe([
        'INV-1', 'INV-2', 'INV-3', 'INV-4', 'INV-5', 'INV-6', 'INV-7', 'INV-8',
        'INV-9', 'INV-10', 'INV-11', 'INV-12', 'INV-13', 'INV-14', 'INV-15', 'INV-16', 'INV-17', 'INV-18',
    ]);

    foreach ($invariants as $invariant) {
        expect($invariant->countSqlTemplate)->toContain('{scope}')
            ->and($invariant->sampleSqlTemplate)->toContain('{scope}')
            ->and($invariant->studentScopePredicate)->toContain('{ids}')
            ->and(in_array($invariant->severity, ['CRITICAL', 'HIGH'], true))->toBeTrue();
    }
});

it('defines INV-18 as active invoice-line residue on a void charge', function (): void {
    $invariant = app(FinanceInvariantRegistry::class)->find('INV-18');

    expect($invariant)->not->toBeNull()
        ->and($invariant->label)->toContain('Active invoice line on void charge')
        ->and($invariant->countSqlTemplate)->toContain('il.status = "active"')
        ->and($invariant->countSqlTemplate)->toContain('fc.status = "void"');
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

it('defines INV-2 as a completed-cash-only cache parity check', function (): void {
    $invariant = app(FinanceInvariantRegistry::class)->find('INV-2');

    expect($invariant)->not->toBeNull()
        ->and($invariant->label)->toContain('cash-only')
        ->and($invariant->countSqlTemplate)->toContain('LEFT JOIN payments p')
        ->and($invariant->countSqlTemplate)->toContain('p.status = "completed"')
        ->and($invariant->countSqlTemplate)->not->toContain('credit_applications')
        ->and($invariant->sampleSqlTemplate)->toContain('p.status = "completed"');
});

it('retires the old INV-6 invoice-cardinality rule without reusing its stable code', function (): void {
    $invariant = app(FinanceInvariantRegistry::class)->find('INV-6');

    expect($invariant)->not->toBeNull()
        ->and($invariant->label)->toContain('Retired')
        ->and($invariant->countSqlTemplate)->toContain('1 = 0')
        ->and($invariant->countSqlTemplate)->not->toContain('HAVING COUNT(*) > 1');
});

it('defines INV-17 as invoice-to-charge scope integrity', function (): void {
    $invariant = app(FinanceInvariantRegistry::class)->find('INV-17');

    expect($invariant)->not->toBeNull()
        ->and($invariant->label)->toContain('scope mismatch')
        ->and($invariant->countSqlTemplate)->toContain('fc.student_id <> si.student_id')
        ->and($invariant->countSqlTemplate)->toContain('fc.semester_id <=> si.semester_id')
        ->and($invariant->countSqlTemplate)->not->toContain('HAVING COUNT(*) > 1');
});
