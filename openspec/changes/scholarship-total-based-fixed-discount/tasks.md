## 1. Data Model

- [x] 1.1 Add a migration for nullable `total_amount` and `total_terms` columns on `scholarship_definitions`.
- [x] 1.2 Update `ScholarshipDefinition` fillable and casts for the new persisted calculation fields.

## 2. Backend Calculation and Validation

- [x] 2.1 Add a small backend helper for fixed scholarship calculation using `ceil((total_amount / total_terms) / 1000) * 1000`.
- [x] 2.2 Update `ScholarshipRequest` validation so fixed-amount scholarships require positive `total_amount` and `total_terms`.
- [x] 2.3 Normalize fixed-amount scholarship payloads so backend-calculated `amount` overrides any submitted stale amount.
- [x] 2.4 Normalize percentage scholarship payloads so `total_amount` and `total_terms` are stored as `null`.
- [x] 2.5 Add focused backend tests for create/update calculation, rounding, required fixed fields, stale amount override, and percentage field clearing.

## 3. Frontend Create/Edit Forms

- [x] 3.1 Update scholarship form schema/types to include `total_amount` and `total_terms`.
- [x] 3.2 Update `Scholarships/Create.vue` to show total amount and total terms when type is fixed amount and preview the calculated discount amount.
- [x] 3.3 Update `Scholarships/Edit.vue` to initialize, show, update, and preview the persisted calculation fields.
- [x] 3.4 Ensure switching to percentage hides fixed calculation fields and submits empty total calculation values.

## 4. Scholarship Display

- [x] 4.1 Update scholarship TypeScript interfaces on list/detail/assignment surfaces that receive scholarship definitions.
- [x] 4.2 Update `Scholarships/Show.vue` to display total amount and total terms for fixed scholarships with saved calculation inputs.
- [x] 4.3 Update `Scholarships/Index.vue` amount display to include compact calculation context for fixed scholarships with saved calculation inputs.

## 5. Verification

- [x] 5.1 Run the targeted backend test file for scholarship create/update behavior.
- [x] 5.2 Run `./scripts/dev.sh npm run type-check`.
- [x] 5.3 Run any formatter/lint command required by touched files or document why it could not be run.
