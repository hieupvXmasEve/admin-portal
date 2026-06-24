<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Modules\AI\Support\Tools\ToolRegistry;
use App\Services\PermissionService;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HN']);
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null): array {
            if ($user->id === $this->authorizedUser->id && $campusId === $this->campus->id) {
                return ['view_ai_metrics'];
            }

            return [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('registers the query metrics tool alongside entity search', function () {
    $registry = app(ToolRegistry::class);

    expect($registry->toolNames())->toBe(['query_metrics', 'search_entities'])
        ->and($registry->has('query_metrics'))->toBeTrue()
        ->and($registry->has('search_entities'))->toBeTrue()
        ->and($registry->definition('query_metrics')->toArray())->toMatchArray([
            'name' => 'query_metrics',
            'schema_version' => 'query_metrics:v1',
            'permission' => 'view_ai_metrics',
        ])
        ->and($registry->definition('search_entities')->toArray())->toMatchArray([
            'name' => 'search_entities',
            'schema_version' => 'search_entities:v1',
            'permission' => 'view_ai_metrics',
        ]);
});

it('dispatches finance collection metrics as bounded aggregate output and audits the completed tool call', function () {
    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('collectionProgress')
            ->once()
            ->with($this->semester->id, Mockery::on(fn (array $filters): bool => $filters['fee_type'] === 'tuition_term'))
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
                        ['key' => 'IT', 'label' => 'IT', 'student_count' => 2, 'outstanding' => 300.0],
                    ],
                ],
                'meta' => [],
            ]);
    });

    $audit = app(AiAuditRecorder::class);
    $conversation = $audit->startConversation($this->authorizedUser, $this->campus);
    $trace = $audit->startTrace($conversation, null, [
        'catalog_version' => 'metric-catalog:v1',
        'tool_schema_version' => 'query_metrics:v1',
    ]);

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'query_metrics',
        arguments: [
            'metric' => 'finance_collection_summary',
            'filters' => [
                'semester' => 'current',
                'fee_type' => 'tuition_term',
            ],
            'group_by' => ['program'],
            'options' => [
                'include_rows' => false,
                'limit' => 50,
            ],
        ],
        actor: $this->authorizedUser,
        campus: $this->campus,
        trace: $trace,
    );

    $resultPayload = $result->toArray();

    expect($resultPayload)->toMatchArray([
        'allowed' => true,
        'tool' => 'query_metrics',
        'tool_schema_version' => 'query_metrics:v1',
        'catalog_version' => 'metric-catalog:v1',
        'metric' => 'finance_collection_summary',
        'normalized_filters' => [
            'semester_id' => $this->semester->id,
            'fee_type' => 'tuition_term',
        ],
        'group_by' => ['program'],
        'campus_scope_snapshot' => [
            'campus_ids' => [$this->campus->id],
        ],
        'summary' => [
            'student_count' => 2,
            'billed_total' => 1000.0,
            'paid_total' => 700.0,
            'outstanding_total' => 300.0,
            'collection_rate' => 0.7,
        ],
        'record_count' => 2,
        'warnings' => [],
        'confidence' => [
            'level' => 'high',
            'basis' => 'source_report_parity',
        ],
        'safe_error_code' => null,
    ])
        ->and($resultPayload['groups'][0])->toMatchArray([
            'key' => 'program',
            'value' => 'IT',
            'label' => 'IT',
            'metrics' => [
                'student_count' => 2,
                'outstanding' => 300.0,
            ],
        ])
        ->and($resultPayload['source_references'][0])->toMatchArray([
            'source_report' => 'finance.reporting.collection-progress',
            'source_reference_policy' => 'report_summary_with_filters',
        ]);

    $toolCall = AiToolCall::query()->where('tool_name', 'query_metrics')->firstOrFail();

    expect($toolCall->status)->toBe('completed')
        ->and($toolCall->permission_result)->toBe('allowed')
        ->and($toolCall->record_count)->toBe(2)
        ->and($toolCall->safe_error_code)->toBeNull()
        ->and($toolCall->source_references[0]['source_report'])->toBe('finance.reporting.collection-progress')
        ->and((float) $toolCall->redacted_result_summary['summary']['outstanding_total'])->toBe(300.0);
});

it('denies invalid plans before source execution and audits the safe error', function () {
    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
    });

    $audit = app(AiAuditRecorder::class);
    $conversation = $audit->startConversation($this->unauthorizedUser, $this->campus);
    $trace = $audit->startTrace($conversation, null);

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'query_metrics',
        arguments: [
            'metric' => 'finance_collection_summary',
            'filters' => ['semester' => 'current'],
            'group_by' => [],
            'sql' => 'select * from students',
        ],
        actor: $this->unauthorizedUser,
        campus: $this->campus,
        trace: $trace,
    );

    expect($result->toArray())->toMatchArray([
        'allowed' => false,
        'tool' => 'query_metrics',
        'metric' => 'finance_collection_summary',
        'summary' => null,
        'groups' => [],
        'source_references' => [],
        'record_count' => 0,
        'confidence' => [
            'level' => 'none',
            'basis' => 'not_executed',
        ],
        'safe_error_code' => 'invalid_query_plan_schema',
    ]);

    $toolCall = AiToolCall::query()->where('tool_name', 'query_metrics')->firstOrFail();

    expect($toolCall->status)->toBe('denied')
        ->and($toolCall->permission_result)->toBe('not_evaluated')
        ->and($toolCall->record_count)->toBe(0)
        ->and($toolCall->safe_error_code)->toBe('invalid_query_plan_schema');
});

it('aggregates academic status rows without leaking student identifiers', function () {
    $this->mock(AiAcademicMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('studentStatusBySemesterExport')
            ->once()
            ->with($this->semester->id, $this->campus->id, null)
            ->andReturn(new Collection([
                [
                    'student_id' => 'SV001',
                    'full_name' => 'Sensitive One',
                    'program_name' => 'IT',
                    'status_at_selected_semester' => 'deferred',
                    'intake_semester' => '2025-T3',
                ],
                [
                    'student_id' => 'SV002',
                    'full_name' => 'Sensitive Two',
                    'program_name' => 'Business',
                    'status_at_selected_semester' => 'active',
                    'intake_semester' => '2025-T3',
                ],
            ]));
    });

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'query_metrics',
        arguments: [
            'metric' => 'academic_student_status_count',
            'filters' => ['semester' => 'current'],
            'group_by' => ['status'],
        ],
        actor: $this->authorizedUser,
        campus: $this->campus,
    );

    $payload = json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $resultPayload = $result->toArray();

    expect($resultPayload)->toMatchArray([
        'allowed' => true,
        'metric' => 'academic_student_status_count',
        'summary' => ['student_count' => 2],
        'record_count' => 2,
    ])
        ->and($resultPayload['groups'][0])->toMatchArray([
            'key' => 'status',
            'value' => 'active',
            'metrics' => ['student_count' => 1],
        ])
        ->and($resultPayload['groups'][1])->toMatchArray([
            'key' => 'status',
            'value' => 'deferred',
            'metrics' => ['student_count' => 1],
        ])
        ->and($payload)->not->toContain('SV001')
        ->and($payload)->not->toContain('SV002')
        ->and($payload)->not->toContain('Sensitive One')
        ->and($payload)->not->toContain('Sensitive Two');
});

it('dispatches fee monitor metrics from source summary and grouped rows', function () {
    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('feeMonitor')
            ->once()
            ->with($this->semester->id, Mockery::on(fn (array $filters): bool => $filters['per_page'] === 500))
            ->andReturn([
                'summary' => [
                    'missing_count' => 1,
                    'generated_count' => 1,
                    'blocked_count' => 0,
                    'paid_count' => 1,
                    'total_count' => 2,
                ],
                'rows' => new Collection([
                    [
                        'expected_source' => 'tuition_plan',
                        'generation_state' => 'missing',
                        'payment_state' => null,
                        'amount' => 1000.0,
                        'outstanding_amount' => 1000.0,
                    ],
                    [
                        'expected_source' => 'egc_tuition',
                        'generation_state' => 'generated',
                        'payment_state' => 'paid',
                        'amount' => 500.0,
                        'outstanding_amount' => 0.0,
                    ],
                ]),
            ]);
    });

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'query_metrics',
        arguments: [
            'metric' => 'finance_fee_monitor_summary',
            'filters' => ['semester' => 'current'],
            'group_by' => ['expected_fee_type'],
        ],
        actor: $this->authorizedUser,
        campus: $this->campus,
    );

    $payload = $result->toArray();

    expect($payload)->toMatchArray([
        'allowed' => true,
        'metric' => 'finance_fee_monitor_summary',
        'summary' => [
            'missing_count' => 1,
            'generated_count' => 1,
            'blocked_count' => 0,
            'paid_count' => 1,
            'total_count' => 2,
        ],
        'record_count' => 2,
        'safe_error_code' => null,
    ])
        ->and($payload['groups'][0])->toMatchArray([
            'key' => 'expected_fee_type',
            'value' => 'tuition_plan',
            'metrics' => [
                'count' => 1,
                'amount' => 1000.0,
                'outstanding_amount' => 1000.0,
            ],
        ]);
});

it('surfaces dng lifecycle truncation as a partial aggregate result', function () {
    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dngLifecycle')
            ->once()
            ->with(null, Mockery::on(fn (array $filters): bool => $filters['fee_type'] === 'tuition_term'))
            ->andReturn([
                'summary' => [
                    'total_count' => 2000,
                    'needs_attention_count' => 12,
                    'attention_amount' => 120000.0,
                ],
                'breakdowns' => [
                    'by_attention_bucket' => [
                        ['key' => 'paid_uninvoiced', 'label' => 'Paid uninvoiced', 'count' => 12, 'amount' => 120000.0],
                    ],
                ],
                'rows' => new Collection([
                    [
                        'attention_buckets' => ['paid_uninvoiced'],
                        'flow_state' => 'attention',
                        'fee_type' => 'tuition_term',
                        'amount' => 120000.0,
                    ],
                ]),
                'meta' => [
                    'truncated' => true,
                    'scan_cap' => 2000,
                ],
            ]);
    });

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'query_metrics',
        arguments: [
            'metric' => 'finance_dng_lifecycle_attention',
            'filters' => ['fee_type' => 'tuition_term'],
            'group_by' => ['attention_bucket'],
        ],
        actor: $this->authorizedUser,
        campus: $this->campus,
    );

    $payload = $result->toArray();

    expect($payload)->toMatchArray([
        'allowed' => true,
        'metric' => 'finance_dng_lifecycle_attention',
        'summary' => [
            'total_count' => 2000,
            'needs_attention_count' => 12,
            'attention_amount' => 120000.0,
        ],
        'record_count' => 2000,
        'warnings' => ['source_result_truncated'],
        'confidence' => [
            'level' => 'partial',
            'basis' => 'source_report_parity',
        ],
        'safe_error_code' => 'source_result_truncated',
        'status' => 'partial',
    ])
        ->and($payload['groups'][0])->toMatchArray([
            'key' => 'attention_bucket',
            'value' => 'paid_uninvoiced',
            'metrics' => [
                'count' => 12,
                'amount' => 120000.0,
            ],
        ]);
});
