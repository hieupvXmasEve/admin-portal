# Phase 1 — Backend: migration + config-aware aggregate query + threshold unification

## Context

Overall Rating calc sites in
`app/Modules/Engagement/Queries/Surveys/GetSurveyRunAggregateQuery.php`:
- `executeHeader` L44-50: all answers `answer_number > 0` (NO question-type filter), thresholds hardcoded ≥4 / ==3 / ≤2.
- Deprecated `execute()` L138-259 holds a duplicate overall calc at L183-191 — **zero callers repo-wide → delete the whole method** (smaller diff than updating it).
- `executeSections` L74-131 computes SECTION-level stats (L95-103) with hardcoded ≥4/≤2 (already rating-filtered) — must adopt config thresholds.
- Export `app/Exports/SurveyRunAggregateExport.php` L115-135 hardcodes ≥4/≤2 and derives section Neutral as `100 - pos - neg` — must adopt config thresholds.

Model: real Form model is `app/Modules/Engagement/Models/Form.php` ($fillable L21, $casts L30).
`app/Models/Form.php` is a deprecated `class_alias` shim — do NOT touch it.

## Steps

1. Migration `database/migrations/2026_08_10_000001_add_aggregate_config_to_forms_table.php`:
   `$table->json('aggregate_config')->nullable()` on `forms`. Rollback rule: revert code
   before ever dropping the column.
2. `app/Modules/Engagement/Models/Form.php`: add `'aggregate_config' => 'array'` to `$casts`.
   **Do NOT add to `$fillable`** — written only via explicit `forceFill`/setter in the gated
   controller (phase 2), keeping mass-assignment paths (`UpdateFormRequest` flows) unable to touch it.
3. New `app/Modules/Engagement/Support/SurveyAggregateConfig.php` (final, readonly):
   - `static fromForm(?Form $form): self` — parse `aggregate_config`, tolerate malformed JSON → defaults.
   - Fields: `?array $questionCodes` (null = all rating questions), `int $positiveMin = 4`, `int $negativeMax = 2`, `bool $isCustom`.
   - Bucket helpers: `isPositive(float)`, `isNegative(float)`; neutral = neither.
4. Rework `GetSurveyRunAggregateQuery`:
   - Delete deprecated `execute()` (zero callers — verified).
   - Resolve config once per call: `SurveyAggregateConfig::fromForm($target->form)`.
   - Overall answer selection (executeHeader): join `questions` on `a.question_id`,
     filter `q.type = 'rating'`, `answer_number > 0`; when `$questionCodes !== null`,
     add `q.code IN (...)`. Codes that no longer resolve match nothing (staleness is
     visible via matched-question count).
   - Extract ONE private helper `overallKpis(Collection $ratingAnswers, SurveyAggregateConfig $cfg): array`
     used by `executeHeader` (single remaining overall site after execute() deletion).
   - Section stats in `executeSections` (L95-103): replace hardcoded 4/2 with
     `$cfg->positiveMin` / `$cfg->negativeMax`. Per-question charts still list ALL
     rating questions (display is not filtered by config; only Overall is).
   - `executeHeader` payload additions under `overall`: `is_custom` (bool),
     `positive_min`, `negative_max`, `included_count` (matched rating-question count) —
     FE labels/colors + staleness hint consume these (phase 3).
5. `app/Exports/SurveyRunAggregateExport.php`: accept thresholds (from header data already
   passed in), replace hardcoded ≥4/≤2 at L115-135; section Neutral derived value stays
   `100 - pos - neg` but now from config thresholds.
6. `SurveyResultController` unchanged (both `aggregate` and `downloadAggregate` flow
   through executeHeader/executeSections — verified only callers).

## Validation

- New `tests/Feature/Form/SurveyAggregateConfigTest.php`:
  - no config → avg over rating questions only (non-rating numeric answer excluded), 4/2 buckets.
  - config with subset of codes → only those counted; unknown/stale code matches nothing.
  - custom thresholds respected in overall AND section stats AND export rows.
  - non-integer answer 3.5 → Neutral bucket (pins accepted behavior change).
  - form with config: old target (older form_version_id) also gets config (form-level).
- Update `tests/Feature/Form/SurveyResultDownloadTest.php` fixtures if they relied on
  non-rating answers or `==3` neutral.
- Run: `./scripts/dev.sh artisan test --filter=SurveyAggregateConfig` then `--filter=SurveyResult`.

## Risk / rollback

Behavior change on default path (rating-only + neutral range) — accepted, pinned by tests.
Rollback = revert code commit; keep column (additive). Never `migrate:rollback` ahead of code revert.
