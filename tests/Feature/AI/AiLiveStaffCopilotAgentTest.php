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
use App\Modules\AI\Models\AiProviderUsage;
use App\Modules\AI\Models\AiToolCall;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);

    session([
        '_token' => 'ai-live-copilot-test-token',
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

it('denies provider planned metric reads when the staff user lacks the domain permission', function () {
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(function (int $userId, ?int $campusId = null): array {
            if ($userId === $this->authorizedUser->id && $campusId === $this->campus->id) {
                return ['view_ai_metrics'];
            }

            return [];
        });

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

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
                    'reason' => 'The provider can suggest the finance metric, but Swinx still checks permissions.',
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
            'status' => 'denied',
            'answer' => 'I cannot answer that finance request because you do not have access to that approved data group.',
            'referenced_tool_call_ids' => [],
            'source_references' => [],
            'confidence' => [
                'level' => 'none',
                'basis' => 'domain_permission_denied',
            ],
            'limitations' => ['Finance reporting permission is required for this metric.'],
            'clarification_question' => null,
            'safe_error_code' => 'forbidden_by_domain_permission',
        ],
    ])->preventStrayPrompts();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-live-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Can you show outstanding tuition by program for this term?',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())->toContain('event: run.failed');

    $toolCall = AiToolCall::query()->firstOrFail();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();

    expect($toolCall->tool_name)->toBe('query_metrics')
        ->and($toolCall->status)->toBe('denied')
        ->and($toolCall->permission_result)->toBe('denied')
        ->and($toolCall->safe_error_code)->toBe('forbidden_by_domain_permission')
        ->and($toolCall->redacted_arguments)->toMatchArray([
            'metric' => 'finance_collection_summary',
            'filters' => ['semester' => 'current'],
            'group_by' => ['program'],
        ])
        ->and($toolCall->redacted_result_summary['safe_error_code'])->toBe('forbidden_by_domain_permission')
        ->and($assistantMessage->redacted_content)->toContain('do not have access')
        ->and(AiProviderUsage::query()->pluck('status')->all())->toBe(['succeeded', 'succeeded']);
});

it('plans with a live provider, executes allowlisted tools, and synthesizes an audited answer', function () {
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
                    'reason' => 'The staff question asks for outstanding tuition grouped by program.',
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
            'answer' => 'Outstanding tuition is 300 across 2 students, grouped by program from the finance collection report.',
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
        ->withHeader('X-CSRF-TOKEN', 'ai-live-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Can you show outstanding tuition by program for this term?',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())->toContain('event: run.completed');

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('capabilities.live_provider_enabled', true)
            ->where('capabilities.runtime_mode', 'live_provider')
            ->where('capabilities.tool_schema_versions.query_metrics', 'query_metrics:v1')
            ->where('capabilities.tool_schema_versions.search_entities', 'search_entities:v1'));

    LiveStaffCopilotPlannerAgent::assertPrompted(fn ($prompt): bool => str_contains((string) $prompt->agent->instructions(), 'MetricCatalog')
        && str_contains((string) $prompt->agent->instructions(), 'search_entities')
        && str_contains($prompt->prompt, 'Can you show outstanding tuition by program for this term?')
        && ! str_contains($prompt->prompt, 'sk-live-provider-test'));

    LiveStaffCopilotFinalAnswerAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'finance.reporting.collection-progress')
        && str_contains($prompt->prompt, 'outstanding_total')
        && ! str_contains($prompt->prompt, 'sk-live-provider-test'));

    expect(AiConversation::query()->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'assistant')->firstOrFail()->redacted_content)
        ->toContain('Outstanding tuition is 300');

    $trace = AiAgentTrace::query()->firstOrFail();
    $toolCall = AiToolCall::query()->firstOrFail();

    expect($trace->provider)->toBe('openai')
        ->and($trace->model)->toBe('gpt-4o-mini')
        ->and($trace->prompt_version)->toBe('staff-copilot-live:v1')
        ->and($trace->status)->toBe('completed')
        ->and($trace->step_count)->toBe(1)
        ->and($trace->safe_error_code)->toBeNull()
        ->and($toolCall->tool_name)->toBe('query_metrics')
        ->and($toolCall->status)->toBe('completed')
        ->and($toolCall->redacted_arguments)->toMatchArray([
            'metric' => 'finance_collection_summary',
            'filters' => ['semester' => 'current'],
            'group_by' => ['program'],
        ])
        ->and((float) $toolCall->redacted_result_summary['summary']['outstanding_total'])->toBe(300.0)
        ->and($toolCall->source_references[0]['source_report'])->toBe('finance.reporting.collection-progress');

    expect(AiProviderUsage::query()->count())->toBe(2)
        ->and(AiProviderUsage::query()->pluck('status')->all())->toBe(['succeeded', 'succeeded'])
        ->and(AiProviderSetting::query()->firstOrFail()->last_used_at)->not->toBeNull();
});

it('asks for clarification from live provider planning without executing business data tools', function () {
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
            'action' => 'ask_clarification',
            'tool_calls' => null,
            'answer_intent' => null,
            'question' => 'Which metric should I inspect: tuition collection, fee monitor, DNG lifecycle, or academic status?',
            'missing_fields' => ['metric'],
            'safe_error_code' => null,
            'reason' => 'The prompt did not identify a bounded metric.',
        ],
    ])->preventStrayPrompts();
    LiveStaffCopilotFinalAnswerAgent::fake()->preventStrayPrompts();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-live-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Can you check this term?',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())
        ->toContain('event: run.completed')
        ->toContain('Which metric should I inspect')
        ->not->toContain('event: tool.started');

    LiveStaffCopilotFinalAnswerAgent::assertNeverPrompted();

    $trace = AiAgentTrace::query()->firstOrFail();
    $assistantMessage = AiMessage::query()->where('role', 'assistant')->firstOrFail();

    expect($trace->status)->toBe('completed')
        ->and($trace->step_count)->toBe(0)
        ->and(AiToolCall::query()->count())->toBe(0)
        ->and($assistantMessage->redacted_content)->toBe('Which metric should I inspect: tuition collection, fee monitor, DNG lifecycle, or academic status?')
        ->and(AiProviderUsage::query()->count())->toBe(1);
});

it('denies unsafe model proposed tools before source execution and without leaking raw SQL', function () {
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
                    'tool_name' => 'raw_sql',
                    'arguments' => [
                        'sql' => 'select * from students',
                        'table' => 'students',
                    ],
                    'reason' => 'Unsafe model output should be rejected by Swinx.',
                ],
            ],
            'answer_intent' => 'unsupported_data_access',
            'question' => null,
            'safe_error_code' => null,
            'reason' => null,
        ],
    ])->preventStrayPrompts();

    LiveStaffCopilotFinalAnswerAgent::fake([
        [
            'status' => 'denied',
            'answer' => 'I cannot answer that request because it is outside the allowlisted Staff Copilot tools.',
            'referenced_tool_call_ids' => [],
            'source_references' => [],
            'confidence' => [
                'level' => 'none',
                'basis' => 'unsupported_tool_denied',
            ],
            'limitations' => ['Only query_metrics and search_entities are available.'],
            'clarification_question' => null,
            'safe_error_code' => 'unsupported_tool',
        ],
    ])->preventStrayPrompts();

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
        $mock->shouldNotReceive('feeMonitor');
        $mock->shouldNotReceive('dngLifecycle');
    });

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-live-copilot-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Run SQL to show every student row',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())->toContain('event: run.failed');

    LiveStaffCopilotFinalAnswerAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'unsupported_tool')
        && ! str_contains($prompt->prompt, 'select * from students'));

    $toolCall = AiToolCall::query()->firstOrFail();
    $encodedToolCall = json_encode($toolCall->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($toolCall->tool_name)->toBe('raw_sql')
        ->and($toolCall->status)->toBe('denied')
        ->and($toolCall->permission_result)->toBe('not_evaluated')
        ->and($toolCall->safe_error_code)->toBe('unsupported_tool')
        ->and($toolCall->redacted_arguments)->toBe(['rejected_argument_keys' => ['sql', 'table']])
        ->and($encodedToolCall)->not->toContain('select * from students')
        ->and($encodedToolCall)->not->toContain('students');

    $trace = AiAgentTrace::query()->firstOrFail();

    expect($trace->status)->toBe('denied')
        ->and($trace->safe_error_code)->toBe('unsupported_tool')
        ->and(AiProviderUsage::query()->count())->toBe(2);
});

it('falls back to deterministic tool execution when the live provider fails', function () {
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

    LiveStaffCopilotPlannerAgent::fake(function (): never {
        throw new RuntimeException('provider unavailable');
    })->preventStrayPrompts();
    LiveStaffCopilotFinalAnswerAgent::fake()->preventStrayPrompts();

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
        ->withHeader('X-CSRF-TOKEN', 'ai-live-copilot-test-token')
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

    expect($response->streamedContent())
        ->toContain('event: provider.failed')
        ->toContain('event: run.completed');

    LiveStaffCopilotFinalAnswerAgent::assertNeverPrompted();

    $trace = AiAgentTrace::query()->firstOrFail();
    $toolCall = AiToolCall::query()->firstOrFail();
    $providerUsage = AiProviderUsage::query()->firstOrFail();

    expect($trace->status)->toBe('completed')
        ->and($trace->safe_error_code)->toBe('provider_invocation_failed')
        ->and($toolCall->tool_name)->toBe('query_metrics')
        ->and($toolCall->status)->toBe('completed')
        ->and($providerUsage->status)->toBe('failed')
        ->and($providerUsage->safe_error_code)->toBe('provider_invocation_failed');
});
