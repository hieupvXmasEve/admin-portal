<?php

declare(strict_types=1);

namespace App\Modules\AI\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class LiveStaffCopilotPlannerAgent implements Agent, HasStructuredOutput
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
            'action' => $schema->string()->enum(['tool_calls', 'ask_clarification', 'unsupported'])->required(),
            'tool_calls' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'tool_name' => $schema->string()->required(),
                    'arguments' => $schema->object()->required(),
                    'reason' => $schema->string()->nullable(),
                ]))
                ->nullable(),
            'answer_intent' => $schema->string()->nullable(),
            'question' => $schema->string()->nullable(),
            'missing_fields' => $schema->array()->items($schema->string())->nullable(),
            'safe_error_code' => $schema->string()->nullable(),
            'reason' => $schema->string()->nullable(),
        ];
    }
}
