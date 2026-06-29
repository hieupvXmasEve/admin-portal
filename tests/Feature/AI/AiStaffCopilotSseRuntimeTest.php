<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiRunEvent;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\StaffCopilotSseRuntime;
use App\Services\PermissionService;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->authorizedPeer = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);

    session([
        '_token' => 'ai-sse-runtime-test-token',
        'current_campus_id' => $this->campus->id,
    ]);

    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null): array {
            if (
                in_array($user->id, [$this->authorizedUser->id, $this->authorizedPeer->id], true)
                && $campusId === $this->campus->id
            ) {
                return ['view_ai_metrics', 'view_finance_reporting', 'view_academic_report'];
            }

            return [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('queues a durable staff copilot run with placeholder state before provider execution', function () {
    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $conversation = AiConversation::query()->firstOrFail();
    $run = AiChatRun::query()->firstOrFail();
    $userMessage = AiMessage::query()->where('role', 'user')->firstOrFail();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();

    expect($run->status)->toBe(AiChatRun::STATUS_QUEUED)
        ->and(AiChatRun::query()->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(1)
        ->and($run->user_id)->toBe($this->authorizedUser->id)
        ->and($run->campus_id)->toBe($this->campus->id)
        ->and($run->provider)->toBe('deterministic')
        ->and($run->model)->toBe('staff-copilot-mvp')
        ->and($run->runtime_mode)->toBe('deterministic')
        ->and($run->stream_transport)->toBe('sse')
        ->and($run->stream_mode)->toBe('fallback_snapshot')
        ->and($run->prompt_version)->toBe('staff-copilot-mvp:v1')
        ->and($run->catalog_version)->toBe('metric-catalog:v1')
        ->and($run->tool_schema_version)->toBe('query_metrics:v1')
        ->and($run->ai_conversation_id)->toBe($conversation->id)
        ->and($run->user_message_id)->toBe($userMessage->id)
        ->and($run->assistant_message_id)->toBe($assistantMessage->id)
        ->and($assistantMessage->redacted_content)->toBe('')
        ->and(AiRunEvent::query()->orderBy('sequence')->pluck('event_type')->all())->toBe([
            'run.queued',
            'message.created',
            'message.created',
        ]);

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('active_run.id', $run->id)
            ->where('active_run.status', AiChatRun::STATUS_QUEUED)
            ->where('active_run.assistant_message_id', $assistantMessage->id)
            ->where('active_run.last_event_id', $run->last_event_id)
            ->where('active_run.replay_cursor', 0)
            ->where('active_run.can_cancel', true)
            ->where('active_run.stream_url', route('ai.copilot.runs.events', $run, false))
            ->where('active_run.provider', 'deterministic')
            ->where('active_run.model', 'staff-copilot-mvp')
            ->where('active_run.runtime_mode', 'deterministic')
            ->where('active_run.stream_transport', 'sse')
            ->where('active_run.stream_mode', 'fallback_snapshot')
            ->where('active_run.streaming_enabled', true)
            ->where('active_run.websocket_required', false)
            ->where('active_run.prompt_version', 'staff-copilot-mvp:v1')
            ->where('active_run.catalog_version', 'metric-catalog:v1')
            ->where('active_run.tool_schema_version', 'query_metrics:v1')
            ->where('capabilities.stream_transport', 'sse')
            ->where('capabilities.streaming_enabled', true)
            ->where('capabilities.websocket_required', false));
});

it('streams normalized run events and completes the queued run through allowlisted tools', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('collectionProgress')
            ->once()
            ->with($this->semester->id, Mockery::on(fn (array $filters): bool => $filters['per_page'] === 500 && $filters['page'] === 1))
            ->andReturn([
                'summary' => [
                    'student_count' => 2,
                    'billed_total' => 1000.0,
                    'paid_total' => 700.0,
                    'outstanding_total' => 300.0,
                    'collection_rate' => 0.7,
                ],
                'breakdowns' => [
                    'by_program' => [
                        [
                            'key' => 'IT',
                            'label' => 'Information Technology',
                            'student_count' => 2,
                            'outstanding' => 300.0,
                        ],
                    ],
                ],
                'meta' => [],
            ]);
    });

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('event: run.started')
        ->toContain('event: run.status')
        ->toContain('event: tool.completed')
        ->toContain('event: message.delta')
        ->toContain('event: message.completed')
        ->toContain('event: run.completed')
        ->not->toContain('raw_provider_request')
        ->not->toContain('raw_provider_response');

    $run->refresh();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();
    $toolCall = AiToolCall::query()->firstOrFail();

    expect($run->status)->toBe(AiChatRun::STATUS_COMPLETED)
        ->and($run->last_event_id)->not->toBeNull()
        ->and($assistantMessage->redacted_content)->toContain('Answer prepared from approved Finance Reporting Collection Progress data.')
        ->and($assistantMessage->redacted_content)->toContain('- Outstanding Total: **300**')
        ->and($assistantMessage->final_answer_id)->not->toBeNull()
        ->and($toolCall->tool_name)->toBe('query_metrics')
        ->and($toolCall->status)->toBe('completed')
        ->and($toolCall->source_references[0]['source_report'])->toBe('finance.reporting.collection-progress')
        ->and(AiRunEvent::query()->where('event_type', 'message.delta')->count())->toBeGreaterThan(0);

    $events = AiRunEvent::query()
        ->where('ai_chat_run_id', $run->id)
        ->orderBy('sequence')
        ->get();

    expect($events->pluck('sequence')->all())->toBe(range(1, $events->count()))
        ->and($events->pluck('redacted_payload.sequence')->all())->toBe(range(1, $events->count()));
});

it('replays persisted run events after a valid cursor without duplicating assistant deltas or executing tools again', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('collectionProgress')
            ->once()
            ->andReturn([
                'summary' => [
                    'student_count' => 2,
                    'billed_total' => 1000.0,
                    'paid_total' => 700.0,
                    'outstanding_total' => 300.0,
                    'collection_rate' => 0.7,
                ],
                'breakdowns' => [
                    'by_program' => [
                        [
                            'key' => 'IT',
                            'label' => 'Information Technology',
                            'student_count' => 2,
                            'outstanding' => 300.0,
                        ],
                    ],
                ],
                'meta' => [],
            ]);
    });

    $initialResponse = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $initialContent = $initialResponse->streamedContent();
    $initialEvents = staffCopilotSseEvents($initialContent);
    $deltaEvent = collect($initialEvents)->firstWhere('event', 'message.delta');

    expect($deltaEvent)->not->toBeNull();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $replayResponse = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', [$run, 'cursor' => $deltaEvent['data']['event_id']]));

    $replayContent = $replayResponse->streamedContent();
    $replayEvents = staffCopilotSseEvents($replayContent);

    expect(collect($replayEvents)->pluck('data.event_id')->all())
        ->not->toContain($deltaEvent['data']['event_id'])
        ->and(collect($replayEvents)->pluck('event')->all())
        ->toContain('message.completed')
        ->toContain('run.completed')
        ->and($replayContent)
        ->not->toContain('event: message.delta');
});

it('rejects an invalid cursor with a stable safe error response before executing provider or tool work', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', [$run, 'cursor' => 999999]))
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'invalid_run_event_cursor')
        ->assertJsonPath('errors.0.field', 'cursor');

    $run->refresh();

    expect($run->status)->toBe(AiChatRun::STATUS_QUEUED)
        ->and(AiToolCall::query()->count())->toBe(0);
});

it('denies run stream and cancellation access to another authorized staff user', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->actingAs($this->authorizedPeer)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversation', null)
            ->where('active_run', null)
            ->has('messages', 0));

    $this->actingAs($this->authorizedPeer)
        ->get(route('ai.copilot.runs.events', $run))
        ->assertForbidden();

    $this->actingAs($this->authorizedPeer)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->post(route('ai.copilot.runs.cancel', $run))
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->post(route('ai.copilot.runs.cancel', $run))
        ->assertForbidden();
});

it('does not recover a staff copilot active run from the wrong campus scope', function () {
    $otherCampus = Campus::factory()->create(['code' => 'DNG']);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null) use ($otherCampus): array {
            if (
                $user->id === $this->authorizedUser->id
                && in_array($campusId, [$this->campus->id, $otherCampus->id], true)
            ) {
                return ['view_ai_metrics', 'view_finance_reporting', 'view_academic_report'];
            }

            return [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
    app()->forgetInstance('campus');
    app()->singleton('campus', fn () => $otherCampus);

    $this->actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $otherCampus->id])
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversation', null)
            ->where('active_run', null)
            ->has('messages', 0));

    $this->actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $otherCampus->id])
        ->get(route('ai.copilot.runs.events', $run))
        ->assertForbidden();

    $this->actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $otherCampus->id])
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->post(route('ai.copilot.runs.cancel', $run))
        ->assertForbidden();
});

it('cancels a queued run without executing provider or tool work', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.runs.cancel', $run))
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('info', 'AI copilot run cancelled.');

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    $run->refresh();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();
    $trace = AiAgentTrace::query()->firstOrFail();
    $cancelEvent = AiRunEvent::query()
        ->where('ai_chat_run_id', $run->id)
        ->where('event_type', 'run.cancelled')
        ->firstOrFail();

    expect($run->status)->toBe(AiChatRun::STATUS_CANCELLED)
        ->and($run->cancellation_requested_at)->not->toBeNull()
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->safe_error_code)->toBe('run_cancelled')
        ->and($run->last_event_id)->toBe($cancelEvent->id)
        ->and($assistantMessage->redacted_content)->toBe('This run was cancelled before a complete answer was produced.')
        ->and($assistantMessage->final_answer_id)->toBe('staff-copilot-terminal-'.$run->ai_agent_trace_id)
        ->and($assistantMessage->hidden_sections)->toBe(['run_cancelled'])
        ->and($trace->status)->toBe(AiChatRun::STATUS_CANCELLED)
        ->and($trace->safe_error_code)->toBe('run_cancelled')
        ->and($trace->final_answer_id)->toBe('staff-copilot-terminal-'.$run->ai_agent_trace_id)
        ->and($cancelEvent->redacted_payload['status'])->toBe(AiChatRun::STATUS_CANCELLED)
        ->and($cancelEvent->redacted_payload['safe_error_code'])->toBe('run_cancelled')
        ->and($response->streamedContent())->toContain('event: run.cancelled')
        ->and(AiToolCall::query()->count())->toBe(0);

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('active_run', null)
            ->where('messages.1.run_status', AiChatRun::STATUS_CANCELLED)
            ->where('messages.1.answer.terminal_state.kind', 'cancelled')
            ->where('messages.1.answer.safe_error_code', 'run_cancelled'));
});

it('denies cancelling terminal runs without changing completed history', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('collectionProgress')
            ->once()
            ->andReturn([
                'summary' => [
                    'student_count' => 2,
                    'billed_total' => 1000.0,
                    'paid_total' => 700.0,
                    'outstanding_total' => 300.0,
                    'collection_rate' => 0.7,
                ],
                'breakdowns' => [],
                'meta' => [],
            ]);
    });

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run))
        ->streamedContent();

    $run->refresh();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();
    $completedContent = $assistantMessage->redacted_content;
    $completedAt = $run->completed_at;
    $lastEventId = $run->last_event_id;

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->post(route('ai.copilot.runs.cancel', $run))
        ->assertStatus(409);

    $run->refresh();
    $assistantMessage->refresh();

    expect($run->status)->toBe(AiChatRun::STATUS_COMPLETED)
        ->and($run->cancellation_requested_at)->toBeNull()
        ->and($run->safe_error_code)->toBeNull()
        ->and($run->completed_at?->toISOString())->toBe($completedAt?->toISOString())
        ->and($run->last_event_id)->toBe($lastEventId)
        ->and($assistantMessage->redacted_content)->toBe($completedContent)
        ->and(AiRunEvent::query()->where('event_type', 'run.cancelled')->exists())->toBeFalse();
});

it('preserves cancelled state when cancellation is requested after execution has started', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('collectionProgress')
            ->once()
            ->andReturnUsing(function (): array {
                app(StaffCopilotSseRuntime::class)->cancel(AiChatRun::query()->firstOrFail());

                return [
                    'summary' => [
                        'student_count' => 2,
                        'billed_total' => 1000.0,
                        'paid_total' => 700.0,
                        'outstanding_total' => 300.0,
                        'collection_rate' => 0.7,
                    ],
                    'breakdowns' => [],
                    'meta' => [],
                ];
            });
    });

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    $content = $response->streamedContent();
    $run->refresh();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();

    expect($content)
        ->toContain('event: run.started')
        ->toContain('event: run.cancelled')
        ->not->toContain('event: message.delta')
        ->not->toContain('event: run.completed')
        ->and($run->status)->toBe(AiChatRun::STATUS_CANCELLED)
        ->and($run->safe_error_code)->toBe('run_cancelled')
        ->and($assistantMessage->redacted_content)->toBe('This run was cancelled before a complete answer was produced.')
        ->and(AiToolCall::query()->count())->toBe(1);
});

it('retries a failed run without duplicating the original user message', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Write an email to all students',
        ]);

    $failedRun = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $failedRun))
        ->assertStreamed();

    $response->streamedContent();

    $failedRun->refresh();

    expect($failedRun->status)->toBe(AiChatRun::STATUS_FAILED)
        ->and(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(1);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-sse-runtime-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.runs.retry', $failedRun))
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run retried.');

    $retryRun = AiChatRun::query()->latest('id')->firstOrFail();

    expect($retryRun->id)->not->toBe($failedRun->id)
        ->and($retryRun->status)->toBe(AiChatRun::STATUS_QUEUED)
        ->and($retryRun->user_message_id)->toBe($failedRun->user_message_id)
        ->and($retryRun->assistant_message_id)->not->toBe($failedRun->assistant_message_id)
        ->and(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(2);
});

/**
 * @return list<array{event: string, data: array<string, mixed>}>
 */
function staffCopilotSseEvents(string $content): array
{
    return collect(preg_split("/\n\n/", trim($content)) ?: [])
        ->map(function (string $block): ?array {
            $event = null;
            $data = null;

            foreach (explode("\n", $block) as $line) {
                if (str_starts_with($line, 'event: ')) {
                    $event = substr($line, 7);
                }

                if (str_starts_with($line, 'data: ')) {
                    $decoded = json_decode(substr($line, 6), true);
                    $data = is_array($decoded) ? $decoded : [];
                }
            }

            if ($event === null) {
                return null;
            }

            return [
                'event' => $event,
                'data' => $data ?? [],
            ];
        })
        ->filter()
        ->values()
        ->all();
}
