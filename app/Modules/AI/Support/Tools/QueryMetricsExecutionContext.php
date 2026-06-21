<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;

class QueryMetricsExecutionContext
{
    public function __construct(
        public readonly User $actor,
        public readonly ?Campus $campus = null,
        public readonly ?AiAgentTrace $trace = null,
    ) {}
}
