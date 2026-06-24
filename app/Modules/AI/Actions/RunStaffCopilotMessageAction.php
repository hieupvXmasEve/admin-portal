<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\StaffCopilotAgentRunner;
use App\Modules\AI\Support\StaffCopilotAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunStaffCopilotMessageAction
{
    public function __construct(
        private readonly AiAuditRecorder $auditRecorder,
        private readonly MetricCatalog $catalog,
        private readonly StaffCopilotAgentRunner $runner,
    ) {}

    public static function run(User $actor, ?Campus $campus, string $question, ?int $conversationId = null): StaffCopilotAnswer
    {
        return app(self::class)->handle($actor, $campus, $question, $conversationId);
    }

    public function handle(User $actor, ?Campus $campus, string $question, ?int $conversationId = null): StaffCopilotAnswer
    {
        $started = microtime(true);
        $conversation = $this->conversationFor($actor, $campus, $question, $conversationId);
        $userMessage = $this->auditRecorder->recordMessage($conversation, 'user', $question, [
            'content_classification' => 'staff_copilot_question',
        ]);
        $trace = $this->auditRecorder->startTrace($conversation, $userMessage, [
            'provider' => 'deterministic',
            'model' => 'staff-copilot-mvp',
            'prompt_version' => StaffCopilotAgentRunner::PROMPT_VERSION,
            'catalog_version' => $this->catalog->version(),
            'tool_schema_version' => $this->catalog->toolSchemaVersion(),
        ]);

        $answer = $this->runner->run($question, $actor, $campus, $trace);
        $finalAnswerId = 'staff-copilot-'.$trace->id;

        DB::transaction(function () use ($conversation, $trace, $answer, $finalAnswerId, $started): void {
            $this->auditRecorder->recordMessage($conversation, 'assistant', $answer->content(), [
                'content_classification' => 'staff_copilot_answer',
                'final_answer_id' => $finalAnswerId,
                'hidden_sections' => $answer->hiddenSections(),
            ]);

            $trace->forceFill([
                'status' => $answer->status(),
                'step_count' => $answer->toolExecuted() ? 1 : 0,
                'duration_ms' => max(0, (int) round((microtime(true) - $started) * 1000)),
                'safe_error_code' => $answer->safeErrorCode() ?? $trace->safe_error_code,
                'final_answer_id' => $finalAnswerId,
            ])->save();
        });

        return $answer;
    }

    private function conversationFor(User $actor, ?Campus $campus, string $question, ?int $conversationId): AiConversation
    {
        $query = AiConversation::query()
            ->where('user_id', $actor->id)
            ->where('origin', 'staff_chat')
            ->where('status', 'open')
            ->when(
                $campus !== null,
                fn ($builder) => $builder->where('campus_id', $campus->id),
                fn ($builder) => $builder->whereNull('campus_id'),
            );

        if ($conversationId !== null) {
            $conversation = (clone $query)->whereKey($conversationId)->first();

            if ($conversation instanceof AiConversation) {
                return $conversation;
            }
        }

        $conversation = (clone $query)
            ->latest('last_message_at')
            ->latest('id')
            ->first();

        if ($conversation instanceof AiConversation) {
            return $conversation;
        }

        return $this->auditRecorder->startConversation($actor, $campus, [
            'origin' => 'staff_chat',
            'title' => Str::limit($question, 80, ''),
            'actor_role_snapshot' => [
                'user_id' => $actor->id,
                'role' => 'staff',
                'permissions' => ['view_ai_metrics'],
            ],
            'campus_scope_snapshot' => [
                'campus_ids' => $campus ? [$campus->id] : [],
            ],
        ]);
    }
}
