# Design

## Domain Model

This story introduces entity search as a safe semantic layer beside the existing
MetricCatalog. The AI layer continues to plan and validate tool calls; domain
modules execute allowlisted reads.

Primary concepts:

- `EntityCatalog`
    - Code-defined catalog for entity search.
    - Owns `entity-catalog:v1`, `search_entities:v1`, accepted entity keys,
      aliases, readable search-result fields, search fields, domain permission,
      campus scope rule, max result limit, source reader, and hidden sections.
- `EntityDefinition`
    - Value object or array shape for one catalog entry.
    - Defines `key`, `aliases`, `business_meaning`, `model`, `source_reader`,
      `required_permission`, `campus_scope_rule`, `search_fields`,
      `result_fields`, `max_results`, `source_reference_policy`, and
      `hidden_sections`.
- `SearchEntitiesTool`
    - Internal AI tool named `search_entities`.
    - Parses tool arguments, validates entity types and filters against
      EntityCatalog, checks permission and campus scope, calls entity resolvers,
      records tool-call audit, and returns `EntitySearchResult`.
- `EntitySearchResult`
    - Stable result object for completed, denied, partial, and failed entity
      search.
    - Mirrors the QueryMetricsResult discipline: no exceptions or raw source
      payloads leak to the caller.
- `EntitySearchResolverRegistry`
    - Maps entity keys to resolver implementations.
    - Keeps ToolDispatcher generic as more tools are added.
- `AiAcademicEntitySearchReader`
    - New shared contract under `App\Shared\Contracts\Academic`.
    - Provides source reads for the first entity types that live in the Academic
      model surface: students, programs, semesters, and course offerings.
    - Implemented by a thin Academic-module adapter or query class; the AI module
      should not own broad cross-domain Eloquent searches directly.
- `ScopedEntityReference`
    - Browser-visible reference for a result candidate.
    - Must not expose raw database primary keys.
    - Encodes entity type, source id, campus scope, catalog version, and expiry or
      issue timestamp in a signed/encrypted token that later stories can decode
      only after re-checking actor permission and campus scope.
- `StaffCopilotAgentRunner`
    - Extends deterministic planning to choose `search_entities` for entity lookup
      questions.
    - Still uses only internal tools and no live provider calls in this story.

Initial EntityCatalog v1:

| Entity key        | User aliases                  | Source model / meaning              | Required permission    | Scope rule            | Search strategy                                                                | Safe result fields                                                                                 |
| ----------------- | ----------------------------- | ----------------------------------- | ---------------------- | --------------------- | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------- |
| `student`         | `student`, `sinh vien`, `sv`  | `App\Models\Student`                | `view_student`         | current campus only   | exact/prefix `student_id`; bounded normalized `full_name` match                | `entity_ref`, `student_code`, `display_name`, `program_code`, `intake_semester_code`, `status`     |
| `program`         | `program`, `major`, `nganh`   | `App\Models\Program`                | `view_program`         | global reference data | exact/prefix `code`; bounded normalized `name` match                           | `entity_ref`, `code`, `name`                                                                       |
| `semester`        | `semester`, `term`, `ky`      | `App\Models\Semester`               | `view_semester`        | global reference data | exact/prefix `code`; bounded normalized `name` match; active semester marker   | `entity_ref`, `code`, `name`, `is_active`, `start_date`, `end_date`                                |
| `course_offering` | `class`, `section`, `lop hoc` | `App\Models\CourseOffering` section | `view_course_offering` | current campus only   | exact/prefix `section_code`; optional unit/semester joins through source query | `entity_ref`, `section_code`, `unit_code`, `unit_title`, `semester_code`, `course_status`, `label` |

Business rules:

- Staff must pass the staff AI surface gate before the copilot can submit a
  question.
- Each entity resolver must also check the entity-specific permission.
- Lack of one entity permission denies or hides only that entity type; it must
  not broaden access to other entity types.
- `student` and `course_offering` searches are current-campus only in v1.
- `program` and `semester` are reference-data searches and do not return roster
  or student membership.
- Student result fields are intentionally narrow. Email, phone, national id,
  date of birth, address, current address, parent/emergency-contact fields,
  admission notes, advisor notes, attendance, assessment, finance, wallet, and
  defer details are hidden.
- Course-offering result fields do not include roster rows, lecturer private
  details, attendance, grades, or assessment details.
- Query must be normalized, length-bounded, and escaped before any source read.
- Exact identifiers may be accepted at shorter lengths; broad fuzzy search must
  require a safer minimum length.
- Default result limit is 5. Absolute max result limit is 10.
- A result set that hits the limit must include a truncation warning and a
  source reference explaining which entity/search fields were used.
- Entity search returns candidates only. Detailed profile reads are deferred to
  `AI-MOD-007-student-profile-sections`.

## Application Flow

Staff copilot page load remains the same as `AI-MOD-005`.

Entity-search message submission:

```text
POST /ai/copilot/messages
  -> SubmitStaffCopilotMessageRequest authorizes view_ai_metrics
  -> RunStaffCopilotMessageAction
      -> records user message
      -> starts AiAgentTrace with prompt/entity/tool versions
      -> StaffCopilotAgentRunner detects an entity lookup intent
      -> ToolDispatcher dispatches search_entities
      -> SearchEntitiesTool validates SearchEntitiesPlan against EntityCatalog
      -> per-entity resolver checks permission and campus scope
      -> resolver calls AiAcademicEntitySearchReader
      -> SearchEntitiesTool records AiToolCall audit
      -> StaffCopilotAnswer renders candidate-list answer
      -> assistant message is recorded with hidden sections and safe errors
      -> trace status becomes completed, denied, partial, or failed
  -> redirect back to ai.copilot.index
```

Permission-denied flow:

```text
staff has view_ai_metrics but lacks view_student
  -> search_entities validates the student entity request
  -> resolver is not executed
  -> EntitySearchResult denied with safe_error_code forbidden_by_permission
  -> hidden_sections includes student
  -> tool-call audit records permission_result denied
  -> answer explains that matching student records are hidden by permission
```

Cross-campus flow:

```text
student exists in another campus
  -> resolver applies current campus scope before matching
  -> result_count is 0
  -> source references record current campus scope
  -> answer says no current-campus candidate was found
  -> no cross-campus identity hint is returned
```

Unsupported / unsafe input flow:

```text
unknown entity type, too-short query, unsupported filter, or over-limit request
  -> validation fails before source execution
  -> EntitySearchResult failed or denied with deterministic safe_error_code
  -> tool-call audit records redacted arguments and status
  -> answer shows safe guidance without internal exception details
```

## Interface Contract

No new public or portal API route is expected. The existing staff copilot routes
remain:

```text
GET  /ai/copilot          name: ai.copilot.index
POST /ai/copilot/messages name: ai.copilot.messages.store
```

Tool definition:

```json
{
    "name": "search_entities",
    "schema_version": "search_entities:v1",
    "catalog_version": "entity-catalog:v1",
    "permission": "view_ai_metrics",
    "description": "Search allowlisted Swinx entities by safe keyword and scoped identifiers."
}
```

Tool arguments:

```json
{
    "query": "AUS24001",
    "entity_types": ["student"],
    "filters": {
        "semester": "current",
        "program_id": 12
    },
    "options": {
        "limit": 5
    }
}
```

Validation rules:

- `query`: required string, trimmed, max 100 characters.
- Broad text search requires at least 3 non-space characters.
- Exact code patterns may use shorter values when the entity definition accepts
  them.
- `entity_types`: required non-empty array; each value must be an EntityCatalog
  key or alias.
- `filters`: optional object; allowed keys depend on the entity type.
- `filters.campus_id`: not accepted from browser, prompt, or tool arguments;
  current campus is resolved from server context.
- `filters.semester`: optional `current` or integer semester id only where the
  entity definition accepts it.
- `filters.program_id`: optional integer only for student/course-offering
  searches.
- `options.limit`: optional integer, min 1, max 10, default 5.
- `options.include_profiles`, `include_rows`, `include_hidden_fields`, raw SQL,
  table names, column names, relationships, or arbitrary include lists are not
  accepted.

Tool result:

```json
{
    "allowed": true,
    "tool": "search_entities",
    "tool_schema_version": "search_entities:v1",
    "catalog_version": "entity-catalog:v1",
    "normalized_query": "aus24001",
    "entity_types": ["student"],
    "result_limit": 5,
    "result_count": 1,
    "results": [
        {
            "entity_type": "student",
            "entity_ref": "signed-opaque-token",
            "label": "AUS24001 - Nguyen Van A",
            "safe_identifiers": {
                "student_code": "AUS24001",
                "program_code": "BIT",
                "intake_semester_code": "2024-T1"
            },
            "match_reason": "student_id_exact",
            "source_reference": {
                "report": "academic.entity-search.student",
                "fields": ["student_id"],
                "scope": "current_campus"
            }
        }
    ],
    "campus_scope_snapshot": {
        "campus_ids": [1],
        "scope_rule": "current_campus_only"
    },
    "hidden_sections": [],
    "warnings": [],
    "confidence": {
        "level": "high",
        "basis": "exact_identifier_match"
    },
    "safe_error_code": null,
    "status": "completed"
}
```

Safe error codes:

- `invalid_entity_search_schema`
- `unsupported_entity_type`
- `unsupported_entity_filter`
- `invalid_entity_filter_value`
- `query_too_short`
- `result_limit_exceeded`
- `forbidden_by_permission`
- `forbidden_by_campus_scope`
- `entity_resolver_missing`
- `entity_reference_encoding_failed`
- `source_query_failed`
- `source_result_truncated`

Staff copilot answer behavior:

- Completed search with results: show a short candidate list with entity type,
  safe label, match reason, source, scope, and confidence.
- Completed search with no results: say no current-scope candidate was found and
  suggest narrowing by code/name/semester.
- Partial search: show allowed entity results and clearly list hidden entity
  types.
- Denied/failed search: show safe error text and no internal exception details.

## Data Model

No migration is expected for this story.

Existing AI audit tables used:

- `ai_conversations`
- `ai_messages`
- `ai_agent_traces`
- `ai_tool_calls`

Existing domain data read surfaces:

- `students` through `App\Models\Student` / Academic source reader.
- `programs` through `App\Models\Program` / Academic source reader.
- `semesters` through `App\Models\Semester` / Academic source reader.
- `course_offerings` through `App\Models\CourseOffering` / Academic source
  reader.

If implementation discovery proves scoped entity references need durable server
state, pause before adding a table. The preferred v1 shape is a signed/encrypted
reference token that can be decoded later and re-authorized without storing a
new resolved-entity row. Durable conversation context is deferred to
`AI-MOD-008-conversation-context`.

## UI / Platform Impact

This story updates the existing internal staff copilot page only.

UI requirements:

- Keep `resources/js/pages/AI/StaffCopilot/Index.vue` as the page surface.
- Use `<script setup lang="ts">`, Inertia `useForm`, named routes, lucide icons,
  and existing `@/components/ui` primitives.
- Add compact rendering for entity-search answer evidence: candidate list,
  entity type, safe label, match reason, source reference, scope, hidden
  sections, warnings, and confidence.
- Do not add a separate entity-search page unless implementation discovery
  proves the chat page cannot present the evidence safely.
- Do not add student portal, lecturer portal, public API, queue worker,
  broadcast channel, MCP server, vector store, or scheduled job behavior.

## Observability

Every `search_entities` tool attempt must create correlated product audit
records:

- conversation id;
- user id and current campus id;
- user message id;
- trace id;
- prompt version;
- entity catalog version;
- tool schema version;
- tool name;
- redacted tool arguments;
- requested entity types;
- normalized query hash or redacted normalized query;
- permission result per entity type;
- campus scope snapshot;
- hidden sections;
- source references;
- result count;
- result limit;
- redacted result summary;
- trace/tool status;
- duration in milliseconds;
- safe error code.

Operational logs may capture unexpected exceptions, but product audit tables
remain the source of truth for AI search evidence.

## Alternatives Considered

1. Build EntityCatalog and `search_entities` as an internal deterministic tool.
    - Recommended because it extends the existing ToolRegistry/ToolDispatcher
      model, keeps tests deterministic, and proves permission/campus/PII
      boundaries before profile reads.
2. Add `get_entity_profile` in the same story.
    - Rejected for this slice because profile sections require section-level
      permission design and more PII risk. That is explicitly assigned to
      `AI-MOD-007-student-profile-sections`.
3. Let the browser submit entity ids or QueryPlan-like entity JSON.
    - Rejected because entity resolution must be server-planned, scoped, and
      audited.
4. Use embeddings, vector search, or provider web search for entity matching.
    - Rejected because Swinx already has structured identifiers and the first
      phase must remain source-of-truth and deterministic.
5. Expose raw database ids in search results.
    - Rejected because scoped opaque references are safer for later profile and
      conversation-context stories.
6. Keep ToolRegistry hard-coded to a single concrete tool class.
    - Rejected for this story because adding a second tool needs a shared
      registry/dispatcher shape that can support later compare/profile tools
      without duplicating dispatch logic.
