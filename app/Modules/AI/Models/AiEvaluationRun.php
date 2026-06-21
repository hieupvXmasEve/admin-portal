<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiEvaluationRun extends Model
{
    protected $fillable = [
        'dataset_version',
        'code_version',
        'prompt_version',
        'catalog_version',
        'tool_schema_version',
        'provider_fake',
        'status',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiEvaluationResult::class);
    }
}
