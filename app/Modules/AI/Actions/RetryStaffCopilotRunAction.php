<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Support\StaffCopilotSseRuntime;

class RetryStaffCopilotRunAction
{
    public function __construct(private readonly StaffCopilotSseRuntime $runtime) {}

    public static function run(AiChatRun $sourceRun): AiChatRun
    {
        return app(self::class)->handle($sourceRun);
    }

    public function handle(AiChatRun $sourceRun): AiChatRun
    {
        return $this->runtime->retry($sourceRun);
    }
}
