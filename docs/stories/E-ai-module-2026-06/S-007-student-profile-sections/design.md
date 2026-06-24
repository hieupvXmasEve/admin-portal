# Design

## Domain Model

This story introduces profile-section reads as a safe capability beside entity
search. The AI layer validates and coordinates access; domain modules own the
business-data reads.

Primary concepts:

- `StudentProfileSectionCatalog`
    - Code-defined catalog for student profile sections.
    - Owns `student-profile-sections:v1`, `get_entity_profile:v1`, accepted
      section keys, section aliases, required permissions, source reader,
      campus rule, safe output fields, max row limits, source references, and
      hidden-section labels.
- `StudentProfileSectionDefinition`
    - Value object or array shape for one section definition.
    - Defines `key`, `aliases`, `business_meaning`, `required_permission`,
      `source_reader`, `campus_scope_rule`, `safe_fields`, `max_rows`,
      `source_report`, `source_reference_policy`, and `hidden_section`.
- `GetEntityProfileTool`
    - Internal AI tool named `get_entity_profile`.
    - Parses tool arguments, decodes entity references, validates requested
      sections, checks permission/campus scope, calls section readers, records
      audit, and returns `EntityProfileResult`.
- `EntityProfileResult`
    - Stable result object for completed, partial, denied, and failed profile
      reads.
    - Mirrors `QueryMetricsResult` and `EntitySearchResult`: safe errors, no raw
      exceptions, no raw source payload leaks.
- `EntityReferenceResolver`
    - Decodes opaque `entity_ref` values created by `search_entities`.
    - Re-checks entity type, source id, campus id, catalog version, scope rule,
      issue time, actor permission, and current campus before use.
    - Should be reusable by `AI-MOD-008-conversation-context`.
- `AiAcademicStudentProfileReader`
    - Shared Academic contract for student identity, academic summary,
      enrollments, attendance summary, and lifecycle actions.
    - Implemented by thin Academic-module readers that reuse existing summary
      services/queries where possible.
- `AiFinanceStudentProfileReader`
    - Shared Finance contract for finance summary.
    - Implemented by thin Finance-module readers that reuse Finance Student 360
      read models and current campus/all-campus visibility rules.
- `LiveStaffCopilotAgent`
    - Updates live planner context so the provider can request
      `get_entity_profile` after an entity candidate has been resolved.
    - Server validation remains authoritative; model-proposed profile reads are
      untrusted until ToolDispatcher accepts them.

Business rules:

- Staff must pass `view_ai_metrics` before the copilot can submit profile-section
  requests.
- `get_entity_profile` only accepts `entity_ref` references created by
  `search_entities`.
- `student` is the only supported entity type in v1.
- The tool must re-check `view_student` before resolving any student profile.
- Each requested section also checks its own permission.
- Lack of one section permission hides only that section; allowed sections may
  still be returned with partial status.
- The AI module must not read profile data with broad cross-domain Eloquent
  queries. It calls Academic/Finance shared contracts and records their source
  references.
- Profile reads are bounded summaries, not detail exports.
- Current-campus scope is mandatory unless an accepted implementation update
  explicitly maps to an existing all-campus permission.
- Final answers must cite section source reports, campus scope, data freshness,
  hidden sections, warnings, and confidence.

Initial section catalog:

| Section key          | Aliases                               | Required permission             | Source reader                                       | Max rows | Hidden label                 |
| -------------------- | ------------------------------------- | ------------------------------- | --------------------------------------------------- | -------- | ---------------------------- |
| `identity`           | `basic`, `profile`, `student`         | `view_student`                  | `AiAcademicStudentProfileReader::identity`          | 1        | `student.identity`           |
| `academic_summary`   | `academic`, `progress`, `gpa`         | `view_student_summary`          | `AiAcademicStudentProfileReader::academicSummary`   | 1        | `student.academic_summary`   |
| `enrollments`        | `courses`, `registrations`, `classes` | `view_student_summary`          | `AiAcademicStudentProfileReader::enrollments`       | 10       | `student.enrollments`        |
| `attendance_summary` | `attendance`                          | `view_student_summary`          | `AiAcademicStudentProfileReader::attendanceSummary` | 10       | `student.attendance_summary` |
| `finance_summary`    | `finance`, `balance`, `tuition`       | `view_finance_student_overview` | `AiFinanceStudentProfileReader::financeSummary`     | 1        | `student.finance_summary`    |
| `lifecycle_actions`  | `actions`, `defer`, `history`         | `view_student_action`           | `AiAcademicStudentProfileReader::lifecycleActions`  | 10       | `student.lifecycle_actions`  |

## Application Flow

Profile-section follow-up:

```text
POST /ai/copilot/messages
  -> SubmitStaffCopilotMessageRequest authorizes view_ai_metrics
  -> RunStaffCopilotMessageAction records user message/run event
  -> StaffCopilotAgentRunner chooses runtime mode
      -> deterministic or live provider planning
      -> model/deterministic planner proposes get_entity_profile
      -> ToolDispatcher dispatches get_entity_profile
      -> GetEntityProfileTool validates entity_ref and requested sections
      -> EntityReferenceResolver re-checks entity type/campus/catalog/actor
      -> SectionCatalog resolves section definitions
      -> section readers check permissions and source scope
      -> allowed sections are read through Academic/Finance contracts
      -> denied sections are reported in hidden_sections
      -> AiToolCall audit records status, arguments, result summary, sources
      -> StaffCopilotAnswer renders summary and hidden-section notices
      -> run events stream status/tool/message updates through SSE
```

Mixed-permission flow:

```text
staff has view_ai_metrics, view_student, view_student_summary
but lacks view_finance_student_overview
  -> identity and academic_summary sections execute
  -> finance_summary does not execute
  -> result status is partial
  -> hidden_sections includes student.finance_summary
  -> answer explains finance data is hidden by permission
```

Invalid reference flow:

```text
tool arguments include student_id or a forged/stale entity_ref
  -> validation fails before source execution
  -> EntityProfileResult failed with safe_error_code invalid_entity_reference
  -> tool-call audit records redacted arguments
  -> answer asks staff to search/select the student candidate again
```

Cross-campus flow:

```text
entity_ref points to a student outside the actor current campus
  -> reference validation fails before section reader execution
  -> result denied or failed with forbidden_by_campus_scope
  -> no cross-campus identity hint is returned
```

## Interface Contract

No new public or portal route is expected. Existing staff copilot routes remain:

```text
GET  /ai/copilot
POST /ai/copilot/messages
GET  /ai/copilot/runs/{run}/events
POST /ai/copilot/runs/{run}/cancel
POST /ai/copilot/runs/{run}/retry
```

Tool definition:

```json
{
    "name": "get_entity_profile",
    "schema_version": "get_entity_profile:v1",
    "profile_catalog_version": "student-profile-sections:v1",
    "entity_catalog_version": "entity-catalog:v1",
    "permission": "view_ai_metrics",
    "description": "Read allowlisted profile sections for a resolved Swinx entity."
}
```

Tool arguments:

```json
{
    "entity_ref": "opaque-token-from-search_entities",
    "entity_type": "student",
    "sections": ["identity", "academic_summary", "finance_summary"],
    "options": {
        "max_rows_per_section": 5
    }
}
```

Validation rules:

- `entity_ref`: required string; must decrypt/verify into a supported scoped
  reference.
- `entity_type`: required; only `student` is accepted in v1.
- `sections`: required non-empty array of accepted section keys or aliases.
- `options.max_rows_per_section`: optional integer, min 1, max 10, default 5.
- Browser/model arguments must not include `student_id`, `source_id`, `campus_id`,
  `sql`, `table`, `columns`, `relationships`, `include`, `include_rows`,
  `include_hidden_fields`, `raw`, or `all_fields`.
- The tool must not infer sections from arbitrary text after validation. Planner
  logic maps user intent to catalog section keys before dispatch.

Tool result:

```json
{
    "allowed": true,
    "tool": "get_entity_profile",
    "tool_schema_version": "get_entity_profile:v1",
    "profile_catalog_version": "student-profile-sections:v1",
    "entity_catalog_version": "entity-catalog:v1",
    "entity_type": "student",
    "entity_ref": "opaque-token-from-search_entities",
    "requested_sections": ["identity", "academic_summary", "finance_summary"],
    "returned_sections": ["identity", "academic_summary"],
    "sections": {
        "identity": {
            "student_code": "AUS24001",
            "display_name": "Nguyen Van A",
            "status": "active",
            "academic_status": "good_standing",
            "campus": { "code": "HCM", "name": "Ho Chi Minh" },
            "program": { "code": "BIT", "name": "Business Information Technology" },
            "intake": { "semester_code": "2026-T1" },
            "gc": { "current_level": 2, "starting_level": 1 }
        },
        "academic_summary": {
            "active_registrations": 4,
            "completed_courses": 12,
            "credits_attempted": 72,
            "credits_earned": 66,
            "current_gpa": 3.1,
            "cumulative_gpa": 3.0,
            "academic_standing": "good"
        }
    },
    "source_references": [
        {
            "source_report": "academic.student-profile.identity",
            "section": "identity",
            "scope": "current_campus",
            "freshness": "read_at_request_time"
        }
    ],
    "campus_scope_snapshot": {
        "campus_ids": [1],
        "scope_rule": "current_campus_only"
    },
    "hidden_sections": ["student.finance_summary"],
    "warnings": [],
    "confidence": {
        "level": "high",
        "basis": "source_reader_summary"
    },
    "safe_error_code": null,
    "status": "partial"
}
```

Safe error codes:

- `invalid_profile_request_schema`
- `unsupported_profile_entity_type`
- `invalid_entity_reference`
- `expired_entity_reference`
- `entity_reference_catalog_mismatch`
- `unsupported_profile_section`
- `unsupported_profile_option`
- `forbidden_by_permission`
- `forbidden_by_campus_scope`
- `profile_reader_missing`
- `source_query_failed`
- `source_result_truncated`
- `unsafe_profile_include`

Staff Copilot answer behavior:

- Completed result: summarize requested sections with short, cited facts.
- Partial result: summarize allowed sections and list hidden sections without
  leaking hidden facts.
- Denied result: explain that the profile section is hidden by permission.
- Failed result: show safe recovery guidance, usually asking staff to search and
  select the student again.

## Data Model

No migration is expected for this story.

Existing AI audit/runtime tables should be used:

- `ai_conversations`
- `ai_messages`
- `ai_agent_traces`
- `ai_tool_calls`
- `ai_provider_usages`
- `ai_chat_runs`
- `ai_run_events`

Implementation discovery may add columns only if existing audit records cannot
preserve required profile-section metadata. Any migration requires explicit
justification in the story trace before code lands.

## UI / Platform Impact

The Staff Copilot browser surface should reuse existing chat answer patterns.

Expected UI impact:

- Capability props include `get_entity_profile` and
  `student-profile-sections:v1`.
- Answer rendering can show section summaries, hidden sections, source
  references, warnings, and confidence.
- SSE run events may include a `tool_running` or `tool_completed` event for
  `get_entity_profile`.

No student portal, lecturer portal, public API route, queue worker, WebSocket,
or external side-effect platform is required.

## Observability

Product audit must show:

- runtime mode;
- provider/model ids where a live provider is used;
- entity reference validation result;
- requested sections, returned sections, hidden sections, and denied sections;
- section permission results;
- campus scope snapshot;
- source references and freshness;
- redacted tool arguments;
- redacted section result summary;
- safe error code, confidence, warnings, duration, and record counts.

Audit must not store or display hidden fields, raw rows, provider credentials,
gateway payloads, attachments, private notes, or unsupported PII fields.

## Alternatives Considered

1. Let the live provider ask `search_entities` with `include_profiles`.
    - Rejected. `AI-MOD-006` intentionally blocks `include_profiles` so entity
      search remains candidate-only and PII-safe.
2. Add a generic `get_entity_profile` that reads any entity type.
    - Rejected for this slice. Student profile reads are already high-risk and
      need proof before program/class/semester profiles are added.
3. Return the existing web student profile payload wholesale.
    - Rejected. Existing web surfaces include fields that are too broad for an AI
      answer contract, such as contact, address, notes, parent, raw attendance,
      raw score, and finance detail payloads.
4. Add profile reads directly inside `LiveStaffCopilotAgent`.
    - Rejected. ToolDispatcher must remain the only execution path for business
      data reads so deterministic and live provider modes share validation,
      permission, audit, and safe-error behavior.
