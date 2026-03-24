# Phase 01: Canonical Redeem Contract and Guardrails

**Status:** completed  
**Effort:** 4h  
**Dependencies:** None

---

## Objective
Stop creating new legacy voucher usage rows. Make manual apply from voucher detail write to `voucher_applications` with correct validation, amount calculation, and permission coverage.

## Context Links
- [Voucher routes](/Users/hunt2412/hieupvdev/project/swinx/routes/web/vouchers.php)
- [Voucher controller](/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/VoucherController.php)
- [Voucher service](/Users/hunt2412/hieupvdev/project/swinx/app/Services/VoucherService.php)
- [Voucher application migration](/Users/hunt2412/hieupvdev/project/swinx/database/migrations/2026_01_19_133749_refactor_vouchers_table.php)
- [Finance batch generation](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php)
- [Finance preview query](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries/Operations/PreviewChargeGenerationQuery.php)

## Key Insights
- `voucher_applications` is the canonical input for current finance preview/generation.
- Current redeem flow writes `voucher_redemptions`, so new UI would still not affect billing.
- `%` voucher amount cannot stay ad-hoc inside one command; it must be shared.

## Requirements
- Keep manual apply backward-compatible with existing route usage where practical.
- Validate via FormRequest, not inline controller validation.
- Resolve current active semester server-side for this phase.
- Reject duplicate apply for same `student_id + semester_id + voucher_definition_id`.
- Honor `max_uses_per_student` when populated.
- Cap `%` voucher amount by `max_discount_amount` when populated.
- Keep `is_stackable` out of this phase; current codebase has no stable stacking semantics.

## Architecture
- Controller stays thin:
  - receives validated request
  - delegates to service
  - redirects back with flash
- Service becomes canonical manual-apply orchestrator:
  - load voucher + student
  - resolve active semester
  - validate business rules
  - compute amount
  - create `VoucherApplication` inside transaction
- Shared calculator/resolver lives in Finance support layer or another reusable location:
  - fixed amount -> direct value
  - percentage -> derive from semester billable base (same semester logic finance uses)
  - informational -> `0`

## Related Code Files
- Update: `/Users/hunt2412/hieupvdev/project/swinx/routes/web/vouchers.php`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/VoucherController.php`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/app/Services/VoucherService.php`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/app/Models/VoucherDefinition.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Requests/RedeemVoucherRequest.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Support/VoucherDiscountAmountResolver.php`
- Optional update: `/Users/hunt2412/hieupvdev/project/swinx/config/permission.php`

## Implementation Steps
1. Replace inline redeem validation with `RedeemVoucherRequest`.
2. Keep `vouchers.redeem` route name, but require explicit permission middleware:
   - preferred minimal option: reuse `edit_voucher`
   - cleaner option: add `assign_voucher` and sync permissions during rollout
3. Refactor service entrypoint from legacy `redeemVoucher()` semantics to canonical manual apply semantics:
   - accept voucher id/code + student id
   - resolve active semester
   - verify student belongs to current campus
   - check active/valid voucher
   - check per-student usage cap
   - create `VoucherApplication`
4. Extract voucher amount calculation into reusable resolver.
5. Update delete guard to check canonical applications, not only legacy redemptions.

## Todo List
- [x] Create FormRequest for redeem/apply payload.
- [x] Add explicit permission middleware to redeem route.
- [x] Refactor service to write `voucher_applications`.
- [x] Add reusable amount resolver for fixed/percentage/info vouchers.
- [x] Enforce duplicate + usage-cap validation.
- [x] Update delete guard to protect vouchers with canonical usage.

## Success Criteria
- New manual apply creates `voucher_applications`.
- No new `voucher_redemptions` rows are created by voucher detail flow.
- Validation errors are field-specific and redirect-friendly.
- Fixed and percentage vouchers persist correct `discount_amount`.
- Delete action is blocked when canonical applications exist.

## Risk Assessment
- Risk: duplicate logic between manual apply and finance generation.
  - Mitigation: shared resolver, no copy-paste math.
- Risk: no active semester configured.
  - Mitigation: fail fast with clear validation message.
- Risk: permission choice causes rollout friction.
  - Mitigation: keep minimal fallback to `edit_voucher` if role matrix must stay unchanged.

## Security Considerations
- Do not trust client-submitted student visibility; re-check campus scope server-side.
- Keep auth explicit with `can:*` middleware.
- Use transaction for application creation.
- Do not expose hidden voucher ids/codes as authority; always load canonical voucher server-side.

## Next Steps
- Phase 02 wires canonical history/count data back into voucher detail payload.
