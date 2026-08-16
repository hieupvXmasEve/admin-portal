<?php

declare(strict_types=1);

use App\Support\MigrationDebt\MigrationDebtContract;
use App\Support\MigrationDebt\MigrationDebtGuard;
use App\Support\MigrationDebt\MigrationDebtInventory;

it('builds a deterministic read-only inventory across supported runtime surfaces', function (): void {
    $inventory = app(MigrationDebtInventory::class);
    $report = $inventory->scan();

    expect($report)->toEqual($inventory->scan())
        ->and($report['read_only'])->toBeTrue()
        ->and($report['coverage']['source_file_count'])->toBeGreaterThan(0)
        ->and($report['scan_roots'])->toBe(MigrationDebtContract::SOURCE_ROOTS)
        ->and(array_keys($report['debt']['counts']))->toEqualCanonicalizing(MigrationDebtContract::RULES)
        ->and(array_keys($report['coverage']['runtime_surfaces']))->toEqualCanonicalizing(MigrationDebtContract::RUNTIME_SURFACES)
        ->and($report['coverage']['finding_ownership']['finding_count'])->toBe(count($report['debt']['findings']))
        ->and($report['coverage']['finding_ownership']['assigned_count'])->toBe(count($report['debt']['findings']))
        ->and($report['coverage']['finding_ownership']['unassigned_count'])->toBe(0)
        ->and($report['coverage']['finding_ownership']['manifest_sha256'])->toHaveLength(64)
        ->and(collect($report['debt']['findings'])->pluck('id')->unique()->count())->toBe(count($report['debt']['findings']))
        ->and(collect($report['debt']['findings'])->every(
            fn (array $finding): bool => $finding['id'] !== '' && $finding['work_package'] !== '',
        ))->toBeTrue()
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

it('passes the approved migration-debt baseline and exact path snapshots', function (): void {
    $result = app(MigrationDebtGuard::class)->evaluate();

    expect($result['passed'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

it('keeps the canonical rule roots surfaces and ownership contract immutable', function (): void {
    expect(array_keys(config('migration_debt.allowlists')))->toEqualCanonicalizing(MigrationDebtContract::RULES)
        ->and(config('migration_debt.source_roots'))->toBe(MigrationDebtContract::SOURCE_ROOTS)
        ->and(config('migration_debt.runtime_surfaces'))->toBe(MigrationDebtContract::RUNTIME_SURFACES)
        ->and(config('migration_debt.required_runtime_surfaces'))->toBe(MigrationDebtContract::REQUIRED_RUNTIME_SURFACES)
        ->and(config('migration_debt.owned_shared_models'))->toBe(MigrationDebtContract::OWNED_SHARED_MODELS)
        ->and(array_keys(MigrationDebtContract::BASELINE_CEILINGS))->toEqualCanonicalizing(MigrationDebtContract::RULES)
        ->and(config('migration_debt.allowlist_entry_baseline'))->toBe(count(MigrationDebtContract::RULES));

    foreach (config('migration_debt.allowlists') as $rule => $entry) {
        expect($entry['baseline'])->toBe(MigrationDebtContract::BASELINE_CEILINGS[$rule]);
    }
});

it('reports broader pattern occurrences without changing guarded file counts', function (): void {
    $inventory = app(MigrationDebtInventory::class);

    $phpOccurrences = $inventory->shadowPatternOccurrences('app/Modules/Example/Http/Api/ExampleController.php', <<<'PHP'
<?php
response()->json([]);
new JsonResponse([]);
ApiResponse::compatible([]);
$request->validate([]);
request()->validate([]);
Validator::make([], []);
throw new HttpResponseException(response());
performWork(); // response()->json([]);
performMoreWork(); /* Validator::make([], []); */
PHP);

    $frontendOccurrences = $inventory->shadowPatternOccurrences('resources/js/example.ts', <<<'TS'
// fetch('/api/commented')
fetch('/data/provinces.json')
api.get('/api/users')
router.visit('/settings')
// router.cancel()
TS);

    expect($phpOccurrences)
        ->toMatchArray([
            'response_json_calls' => 1,
            'json_response_constructions' => 1,
            'api_response_compatible_calls' => 1,
            'request_validate_calls' => 1,
            'request_helper_validate_calls' => 1,
            'validator_make_calls' => 1,
            'http_response_exception_constructions' => 1,
        ])
        ->and($frontendOccurrences['frontend_application_url_calls'])->toBe(2)
        ->and($frontendOccurrences['removed_inertia_api_calls'])->toBe(0);
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

it('rejects configured baseline drift in either direction', function (): void {
    $baseline = MigrationDebtContract::BASELINE_CEILINGS['shared_model_imports'];
    config()->set('migration_debt.allowlists.shared_model_imports.baseline', $baseline + 1);

    $increased = app(MigrationDebtGuard::class)->evaluate();

    config()->set('migration_debt.allowlists.shared_model_imports.baseline', $baseline - 1);

    $decreasedWithoutPairedCeiling = app(MigrationDebtGuard::class)->evaluate();

    expect($increased['passed'])->toBeFalse()
        ->and($increased['errors'])->toContain(
            "Debt rule 'shared_model_imports' baseline ".($baseline + 1)." must equal canonical ceiling {$baseline}.",
        )
        ->and($decreasedWithoutPairedCeiling['passed'])->toBeFalse()
        ->and($decreasedWithoutPairedCeiling['errors'])->toContain(
            "Debt rule 'shared_model_imports' baseline ".($baseline - 1)." must equal canonical ceiling {$baseline}.",
        );
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

it('rejects stale approved paths after a frozen path is removed', function (): void {
    $approvedPaths = config('migration_debt_paths.frozen_services');
    $approvedPaths[] = 'app/Services/RemovedService.php';
    config()->set('migration_debt_paths.frozen_services', $approvedPaths);

    $result = app(MigrationDebtGuard::class)->evaluate();

    expect($result['passed'])->toBeFalse()
        ->and($result['errors'])->toContain("Debt rule 'frozen_services' retains stale approved path 'app/Services/RemovedService.php'.");
});

it('rejects removal of a canonical rule or source root', function (): void {
    $allowlists = config('migration_debt.allowlists');
    unset($allowlists['direct_json_responses']);
    config()->set('migration_debt.allowlists', $allowlists);
    config()->set('migration_debt.source_roots', ['app', 'routes']);

    $result = app(MigrationDebtGuard::class)->evaluate();

    expect($result['passed'])->toBeFalse()
        ->and($result['errors'])->toContain(
            'Configured migration-debt rules changed; missing [direct_json_responses], unexpected [].',
        )
        ->and($result['errors'])->toContain(
            'Configured source roots changed; missing [config/mcp.php, resources/js], unexpected [].',
        );
});

it('rejects an unreviewed shared-model ownership exemption', function (): void {
    $ownership = config('migration_debt.owned_shared_models');
    $ownership['Academic']['Delivery'][] = 'Lecture';
    config()->set('migration_debt.owned_shared_models', $ownership);

    $result = app(MigrationDebtGuard::class)->evaluate();

    expect($result['passed'])->toBeFalse()
        ->and($result['errors'])->toContain(
            'Configured shared-model ownership map differs from the canonical ownership contract.',
        );
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
