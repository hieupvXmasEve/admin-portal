<?php

declare(strict_types=1);

use App\Mcp\Servers\SwinxMcpServer;
use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpAuditRecorder;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Tools\SearchEntitiesMcpTool;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Shared\Contracts\Academic\AiAcademicEntitySearchReader;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Passport\AccessToken;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->hcm = Campus::factory()->create(['code' => 'HCM']);
    $this->hn = Campus::factory()->create(['code' => 'HN']);
    $this->role = Role::factory()->create(['code' => 'mcp_staff', 'name' => 'MCP Staff']);

    // [userId]['all'] => permission codes. The campus resolver reads the all-campus AI scope
    // at campusId=null, and — because the MCP path is sessionless — the per-entity-type gate
    // (Gate::allows, which looks up session('current_campus_id') = null) reads the same bucket.
    $this->permissionMap = [];

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(function (int $userId, ?int $campusId = null): array {
            return $this->permissionMap[$userId][$campusId ?? 'all'] ?? [];
        });

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

if (! function_exists('mcpGrantCampusRole')) {
    /**
     * Grant the user a role at a campus (the permitted-campus association the resolver reads).
     */
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
 * Attach a Passport access token (carrying the OAuth client id) to the actor and drive the
 * MCP server over the `api` guard, exercising actor → campus → dispatch → audit end to end.
 */
function callSearchEntities(User $actor, array $arguments, string $clientId = 'client-mcp-1'): TestResponse
{
    $actor->withAccessToken(new AccessToken([
        'oauth_client_id' => $clientId,
        'oauth_user_id' => $actor->id,
        'oauth_scopes' => ['mcp:use'],
    ]));

    return SwinxMcpServer::actingAs($actor, 'api')->tool(SearchEntitiesMcpTool::class, $arguments);
}

function mockStudentSearch(): void
{
    test()->mock(AiAcademicEntitySearchReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('searchStudents')->andReturn([
            [
                'entity_type' => 'student',
                'source_id' => 4242,
                'label' => 'AUS24001 - NGUYEN VAN A',
                'safe_identifiers' => [
                    'student_code' => 'AUS24001',
                    'program_code' => 'BIT',
                    'intake_semester_code' => '2026-T1',
                    'status' => 'active',
                ],
                'match_reason' => 'student_id_exact',
            ],
        ]);
        $mock->shouldReceive('searchPrograms')->andReturn([]);
        $mock->shouldReceive('searchSemesters')->andReturn([]);
        $mock->shouldReceive('searchCourseOfferings')->andReturn([]);
    });
}

$studentArgs = fn (?int $campusId = null): array => array_filter([
    'query' => 'AUS24001',
    'entity_types' => ['student'],
    'options' => ['limit' => 5],
    'campus_id' => $campusId,
], fn ($value) => $value !== null);

it('lists search_entities under its canonical name, marks it read-only, and registers it on the server', function () {
    $tool = app(SearchEntitiesMcpTool::class);
    $array = $tool->toArray();

    expect($tool->name())->toBe('search_entities')
        ->and($array['name'])->toBe('search_entities')
        ->and($array['annotations'])->toMatchArray(['readOnlyHint' => true])
        ->and(array_keys($array['inputSchema']['properties']))
        ->toContain('query', 'entity_types', 'filters', 'options', 'campus_id');

    $tools = (new ReflectionClass(SwinxMcpServer::class))->getDefaultProperties()['tools'];
    expect($tools)->toContain(SearchEntitiesMcpTool::class);
});

it('returns structured JSON with evidence and writes exactly one MCP audit row for an in-scope campus', function () use ($studentArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    mockStudentSearch();

    $response = callSearchEntities($actor, $studentArgs($this->hcm->id), 'client-evidence');

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee([
            '"tool":"search_entities"',
            '"normalized_query":"aus24001"',
            '"record_count":1',
            '"student_code":"AUS24001"',
            'academic.entity-search.student',
            '"campus_ids":['.$this->hcm->id.']',
            '"permission_result":"allowed"',
        ]);

    $row = AiToolCall::query()->mcp()->sole();

    expect(AiToolCall::query()->mcp()->count())->toBe(1)
        ->and($row->channel)->toBe(AiToolCall::CHANNEL_MCP)
        ->and($row->actor_user_id)->toBe($actor->id)
        ->and($row->mcp_client_id)->toBe('client-evidence')
        ->and($row->tool_name)->toBe('search_entities')
        ->and($row->status)->toBe('completed')
        ->and($row->permission_result)->toBe('allowed')
        ->and($row->record_count)->toBe(1)
        ->and($row->safe_error_code)->toBeNull()
        ->and($row->campus_scope_snapshot['campus_ids'])->toBe([$this->hcm->id])
        ->and($row->ai_conversation_id)->toBeNull()
        ->and($row->ai_agent_trace_id)->toBeNull();
});

it('never leaks the raw source id in the returned entity reference', function () use ($studentArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    mockStudentSearch();

    callSearchEntities($actor, $studentArgs($this->hcm->id))
        ->assertOk()
        // The opaque entity_ref must not expose the underlying source id (4242).
        ->assertDontSee('"entity_ref":"4242"');
});

it('returns the campus-scope safe error for an explicit campus the actor has no role at', function () use ($studentArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    $this->mock(AiAcademicEntitySearchReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('searchStudents');
    });

    $response = callSearchEntities($actor, $studentArgs($this->hn->id));

    $response->assertHasErrors()
        ->assertSee('forbidden_by_campus_scope');

    $row = AiToolCall::query()->mcp()->sole();
    expect($row->safe_error_code)->toBe('forbidden_by_campus_scope')
        ->and($row->status)->toBe('denied');
});

it('auto-resolves the only permitted campus when campus_id is omitted', function () use ($studentArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    mockStudentSearch();

    $response = callSearchEntities($actor, $studentArgs());

    $response->assertOk()
        ->assertSee('"campus_ids":['.$this->hcm->id.']');

    expect(AiToolCall::query()->mcp()->sole()->campus_scope_snapshot['campus_ids'])
        ->toBe([$this->hcm->id]);
});

it('returns a clarification (not a deny) when campus_id is omitted and several campuses are permitted', function () use ($studentArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    mcpGrantCampusRole($actor, $this->hn, $this->role);
    // Authorized at both, but not an all-campus holder → ambiguous campus.
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    $response = callSearchEntities($actor, $studentArgs());

    $response->assertHasNoErrors()
        ->assertSee([
            'clarification_required',
            (string) $this->hcm->id,
            (string) $this->hn->id,
        ]);

    expect(AiToolCall::query()->mcp()->sole()->safe_error_code)->toBe('clarification_required');
});

it('gates per entity type and reports the denied type under hidden_sections', function () use ($studentArgs) {
    $actor = User::factory()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    // Holds the base AI-metrics permission but NOT the per-entity-type student-view permission.
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics'];

    $this->mock(AiAcademicEntitySearchReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('searchStudents');
    });

    $response = callSearchEntities($actor, $studentArgs($this->hcm->id));

    $response->assertHasErrors()
        ->assertSee([
            'forbidden_by_permission',
            'student',
        ]);

    expect(AiToolCall::query()->mcp()->sole())
        ->permission_result->toBe('denied')
        ->safe_error_code->toBe('forbidden_by_permission')
        ->and(AiToolCall::query()->mcp()->sole()->hidden_sections)->toBe(['student']);
});

it('refuses a non-active account even with a valid token', function () use ($studentArgs) {
    $actor = User::factory()->inactive()->create();
    mcpGrantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_student'];

    $this->mock(AiAcademicEntitySearchReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('searchStudents');
    });

    $response = callSearchEntities($actor, $studentArgs($this->hcm->id));

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
        EntityCatalog::class,
        Factory::class,
    ];

    $constructor = (new ReflectionClass(SearchEntitiesMcpTool::class))->getConstructor();

    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();
        expect($type)->not->toBeNull()
            ->and($type->getName())->toBeIn($allowed);
    }
});
