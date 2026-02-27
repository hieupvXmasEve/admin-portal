# Phase 03 - Frontend Page and Menu

## Context links
- [StudentLifecycleYearlyAnalysis page pattern](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Admin/Reports/StudentLifecycleYearlyAnalysis/Index.vue)
- [StudentActionsAudit page](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Admin/Reports/StudentActionsAudit.vue)
- [Student route helpers](/Users/hunt2412/hieupvdev/project/swinx/resources/js/utils/routes.ts)
- [Sidebar config](/Users/hunt2412/hieupvdev/project/swinx/resources/js/constants/menu-sidebar.ts)

## Overview (date/priority/status)
- Date: 2026-02-26
- Priority: P1
- Status: completed (100%)

## Key Insights
- Existing report layout can be reused with minimal UX drift.
- Deep link to action detail already exists via route helper `studentStatusActionShow(id)`.
- File link should use `target="_blank" rel="noopener noreferrer"`.

## Requirements
- Build registry page for decision list + create/edit.
- Show linked action/student count per decision.
- Provide decision detail panel/table listing linked student actions.
- Each linked row has “View Action” link to `reports/student-actions/{id}`.
- Decision file can be opened in new tab.
- Add menu item under `Student Status`, below `Lifecycle Yearly Analysis`.

## Architecture
- Main page: `Admin/Reports/StudentDecisions/Index.vue`.
- Optional detail route/page if data is heavy: `Show.vue`; else modal drawer in same page.
- Reuse existing `FileUpload` for uploading/storing `upload_record_id`.

## Related code files
- Create: [resources/js/pages/Admin/Reports/StudentDecisions/Index.vue](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Admin/Reports/StudentDecisions/Index.vue)
- Optional create: [resources/js/pages/Admin/Reports/StudentDecisions/Show.vue](/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Admin/Reports/StudentDecisions/Show.vue)
- Update: [resources/js/utils/routes.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/utils/routes.ts)
- Update: [resources/js/constants/menu-sidebar.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/constants/menu-sidebar.ts)
- Optional create: [resources/js/types/student-decision.ts](/Users/hunt2412/hieupvdev/project/swinx/resources/js/types/student-decision.ts)

## Implementation Steps
1. Add route helpers for decisions index/store/update/show.
2. Build list table columns:
   - decision_name, decision_number, signer, issued_at, expires_at
   - linked_actions_count, linked_students_count
   - file open button (new tab)
3. Add create/edit form with required fields + optional file.
4. Build linked rows section with columns:
   - student code/name
   - action type
   - action timestamp
   - “View Action” link to detail route
5. Insert sidebar menu item directly after `Lifecycle Yearly Analysis`.

## Todo list
- [x] Route helpers added.
- [x] Registry list/form page complete.
- [x] Linked student/action view complete.
- [x] New-tab file view works.
- [x] Menu position correct.

## Success Criteria
- User can inspect decision usage across students/actions.
- User can jump to exact action detail from linked row.
- Decision file opens in new tab from list/detail.

## Risk Assessment
- Risk: too many linked rows slow down detail view.
- Mitigation: paginate linked rows and keep default page size small.

## Security Considerations
- Escape and validate all URLs from backend payload.
- Keep permissions consistent with reports module.

## Next steps
- Add feature tests and targeted regression checks.

## Unresolved questions
None.
