<?php

declare(strict_types=1);

namespace App\Support\MigrationDebt;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @phpstan-type Finding array{id: string, rule: string, path: string, line: int|null, owner: string, work_package: string, runtime_surface: string, message: string}
 * @phpstan-type ContractPath array{path: string, kind: string, area: string|null, exists: bool, file_count: int, excluded_from_debt_scan: bool}
 * @phpstan-type InventoryReport array{
 *     schema_version: int,
 *     read_only: bool,
 *     scan_roots: list<string>,
 *     excluded_path_fragments: list<string>,
 *     coverage: array{
 *         source_file_count: int,
 *         runtime_surfaces: array<string, int>,
 *         owning_contexts: array<string, int>,
 *         finding_ownership: array{finding_count: int, assigned_count: int, unassigned_count: int, package_counts: array<string, int>, manifest_sha256: string},
 *         portal_contracts: array<string, array{owner: string, reason: string, canonical_replacement: string, retirement_condition: string, paths: list<ContractPath>, consumer_inventory: array{file_count: int, areas: array{types: int, composables: int, stores: int, pages: int}, runtime_surface: string, owner: string}}},
 *     debt: array{
 *         counts: array<string, int>,
 *         by_owner: array<string, int>,
 *         by_runtime_surface: array<string, int>,
 *         shadow: array{
 *             unique_paths: array<string, int>,
 *             pattern_occurrences: array<string, int>
 *         },
 *         findings: list<Finding>
 *     }
 * }
 */
final class MigrationDebtInventory
{
    /** @var list<string> */
    private const SHADOW_PATTERN_METRICS = [
        'response_json_calls',
        'json_response_constructions',
        'api_response_compatible_calls',
        'request_validate_calls',
        'request_helper_validate_calls',
        'validator_make_calls',
        'http_response_exception_constructions',
        'frontend_application_url_calls',
        'removed_inertia_api_calls',
    ];

    /**
     * Build a deterministic, read-only inventory of source paths and debt findings.
     *
     * @return InventoryReport
     */
    public function scan(): array
    {
        $files = $this->sourceFiles();
        $findings = [];
        $surfaceCounts = array_fill_keys($this->runtimeSurfaces(), 0);
        $ownerCounts = [];
        $shadowPatternOccurrences = array_fill_keys(self::SHADOW_PATTERN_METRICS, 0);

        foreach ($files as $path => $contents) {
            $surface = $this->runtimeSurface($path);
            $owner = $this->owner($path);
            $surfaceCounts[$surface]++;
            $ownerCounts[$owner] = ($ownerCounts[$owner] ?? 0) + 1;

            $this->collectPathFindings($path, $contents, $findings);
            $this->collectPatternFindings($path, $contents, $findings);

            foreach ($this->shadowPatternOccurrences($path, $contents) as $metric => $count) {
                $shadowPatternOccurrences[$metric] += $count;
            }
        }

        $this->collectPageDirectoryFindings($files, $findings);
        $this->sortFindings($findings);
        ksort($surfaceCounts);
        ksort($ownerCounts);

        $findingCounts = array_fill_keys(MigrationDebtContract::RULES, 0);
        $findingsByOwner = [];
        $findingsBySurface = array_fill_keys($this->runtimeSurfaces(), 0);
        $findingPaths = array_fill_keys(MigrationDebtContract::RULES, []);

        foreach ($findings as $finding) {
            $rule = $finding['rule'];
            $findingCounts[$rule] = ($findingCounts[$rule] ?? 0) + 1;
            $findingPaths[$rule][$finding['path']] = true;
            $findingOwner = $finding['owner'];
            $findingsByOwner[$findingOwner] = ($findingsByOwner[$findingOwner] ?? 0) + 1;
            $findingsBySurface[$finding['runtime_surface']]++;
        }

        ksort($findingCounts);
        ksort($findingsByOwner);
        ksort($findingsBySurface);
        ksort($shadowPatternOccurrences);

        $uniquePaths = [];
        foreach ($findingPaths as $rule => $paths) {
            $uniquePaths[$rule] = count($paths);
        }
        ksort($uniquePaths);
        $findingOwnership = $this->findingOwnership($findings);

        return [
            'schema_version' => 1,
            'read_only' => true,
            'scan_roots' => MigrationDebtContract::SOURCE_ROOTS,
            'excluded_path_fragments' => config('migration_debt.excluded_path_fragments', []),
            'coverage' => [
                'source_file_count' => count($files),
                'runtime_surfaces' => $surfaceCounts,
                'owning_contexts' => $ownerCounts,
                'finding_ownership' => $findingOwnership,
                'portal_contracts' => $this->portalContracts(),
            ],
            'debt' => [
                'counts' => $findingCounts,
                'by_owner' => $findingsByOwner,
                'by_runtime_surface' => $findingsBySurface,
                'shadow' => [
                    'unique_paths' => $uniquePaths,
                    'pattern_occurrences' => $shadowPatternOccurrences,
                ],
                'findings' => $findings,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (MigrationDebtContract::SOURCE_ROOTS as $root) {
            $absoluteRoot = base_path($root);

            if (is_file($absoluteRoot)) {
                $relativePath = $this->relativePath($absoluteRoot);
                if ($this->isSupportedFile($relativePath)) {
                    $files[$relativePath] = file_get_contents($absoluteRoot) ?: '';
                }

                continue;
            }

            if (! is_dir($absoluteRoot)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $relativePath = $this->relativePath($file->getPathname());
                if (! $this->isSupportedFile($relativePath)) {
                    continue;
                }

                $files[$relativePath] = file_get_contents($file->getPathname()) ?: '';
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function collectPathFindings(string $path, string $contents, array &$findings): void
    {
        if (str_starts_with($path, 'app/Services/') && str_ends_with($path, '.php')) {
            $this->addFinding($findings, 'frozen_services', $path, 'Existing frozen service path.');
        }

        if (str_starts_with($path, 'app/Http/Controllers/')
            && basename($path) !== 'Controller.php'
            && str_ends_with($path, '.php')) {
            $this->addFinding($findings, 'frozen_controllers', $path, 'Existing frozen top-level controller path.');
        }

        if ((str_starts_with($path, 'routes/web/') || str_starts_with($path, 'routes/api/'))
            && str_ends_with($path, '.php')) {
            $this->addFinding($findings, 'frozen_routes', $path, 'Existing frozen split route path.');
        }

        if (str_starts_with($path, 'app/Modules/') && str_ends_with($path, '.php')) {
            $this->collectSharedModelImports($path, $contents, $findings);
            $this->collectSharedModelReferences($path, $contents, $findings);

            $this->collectCrossContextImports($path, $contents, $findings);
        }

        if (str_starts_with($path, 'app/Console/Commands/') && $this->isMigrationCommand($contents)) {
            $this->addFinding($findings, 'migration_commands', $path, 'Migration/backfill/rebuild/reconcile command remains registered.');
        }
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function collectPatternFindings(string $path, string $contents, array &$findings): void
    {
        if (str_ends_with($path, '.php')
            && (str_starts_with($path, 'app/Http/Controllers/') || str_starts_with($path, 'app/Modules/'))
            && preg_match('/response\s*\(\s*\)\s*->\s*json\s*\(/', $contents) === 1) {
            $this->addFinding($findings, 'direct_json_responses', $path, 'Direct JSON response candidate.');
        }

        if (str_ends_with($path, '.php')
            && (str_starts_with($path, 'app/') || str_starts_with($path, 'routes/'))
            && preg_match('/\$request\s*->\s*validate\s*\(/', $contents) === 1) {
            $this->addFinding($findings, 'inline_request_validation', $path, 'Inline request validation candidate.');
        }

        if (str_ends_with($path, '.php')
            && str_starts_with($path, 'app/')
            && preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $contents) !== 1) {
            $this->addFinding($findings, 'missing_strict_types', $path, 'Application PHP file is missing strict types.');
        }

        if (str_ends_with($path, '.php')
            && str_starts_with($path, 'routes/')
            && preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $contents) !== 1) {
            $this->addFinding($findings, 'missing_route_strict_types', $path, 'Application route PHP file is missing strict types.');
        }

        if (str_starts_with($path, 'resources/js/pages/')
            && preg_match('/use(?:InertiaFilters|ServerTableQuery|Filters|TableFilters)/', $contents) === 1) {
            $this->addFinding($findings, 'legacy_filter_stacks', $path, 'Page uses a transitional filter composable.');
        }

        if (str_starts_with($path, 'resources/js/')
            && preg_match('/(?:router\.(?:visit|post|put|patch|delete)|fetch)\s*\(\s*[`\'\"]\/(?:api\/)?[a-z]/i', $contents) === 1) {
            $this->addFinding($findings, 'literal_frontend_urls', $path, 'Frontend navigation/API call contains a literal application path.');
        }

        if (preg_match('/Inertia::lazy\s*\(|\$page\.props\.flash|<Deferred\b[^>]*:data\s*=\s*(?:\{|\[)/i', $contents) === 1) {
            $this->addFinding($findings, 'removed_inertia_apis', $path, 'Removed Inertia v2 API pattern.');
        }
    }

    /**
     * Report broader pattern occurrence counts without changing guarded debt semantics.
     *
     * @return array<string, int>
     */
    public function shadowPatternOccurrences(string $path, string $contents): array
    {
        $counts = array_fill_keys(self::SHADOW_PATTERN_METRICS, 0);

        if (str_ends_with($path, '.php')) {
            $counts['response_json_calls'] = $this->countMatchesOutsideComments(
                $contents,
                '/response\s*\(\s*\)\s*->\s*json\s*\(/',
            );
            $counts['json_response_constructions'] = $this->countMatchesOutsideComments(
                $contents,
                '/new\s+(?:\\\\?Illuminate\\\\Http\\\\)?JsonResponse\s*\(/',
            );
            $counts['api_response_compatible_calls'] = $this->countMatchesOutsideComments(
                $contents,
                '/ApiResponse::compatible\s*\(/',
            );
            $counts['request_validate_calls'] = $this->countMatchesOutsideComments(
                $contents,
                '/\$request\s*->\s*validate\s*\(/',
            );
            $counts['request_helper_validate_calls'] = $this->countMatchesOutsideComments(
                $contents,
                '/request\s*\(\s*\)\s*->\s*validate\s*\(/',
            );
            $counts['validator_make_calls'] = $this->countMatchesOutsideComments(
                $contents,
                '/(?:\\\\?Illuminate\\\\Support\\\\Facades\\\\)?Validator::make\s*\(/',
            );
            $counts['http_response_exception_constructions'] = $this->countMatchesOutsideComments(
                $contents,
                '/new\s+(?:\\\\?Illuminate\\\\Http\\\\Exceptions\\\\)?HttpResponseException\s*\(/',
            );
        }

        if (str_starts_with($path, 'resources/js/')) {
            $counts['frontend_application_url_calls'] = $this->countMatchesOutsideComments(
                $contents,
                '/(?:router\.(?:visit|post|put|patch|delete)|fetch|(?:api|http|client)\.(?:get|post|put|patch|delete))\s*\(\s*[`\'"]\/(?!data\/)(?:api\/)?[a-z]/i',
            );
        }

        $counts['removed_inertia_api_calls'] = $this->countMatchesOutsideComments(
            $contents,
            '/Inertia::lazy\s*\(|router\.cancel\s*\(|\$page\.props\.flash|<Deferred\b[^>]*:data\s*=\s*(?:\{|\[)/i',
        );

        return $counts;
    }

    /**
     * @param  array<string, string>  $files
     * @param  list<Finding>  $findings
     */
    private function collectPageDirectoryFindings(array $files, array &$findings): void
    {
        $directories = [];

        foreach (array_keys($files) as $path) {
            if (! str_starts_with($path, 'resources/js/pages/')) {
                continue;
            }

            $relative = substr($path, strlen('resources/js/pages/'));
            $directory = explode('/', $relative)[0] ?? '';
            if ($directory !== '' && ! str_contains($directory, '.')) {
                $directories[$directory] = true;
            }
        }

        foreach (array_keys($directories) as $directory) {
            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $directory) === 1) {
                continue;
            }

            $this->addFinding(
                $findings,
                'legacy_page_directories',
                'resources/js/pages/'.$directory,
                'Page directory is not PascalCase.',
            );
        }
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function collectCrossContextImports(string $path, string $contents, array &$findings): void
    {
        if (preg_match('/^namespace\s+App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/m', $contents, $namespace) !== 1) {
            return;
        }

        if (preg_match_all('/App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/m', $contents, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return;
        }

        $reportedLines = [];
        foreach ($matches[1] as $match) {
            if ($match[0] === $namespace[1]) {
                continue;
            }

            $line = $this->lineAtOffset($contents, (int) $match[1]);
            $trimmedLine = ltrim($line['contents']);
            if (str_starts_with($trimmedLine, '*')
                || str_starts_with($trimmedLine, '//')
                || str_starts_with($trimmedLine, '#')
                || str_starts_with($trimmedLine, '/*')) {
                continue;
            }

            if (isset($reportedLines[$line['number']])) {
                continue;
            }

            $reportedLines[$line['number']] = true;

            $this->addFinding(
                $findings,
                'cross_context_concrete_imports',
                $path,
                'Module imports concrete code from another top-level module.',
                $line['number'],
            );
        }
    }

    /**
     * Count fully qualified shared-model references that are not import
     * statements. This keeps the inventory aligned with the parent audit's
     * import/reference definition without counting documentation examples.
     *
     * @param  list<Finding>  $findings
     */
    private function collectSharedModelReferences(string $path, string $contents, array &$findings): void
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
            $trimmed = ltrim($line);
            if (! str_contains($line, 'App\\Models\\')
                || str_starts_with($trimmed, 'use ')
                || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '#')) {
                continue;
            }

            $this->addFinding(
                $findings,
                'shared_model_imports',
                $path,
                'Module still references a shared Eloquent model.',
                $index + 1,
            );
        }
    }

    /** @param list<Finding> $findings */
    private function collectSharedModelImports(string $path, string $contents, array &$findings): void
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
            if (preg_match('/^use\s+App\\\\Models\\\\([^;]+);/', $line, $match) !== 1
                || $this->isOwnedSharedModel($path, $match[1])) {
                continue;
            }

            $this->addFinding(
                $findings,
                'shared_model_imports',
                $path,
                'Module still imports a shared Eloquent model.',
                $index + 1,
            );
        }
    }

    private function isOwnedSharedModel(string $path, string $model): bool
    {
        if (preg_match('#^app/Modules/([^/]+)/#', $path, $match) !== 1) {
            return false;
        }

        $module = $match[1];
        $nestedOwner = null;

        if ($module === 'Academic'
            && preg_match('#^app/Modules/Academic/([A-Za-z0-9_]+)/#', $path, $nestedMatch) === 1) {
            $nestedOwner = $nestedMatch[1];
        }

        if ($nestedOwner !== null) {
            return in_array(
                $model,
                config("migration_debt.owned_shared_models.{$module}.{$nestedOwner}", []),
                true,
            );
        }

        return in_array($model, config("migration_debt.owned_shared_models.{$module}", []), true);
    }

    private function isMigrationCommand(string $contents): bool
    {
        if (preg_match(
            '/(?:protected\s+\$signature\s*=\s*[\'\"]([^\'\"]+)|#\[Signature\(\s*[\'\"]([^\'\"]+))/s',
            $contents,
            $signature,
        ) !== 1) {
            return false;
        }

        return preg_match('/backfill|migrate|rebuild|reconcile/i', $signature[1] ?: ($signature[2] ?? '')) === 1;
    }

    private function runtimeSurface(string $path): string
    {
        if (str_starts_with($path, 'routes/api/v1/student.php')
            || str_starts_with($path, 'routes/api/v1/lecturer.php')
            || $path === 'app/Modules/Identity/routes/api.php') {
            return 'portal';
        }

        if (str_contains($path, '/Mcp/') || $path === 'routes/ai.php' || $path === 'config/mcp.php') {
            return 'mcp';
        }

        if ($path === 'routes/channels.php') {
            return 'broadcasts';
        }

        if ($path === 'routes/console.php') {
            return 'scheduler';
        }

        if (str_starts_with($path, 'app/Console/Commands/')) {
            return 'commands';
        }

        if (str_contains($path, '/Jobs/') || str_starts_with($path, 'app/Jobs/')) {
            return 'queue';
        }

        if (str_contains(strtolower($path), 'webhook')) {
            return 'webhooks';
        }

        if (str_contains($path, '/Events/') || str_starts_with($path, 'app/Events/')) {
            return 'broadcasts';
        }

        if (str_contains($path, '/Imports/')
            || str_contains($path, '/Exports/')
            || preg_match('/(?:Import|Export)/', basename($path)) === 1) {
            return 'imports_exports';
        }

        if (str_contains(strtolower($path), 'report')) {
            return 'reports';
        }

        if (str_starts_with($path, 'routes/api.php')
            || str_starts_with($path, 'routes/api/')
            || str_contains($path, '/Http/Api/')
            || str_contains($path, '/Http/Controllers/Api/')
            || (str_contains($path, '/routes/') && basename($path) === 'api.php')) {
            return 'api';
        }

        if (str_starts_with($path, 'routes/web.php')
            || str_starts_with($path, 'routes/web/')
            || $path === 'routes/web.php'
            || str_contains($path, '/Http/Web/')
            || str_contains($path, '/Http/Controllers/Web/')
            || (str_contains($path, '/routes/') && basename($path) === 'web.php')) {
            return 'http';
        }

        if (str_starts_with($path, 'resources/js/')) {
            return 'frontend';
        }

        return 'other';
    }

    private function owner(string $path): string
    {
        if (preg_match('/^app\/Modules\/([A-Za-z0-9_]+)\//', $path, $matches) === 1) {
            return $matches[1];
        }

        if (str_starts_with($path, 'routes/api/v1/student.php') || str_starts_with($path, 'docs/api/student')) {
            return 'Student Portal';
        }

        if (str_starts_with($path, 'routes/api/v1/lecturer.php') || str_starts_with($path, 'docs/api/lecturer')) {
            return 'Lecturer Portal';
        }

        foreach (['Academic', 'Admissions', 'Finance', 'Identity', 'Institution', 'Notification', 'StudentRegistry', 'AI', 'Facilities'] as $context) {
            if (str_contains($path, '/'.$context.'/') || str_contains($path, '/'.$context)) {
                return $context;
            }
        }

        if (preg_match('~^resources/js/pages/([^/]+)~', $path, $matches) === 1) {
            return $matches[1];
        }

        return 'Platform / shared legacy';
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function addFinding(
        array &$findings,
        string $rule,
        string $path,
        string $message,
        ?int $line = null,
    ): void {
        $owner = $this->owner($path);
        $findings[] = [
            'id' => hash('sha256', implode('|', [$rule, $path, (string) ($line ?? 0)])),
            'rule' => $rule,
            'path' => $path,
            'line' => $line,
            'owner' => $owner,
            'work_package' => $this->workPackage($rule, $path, $owner),
            'runtime_surface' => $this->runtimeSurface($path),
            'message' => $message,
        ];
    }

    /**
     * @param  list<Finding>  $findings
     * @return array{finding_count: int, assigned_count: int, unassigned_count: int, package_counts: array<string, int>, manifest_sha256: string}
     */
    private function findingOwnership(array $findings): array
    {
        $packageCounts = [];
        $manifest = [];

        foreach ($findings as $finding) {
            $package = $finding['work_package'];
            $packageCounts[$package] = ($packageCounts[$package] ?? 0) + 1;
            $manifest[] = [
                'id' => $finding['id'],
                'work_package' => $package,
            ];
        }

        ksort($packageCounts);

        return [
            'finding_count' => count($findings),
            'assigned_count' => count($manifest),
            'unassigned_count' => 0,
            'package_counts' => $packageCounts,
            'manifest_sha256' => hash(
                'sha256',
                json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ),
        ];
    }

    private function workPackage(string $rule, string $path, string $owner): string
    {
        $phase = match ($rule) {
            'cross_context_concrete_imports', 'removed_inertia_apis' => 1,
            'legacy_page_directories' => 2,
            'shared_model_imports' => $this->domainPhase($path, $owner),
            'direct_json_responses', 'inline_request_validation' => $this->domainPhase($path, $owner),
            'legacy_filter_stacks', 'literal_frontend_urls' => 8,
            'frozen_services', 'frozen_controllers', 'frozen_routes' => $this->frozenShellPhase($path),
            'missing_strict_types', 'missing_route_strict_types' => 9,
            'migration_commands' => 10,
            default => 9,
        };
        $ownerSlug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($owner)));
        $ownerSlug = trim($ownerSlug, '-');

        return sprintf('phase-%02d:%s:%s', $phase, $ownerSlug, $rule);
    }

    /**
     * A frozen shell belongs to the phase that owns its workflow, not to the
     * residue sweep. Phase 9 only deletes dead or already-replaced code, so a
     * shell still serving a live workflow has to route to the owner that will
     * actually cut it over. Anything without a live owner falls through to 9.
     */
    private function frozenShellPhase(string $path): int
    {
        // Student and Lecturer portal APIs: live portal contracts, cut over in phase 5.
        if (str_starts_with($path, 'app/Http/Controllers/Api/V1/Student/')
            || str_starts_with($path, 'app/Services/V1/Student/')
            || $path === 'routes/api/v1/student.php'
            || $path === 'routes/api/v1/lecturer.php') {
            return 5;
        }

        // Scholarship, tuition plan, voucher, and financial import: live money workflows,
        // cut over with the rest of Finance in phase 6.
        $financeShells = [
            'app/Http/Controllers/ScholarshipController.php',
            'app/Http/Controllers/StudentScholarshipController.php',
            'app/Http/Controllers/StudentFinancialImportController.php',
            'app/Http/Controllers/TuitionPlanController.php',
            'app/Http/Controllers/VoucherController.php',
            'app/Services/ScholarshipService.php',
            'app/Services/StudentFinancialImportService.php',
            'app/Services/TuitionPlanService.php',
            'app/Services/VoucherService.php',
            'routes/web/scholarships.php',
            'routes/web/student-scholarships.php',
            'routes/web/tuition-plans.php',
            'routes/web/vouchers.php',
        ];

        if (in_array($path, $financeShells, true)) {
            return 6;
        }

        // Email, notification, gold, and wallet surfaces belong to the operational
        // domains migrated in phase 7.
        $operationalShells = [
            'app/Http/Controllers/EmailLogController.php',
            'app/Http/Controllers/Api/GoldTransactionController.php',
            'app/Http/Controllers/Api/NotificationController.php',
            'app/Http/Controllers/Api/StudentWalletController.php',
            'app/Http/Controllers/Api/V1/Admin/EmailConfigurationController.php',
            'app/Http/Controllers/Api/V1/Admin/EmailController.php',
            'app/Http/Controllers/Web/Admin/NotificationController.php',
            'app/Http/Controllers/Web/ApplicationGuardianController.php',
            'app/Http/Controllers/Web/EmailConfigurationController.php',
            'app/Services/EmailCredentialEncryptionService.php',
            'app/Services/EmailLoggingService.php',
            'app/Services/EmailService.php',
            'app/Services/GoldService.php',
            'app/Services/NotificationService.php',
            'app/Services/UserEmailPreferenceService.php',
            'routes/web/notifications.php',
            'routes/api/admin/notification.php',
        ];

        if (in_array($path, $operationalShells, true)) {
            return 7;
        }

        if (str_starts_with($path, 'app/Services/Admissions/')) {
            return 7;
        }

        return 9;
    }

    private function domainPhase(string $path, string $owner): int
    {
        return match ($owner) {
            'Identity', 'Institution', 'StudentRegistry' => 3,
            'Academic' => match (true) {
                str_contains($path, '/Progression/') => 5,
                str_contains($path, '/Reporting/'),
                str_contains($path, '/Report') => 8,
                default => 4,
            },
            'Finance' => 6,
            'AI' => 8,
            default => 7,
        };
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function collectMatches(
        array &$findings,
        string $rule,
        string $path,
        string $contents,
        string $pattern,
        string $message,
    ): void {
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return;
        }

        foreach ($matches[0] as $match) {
            $this->addFinding($findings, $rule, $path, $message, $this->lineNumber($contents, (int) $match[1]));
        }
    }

    private function lineNumber(string $contents, int $offset): int
    {
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }

    private function countMatches(string $contents, string $pattern): int
    {
        return preg_match_all($pattern, $contents) ?: 0;
    }

    private function countMatchesOutsideComments(string $contents, string $pattern): int
    {
        return $this->countMatches($this->stripComments($contents), $pattern);
    }

    private function stripComments(string $contents): string
    {
        $result = '';
        $quote = null;
        $escaped = false;
        $insideLineComment = false;
        $insideBlockComment = false;
        $length = strlen($contents);

        for ($index = 0; $index < $length; $index++) {
            $character = $contents[$index];
            $next = $contents[$index + 1] ?? '';

            if ($insideLineComment) {
                if ($character === "\n") {
                    $insideLineComment = false;
                    $result .= $character;
                }

                continue;
            }

            if ($insideBlockComment) {
                if ($character === '*' && $next === '/') {
                    $insideBlockComment = false;
                    $index++;
                } elseif ($character === "\n") {
                    $result .= $character;
                }

                continue;
            }

            if ($quote !== null) {
                $result .= $character;

                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if (in_array($character, ['\'', '"', '`'], true)) {
                $quote = $character;
                $result .= $character;

                continue;
            }

            if ($character === '/' && $next === '/') {
                $insideLineComment = true;
                $index++;

                continue;
            }

            if ($character === '/' && $next === '*') {
                $insideBlockComment = true;
                $index++;

                continue;
            }

            if ($character === '#' && $next !== '[') {
                $insideLineComment = true;

                continue;
            }

            $result .= $character;
        }

        return $result;
    }

    /**
     * @return array{number: int, contents: string}
     */
    private function lineAtOffset(string $contents, int $offset): array
    {
        $number = $this->lineNumber($contents, $offset);
        $lines = preg_split('/\R/', $contents) ?: [];

        return [
            'number' => $number,
            'contents' => $lines[$number - 1] ?? '',
        ];
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function sortFindings(array &$findings): void
    {
        usort($findings, static function (array $left, array $right): int {
            return [$left['rule'], $left['path'], $left['line'] ?? 0]
                <=> [$right['rule'], $right['path'], $right['line'] ?? 0];
        });
    }

    /**
     * @return array<string, array{owner: string, reason: string, canonical_replacement: string, retirement_condition: string, paths: list<ContractPath>}>
     */
    private function portalContracts(): array
    {
        $contracts = [];
        foreach (config('migration_debt.portal_contracts', []) as $name => $contract) {
            $paths = [];
            $consumerAreaCounts = array_fill_keys(['types', 'composables', 'stores', 'pages'], 0);
            $consumerFileCount = 0;
            foreach ($contract['paths'] as $path) {
                $absolutePath = base_path($path);
                $paths[] = [
                    'path' => $path,
                    'kind' => 'backend_contract',
                    'area' => null,
                    'exists' => file_exists($absolutePath),
                    'file_count' => $this->pathFileCount($absolutePath),
                    'excluded_from_debt_scan' => false,
                ];
            }

            foreach ($contract['consumer_paths'] as $path) {
                $absolutePath = base_path($path);
                $fileCount = $this->pathFileCount($absolutePath);
                $area = $this->portalConsumerArea($path);
                $consumerFileCount += $fileCount;
                if (array_key_exists($area, $consumerAreaCounts)) {
                    $consumerAreaCounts[$area] += $fileCount;
                }

                $paths[] = [
                    'path' => $path,
                    'kind' => 'portal_consumer',
                    'area' => $area,
                    'exists' => file_exists($absolutePath),
                    'file_count' => $fileCount,
                    'excluded_from_debt_scan' => true,
                ];
            }

            $contracts[$name] = [
                'owner' => $contract['owner'],
                'reason' => $contract['reason'],
                'canonical_replacement' => $contract['canonical_replacement'],
                'retirement_condition' => $contract['retirement_condition'],
                'paths' => $paths,
                'consumer_inventory' => [
                    'file_count' => $consumerFileCount,
                    'areas' => $consumerAreaCounts,
                    'runtime_surface' => 'portal',
                    'owner' => $contract['owner'],
                ],
            ];
        }

        ksort($contracts);

        return $contracts;
    }

    private function pathFileCount(string $absolutePath): int
    {
        return is_dir($absolutePath) ? count($this->filesUnder($absolutePath)) : (is_file($absolutePath) ? 1 : 0);
    }

    private function portalConsumerArea(string $path): string
    {
        return match (true) {
            str_contains($path, '/shared/types') => 'types',
            str_contains($path, '/app/composables') => 'composables',
            str_contains($path, '/app/stores') => 'stores',
            str_contains($path, '/app/pages') => 'pages',
            default => 'other',
        };
    }

    /**
     * @return array<int, string>
     */
    private function filesUnder(string $absoluteRoot): array
    {
        $files = [];
        if (! is_dir($absoluteRoot)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $absolutePath): string
    {
        $basePath = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if (str_starts_with($normalizedPath, $basePath)) {
            return substr($normalizedPath, strlen($basePath));
        }

        return ltrim($normalizedPath, '/');
    }

    private function isSupportedFile(string $relativePath): bool
    {
        foreach (config('migration_debt.excluded_path_fragments', []) as $fragment) {
            if (str_contains('/'.$relativePath, $fragment)) {
                return false;
            }
        }

        return preg_match('/\.(php|vue|ts)$/', $relativePath) === 1;
    }

    /**
     * @return list<string>
     */
    private function runtimeSurfaces(): array
    {
        return MigrationDebtContract::RUNTIME_SURFACES;
    }
}
