<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Services\PermissionService;
use App\Shared\Contracts\Academic\AcademicPeriodReader;

class QueryPlanValidator
{
    public function __construct(
        private readonly MetricCatalog $catalog,
        private readonly PermissionService $permissionService,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    public function validate(
        QueryPlan $plan,
        User $actor,
        ?Campus $campus = null,
        ?CampusScopeSnapshot $campusScope = null,
    ): QueryPlanValidationResult {
        // Campus may be supplied explicitly by the caller; web-chat continues to rely on
        // the container-bound `campus` resolved by SetCampus middleware when none is passed.
        $campus ??= app()->bound('campus') ? app('campus') : null;
        $campusId = $campus?->id;
        // A pre-resolved MCP scope (possibly multi-campus) wins; otherwise fall back to the
        // single-campus snapshot derived from the resolved campus (web-chat shape unchanged).
        $scope = $campusScope ?? CampusScopeSnapshot::single($campusId);
        $campusScopeSnapshot = $scope->toArray();

        if ($plan->hasSchemaViolations()) {
            return $this->deny(
                plan: $plan,
                permissionResult: 'not_evaluated',
                campusScopeSnapshot: $campusScopeSnapshot,
                safeErrorCode: 'invalid_query_plan_schema',
                messages: $plan->schemaViolations(),
            );
        }

        $metric = $this->catalog->metric($plan->metric());

        if ($metric === null) {
            return $this->deny($plan, 'not_evaluated', $campusScopeSnapshot, 'unsupported_metric');
        }

        $permission = (string) ($metric['required_permission'] ?? 'view_ai_metrics');
        $domainPermission = isset($metric['required_domain_permission'])
            ? (string) $metric['required_domain_permission']
            : null;
        $permissions = $this->permissionService->getUserPermissions($actor, $campusId);

        if (! in_array($permission, $permissions, true)) {
            return $this->deny($plan, 'denied', $campusScopeSnapshot, 'forbidden_by_permission', $metric, [
                "missing_permission:{$permission}",
            ]);
        }

        if (
            $domainPermission !== null
            && $domainPermission !== ''
            && $domainPermission !== $permission
            && ! in_array($domainPermission, $permissions, true)
        ) {
            return $this->deny($plan, 'denied', $campusScopeSnapshot, 'forbidden_by_domain_permission', $metric, [
                "missing_permission:{$domainPermission}",
            ]);
        }

        $campusFilter = $plan->filters()['campus_id'] ?? null;

        // A campus filter must fall within the resolved scope: the single resolved campus for
        // web-chat, or any campus in the permitted span for an MCP all-campus holder.
        if ($campusFilter !== null && ! in_array((int) $campusFilter, $scope->campusIds(), true)) {
            return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'forbidden_by_campus_scope', $metric);
        }

        $allowedFilters = array_keys($metric['allowed_filters'] ?? []);

        foreach (array_keys($plan->filters()) as $filter) {
            if (! in_array($filter, $allowedFilters, true)) {
                return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'unsupported_filter', $metric, [$filter]);
            }
        }

        $allowedGroupBy = $metric['allowed_group_by'] ?? [];

        foreach ($plan->groupBy() as $groupBy) {
            if (! in_array($groupBy, $allowedGroupBy, true)) {
                return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'unsupported_group_by', $metric, [$groupBy]);
            }
        }

        $options = $plan->options();

        if (($options['include_rows'] ?? false) !== false) {
            return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'unsupported_query_plan_option', $metric, ['include_rows']);
        }

        $maxRecordLimit = (int) ($metric['max_record_limit'] ?? 100);
        $limit = isset($options['limit']) ? (int) $options['limit'] : $maxRecordLimit;

        if ($limit > $maxRecordLimit || $limit < 1) {
            return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'max_record_limit_exceeded', $metric);
        }

        $normalizedFilters = $this->normalizeFilters($plan, $metric);

        if (($normalizedFilters['__error'] ?? null) === 'missing_required_filter') {
            return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'missing_required_filter', $metric);
        }

        if (($normalizedFilters['__error'] ?? null) === 'invalid_filter_value') {
            return $this->deny($plan, 'allowed', $campusScopeSnapshot, 'invalid_filter_value', $metric);
        }

        return new QueryPlanValidationResult(
            allowed: true,
            catalogVersion: $this->catalog->version(),
            toolSchemaVersion: $this->catalog->toolSchemaVersion(),
            metric: $plan->metric(),
            normalizedFilters: $normalizedFilters,
            groupBy: $plan->groupBy(),
            permissionResult: 'allowed',
            campusScopeSnapshot: $campusScopeSnapshot,
            hiddenSections: $metric['hidden_sections'] ?? [],
            maxRecordLimit: $maxRecordLimit,
            estimatedRecordCount: min($limit, $maxRecordLimit),
            sourceReport: (string) $metric['source_report'],
            sourceReferencePolicy: (string) $metric['source_reference_policy'],
            safeErrorCode: null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metric
     * @param  list<string>  $messages
     */
    private function deny(
        QueryPlan $plan,
        string $permissionResult,
        array $campusScopeSnapshot,
        string $safeErrorCode,
        ?array $metric = null,
        array $messages = [],
    ): QueryPlanValidationResult {
        return new QueryPlanValidationResult(
            allowed: false,
            catalogVersion: $this->catalog->version(),
            toolSchemaVersion: $this->catalog->toolSchemaVersion(),
            metric: $plan->metric() !== '' ? $plan->metric() : null,
            normalizedFilters: [],
            groupBy: $plan->groupBy(),
            permissionResult: $permissionResult,
            campusScopeSnapshot: $campusScopeSnapshot,
            hiddenSections: $metric['hidden_sections'] ?? [],
            maxRecordLimit: (int) ($metric['max_record_limit'] ?? 0),
            estimatedRecordCount: 0,
            sourceReport: isset($metric['source_report']) ? (string) $metric['source_report'] : null,
            sourceReferencePolicy: isset($metric['source_reference_policy']) ? (string) $metric['source_reference_policy'] : null,
            safeErrorCode: $safeErrorCode,
            messages: $messages,
        );
    }

    /**
     * @param  array<string, mixed>  $metric
     * @return array<string, mixed>
     */
    private function normalizeFilters(QueryPlan $plan, array $metric): array
    {
        $normalized = [];
        $filters = $plan->filters();
        $definitions = $metric['allowed_filters'] ?? [];

        foreach ($definitions as $filter => $definition) {
            if (($definition['required'] ?? false) === true && ! array_key_exists($filter, $filters)) {
                return ['__error' => 'missing_required_filter'];
            }
        }

        foreach ($filters as $filter => $value) {
            if ($filter === 'campus_id') {
                continue;
            }

            $type = (string) ($definitions[$filter]['type'] ?? 'string');

            if ($type === 'semester') {
                $semester = $this->resolveSemester($value);

                if (! $semester) {
                    return ['__error' => 'invalid_filter_value'];
                }

                $normalized['semester_id'] = $semester->id;

                continue;
            }

            if ($type === 'integer') {
                if (! is_numeric($value)) {
                    return ['__error' => 'invalid_filter_value'];
                }

                $normalized[$filter] = (int) $value;

                continue;
            }

            $normalized[$filter] = (string) $value;
        }

        return $normalized;
    }

    private function resolveSemester(mixed $value): ?Semester
    {
        if ($value === 'current') {
            $currentPeriodId = $this->academicPeriods->current()?->id;

            return $currentPeriodId === null ? null : Semester::query()->find($currentPeriodId);
        }

        if (is_numeric($value)) {
            return Semester::query()->find((int) $value);
        }

        return null;
    }
}
