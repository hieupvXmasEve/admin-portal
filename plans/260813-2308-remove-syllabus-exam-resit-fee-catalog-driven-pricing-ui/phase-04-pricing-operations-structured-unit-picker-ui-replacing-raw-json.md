---
phase: 3
title: "Pricing Operations: structured unit-picker UI replacing raw JSON"
status: completed
priority: P1
effort: "6h"
dependencies: []
---

# Phase 3: Pricing Operations — structured unit-picker UI replacing raw JSON

## Overview

Replace the raw `facts_match_json` `<Textarea>` on `/finance/pricing-operations`
(`Index.vue:217-224`) with a Unit combobox for the two `PricingStrategy::CatalogFixed`
obligation types (`retake_fee`, `exam_resit_fee`). Staff pick a unit from a
searchable list instead of typing `{"unit_id": 123}` by hand. No backend
validation contract changes — `StorePricingRuleVersionRequest` already accepts
`facts_match_json` as a JSON string; the frontend just builds that string from
structured input instead of a free-text box.

## Requirements

- Functional: when `obligation_type` is `retake_fee` or `exam_resit_fee`, show
  an optional Unit combobox ("Áp dụng cho môn học cụ thể (để trống = mọi môn)")
  instead of the JSON textarea.
- Functional: selecting a unit builds `facts_match_json = '{"unit_id": <id>}'`
  client-side; leaving it empty submits `facts_match_json = null` (catch-all
  rule, current default behavior preserved).
- Functional: for any other obligation_type (non-catalog-fixed types, if the
  page ever lists them), keep current behavior unchanged — do not force the
  Unit picker where it doesn't apply.
- Non-functional: reuse the existing debounced-search combobox pattern
  (`StudentCombobox.vue`) rather than inventing a new one.

## Architecture

`StorePricingRuleVersionRequest` (`app/Modules/Finance/Http/Requests/Pricing/StorePricingRuleVersionRequest.php:38,48,55,63,69,90`)
already validates `facts_match_json` as an optional JSON-object string — no
backend change needed for this phase. Only two things change:
1. Frontend: swap the textarea for a `PricingUnitCombobox` component, serialize
   its selection into the same `facts_match_json` form field the backend
   already expects.
2. Backend: **decided via `/ak:plan validate` interview (plan.md Validation
   Log, Decision 4)** — do not depend on Academic's `units/search`
   (`can:view_unit` gate, unconfirmed for Finance staff). Add a Finance-owned
   proxy route in `app/Modules/Finance/routes/web.php` with its own
   permission gate, delegating to the same `UnitController::search()` logic
   (call the same query/service the Academic controller uses, don't
   duplicate the search logic itself — only the route/gate is Finance-owned).

Do not build a generic config-driven `facts_match` key/value editor — only
two obligation types use `CatalogFixed` today (`ObligationTypeRegistry.php:182,197`),
a dedicated Unit picker is smaller and matches YAGNI. If a third catalog-fixed
type needing a different fact key appears later, extend then.

## Related Code Files

- Read first: `resources/js/components/StudentCombobox.vue` — copy its
  debounced-search / Combobox-from-`@/components/ui/combobox` pattern.
- Create: `resources/js/components/finance/PricingUnitCombobox.vue`
  — Unit-flavored combobox, calls the new Finance-side unit search route,
  emits `unitId: number | null`.
- Modify: `resources/js/pages/Finance/PricingOperations/Index.vue`
  — replace `<Textarea>` around lines 217-225 with conditional render: show
  `PricingUnitCombobox` when `form.obligation_type` is `retake_fee` or
  `exam_resit_fee`; on selection change, set
  `form.facts_match_json = unitId ? JSON.stringify({ unit_id: unitId }) : null`.
  Keep `useForm` (Inertia) pattern already in use (line 83) — no new form lib.
- Check/modify: `app/Modules/Finance/Http/Web/Admin/PricingOperationsController.php`
  — no new prop needed; combobox hits its own search endpoint, page doesn't
  need a preloaded unit list.
- Create: Finance-side proxy route for unit search in
  `app/Modules/Finance/routes/web.php` (e.g. `finance/pricing-operations/units/search`),
  own permission gate (match this page's existing admin gate, not
  `can:view_unit`), delegating to the same unit-search query/service used by
  `app/Modules/Academic/Catalog/routes/web.php:89`'s `UnitController::search()`
  — read that controller method first to reuse its underlying query rather
  than reimplementing search logic.

## Implementation Steps

1. Read `StudentCombobox.vue` fully; read `Index.vue`'s full form section
   (state, submit handler) to match existing conventions exactly.
2. Read `UnitController::search()` (Academic) to identify the reusable
   query/service; add the Finance-side proxy route calling into it.
3. Build `PricingUnitCombobox.vue` following `StudentCombobox.vue`'s shape
   (debounce, min-chars, loading/empty states) but scoped to units, pointed
   at the new Finance proxy route.
4. Wire it into `Index.vue`: conditional render, `facts_match_json` synthesis,
   pre-fill combobox selection when editing/viewing an existing rule that has
   a `unit_id`-shaped `facts_match` (round-trip: parse existing JSON back into
   a unit selection on mount).
5. Manual browser check per repo UI rules: create a rule with a unit selected,
   confirm `finance_pricing_catalog_items.facts_match` stores
   `{"unit_id": N}`; create a catch-all rule with no unit, confirm
   `facts_match` is null; verify specificity ordering still prefers the
   unit-specific rule (existing backend behavior, `FinancePricingCatalog:164-212`,
   not touched).

## Success Criteria

- [ ] Staff can create a per-unit `exam_resit_fee`/`retake_fee` pricing rule
      without typing JSON
- [ ] Catch-all rule creation (no unit selected) still works identically to
      today
- [ ] Existing rules with hand-typed `facts_match` JSON still display/edit
      correctly (no round-trip data loss)
- [ ] Non-catalog-fixed obligation types (if present on this page) unaffected

## Risk Assessment

- **Resolved via interview:** proxy route decided upfront (plan.md
  Validation Log, Decision 4) — no longer a runtime risk to verify, it's the
  chosen approach.
- **Risk:** existing catalog rules already have hand-typed `facts_match` with
  keys other than `unit_id` (e.g., campus_id) if any were created ad hoc —
  the round-trip parse (step 4) must not crash on unrecognized shapes; fall
  back to showing raw JSON read-only for those, don't silently drop data.
- **Mitigation:** query `finance_pricing_catalog_items` for existing
  non-null `facts_match` rows before building the UI, to know what shapes
  must be tolerated.
