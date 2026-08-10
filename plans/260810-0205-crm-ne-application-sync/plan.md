---
title: "CRM NE Application Sync"
description: "Pull New Enrollment records from the CRM into Student Applications, with a staff mapping gate before student conversion."
status: completed
priority: P1
effort: "4-5d"
tags: [admissions, integration, crm]
created: 2026-08-10
---

# CRM NE Application Sync

## Overview

Pull applicant records from the CRM (`POST /api/login` → `GET /api/ne`) into `student_applications`,
including guardians and document links. Sync is intentionally dumb: it stores CRM values verbatim and
never guesses local codes. A separate Admissions mapping screen turns raw CRM values (campus, major,
scholarship…) into local codes and holds the target intake; conversion to Student is blocked until the
required mappings resolve.

Accepted design decisions D1-D16 and the full CRM→domain field mapping live in
[brainstorm report](../reports/brainstorm-260810-0032-crm-ne-application-sync.md). That report is the
authority on field-level mapping; this plan does not restate the whole table. Where red-team findings
superseded a report claim, this plan wins — see `## Red Team Review`.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Idempotent pull of CRM NE records into applications, keyed on `student_code` | P1 |
| 2 | No data loss: all transcript/achievement/certificate URLs and all CRM score fields persisted, and no CRM record permanently unsyncable | P1 |
| 3 | Per-record failure isolation, with failures visible instead of silently retried forever | P1 |
| 4 | Staff-driven mapping config in Admissions; no deploy needed for a new campus/major | P1 |
| 5 | Conversion gate: application cannot be approved until required mappings resolve, with a precise error | P1 |
| 6 | No credentials in source; no applicant PII in logs; CRM-supplied strings never trusted as URLs or keys | P1 |

## Non-Goals

- No Finance charge/payment from `paid_amount` (stored as a plain column only).
- No auto-approval or automatic Student creation.
- No file download/mirroring — document URLs stored as-is (ADR-0004), subject to scheme validation.
- No push-back to CRM. One-way pull.
- No behavior change to the live push ingest endpoint `POST /api/v1/admissions/applications`
  ([app/Modules/Admissions/routes/api.php:18](../../app/Modules/Admissions/routes/api.php)) beyond the
  explicit match-key branch in Phase 3. Its guardian replace-wholesale semantics stay exactly as they are.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Schema and storage](./phase-01-schema-and-storage.md) | Completed |
| 2 | [Phase 2: CRM client and config](./phase-02-crm-client.md) | Completed |
| 3 | [Phase 3: Mapper and sync command](./phase-03-mapper-and-sync.md) | Completed |
| 4 | [Phase 4: Mapping config UI](./phase-04-mapping-config-ui.md) | Completed |
| 5 | [Phase 5: Conversion readiness gate](./phase-05-conversion-gate.md) | Completed |

Dependencies: 2 → 1, 3 → 1+2, 4 → 1+3, 5 → 1+4.

**Phase 1 must not ship alone to production.** It makes `campus_code` nullable and relaxes the module
FormRequests; until Phase 4 exists there is no way for staff to fill a campus, and until Phase 5 exists
the approval failure message is generic. Ship 1-5 as one release.

## Architecture

```
CRM ──login──► CrmClient ──/ne──► CrmApplicationMapper ──► UpsertCrmApplicationAction
               (config/services.crm)  (raw → payload,        (module-owned, LIVE path;
                                       URL + code hygiene)    match: crm_admission_id XOR student_code)
                                                                     │
                                    crm_value_mappings ◄─────────────┤ raw values recorded
                                    (incl. kind=intake)              │
                                                                     ▼
                                  Mapping UI ──resolve──► campus_code / intended_program / intake
                                                                     │
                                                  ConversionReadiness ──gate──► ApproveApplicationAction
```

Two invariants carry the design:
- **Sync never resolves codes, approval never guesses them.**
- **The write path is the module-owned action** — `app/Services/Admissions/ApplicationIngestionService`
  is reachable only from an unregistered route file and is treated as dead code (Phase 3, step 0).

## Success Criteria

- [ ] Running the sync twice over the same CRM data creates zero duplicate applications, guardians, or document rows.
- [ ] A pre-existing manually created application (null `crm_admission_id`) is byte-identical after a full sync run.
- [ ] After a sync, re-reading a row shows the CRM-specific columns populated (`crm_campus`, `crm_major`, `gpa`, `crm_paid_amount`, `last_synced_at`) — not just the mapper output asserted in isolation.
- [ ] Applications with `status != pending` are skipped and logged; their local edits survive.
- [ ] All 3 transcript URLs + both achievement URLs + both English-certificate URLs persisted; primary transcript resolves `file_diploma ?? file_transcript ?? file_transcript_1`, null when all three are null.
- [ ] A document whose CRM URL later becomes null is removed, not left pointing at a dead file.
- [ ] A CRM record with a blank or duplicated email is ingested, not permanently rejected.
- [ ] A non-`https` document URL is rejected instead of stored.
- [ ] Login failure / 4xx / 5xx / timeout / non-array `data` fails the run with an actionable message and no partial garbage.
- [ ] One invalid record is logged and skipped; the rest of the batch still lands; the command exits non-zero when any record failed; the log contains no `email`, `phone`, or `national_id` value.
- [ ] An unmapped campus/major does not fail sync, but blocks approval with an error naming the exact missing mapping — while reject/revoke stay available.
- [ ] After a staff maps that value, the affected pending applications resolve without re-syncing.
- [ ] A campus-scoped staff member cannot remap values affecting another campus.
- [ ] No credential appears in source, logs, or test fixtures.

## Red Team Review

### Session — 2026-08-10
**Findings:** 16 (16 accepted, 0 rejected) — deduplicated from 24 raw findings across 3 hostile reviewers
(Security Adversary, Assumption Destroyer, Failure Mode Analyst).
**Severity breakdown:** 4 Critical, 10 High, 2 Medium.
Four claims were independently re-verified against the codebase before acceptance (live route target,
`whereNull` coercion, `Arr::only` whitelist, absence of a `campus_code` FK).

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | `where('crm_admission_id', null)` coerces to `whereNull` → CRM record overwrites an arbitrary manually created application | Critical | Accept | Phase 3 |
| 2 | Plan targeted `ApplicationIngestionService`, which no registered route reaches; the live path is `UpsertCrmApplicationAction` | Critical | Accept | Phase 3, plan.md |
| 3 | `Arr::only()` whitelist drops every new CRM column → feature no-ops with green tests | Critical | Accept | Phase 3 |
| 4 | `email` is NOT NULL UNIQUE and was never relaxed → blank/duplicate-email records fail forever | Critical | Accept | Phase 1 |
| 5 | Global mapping screen behind a campus-scoped permission → cross-campus escalation; unscoped discovery query leaks other campuses | High | Accept | Phase 4 |
| 6 | Policy denies approve **and reject/revoke** when campus is null → unmapped junk records are unremovable and the gate message is unreachable | High | Accept | Phase 1, Phase 5 |
| 7 | `crm_file_id` synthesized from an untrusted `student_code`, and `updateOrCreate` re-points `student_application_id` → document theft across applications | High | Accept | Phase 3 |
| 8 | CRM document URLs stored and rendered with no scheme validation → stored XSS in an authenticated staff session | High | Accept | Phase 3 |
| 9 | Guardian "upsert by (application, relationship)" does not exist; shared path deletes-and-recreates; changing it breaks push-ingest guardian removal → portal access for a stranger | High | Accept | Phase 1, Phase 3 |
| 10 | `system_settings` is a Platform-owned closed key registry; writing it from Admissions throws and violates a boundary arch test | High | Accept | Phase 1, Phase 4 |
| 11 | New routes appended after the `/{studentApplication}` wildcard never match | High | Accept | Phase 4 |
| 12 | A CRM URL going null leaves a stale document row forever (asymmetric with the scores rule) | High | Accept | Phase 3 |
| 13 | `campus_code` has no FK (plan and brainstorm both claimed one); `down()` cannot restore NOT NULL once synced rows hold NULL | High | Accept | Phase 1 |
| 14 | No run isolation: sync races itself and races the Phase 4 backfill (read-then-write TOCTOU) | High | Accept | Phase 3, Phase 4 |
| 15 | `curriculum_match_count` conflates "not mapped yet" with "no curriculum exists" → readiness reports a phantom blocker | Medium | Accept | Phase 5 |
| 16 | Failure handling: PII risk in logs, and exit code 0 forever hides permanently failing records; `kind=major` should seed from the existing `IntendedProgramNormalizer::LABEL_TO_CODE` | Medium | Accept | Phase 3, Phase 4 |

**User decisions taken during adjudication:**
- Guardians: NE sync reconciles father/mother only; push ingest keeps replace-wholesale (finding 9).
- `email`: made nullable and its unique index dropped (finding 4).
- Null campus: reject/revoke authorize against the actor's current campus permissions; approve stays blocked (finding 6).

### Whole-Plan Consistency Sweep

Decision deltas applied across all files: write target changed `ApplicationIngestionService` →
`UpsertCrmApplicationAction` (plan.md architecture, Phase 3 files/steps/risks); intake storage changed
`system_settings` → `crm_value_mappings kind=intake` (Phase 1 §4, Phase 4 architecture/steps/success
criteria); `campus_code` FK claim removed (plan.md, Phase 1 — the brainstorm report's D12 wording is
superseded and annotated below); guardian semantics restated per source (Phase 1 index, Phase 3);
`email` nullability added to Phase 1 and to plan-level success criteria; `--dry-run` redefined from
rollback to no-write (Phase 3). Non-Goals endpoint path corrected from the non-existent
`POST /api/admissions/ingest` to `POST /api/v1/admissions/applications`.

Superseded brainstorm claims (report left as the historical record, corrected here):
- D12 "FKs stay" — there is no FK on `campus_code`; nothing to keep.
- Report §"Sync design" named `ApplicationIngestionService`; the live target is the module action.
- Report §Documents `crm_file_id` recipe used `student_code`; now keyed on the local application id.

No unresolved contradictions remain between `plan.md` and the five phase files.

## Validation Log

### Session 1 — 2026-08-10
Verification pass skipped per the workflow guard: `## Red Team Review` above already carries
codebase-verified evidence (4 claims independently re-verified during adjudication). No `[UNVERIFIED]`
tags remain in any plan file. 6 questions asked, all answered.

| # | Question | Decision | Propagated to |
|---|----------|----------|---------------|
| V1 | How does the sync run? | Manual command only; no scheduler entry, no UI button. Add scheduling after the first supervised runs. | Phase 3 "Run mode" |
| V2 | The unreachable ingest trio (`routes/api/v1/admissions.php`, global V1 controller, `ApplicationIngestionService`) | Leave untouched — do not modify, delete, or register. Cleanup is a separate task. | Phase 3 step 0 |
| V3 | Manual-entry email uniqueness after dropping the DB index | Keep the `unique` validation rule on manual FormRequests; only the CRM path may create duplicates. | Phase 1 §2 + step 3 |
| V4 | Document URL validation strictness | Scheme-only (`https`); no host allowlist — CRM mixes Drive links with its own hosts and a list would silently drop real documents. | Phase 2 config, Phase 3 mapper |
| V5 | `/api/ne` data volume (no pagination) | A few hundred records: single-shot fetch, timeout raised 30s→120s. No batching, no queue. Print elapsed time so the batching threshold is measured. | Phase 2 config, Phase 3 command |
| V6 | Where failed records surface | Command summary table + log file. No failure table, no failure screen — the operator is present during a manual run. Revisit when scheduling lands. | Phase 3 "Run mode" |

Scope effect: V1, V5 and V6 each removed work from the plan (scheduler entry, batching/queue, failure
table + screen). V3 and V4 removed a config key and a per-site decision. Net effect on effort: Phase 2
and Phase 3 got simpler, not larger.

### Whole-Plan Consistency Sweep

Deltas checked across `plan.md` and all five phase files: host-allowlist config removed from Phase 2 and
the Phase 3 mapper rule in the same pass (no orphan config key remains); timeout value stated once (Phase 2)
and referenced, not restated, in Phase 3; the dead-code trio now appears only as an explicit
out-of-scope decision (Phase 3 step 0) and no longer as an open question; email-uniqueness wording
reconciled between Phase 1 §2 and Phase 1 step 3; "nightly"/"scheduler" phrasing in Phase 3 corrected to
match manual-run-only, with the exit-code rationale kept for the future scheduled mode.

No unresolved contradictions.

## Open Questions

1. `thithpt_option1/2` subject labels unknown — stored as opaque slots, no blocker.
2. Target intake is a single global row. If a cycle ever needs two live intakes, it becomes per-application; Phase 4 must read it in exactly one place so that change stays cheap.
3. Does `/api/ne` guarantee a non-empty, globally unique `student_code`? D1 assumes it. Phase 3 hard-fails a record without one, so a violation is loud rather than silent — but the assumption is unverified against real data.
4. Approval blocks on a blank/duplicate email (added post-implementation, see Implementation Notes) — should a CRM applicant with no email instead get a synthesized placeholder so approval never hard-blocks on it? No product decision recorded.
5. `manage_crm_value_mapping` is org-wide by seeder convention only, not by a code-level constraint — nothing stops a future seeder edit from granting it to a campus-scoped role, which would let that role backfill `campus_code` across every campus's pending applications.

## Implementation Notes (post-plan deviations, 2026-08-10)

All 5 phases shipped; 137 new tests, 0 regressions in the Admissions/StudentApplication suites (verified against 5 pre-existing unrelated Architecture-test failures, confirmed present before this branch too). Two deviations from the plan text, both discovered by tests during implementation, not by inspection:

- **No DB uniqueness on `(application_guardians.student_application_id, relationship)`.** The plan's Phase 1 §5 called for enforcing this at the schema level. Implementation first added a scoped generated-column unique index (father/mother only, mirroring the table's existing `primary_guardian_key` pattern) — then removed it entirely after `tests/Feature/StudentApplication/GuardiansTest.php` proved the manual "add guardian" screen legitimately allows two `father` rows on one application (e.g. biological + step-parent). `UpsertCrmApplicationAction::reconcileNeGuardians()` now does its own find-or-update by relationship (Eloquent's normal `first()` semantics) with no DB constraint backing it — duplicates from manual entry are simply left alone, same as any other relationship label.
- **`crm_admission_id` is omitted (not set to null) by `CrmApplicationMapper`.** An earlier version set it to `null` explicitly, which — via `Arr::only()` in `UpsertCrmApplicationAction::attributes()` — wiped `crm_admission_id` off a push-created row matched by `student_code`, breaking that row's push-path idempotency on the next push upsert. Caught in code review, not by the original test suite; a regression test now covers it.

Conversion-readiness also gained a check not in the original Phase 5 spec: blank or duplicate `email` now blocks approval (readiness `reason: blank_email` / `duplicate_email`), because `ProvisionStudentAccessAction` creates a `User` against a still-unique `users.email` column — the dropped `student_applications.email` index (Phase 1 §2) otherwise lets approval reach an uncaught `QueryException`. See Open Question 4 for the unresolved product call this surfaced.

<!-- slug: crm-ne-application-sync -->
