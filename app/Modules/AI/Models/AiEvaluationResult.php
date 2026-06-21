<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEvaluationResult extends Model
{
    protected $fillable = [
        'ai_evaluation_run_id',
        'ai_evaluation_case_id',
        'status',
        'assertions',
        'redacted_diff_summary',
        'tool_call_evidence',
        'source_parity_status',
        'safe_error_code',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'assertions' => 'array',
            'tool_call_evidence' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiEvaluationRun::class, 'ai_evaluation_run_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(AiEvaluationCase::class, 'ai_evaluation_case_id');
    }
}
