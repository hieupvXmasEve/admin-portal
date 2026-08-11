---
title: "Student Applications: full CRM columns, column visibility, advanced filters"
description: "Expose all synced CRM fields as toggleable columns on /student-applications, add multi-column advanced filtering, and retire duplicate document-type columns."
status: done
priority: P1
effort: "3-4d"
tags: [admissions, crm, ui, datatable]
created: 2026-08-10
---

# Student Applications: full CRM columns, column visibility, advanced filters

## Overview

`/student-applications` shows 8 fixed columns + one column per active document
type. The CRM NE sync (plan `260810-0205-crm-ne-application-sync`) landed 20 new
CRM columns on `student_applications` plus academic scores and guardians — none
of which the list surfaces. Filtering is limited to search + status + intake.

Three deliverables:

1. Surface all populated CRM fields as columns, user-toggleable show/hide,
   persisted per browser.
2. Advanced filter panel covering the useful columns.
3. Retire duplicate document-type columns that render permanently-empty or
   confusing duplicates.

## Evidence Base

Measured on the dev DB (`asia`), 2026-08-10. 395 applications, 154 CRM-synced.

**CRM column fill rates** (non-null, non-empty / 395):

| Column | Fill | Verdict |
|---|---|---|
| `crm_campus`, `crm_major`, `province`, `school`, `gpa`, `gpa_type`, `last_synced_at` | 154 | ship |
| `pathway_gateway` | 152 | ship |
| `nationality` 147, `crm_paid_amount` 147, `id_card_place_of_issue` 146, `scholarship` 145 | ~146 | ship |
| `religion` 142, `permanent_address` 140, `graduation_year` 138, `birth_place` 131 | ~135 | ship |
| `uu_dai_gc` | 72 | ship |
| `new_street`, `new_ward` | 4 | **exclude** |
| `new_province` | 0 | **exclude** |
| `intended_specialization`, `sut_id`, `study_link_status` | 0 | **exclude** |

Pre-existing non-CRM columns already populated and worth exposing: `gender` 390,
`ethnicity` 271, `address` 331, `english_test_type` 176, `overall` 206.

**Academic scores**: `application_academic_scores` has 1407 rows / 154 apps,
13 `school_report` subjects, **0 `national_exam` rows**. The 4 `thithpt_*`
subjects in `CrmApplicationMapper::NATIONAL_EXAM_SUBJECTS` never arrive.

**Guardians**: `application_guardians` 377 rows / 235 apps.

**Document-type duplicates** — root cause is in
`app/Modules/Admissions/Support/Crm/CrmApplicationMapper.php:34-47`: the mapper
folds CRM's `_1`/`_2`/typo variants into the *base* `file_type_code` using
`page_index`, but the catalog (`application_document_types`) still carries the
variant codes as separate rows, so each renders its own column.

| Base code | Variant code | Variant docs | Variant gets new CRM docs? |
|---|---|---|---|
| `transcript` (Bảng điểm/học bạ) | `transcript_1` (Học bạ THPT) | 330 (96 apps) | no — all legacy |
| `english_certificate` | `english_certificare` | 69 (68 apps) | no — all legacy |
| `other_achievements` | `other_achievements_2` | 36 (22 apps) | no — all legacy |

Verified: `transcript_1` has **zero** rows with a `ne:` prefixed `crm_file_id`
(all 330 are legacy-backfill rows); `transcript` has 294 `ne:` + 443 legacy + 6
manual.

`other_achievements_1` (5 docs) is a *semantically distinct* document ("Giấy xác
nhận sinh viên của anh/chị/em ruột"), not a duplicate — it stays.

## Decisions (user-accepted 2026-08-10)

| # | Decision | Consequence |
|---|---|---|
| D1 | Retire duplicate columns by **deactivating the catalog row only** — no document data migration | 330 + 69 + 36 legacy docs stop appearing in **the list and export only**. They remain visible on the Show/Documents checklist as an uncatalogued group (corrected by red team — the earlier "invisible everywhere" framing was wrong). Rows stay in `application_documents`; reversible by flipping `active` back. See also D9 |
| D2 | Fix **all three** duplicate pairs, not just `transcript` | Kills the whole duplication class in one pass |
| D3 | Column headers in **English** | Consistent with every existing header on this page and `Show.vue`. CRM-owned document names stay Vietnamese as today |
| D4 | Column visibility persisted in **localStorage** per browser | No migration, no endpoint. Follows the existing `useAppearance.ts` pattern |
| D5 | New code uses the canonical `App\Modules\Upload\Models\...` namespace; the repo-wide shim sweep is a **separate plan** | Keeps this plan reviewable. 30 shims / 86 files measured — see Validation Log |
| D6 | Numeric range with min > max returns an **empty result set** | No error-display wiring needed for three range pairs |
| D7 | Doc-type retirement test lives in `tests/Feature/Admissions` | Tests the observable symptom (list rendering), next to existing CRM mapping tests |
| D8 | All new columns default **hidden** | Zero visual change for existing users until they open the toggle |
| D9 | **One transcript satisfies the transcript requirement.** Retiring `transcript_1` drops it from the required set | 149 applications flip incomplete → complete. Accepted: CRM sync only ever fills `transcript`, so requiring `transcript_1` permanently flags every synced applicant for a document that can no longer arrive. Must appear in the release note |
| D10 | Security findings this plan amplifies are fixed **in** this plan, not deferred | Campus fail-closed, `like` escaping, campus-pinned option lists become phase-2 requirements |
| D11 | **Remove `scope=all` from the export entirely** | The UI only ever sends `scope=filtered` (`Index.vue:183`), so nothing legitimate uses it. Export always follows the on-screen filters. Closes the URL-edit bypass and makes the phase-4 parity criterion achievable |
| D12 | **`overall = 0.00` means "no data"** (105 rows) | Renders as `—`, and range filters skip those rows rather than treating them as a zero score. Apply the same rule to `gpa` |
| D13 | Per-field PII permissions and export audit logging are **out of scope** | Pre-existing: the export already emits National ID, Ethnicity, Health Information, and `StudentApplicationPolicy` has no read ability. Recorded as Open Question 5, not widened into this plan |

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Every populated CRM field is available as a column, hidden by default beyond the current 8 | P1 |
| 2 | Users toggle column visibility; choice survives reload | P1 |
| 3 | Advanced filter panel filters on the high-value columns, server-side, whitelisted | P1 |
| 4 | Exactly one column per real document concept | P1 |
| 5 | No regression to sort, pagination, export, or campus scoping | P1 |

## Non-Goals

- No document data migration (D1). No merging of `application_documents` rows.
- No changes to `CrmApplicationMapper` field coverage — this plan surfaces what
  sync already stores, it does not add new CRM fields.
- No saved/named filter presets. No cross-device column sync.
- `Show.vue` does not currently render any of the 20 CRM fields either. Out of
  scope here; flagged in Open Questions.
- No per-subject academic-score columns (13 subjects × relation flattening is a
  separate feature; see Open Questions).
- No repo-wide deprecated-shim namespace sweep (D5). Tracked as its own plan.

## Architecture

```
ListApplicationsRequest  ──whitelist──►  ListApplicationsQuery
   (new filter rules,                      (new select fields,
    expanded sort allow-list)               new where clauses)
                                                  │
                                                  ▼
                                    Inertia props: applications, filters,
                                                   filterOptions
                                                  │
                    ┌─────────────────────────────┼──────────────────────────┐
                    ▼                             ▼                          ▼
        useDataTable<Filters>          useTableColumnVisibility     FilterPanel +
        (extended filter set)          (new, localStorage)          FilterSelect /
                    │                             │                 FilterSearchInput
                    └──────────────► DataTable (show-column-toggle) ◄─────────┘
```

Reuse, do not reinvent:

- `resources/js/components/DataTable.vue` — already implements the column-toggle
  dropdown behind the `showColumnToggle` prop (currently passed `false` on this
  page). Needs one fix: the dropdown renders `{{ column.id }}`, which would show
  `crm_campus` / `doc_transcript` instead of a readable label.
- `resources/js/components/filters/` — `FilterPanel`, `FilterSelect`,
  `FilterSearchInput`, `FilterDateRange` already exist and are used by 10+ pages.
  Closest precedent: `resources/js/pages/Admin/Notifications/Ops/Deliveries.vue`.
- `resources/js/composables/useDataTable.ts` — extend the `Filters` generic; no
  composable changes needed.

## Phases

| # | Phase | Status | Depends on |
|---|-------|--------|-----------|
| 1 | [Retire duplicate document-type columns](./phase-01-retire-duplicate-document-type-columns.md) | Done | — |
| 2 | [Backend column and filter contract](./phase-02-backend-column-and-filter-contract.md) | Done | — |
| 3 | [Frontend column set and visibility](./phase-03-frontend-column-set-and-visibility.md) | Done | 2 |
| 4 | [Advanced filter UI](./phase-04-advanced-filter-ui.md) | Done | 2, 3 |

Phase 1 is independent and shippable on its own.

## Success Criteria

- [x] `/student-applications` offers every column listed in the Evidence Base
      ship-list; the 6 zero/near-zero columns are absent
- [x] Column dropdown shows readable English labels, not raw column ids
- [x] Hiding a column and reloading keeps it hidden (localStorage persistence
      reviewed + eslint-clean; not live-browser verified — no dev auth bypass
      found in this environment, see plan Open Questions)
- [x] Default visible column set equals today's 8 + document columns (no visual
      regression for users who never open the toggle)
- [x] Advanced filters return correct rows; every filter field is whitelisted
      server-side; an unknown field is rejected, not silently applied
- [x] Filters compose with search, sort, pagination, and campus scoping
- [x] Exactly one column each for transcript / english certificate / other
      achievements
- [x] `tests/Feature/Admissions` green (246 tests, 904 assertions)

## Risks

| Risk | Mitigation |
|---|---|
| **149 applications flip incomplete → complete** when `transcript_1` leaves the required set | D9 is explicit and tested. Must be in the release note — admissions staff need to know the completeness rule changed |
| Deactivating a catalog row hides real legacy docs (D1) | Accepted. Retained in DB and still shown on the checklist; `active` flip restores the columns |
| `ApplicationBackfillService` re-run resurrects the deactivated rows | Guard moved **into** `ApplicationDocumentTypeSyncService` via a `RETIRED_CODES` constant, with a test. A docblock was not sufficient — the catalog is runtime-synced state |
| 40+ columns make the table unusably wide | `ui/table` is `w-full` inside `overflow-auto`, so it **compresses rather than scrolls**. Phase 3 adds an explicit min-width step and verifies with 25 columns. (The earlier "already handled" claim was false.) |
| Campus scoping fails **open** when the session holds a deleted campus id | Phase 2 makes null campus return zero rows in both the query and the export action |
| `scope=all` export bypassed every filter | **Removed entirely (D11).** Nothing legitimate used it — the UI only ever sent `scope=filtered` |
| `like` filters treat `%`/`_` as wildcards | Phase 2 adds a shared `escapeLike()`; asserted by test |
| Option lists leak another campus's applicant population via Inertia props | Phase 2 pins `$campusCode` in the shared helper signature; asserted by test |
| Readiness query runs per pending row (up to 200/page) | Pre-existing; phase 2 explicitly does not claim to fix it. Batching is out of scope |
| Filter explosion causes unindexed full scans | Restrict filterable set; measure before indexing. 395 rows today |
| Sentinel change empties the list for stale bundles or bookmarked URLs | Phase 4 keeps the backend accepting `'all'`/`''`/null/absent permanently; only the frontend normalizes |

## Validation Log

### Session 1 — 2026-08-10

**Verification Results** (Standard tier: Fact Checker + Contract Verifier, 4 phases)

- Claims checked: 18
- Verified: 17 | Failed: 1 | Unverified: 0

Verified with file:line evidence:

| Claim | Evidence |
|---|---|
| `DataTable` already renders a column-toggle dropdown | `DataTable.vue:154-176` |
| Toggle labels leak raw ids | `DataTable.vue:172` = `{{ column.id }}` |
| `columnVisibility` is a local ref | `DataTable.vue:41` |
| This page disables the toggle | `Index.vue:304` `:show-column-toggle="false"` |
| `FilterSelect` "all" sentinel is `''` | `FilterSelect.vue:29` |
| Page uses `'all'` sentinel instead | `Index.vue:123-129`, `ListApplicationsQuery.php:26,29` |
| localStorage precedent exists | `useAppearance.ts:43,69,79` |
| `Collapsible` ui component exists | `resources/js/components/ui/collapsible` |
| `scopeActiveOrdered` exists | `Modules/Upload/Models/ApplicationDocumentType.php:69` |
| Three surfaces read via `activeOrdered()` | `ListApplicationsQuery.php:48`, `GetApplicantDocumentChecklistQuery.php:23`, `StudentApplicationExport.php:44` |
| NE sync syncs only 2 hardcoded doc-type codes | `CrmApplicationSyncService.php:52-55` |
| `transcript_1` has zero `ne:` documents | DB: 330 legacy, 0 `ne:` |

**FAILED (1)** — plan referenced `App\Models\ApplicationDocumentType`, which is a
deprecated `class_alias` shim (`app/Models/ApplicationDocumentType.php:12`).
Canonical is `App\Modules\Upload\Models\ApplicationDocumentType`. Corrected in
phase 1 and phase 2 per D5.

**Interview decisions:** D5, D6, D7, D8 (see Decisions table).

**Scope challenge raised and accepted:** the user initially chose a full shim
sweep. Measurement showed 30 shim models / 86 files repo-wide, and completed plan
`260809-1557-legacy-model-module-migration` line 40 already reserved that work
for a separate follow-up plan. Presented the conflict; user selected the
separate-plan option. Created
`plans/260811-0012-deprecated-model-shim-namespace-sweep/`. No cross-plan
`blockedBy` relationship — the sweep and this plan touch different concerns and
can land in either order.

Writing that sweep plan surfaced a finding that also validates D5: `activity_log`
persists 218 rows of shimmed FQCNs (`Room` 87, `RoomBooking` 31, `ClubMember`
100), so the sweep is stateful, not mechanical. Folding it into this UI plan
would have imported a data-migration risk into a frontend change.

**Ambiguity resolved by measurement, not deferred:** select-vs-free-text per
filter field is now fixed by measured distinct counts (phase 2 Architecture).
`nationality` and `uu_dai_gc` (1 distinct each) dropped from the filter set.

### Whole-Plan Consistency Sweep

Re-read `plan.md` + all 4 phase files after propagation.

- Namespace: phase 1 and phase 2 both state canonical-in-new-code and
  leave-existing-imports. No contradiction.
- Filter field groups: phase 2 group table, measured-counts table, and phase 4
  UI groups now agree — `nationality`/`uu_dai_gc` are columns-only in all three.
- `school`/`birth_place`: phase 2 risk row updated from "measure first" to the
  resolved outcome; no stale "measure before choosing" text remains.
- min > max: phase 2 success criterion no longer offers two options.
- Default-hidden columns: plan D8 and phase 3 agree.
- Sentinel normalization appears only in phase 4 and is not contradicted
  elsewhere.

Unresolved contradictions: **none**.

## Red Team Review

### Session 1 — 2026-08-11

**Reviewers:** Security Adversary, Assumption Destroyer, Failure Mode Analyst
(3 lenses, 4 phases). **Findings:** 27 raw → 21 after dedup. **20 accepted, 1
reclassified.** Every finding carried `file:line` evidence; none failed the
evidence filter.

**Severity:** 1 Critical, 11 High, 9 Medium.

| # | Finding | Sev | Disposition | Applied to |
|---|---|---|---|---|
| 1 | `transcript_1` is `required=1` — retiring it flips **149** applications incomplete → complete | Critical | Accept → **D9** | Phase 1 |
| 2 | Checklist re-emits retired types as an uncatalogued group; "invisible everywhere" was wrong | High | Accept | Phase 1, D1 |
| 3 | Migration is the wrong guard for runtime-synced catalog state; backfill re-run reactivates | High | Accept | Phase 1 |
| 4 | Sort allow-list duplicated in 2 FormRequests; sort-then-export 422s | High | Accept | Phase 2 |
| 5 | Export search omits `student_code` — parity already false pre-plan | High | Accept | Phase 4 |
| 6 | `scope=all` bypasses every filter; parity criterion unachievable | High | Accept | Phase 4 |
| 7 | `''` never reaches the server (`useDataTable.ts:68`) — sentinel plan would empty the list | High | Accept | Phase 4 |
| 8 | Third sentinel site (`ExportApplicationsAction.php:28`) unlisted | High | Accept | Phase 4 |
| 9 | `useDataTable.navigate()` drops concurrent requests; ~12 immediate selects planned | High | Accept | Phase 4 |
| 10 | Campus scoping fails **open** on a stale/deleted session campus | High | Accept → D10 | Phase 2 |
| 11 | `crm_campus` distinct = **1 per campus**; counts were measured unscoped | High | Accept | Phase 2 |
| 12 | `Rule::in()` from live distincts → bookmarked filter 422s, contradicts D6 | High | Accept | Phase 2 |
| 13 | `filters()` helper unpinned on campus → cross-campus value leak in props | High | Accept → D10 | Phase 2 |
| 14 | Toggle label change affects **58** pages, not "several"; 2-3 spot-check inadequate | Med | Accept | Phase 3 |
| 15 | `w-full` table compresses instead of scrolling; cited mitigation did not exist | Med | Accept | Phase 3, Risks |
| 16 | `meta.label` needs a `ColumnMeta` augmentation that does not exist; eslint can't catch it | Med | Accept | Phase 3 |
| 17 | Phase-1 success criterion logically unsatisfiable (`other_achievements_1`) | Med | Accept | Phase 1 |
| 18 | Phase-1 validation command used the shim D5 forbids | Med | Accept | Phase 1 |
| 19 | Phase-1 "no documents deleted" criterion is tautological — migration can't delete them | Med | Accept | Phase 1 |
| 20 | "No N+1" requirement false on arrival (readiness per pending row) | Med | Accept | Phase 2 |
| 21 | `like` wildcards unescaped; `filterOptions` computed then discarded; localStorage survives revert | Med | Accept | Phase 2, 3 |

**Reclassified:** the Security Adversary rated `scope=all` as a full-*database*
dump. Verified false — campus scoping sits outside the `scope` branch
(`ExportApplicationsAction.php:20-23`), so it is a full-*campus* dump. Still
accepted at High; the stated blast radius was corrected.

**Not adopted as plan requirements:** a per-field permission gate for
religion/ethnicity/national_id, and export audit logging. Both are real gaps
(`StudentApplicationPolicy` has no read ability; `ListApplicationsRequest::authorize()`
returns `true`), but they are pre-existing and product decisions rather than
consequences of this plan. Recorded in Open Questions.

**Self-inflicted defects found:** 4 of the accepted findings (#17, #18, #19, #20)
were errors in the plan's own text, two of them inside the file the Validation
Log had just swept and declared contradiction-free. The prior sweep was
insufficient.

### Whole-Plan Consistency Sweep

Re-read `plan.md` + all 4 phase files after applying findings.

- **D1 restated** in both `plan.md` and phase 1 to say list+export only, not
  "invisible everywhere". No surviving copy of the old framing.
- **D9 added** and referenced consistently from plan Decisions, plan Risks,
  phase-1 Overview/Requirements/Success Criteria.
- **`crm_campus`** removed from phase-2's filter-group table, marked
  columns-only in the distinct-counts table, and absent from phase-4's UI
  groups — all three agree.
- **Sentinel**: phase 4 now states the four accepted forms in Architecture,
  Implementation Step 1, Success Criteria, and Risks; the old "adopt `''` and
  update two comparisons" text is gone. Three PHP sites enumerated.
- **Sort allow-list**: phase 2 Architecture, Related Code Files, and Success
  Criteria all reference the shared constant and both FormRequests.
- **Table width**: the false "horizontal scroll already handled" mitigation is
  removed from plan Risks and replaced in phase 3 with an explicit step.
- **Namespace**: phase-1 validation command now uses the canonical FQCN,
  consistent with D5.
- **N+1**: the false non-functional requirement is removed from phase 2 and
  replaced with an explicit statement that the behavior is unchanged.
- Cross-plan link to the shim sweep resolves to the real directory.

Unresolved contradictions: **none**.

### Session 2 — 2026-08-11 (post-red-team re-validation)

Triggered because all four phase files were rewritten after the red team, so the
current text had never been through a verification pass.

**Verification:** skipped the full pass per the workflow guard (a
`## Red Team Review` section with verification evidence already exists).
Spot-checked only claims the rewrite *introduced*:

| New claim | Result |
|---|---|
| `useDataTable.navigate()` early-returns while a request is in flight (drives the debounce decision) | VERIFIED `useDataTable.ts:118-119` |
| `ApplicationDocumentService` has the same uncatalogued fallback as the checklist query | VERIFIED `ApplicationDocumentService.php:52-58` |
| Post-retirement active catalog is exactly 8 named codes | VERIFIED against current catalog (11 active − 3 retired) |
| `FilterPanel` consumer count | **CORRECTED** 8 → 9 pages |

**Interview decisions:** D11, D12, D13 (see Decisions table).

- **D11** also simplifies phase 4: removing `scope` leaves one export code path,
  so the parity criterion became assertable rather than aspirational.
- **D13** keeps the PII gap out of scope. It is real but pre-existing — the
  export already emits National ID, Ethnicity, and Health Information today.

### Whole-Plan Consistency Sweep (Session 2)

- `scope=all` removed from phase-4 Architecture, Implementation Steps, Success
  Criteria, and Risks. No text still asks the reader to "decide" it.
- D12 propagated to phase 2 (range filters must exclude zeros explicitly) and
  phase 3 (render `0.00` as `—`), including both success criteria.
- `FilterPanel` count corrected to 9 in phase 4.
- Decisions table now runs D1–D13 with no superseded entries.

Unresolved contradictions: **none**.

## Implementation Log — 2026-08-11

All 4 phases implemented and merged into working tree. 246 tests green
(`tests/Feature/Admissions` + `tests/Feature/StudentApplication`, 904
assertions), eslint clean on all touched frontend files, Pint clean on all
touched PHP files.

**Post-implementation code review** (independent `code-reviewer` agent, ran
tests itself) found no critical/high defects that survived; applied fixes:

- eslint failure on the `ColumnMeta` type augmentation (unused generics —
  standard TanStack pattern, disabled the lint rule for that line)
- Pint style violations in 5 new/touched files (FQCN docblocks)
- the null-campus export test was a phantom assertion (`not->toBeNull()`
  passes for any response); rewritten to assert the export's query returns
  zero rows
- added filter-coverage tests for the remaining `SELECT_FILTERS`, `synced`,
  and the `crm_paid_amount` range (previously only 3 of ~19 fields were
  row-asserted)
- collapsed the controller's hand-written 19-key filter array into one built
  from `ListApplicationsQuery::ADVANCED_FILTER_KEYS`, removing a third
  parallel filter-key list that could silently drift from the validation
  rules and the export whitelist
- removed the unused pre-D11 `campus_code` export filter param (nothing ever
  sent it; it duplicated session campus scoping as dead surface)
- `applyRange()` now also treats an empty-string bound as absent (previously
  only `null` short-circuited the zero-exclusion `where`)
- `fieldValue()` now renders an empty string as `—`, not just `null`

**Known limitations, not fixed in this pass:**

- `useDataTable.navigate()` (shared composable, used by many list pages)
  drops a navigation if one is already in flight and never re-fires. Rapidly
  changing two advanced-filter selects can leave the UI showing a filter the
  server never applied. Phase-4's "no dropped navigation" criterion is
  therefore aspirational as shipped — fixing it means changing shared
  navigation behavior across every page that uses the composable, which is
  out of this plan's blast radius. Flagged for a follow-up.
- D9's required release note (transcript_1 retirement flips ~149
  applications from incomplete to complete) has no home: this repo has no
  release-notes/changelog surface. Recorded here instead; whoever
  communicates this rollout to admissions staff should read D9 and this note
  before doing so.
- Frontend column-visibility persistence (phase 3) and the advanced-filter
  panel (phase 4) were verified by code review + eslint, not by a live
  browser session — the app only supports Google OAuth login and no
  dev-login shortcut was found in this environment/session.
- Whole-project `vue-tsc --noEmit` reliably OOMs in the `swinx-app-dev`
  container (pre-existing, documented constraint) — type-checking for the
  new `ColumnMeta` augmentation relied on eslint + manual review only.

## Open Questions

1. Should `Show.vue` also render the 20 CRM fields? It currently shows none of
   them — same information gap, different surface. Separate plan?
2. Do the 13 `school_report` subject scores belong as list columns, or only on
   the detail page? Flattening a 1-n relation into 13 columns is a real cost.
3. `CrmApplicationMapper::NATIONAL_EXAM_SUBJECTS` (4 `thithpt_*` fields) has
   produced 0 rows ever. Dead CRM contract, or not yet in season?
4. `new_province` (0/395) is mapped by the sync but never populated. Is the CRM
   field name wrong, or genuinely unused?
5. **Should religion / ethnicity / national_id sit behind a separate
   permission?** They become toggleable *and* exportable here.
   `StudentApplicationPolicy` has no read ability at all; access is one coarse
   `can:view_student_application` route gate, and successful exports are not
   logged. Pre-existing, but this plan widens what a holder of that permission
   can extract. Raised by red team; not adopted as a requirement.
6. ~~What should `scope=all` mean?~~ **Resolved: D11 removes it.**
7. ~~Does `0.00` in `overall`/`gpa` mean "unknown"?~~ **Resolved: D12, yes.**
8. `routes/web/student-application.php` is not required from `routes/web.php`, so
   `app/Http/Controllers/Web/StudentApplicationController.php` appears to be an
   unreachable duplicate of the module controller. Delete, or is it pending
   revival? Out of scope here.

<!-- slug: student-applications-crm-columns-and-filters -->
