# Phase 04: Validation and Rollout

**Status:** in_progress  
**Effort:** 2h  
**Dependencies:** Phase 03

---

## Objective
Verify the new flow is correct, guard against legacy-data confusion, and document the few manual rollout steps this feature needs.

## Context Links
- [Voucher routes](/Users/hunt2412/hieupvdev/project/swinx/routes/web/vouchers.php)
- [Voucher detail UI](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Show.vue)
- [Permission config](/Users/hunt2412/hieupvdev/project/swinx/config/permission.php)
- [Legacy migration command](/Users/hunt2412/hieupvdev/project/swinx/app/Console/Commands/MigrateVoucherRedemptionsToApplications.php)

## Requirements
- Cover happy path and blocking cases with tests.
- Run local type/lint/test gates or record why not run.
- Document command-level rollout steps when permission/data sync is needed.

## Validation Matrix
- Happy path:
  - apply fixed-amount voucher from detail page
  - apply percentage voucher from detail page
  - apply informational voucher from detail page
- Guardrails:
  - duplicate same student + active semester + voucher rejected
  - expired/inactive voucher rejected
  - missing active semester rejected
  - voucher delete blocked when applications exist
- UI:
  - apply card hidden/disabled without permission
  - canonical history row appears after submit
  - mobile layout remains usable

## Related Code Files
- Create: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Voucher/VoucherDetailApplyTest.php`
- Optional create: `/Users/hunt2412/hieupvdev/project/swinx/tests/Unit/Finance/VoucherDiscountAmountResolverTest.php`
- Optional update: `/Users/hunt2412/hieupvdev/project/swinx/docs/codebase-summary.md`

## Implementation Steps
1. Add feature tests for the manual apply flow.
2. Add unit coverage for shared voucher amount resolver if extracted.
3. Run local checks:
   - `php artisan test`
   - `npm run type-check`
   - `npm run lint`
4. If permission key added:
   - run `php artisan permissions:sync`
5. If old redemptions must show in canonical history:
   - run `php artisan migrate:vouchers`
6. Record any compatibility caveats in summary/docs.

## Todo List
- [ ] Add feature tests for voucher detail apply flow.
- [ ] Add unit test for amount resolver if new class exists.
- [ ] Complete PHP + frontend quality gates in an environment with PHP available.
- [x] Keep existing `edit_voucher` permission; no permission sync needed for this change.
- [ ] Migrate legacy voucher data if environment still depends on it.

## Validation Notes
- `npm run type-check` was attempted and failed on many pre-existing repo-wide TypeScript issues outside the voucher scope; no voucher-page errors surfaced in that run.
- Targeted ESLint passed for `resources/js/pages/Vouchers/Show.vue` and `resources/js/pages/Vouchers/Index.vue`.
- PHP validation could not be completed in this environment because `php` is not installed on `PATH`, so `php artisan test` and `php -l` were not runnable.
- The current test scaffold is incomplete for new PHP tests (`tests/TestCase.php` is missing while `tests/Pest.php` references it), so automated coverage is still pending infrastructure repair.

## Success Criteria
- Tests prove new flow writes canonical application rows.
- Local gates pass or any gap is explicitly recorded.
- Operators do not lose visibility of existing voucher usage after rollout.
- Permission/data sync commands are documented, not tribal knowledge.

## Risk Assessment
- Risk: old environments still rely on `voucher_redemptions` visibility.
  - Mitigation: run migration command before rollout sign-off.
- Risk: permission added in config but not synced in DB.
  - Mitigation: include `permissions:sync` in rollout checklist.

## Security Considerations
- Test unauthorized access to apply route.
- Ensure feature tests cover campus-scoped student selection assumptions where relevant.

## Next Steps
- Handoff to `/ck:cook /Users/hunt2412/hieupvdev/project/swinx/plans/260324-1020-voucher-detail-student-apply/plan.md --auto`
