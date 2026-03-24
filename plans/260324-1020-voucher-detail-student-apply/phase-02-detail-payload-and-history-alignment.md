# Phase 02: Detail Payload and History Alignment

**Status:** completed  
**Effort:** 3h  
**Dependencies:** Phase 01

---

## Objective
Make voucher detail read the same canonical model that manual apply writes, so operators see accurate usage immediately after submit.

## Context Links
- [Voucher detail controller](/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/VoucherController.php)
- [Voucher detail page](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Show.vue)
- [Voucher application model](/Users/hunt2412/hieupvdev/project/swinx/app/Models/VoucherApplication.php)
- [Voucher definition model](/Users/hunt2412/hieupvdev/project/swinx/app/Models/VoucherDefinition.php)
- [Student search API](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Web/Admin/StudentController.php)

## Key Insights
- `Show.vue` currently renders `redemptions` only.
- `VoucherApplication` cannot yet provide invoice display because the model misses `invoice()` relation.
- Voucher index also counts `redemptions`, so list-level counts drift after canonical apply unless updated.

## Requirements
- Voucher detail must return:
  - voucher definition
  - current active semester summary
  - canonical usage/application history
  - capability flag for showing apply UI
- History rows should expose:
  - student id/name/email
  - semester code/name
  - applied amount
  - status
  - invoice number when consumed
  - applied timestamp
- Prefer a stable prop name like `applications` or `usageHistory`, not `redemptions`.

## Architecture
- Extend `VoucherApplication` relations:
  - `invoice()`
  - keep `student`, `semester`, `voucherDefinition`, `appliedBy`
- Controller `show()` loads canonical history ordered newest first.
- Controller also passes current active semester for the apply card.
- Voucher index/count logic should move to canonical count, or be renamed to neutral `usage_count`.

## Related Code Files
- Update: `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/VoucherController.php`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/app/Models/VoucherApplication.php`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/app/Models/VoucherDefinition.php`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Index.vue`
- Update: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Show.vue`

## Implementation Steps
1. Add missing relation(s) on `VoucherApplication`.
2. Replace voucher detail payload from legacy `redemptions` to canonical application history.
3. Pass `currentSemester` prop from controller:
   - active semester id/code/name
   - `null` if missing so UI can block apply
4. Update index query/count to avoid stale numbers after canonical apply.
5. Keep naming neutral in UI where possible:
   - `Usage History` or `Applications`
   - not `Redemption History` if source is no longer redemptions

## Todo List
- [x] Add `invoice()` relation to `VoucherApplication`.
- [x] Load canonical application history in voucher detail.
- [x] Expose current active semester in voucher detail props.
- [x] Update voucher list count to canonical usage count.
- [x] Rename page props/text away from legacy-only wording.

## Success Criteria
- Voucher detail reflects newly applied usage after redirect.
- Usage rows show semester and amount correctly.
- Voucher index count no longer ignores newly applied canonical usage.
- No controller/view code depends on legacy-only `redemptions` for new flow.

## Risk Assessment
- Risk: old migrated data does not appear until migration command runs.
  - Mitigation: rollout includes `migrate:vouchers` note.
- Risk: renaming props breaks current Vue page.
  - Mitigation: switch controller + page in same change window.

## Security Considerations
- Only expose history for users already allowed to view voucher detail.
- Do not return unnecessary student PII beyond current table needs.

## Next Steps
- Phase 03 builds the apply form and integrates the new payload into the page.
