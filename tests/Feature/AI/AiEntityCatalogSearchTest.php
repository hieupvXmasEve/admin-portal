<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Modules\AI\Support\Tools\ToolRegistry;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->fullAccessUser = User::factory()->create();
    $this->aiOnlyUser = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HN']);
    $this->program = Program::factory()->create([
        'code' => 'BIT',
        'name' => 'Business Information Technology',
    ]);
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);
    $this->unit = Unit::factory()->create([
        'code' => 'ICT101',
        'name' => 'Information Systems Foundations',
    ]);
    $this->student = Student::factory()->create([
        'student_id' => 'AUS24001',
        'full_name' => 'Nguyen Van A',
        'email' => 'secret.student@example.test',
        'phone' => '0900000001',
        'national_id' => '0123456789',
        'address' => 'Hidden address',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 2024,
        'status' => 'active',
    ]);
    $this->otherCampusStudent = Student::factory()->create([
        'student_id' => 'AUS99999',
        'full_name' => 'Remote Campus Match',
        'campus_id' => $this->otherCampus->id,
        'program_id' => $this->program->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 2024,
    ]);
    $this->courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'section_code' => 'BIT-A1',
        'course_status' => 'in_progress',
    ]);

    session([
        '_token' => 'ai-entity-search-test-token',
        'current_campus_id' => $this->campus->id,
    ]);

    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null): array {
            if ($user->id === $this->fullAccessUser->id && $campusId === $this->campus->id) {
                return [
                    'view_ai_metrics',
                    'view_student',
                    'view_program',
                    'view_semester',
                    'view_course_offering',
                ];
            }

            if ($user->id === $this->aiOnlyUser->id && $campusId === $this->campus->id) {
                return ['view_ai_metrics'];
            }

            return [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('exposes entity catalog definitions and registers the search entities tool', function () {
    $catalog = app(EntityCatalog::class);
    $registry = app(ToolRegistry::class);

    expect($catalog->version())->toBe('entity-catalog:v1')
        ->and($catalog->toolSchemaVersion())->toBe('search_entities:v1')
        ->and($catalog->entityKeys())->toBe(['student', 'program', 'semester', 'course_offering'])
        ->and($catalog->normalizeEntityTypes(['student', 'class', 'term']))->toBe(['student', 'course_offering', 'semester'])
        ->and($catalog->entity('student'))->toMatchArray([
            'key' => 'student',
            'required_permission' => 'view_student',
            'campus_scope_rule' => 'current_campus_only',
            'max_results' => 5,
        ])
        ->and($registry->toolNames())->toBe(['query_metrics', 'search_entities'])
        ->and($registry->has('search_entities'))->toBeTrue()
        ->and($registry->definition('search_entities')?->toArray())->toMatchArray([
            'name' => 'search_entities',
            'schema_version' => 'search_entities:v1',
            'permission' => 'view_ai_metrics',
        ]);
});

it('searches current campus student candidates with safe fields and audit evidence', function () {
    $audit = app(AiAuditRecorder::class);
    $conversation = $audit->startConversation($this->fullAccessUser, $this->campus);
    $trace = $audit->startTrace($conversation, null, [
        'catalog_version' => 'entity-catalog:v1',
        'tool_schema_version' => 'search_entities:v1',
    ]);

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AUS24001',
            'entity_types' => ['student'],
            'options' => ['limit' => 5],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
        trace: $trace,
    );

    $payload = $result->toArray();
    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($payload)->toMatchArray([
        'allowed' => true,
        'tool' => 'search_entities',
        'tool_schema_version' => 'search_entities:v1',
        'catalog_version' => 'entity-catalog:v1',
        'normalized_query' => 'aus24001',
        'entity_types' => ['student'],
        'result_limit' => 5,
        'result_count' => 1,
        'campus_scope_snapshot' => [
            'campus_ids' => [$this->campus->id],
            'scope_rule' => 'current_campus_only',
        ],
        'warnings' => [],
        'confidence' => [
            'level' => 'high',
            'basis' => 'exact_identifier_match',
        ],
        'safe_error_code' => null,
        'status' => 'completed',
    ])
        ->and($payload['results'][0])->toMatchArray([
            'entity_type' => 'student',
            'label' => 'AUS24001 - NGUYEN VAN A',
            'safe_identifiers' => [
                'student_code' => 'AUS24001',
                'program_code' => 'BIT',
                'intake_semester_code' => '2026-T1',
                'status' => 'active',
            ],
            'match_reason' => 'student_id_exact',
        ])
        ->and($payload['results'][0]['entity_ref'])->not->toBe((string) $this->student->id)
        ->and(is_numeric($payload['results'][0]['entity_ref']))->toBeFalse()
        ->and($encodedPayload)->not->toContain('secret.student@example.test')
        ->and($encodedPayload)->not->toContain('0900000001')
        ->and($encodedPayload)->not->toContain('0123456789')
        ->and($encodedPayload)->not->toContain('Hidden address');

    $toolCall = AiToolCall::query()->where('tool_name', 'search_entities')->firstOrFail();

    expect($toolCall->status)->toBe('completed')
        ->and($toolCall->permission_result)->toBe('allowed')
        ->and($toolCall->record_count)->toBe(1)
        ->and($toolCall->safe_error_code)->toBeNull()
        ->and($toolCall->source_references[0]['source_report'])->toBe('academic.entity-search.student')
        ->and($toolCall->redacted_result_summary['results'][0]['safe_identifiers']['student_code'])->toBe('AUS24001');
});

it('denies missing entity permission and rejects unsafe entity arguments before search execution', function () {
    $permissionDenied = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AUS24001',
            'entity_types' => ['student'],
        ],
        actor: $this->aiOnlyUser,
        campus: $this->campus,
    );

    expect($permissionDenied->toArray())->toMatchArray([
        'allowed' => false,
        'tool' => 'search_entities',
        'entity_types' => ['student'],
        'result_count' => 0,
        'results' => [],
        'hidden_sections' => ['student'],
        'safe_error_code' => 'forbidden_by_permission',
        'status' => 'denied',
    ]);

    $unsafeFilter = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AUS24001',
            'entity_types' => ['student'],
            'filters' => ['campus_id' => $this->otherCampus->id],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    expect($unsafeFilter->toArray())->toMatchArray([
        'allowed' => false,
        'tool' => 'search_entities',
        'result_count' => 0,
        'results' => [],
        'safe_error_code' => 'unsupported_entity_filter',
        'status' => 'failed',
    ]);

    $unsafeInclude = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AUS24001',
            'entity_types' => ['student'],
            'include_profiles' => true,
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    expect($unsafeInclude->toArray())->toMatchArray([
        'allowed' => false,
        'tool' => 'search_entities',
        'result_count' => 0,
        'results' => [],
        'safe_error_code' => 'invalid_entity_search_schema',
        'status' => 'failed',
    ]);
});

it('fails unsupported entity, too-short query, and over-limit requests safely', function () {
    $unsupportedEntity = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AUS24001',
            'entity_types' => ['invoice'],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    $tooShortQuery = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AU',
            'entity_types' => ['student'],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    $overLimit = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'AUS24001',
            'entity_types' => ['student'],
            'options' => ['limit' => 11],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    expect($unsupportedEntity->toArray())->toMatchArray([
        'allowed' => false,
        'tool' => 'search_entities',
        'result_count' => 0,
        'results' => [],
        'safe_error_code' => 'unsupported_entity_type',
        'status' => 'failed',
    ])
        ->and($tooShortQuery->toArray())->toMatchArray([
            'allowed' => false,
            'tool' => 'search_entities',
            'result_count' => 0,
            'results' => [],
            'safe_error_code' => 'query_too_short',
            'status' => 'failed',
        ])
        ->and($overLimit->toArray())->toMatchArray([
            'allowed' => false,
            'tool' => 'search_entities',
            'result_count' => 0,
            'results' => [],
            'safe_error_code' => 'result_limit_exceeded',
            'status' => 'failed',
        ]);
});

it('hides cross campus student matches without leaking identity hints', function () {
    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'search_entities',
        arguments: [
            'query' => 'Remote Campus Match',
            'entity_types' => ['student'],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    $payload = $result->toArray();
    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($payload)->toMatchArray([
        'allowed' => true,
        'result_count' => 0,
        'results' => [],
        'status' => 'completed',
    ])
        ->and($encodedPayload)->not->toContain('AUS99999')
        ->and($encodedPayload)->not->toContain('Remote Campus Match');
});

it('answers staff copilot entity lookup prompts through search entities', function () {
    $this->actingAs($this->fullAccessUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-entity-search-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Find student AUS24001',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot answer generated.');

    expect(AiConversation::query()->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(1);

    $trace = AiAgentTrace::query()->firstOrFail();
    $toolCall = AiToolCall::query()->firstOrFail();

    expect($trace->status)->toBe('completed')
        ->and($trace->step_count)->toBe(1)
        ->and($toolCall->tool_name)->toBe('search_entities')
        ->and($toolCall->status)->toBe('completed')
        ->and($toolCall->redacted_arguments)->toMatchArray([
            'query' => 'AUS24001',
            'entity_types' => ['student'],
        ])
        ->and($toolCall->redacted_result_summary['results'][0]['safe_identifiers']['student_code'])->toBe('AUS24001');

    $this->actingAs($this->fullAccessUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('messages', 2)
            ->where('messages.1.role', 'assistant')
            ->where('messages.1.answer.status', 'completed')
            ->where('messages.1.answer.entity_results.0.safe_identifiers.student_code', 'AUS24001')
            ->where('messages.1.answer.entity_results.0.match_reason', 'student_id_exact')
            ->where('messages.1.answer.source_references.0.source_report', 'academic.entity-search.student'));
});
