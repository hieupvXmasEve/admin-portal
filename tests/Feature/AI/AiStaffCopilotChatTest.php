<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Agents\LiveStaffCopilotFinalAnswerAgent;
use App\Modules\AI\Agents\LiveStaffCopilotPlannerAgent;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Models\AiRunEvent;
use App\Modules\AI\Models\AiToolCall;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);

    session([
        '_token' => 'ai-copilot-test-token',
        'current_campus_id' => $this->campus->id,
    ]);

    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(function (int $userId, ?int $campusId = null): array {
            if ($userId === $this->authorizedUser->id && $campusId === $this->campus->id) {
                return ['view_ai_metrics', 'view_finance_reporting', 'view_academic_report'];
            }

            return [];
        });

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('renders the staff copilot page with prompt and tool capabilities', function () {
    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AI/StaffCopilot/Index')
            ->has('suggested_prompts', 5)
            ->where('suggested_prompts.2.key', 'finance_collection_summary_by_program')
            ->where('suggested_prompts.2.question', 'Current semester outstanding tuition by program là bao nhiêu?')
            ->where('capabilities.tool_names.0', 'query_metrics')
            ->where('capabilities.catalog_version', 'metric-catalog:v1')
            ->where('capabilities.tool_schema_version', 'query_metrics:v1')
            ->where('capabilities.sdk_installed', true)
            ->where('capabilities.live_provider_enabled', false)
            ->where('conversation', null)
            ->has('messages', 0));
});

it('denies copilot access to staff without AI metric permission', function () {
    $this->actingAs($this->unauthorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ])
        ->assertForbidden();
});

it('answers a supported finance prompt through query metrics and records audited evidence', function () {
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

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())->toContain('event: run.completed');

    $conversation = AiConversation::query()->firstOrFail();

    expect($conversation->origin)->toBe('staff_chat')
        ->and($conversation->status)->toBe('open')
        ->and($conversation->user_id)->toBe($this->authorizedUser->id)
        ->and($conversation->campus_id)->toBe($this->campus->id)
        ->and(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(1);

    $trace = AiAgentTrace::query()->firstOrFail();
    $toolCall = AiToolCall::query()->firstOrFail();

    expect($trace->status)->toBe('completed')
        ->and($trace->catalog_version)->toBe('metric-catalog:v1')
        ->and($trace->tool_schema_version)->toBe('query_metrics:v1')
        ->and($trace->step_count)->toBe(1)
        ->and($trace->final_answer_id)->not->toBeNull()
        ->and($toolCall->tool_name)->toBe('query_metrics')
        ->and($toolCall->status)->toBe('completed')
        ->and($toolCall->permission_result)->toBe('allowed')
        ->and($toolCall->record_count)->toBe(2)
        ->and($toolCall->source_references[0]['source_report'])->toBe('finance.reporting.collection-progress')
        ->and($toolCall->redacted_arguments)->toMatchArray([
            'metric' => 'finance_collection_summary',
            'filters' => ['semester' => 'current'],
            'group_by' => ['program'],
        ])
        ->and((float) $toolCall->redacted_result_summary['summary']['outstanding_total'])->toBe(300.0)
        ->and($toolCall->redacted_result_summary['normalized_filters'])->toBe(['semester_id' => $this->semester->id])
        ->and($toolCall->redacted_result_summary['group_by'])->toBe(['program'])
        ->and($toolCall->redacted_result_summary['groups'][0]['key'])->toBe('program');

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('messages', 2)
            ->where('messages.0.role', 'user')
            ->where('messages.1.role', 'assistant')
            ->where('messages.1.answer.status', 'completed')
            ->where('messages.1.answer.summary.outstanding_total', 300)
            ->where('messages.1.answer.groups.0.label', 'Information Technology')
            ->where('messages.1.answer.source_references.0.source_report', 'finance.reporting.collection-progress'));
});

it('fails unsupported staff questions safely without source execution', function () {
    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $this->mock(AiAcademicMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('studentStatusBySemesterExport');
    });

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Write an email to all students',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())->toContain('event: run.failed');

    $trace = AiAgentTrace::query()->firstOrFail();

    expect($trace->status)->toBe('failed')
        ->and($trace->safe_error_code)->toBe('unsupported_staff_question')
        ->and($trace->step_count)->toBe(0)
        ->and(AiToolCall::query()->count())->toBe(0)
        ->and(AiMessage::query()->where('role', 'assistant')->firstOrFail()->hidden_sections)->toBe(['unsupported_staff_question']);

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('messages.1.answer.terminal_state.kind', 'unsupported')
            ->where('messages.1.answer.safe_error_code', 'unsupported_staff_question')
            ->where('messages.1.answer.terminal_state.safe_error_code', 'unsupported_staff_question')
            ->where('messages.1.answer.hidden_section_notice', 'Some details are withheld because Staff Copilot only shows approved, safe answer content.'));
});

it('stores and renders live completed answers as safe markdown while preserving answer evidence', function () {
    AiProviderSetting::query()->create([
        'user_id' => $this->authorizedUser->id,
        'provider' => 'openai',
        'default_model' => 'gpt-4o-mini',
        'encrypted_api_key' => 'sk-live-provider-test',
        'enabled' => true,
        'tested_at' => now(),
        'last_test_status' => 'success',
        'created_by_user_id' => $this->authorizedUser->id,
        'updated_by_user_id' => $this->authorizedUser->id,
    ]);

    LiveStaffCopilotPlannerAgent::fake([
        [
            'action' => 'tool_calls',
            'tool_calls' => [
                [
                    'tool_name' => 'query_metrics',
                    'arguments' => [
                        'metric' => 'finance_collection_summary',
                        'filters' => ['semester' => 'current'],
                        'group_by' => ['program'],
                        'options' => [
                            'include_rows' => false,
                            'limit' => 500,
                        ],
                    ],
                    'reason' => 'The prompt asks for outstanding tuition grouped by program.',
                ],
            ],
            'answer_intent' => 'finance_collection_summary',
            'question' => null,
            'safe_error_code' => null,
            'reason' => null,
        ],
    ])->preventStrayPrompts();

    LiveStaffCopilotFinalAnswerAgent::fake([
        [
            'status' => 'completed',
            'answer' => implode("\n", [
                '## Tuition answer',
                '<script>alert("x")</script>',
                '```sql',
                'select * from students where national_id is not null;',
                '```',
                '![chart](https://example.invalid/chart.png)',
                '[raw provider payload](https://example.invalid/provider)',
                'Outstanding tuition is **300** across 2 students.',
                'raw_provider_response: {"secret":"sk-live-provider-test"}',
            ]),
            'referenced_tool_call_ids' => ['tool-call-1'],
            'source_references' => [
                [
                    'source_report' => 'finance.reporting.collection-progress',
                    'source_reference_policy' => 'report_summary_with_filters',
                ],
            ],
            'confidence' => [
                'level' => 'high',
                'basis' => 'tool_result_exact_match',
            ],
            'limitations' => [],
            'clarification_question' => null,
            'safe_error_code' => null,
        ],
    ])->preventStrayPrompts();

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

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Can you show outstanding tuition by program for this term?',
        ])
        ->assertRedirect(route('ai.copilot.index'));

    $run = AiChatRun::query()->firstOrFail();

    $streamResponse = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $streamResponse->assertStreamed();

    $streamContent = $streamResponse->streamedContent();

    expect($streamContent)
        ->toContain('Outstanding tuition is **300**')
        ->not->toContain('<script')
        ->not->toContain('```')
        ->not->toContain('select *')
        ->not->toContain('https://example.invalid')
        ->not->toContain('raw provider payload')
        ->not->toContain('raw_provider_response')
        ->not->toContain('sk-live-provider-test');

    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();

    expect($assistantMessage->redacted_content)
        ->toContain('Outstanding tuition is **300**')
        ->not->toContain('<script')
        ->not->toContain('```')
        ->not->toContain('select *')
        ->not->toContain('https://example.invalid')
        ->not->toContain('raw provider payload')
        ->not->toContain('raw_provider_response')
        ->not->toContain('sk-live-provider-test');

    $pageResponse = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'));

    $pageResponse
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('messages.1.answer.status', 'completed')
            ->where('messages.1.answer.terminal_state.kind', 'completed')
            ->where('messages.1.answer.content_markdown', $assistantMessage->redacted_content)
            ->where('messages.1.answer.summary.outstanding_total', 300)
            ->where('messages.1.answer.groups.0.label', 'Information Technology')
            ->where('messages.1.answer.source_references.0.source_report', 'finance.reporting.collection-progress')
            ->where('messages.1.answer.normalized_filters.semester_id', $this->semester->id)
            ->where('messages.1.answer.campus_scope_snapshot.campus_ids.0', $this->campus->id)
            ->where('messages.1.answer.freshness.rule', 'computed_at_request_time')
            ->where('messages.1.answer.confidence.level', 'high'));

    expect($pageResponse->getContent())
        ->toContain('Outstanding tuition is **300**')
        ->not->toContain('alert(&quot;x&quot;)')
        ->not->toContain('```')
        ->not->toContain('select *')
        ->not->toContain('https://example.invalid')
        ->not->toContain('raw provider payload')
        ->not->toContain('raw_provider_response')
        ->not->toContain('sk-live-provider-test');
});

it('renders a failed live final answer as terminal copy while preserving completed tool evidence', function () {
    AiProviderSetting::query()->create([
        'user_id' => $this->authorizedUser->id,
        'provider' => 'openai',
        'default_model' => 'gpt-4o-mini',
        'encrypted_api_key' => 'sk-live-provider-test',
        'enabled' => true,
        'tested_at' => now(),
        'last_test_status' => 'success',
        'created_by_user_id' => $this->authorizedUser->id,
        'updated_by_user_id' => $this->authorizedUser->id,
    ]);

    LiveStaffCopilotPlannerAgent::fake([
        [
            'action' => 'tool_calls',
            'tool_calls' => [
                [
                    'tool_name' => 'query_metrics',
                    'arguments' => [
                        'metric' => 'finance_collection_summary',
                        'filters' => ['semester' => 'current'],
                        'group_by' => ['program'],
                        'options' => [
                            'include_rows' => false,
                            'limit' => 500,
                        ],
                    ],
                    'reason' => 'The prompt asks for outstanding tuition grouped by program.',
                ],
            ],
            'answer_intent' => 'finance_collection_summary',
            'question' => null,
            'safe_error_code' => null,
            'reason' => null,
        ],
    ])->preventStrayPrompts();

    LiveStaffCopilotFinalAnswerAgent::fake([
        [
            'status' => 'failed',
            'answer' => "raw_provider_response: {\"secret\":\"sk-live-provider-test\"}\nselect * from students;",
            'referenced_tool_call_ids' => ['tool-call-1'],
            'source_references' => [
                [
                    'source_report' => 'finance.reporting.collection-progress',
                    'source_reference_policy' => 'report_summary_with_filters',
                ],
            ],
            'confidence' => [
                'level' => 'none',
                'basis' => 'provider_unavailable',
            ],
            'limitations' => [],
            'clarification_question' => null,
            'safe_error_code' => 'provider_invocation_failed',
        ],
    ])->preventStrayPrompts();

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
                'breakdowns' => [],
                'meta' => [],
            ]);
    });

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Can you show outstanding tuition by program for this term?',
        ])
        ->assertRedirect(route('ai.copilot.index'));

    $run = AiChatRun::query()->firstOrFail();

    $streamResponse = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $streamResponse->assertStreamed();

    expect($streamResponse->streamedContent())
        ->toContain('event: run.failed')
        ->toContain('The connected AI provider was unavailable, so the run stopped safely.')
        ->not->toContain('raw_provider_response')
        ->not->toContain('select *')
        ->not->toContain('sk-live-provider-test');

    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();
    $toolCall = AiToolCall::query()->firstOrFail();
    $failedEvent = AiRunEvent::query()
        ->where('ai_chat_run_id', $run->id)
        ->where('event_type', 'run.failed')
        ->firstOrFail();

    expect($assistantMessage->redacted_content)
        ->toBe('The connected AI provider was unavailable, so the run stopped safely.')
        ->and($toolCall->status)->toBe('completed')
        ->and($failedEvent->redacted_payload['audit_evidence']['terminal']['safe_error_code'])->toBe('provider_invocation_failed')
        ->and($failedEvent->redacted_payload['audit_evidence']['terminal']['reason'])->toBe('provider_failure')
        ->and($failedEvent->redacted_payload['audit_evidence']['access_layers']['connected_provider_used'])->toBeTrue()
        ->and($failedEvent->redacted_payload['audit_evidence']['source_references'][0]['source_report'])->toBe('finance.reporting.collection-progress');

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('messages.1.answer.status', AiChatRun::STATUS_FAILED)
            ->where('messages.1.answer.terminal_state.kind', 'failed')
            ->where('messages.1.answer.terminal_state.is_retryable', true)
            ->where('messages.1.answer.safe_error_code', 'provider_invocation_failed')
            ->where('messages.1.answer.content_markdown', 'The connected AI provider was unavailable, so the run stopped safely.')
            ->where('messages.1.answer.summary.outstanding_total', 300)
            ->where('messages.1.answer.source_references.0.source_report', 'finance.reporting.collection-progress'));
});

it('keeps previous completed answers intact when a later run fails safely', function () {
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
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $completedRun = AiChatRun::query()->firstOrFail();

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $completedRun))
        ->streamedContent();

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Write an email to all students',
        ]);

    $failedRun = AiChatRun::query()->latest('id')->firstOrFail();

    $streamResponse = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $failedRun));

    $streamResponse->assertStreamed();

    expect($streamResponse->streamedContent())
        ->toContain('event: run.failed')
        ->not->toContain('select *')
        ->not->toContain('raw_provider_response');

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('messages', 4)
            ->where('messages.1.answer.status', 'completed')
            ->where('messages.1.answer.summary.outstanding_total', 300)
            ->where('messages.3.answer.terminal_state.kind', 'unsupported')
            ->where('messages.3.answer.safe_error_code', 'unsupported_staff_question')
            ->where('messages.3.answer.terminal_state.safe_error_code', 'unsupported_staff_question')
            ->where('messages.3.answer.content_markdown', 'I cannot answer that in Staff Copilot yet. It only supports query-only questions over approved Swinx data.'));
});

it('renders cancelled runs with stable terminal metadata after reload', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Current semester outstanding tuition by program là bao nhiêu?',
        ]);

    $run = AiChatRun::query()->firstOrFail();

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.runs.cancel', $run))
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('info', 'AI copilot run cancelled.');

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('active_run', null)
            ->where('messages.1.run_status', AiChatRun::STATUS_CANCELLED)
            ->where('messages.1.answer.status', AiChatRun::STATUS_CANCELLED)
            ->where('messages.1.answer.terminal_state.kind', 'cancelled')
            ->where('messages.1.answer.safe_error_code', 'run_cancelled')
            ->where('messages.1.answer.terminal_state.safe_error_code', 'run_cancelled')
            ->where('messages.1.answer.content_markdown', 'This run was cancelled before a complete answer was produced.'));
});
