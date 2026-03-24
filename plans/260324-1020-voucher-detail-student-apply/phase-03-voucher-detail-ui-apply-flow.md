# Phase 03: Voucher Detail UI Apply Flow

**Status:** completed  
**Effort:** 3h  
**Dependencies:** Phase 02

---

## Objective
Add an operator-friendly apply flow directly into voucher detail, consistent with current card/table/shadcn-style UI already used in voucher screens.

## Context Links
- [Voucher detail UI](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Show.vue)
- [Student combobox](/Users/hunt2412/hieupvdev/project/swinx/resources/js/components/StudentCombobox.vue)
- [Voucher create/edit UI pattern](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Create.vue)
- [Design guidelines](/Users/hunt2412/hieupvdev/project/swinx/docs/design-guidelines.md)

## Key Insights
- Voucher detail already uses `Card`, `Alert`, `Badge`, `Table`, `useForm`.
- `StudentCombobox` already handles current-campus search and inline error states.
- The page already has a clear “details + history” structure; adding one more detail card is lower-risk than a dialog-first flow.

## Requirements
- Form must live on voucher detail, not a separate page.
- Form fields stay minimal:
  - selected student
  - current active semester display
  - optional informational summary of discount behavior
- Disable submit when:
  - voucher expired/inactive
  - no active semester
  - no student selected
- Show flash/toast/inline errors using existing app patterns.
- Preserve current responsive layout.

## Architecture
- Add one new `Apply Voucher` card near the existing voucher detail cards.
- Use Inertia `useForm`.
- Reuse `StudentCombobox` for student search.
- Keep history table below, but update columns for canonical usage:
  - Student
  - Semester
  - Amount
  - Status
  - Invoice
  - Applied At

## Related Code Files
- Update: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/Show.vue`
- Optional create if page grows too large: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Vouchers/components/ApplyVoucherCard.vue`
- Reuse: `/Users/hunt2412/hieupvdev/project/swinx/resources/js/components/StudentCombobox.vue`

## Implementation Steps
1. Add new props to page:
   - `currentSemester`
   - `applications` / `usageHistory`
   - `canApplyVoucher`
2. Build `Apply Voucher` card:
   - student selector
   - active semester badge/text
   - helper copy for discount voucher type
   - submit button
3. Post to `vouchers.redeem` with selected student and voucher identifier.
4. Replace legacy history section text/table with canonical usage history.
5. Keep destructive alert for expired voucher; also use it to disable apply action.

## UI Notes
- Prefer inline card, not modal:
  - fewer clicks
  - keeps voucher context visible
  - matches current page composition
- Show amount preview text carefully:
  - fixed amount: direct formatted currency
  - percentage: show `%` with note that applied amount is computed for active semester
  - informational: show “tracks usage, no discount amount”

## Todo List
- [x] Add apply card to voucher detail.
- [x] Reuse `StudentCombobox`.
- [x] Wire Inertia form submission.
- [x] Update history table to canonical columns.
- [x] Handle disabled/empty/error states explicitly.
- [x] Check mobile layout for stacked cards + table overflow.

## Success Criteria
- Operator can apply voucher without leaving detail page.
- Student selection is searchable and campus-scoped.
- Disabled states prevent invalid submissions.
- History updates reflect canonical applications.
- UI stays visually consistent with existing voucher pages.

## Risk Assessment
- Risk: `Show.vue` becomes too large/noisy.
  - Mitigation: extract `ApplyVoucherCard.vue` only if page readability drops.
- Risk: percentage vouchers confuse users because final amount is computed.
  - Mitigation: explicit helper text on the card.

## Security Considerations
- Never expose apply action button when user lacks permission.
- Do not allow free-text student ids if combobox already resolves canonical student ids.

## Next Steps
- Phase 04 validates the flow end-to-end and documents rollout steps for permissions + legacy data.
