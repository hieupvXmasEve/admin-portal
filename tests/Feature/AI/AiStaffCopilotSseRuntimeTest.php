<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiRunEvent;
use App\Modules\AI\Models\AiToolCall;
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
                return ['view_ai_metrics'];
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
        ->and($run->stream_transport)->toBe('sse')
        ->and($run->stream_mode)->toBe('fallback_snapshot')
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
            ->where('active_run.can_cancel', true)
            ->where('active_run.stream_url', route('ai.copilot.runs.events', $run, false))
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
        ->and($assistantMessage->redacted_content)->toBe('Answer generated from finance.reporting.collection-progress.')
        ->and($assistantMessage->final_answer_id)->not->toBeNull()
        ->and($toolCall->tool_name)->toBe('query_metrics')
        ->and($toolCall->status)->toBe('completed')
        ->and($toolCall->source_references[0]['source_report'])->toBe('finance.reporting.collection-progress')
        ->and(AiRunEvent::query()->where('event_type', 'message.delta')->count())->toBeGreaterThan(0);
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
        ->get(route('ai.copilot.runs.events', $run))
        ->assertForbidden();

    $this->actingAs($this->authorizedPeer)
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

    expect($run->status)->toBe(AiChatRun::STATUS_CANCELLED)
        ->and($run->cancellation_requested_at)->not->toBeNull()
        ->and($assistantMessage->redacted_content)->toBe('This run was cancelled before completion.')
        ->and($response->streamedContent())->toContain('event: run.cancelled')
        ->and(AiToolCall::query()->count())->toBe(0);
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
