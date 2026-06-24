<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Agents\LiveStaffCopilotFinalAnswerAgent;
use App\Modules\AI\Agents\LiveStaffCopilotPlannerAgent;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Support\Tools\EntityProfileResult;
use App\Modules\AI\Support\Tools\EntitySearchResult;
use App\Modules\AI\Support\Tools\QueryMetricsResult;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Modules\AI\Support\Tools\ToolRegistry;
use Illuminate\Support\Arr;
use Laravel\Ai\Ai;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

class LiveStaffCopilotAgent
{
    public const PROMPT_VERSION = 'staff-copilot-live:v1';

    private const MAX_TOOL_CALLS = 3;

    public function __construct(
        private readonly LaravelAiProviderResolver $providerResolver,
        private readonly MetricCatalog $metricCatalog,
        private readonly EntityCatalog $entityCatalog,
        private readonly StudentProfileSectionCatalog $studentProfileSectionCatalog,
        private readonly ToolRegistry $toolRegistry,
        private readonly ToolDispatcher $toolDispatcher,
        private readonly AiAuditRecorder $auditRecorder,
        private readonly AiProviderUsageRecorder $usageRecorder,
    ) {}

    public function run(string $question, User $actor, ?Campus $campus, AiAgentTrace $trace): ?StaffCopilotAnswer
    {
        $setting = $this->eligibleProviderSetting($actor);

        if ($setting === null) {
            return null;
        }

        try {
            $resolvedProvider = $this->providerResolver->resolve($setting);
        } catch (Throwable) {
            return null;
        }

        $this->prepareSdkProvider($setting, $resolvedProvider);
        $this->markLiveTrace($trace, $resolvedProvider);

        try {
            $plannerResponse = $this->promptPlanner($question, $actor, $campus, $resolvedProvider);
            $this->recordUsage($trace, $plannerResponse, $resolvedProvider, 'succeeded');

            $plannerOutput = $this->responseToArray($plannerResponse);
            $toolResults = $this->executePlannerOutput($plannerOutput, $actor, $campus, $trace);

            if ($toolResults === []) {
                return $this->answerWithoutTools($plannerOutput);
            }

            $finalResponse = $this->promptFinalAnswer($question, $toolResults, $resolvedProvider);
            $this->recordUsage($trace, $finalResponse, $resolvedProvider, 'succeeded');

            $setting->forceFill(['last_used_at' => now()])->save();

            return StaffCopilotAnswer::fromLiveFinalAnswer(
                finalAnswer: $this->responseToArray($finalResponse),
                toolResults: $toolResults,
            );
        } catch (Throwable) {
            $trace->forceFill([
                'safe_error_code' => 'provider_invocation_failed',
            ])->save();

            $this->usageRecorder->record($trace, [
                'provider' => $resolvedProvider['provider'],
                'model' => $resolvedProvider['model'],
                'status' => 'failed',
                'safe_error_code' => 'provider_invocation_failed',
            ]);

            return null;
        }
    }

    private function eligibleProviderSetting(User $actor): ?AiProviderSetting
    {
        $setting = AiProviderSetting::query()
            ->where('user_id', $actor->id)
            ->where('enabled', true)
            ->first();

        if (! $setting instanceof AiProviderSetting || ! $setting->hasApiKey()) {
            return null;
        }

        if ($setting->last_test_status !== 'success') {
            return null;
        }

        return $setting;
    }

    /**
     * @param  array<string, mixed>  $resolvedProvider
     */
    private function markLiveTrace(AiAgentTrace $trace, array $resolvedProvider): void
    {
        $trace->forceFill([
            'provider' => (string) $resolvedProvider['provider'],
            'model' => (string) $resolvedProvider['model'],
            'prompt_version' => self::PROMPT_VERSION,
            'catalog_version' => $this->metricCatalog->version().'|'.$this->entityCatalog->version().'|'.$this->studentProfileSectionCatalog->version(),
            'tool_schema_version' => $this->metricCatalog->toolSchemaVersion().'|'.$this->entityCatalog->toolSchemaVersion().'|'.$this->studentProfileSectionCatalog->toolSchemaVersion(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $resolvedProvider
     */
    private function prepareSdkProvider(AiProviderSetting $setting, array $resolvedProvider): void
    {
        $sdkLab = (string) $resolvedProvider['sdk_lab'];

        config([
            "ai.providers.{$sdkLab}.key" => (string) $setting->encrypted_api_key,
        ]);

        Ai::purge($sdkLab);
    }

    /**
     * @param  array<string, mixed>  $resolvedProvider
     */
    private function promptPlanner(string $question, User $actor, ?Campus $campus, array $resolvedProvider): AgentResponse
    {
        return LiveStaffCopilotPlannerAgent::make(
            instructions: $this->plannerInstructions($actor, $campus),
        )->prompt(
            prompt: $this->plannerPrompt($question),
            provider: $this->sdkProvider($resolvedProvider),
            model: (string) $resolvedProvider['model'],
            timeout: (int) $resolvedProvider['timeout_seconds'],
        );
    }

    /**
     * @param  list<QueryMetricsResult|EntitySearchResult|EntityProfileResult>  $toolResults
     * @param  array<string, mixed>  $resolvedProvider
     */
    private function promptFinalAnswer(string $question, array $toolResults, array $resolvedProvider): AgentResponse
    {
        return LiveStaffCopilotFinalAnswerAgent::make(
            instructions: $this->finalAnswerInstructions(),
        )->prompt(
            prompt: $this->finalAnswerPrompt($question, $toolResults),
            provider: $this->sdkProvider($resolvedProvider),
            model: (string) $resolvedProvider['model'],
            timeout: (int) $resolvedProvider['timeout_seconds'],
        );
    }

    /**
     * @param  array<string, mixed>  $resolvedProvider
     */
    private function sdkProvider(array $resolvedProvider): Lab|string
    {
        return Lab::tryFrom((string) $resolvedProvider['sdk_lab']) ?? (string) $resolvedProvider['sdk_lab'];
    }

    private function plannerInstructions(User $actor, ?Campus $campus): string
    {
        return implode("\n", [
            'You are the live Staff Copilot planner for Swinx.',
            'Guardrails: use only the supplied tool catalog; never request SQL, tables, columns, hidden fields, raw rows, credentials, source models, or cross-campus scope.',
            'Return structured output only. Use action tool_calls, ask_clarification, or unsupported.',
            'Current actor context: '.json_encode([
                'user_id' => $actor->id,
                'role' => 'staff',
                'campus' => $campus ? ['id' => $campus->id, 'code' => $campus->code] : null,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'MetricCatalog: '.$this->catalogSummary(),
            'EntityCatalog: '.$this->entityCatalogSummary(),
            'StudentProfileSectionCatalog: '.$this->studentProfileSectionSummary(),
            'Available tools: '.$this->toolSummary(),
        ]);
    }

    private function plannerPrompt(string $question): string
    {
        return "Staff question:\n{$question}\n\nPlan with allowlisted tools only.";
    }

    private function finalAnswerInstructions(): string
    {
        return implode("\n", [
            'You are the live Staff Copilot answer synthesizer for Swinx.',
            'Use only the redacted tool results supplied in the prompt.',
            'Every metric or entity fact must cite a source_report from the tool results.',
            'Do not expose hidden sections, credentials, SQL, raw rows, or internal exception details.',
            'Return structured output only.',
        ]);
    }

    /**
     * @param  list<QueryMetricsResult|EntitySearchResult|EntityProfileResult>  $toolResults
     */
    private function finalAnswerPrompt(string $question, array $toolResults): string
    {
        return 'Staff question: '.$question."\n\nRedacted tool results:\n".json_encode(
            array_map(fn (QueryMetricsResult|EntitySearchResult|EntityProfileResult $result): array => $result->toArray(), $toolResults),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param  array<string, mixed>  $plannerOutput
     * @return list<QueryMetricsResult|EntitySearchResult|EntityProfileResult>
     */
    private function executePlannerOutput(array $plannerOutput, User $actor, ?Campus $campus, AiAgentTrace $trace): array
    {
        if (($plannerOutput['action'] ?? null) !== 'tool_calls') {
            return [];
        }

        $toolCalls = $plannerOutput['tool_calls'] ?? [];

        if (! is_array($toolCalls) || count($toolCalls) > self::MAX_TOOL_CALLS) {
            $this->recordDeniedToolCall($trace, 'planner_output', [], 'invalid_live_planner_output');

            return [];
        }

        $results = [];

        foreach ($toolCalls as $toolCall) {
            if (! is_array($toolCall)) {
                $this->recordDeniedToolCall($trace, 'planner_output', [], 'invalid_live_planner_output');

                return $results;
            }

            $toolName = (string) ($toolCall['tool_name'] ?? '');
            $arguments = $toolCall['arguments'] ?? [];

            if ($toolName === '' || ! is_array($arguments) || ! $this->toolRegistry->has($toolName)) {
                $this->recordDeniedToolCall($trace, $toolName !== '' ? $toolName : 'unknown_tool', is_array($arguments) ? $arguments : [], 'unsupported_tool');

                $results[] = $this->deniedToolResult($toolName !== '' ? $toolName : 'unknown_tool', 'unsupported_tool');

                return $results;
            }

            $results[] = $this->toolDispatcher->dispatch($toolName, $arguments, $actor, $campus, $trace);

            $latestResult = $results[array_key_last($results)];

            if (! in_array($latestResult->status(), ['completed', 'partial'], true)) {
                return $results;
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function recordDeniedToolCall(AiAgentTrace $trace, string $toolName, array $arguments, string $safeErrorCode): void
    {
        $this->auditRecorder->recordToolCall($trace, [
            'tool_name' => $toolName,
            'tool_schema_version' => null,
            'arguments' => [
                'rejected_argument_keys' => array_values(array_map('strval', array_keys($arguments))),
            ],
            'permission_result' => 'not_evaluated',
            'campus_scope_snapshot' => [],
            'hidden_sections' => [$safeErrorCode],
            'record_count' => 0,
            'source_references' => [],
            'result_summary' => [
                'allowed' => false,
                'safe_error_code' => $safeErrorCode,
            ],
            'status' => 'denied',
            'safe_error_code' => $safeErrorCode,
        ]);
    }

    private function deniedToolResult(string $toolName, string $safeErrorCode): QueryMetricsResult
    {
        return QueryMetricsResult::failed($toolName, [
            'catalog_version' => $this->metricCatalog->version(),
            'tool_schema_version' => 'live_planner:v1',
            'permission_result' => 'not_evaluated',
            'hidden_sections' => [$safeErrorCode],
            'campus_scope_snapshot' => [],
        ], $safeErrorCode);
    }

    /**
     * @param  array<string, mixed>  $plannerOutput
     */
    private function answerWithoutTools(array $plannerOutput): StaffCopilotAnswer
    {
        if (($plannerOutput['action'] ?? null) === 'ask_clarification') {
            return StaffCopilotAnswer::clarification((string) ($plannerOutput['question'] ?? 'Could you clarify the metric or entity you want to inspect?'));
        }

        return StaffCopilotAnswer::unsupported();
    }

    private function responseToArray(AgentResponse $response): array
    {
        if (method_exists($response, 'toArray')) {
            $payload = $response->toArray();

            return is_array($payload) ? $payload : [];
        }

        $decoded = json_decode((string) $response, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $resolvedProvider
     */
    private function recordUsage(AiAgentTrace $trace, AgentResponse $response, array $resolvedProvider, string $status): void
    {
        $usage = $response->usage;

        $this->usageRecorder->record($trace, [
            'provider' => $response->meta->provider ?? $resolvedProvider['provider'],
            'model' => $response->meta->model ?? $resolvedProvider['model'],
            'status' => $status,
            'tokens_in' => $usage->promptTokens,
            'tokens_out' => $usage->completionTokens,
            'provider_request_id' => $response->invocationId,
        ]);
    }

    private function catalogSummary(): string
    {
        return json_encode(
            collect($this->metricCatalog->metrics())
                ->map(fn (array $metric): array => Arr::only($metric, [
                    'key',
                    'label',
                    'description',
                    'allowed_filters',
                    'allowed_group_by',
                    'source_report',
                    'source_reference_policy',
                    'max_record_limit',
                ]))
                ->values()
                ->all(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    private function entityCatalogSummary(): string
    {
        return json_encode(
            collect($this->entityCatalog->entities())
                ->map(fn (array $entity): array => Arr::only($entity, [
                    'key',
                    'aliases',
                    'business_meaning',
                    'search_fields',
                    'result_fields',
                    'allowed_filters',
                    'source_report',
                    'source_reference_policy',
                    'max_results',
                ]))
                ->values()
                ->all(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    private function studentProfileSectionSummary(): string
    {
        return json_encode([
            'catalog_version' => $this->studentProfileSectionCatalog->version(),
            'tool_schema_version' => $this->studentProfileSectionCatalog->toolSchemaVersion(),
            'sections' => collect($this->studentProfileSectionCatalog->sections())
                ->map(fn (array $section): array => Arr::only($section, [
                    'key',
                    'aliases',
                    'required_permission',
                    'source_report',
                    'source_reference_policy',
                    'fields',
                ]))
                ->values()
                ->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function toolSummary(): string
    {
        return json_encode(
            collect($this->toolRegistry->toolNames())
                ->map(fn (string $toolName): array => $this->toolRegistry->definition($toolName)?->toArray() ?? ['name' => $toolName])
                ->values()
                ->all(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
}
