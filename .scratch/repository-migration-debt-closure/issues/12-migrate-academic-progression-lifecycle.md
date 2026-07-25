# Migrate Academic Progression & Lifecycle

Status: ready-for-human

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Establish Academic Progression & Lifecycle ownership for Program Enrollment, Study Stage, Transcript Entry, GPA, Student Action, Decision, EGC progression, best-attempt, graduation, and Student Hub workflows without collapsing their distinct state machines.

This is the architecture and compatibility slice. It must introduce a Progression-owned Student Hub Query and a versioned read projection/contract for Delivery evidence while preserving current supported behavior. It is read-only with respect to historical academic evidence; historical data backfill, reconciliation, and compatibility retirement are owned by [24 — Backfill and reconcile historical Academic Progression evidence](24-backfill-reconcile-historical-academic-progression-evidence.md).

## Acceptance criteria

- [x] Program Enrollment Status, Study Stage, Account Status, and Student identity remain separate concepts and persistence responsibilities.
- [x] Transcript, GPA, standing, best-attempt, EGC, lifecycle actions, Decisions, and graduation derive from Progression-owned evidence; the transition preserves legacy-only outcomes through the declared compatibility projection.
- [x] Student Hub behavior, permissions, filters, lifecycle guards, audit timeline, and notifications remain compatible.
- [ ] Finance and other consumers receive lifecycle facts through approved contracts/events rather than Academic persistence reads.
- [x] No historical lifecycle data mutation or compatibility-path removal occurs in this slice; each requires the separately approved data checkpoint in issue 24.

## Blocked by

- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)
- [Migrate assessment, gradebook and Course Result](11-migrate-assessment-gradebook-course-result.md)

## Follow-up data cutover

- [Backfill and reconcile historical Academic Progression evidence](24-backfill-reconcile-historical-academic-progression-evidence.md) owns the approved write checkpoint, reconciliation, cutover, and removal of the compatibility projection after this issue establishes it.

## Comments

### 2026-07-24 — split into architecture and approved data-cutover slices

The prerequisite issues 06, 08, and 11 are complete. At maintainer direction, the previous blocker is split into a dedicated child issue rather than combining read-boundary work with historical data writes.

Student Hub still obtains registrations, scores, graduation, and export payloads from frozen `app/Services/StudentAcademicSummaryService`. This issue must replace that ownership with a real Progression Query; a forwarding façade is not acceptable. Its compatibility projection must preserve legacy-only `academic_records` outcomes and the registrations payload fields `meets_attendance_requirement`, `grade_status`, and `completion_status` until issue 24 completes an approved reconciliation.

No application code was retained from the rejected façade attempt. This issue may now proceed with the read-only architecture and compatibility work; issue 24 remains `needs-info` pending human approval before any data mutation.

### 2026-07-24 — partial read-boundary slice delivered; issue remains open

Completed in this slice:

- Student Hub Registration and Graduation now call Progression-owned Queries instead of forwarding through `StudentAcademicSummaryService`.
- Delivery supplies explicit V1, read-only registration and course-outcome evidence contracts. `TranscriptEntry` is preferred where finalized; legacy Course Result fields (`meets_attendance_requirement`, `grade_status`, and `completion_status`) remain available as compatibility evidence without mutating history.
- Catalog supplies curriculum graduation requirements through a contract. Graduation counts one best passing attempt per curriculum unit and retains active-semester behavior even when legacy schedule dates are absent.
- Registration filters, permission-protected HTTP behavior, pagination ranges, and out-of-range-page metadata have characterization coverage. Architecture documentation and boundary checks were updated.

Validation completed: scoped Registration and Graduation feature tests, Student Hub ownership architecture test, Pint, `git diff --check`, and `migration-debt:inventory --check` passed. The mandated full suite was run once but did not pass because of failures outside this slice (including existing Notification/AI and unrelated Academic feature suites); it needs separate triage.

This issue intentionally remains `ready-for-agent`, not complete. Outstanding work includes moving the Overview, Scores/GPA/Standing, and Export callers off `StudentAcademicSummaryService`, plus the remaining Progression lifecycle/EGC/Decision ownership and approved Finance integration work. Historical reconciliation and compatibility removal remain exclusively in issue 24.

### 2026-07-24 — Export read-boundary slice delivered; issue remains open

- Student Hub Export now calls `GetStudentAcademicSummaryExportQuery` in Progression rather than `StudentAcademicSummaryService`.
- The query receives identity display values from the controller, takes lifecycle/program context from the Progression enrollment projection, uses the Progression GPA and graduation queries, and reads Delivery course-outcome evidence through its V1 contract.
- Export outcome rows are keyed by the stable `academic_records.id -> transcript_entries.course_result_id` mapping: a `TranscriptEntry` is canonical, while a final legacy Course Result is appended only when its matching Transcript Entry is absent. This also preserves transcript-only entries. No historical data or compatibility path was changed.

Verification: targeted export and Hub ownership tests passed (7 tests, 44 assertions), Pint, and `migration-debt:inventory` guard passed. The required full suite was invoked, but the Docker wrapper emitted only progress markers without a terminal summary; it is inconclusive and is not recorded as passing evidence. Remaining Issue 12 work is Overview, Scores/GPA/Standing, score-detail endpoints, and Progression lifecycle/EGC/Decision/Finance ownership. Status remains `ready-for-agent`.

### 2026-07-24 — Overview read-boundary slice delivered; issue remains open

- Student Hub Overview now calls `GetStudentHubOverviewQuery` in Progression rather than `StudentAcademicSummaryService`.
- The query preserves the full and reduced permission payloads while composing Registry profile fields, Progression enrollment/GPA/hold facts, Delivery registration and course-outcome evidence, Institution campus reference, Identity guardian account, and Finance scholarship data through read-only contracts.
- Overview credit statistics merge finalized evidence Transcript-first by `course_result_id`, retaining legacy Course Results without a matching Transcript Entry (including in-progress rows for attempted-credit compatibility). A dedicated Progression compatibility query retains the Hub's legacy current-or-latest-by-creation GPA projection without weakening the finalized-only shared GPA reader. No historical academic evidence was updated or removed.

Verification: targeted Overview and Student Hub ownership tests passed (11 tests, 133 assertions), Pint, `git diff --check`, and `migration-debt:inventory --check` passed. The required full suite was invoked, but the Docker wrapper emitted only progress markers without a terminal summary; it is inconclusive and is not recorded as passing evidence. Remaining Issue 12 work is Scores/GPA/Standing, score-detail endpoints, and Progression lifecycle/EGC/Decision/Finance ownership. Status remains `ready-for-agent`.

### 2026-07-24 — Score-detail endpoint read-boundary slice delivered; issue remains open

- Student Hub `score-details` and lazy `course-scores` endpoints now call Delivery-owned Queries rather than `StudentAcademicSummaryService`; controller validation uses Delivery FormRequests and preserves the established `{success, data}` response envelope.
- The detail query retains assessment-component aggregation and lazy-score pagination. It corrects two legacy read failures: relation-name SQL ordering and a non-existent `assessment_component_details.instructions` select.
- The main Scores/GPA/Standing Inertia read model remains outstanding, together with lifecycle/EGC/Decision/Finance ownership. No historical evidence was written or removed.

Verification: targeted score-detail endpoint and Student Hub ownership tests passed (2 tests, 29 assertions), Pint, `git diff --check`, and `migration-debt:inventory --check` passed. Status remains `ready-for-agent`.

### 2026-07-25 — Scores read-boundary groundwork delivered; issue remains open

- Catalog now exposes ordered curriculum-module composition (module identity, module-unit grading type, weight, and order) through the typed `CurriculumModuleCompositionReader` contract. This lets the forthcoming Progression Scores query construct module-score presentation without reaching into Catalog persistence.
- This is groundwork only: the Scores/GPA/Standing controller still uses the legacy service until the Delivery assessment-evidence contract and Progression query are connected in the next slice.

Verification: `CurriculumModuleCompositionReaderTest` passed (1 test, 16 assertions), Pint, `git diff --check`, and `migration-debt:inventory --check` passed. Status remains `ready-for-agent`.

### 2026-07-25 — Delivery assessment-evidence groundwork delivered; issue remains open

- Delivery now exposes per-course assessment evidence through `StudentHubAssessmentEvidenceReader`, including assessment scores, course identity, grading type, stored grading-scheme metadata, and stable score fields required by the Hub Scores page.
- Existing course-outcome evidence now carries the persisted grade-display payload, so a future Progression Scores query can preserve scheme-grade presentation without importing Delivery persistence.
- The Scores/GPA/Standing controller is intentionally unchanged until the Progression composition query consumes both approved evidence contracts.

Verification: score-detail endpoint and Hub ownership tests passed (2 tests, 46 assertions), including no-scheme and custom-scheme grade-display compatibility, Pint, `git diff --check`, and `migration-debt:inventory --check` passed. Status remains `ready-for-agent`.

### 2026-07-25 — Scores/GPA/Standing read-boundary slice delivered; issue remains open

- Student Hub Scores now calls `GetStudentHubScoresQuery` in Progression; `StudentAcademicSummaryService` is no longer injected into the Hub controller.
- The read-only query composes Delivery assessment and Course Result evidence, Catalog curriculum-module composition, Program Enrollment context, and Progression-owned GPA calculations. It preserves stored grading-scheme display, legacy current-or-latest GPA selection, final-record attempted/earned-credit snapshots, and module pivot grading semantics (including explicit zero weights).
- The Course Result evidence contract now provides semester and GPA-exclusion facts needed for the compatibility projection. No historical evidence was written, reconciled, or removed.

Verification: focused Scores/GPA, scheme-display, score-detail, and ownership tests passed (11 tests, 102 assertions); Pint, `git diff --check`, and `migration-debt:inventory --check` passed. Two independent implementation reviews found no remaining actionable issues. Status remains `ready-for-agent`; remaining work is lifecycle/EGC/Decision ownership and approved Finance lifecycle integration.

### 2026-07-25 — Lifecycle timeline ownership slice delivered; issue remains open

- The Student Hub lifecycle/EGC/Decision timeline query now belongs to Progression and receives Program Enrollment through its explicit contract dependency. The Hub controller and lifecycle tests use the Progression query directly.
- The merged action/progression timeline, EGC detail panel, IELTS history, Decision payload, ordering, permissions, and Finance isolation are unchanged. Finance access remains only through its approved Hub contracts; no Academic or Finance persistence was mutated.

Verification: lifecycle timeline, Hub lifecycle, and ownership tests passed (19 tests, 132 assertions); Pint, `git diff --check`, and `migration-debt:inventory --check` passed. Two independent implementation reviews found no remaining actionable issues. Status remains `ready-for-agent`; remaining work is lifecycle/EGC action write ownership and approved Finance lifecycle integration.

### 2026-07-25 — Finance lifecycle due-exception read boundary delivered; issue remains open

- Finance’s Lifecycle Due Exceptions page now receives latest student-action evidence through the typed `StudentLifecycleActionReader` contract owned by Progression. Its Finance mapper no longer imports or queries `StudentActionLog` directly.
- The reader batches page student IDs and preserves the existing latest-by-action-id payload (`id`, action type, timestamp, notes), including null behavior. No lifecycle, Finance, or historical data was mutated.

Verification: lifecycle/Finance boundary and Hub lifecycle tests passed (13 tests, 92 assertions); Pint, `git diff --check`, and `migration-debt:inventory --check` passed. Two independent implementation reviews found no remaining actionable issues. Status remains `ready-for-agent`; remaining Finance readers that query lifecycle persistence are separate compatibility/reporting work.

### 2026-07-25 — lifecycle ownership and compatibility slice implemented; ready for human review

- Student Decision create/update writes now run through Progression Actions; the Student Hub architecture guard rejects direct controller persistence writes.
- `InvalidProgressionState` remains a Progression-owned lifecycle guard while preserving the existing `ValidationException` contract expected by supported HTTP and Finance defer flows.
- Graduation accepts the persisted `registered`/`confirmed` enrollment states, retains active-semester behavior, and evaluates all finalized attempts so a later failing attempt cannot hide an earlier passing best attempt.
- Transcript-backed registration evidence derives `grade_points` from `quality_points / credit_points`, preserving the Hub GPA summary instead of exposing a binary pass/fail score.
- The migrated Student Hub registration, graduation, overview, export, score-detail, Scores/GPA/Standing, lifecycle timeline, EGC/Decision, and Finance due-exception paths use Progression or approved evidence contracts. No historical lifecycle/evidence mutation, backfill, reconciliation, compatibility removal, or portal source change was made; issue 24 owns the data checkpoint.
- Finance’s `BillingExceptionCollector`, `BillingExceptionDeferredEnrollmentMatcher`, and `DeferCaseService` still contain deliberate lifecycle compatibility/reporting reads. They are explicitly deferred Finance-owned follow-up work (tracked with issue 20/Finance operations); this issue adds no new Academic persistence consumer and does not retire those paths.

Verification:

- `./scripts/dev.sh artisan test --compact tests/Feature/Academic/StudentHubGraduationTest.php tests/Feature/Academic/StudentDecisionRosterTest.php tests/Feature/Architecture/StudentHubProgressionOwnershipTest.php tests/Feature/Architecture/StudentLifecycleProgressionFinanceBoundaryArchTest.php tests/Feature/Finance/Defer/DeferFullScopeItemizationTest.php` — pass (20 tests, 158 assertions)
- `./scripts/dev.sh artisan test --compact tests/Feature/Academic/StudentHubRegistrationsTest.php` — pass (7 tests, 50 assertions)
- Delegated tester expanded lifecycle/Hub/Finance/architecture suite — pass (45 tests, 295 assertions)
- Delegated reviewer targeted lifecycle/Hub/architecture checks — pass (21 tests, 104 assertions; graduation/architecture follow-up 11 tests, 100 assertions)
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — pass
- `git diff --check` — pass
- `./scripts/dev.sh artisan migration-debt:inventory --check` — pass

The remaining unchecked Finance criterion is intentional: the supported due-exception path is contract-based, while the three Finance compatibility/reporting readers above require a separate cutover decision. Full-repository tests and global frontend checks were not run for this scoped backend migration; no portal repository was changed.
