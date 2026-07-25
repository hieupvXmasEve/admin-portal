<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Modules\Academic\Progression\Queries\GetAcademicProgressionReconciliationQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class AuditAcademicProgressionReconciliationCommand extends Command
{
    protected $signature = 'academic:audit-progression-reconciliation
        {--student-id= : Restrict to one Student primary key}
        {--semester-id= : Restrict to one Semester primary key}
        {--all : Explicitly inspect every supported historical outcome}
        {--baseline-database= : Compare current findings with a read-only baseline database on the same connection}
        {--strict : Return failure when an exception or supported consumer exists}
        {--format=table : Output format: table or json}';

    protected $description = 'Read-only reconciliation of historical Academic Progression evidence and compatibility consumers.';

    public function __construct(private readonly GetAcademicProgressionReconciliationQuery $reconciliation)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $format = $this->formatOption();
            $scope = [
                'student_id' => $this->positiveIdOption('student-id'),
                'semester_id' => $this->positiveIdOption('semester-id'),
                'all' => (bool) $this->option('all'),
            ];
            if ($scope['all'] && ($scope['student_id'] !== null || $scope['semester_id'] !== null)) {
                throw new InvalidArgumentException('--all cannot be combined with --student-id or --semester-id.');
            }
            $report = $this->reconciliation->handle($scope);
            $baselineDatabase = $this->baselineDatabaseOption();
            if ($baselineDatabase !== null) {
                $currentSnapshot = $this->snapshotData();
                $baselineBundle = $this->baselineBundle($baselineDatabase, $scope);
                $report['baseline_comparison'] = $this->compareReports($report, $baselineBundle['report'], $baselineDatabase);
                $report['baseline_comparison']['data_changes'] = $this->compareDataSnapshots($currentSnapshot, $baselineBundle['snapshot']);
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($format === 'json') {
            try {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->error("Unable to encode reconciliation report: {$exception->getMessage()}");

                return self::FAILURE;
            }
        } else {
            $this->warn('READ-ONLY: no academic, lifecycle, EGC, Finance, queue, notification, or compatibility data was modified.');
            $this->table(
                ['Metric', 'Count'],
                collect($report['counts'])->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
            );
            $this->line('Reconciliation passed: '.($report['reconciliation_passed'] ? 'yes' : 'no'));
            $this->line('Retirement eligible: '.($report['retirement_eligible'] ? 'yes' : 'no'));
            if (isset($report['baseline_comparison'])) {
                $comparison = $report['baseline_comparison'];
                $this->line('Baseline regression-free: '.($comparison['regression_free'] ? 'yes' : 'no'));
                $this->line('Baseline introduced exceptions: '.$comparison['introduced_exception_count']);
                $this->line('Baseline changed exceptions: '.$comparison['changed_exception_count']);
                $this->line('Baseline resolved exceptions: '.$comparison['resolved_exception_count']);
                $this->line('Baseline pre-existing exceptions: '.$comparison['pre_existing_exception_count']);
                foreach ($comparison['data_changes'] as $table => $changes) {
                    $this->line(sprintf(
                        'Baseline data changes [%s]: added=%d removed=%d updated=%d',
                        $table,
                        $changes['current_only_count'],
                        $changes['baseline_only_count'],
                        $changes['updated_count'],
                    ));
                }
            }
        }

        if ($this->option('strict') && (! $report['reconciliation_passed'] || ! $report['retirement_eligible'])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function positiveIdOption(string $option): ?int
    {
        $value = $this->option($option);
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException("--{$option} must be a positive integer.");
        }

        return (int) $value;
    }

    private function formatOption(): string
    {
        $format = $this->option('format');
        if (! is_string($format) || ! in_array($format, ['table', 'json'], true)) {
            throw new InvalidArgumentException('--format must be table or json.');
        }

        return $format;
    }

    private function baselineDatabaseOption(): ?string
    {
        $value = $this->option('baseline-database');
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
            throw new InvalidArgumentException('--baseline-database must be a database identifier containing only letters, numbers, and underscores.');
        }
        if ($value === (string) config('database.connections.'.config('database.default').'.database')) {
            throw new InvalidArgumentException('--baseline-database must differ from the current database.');
        }

        return $value;
    }

    /** @param array{student_id: ?int, semester_id: ?int, all: bool} $scope @return array{report: array<string, mixed>, snapshot: array<string, mixed>} */
    private function baselineBundle(string $database, array $scope): array
    {
        try {
            return $this->runAgainstDatabase($database, fn (): array => [
                'report' => $this->reconciliation->handle($scope),
                'snapshot' => $this->snapshotData(),
            ]);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("Unable to read baseline database '{$database}': {$exception->getMessage()}", previous: $exception);
        }
    }

    /** @return array<string, mixed> */
    private function runAgainstDatabase(string $database, callable $callback): array
    {
        $connection = (string) config('database.default');
        $configKey = 'database.connections.'.$connection.'.database';
        $originalDatabase = (string) config($configKey);

        DB::disconnect($connection);
        config([$configKey => $database]);
        DB::purge($connection);

        try {
            return $callback();
        } finally {
            DB::disconnect($connection);
            config([$configKey => $originalDatabase]);
            DB::purge($connection);
        }
    }

    /** @return array<string, mixed> */
    private function compareReports(array $current, array $baseline, string $database): array
    {
        $currentExceptions = $this->exceptionMap($current['exceptions'] ?? []);
        $baselineExceptions = $this->exceptionMap($baseline['exceptions'] ?? []);
        $preExisting = array_intersect_key($currentExceptions, $baselineExceptions);
        $introduced = array_diff_key($currentExceptions, $baselineExceptions);
        $resolved = array_diff_key($baselineExceptions, $currentExceptions);
        $changed = $this->pairChangedExceptions($introduced, $resolved);

        return [
            'baseline_database' => $database,
            'baseline_counts' => $baseline['counts'] ?? [],
            'baseline_exception_count' => count($baseline['exceptions'] ?? []),
            'current_exception_count' => count($current['exceptions'] ?? []),
            'pre_existing_exception_count' => count($preExisting),
            'introduced_exception_count' => count($introduced),
            'changed_exception_count' => count($changed),
            'resolved_exception_count' => count($resolved),
            'regression_free' => $introduced === [] && $changed === [],
            'introduced_exceptions' => array_values($introduced),
            'changed_exceptions' => array_values($changed),
            'resolved_exceptions' => array_values($resolved),
        ];
    }

    /** @param list<array<string, mixed>> $exceptions @return array<string, array<string, mixed>> */
    private function exceptionMap(array $exceptions): array
    {
        $map = [];
        foreach ($exceptions as $exception) {
            $fingerprint = serialize([
                $exception['invariant_code'] ?? null,
                $exception['stable_key'] ?? null,
                $exception['field'] ?? null,
                $exception['expected'] ?? null,
                $exception['actual'] ?? null,
                $exception['reason'] ?? null,
            ]);
            $map[$fingerprint] = $exception;
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $introduced
     * @param  array<string, array<string, mixed>>  $resolved
     * @return list<array{baseline: array<string, mixed>, current: array<string, mixed>}>
     */
    private function pairChangedExceptions(array &$introduced, array &$resolved): array
    {
        $introducedByKey = $this->groupExceptionsByComparisonKey($introduced);
        $resolvedByKey = $this->groupExceptionsByComparisonKey($resolved);
        $changed = [];

        foreach (array_intersect(array_keys($introducedByKey), array_keys($resolvedByKey)) as $comparisonKey) {
            $currentKeys = array_keys($introducedByKey[$comparisonKey]);
            $baselineKeys = array_keys($resolvedByKey[$comparisonKey]);
            $pairCount = min(count($currentKeys), count($baselineKeys));

            for ($index = 0; $index < $pairCount; $index++) {
                $currentKey = $currentKeys[$index];
                $baselineKey = $baselineKeys[$index];
                $changed[] = [
                    'baseline' => $resolved[$baselineKey],
                    'current' => $introduced[$currentKey],
                ];
                unset($introduced[$currentKey], $resolved[$baselineKey]);
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, array<string, mixed>>  $exceptions
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function groupExceptionsByComparisonKey(array $exceptions): array
    {
        $groups = [];
        foreach ($exceptions as $fingerprint => $exception) {
            $comparisonKey = serialize([
                $exception['invariant_code'] ?? null,
                $exception['stable_key'] ?? null,
                $exception['reason'] ?? null,
            ]);
            $groups[$comparisonKey][$fingerprint] = $exception;
        }

        return $groups;
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function snapshotData(): array
    {
        $tables = [
            'academic_records',
            'transcript_entries',
            'gpa_calculations',
            'student_action_logs',
            'student_decisions',
            'student_decision_student',
            'egc_blocks',
        ];
        $snapshot = [];

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(function (object $row): array {
                $values = (array) $row;
                ksort($values);

                return $values;
            });
            $snapshot[$table] = $rows->keyBy(function (array $row): string {
                if (array_key_exists('id', $row)) {
                    return (string) $row['id'];
                }

                return sha1(serialize($row));
            })->all();
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function compareDataSnapshots(array $current, array $baseline): array
    {
        $comparison = [];

        foreach ($current as $table => $currentRows) {
            $baselineRows = $baseline[$table] ?? [];
            $currentOnly = array_diff_key($currentRows, $baselineRows);
            $baselineOnly = array_diff_key($baselineRows, $currentRows);
            $updated = [];
            $fieldChanges = [];

            foreach (array_intersect_key($currentRows, $baselineRows) as $key => $currentRow) {
                $baselineRow = $baselineRows[$key];
                if ($currentRow === $baselineRow) {
                    continue;
                }
                $changes = [];
                foreach (array_unique([...array_keys($baselineRow), ...array_keys($currentRow)]) as $field) {
                    $before = $baselineRow[$field] ?? null;
                    $after = $currentRow[$field] ?? null;
                    if ($before === $after) {
                        continue;
                    }
                    $changes[$field] = ['before' => $before, 'after' => $after];
                    $fieldChanges[$field] = ($fieldChanges[$field] ?? 0) + 1;
                }
                $updated[$key] = ['key' => $key, 'changes' => $changes];
            }

            $comparison[$table] = [
                'current_only_count' => count($currentOnly),
                'baseline_only_count' => count($baselineOnly),
                'updated_count' => count($updated),
                'field_changes' => $fieldChanges,
                'current_only_keys' => array_keys($currentOnly),
                'baseline_only_keys' => array_keys($baselineOnly),
                'updated_samples' => array_values(array_slice($updated, 0, 25)),
            ];
        }

        return $comparison;
    }
}
