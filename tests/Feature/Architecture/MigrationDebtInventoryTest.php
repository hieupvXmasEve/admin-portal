<?php

declare(strict_types=1);

use App\Support\MigrationDebt\MigrationDebtGuard;
use App\Support\MigrationDebt\MigrationDebtInventory;

it('builds a deterministic read-only inventory across supported runtime surfaces', function (): void {
    $inventory = app(MigrationDebtInventory::class);
    $report = $inventory->scan();

    expect($report)->toEqual($inventory->scan())
        ->and($report['read_only'])->toBeTrue()
        ->and($report['coverage']['source_file_count'])->toBeGreaterThan(0)
        ->and(array_keys($report['coverage']['runtime_surfaces']))->toEqualCanonicalizing(config('migration_debt.runtime_surfaces'))
        ->and($report['coverage']['portal_contracts'])->toHaveKeys(['student', 'lecturer'])
        ->and(collect($report['coverage']['portal_contracts']['student']['paths'])->where('kind', 'portal_consumer')->count())->toBe(4)
        ->and(collect($report['coverage']['portal_contracts']['lecturer']['paths'])->where('kind', 'portal_consumer')->count())->toBe(4)
        ->and(collect($report['coverage']['portal_contracts']['student']['paths'])->where('kind', 'portal_consumer')->every(fn (array $path): bool => $path['excluded_from_debt_scan']))->toBeTrue()
        ->and($report['coverage']['portal_contracts']['student']['consumer_inventory']['areas'])->toHaveKeys(['types', 'composables', 'stores', 'pages'])
        ->and($report['coverage']['portal_contracts']['lecturer']['consumer_inventory']['areas'])->toHaveKeys(['types', 'composables', 'stores', 'pages'])
        ->and($report['coverage']['portal_contracts']['student']['consumer_inventory']['runtime_surface'])->toBe('portal')
        ->and($report['coverage']['portal_contracts']['lecturer']['consumer_inventory']['runtime_surface'])->toBe('portal')
        ->and($report['debt']['findings'])->not->toContain(fn (array $finding): bool => str_contains($finding['path'], 'vendor'))
        ->and($report['debt']['findings'])->not->toContain(fn (array $finding): bool => str_contains($finding['path'], 'FE/'));
});

it('passes the approved migration-debt baseline after the finance baseline is restored', function (): void {
    $result = app(MigrationDebtGuard::class)->evaluate();

    expect($result['passed'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

it('requires ownership, replacement, reason, and retirement metadata for every allowlist entry', function (): void {
    foreach (config('migration_debt.allowlists') as $entry) {
        expect($entry['owner'])->toBeString()->not->toBeEmpty()
            ->and($entry['reason'])->toBeString()->not->toBeEmpty()
            ->and($entry['canonical_replacement'])->toBeString()->not->toBeEmpty()
            ->and($entry['retirement_condition'])->toBeString()->not->toBeEmpty();
    }
});

it('rejects a simulated increase in an approved debt baseline', function (): void {
    $inventory = app(MigrationDebtInventory::class);
    $report = $inventory->scan();
    $baseline = (int) config('migration_debt.allowlists.frozen_services.baseline');
    $report['debt']['counts']['frozen_services'] = $baseline + 1;

    $result = app(MigrationDebtGuard::class)->evaluate($report);

    expect($result['passed'])->toBeFalse()
        ->and($result['errors'])->toContain("Debt rule 'frozen_services' changed from approved baseline {$baseline} to ".($baseline + 1).' (max).');
});

it('rejects an unapproved frozen path even when its aggregate count is unchanged', function (): void {
    $inventory = app(MigrationDebtInventory::class);
    $report = $inventory->scan();
    $report['debt']['findings'][] = [
        'rule' => 'frozen_services',
        'path' => 'app/Services/NewService.php',
        'line' => null,
        'owner' => 'Platform / shared legacy',
        'runtime_surface' => 'other',
        'message' => 'Simulated frozen service path.',
    ];

    $result = app(MigrationDebtGuard::class)->evaluate($report);

    expect($result['passed'])->toBeFalse()
        ->and($result['errors'])->toContain("Debt rule 'frozen_services' introduced unapproved path 'app/Services/NewService.php'.");
});

it('rejects a simulated concrete cross-context import', function (): void {
    $inventory = app(MigrationDebtInventory::class);
    $report = $inventory->scan();
    $report['debt']['counts']['cross_context_concrete_imports'] = 1;

    $result = app(MigrationDebtGuard::class)->evaluate($report);

    expect($result['passed'])->toBeFalse()
        ->and($result['errors'])->toContain("Debt rule 'cross_context_concrete_imports' changed from approved baseline 0 to 1 (exact).");
});

it('exposes the inventory through an artisan check command', function (): void {
    $this->artisan('migration-debt:inventory', ['--check' => true, '--format' => 'json'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"read_only": true');
});
