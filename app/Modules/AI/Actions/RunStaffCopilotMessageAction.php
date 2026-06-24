<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Support\StaffCopilotSseRuntime;

class RunStaffCopilotMessageAction
{
    public function __construct(private readonly StaffCopilotSseRuntime $runtime) {}

    public static function run(User $actor, ?Campus $campus, string $question, ?int $conversationId = null): AiChatRun
    {
        return app(self::class)->handle($actor, $campus, $question, $conversationId);
    }

    public function handle(User $actor, ?Campus $campus, string $question, ?int $conversationId = null): AiChatRun
    {
        return $this->runtime->queue($actor, $campus, $question, $conversationId);
    }
}
