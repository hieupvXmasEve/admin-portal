# Backfill and reconcile historical Academic Progression evidence

Status: needs-info

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## Depends on

[Migrate Academic Progression & Lifecycle](12-migrate-academic-progression-lifecycle.md)

## What to build

After issue 12 establishes the Progression-owned Student Hub Query and compatibility projection, backfill historical academic outcomes from the legacy compatibility source into Progression-owned Transcript Entries. Reconcile records and derived GPA, standing, best-attempt, EGC, graduation, and Student Hub/export results before cutting off the compatibility projection.

This issue is data-affecting. Design and read-only preflight are allowed; no write, conversion, merge, cleanup, or compatibility-path removal may run until the checkpoint below receives explicit human approval.

## Acceptance criteria

- [x] A read-only preflight identifies the exact source records, target Transcript Entries, unmatched records, duplicate candidates, and conflict rows using stable business identifiers.
- [x] The proposed backfill is idempotent, restart-safe, scope-constrained, transactionally safe where possible, and has a documented mixed-version and rollback plan.
- [ ] Every mapped outcome preserves final percentage, letter grade, pass/fail, attempted and earned credits, quality points, attempt number, finalization time, GPA exclusion, standing, graduation, and prerequisite effects.
- [ ] Reconciliation proves no unexplained difference in transcript rows, GPA, academic standing, best attempt, EGC/graduation readiness, Student Hub registration fields, exports, decisions, lifecycle timeline, and affected portal responses.
- [x] A human approves the checkpoint before execution; no record is guessed, silently overwritten, or deleted.
- [ ] The compatibility projection is removed only after reconciliation passes, zero supported consumers remain, and a separate removal approval is recorded.

## Required human-approval checkpoint

Before execution, append all of the following and stop for explicit approval:

1. Exact environment, source/target tables, record scope, and stable identifiers.
2. Read-only counts, exception rows, duplicate/conflict rows, and expected before/after totals.
3. Mapping rules and invariants for outcomes, GPA, standing, best-attempt, EGC, graduation, decisions, and lifecycle history.
4. Idempotency and restart behavior, transaction strategy, backup/recovery, rollback, and mixed-version deployment plan.
5. Student/lecturer portal, queue, notification, export, audit, and Finance-consumer implications.
6. The exact command or migration proposed, its dry-run output, and the named human approver.

## Non-goals

- Changing Student Hub routes, permissions, filters, or payload fields.
- Changing assessment component or attendance evidence ownership.
- Deleting `academic_records` or other historical evidence without separate removal approval.
- Inventing missing academic history or resolving ambiguous mappings automatically.

## Comments

- 2026-07-24: Created at maintainer direction as the required separate data checkpoint for issue 12. No data mutation, migration, backfill, cleanup, or compatibility retirement is authorized or has been attempted.

### 2026-07-24 — read-only preflight checkpoint; explicit approval required

Environment: local Docker application environment (`Asia University`, Laravel 13.6.0, PHP 8.4.23, `Asia/Ho_Chi_Minh`). Scope is explicit `--all`: live, final `academic_records` into `transcript_entries`, using stable identifier `academic_records.id -> transcript_entries.course_result_id` and duplicate business key `student_id + semester_id + unit_id + attempt_number`.

Read-only command and output:

```text
./scripts/dev.sh artisan academic:preflight-transcript-backfill --all --format=table
source_final_outcomes=2052
target_transcript_entries=2052
ready_to_backfill=0
already_matching=2052
conflicts=0
invalid_sources=0
ambiguous_sources=0
date_precision_warnings=2052
unmatched_targets=0
```

`--format=json` emits the stable identifier and every row in each exception category. There are no conflict, invalid, ambiguous, or unmatched rows. All 2,052 matching rows have `legacy_finalization_is_date_only`: the legacy source column is `academic_records.grade_finalized_date` (DATE), while the target stores `transcript_entries.finalized_at` (timestamp). The preflight confirms the dates agree but cannot prove or recreate the missing time-of-day; no time is guessed.

Mapping and invariants: final legacy outcomes map source id, student, course offering, semester, unit, program, campus, attempt number, final percentage, letter grade, attempted/earned credits, quality points, pass/fail, GPA exclusion, standing, graduation, and prerequisite flags field-for-field. Missing required source values, duplicate business attempts, target differences, and target rows with no live final source are blocking exceptions. `grade_finalized_date` may only be promoted after a human approves a documented date-to-timestamp policy. GPA, standing, best-attempt, EGC/graduation, Student Hub/export, Decisions, lifecycle timeline, and portal/Finance consumers are not recomputed or changed by this preflight.

Idempotency and recovery plan for any future approved write: use the unique `course_result_id`, scope by explicit student/semester or `--all`, insert only absent matching candidates, abort on every exception, and process bounded chunks in DB transactions. Do not overwrite or delete target/source rows. Capture a database backup and per-run created-id ledger before execution; rollback may delete only rows recorded by that run. During mixed-version deployment, keep the compatibility projection and write-path dual publishing active; pause queues/notifications only if their reconciliation evidence changes. Finance, student/lecturer API responses, exports, audit, and notification payloads must be compared read-only before any removal approval.

The only implemented command is the read-only preflight above; it deliberately has no `--apply` option. A write command is not proposed for execution because this preflight has zero eligible rows. Before the approval below, the finalization-time policy was unresolved. No mutation has run, and the compatibility projection remains in place.

### 2026-07-24 — human approval recorded

**Hiếu** approved the following policy and no-op checkpoint:

- Treat legacy `academic_records.grade_finalized_date` as date-only provenance.
- Accept `transcript_entries.finalized_at` at `00:00:00` in `Asia/Ho_Chi_Minh` as the canonical representation of that date, never as evidence of the original historical time-of-day.
- Reconcile finalization at calendar-date precision only.
- Accept the `--all` preflight as a no-op: all 2,052 source/target mappings match and no backfill write is required.

No compatibility projection removal is authorized by this approval. The issue is now `ready-for-agent` for the remaining read-only derived-behavior reconciliation; no data mutation was run.

### 2026-07-24 — read-only derived-evidence reconciliation

Implemented `academic:audit-transcript-derived` as a read-only, explicitly scoped audit (`--student-id`, `--semester-id`, or `--all`). It derives GPA and academic standing by the existing GPA rules (every non-excluded, credit-bearing final attempt; academic periods ordered by `start_date`) and the Student Hub's current per-unit selection (latest finalization date, then attempt number; failures are included). A semester-scoped GPA audit retains earlier semesters for cumulative calculation while reporting the requested semester only; its best-attempt output is limited to units in the requested semester. It compares legacy final `academic_records` with Progression-owned `transcript_entries`; `--format=json` includes each stable-key exception row and `read_only: true`. The command has no `--apply` option and does not update transcript, GPA, standing, lifecycle, or source data.

Live local `--all` result:

```text
gpa_rows=464
gpa_mismatches=0
standing_mismatches=0
best_attempts=2002
best_attempt_mismatches=0
```

Verification: `tests/Feature/Academic/TranscriptDerivedEvidenceAuditTest.php` and `tests/Feature/Academic/TranscriptBackfillPreflightTest.php` passed (9 tests, 46 assertions); Pint passed; `migration-debt:inventory` guard passed with `migration_commands=7` and `shared_model_imports=566`, both within baseline.

This is source-to-transcript derived equality evidence only. It does not yet reconcile persisted `gpa_calculations`, EGC/graduation readiness, Student Hub registration fields or exports, Decisions, lifecycle timeline, Finance/notification/queue consumers, or student/lecturer portal responses. The compatibility projection remains in place; removal still requires zero supported consumers and a separate human approval. Status remains `ready-for-agent`; no write was run.

### 2026-07-25 — reconciliation implementation and retirement decision checkpoint

The read-only reconciliation implementation is available as:

```bash
./scripts/dev.sh artisan academic:audit-progression-reconciliation --all --format=table
```

The command has no `--apply` option and emits `read_only=true`. It compares transcript evidence, derived GPA/standing/best-passing-attempt rules, persisted GPA rows, required-decision states, runtime graduation evaluation, EGC business-key and legacy/progression state integrity, and normalized Student Hub course-outcome evidence. It reports the supported-consumer inventory without removing or mutating any compatibility path.

Live local result at `2026-07-25T14:45:16+07:00`:

```text
transcript_exceptions=0
derived_exceptions=0
persisted_gpa_exceptions=498
lifecycle_exceptions=103
graduation_exceptions=0
student_hub_exceptions=200
exceptions=801
supported_consumers=18
reconciliation_passed=no
retirement_eligible=no
```

The transcript evidence remains complete (`2,052` source final outcomes, `2,052` target entries, `0` conflicts, `0` ambiguous rows, `0` unmatched targets). The derived audit remains equal (`464` GPA rows, `0` GPA mismatches, `0` standing mismatches, `2,002` best attempts, `0` best-attempt mismatches). Remaining findings are preserved as stable exception rows with source/target IDs, expected/actual values, and stable keys.

Canonical rules used by this checkpoint:

- GPA/standing use the single approved 0–100 scale and `normal`/`warning` standing. The legacy 4.0-scale `academic_standings` model is out of scope and is not deleted.
- Graduation compares the latest valid passing attempt by finalization date and attempt number; a later failed attempt cannot hide an earlier pass.
- Decision-required action states are `STUDENT_ENROLLMENT_NE`, `STUDENT_MAJOR_ENROLLMENT`, `ACADEMIC_DEFER`, `ACADEMIC_RESUME`, `ACADEMIC_DROPOUT`, and `CAMPUS_TRANSFER`. Admission deferral and waiting-for-course-opening do not require a decision. EGC events remain secondary records.
- `academic_records.grade_finalized_date` remains date-only evidence; no time-of-day is inferred.

The 498 persisted-GPA findings are missing or differing `gpa_calculations` under `student_id + semester_id`. The 103 lifecycle findings are required transitions without decisions. The 200 Student Hub findings are `completion_status` drift in normalized course-outcome comparisons; legacy-only fields (`gradePoints`, repeat-course, attendance requirement, and grade display) are reported as compatibility fields and are not silently mapped into the canonical percentage/GPA-100 scale.

Consumer classification:

- Supported runtime consumers remain across Student Hub, Student and Lecturer APIs/reports/exports, Finance billing/defer/charge-source/EGC paths, EGC progression, course-result/transcript dual-write, legacy academic-record sync, and AI academic profile reads.
- Removal candidate only after a new approval: `App\\Shared\\Contracts\\Academic\\LegacyTranscriptOutcomeReader`, after the preflight/derived-audit commands are migrated away from it.
- The active `StudentHubCourseOutcomeEvidenceReader` remains a supported runtime consumer and is not a removal candidate in this checkpoint.

No backfill, update, delete, compatibility removal, queue pause, notification change, API change, portal change, or Finance cutover was executed. Rollback is therefore not applicable to this read-only run; any future approved write must retain the backup and created-ID ledger procedure above.

**Decision: blocked.** This checkpoint does not approve retirement of the compatibility projection. A future named human approval is required only after the 801 findings are resolved or explicitly dispositioned, affected consumer contracts are migrated and rechecked, and the supported-consumer count for the specific removal candidate is zero.

### 2026-07-25 — production backup baseline comparison

The production backup `backups/asia.sql` was loaded read-only into the temporary database `asia_issue24_backup_20260725`. The application database `asia` was not imported into or overwritten. The reconciliation command now supports:

```bash
./scripts/dev.sh artisan academic:audit-progression-reconciliation \
  --all \
  --baseline-database=asia_issue24_backup_20260725 \
  --format=table
```

Baseline exception classification:

```text
current_exceptions=801
pre_existing_exceptions=800
introduced_exceptions=0
changed_exceptions=1
resolved_exceptions=2060
baseline_regression_free=no
```

The 498 persisted-GPA findings are pre-existing in the production backup: 203 missing persisted GPA rows and 295 stale/different persisted values. The 200 Student Hub findings are also pre-existing: legacy `academic_records.completion_status` stores `completed` while `is_passed=false`, whereas `TranscriptEntry` derives `completion_status=failed`. This is a legacy semantic mismatch, not a migration-created mismatch. The 103 current missing-decision findings include 102 pre-existing rows; `action-391` existed in the backup without a decision and is no longer present in the current database.

The one current-vs-baseline difference requiring disposition is:

```text
stable_key=course-result:5665
academic_records.is_passed: backup=true -> current=false
```

Because `backups/asia.sql` is the accepted production baseline, the backup value
(`is_passed=true`) is authoritative for this comparison. The current value is
therefore treated as data drift pending a separately approved correction. This
comparison does not establish that application code caused the difference, and
no data mutation was run.

Other raw data deltas that require separate disposition, but are not GPA reconciliation findings:

- `academic_records`: 12 backup-only in-progress rows; 857 updated rows, primarily attendance counters/percentage and 1 `is_passed` change.
- `transcript_entries`: all 2,052 rows have timestamp metadata changes; `finalized_at` changed from the backup's UTC representation (`17:00:00`) to the approved local-date midnight representation (`00:00:00` Asia/Ho_Chi_Minh). This is the approved date-only normalization, not a newly inferred time-of-day.
- `gpa_calculations`: no raw row delta.
- `student_action_logs`: backup-only `action-391`.
- `egc_blocks`: 43 updated rows, including result, attendance, and sync metadata changes; these require EGC business disposition before any projection retirement decision.

The baseline comparison changes the interpretation of historical findings, but
not the retirement gate: one current-vs-baseline data difference still needs a
recorded disposition, 18 supported runtime consumers remain, and no named
retirement approval exists. Projection retirement remains blocked.

### Ownership of the 18 supported consumers

Issue 24 owns the read-only reconciliation, evidence disposition, and final
retirement gate. It does not own implementation of every consumer migration.
The implementation work is distributed as follows:

| Consumer surface | Implementation owner |
| --- | --- |
| Student Hub and Progression-owned lifecycle/EGC/Decision readers | [Issue 12 — Migrate Academic Progression & Lifecycle](12-migrate-academic-progression-lifecycle.md) |
| Student API and student portal academic/progression readers | [Issue 16 — Cut over the Student API and student portal](16-cut-over-student-api-portal.md), with Progression-specific ownership remaining in Issue 12 |
| Lecturer API, gradebook, assessment, and lecturer portal reads | [Issue 17 — Cut over the Lecturer API and lecturer portal](17-cut-over-lecturer-api-portal.md) |
| Dashboards, reports, exports, and AI academic reads | [Issue 20 — Migrate dashboards, reports and AI read surfaces](20-migrate-dashboards-reports-ai-reads.md) |
| Finance billing, deferment, charge-source, collection, and Finance/EGC compatibility reads | [Issue 13 — Close Finance obligation, settlement and collection Migration Debt](13-close-finance-obligation-settlement-collection-debt.md), using Finance Legacy Retirement issues 08 and 18 as its detailed implementation evidence |
| Final repository-wide inventory and retirement coordination | [Issue 23 — Close repository migration debt inventory](23-close-repository-migration-debt-inventory.md) |

Issue 24 can receive final retirement approval only after those owner issues
remove or migrate their supported reads, the reconciliation is rerun, and the
specific compatibility projection reports zero supported consumers.

### 2026-07-25 — earlier reconciliation command stabilization

The read-only command now fails cleanly when a requested baseline database is
unavailable and restores the default application connection before returning.
Focused verification passed: `AcademicProgressionReconciliationTest`,
`TranscriptDerivedEvidenceAuditTest`, and `TranscriptBackfillPreflightTest`
(14 tests, 63 assertions); scoped Pint and `git diff --check` passed.

The live current-versus-production-baseline audit at this checkpoint reported
801 exceptions, 18 supported consumers, zero introduced exceptions, and one
changed exception. The later 2026-07-26 checkpoint supersedes those counts
after timezone normalization and the current local data snapshot were
reverified. No business data, compatibility path, queue, notification, API, or
portal source was modified.

### 2026-07-26 — implementation completed; data disposition still required

Completed the read-only reconciliation implementation and its review cycle.
The command now:

- compares the production backup without changing the active connection
  permanently;
- emits stable expected/actual exception evidence and raw table-delta samples;
- treats MySQL `TIMESTAMP` values returned in the UTC database session as UTC,
  then compares finalization at the approved `Asia/Ho_Chi_Minh` calendar-date
  precision;
- selects the latest passing graduation attempt by local finalization date
  before attempt number;
- avoids adding new shared-model imports from the reconciliation Query; and
- skips the expensive raw-table snapshot when no baseline comparison was
  requested.

Fresh local read-only result:

```text
transcript_exceptions=1
derived_exceptions=0
persisted_gpa_exceptions=498
lifecycle_exceptions=104
graduation_exceptions=7
student_hub_exceptions=200
exceptions=810
supported_consumers=18
reconciliation_passed=no
retirement_eligible=no
```

The sole transcript exception is `course-result:5665`
(`mismatch_is_passed`). Graduation exceptions comprise that same missing
passing target plus six EGC legacy/Program Enrollment current-level
differences for Student `502`. The 104 lifecycle findings are required
transitions without an attached Decision. The prior 801-exception checkpoint
described a different local data snapshot; this fresh result supersedes it for
the current worktree.

The comparison against `asia_issue24_backup_20260725` reports zero introduced
or changed exception fingerprints and 810 pre-existing exceptions. It also
reports 860 raw `academic_records` updates for evidence review. No application
or baseline data was mutated by either command.

Verification:

- focused issue-24 and graduation suite: 25 tests, 113 assertions, passed;
- regression tests cover UTC-to-local date-only comparison and
  finalization-before-attempt selection;
- live read-only current and backup-baseline audits completed;
- Pint, `scripts/check-docs.sh`, and `git diff --check` passed;
- the full repository suite reached the existing
  `AcademicNotificationBoundaryArchTest` and exited with status 2 without a
  diagnostic; that unchanged architecture test also exits 2 when run alone,
  while the issue-focused architecture and behavior checks pass;
- `migration-debt:inventory` passes the frozen-service, controller, route, and
  other guards, but the repository-wide shared-model-import count is already
  `601` against baseline `568` from other migration slices. Issue 24 does not
  raise that baseline.

Status is `needs-info`. Before any write or compatibility retirement, a named
human must approve exact dispositions for the 810 exceptions and the affected
records, and the implementation owners must migrate or permanently classify
the 18 supported consumers. The previous no-op/date-precision approval does
not authorize these corrections or projection removal.
