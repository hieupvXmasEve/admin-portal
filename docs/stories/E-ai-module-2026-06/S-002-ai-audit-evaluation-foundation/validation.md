# Validation

## Proof Strategy

Validation for the future implementation must prove the AI audit and evaluation
boundary before any AI tool or chat feature can read business data.

Automated tests must use deterministic fixtures, fake provider responses,
Laravel AI SDK fakes/assertions where available, HTTP fakes, or local adapter
fakes. Live provider credentials, real network calls, production data, or broad
source-record dumps must not be required to prove this story.

This implementation step is validated by targeted feature tests that prove the
schema, redaction boundary, audit correlation, provider usage capture, and
evaluation result persistence without live provider credentials or business-data
AI access.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Redaction removes API keys, encrypted keys, auth headers, provider payloads, sensitive source fields, and disallowed tool args; safe error taxonomy maps provider/tool/redaction failures; source reference builder emits allowed references only; usage/cost calculator handles missing token data deterministically; evaluation assertions detect tool-sequence, source-parity, permission, and hidden-section failures. |
| Integration | Conversation/message/trace/tool-call/provider-usage records are correlated; denied tool calls are audited without execution; allowed tool calls record permission snapshots, campus scope, record counts, hidden sections, source references, duration, and answer linkage; provider failures write safe usage metadata; evaluation run stores per-case results and fails closed when required assertions fail. |
| E2E | No user-facing E2E is required for the first backend-only audit/evaluation foundation. Later chat/admin viewer stories must prove staff-visible source citations, hidden-section notices, feedback controls, and audit lookup flows. |
| Platform | No live provider credentials are needed; Laravel AI SDK fakes/assertions or adapter fakes prevent stray provider calls; logs and audit rows are secret-redacted; no student/lecturer portal code changes. |
| Performance | Audit writes are bounded per agent turn; source references avoid storing broad source-row dumps; evaluation datasets remain small enough for targeted CI; indexes support lookup by user/campus/conversation/status/time. |
| Logs/Audit | Product audit includes actor, role/campus snapshot, conversation, message, trace, tool call, provider/model, usage/cost, permission result, hidden sections, source references, duration, status, error code, and answer linkage; excludes raw keys, auth headers, raw provider bodies, unredacted tool payloads, and unbounded source rows. |

## Fixtures

Future implementation tests should define:

- internal staff user with AI analysis permission for one campus;
- internal staff user without AI analysis permission;
- optional admin actor for future governance-only assertions;
- deterministic campus, semester, program, student, finance, and academic
  records only where a specific source-parity case requires them;
- fake provider success response with token usage;
- fake provider response without usage metadata;
- fake provider authentication failure, timeout, rate-limit, unavailable, and
  invalid-response cases;
- tool-call request containing sensitive fields that must be redacted;
- source-reference samples with allowed fields, hidden fields, stale data, and
  record-count limits;
- evaluation case samples for:
  - current-term defer count;
  - current-term tuition revenue;
  - student program lookup with permission allowed;
  - student tuition debt lookup with permission denied or hidden;
  - program defer-rate comparison;
  - current-vs-previous term trend question;
  - missing-data answer that must not fabricate numbers;
- SDK event or response usage samples with secret-free provider metadata.

## Commands

Requirements validation for this story packet:

```text
./scripts/harness query matrix --numeric | rg -F "AI-MOD-002-ai-audit-evaluation-foundation"
test -f docs/stories/E-ai-module-2026-06/S-002-ai-audit-evaluation-foundation/overview.md
test -f docs/stories/E-ai-module-2026-06/S-002-ai-audit-evaluation-foundation/design.md
test -f docs/stories/E-ai-module-2026-06/S-002-ai-audit-evaluation-foundation/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-002-ai-audit-evaluation-foundation/validation.md
git diff --check
```

Implementation validation for this runtime slice:

```text
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec app sh -lc './vendor/bin/pest tests/Feature/AI/AiAuditFoundationTest.php --colors=never'
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec app sh -lc './vendor/bin/pest tests/Feature/AI/AiProviderSettingsTest.php --colors=never'
./scripts/dev.sh composer show laravel/ai
./scripts/dev.sh composer exec pint -- app/Modules/AI database/migrations tests/Feature/AI tests/Unit/AI
git diff --check
```

If the first implementation is backend-only and no frontend files are touched,
frontend checks may be recorded as not applicable for that slice. If repo-wide
frontend checks remain blocked by pre-existing diagnostics or container limits,
the implementation trace must record targeted substitute checks and prove no
diagnostics come from touched AI files.

## Acceptance Evidence

- Harness intake recorded for this requirements step as Intake #133.
- Story packet exists at
  `docs/stories/E-ai-module-2026-06/S-002-ai-audit-evaluation-foundation/`.
- Harness story row is registered for
  `AI-MOD-002-ai-audit-evaluation-foundation`.
- Requirements validation commands were run and recorded in the final response
  for this documentation slice.
- Portal impact remained `none`; no `/api/v1/student/*`, `/api/v1/lecturer/*`,
  or nested Nuxt portal files were changed.
- Backend-only implementation added the AI audit/evaluation tables, models,
  `AiRedactor`, `AiAuditRecorder`, `AiProviderUsageRecorder`, and
  `AiEvaluationRunner`.
- `AiAuditFoundationTest` passed with 4 tests / 118 assertions, covering nested
  secret redaction, correlated conversation/message/trace/tool-call/provider
  usage audit rows, evaluation case/run/result persistence, and absence of raw
  provider/unredacted payload columns.
