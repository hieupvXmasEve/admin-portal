# Phase 06 - Tests, Observability, and Final Cutover

## Context Links

- Plan: `plans/260303-notification-module-domain-event-phase-1/plan.md`
- Rules: `.claude/rules/development-rules.md`

## Overview

- Priority: High
- Status: in-progress
- Objective: validate reliability/security with tests, add health metrics, and execute controlled cutover.

## Key Insights

- `recipient_unresolved` is expected edge case and must be visible via audit + alerts.
- Final cutover is operational decision, not only code deployment.

## Requirements

- Functional:
    - cover happy, failure, retry, and security paths
    - expose operational metrics and health checks
    - finalize cutover and rollback runbook
- Non-functional:
    - zero critical leak vulnerabilities in realtime auth
    - clear observability for queue/backlog/failure

## Architecture

- Metrics dimensions: `event_name`, `type_key`, `channel`, `status`, `campus_id`.
- Add unresolved recipient metric:
    - `notification_recipient_unresolved_total{event_name,campus_id,target_type}`
- Health command reports pending backlog, failed counts, retry lag.

## Related Code Files

- Create:
    - `tests/Feature/Modules/Notification/*`
    - `tests/Unit/Modules/Notification/*`
    - `app/Modules/Notification/Support/NotificationMetrics.php`
    - `app/Console/Commands/NotificationOutboxHealthCommand.php`
- Modify:
    - `routes/console.php`
    - docs in `docs/` if behavior/operations changed

### Ops Tests (COMPLETED 2026-03-04)

- Created:
    - `tests/Feature/Notification/NotificationOpsControllerTest.php` (controller endpoint tests)
    - `tests/Feature/Notification/RetryActionsTest.php` (retry action unit tests)
- Total: 38 tests passing covering ops list/show/retry flows

## Implementation Steps

1. Add tests for:
    - idempotency at all levels
    - strict campus isolation and exception allowlist
    - no duplicate send during retries
    - unresolved recipient skip/audit/metrics
2. Run targeted tests, then broader suite as needed.
3. Add health and alert thresholds for backlog/failure spikes.
4. Execute staged cutover (`legacy -> dual_compare -> v2`) and verify metrics.
5. Document rollback procedure and validation checklist.

## Todo List

### Tests (PARTIAL)

- [x] Write unit tests for mappers/resolvers/policy (`EventIntentMapperTest`, `PolicyResolverTest`)
- [x] Write feature tests for ops controller endpoints (`NotificationOpsControllerTest` - 38 tests)
- [x] Write unit tests for retry actions (`RetryActionsTest`)
- [ ] Write feature tests for outbox processing flows
- [ ] Write security tests for realtime channel auth

### Observability (PENDING)

- [ ] Add unresolved-recipient metric + alert
- [ ] Add outbox health command and schedule
- [ ] Run cutover and rollback simulation

## Success Criteria

- All targeted tests pass.
- No critical code-review findings.
- Operational metrics show stable delivery and acceptable failure rates.
- Cutover and rollback both verified in controlled rollout.

## Risk Assessment

- Risk: hidden production race conditions under load.
- Mitigation: canary by campus + alert-driven rollback thresholds.

## Security Considerations

- Redact sensitive content from logs and audit trails.
- Keep channel auth and campus checks enforced in every path.

## Next Steps

- Move legacy notification flow to deprecation phase after stability window.

## Unresolved Questions

- None.
