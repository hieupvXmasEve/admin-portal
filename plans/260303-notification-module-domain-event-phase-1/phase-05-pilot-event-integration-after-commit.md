# Phase 05 - Pilot Event Integration After Commit

## Context Links

- Finance service candidate: `app/Modules/Finance/Services/PaymentService.php`
- Legacy listener map: `app/Providers/EventServiceProvider.php`

## Overview

- Priority: High
- Status: in-progress
- Objective: integrate 1-2 pilot business events into new notification module using after-commit emission.

## Key Insights

- Existing notification writes are scattered and sometimes inside transactions.
- Pilot should be narrow to reduce blast radius and simplify rollback.

## Requirements

- Functional:
    - emit domain events only after transaction commit
    - process pilot events through new outbox module
    - prevent duplicate sending from legacy + v2 during pilot
- Non-functional:
    - flag-driven canary rollout
    - rollback in one config flip

## Architecture

- Producers write envelope to outbox in transaction.
- Dispatcher projects to messages/deliveries.
- Legacy notification path guarded by event-level flag for pilot types.

## Related Code Files

- Create:
    - `app/Modules/Notification/Domain/Events/AcademicEnrollmentConfirmedEvent.php`
    - `app/Modules/Notification/Domain/Events/FinanceInvoicePaidEvent.php`
    - `app/Modules/Notification/Handlers/EnrollmentConfirmedHandler.php`
    - `app/Modules/Notification/Handlers/InvoicePaidHandler.php`
- Modify:
    - Academic producer action(s) that confirm enrollment
    - Finance producer action(s) that represent paid invoice/payment success
    - `app/Providers/EventServiceProvider.php` (guard legacy listeners as needed)

## Implementation Steps

1. Pick exact producer points for `academic.enrollment_confirmed` and `finance.invoice_paid`.
2. Add after-commit event publication to outbox.
3. Add intent handlers and template mapping for both events.
4. Add feature flags for pilot events and campus subsets.
5. Add guardrails to avoid double send from legacy pipeline.

## Todo List

- [ ] Identify and wire exact producer actions
- [ ] Emit outbox events after commit only
- [ ] Implement pilot handlers and templates
- [ ] Add pilot event/campus feature flags
- [ ] Add duplicate-send prevention checks

## Success Criteria

- Pilot events create correct v2 messages/deliveries.
- No duplicate notifications during dual mode.
- Rollback to legacy mode requires config flip only.

## Risk Assessment

- Risk: wrong producer location emits premature or duplicate events.
- Mitigation: transactional tests around commit/rollback behavior.

## Security Considerations

- Derive `campus_id` from aggregate data, not request payload.
- Ensure system/global exception allowlist is explicit and auditable.

## Next Steps

- Continue to Phase 06 full validation and cutover readiness.

## Unresolved Questions

- None.
