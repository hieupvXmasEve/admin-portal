---
title: "Close remaining legacy shims and final DEAD cleanup"
description: "Consolidated legacy-refactor closure: DEAD cleanup + 6 remaining shims (from advise-260815-1000) + auth legacy single-source retirement (merged from 260815-0942) + notification/email table drops (merged from 260807-0042); supersedes 260811-0012 phase 5"
status: pending
priority: P1
effort: "8-10d + 7-day soak"
tags: [legacy-refactor, shim-sweep, architecture, auth, decommission]
created: 2026-08-15
---

# Close remaining legacy shims and final DEAD cleanup

## Overview

Executes the foundation slice of `plans/reports/advise-260815-1000-legacy-model-refactor.md` (Mức 3 confirmed: contract-for-read, events only Academic↔Finance). Closes out the 260811-0012 shim sweep: 24/30 shims already deleted; this plan deletes the remaining 6, the last DEAD route file, and the dead legacy ingest stack behind it.

**Verified current state (2026-08-15, supersedes audit 260810-0004):**
- `app/Models` = 84 files; 6 shims remain: ApplicationDocument, FormResponse, GoldTransaction, QueryReply, QueryTicket, UploadRecord.
- Split-brain `EgcRetakeDiscountLink` ALREADY FIXED — legacy file gone, zero importers. Audit line "vẫn chưa fix" is stale.
- DEAD: 5/6 deleted; remaining = `routes/api/v1/admissions.php` (orphan) PLUS its dead stack found by red-team: global `IngestionController`, `IngestApplicationRequest`, `ApplicationIngestionService` (sole caller = dead controller), `ApplicationDocumentService` (0 callers) — all unhardened duplicates of the live Admissions module path.
- Guard: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — `SHIMMED_MODELS` (6) + `SHIMMED_MODEL_IMPORT_BASELINE` (exact two-way match). FOUR other placement arch tests currently assert the shims EXIST and must be flipped per phase (`EngagementFormModelPlacementArchTest`, `EngagementQueryTicketModelPlacementArchTest`, `MerchandiseModelPlacementArchTest`, `UploadModelPlacementArchTest`).

**Why the 6 survived:** each is pinned by live cross-module reads/writes that would trip `cross_context_concrete_imports` (exact, ceiling 0) if imports were renamed in place. Fix shape = contract in `app/Shared/Contracts/` (precedent: `SpaceReferenceReader`, `CampusBuildingCountReader`, `CourseSurveyTargetReader`, `ApplicationDocumentCatalogReader`) or move the read into the global owner service. ApplicationDocument is additionally pinned by a fraud-sensitive cross-module WRITE with two live callers → gated phase.

**Hidden caller class (red-team F1):** ~13 files inside `namespace App\Models` bind the shims with bare `X::class` — invisible to string grep (the exact failure that broke ClassSession→Room at runtime). Every sweep phase adds explicit `use` statements to its files and verifies with BOTH detectors (string grep + bare-name grep inside `app/Models`).

**Key coupling:** Engagement↔Upload is mutual — `Upload\UploadRecord` holds inverse relations to the 3 Engagement models (believed unused; model-anchored inventory required before deletion), while `Engagement\QueryReply.uploadRecord()` + `FormResponse.attachments()` are LIVE reads with 6 consumer sites. Phase 2 breaks Upload→Engagement; Phase 4 breaks Engagement→Upload.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | `SHIMMED_MODELS` shrinks 6 → 0 (or 1 if ApplicationDocument gate unresolved) | P1 |
| 2 | `SHIMMED_MODEL_IMPORT_BASELINE` shrinks to its permanent floor: 2 morph-backfill migrations + 3 assertion-data test files (5 entries — see Phase 5 "Completion state"; NEVER delete those assertions to chase zero) | P1 |
| 3 | Last DEAD route file + dead ingest stack deleted; audit report updated to verified reality | P1 |
| 4 | Guardian authz single-source: zero `parent_student` reads in app code; `EitherMiddleware` deleted; 8 token actions on one login pipeline | P1 |
| 5 | `notifications` + `email_templates` tables dropped after soak (backup + rehearsed) | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: DEAD remainder, dead ingest stack, guards + audit refresh](./phase-01-start.md) | Pending |
| 2 | [Phase 2: Engagement shims sweep](./phase-02-engagement-shims-sweep.md) | Pending |
| 3 | [Phase 3: GoldTransaction shim sweep](./phase-03-goldtransaction-shim-sweep.md) | Pending |
| 4 | [Phase 4: UploadRecord shim sweep](./phase-04-uploadrecord-shim-sweep.md) | Pending |
| 5 | [Phase 5: ApplicationDocument shim sweep (gated)](./phase-05-applicationdocument-shim-sweep-gated.md) | Pending |
| 6 | [Phase 6: Auth — grants-only guardian reader](./phase-06-auth-grants-only-guardian-reader.md) | Completed |
| 7 | [Phase 7: Auth — swap relations off legacy pivot](./phase-07-auth-swap-relations-off-legacy-pivot.md) | Completed |
| 8 | [Phase 8: Auth — explicit ParentOrStudentAccess middleware](./phase-08-auth-explicit-parent-or-student-middleware.md) | Completed |
| 9 | [Phase 9: Auth — unified login pipeline](./phase-09-auth-unified-login-pipeline.md) | Completed |
| 10 | [Phase 10: Notification/email — soak and drop tables](./phase-10-notification-email-soak-and-drop-tables.md) | Pending |

**Ordering — three independent tracks:**
- **Shim track (1→2→3→4→5), strictly serialized** — every phase edits the same two exact-match constants in `DeprecatedModelShimArchTest` (stale entry fails as hard as a missing one), so no parallel PRs within the track; each phase carries a rollback recipe naming the constant entries to restore. Phase 4 depends on Phase 2 (mutual Engagement↔Upload edges). Phase 1's morph pre-flight gates phases 2-5. Phase 5 code blocked by the fraud-design gate ADR; the gate REVIEW (ADR only, no code) runs in parallel with 1-4 (validation Q4).
- **Auth track (6→7→8; 9 needs only 6)** — merged from 260815-0942; does not touch the shim arch test, so it may interleave with the shim track. Note Phase 7 edits `app/Models/Student.php`/`User.php`, which phases 2-4 also touch (unqualified-binding `use` lines) — coordinate merges, trivial conflicts only.
- **Drop track (10)** — independent; soak clock already running from the deployed decommission PRs. Start verification any time; drop only after the soak criteria hold.

## Success Criteria

- [ ] Phase 1: 5 dead files deleted; route:list hash unchanged; `config/migration_debt_paths.php` reconciled; morph pre-flight recorded
- [ ] 5 shims deleted (all except ApplicationDocument unless gate cleared): `ls app/Models/*.php | wc -l` = 79 (or 78)
- [ ] `./scripts/dev.sh artisan test tests/Feature/Architecture` green (incl. flipped placement tests + MigrationDebtInventoryTest)
- [ ] Touched-module suites: no new failures vs recorded baselines (Finance 26 fail/324 pass; run Academic timeline test file individually — known CHECK-constraint flake aborts the dir)
- [ ] Attachment presence tests + upload denial test green (Phase 4 silent-failure and authz guards)
- [ ] Zero new files under `app/Http`, `app/Services`, `app/Models` (exception: `app/Http/Middleware/ParentOrStudentAccess.php`, Phase 8 — replaces a deleted global middleware in place)
- [ ] Audit report `audit-260810-0004` updated with 2026-08-15 verified state
- [~] Auth: `EitherMiddleware` gone ✓; login matrix test green (3 actors, 13 cases across Login/Refresh/Google) ✓; `grep -rn parent_student app/` still shows `GrantGuardianAccessAction`/`RevokeGuardianAccessAction` hits beyond migrations + `CleanupParentDataCommand` — out of phases 6-9 scope, not fixed (see Phase 9 Deviations)
- [ ] Drops: `notifications` + `email_templates` absent on prod after soak; backup rehearsed

## Non-goals

- No schema/FK changes. No moving the 20 misplaced `app/Services` (pinned `frozen_services` paths are modified in place only). No ALIVE-LEGACY (~70 model) domain batches — separate plans per advice. No full DDD purity. Deploy/runbook concerns (autoload dump, opcache, queue-worker restart) handled outside this plan at release time per user decision (red-team F9 rejected).

## Red Team Review

### Session — 2026-08-15
**Findings:** 15 adjudicated (14 accepted, 1 rejected by user) + 1 auto-rejected pre-adjudication (superseded evidence)
**Severity breakdown:** 5 Critical, 7 High, 3 Medium
**Reviewers:** Security Adversary (Fact Checker), Assumption Destroyer (Scope Auditor), Failure Mode Analyst (Flow Tracer) — 27 raw findings deduplicated to 15. Fact-check: 13/20 sampled claims VERIFIED, 5 FAILED, 2 UNVERIFIED.

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | ~13 unqualified `X::class` bindings in `app/Models` invisible to string grep (Room-incident repeat) | Critical | Accept | Phases 2,3,4,5 |
| 2 | Phase 3 premise false — 3 live Eloquent queries on GoldTransaction in Engagement (incl. reclaim money-guard) | Critical | Accept | Phase 3 (rewritten) |
| 3 | 4 placement arch tests assert shims must EXIST; unfixed = arch suite red | Critical | Accept | Phases 2,3,4,5 |
| 4 | Phase 4 targeted nonexistent `uploads()`; real relation `attachments()` has live consumers | Critical | Accept | Phase 4 (rewritten) |
| 5 | "Byte-identical" mandate would freeze push-shape `crm_file_id` cross-application re-point defect | Critical | Accept | Phase 5 (gate Q4 + negative test) |
| 6 | `frozen_routes` snapshot pins the route file being deleted | High | Accept | Phase 1 |
| 7 | `QueryReplyResource` fails silent on missing relation (attachment key vanishes, HTTP 200) | High | Accept | Phase 4 (presence tests) |
| 8 | Morph-coverage claim false — 0/6 models in backfill migrations or morph map | High | Accept | Phase 1 (pre-flight) + Phase 2 |
| 9 | No deploy sequence (classmap/opcache/queue-worker) | High | **Reject (user)** — deploy handled outside plan; local tests gate prod | — |
| 10 | "Zero consumers" greps name-ambiguous (`response`/`ticket` collisions) — unfalsifiable | High | Accept | Phase 2 (model-anchored inventory) |
| 11 | Goal 2 unachievable — 3 assertion-data test files stay in baseline forever | High | Accept | plan.md Goal 2 + Phase 5 |
| 12 | Phase 5 targeted DEAD controller; orphan ingest services should be deleted, not swept | High | Accept | Phase 1 (deletions) + Phase 5 (live callers) |
| 13 | `summariesByIds(id-list)` contract = policy-bypassing enumeration oracle | High | Accept | Phase 4 (entity-keyed contract + denial test) |
| 14 | Phase 5 missed live NE-sync caller (swallows Throwable; NE-empty deletes all `ne:` docs) | High | Accept | Phase 5 |
| 15 | Phases not independently revertable — shared exact-match constants | Medium | Accept | plan.md ordering + per-phase rollback |
| — | `FormResponse.uploads()` speculative-contract complaint | Medium | Auto-reject | Superseded by finding 4 (relation live under real name) |

### Whole-Plan Consistency Sweep

Performed after applying findings — all 6 plan files rewritten in one pass, checked for stale terms:
- `uploads()` relation name: purged everywhere; only `attachments()` remains. ✔
- Dead ingest stack: Phase 1 deletes; Phase 5 no longer lists global controller/services (except live `ApplicationBackfillService`, kept + noted in both). ✔
- Goal 2 wording in plan.md matches Phase 5 "Completion state" (5-entry floor). ✔
- Phase ordering note (serialized) consistent with per-phase Rollback sections. ✔
- Morph claim: false "covered" sentence removed; replaced by Phase 1 pre-flight + per-phase morph gate. ✔
- Effort updated: Phase 3 → 1-1.5d, Phase 4 → 2d, plan total 5-6d. ✔
- Unresolved contradictions: none.

## Validation Log

### Session 1 — 2026-08-15
**Verification pass:** skipped per guard — Red Team Review already carries verification evidence (13/20 VERIFIED, failures corrected). No `[UNVERIFIED]` tags remain.
**Questions asked:** 4

| # | Topic | Decision |
|---|-------|----------|
| 1 | Push-shape `crm_file_id` cross-application re-point defect | **FIX** — scope push match key by `(crm_file_id, student_application_id)` like NE; negative test mandatory. No longer a gate question. |
| 2 | NE-empty-documents delete-all edge | Gate investigates real NE CRM behavior, then rules; characterization test pins the ruling |
| 3 | Phase 3 gold-query routing | **GoldService** (global service) — no new contract surface; queries travel with the service if it later moves into Merchandise |
| 4 | Phase 5 gate timing | Gate review (ADR, no code) runs **in parallel** with phases 1-4 |

### Whole-Plan Consistency Sweep (Session 1)
- Phase 5 gate list updated: push-key scoping moved from gate question → decided requirement; remaining gate rulings = write ownership, reconciliation ownership, NE-empty edge, `link` scheme allowlist. ✔
- plan.md phases note updated for parallel gate review. ✔
- Phase 3 already specifies GoldService routing — no change needed. ✔
- No unresolved contradictions.

## Merge Log — 2026-08-15 (Session 2)

Per user request, consolidated the remaining in-progress legacy-refactor plans into this plan:

| Source plan | What merged | Source disposition |
|-------------|-------------|--------------------|
| `260811-0012-deprecated-model-shim-namespace-sweep` (phases 1-4 done, phase 5 pending) | Nothing to copy — this plan's phases 1-5 ARE its phase-5 close-out, upgraded by red-team | Marked completed/superseded → pointer here |
| `260815-0942-auth-legacy-single-source` (phase 0 audit done; 1-4 pending) | Phases 1-4 → this plan's phases 6-9 (incl. its Validation Session 1 decisions, phase-0 parity results) | Marked superseded → pointer here |
| `260807-0042-legacy-notificationemail-decommission` (phases 1-4 completed; 5 pending) | Phase 5 → this plan's phase 10 (verbatim, incl. its red-team F1/F12 notes) | Marked superseded (phase 5 only) → pointer here |

NOT merged (user confirmed): `260726-0333-zero-migration-debt-closure` — XL program umbrella (8 phases remaining); stays standalone as the program index. This plan clears its shim/auth/notification sub-fronts; domain batches for ~70 ALIVE-LEGACY models remain future per-domain plans per `advise-260815-1000`.

### Whole-Plan Consistency Sweep (Session 2)
- Cross-references in merged phases repointed: source "Phase 1/2/4" → "Phase 6/7/9"; Phase 7 cleanup handoff → Phase 9 step 5. ✔
- Phase 7 vs shim track overlap on `app/Models/Student.php`/`User.php` noted in ordering. ✔
- Success criteria + goals extended for auth + drops; global-file exception for `ParentOrStudentAccess.php` recorded. ✔
- No unresolved contradictions.

## Execution Log — 2026-08-16 (Session 3, auth track)

Phases 6-9 implemented and verified in one session. Two live decisions made mid-implementation (not pre-resolvable from code):
1. `Student::BLOCKED_STATUSES` unification for the parent-proxy allowlist → user approved adding `suspended`, `admission_deferred`, `pending_course_opening`.
2. Whether the same gate should also apply to student's own login → user declined; `StudentLifecyclePortalCompatibilityTest` proved raw-status `'pending'` students must still be able to log in. Login stays gate-free on `Student.status` (see Phase 9 Deviations).

Independent `code-reviewer` + `tester` subagents ran post-implementation. Tester: 182 tests green, all pre-existing baseline failures confirmed unchanged. Code-reviewer: 3 HIGH + 4 MEDIUM + 4 LOW findings; all HIGH/MEDIUM addressed except two accepted-as-is (see Phase 9 Deviations for the full list and rationale): moved `/auth/logout` out of the `parent_or_student` middleware group (holds/status must never block logout), added the missing `AuthenticationException` branch to `LecturerAuthController::refresh()` (was silently 500ing), wrapped `CleanupParentDataCommand`'s Case 1 in a transaction, widened `LoginPipeline::verifyGoogleIdToken()`'s catch to cover Firebase JWT's `UnexpectedValueException` family (was only catching `Google\Exception`). Re-verified full suite after fixes: 163/164 green (1 pre-existing unrelated failure).

Full diff uncommitted at session end — user has not yet approved committing.

<!-- slug: close-remaining-legacy-shims-and-final-dead-cleanup -->
