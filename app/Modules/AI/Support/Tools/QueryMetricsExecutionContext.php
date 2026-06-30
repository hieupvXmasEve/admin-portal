<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Support\CampusScopeSnapshot;

class QueryMetricsExecutionContext
{
    public function __construct(
        public readonly User $actor,
        public readonly ?Campus $campus = null,
        public readonly ?AiAgentTrace $trace = null,
        /**
         * Pre-resolved, multi-campus-capable scope supplied by the MCP campus resolver.
         * When null (the web-chat path) the validator derives a single-campus snapshot
         * from {@see $campus}, keeping existing behavior byte-identical.
         */
        public readonly ?CampusScopeSnapshot $campusScope = null,
    ) {}
}
