<?php

declare(strict_types=1);

use App\Mcp\Servers\SwinxMcpServer;
use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpAuditRecorder;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Support\PermissionMcpCampusResolver;
use App\Mcp\Tools\QueryMetricsMcpTool;
use App\Models\Campus;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Services\PermissionService;
use App\Shared\Contracts\Finance\AiFinanceMetricReader;
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
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);
    $this->role = Role::factory()->create(['code' => 'mcp_staff', 'name' => 'MCP Staff']);

    // [userId][campusId|'all'] => permission codes, consumed by both the campus resolver
    // (all-campus detection at campusId=null) and the validator (metric gating at campusId).
    $this->permissionMap = [];

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null): array {
            return $this->permissionMap[$user->id][$campusId ?? 'all'] ?? [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

/**
 * Grant the user a role at a campus (the permitted-campus association the resolver reads).
 */
function grantCampusRole(User $user, Campus $campus, Role $role): void
{
    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Attach a Passport access token (carrying the OAuth client id) to the actor and drive the
 * MCP server over the `api` guard, exercising actor → campus → dispatch → audit end to end.
 */
function callQueryMetrics(User $actor, array $arguments, string $clientId = 'client-mcp-1'): TestResponse
{
    $actor->withAccessToken(new AccessToken([
        'oauth_client_id' => $clientId,
        'oauth_user_id' => $actor->id,
        'oauth_scopes' => ['mcp:use'],
    ]));

    return SwinxMcpServer::actingAs($actor, 'api')->tool(QueryMetricsMcpTool::class, $arguments);
}

function mockCollectionProgress(): void
{
    test()->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldReceive('collectionProgress')->andReturn([
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
}

$financeArgs = fn (?int $campusId = null): array => array_filter([
    'metric' => 'finance_collection_summary',
    'filters' => ['semester' => 'current', 'fee_type' => 'tuition_term'],
    'group_by' => ['program'],
    'campus_id' => $campusId,
], fn ($value) => $value !== null);

it('lists query_metrics under its canonical name, marks it read-only, and registers it on the server', function () {
    $tool = app(QueryMetricsMcpTool::class);
    $array = $tool->toArray();

    expect($tool->name())->toBe('query_metrics')
        ->and($array['name'])->toBe('query_metrics')
        ->and($array['annotations'])->toMatchArray(['readOnlyHint' => true])
        ->and(array_keys($array['inputSchema']['properties']))
        ->toContain('metric', 'filters', 'group_by', 'options', 'campus_id');

    $tools = (new ReflectionClass(SwinxMcpServer::class))->getDefaultProperties()['tools'];
    expect($tools)->toContain(QueryMetricsMcpTool::class);
});

it('returns structured JSON with evidence and writes exactly one MCP audit row for an in-scope campus', function () use ($financeArgs) {
    $actor = User::factory()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id][$this->hcm->id] = ['view_ai_metrics', 'view_finance_reporting'];

    mockCollectionProgress();

    $response = callQueryMetrics($actor, $financeArgs($this->hcm->id), 'client-evidence');

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee([
            '"tool":"query_metrics"',
            '"metric":"finance_collection_summary"',
            '"record_count":2',
            'finance.reporting.collection-progress',
            '"campus_ids":['.$this->hcm->id.']',
            '"permission_result":"allowed"',
        ]);

    $row = AiToolCall::query()->mcp()->sole();

    expect(AiToolCall::query()->mcp()->count())->toBe(1)
        ->and($row->channel)->toBe(AiToolCall::CHANNEL_MCP)
        ->and($row->actor_user_id)->toBe($actor->id)
        ->and($row->mcp_client_id)->toBe('client-evidence')
        ->and($row->tool_name)->toBe('query_metrics')
        ->and($row->status)->toBe('completed')
        ->and($row->permission_result)->toBe('allowed')
        ->and($row->record_count)->toBe(2)
        ->and($row->safe_error_code)->toBeNull()
        ->and($row->campus_scope_snapshot)->toBe(['campus_ids' => [$this->hcm->id]])
        ->and($row->ai_conversation_id)->toBeNull()
        ->and($row->ai_agent_trace_id)->toBeNull();
});

it('returns the campus-scope safe error for an explicit campus the actor has no role at', function () use ($financeArgs) {
    $actor = User::factory()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id][$this->hcm->id] = ['view_ai_metrics', 'view_finance_reporting'];

    $response = callQueryMetrics($actor, $financeArgs($this->hn->id));

    $response->assertHasErrors()
        ->assertSee('forbidden_by_campus_scope');

    $row = AiToolCall::query()->mcp()->sole();
    expect($row->safe_error_code)->toBe('forbidden_by_campus_scope')
        ->and($row->status)->toBe('denied');
});

it('auto-resolves the only permitted campus when campus_id is omitted', function () use ($financeArgs) {
    $actor = User::factory()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id][$this->hcm->id] = ['view_ai_metrics', 'view_finance_reporting'];

    mockCollectionProgress();

    $response = callQueryMetrics($actor, $financeArgs());

    $response->assertOk()
        ->assertSee('"campus_ids":['.$this->hcm->id.']');

    expect(AiToolCall::query()->mcp()->sole()->campus_scope_snapshot)
        ->toBe(['campus_ids' => [$this->hcm->id]]);
});

it('returns a clarification (not a deny) when campus_id is omitted and several campuses are permitted', function () use ($financeArgs) {
    $actor = User::factory()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    grantCampusRole($actor, $this->hn, $this->role);
    // Authorized at both, but not an all-campus holder → ambiguous campus.
    $this->permissionMap[$actor->id]['all'] = ['view_ai_metrics', 'view_finance_reporting'];

    $response = callQueryMetrics($actor, $financeArgs());

    $response->assertHasNoErrors()
        ->assertSee([
            'clarification_required',
            (string) $this->hcm->id,
            (string) $this->hn->id,
        ]);

    expect(AiToolCall::query()->mcp()->sole()->safe_error_code)->toBe('clarification_required');
});

it('spans every permitted campus for an all-campus AI scope holder who omits campus_id', function () use ($financeArgs) {
    $actor = User::factory()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    grantCampusRole($actor, $this->hn, $this->role);
    // All-campus AI scope + the metric/domain permissions resolved across all campuses.
    $this->permissionMap[$actor->id]['all'] = [
        PermissionMcpCampusResolver::ALL_CAMPUS_PERMISSION,
        'view_ai_metrics',
        'view_finance_reporting',
    ];

    mockCollectionProgress();

    $response = callQueryMetrics($actor, $financeArgs());

    $response->assertOk()
        ->assertSee('"campus_ids":['.$this->hcm->id.','.$this->hn->id.']');

    expect(AiToolCall::query()->mcp()->sole()->campus_scope_snapshot)
        ->toBe(['campus_ids' => [$this->hcm->id, $this->hn->id]]);
});

it('enforces the metric domain permission on top of the AI-metrics permission', function () use ($financeArgs) {
    $actor = User::factory()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    // Holds the base AI-metrics permission but NOT the finance domain permission.
    $this->permissionMap[$actor->id][$this->hcm->id] = ['view_ai_metrics'];

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
    });

    $response = callQueryMetrics($actor, $financeArgs($this->hcm->id));

    $response->assertHasErrors()
        ->assertSee('forbidden_by_domain_permission');

    expect(AiToolCall::query()->mcp()->sole())
        ->permission_result->toBe('denied')
        ->safe_error_code->toBe('forbidden_by_domain_permission');
});

it('refuses a non-active account even with a valid token', function () use ($financeArgs) {
    $actor = User::factory()->inactive()->create();
    grantCampusRole($actor, $this->hcm, $this->role);
    $this->permissionMap[$actor->id][$this->hcm->id] = ['view_ai_metrics', 'view_finance_reporting'];

    $this->mock(AiFinanceMetricReader::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('collectionProgress');
    });

    $response = callQueryMetrics($actor, $financeArgs($this->hcm->id));

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
        MetricCatalog::class,
        Factory::class,
    ];

    $constructor = (new ReflectionClass(QueryMetricsMcpTool::class))->getConstructor();

    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();
        expect($type)->not->toBeNull()
            ->and($type->getName())->toBeIn($allowed);
    }
});
