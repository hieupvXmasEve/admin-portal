<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\QueryPlan;
use App\Modules\AI\Support\QueryPlanValidator;
use App\Modules\AI\Support\Tools\Metrics\MetricResolverRegistry;
use Throwable;

class QueryMetricsTool
{
    public const NAME = 'query_metrics';

    public function __construct(
        private readonly QueryPlanValidator $validator,
        private readonly MetricCatalog $catalog,
        private readonly MetricResolverRegistry $resolvers,
        private readonly AiAuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments, QueryMetricsExecutionContext $context): QueryMetricsResult
    {
        $started = microtime(true);
        $plan = QueryPlan::fromArray($arguments);
        $validation = $this->validator->validate($plan, $context->actor, $context->campus, $context->campusScope);
        $validationData = $validation->toArray();

        if (! $validation->allowed()) {
            $result = QueryMetricsResult::denied(self::NAME, $validationData);
            $this->recordAudit($context, $arguments, $result, $validationData, $started);

            return $result;
        }

        $metricKey = (string) $validationData['metric'];
        $metric = $this->catalog->metric($metricKey);
        $resolver = $this->resolvers->resolverFor($metricKey);

        if ($metric === null || $resolver === null) {
            $result = QueryMetricsResult::failed(self::NAME, $validationData, 'metric_resolver_missing');
            $this->recordAudit($context, $arguments, $result, $validationData, $started);

            return $result;
        }

        try {
            $payload = $resolver->resolve($validationData, $metric, $context);
            $result = QueryMetricsResult::completed(self::NAME, $validationData, $payload);
        } catch (Throwable) {
            $result = QueryMetricsResult::failed(self::NAME, $validationData, 'source_query_failed');
        }

        $this->recordAudit($context, $arguments, $result, $validationData, $started);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $validationData
     */
    private function recordAudit(
        QueryMetricsExecutionContext $context,
        array $arguments,
        QueryMetricsResult $result,
        array $validationData,
        float $started,
    ): void {
        if ($context->trace === null) {
            return;
        }

        $this->auditRecorder->recordToolCall($context->trace, [
            'tool_name' => self::NAME,
            'tool_schema_version' => $validationData['tool_schema_version'] ?? $this->catalog->toolSchemaVersion(),
            'arguments' => $arguments,
            'permission_result' => $result->permissionResult(),
            'campus_scope_snapshot' => $validationData['campus_scope_snapshot'] ?? [],
            'hidden_sections' => $validationData['hidden_sections'] ?? [],
            'record_count' => $result->recordCount(),
            'source_references' => $result->sourceReferences(),
            'result_summary' => $result->auditSummary(),
            'status' => $result->status(),
            'duration_ms' => max(0, (int) round((microtime(true) - $started) * 1000)),
            'safe_error_code' => $result->safeErrorCode(),
        ]);
    }
}
