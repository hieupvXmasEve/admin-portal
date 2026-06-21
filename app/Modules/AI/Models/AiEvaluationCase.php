<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiEvaluationCase extends Model
{
    protected $fillable = [
        'key',
        'dataset_version',
        'question',
        'actor_fixture',
        'permission_context',
        'expected_tool_calls',
        'expected_source_references',
        'expected_answer_properties',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'actor_fixture' => 'array',
            'permission_context' => 'array',
            'expected_tool_calls' => 'array',
            'expected_source_references' => 'array',
            'expected_answer_properties' => 'array',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiEvaluationResult::class);
    }
}
