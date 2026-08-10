# Phase 3 — Form builder UI

## Context

Builder page `resources/js/pages/Forms/Admin/Edit.vue` mounts
`resources/js/components/forms/FormBuilder.vue`; sibling settings panels follow the
`TargetingSettings.vue` / `VisibilitySettings.vue` pattern in `resources/js/components/forms/`.
Aggregate results page `resources/js/pages/Forms/Admin/results/Aggregate.vue` declares a
local `AggregateOverall` interface (L39) and hardcodes color rule `avg >= 4` (L165) and
section coloring (L626-640). `questions.section_id` is nullable — sectionless rating
questions exist and must be selectable.

## Steps

1. New `resources/js/components/forms/AggregateRatingSettings.vue`:
   - Props: sections + questions of latest published version, current `aggregate_config`,
     `canConfigure`.
   - UI: grouped by section — section checkbox = select all its rating questions;
     expandable per-question checkboxes. Sectionless rating questions appear in an
     **"Ungrouped questions"** pseudo-section. Sections with no rating questions hidden.
     Selection state persists as explicit `question_codes` (section grouping is display-only;
     no section ids sent).
   - Thresholds: two number inputs (`positive_min`, `negative_max`) prefilled 4 / 2 on
     create; live helper text "≥{p} Positive, {n+1}..{p-1} Neutral, ≤{n} Negative"
     (renders "≥4 Positive, 3 Neutral, ≤2 Negative" for defaults). Client guard
     `positive_min > negative_max`; block save on empty selection.
   - Empty state: "Using default: average of all rating questions" + "Customize" button.
   - Actions: save → Inertia `put` `forms.admin.aggregate-config.update`;
     "Reset to default" → Inertia `delete` `forms.admin.aggregate-config.destroy`
     (with confirm).
2. `resources/js/pages/Forms/Admin/Edit.vue`: render panel next to Targeting/Visibility
   settings, only when `can_configure_aggregate` prop true.
3. `resources/js/pages/Forms/Admin/results/Aggregate.vue`:
   - Extend `AggregateOverall` interface (L39): `is_custom`, `positive_min`,
     `negative_max`, `included_count` (matches phase-1 payload).
   - Replace hardcoded `>= 4` color rules (L165, L626-640) with threshold props.
   - Badge "Custom formula (N questions)" when `is_custom`; warning hint when
     `included_count === 0` (stale config) instead of silently hiding the KPI block —
     adjust the `v-if="overall.average > 0"` guard (L561) to distinguish "no data" from
     "config matches nothing".

## Validation

- Per-file eslint (whole-project vue-tsc OOMs in dev container — repo gotcha):
  `./scripts/dev.sh npm exec eslint -- resources/js/components/forms/AggregateRatingSettings.vue resources/js/pages/Forms/Admin/Edit.vue resources/js/pages/Forms/Admin/results/Aggregate.vue`
- Manual: configure a survey → aggregate page + export reflect selection + thresholds;
  reset returns default; user without permission sees no panel; stale-config warning
  shows after removing a selected question via structure edit.

## Risk / rollback

FE-only phase (backend contract fields shipped in phase 1). Rollback = drop component +
prop wiring; `is_custom`/threshold fields keep flowing harmlessly from backend.
