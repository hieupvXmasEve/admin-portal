<?php

declare(strict_types=1);

namespace App\Modules\AI\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class LiveStaffCopilotFinalAnswerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly string $instructions) {}

    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['completed', 'partial', 'failed', 'denied'])->required(),
            'answer' => $schema->string()->required(),
            'referenced_tool_call_ids' => $schema->array()->items($schema->string())->nullable(),
            'source_references' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'source_report' => $schema->string()->required(),
                    'source_reference_policy' => $schema->string()->required(),
                ]))
                ->nullable(),
            'confidence' => $schema->object(fn (JsonSchema $schema): array => [
                'level' => $schema->string()->enum(['none', 'low', 'medium', 'high'])->required(),
                'basis' => $schema->string()->required(),
            ])->required(),
            'limitations' => $schema->array()->items($schema->string())->nullable(),
            'clarification_question' => $schema->string()->nullable(),
            'safe_error_code' => $schema->string()->nullable(),
        ];
    }
}
