<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Support\MetricCatalog;

class ToolDispatcher
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly MetricCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function dispatch(
        string $toolName,
        array $arguments,
        User $actor,
        ?Campus $campus = null,
        ?AiAgentTrace $trace = null,
    ): QueryMetricsResult {
        $tool = $this->registry->tool($toolName);

        if (! $tool) {
            return QueryMetricsResult::failed($toolName, [
                'catalog_version' => $this->catalog->version(),
                'tool_schema_version' => $this->catalog->toolSchemaVersion(),
                'metric' => $arguments['metric'] ?? null,
                'permission_result' => 'not_evaluated',
                'campus_scope_snapshot' => [
                    'campus_ids' => $campus?->id ? [$campus->id] : [],
                ],
            ], 'unsupported_tool');
        }

        return $tool->handle($arguments, new QueryMetricsExecutionContext($actor, $campus, $trace));
    }
}
