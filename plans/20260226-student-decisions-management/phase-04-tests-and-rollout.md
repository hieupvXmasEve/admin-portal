# Phase 04 - Tests and Rollout

## Context links
- [Academic routes](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/routes/web.php)
- [Current test baseline](/Users/hunt2412/hieupvdev/project/swinx/tests/Unit/Finance/DeferChargePolicyTest.php)

## Overview (date/priority/status)
- Date: 2026-02-26
- Priority: P1
- Status: pending (0%)

## Key Insights
- Highest risk here is broken deep links and incorrect nullable-link handling.
- Need coverage for linked counts and file link rendering.

## Requirements
- Feature tests for decision registry routes and permissions.
- Validation tests for action write with nullable `decision_id`.
- UI sanity checks for deep-link and new-tab file view behavior.

## Architecture
- Keep tests focused: one registry flow test, one nullable-link validation test, one link generation test.

## Related code files
- Create: [tests/Feature/Academic/StudentDecisionRegistryTest.php](/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Academic/StudentDecisionRegistryTest.php)
- Create/Update: [tests/Feature/Academic/StudentActionDecisionLinkingTest.php](/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Academic/StudentActionDecisionLinkingTest.php)

## Implementation Steps
1. Add feature test for decisions index access and decision create/update.
2. Add feature test for decision detail linked rows and action detail link format.
3. Add validation test:
   - action type with `decision_id = null` passes
   - invalid `decision_id` fails
4. Run checks:
   - `php artisan test --filter=StudentDecision`
   - `npm run type-check`
5. Manual QA:
   - open decision file in new tab
   - open linked action detail route

## Todo list
- [ ] Registry tests added.
- [ ] Nullable decision-link tests added.
- [ ] Targeted checks executed.
- [ ] Manual deep-link/file-view QA completed.

## Success Criteria
- Nullable decision link behavior works ổn định.
- Linked list navigation stable.
- No regression at Student Status report menus/routes.

## Risk Assessment
- Risk: users quên gán quyết định dù nghiệp vụ cần.
- Mitigation: hiển thị badge/cột “No decision linked” để hỗ trợ vận hành.

## Security Considerations
- Verify only authorized users can view decision file URL in UI context.
- Verify permission middleware blocks unauthorized route access.

## Next steps
- Handoff implementation with nullable manual-link flow.

## Unresolved questions
None.
