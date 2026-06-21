<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiToolCall;
use App\Services\PermissionService;
use App\Shared\Contracts\Academic\AiAcademicMetricReader;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
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
        ->assertInertiaFlash('success', 'AI copilot answer generated.');

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
        ->assertInertiaFlash('error', 'AI copilot could not answer this question yet.');

    $trace = AiAgentTrace::query()->firstOrFail();

    expect($trace->status)->toBe('failed')
        ->and($trace->safe_error_code)->toBe('unsupported_staff_question')
        ->and($trace->step_count)->toBe(0)
        ->and(AiToolCall::query()->count())->toBe(0)
        ->and(AiMessage::query()->where('role', 'assistant')->firstOrFail()->hidden_sections)->toBe(['unsupported_staff_question']);
});
