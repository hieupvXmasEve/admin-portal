<?php

declare(strict_types=1);

use App\Mcp\Servers\SwinxMcpServer;
use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpAuditRecorder;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Tools\GetEntityProfileMcpTool;
use App\Mcp\Tools\QueryMetricsMcpTool;
use App\Mcp\Tools\SearchEntitiesMcpTool;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Services\PermissionService;
use App\Shared\Contracts\Academic\AiAcademicStudentProfileReader;
use App\Shared\Contracts\Finance\AiFinanceStudentProfileReader;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hcm = Campus::factory()->create(['code' => 'HCM']);
    $this->hn = Campus::factory()->create(['code' => 'HN']);
    $this->role = Role::factory()->create(['code' => 'mcp_staff', 'name' => 'MCP Staff']);
    $this->studentSourceId = 4242;

    // [userId]['all'] => permission codes. The campus resolver reads the all-campus AI scope
    // at campusId=null, and — because the MCP path is sessionless — the per-section gate
    // (Gate::allows, which looks up session('current_campus_id') = null) reads the same bucket.
    $this->permissionMap = [];

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null): array {
            return $this->permissionMap[$user->id][$campusId ?? 'all'] ?? [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

if (! function_exists('mcpGrantCampusRole')) {
    function mcpGrantCampusRole(User $user, Campus $campus, Role $role): void
    {
        DB::table('campus_user_roles')->insert([
            'user_id' => $user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * Build an opaque entity_ref the way search_entities does — bound to campus + catalog version
 * + a 24h expiry, NOT to any user or session — so profile resolution can be exercised
 * session-free over MCP.
 */
function makeStudentEntityRef(int $sourceId, Campus $campus): string
{
    return Crypt::encryptString(json_encode([
        'entity_type' => 'student',
        'source_id' => $sourceId,
        'campus_id' => $campus->id,
        'catalog_version' => EntityCatalog::VERSION,
        'scope_rule' => 'current_campus_only',
        'issued_at' => now()->toISOString(),
    ], JSON_THROW_ON_ERROR));
}

function callGetEntityProfile(User $actor, array $arguments, string $clientId = 'client-mcp-1'): TestResponse
{
    $actor->withAccessToken(new AccessToken([
        'oauth_client_id' => $clientId,
        'oauth_user_id' => $actor->id,
        'oauth_scopes' => ['mcp:use'],
    ]));

    return SwinxMcpServer::actingAs($actor, 'api')->tool(GetEntityProfileMcpTool::class, $arguments);
}

function mockProfileReaders(): void
{
    test()->mock(AiAcademicStudentProfileReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('identity')->andReturn([
            'student_code' => 'AUS24001',
            'display_name' => 'NGUYEN VAN A',
            'campus_code' => 'HCM',
            'program_code' => 'BIT',
            'status' => 'active',
        ]);
        $mock->shouldReceive('academicSummary')->andReturn(['cumulative_gpa' => 3.5]);
        $mock->shouldReceive('enrollments')->andReturn([]);
        $mock->shouldReceive('attendanceSummary')->andReturn([]);
        $mock->shouldReceive('lifecycleActions')->andReturn([]);
    });

    test()->mock(AiFinanceStudentProfileReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('financeSummary')->andReturn(['balance' => 0]);
    });
}

$profileArgs = function (string $entityRef, array $sections, ?int $campusId = null): array {
    return array_filter([
        'entity_type' => 'student',
        'entity_ref' => $entityRef,
        'sections' => $sections,
        'options' => ['limit' => 5],
        'campus_id' => $campusId,
    ], fn ($value) => $value !== null);
};

it('lists get_entity_profile under its canonical name, marks it read-only, and registers all three tools', function () {
    $tool = app(GetEntityProfileMcpTool::class);
    $array = $tool->toArray();

    expect($tool->name())->toBe('get_entity_profile')
        ->and($array['name'])->toBe('get_entity_profile')
        ->and($array['annotations'])->toMatchArray(['readOnlyHint' => true])
        ->and(array_keys($array['inputSchema']['properties']))
        ->toContain('entity_type', 'entity_ref', 'sections', 'options', 'campus_id');

    // All three canonical tools are registered and the ping stub is gone.
    $tools = (new ReflectionClass(SwinxMcpServer::class))->getDefaultProperties()['tools'];
    expect($tools)->toBe([
        QueryMetricsMcpTool::class,
        SearchEntitiesMcpTool::class,
        GetEntityProfileMcpTool::class,
    ]);
});

it('returns permitted sections plus a hidden-sections list and writes one MCP audit row', function () use ($profileArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    // Holds AI-metrics + student view (covers the identity section) but NOT the finance section.
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    mockProfileReaders();

    $entityRef = makeStudentEntityRef($this->studentSourceId, $this->hcm);

    $response = callGetEntityProfile(
        $actor,
        $profileArgs($entityRef, ['identity', 'finance_summary'], $this->hcm->id),
        'client-profile',
    );

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee([
            '"tool":"get_entity_profile"',
            '"status":"partial"',
            '"student_code":"AUS24001"',
            'student.finance_summary',
            '"campus_ids":['.$this->hcm->id.']',
        ])
        // The withheld section's data must not appear in the returned payload.
        ->assertDontSee('"balance"');

    $row = AiToolCall::query()->mcp()->sole();

    expect(AiToolCall::query()->mcp()->count())->toBe(1)
        ->and($row->channel)->toBe(AiToolCall::CHANNEL_MCP)
        ->and($row->actor_user_id)->toBe($actor->id)
        ->and($row->mcp_client_id)->toBe('client-profile')
        ->and($row->tool_name)->toBe('get_entity_profile')
        ->and($row->status)->toBe('partial')
        ->and($row->permission_result)->toBe('partial')
        ->and($row->hidden_sections)->toBe(['student.finance_summary'])
        ->and($row->ai_conversation_id)->toBeNull()
        ->and($row->ai_agent_trace_id)->toBeNull();
});

it('resolves the entity reference session-free and keeps it campus-gated on resolve', function () use ($profileArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    mcpGrantCampusRole($actor, $this->hn, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    $this->mock(AiAcademicStudentProfileReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('identity');
    });

    // The reference is bound to HCM; resolving it under an HN scope must be refused — the
    // reference is re-checked against the resolved campus, not the issuing user/session.
    $entityRef = makeStudentEntityRef($this->studentSourceId, $this->hcm);

    $response = callGetEntityProfile($actor, $profileArgs($entityRef, ['identity'], $this->hn->id));

    $response->assertHasErrors()
        ->assertSee('forbidden_by_campus_scope');

    expect(AiToolCall::query()->mcp()->sole()->safe_error_code)->toBe('forbidden_by_campus_scope');
});

it('denies the whole profile when the student-view permission is missing', function () use ($profileArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    // AI-metrics only: passes the first gate but lacks student view → every section is withheld.
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics'];

    $this->mock(AiAcademicStudentProfileReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('identity');
    });

    $entityRef = makeStudentEntityRef($this->studentSourceId, $this->hcm);

    $response = callGetEntityProfile($actor, $profileArgs($entityRef, ['identity', 'finance_summary'], $this->hcm->id));

    $response->assertHasErrors()
        ->assertSee('forbidden_by_permission');

    expect(AiToolCall::query()->mcp()->sole())
        ->permission_result->toBe('denied')
        ->status->toBe('denied');
});

it('refuses a non-active account even with a valid token', function () use ($profileArgs) {
    $actor = User::factory()->inactive()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    $this->mock(AiAcademicStudentProfileReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('identity');
    });

    $entityRef = makeStudentEntityRef($this->studentSourceId, $this->hcm);

    $response = callGetEntityProfile($actor, $profileArgs($entityRef, ['identity'], $this->hcm->id));

    $response->assertHasErrors()
        ->assertSee('forbidden_inactive_account');

    expect(AiToolCall::query()->mcp()->sole()->safe_error_code)->toBe('forbidden_inactive_account');
});

it('reaches data only through the dispatcher, resolvers, and recorder', function () {
    $allowed = [
        McpActorResolver::class,
        McpCampusResolver::class,
        ToolDispatcher::class,
        McpAuditRecorder::class,
        StudentProfileSectionCatalog::class,
        Factory::class,
    ];

    $constructor = (new ReflectionClass(GetEntityProfileMcpTool::class))->getConstructor();

    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();
        expect($type)->not->toBeNull()
            ->and($type->getName())->toBeIn($allowed);
    }
});
