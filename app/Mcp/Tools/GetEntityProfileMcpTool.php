<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpAuditRecorder;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Support\McpToolException;
use App\Models\User;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Modules\AI\Support\Tools\EntityProfileResult;
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
 * Controlled MCP server wrapper for the read-only `get_entity_profile` capability (ADR-0008/0010).
 *
 * Like {@see QueryMetricsMcpTool}, it resolves the OAuth actor (active-status gate), resolves
 * the campus scope, dispatches through {@see ToolDispatcher} — the only data path, so this
 * tool never touches a model or query — writes one standalone MCP audit row, and maps the
 * dispatcher result to structured JSON evidence (or a safe MCP error). Gating is the
 * AI-metrics permission plus the student-view permission plus **per-section** permissions:
 * the dispatched tool returns only permitted sections and reports withheld ones in the
 * hidden-sections list, so a partial result is still a success (text), not an error.
 *
 * The opaque `entity_ref` is resolved session-free — it is bound to campus + expiry + catalog
 * version (not the issuing user) and re-checked for the student-view permission and the
 * resolved campus on every resolve, so holding a reference is never a way around access
 * control.
 *
 * The base class would derive `get-entity-profile-mcp-tool` from the class name, so the
 * canonical name `get_entity_profile` is pinned via `$name`. The `campus_id` argument is
 * consumed by the campus resolver and stripped before dispatch (the profile plan only allows
 * entity_type/entity_ref/sections/options); the full arguments are still recorded for audit.
 */
#[IsReadOnly]
#[Title('Get entity profile')]
class GetEntityProfileMcpTool extends Tool
{
    protected string $name = 'get_entity_profile';

    protected string $description = 'Return allowlisted student profile sections (identity, academic summary, enrollments, attendance, finance summary, lifecycle actions) from an opaque entity_ref produced by search_entities, on behalf of the signed-in staff member. Read-only; returns only the sections you are permitted to see plus a hidden-sections list for what was withheld, with evidence (source, scope, permissions). Never returns contact PII or raw source records.';

    public function __construct(
        private readonly McpActorResolver $actorResolver,
        private readonly McpCampusResolver $campusResolver,
        private readonly ToolDispatcher $dispatcher,
        private readonly McpAuditRecorder $auditRecorder,
        private readonly StudentProfileSectionCatalog $catalog,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entity_type' => $schema->string()
                ->description('The entity type to profile. Only "student" is supported in v1.')
                ->enum(['student'])
                ->required(),
            'entity_ref' => $schema->string()
                ->description('The opaque entity reference returned by search_entities. Bound to a campus, catalog version, and 24-hour expiry; re-checked for permission and campus on resolve.')
                ->required(),
            'sections' => $schema->array()
                ->items($schema->string()->enum($this->catalog->sectionKeys()))
                ->description('Profile sections to return (e.g. ["identity","finance_summary"]). Each is gated by its own permission; sections you lack are reported under hidden_sections.')
                ->required(),
            'options' => $schema->object()
                ->description('Bounded options, e.g. {"limit":5}. Maximum 10; raw or row-level output is not permitted.'),
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

        // campus_id is the scoping argument, not part of the profile plan (which only allows
        // entity_type/entity_ref/sections/options). Strip it before dispatch; audit keeps the
        // original arguments.
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

    private function respond(EntityProfileResult $result): Response
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

        // A `partial` status (some sections permitted, others withheld) is still a successful
        // result carrying the granted sections plus the hidden-sections list.
        return Response::text($this->encode($this->evidenceShape($snapshot, $result)));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function evidenceShape(array $snapshot, EntityProfileResult $result): array
    {
        return [
            'tool' => $snapshot['tool'] ?? $this->name(),
            'entity_type' => $snapshot['entity_type'] ?? null,
            'status' => $result->status(),
            'value' => [
                'requested_sections' => $snapshot['requested_sections'] ?? [],
                'returned_sections' => $snapshot['returned_sections'] ?? [],
                'sections' => $snapshot['sections'] ?? [],
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
                'entity_catalog_version' => $snapshot['entity_catalog_version'] ?? null,
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

        $result = EntityProfileResult::denied(
            $this->name(),
            $this->catalog->toolSchemaVersion(),
            $this->catalog->version(),
            EntityCatalog::VERSION,
            $this->entityType($arguments['entity_type'] ?? null),
            $this->requestedSections($arguments['sections'] ?? null),
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

    private function entityType(mixed $entityType): ?string
    {
        return is_string($entityType) && $entityType !== '' ? $entityType : null;
    }

    /**
     * @return list<string>
     */
    private function requestedSections(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        return $this->catalog->normalizeSections(array_values(array_map('strval', $sections)));
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
