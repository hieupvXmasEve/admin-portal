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

        if (count($allowlists) > (int) config('migration_debt.allowlist_entry_baseline')) {
            $errors[] = sprintf(
                'Approved allowlist grew from %d to %d entries.',
                (int) config('migration_debt.allowlist_entry_baseline'),
                count($allowlists),
            );
        }

        foreach ($allowlists as $rule => $entry) {
            foreach (['owner', 'reason', 'canonical_replacement', 'retirement_condition'] as $field) {
                if (! is_string($entry[$field] ?? null) || trim($entry[$field]) === '') {
                    $errors[] = "Allowlist entry '{$rule}' is missing '{$field}'.";
                }
            }

            $current = (int) ($report['debt']['counts'][$rule] ?? 0);
            $baseline = (int) ($entry['baseline'] ?? 0);
            $mode = $entry['mode'] ?? 'max';

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

        foreach (config('migration_debt_paths', []) as $rule => $approvedPaths) {
            $currentPaths = [];
            foreach ($report['debt']['findings'] as $finding) {
                if ($finding['rule'] === $rule) {
                    $currentPaths[] = $finding['path'];
                }
            }

            $currentPaths = array_values(array_unique($currentPaths));
            sort($currentPaths);
            $newPaths = array_values(array_diff($currentPaths, $approvedPaths));

            foreach ($newPaths as $path) {
                $errors[] = "Debt rule '{$rule}' introduced unapproved path '{$path}'.";
            }
        }

        foreach ($this->requiredRuntimeSurfaces() as $surface) {
            if (! array_key_exists($surface, $report['coverage']['runtime_surfaces'] ?? [])) {
                $errors[] = "Inventory omitted required runtime surface '{$surface}'.";
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

    /**
     * @return list<string>
     */
    private function requiredRuntimeSurfaces(): array
    {
        return config('migration_debt.required_runtime_surfaces', []);
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
}
