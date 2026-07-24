# Close repository-wide Migration Debt

Status: ready-for-agent

Portal impact: both

## Problem

Swinx has accepted target Bounded Contexts and canonical Laravel 13 / Inertia v3 patterns, but supported runtime paths still mix those targets with frozen controllers, services, route files, shared Eloquent business models, compatibility adapters, deprecated frontend patterns, and partially migrated query code. This issue closes that **Migration Debt** without treating age, filename, or the word `Service` as proof that code must be removed.

The immediate reported failure is reproducible at `GET /finance/operations/exceptions`: the default `type=all` path calls `toBase()` on an `Illuminate\Database\Query\Builder` in `BillingExceptionCollector`. The same focused suite also exposes a second incomplete boundary migration in the zero-tuition-waiver path, where `StudentChargeTimingResolver` now requires a Student identifier or `ProgramEnrollmentSummary`, but the caller constructs an unpersisted partial `Student` object with no usable identifier.

This is the one umbrella tracker for repository-wide closure. Implementation must land as small vertical migration slices with independent characterization, cutover, rollback, and evidence; this issue does not authorize a big-bang namespace or schema rewrite.

## Locked decisions

1. **Migration Debt definition:** a supported runtime path is debt when it violates an accepted ownership boundary or still depends on a transitional compatibility pattern after a canonical replacement exists. Old but permanently owned components remain valid.
2. **Data safety:** schema evolution and backfill may be designed here, but every operation that writes, converts, merges, deletes, or drops data requires a separate human approval checkpoint before execution.
3. **Compatibility:** preserve route names, URLs, API envelopes, public fields, permissions, and UI behavior by default. Removal requires evidence of no supported caller plus explicit approval.
4. **Delivery shape:** keep this single umbrella issue, but implement it through reviewable vertical slices. Do not combine the repository migration into one PR or deployment.
5. **Domain interaction:** use narrow Shared Query/Command Contracts, post-commit Domain Events/outbox, neutral references, or declared Read Projections. Shared Eloquent business state and another context's concrete implementation are not supported integration mechanisms.

## Audit baseline — 2026-07-22

These counts are discovery inputs, not automatic deletion targets. Every item must be classified against a named owner and canonical replacement.

| Surface | Current evidence |
|---|---:|
| `app/Services/**/*.php` frozen-zone files | 119 files / approximately 49,028 LOC |
| Top-level `app/Http/Controllers/**/*.php` excluding the base controller | 120 files / approximately 31,171 LOC |
| Top-level `routes/web/**/*.php` and `routes/api/**/*.php` | 42 files / 818 `Route::` declarations |
| Runtime imports of `App\Services\*` from `app/` or `routes/` | 179 imports across 145 files |
| Legacy top-level controller references from route files | 125 references |
| `App\Models\*` imports from code inside Modules | 568 imports across 318 files |
| Direct imports between different top-level `App\Modules\*` owners | 0 found; retain a regression guard |
| Files containing direct JSON response candidates | 56 |
| Controllers containing inline `$request->validate(...)` candidates | 96 |
| Application PHP files missing `declare(strict_types=1)` | 257 |
| Lowercase or kebab-case top-level Vue page directories | 23 directories |
| Vue pages using legacy filter stacks | 28 files discovered; composable counts overlap |
| Pages using `useInertiaFilters` | 18 files |
| Pages using `useServerTableQuery` | 6 files |
| Pages using `useFilters` / `useTableFilters` | 4 / 4 files |
| Frontend files containing literal application/API URL candidates | 39 |
| Removed Inertia APIs found (`Inertia::lazy`, array-bound `Deferred`, `$page.props.flash`) | 0 in the sampled scan; keep regression guards |
| Backfill/migrate/rebuild/reconcile console commands matching the initial scan | 7 |

Highest-risk frozen files include `AssessmentReportService` (3,084 LOC), `StudentAcademicSummaryService` (1,652), `EventParticipationService` (1,568), `UnitExcelImportService` (1,385), `CourseOfferingController` (2,018), `Lecturer\AssessmentController` (1,447), `SemesterEnrollmentController` (1,252), and `CurriculumVersionController` (1,182). Size determines investigation order, not ownership or deletion.

## Existing work to consume, not duplicate

- [Swinx Domain Boundary Decomposition](../../academic-domain-boundary-decomposition/PRD.md) defines target ownership, migration sequencing, and behavior/boundary test seams.
- [Close remaining domain-boundary bypasses](../../academic-domain-boundary-decomposition/issues/23-close-boundary-bypasses-and-retire-adapters.md) owns the final cross-context adapter closure within that program.
- [Finance Legacy Retirement](../../finance-legacy-retirement/PRD.md) defines permanent versus transitional Finance components.
- [Reconcile Finance invariants and close release evidence](../../finance-legacy-retirement/issues/18-reconcile-invariants-and-close-release-evidence.md) retains Finance-specific data correction approvals and monetary evidence.
- Existing Finance Obligation v2 wave issues remain authoritative for obligation, entitlement, ledger, DNG, and backfill behavior.

When an item is already owned by one of these trackers, record the reference and current evidence here; do not restate its implementation specification or create a competing source of truth.

## Migration inventory contract

Before moving a path, classify it in this issue's Comments/evidence log with:

- runtime entry points and callers, including HTTP, queue, scheduler, command, import/export, report, webhook, MCP, and both portals;
- current and target Bounded Context owner;
- authoritative data owner and tables/read models touched;
- supported behavior and characterization-test seam;
- canonical replacement: Action, Query, module controller/route, Shared Contract, Domain Event, neutral reference, Read Projection, `useDataTable`, `useForm`, or supported infrastructure component;
- portal impact: `none`, `student`, `lecturer`, or `both`;
- data impact: `none`, `read-only verification`, or `approval required`;
- compatibility disposition: preserve, redirect/deprecate, or removal awaiting approval;
- rollback and observability signal;
- final disposition: migrated, retained with permanent owner, dead and approved for removal, or blocked with named evidence.

No item may be marked migrated merely because its namespace changed. Its runtime caller and ownership boundary must actually cut over.

## Execution order

### Slice 0 — Restore a trustworthy baseline

- Add a regression test for the default Billing Exceptions `type=all` path, then remove the invalid Query Builder `toBase()` call without changing result shape, ordering, campus scope, semester scope, or encoded IDs.
- Repair the zero-tuition-waiver characterization failure through the canonical Program Enrollment boundary; do not restore a partial Student God Model workaround.
- Run the complete Billing Exceptions file and record any further baseline failures separately from new regressions.
- Establish read-only inventory commands/tests so future counts are reproducible and generated/vendor/portal output is not misclassified.

### Slice 1 — Prevent new Migration Debt

- Extend architecture tests to reject new frozen-zone services/controllers/routes, cross-context concrete imports, unapproved shared-model reads, and expansion of transitional allowlists.
- Guard Laravel 13, Inertia v3, API envelope, FormRequest, `useDataTable`, route-helper, page-folder naming, and strict-types rules where static verification is reliable.
- Existing allowlists must name an owner, reason, replacement, and retirement condition; the count may only decrease.

### Slice 2 — Migrate backend behavior vertically

- Work one supported use case at a time: characterize public behavior, introduce the owner boundary, cut all callers over, verify, then retire only the now-dead compatibility path.
- Move writes to owner Actions and reads to owner Queries; keep controllers orchestration-only and validation in FormRequests.
- Move route ownership into `app/Modules/{Domain}/routes`; preserve public names and URLs unless separately approved.
- Replace shared Eloquent cross-context reads with neutral references or owner contracts. Models may remain physically shared during transition only with a documented compatibility owner and shrinking consumer list.
- Sequence ownership foundations before dependants: Identity/Institution/Student Registry; Admissions/Catalog/Delivery/Progression; Finance/Facilities/Notification/AI and cross-context reporting.
- Treat queue jobs, scheduler tasks, commands, imports, exports, webhooks, reports, policies, broadcasts, and MCP tools as runtime consumers, not cleanup afterthoughts.

### Slice 3 — Migrate API and frontend surfaces

- Move student- and lecturer-facing controllers/services behind their owning module boundaries while preserving `ApiResponse` envelopes, auth actor rules, snake_case contracts, pagination, and error semantics.
- Replace legacy list/filter composables with `useDataTable` while preserving query keys, browser history, pagination, sorting, empty/loading/error states, and permissions.
- Move lowercase/kebab-case page directories only with matching Inertia render-path and import updates; do not perform case-only moves without cross-platform verification.
- Replace literal application URLs with Ziggy `route(...)` or established route helpers.
- Keep Inertia page forms on `useForm`; keep non-navigating modal/drawer forms on vee-validate + Zod + `useApi`; retain Inertia v3 deferred/flash behavior.
- For API contract changes, update the matching gitignored portal in the same work window and keep Swinx and nested portal git state separate.

### Slice 4 — Data cutover and compatibility retirement

- Use expand → idempotent backfill → reconciliation → cutover → cleanup.
- Complete read-only preflight and exception reports before requesting approval for any write.
- After approval, constrain each run to exact records/scope, capture before/after evidence, execute transactionally or restart-safely, and rerun reconciliation.
- Never guess identity, academic history, authorization, money allocation, provider outcome, or lifecycle state. Unresolved evidence becomes an explicit exception for human disposition.
- Drop columns/tables, delete historical records, rewrite monetary/lifecycle state, or remove compatibility paths only after separate approval and proven zero supported consumers.

### Slice 5 — Closure

- Re-run the inventory and classify every baseline item. The target is zero **unexplained** Migration Debt, not necessarily zero Services, controllers, adapters, or shared technical components.
- Shrink architecture allowlists to zero where a permanent exception is not explicitly justified.
- Run full backend, frontend, architecture, portal, scheduler/queue, import/export, reporting, and data-invariant verification.
- Update current-state architecture docs to the landed state; keep implementation history and evidence in this issue/commits rather than duplicating it in `CONTEXT.md`.

## Data approval gate

Before any data-affecting slice runs, append a checkpoint containing:

1. exact environment and record/table scope;
2. read-only counts and exception rows with stable business identifiers;
3. invariants and expected before/after totals or state transitions;
4. idempotency/restart behavior;
5. transaction, backup/recovery, rollback, and mixed-version deployment plan;
6. portal/provider/queue implications;
7. the exact command or migration proposed.

Stop and request human approval. Approval to design or test a backfill is not approval to run it. A previous slice's approval does not authorize a later cleanup/drop.

For Finance, preserve verified cash and raw provider evidence, reconcile gross/discount/cash/credit/remaining collectible separately, and defer to the Finance-specific invariant tracker. For Academic/Admissions/Registry/Identity, preserve historical enrollments, transcripts, decisions, applications, guardians, accounts, access/audit evidence, and external identifiers.

## Compatibility and removal gate

- Characterization tests must prove current supported behavior before cutover.
- Preserve route names/URLs, permissions, Inertia props, API fields/envelopes, pagination/filter keys, import/export shapes, broadcasts, scheduled behavior, and portal contracts by default.
- A proposed removal must list repository callers, database-configured jobs/templates where applicable, portal callers, external/provider hooks, replacement/redirect behavior, and rollback.
- Stop for explicit approval before a supported contract is broken or a compatibility surface is removed without a transparent equivalent.

## Acceptance criteria

### Reported failures and test baseline

- [x] `GET /finance/operations/exceptions` succeeds for the default/all filter and each individual exception type without calling Eloquent-only methods on Query Builder.
- [x] Billing exception counts and list results agree by type, preserve campus/semester scoping, stable encoded IDs, ordering, pagination, and fixability semantics.
- [x] The zero-tuition-waiver path resolves pricing through the accepted Program Enrollment boundary.
- [x] `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php` passes and includes default/all-filter coverage.

### Inventory and ownership

- [ ] Every baseline service, controller, route file, shared-model Module consumer, compatibility adapter, allowlist entry, legacy frontend page/pattern, and migration command has a recorded classification and owner.
- [ ] No supported runtime consumer remains unexamined, including queues, schedules, commands, imports/exports, reports, webhooks, broadcasts, policies, MCP tools, and portals.
- [ ] Every retained component has a permanent responsibility; every transitional component has a measurable retirement condition.
- [ ] No mass namespace move is accepted as a boundary migration without caller cutover and behavior evidence.

### Backend and boundaries

- [ ] New debt is blocked by architecture tests; frozen-zone and transitional allowlists never grow without explicit human approval.
- [ ] Supported business writes and reads are owned by the correct Bounded Context through Actions/Queries or explicitly permanent domain services.
- [ ] Module controllers orchestrate through FormRequests and owner use cases; API responses use `ApiResponse`.
- [ ] Module routes own migrated endpoints while public contracts remain compatible.
- [ ] Cross-context runtime interaction uses approved Shared Contracts, neutral references, Domain Events/outbox, or declared Read Projections; no unexplained shared Eloquent business-state dependency remains.
- [ ] Application PHP files comply with strict types and current code standards, or a narrowly justified framework/generated exception is recorded.

### Frontend and portals

- [ ] Supported list pages use `useDataTable`; no unexplained `useInertiaFilters`, `useServerTableQuery`, `useFilters`, or `useTableFilters` usage remains.
- [ ] Migrated pages use PascalCase directories, route helpers, current form patterns, reusable UI primitives, and Inertia v3 APIs without changing supported UX.
- [ ] Student and lecturer API changes carry correct portal-impact metadata and matching portal type/composable/store/page updates.
- [ ] Affected student and lecturer portals pass lint, typecheck, and build, or an environmental blocker is recorded with reproducible evidence.

### Data, release, and documentation

- [ ] No data-affecting action ran without its recorded human approval checkpoint.
- [ ] Approved backfills are idempotent/restart-safe and reconcile counts, relationships, audit history, access state, provider evidence, and financial totals with zero unexplained difference.
- [ ] Destructive cleanup occurs only after cutover, zero-consumer evidence, rollback planning, and separate approval.
- [ ] Targeted and full Pest suites, architecture tests, Pint, frontend type-check/lint/format checks, and relevant portal checks pass or have explicitly owned baseline blockers.
- [ ] Current-state docs reflect landed ownership; the glossary remains implementation-free and uses **Migration Debt** consistently.
- [ ] Final inventory reports zero unexplained Migration Debt and no unowned compatibility path.

## Non-goals

- Splitting the monolith into microservices or separate databases.
- Renaming public routes, API fields, or database structures merely for aesthetics.
- Deleting a valid permanent service or read model because it is old or large.
- Rewriting working features without a named boundary violation or canonical replacement.
- Silently correcting production data, clamping finance evidence, inventing academic history, or dropping compatibility for convenience.
- Creating additional tracker issues automatically; this umbrella issue remains the requested single tracker unless the maintainer later changes that decision.

## Initial verification evidence

- Read-only tinker reproduction after binding a null campus context fails with `BadMethodCallException: Call to undefined method Illuminate\Database\Query\Builder::toBase()` on the default/all Billing Exceptions query.
- The full focused Billing Exceptions file currently reports 12 passing tests and 1 failure. The isolated failure is `lists zero tuition waived students separately from missing charge exceptions`, which raises `InvalidArgumentException: Pricing requires a student identifier or ProgramEnrollmentSummary` before its assertions.
- No test currently exercises `ListBillingExceptionsQuery::handle(..., 'all', ...)`; the reported runtime branch therefore lacks regression coverage.
- The initial static scan found no direct imports between different top-level Module namespaces, confirming earlier boundary work has value; shared-model logical ownership and other compatibility paths remain the larger closure surface.
- No application or portal code and no data were changed during this audit.

## Comments

- 2026-07-24: Maintainer authorized a dedicated data-cutover issue, [24 — Backfill and reconcile historical Academic Progression evidence](24-backfill-reconcile-historical-academic-progression-evidence.md), as an explicit exception to this tracker’s default no-new-issues rule. Issue 12 now owns the read-only Progression/Student Hub architecture and compatibility projection; issue 24 owns approval-gated historical backfill, reconciliation, cutover, and retirement.
- 2026-07-22: Maintainer approved the definition of Migration Debt, the data approval gate, preserve-by-default compatibility, and a single umbrella issue implemented through small vertical slices. No data mutation was authorized or attempted.
- 2026-07-22: Slice 0 / child issue 02 completed. Runtime entry points for this slice are the Finance web route `finance.operations.exceptions`, `BillingOperationsController::exceptions`, `GetBillingExceptionCountsQuery`, and `ListBillingExceptionsQuery`; no queue, scheduler, command, import/export, report, webhook, broadcast, MCP, or portal consumer was found for this path. Finance remains the owner of the read-only exception queue and its `course_registrations`, `finance_charges`, `student_action_logs`, and related read tables; tuition timing now consumes the Progression-owned `ProgramEnrollmentReader` DTO. The supported behavior seam is `tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php`, with `BillingExceptionsPaginationTest.php` and Finance owner-reader architecture tests as boundary checks. Portal impact is none; data impact is read-only verification only; route names, URL, fields, encoded IDs, permissions, ordering, pagination, campus/semester scope, and fixability behavior are preserved. Rollback is a revert of the slice commit, with focused test failures and the route exception log as signals. `BillingExceptionCollector` is retained as a permanent Finance read component; the partial Student compatibility workaround is removed.
- 2026-07-22: Verification evidence: focused Billing Exceptions suite passes 16 tests / 64 assertions; pagination and four related architecture tests pass 5 tests / 14 assertions; Pint and `git diff --check` pass. The repository-wide `./scripts/dev.sh test` still terminates during global discovery with the reproducible PHP fatal `Allowed memory size of 268435456 bytes exhausted`, without an assertion failure from this slice. The parent remains `ready-for-agent`: inventory/ownership, regression guards, remaining backend/frontend/portal migrations, data/release closure, and sibling slices 03–23 are not complete.
