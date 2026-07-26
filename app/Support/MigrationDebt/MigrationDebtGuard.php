<?php

declare(strict_types=1);

namespace App\Support\MigrationDebt;

/**
 * @phpstan-type Finding array{rule: string, path: string, line: int|null, owner: string, runtime_surface: string, message: string}
 * @phpstan-type InventoryReport array{
 *     debt: array{counts: array<string, int>, findings: list<Finding>},
 *     coverage: array{runtime_surfaces: array<string, int>}
 * }
 */
final class MigrationDebtGuard
{
    public function __construct(private readonly MigrationDebtInventory $inventory) {}

    /**
     * @param  InventoryReport|null  $report
     * @return array{passed: bool, errors: list<string>}
     */
    public function evaluate(?array $report = null): array
    {
        $report ??= $this->inventory->scan();
        $errors = [];
        $allowlists = config('migration_debt.allowlists', []);

        if (! is_array($allowlists)) {
            $allowlists = [];
            $errors[] = 'Migration-debt allowlists must be an array.';
        }

        $this->assertExactStringSet(
            'Configured migration-debt rules',
            array_keys($allowlists),
            MigrationDebtContract::RULES,
            $errors,
        );
        $this->assertExactStringSet(
            'Reported migration-debt rules',
            array_keys($report['debt']['counts'] ?? []),
            MigrationDebtContract::RULES,
            $errors,
        );
        $this->assertExactStringSet(
            'Canonical baseline ceiling rules',
            array_keys(MigrationDebtContract::BASELINE_CEILINGS),
            MigrationDebtContract::RULES,
            $errors,
        );
        $this->assertExactStringSet(
            'Configured source roots',
            config('migration_debt.source_roots', []),
            MigrationDebtContract::SOURCE_ROOTS,
            $errors,
        );
        $this->assertExactStringSet(
            'Reported source roots',
            $report['scan_roots'] ?? [],
            MigrationDebtContract::SOURCE_ROOTS,
            $errors,
        );
        $this->assertExactStringSet(
            'Configured runtime surfaces',
            config('migration_debt.runtime_surfaces', []),
            MigrationDebtContract::RUNTIME_SURFACES,
            $errors,
        );
        $this->assertExactStringSet(
            'Configured required runtime surfaces',
            config('migration_debt.required_runtime_surfaces', []),
            MigrationDebtContract::REQUIRED_RUNTIME_SURFACES,
            $errors,
        );

        if (config('migration_debt.owned_shared_models', []) !== MigrationDebtContract::OWNED_SHARED_MODELS) {
            $errors[] = 'Configured shared-model ownership map differs from the canonical ownership contract.';
        }

        if ((int) config('migration_debt.allowlist_entry_baseline') !== count(MigrationDebtContract::RULES)) {
            $errors[] = sprintf(
                'Allowlist entry baseline must remain exactly %d.',
                count(MigrationDebtContract::RULES),
            );
        }

        foreach (MigrationDebtContract::RULES as $rule) {
            $entry = $allowlists[$rule] ?? [];
            foreach (['owner', 'reason', 'canonical_replacement', 'retirement_condition'] as $field) {
                if (! is_string($entry[$field] ?? null) || trim($entry[$field]) === '') {
                    $errors[] = "Allowlist entry '{$rule}' is missing '{$field}'.";
                }
            }

            $current = (int) ($report['debt']['counts'][$rule] ?? 0);
            $baseline = (int) ($entry['baseline'] ?? 0);
            $ceiling = MigrationDebtContract::BASELINE_CEILINGS[$rule];
            $mode = $entry['mode'] ?? 'max';

            if ($baseline !== $ceiling) {
                $errors[] = sprintf(
                    "Debt rule '%s' baseline %d must equal canonical ceiling %d.",
                    $rule,
                    $baseline,
                    $ceiling,
                );
            }

            if (($mode === 'exact' && $current !== $baseline) || ($mode === 'max' && $current > $baseline)) {
                $errors[] = sprintf(
                    "Debt rule '%s' changed from approved baseline %d to %d (%s).",
                    $rule,
                    $baseline,
                    $current,
                    $mode,
                );
            }
        }

        $pathSnapshots = config('migration_debt_paths', []);
        if (! is_array($pathSnapshots)) {
            $pathSnapshots = [];
            $errors[] = 'Migration-debt path snapshots must be an array.';
        }

        $this->assertExactStringSet(
            'Configured path snapshot rules',
            array_keys($pathSnapshots),
            MigrationDebtContract::PATH_SNAPSHOT_RULES,
            $errors,
        );

        foreach (MigrationDebtContract::PATH_SNAPSHOT_RULES as $rule) {
            $approvedPaths = array_values(array_unique($pathSnapshots[$rule] ?? []));
            sort($approvedPaths);
            $currentPaths = [];
            foreach ($report['debt']['findings'] as $finding) {
                if ($finding['rule'] === $rule) {
                    $currentPaths[] = $finding['path'];
                }
            }

            $currentPaths = array_values(array_unique($currentPaths));
            sort($currentPaths);
            $newPaths = array_values(array_diff($currentPaths, $approvedPaths));
            $stalePaths = array_values(array_diff($approvedPaths, $currentPaths));

            foreach ($newPaths as $path) {
                $errors[] = "Debt rule '{$rule}' introduced unapproved path '{$path}'.";
            }

            foreach ($stalePaths as $path) {
                $errors[] = "Debt rule '{$rule}' retains stale approved path '{$path}'.";
            }

            if (count($approvedPaths) !== count($pathSnapshots[$rule] ?? [])) {
                $errors[] = "Debt rule '{$rule}' path snapshot contains duplicate entries.";
            }
        }

        if ((int) ($report['coverage']['source_file_count'] ?? 0) < 1) {
            $errors[] = 'Inventory scanned no source files.';
        }

        foreach (MigrationDebtContract::REQUIRED_RUNTIME_SURFACES as $surface) {
            $count = $report['coverage']['runtime_surfaces'][$surface] ?? null;
            if (! is_int($count) || $count < 1) {
                $errors[] = "Inventory omitted or did not cover required runtime surface '{$surface}'.";
            }
        }

        foreach (config('migration_debt.portal_contracts', []) as $name => $contract) {
            foreach (['owner', 'reason', 'canonical_replacement', 'retirement_condition', 'paths', 'consumer_paths'] as $field) {
                if (! array_key_exists($field, $contract) || $contract[$field] === '' || $contract[$field] === []) {
                    $errors[] = "Portal contract '{$name}' is missing '{$field}'.";
                }
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public function rulePasses(string $rule, int $current): bool
    {
        $entry = config('migration_debt.allowlists.'.$rule, []);
        if (! is_array($entry)) {
            return false;
        }

        $baseline = (int) ($entry['baseline'] ?? 0);
        $mode = $entry['mode'] ?? 'max';

        return ($mode === 'exact' && $current === $baseline)
            || ($mode === 'max' && $current <= $baseline);
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $errors
     */
    private function assertExactStringSet(string $label, mixed $actual, array $expected, array &$errors): void
    {
        if (! is_array($actual) || array_filter($actual, 'is_string') !== $actual) {
            $errors[] = "{$label} must be a list of strings.";

            return;
        }

        $actual = array_values($actual);
        sort($actual);
        sort($expected);

        if ($actual === $expected) {
            return;
        }

        $missing = array_values(array_diff($expected, $actual));
        $unexpected = array_values(array_diff($actual, $expected));
        $errors[] = sprintf(
            '%s changed; missing [%s], unexpected [%s].',
            $label,
            implode(', ', $missing),
            implode(', ', $unexpected),
        );
    }
}
