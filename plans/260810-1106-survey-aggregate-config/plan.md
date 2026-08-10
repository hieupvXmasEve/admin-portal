# Survey Aggregate Config — dynamic Overall Rating per survey

Status: IMPLEMENTED (2026-08-10) — all 3 phases shipped on `dev`, unpushed
Owner module: Engagement

## Outcome

Overall Rating KPI on `/forms/admin/results/{target}/aggregate` (+ download export)
computed from per-form config: pick rating questions (UI grouped by section — whole
section or individual questions), plus configurable positive/negative thresholds.
Config edited in form builder UI, gated by new permission `configure_survey_aggregate`.

## Contract

- **Constraints:**
  - Config is **form-level** (`forms.aggregate_config`), keyed by stable `questions.code`
    — survives version churn (`updateFormStructure` creates a new FormVersion on every
    structure save) and `cloneForm` replicate. Applies immediately to existing targets.
  - No config → default = avg of ALL `rating`-type questions, thresholds 4/2.
  - Accepted behavior changes on old surveys (no config): (1) non-rating
    `answer_number` answers no longer counted; (2) Neutral becomes a range
    (negative_max < x < positive_min) instead of exact `== 3` — non-integer stored
    answers (column is decimal(12,4)) e.g. 3.5 move from "no bucket" into Neutral.
  - Scale stays 1-5; thresholds integer 1..5, `positive_min > negative_max`.
  - Export must match screen; section-level KPIs and FE coloring use the SAME
    thresholds as the Overall card (no split-brain on one page).
  - JSON config, no DB enums. `aggregate_config` NOT mass-assignable (written via
    explicit setter in gated controller only).
- **Non-goals:**
  - Lecturer-GPA page (`ListLecturerGpaQuery`) untouched — formula may diverge; accepted.
  - Stats page (`GetSurveyProgramStatsQuery` "high_rated" ≥4 hardcode) untouched —
    may diverge from configured surveys; accepted, documented here.
  - No weights, no formula engine, no per-version config.
  - New questions added to a fully-selected section are NOT auto-included (config
    stores explicit codes) — admin re-opens config to add them.
- **Acceptance:**
  1. Form with config → Overall on every target of that form (old + new) uses only
     selected question codes + config thresholds.
  2. No config → avg of all rating questions, thresholds 4/2 (neutral derived).
  3. `configure_survey_aggregate` permission gates panel + save endpoint; permission
     granted to roles that hold `edit_survey` (deploy step), verified usable post-deploy.
  4. `aggregate/download` export matches on-screen KPIs, including section rows.
  5. UI prefills positive_min=4, negative_max=2 (shown as "≥4 Positive / 3 Neutral /
     ≤2 Negative" helper) when creating config.
  6. Reset action returns form to default (nulls column).
  7. Config with empty selection is rejected (422).
  8. Structure re-save and form clone do NOT lose/corrupt config (question codes stable).

## Config shape (`forms.aggregate_config` JSON, nullable)

```json
{
  "overall": {
    "question_codes": ["a1", "a2", "a3", "b2", "b5"],
    "thresholds": { "positive_min": 4, "negative_max": 2 }
  }
}
```

- UI groups by section and offers "select all in section", but persists the expanded
  explicit `question_codes` list (section ids are per-version surrogate keys —
  NOT stored).
- Validation: codes exist in the form's latest published version, are `rating` type,
  list non-empty (`min:1`); thresholds int 1..5, `positive_min > negative_max`.
- Read time: filter `q.code IN (codes) AND q.type='rating'`; codes that no longer
  resolve simply match nothing (staleness surfaced via `overall.is_custom` +
  matched-question count in payload).
- `null` config = default (all rating questions). Empty array never persisted.

## Phases

| # | Phase | File | Depends |
|---|-------|------|---------|
| 1 | Backend: migration + config-aware query + threshold unification | [phase-1-backend.md](phase-1-backend.md) | — |
| 2 | Permission (incl. role grant) + gated save endpoint + route hardening | [phase-2-permission-endpoint.md](phase-2-permission-endpoint.md) | 1 |
| 3 | Form builder UI | [phase-3-frontend.md](phase-3-frontend.md) | 2 |

## Pinned paths (all layers)

- Migration: `database/migrations/2026_08_10_000001_add_aggregate_config_to_forms_table.php`
- Model cast: `app/Modules/Engagement/Models/Form.php` (real model; `app/Models/Form.php`
  is a deprecated `class_alias` shim — do NOT touch). Cast only, NOT in `$fillable`.
- Query: `app/Modules/Engagement/Queries/Surveys/GetSurveyRunAggregateQuery.php`
  (modify; overall calc sites: `executeHeader` L44-50 and inside deprecated
  `execute()` L183-191 — `execute()` has zero callers → DELETE it; section-stat
  sites L95-103 within `executeSections` get config thresholds)
- Config value object: `app/Modules/Engagement/Support/SurveyAggregateConfig.php` (new)
- Export: `app/Exports/SurveyRunAggregateExport.php` (modify — hardcoded ≥4/≤2 +
  derived neutral at L115-135 must use config thresholds)
- Permission: `config/permission.php` → `surveys` group, key `configure_survey_aggregate`;
  row created by `app/Console/Commands/SyncPermissions.php`; role grant via
  `database/seeders/UpdatePermissionsSeeder.php` run (grants super admin) + explicit
  grant to roles holding `edit_survey`
- Controller: `app/Modules/Engagement/Http/Web/Admin/SurveyAggregateConfigController.php` (new)
- FormRequest: `app/Modules/Engagement/Http/Requests/Forms/UpdateAggregateConfigRequest.php` (new)
- Route: `app/Modules/Engagement/routes/web.php` → `PUT forms/admin/{form}/aggregate-config`;
  same file: add `can:` middleware to currently-ungated form write routes (see phase 2)
- FE component: `resources/js/components/forms/AggregateRatingSettings.vue` (new)
- FE pages: `resources/js/pages/Forms/Admin/Edit.vue` (mount panel),
  `resources/js/pages/Forms/Admin/results/Aggregate.vue` (threshold-aware labels/colors,
  `AggregateOverall` interface at L39 gets `is_custom` + threshold fields)
- Tests: `tests/Feature/Form/SurveyAggregateConfigTest.php` (new),
  update `tests/Feature/Form/SurveyResultDownloadTest.php`

## Risks

- Behavior shift on old surveys (rating-only filter + neutral-as-range). Accepted,
  listed in Contract. Pin with tests incl. one non-integer (3.5) answer.
- Rollback rule: revert code first, keep the column; NEVER `migrate:rollback` while
  config-reading code is deployed.
- Three sibling formulas remain (aggregate/config, stats page, lecturer GPA) — numbers
  may disagree across pages; documented in Non-goals.
- CSRF active in feature tests — include `_token` in PUT tests.
- Campus note: `forms` has no campus_id — config is global per form; a coordinator
  with the permission changes KPIs for all campuses using that form. Accepted for now.

## Validation Log

### Session 1 — 2026-08-10
Verification pass: skipped per guard (Red Team Review below already carries
codebase-verified evidence, 0 unresolved [UNVERIFIED] tags).

**Questions asked:** 4 — all answered with recommended option; plan already
reflects every decision, no phase edits required.

| # | Decision point | Answer |
|---|----------------|--------|
| 1 | Route hardening scope | Forms CRUD only (create/store/edit/update/destroy); runs/inbox hardening = separate task |
| 2 | `configure_survey_aggregate` grant | Roles holding `edit_survey` + super admin via seeder |
| 3 | Stale config (included_count=0) | Warning + zeros on aggregate page; NO auto-fallback to default |
| 4 | Retroactivity | Form-level config applies to ALL targets incl. closed runs — historical reports change; accepted |

### Whole-Plan Consistency Sweep
Re-checked plan.md + 3 phase files after session: all four decisions were already
encoded (phase-2 steps 2-3, phase-3 step 3, Contract constraint on form-level).
Zero unresolved contradictions. Plan eligible for implementation.

## Red Team Review

### Session — 2026-08-10
**Findings:** 12 deduped from 28 (3 reviewers: Security Adversary, Assumption Destroyer, Failure Mode Analyst)
**Severity:** 3 Critical, 5 High, 4 Medium — 12 accepted, 0 rejected

| # | Finding | Severity | Disposition | Applied To |
|---|---------|----------|-------------|------------|
| 1 | `app/Models/FormVersion.php` is class_alias shim, not the model | Critical | Accept | Pinned paths (repinned to Engagement Form model; design moved config to forms) |
| 2 | Version churn destroys config (new version per structure save; targets pin old version; clone replicates stale ids) | Critical | Accept | Whole design → form-level + question codes |
| 3 | `PUT /forms/admin/{form}` ungated; UpdateFormRequest no authorize; fillable bypass | Critical | Accept | Phase 2 route hardening; cast-only no fillable |
| 4 | `permissions:sync` grants nobody; no super-admin bypass | High | Accept | Phase 2 grant step + acceptance #3 |
| 5 | Wrong calc-site map (executeSections has no overall; L183-191 in dead `execute()`) | High | Accept | Pinned paths + Phase 1 (delete execute()) |
| 6 | Section KPIs + export + Aggregate.vue hardcode 4/2 → same-page inconsistency | High | Accept | Contract + Phase 1/3, export pinned |
| 7 | Empty include silently hides KPI block; no reset path | High | Accept | Acceptance #6/#7, Phase 2 validation, Phase 3 reset UI |
| 8 | Stale `form_version_id` payload writes to dead version (IDOR-ish) | High | Accept | Form-level design removes version from payload |
| 9 | Sectionless rating questions uncountable in section-rooted UI | Medium | Accept | Phase 3 "Ungrouped" bucket |
| 10 | Neutral `==3` → range is unlisted behavior change; decimal answers shift buckets | Medium | Accept | Contract accepted-changes + test |
| 11 | Stats page formula diverges | Medium | Accept | Non-goals |
| 12 | `is_custom` contract change misplaced in phase 3; `AggregateOverall` interface unpinned; rollback one-way door | Medium | Accept | Moved to Phase 1; FE interface pinned; rollback rule in Risks |

### Whole-Plan Consistency Sweep
Plan and all phase files rewritten wholesale after adjudication (form-level design);
no stale version-level references remain. Verified: no `form_versions.aggregate_config`,
no `section_id` in persisted config shape, no `app/Models/FormVersion.php` edit step,
phase 2/3 no longer send `form_version_id`. Zero unresolved contradictions.

## Implementation Log — 2026-08-10

All 3 phases implemented + code-reviewed. Backend: 9 new tests in
`SurveyAggregateConfigTest.php`, full Form/Survey/Engagement sweep clean (2 pre-existing
unrelated failures in Finance/Upload confirmed identical on clean tree). Frontend: eslint
clean, `npm run build` clean. `configure_survey_aggregate` granted to super_admin +
roles holding `edit_survey` (super_admin, giam_doc_dao_tao, dao_tao) directly via
role_permissions on dev DB.

Post-implementation code review (mandatory gate) found 1 HIGH + 3 MEDIUM/LOW findings,
all fixed except documented judgment calls:
- **Fixed (HIGH):** `SurveyAggregateConfigController::update()` was persisting
  `$request->validated()` directly — Laravel's `validated()` does not strip
  unvalidated sub-keys under a validated parent array, so an attacker with
  `configure_survey_aggregate` could smuggle arbitrary JSON into `aggregate_config`.
  Now builds the persisted array explicitly from known fields.
- **Fixed (MEDIUM):** `SurveyAggregateConfig::isCustom` was true for any
  `{"overall": {...}}` shape even when it degraded to default behavior (empty/missing
  codes). Now `isCustom = ($codes !== null)`.
- **Fixed (MEDIUM):** Aggregate.vue only warned on 100% stale config
  (`included_count === 0`). Added `configured_count` to the payload and a partial-match
  warning banner (steady-state case for form-level retroactive config after a question
  is removed from a later structure edit).
- **Fixed (LOW):** mass-assignment regression test tightened to assert the PUT actually
  succeeded and to prove junk/duplicate keys sent through the real endpoint don't
  survive persistence (closes the same hole as the HIGH fix, end-to-end).
- **Accepted, not changed:** section-level KPIs/export apply config *thresholds* but
  not the *code filter* (all rating questions still count per-section) — this was
  already the plan's design (Contract: "Per-question charts still list ALL rating
  questions; only Overall is filtered"), not a new gap.
- **Accepted, not changed:** `configure_survey_aggregate` is only reachable through the
  `edit_survey`-gated Edit page today, so it can't currently be granted independently
  of `edit_survey`. Matches the plan's Session-1 decision #2 (grant scoped to
  `edit_survey` holders); revisit only if an independent-grant use case appears.
