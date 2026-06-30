<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpAuditRecorder;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Support\McpToolException;
use App\Models\User;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\Tools\EntitySearchResult;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Controlled MCP server wrapper for the read-only `search_entities` capability (ADR-0008/0010).
 *
 * Like {@see QueryMetricsMcpTool}, it resolves the OAuth actor (active-status gate), resolves
 * the campus scope (explicit / single / all-campus / clarification), dispatches through
 * {@see ToolDispatcher} — the only data path, so this tool never touches a model or query —
 * writes one standalone MCP audit row, and maps the dispatcher result to structured JSON
 * evidence (or a safe MCP error). Gating is finer than the registry default: it is applied
 * **per entity type** (e.g. the student-view permission) inside the dispatched tool, which
 * surfaces denied types as the hidden-sections list.
 *
 * The base class would derive `search-entities-mcp-tool` from the class name, so the canonical
 * name `search_entities` is pinned via `$name`. The `campus_id` argument is consumed by the
 * campus resolver and stripped before dispatch (the entity search plan only allows
 * query/entity_types/filters/options); the full arguments are still recorded for audit.
 */
#[IsReadOnly]
#[Title('Search entities')]
class SearchEntitiesMcpTool extends Tool
{
    protected string $name = 'search_entities';

    protected string $description = 'Search allowlisted academic entity candidates (students, programs, semesters, course offerings) on behalf of the signed-in staff member, scoped to the campuses they are permitted to see. Read-only; returns a bounded candidate list with opaque entity references and evidence (source, scope, permissions). Never returns contact PII or raw source records.';

    public function __construct(
        private readonly McpActorResolver $actorResolver,
        private readonly McpCampusResolver $campusResolver,
        private readonly ToolDispatcher $dispatcher,
        private readonly McpAuditRecorder $auditRecorder,
        private readonly EntityCatalog $catalog,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The free-text query to match candidates against (e.g. a student code or name fragment). 3-100 characters.')
                ->required(),
            'entity_types' => $schema->array()
                ->items($schema->string()->enum($this->catalog->entityKeys()))
                ->description('Entity types to search (e.g. ["student"]). Each is gated by its own view permission; types you lack are reported under hidden_sections.')
                ->required(),
            'filters' => $schema->object()
                ->description('Entity-specific filters (e.g. {"semester":"current","program_id":12}). A "campus_id" filter is not permitted; campus is resolved from campus_id below.'),
            'options' => $schema->object()
                ->description('Bounded search options, e.g. {"limit":5}. Maximum 10; row-level or profile output is not permitted.'),
            'campus_id' => $schema->integer()
                ->description('Campus to scope to. Omit when you are tied to a single campus or hold all-campus AI scope; required to disambiguate when you have several.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $startedAt = microtime(true);
        $arguments = $request->all();

        try {
            $actor = $this->actorResolver->resolve();
            $scope = $this->campusResolver->resolve($actor, $this->campusArgument($arguments));
        } catch (McpToolException $exception) {
            return $this->refuse($exception, $arguments, $startedAt);
        }

        // campus_id is the scoping argument, not part of the entity search plan (which only
        // allows query/entity_types/filters/options). Strip it before dispatch; audit keeps
        // the original arguments.
        $planArguments = Arr::except($arguments, ['campus_id']);

        $result = $this->dispatcher->dispatch(
            $this->name(),
            $planArguments,
            $actor,
            null,
            null,
            $scope,
        );

        $this->auditRecorder->record($actor, $this->clientId($actor), $result, $arguments, $startedAt);

        return $this->respond($result);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function campusArgument(array $arguments): ?int
    {
        $campusId = $arguments['campus_id'] ?? null;

        return is_numeric($campusId) ? (int) $campusId : null;
    }

    private function respond(EntitySearchResult $result): Response
    {
        $snapshot = $result->toArray();
        $status = $result->status();

        if ($status === 'denied' || $status === 'failed') {
            return Response::error($this->encode([
                'error' => [
                    'safe_error_code' => $result->safeErrorCode(),
                    'permission_result' => $result->permissionResult(),
                    'hidden_sections' => $snapshot['hidden_sections'] ?? [],
                    'campus_scope' => $snapshot['campus_scope_snapshot'] ?? ['campus_ids' => []],
                ],
            ]));
        }

        return Response::text($this->encode($this->evidenceShape($snapshot, $result)));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function evidenceShape(array $snapshot, EntitySearchResult $result): array
    {
        return [
            'tool' => $snapshot['tool'] ?? $this->name(),
            'status' => $result->status(),
            'value' => [
                'normalized_query' => $snapshot['normalized_query'] ?? null,
                'entity_types' => $snapshot['entity_types'] ?? [],
                'result_limit' => $snapshot['result_limit'] ?? null,
                'results' => $snapshot['results'] ?? [],
                'record_count' => $result->recordCount(),
            ],
            'evidence' => [
                'source_references' => $result->sourceReferences(),
                'campus_scope' => $snapshot['campus_scope_snapshot'] ?? ['campus_ids' => []],
                'permission_result' => $result->permissionResult(),
                'hidden_sections' => $snapshot['hidden_sections'] ?? [],
                'confidence' => $snapshot['confidence'] ?? [],
                'warnings' => $snapshot['warnings'] ?? [],
                'tool_schema_version' => $snapshot['tool_schema_version'] ?? null,
                'catalog_version' => $snapshot['catalog_version'] ?? null,
                'safe_error_code' => $result->safeErrorCode(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function refuse(McpToolException $exception, array $arguments, float $startedAt): Response
    {
        // The active-status gate refuses a token whose user object still resolves, so the
        // refused attempt is still attributable — write the one audit row for this call.
        $actor = $this->auth->guard('api')->user();

        $result = EntitySearchResult::denied(
            $this->name(),
            $this->catalog->toolSchemaVersion(),
            $this->catalog->version(),
            $this->normalizedQuery($arguments['query'] ?? ''),
            $this->entityTypes($arguments['entity_types'] ?? null),
            0,
            $exception->campusScopeSnapshot(),
            [],
            $exception->safeErrorCode(),
        );

        if ($actor instanceof User) {
            $this->auditRecorder->record($actor, $this->clientId($actor), $result, $arguments, $startedAt);
        }

        $body = [
            'safe_error_code' => $exception->safeErrorCode(),
            'permission_result' => $exception->permissionResult(),
            'campus_scope' => $exception->campusScopeSnapshot(),
            'message' => $exception->getMessage(),
        ] + $exception->meta();

        if ($exception->isClarification()) {
            // A clarification is a "tell me which campus" prompt, not a denial.
            return Response::text($this->encode(['status' => 'clarification_required'] + $body));
        }

        return Response::error($this->encode(['error' => $body]));
    }

    private function normalizedQuery(mixed $query): string
    {
        if (! is_string($query)) {
            return '';
        }

        return str($query)->lower()->squish()->toString();
    }

    /**
     * @return list<string>
     */
    private function entityTypes(mixed $entityTypes): array
    {
        if (! is_array($entityTypes) || $entityTypes === []) {
            return [];
        }

        return $this->catalog->normalizeEntityTypes(array_values(array_map('strval', $entityTypes)));
    }

    private function clientId(User $actor): string
    {
        $token = $actor->currentAccessToken();
        $clientId = $token?->oauth_client_id ?? null;

        return is_scalar($clientId) ? (string) $clientId : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
