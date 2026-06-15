# Exec Plan

## Goal

Complete the integrated validation and evidence pass for Student 360 Milestone 2.

## Scope

In scope:

- Run full targeted M2 backend tests.
- Re-run M1 regression tests.
- Run frontend build and targeted lint.
- Smoke the permission matrix and focus behavior.
- Run finance invariant checks before/after write-path smoke.
- Update `S-010-finance-staff-workspace/validation.md`.
- Record Harness trace for M2.

Out of scope:

- Product implementation changes, unless validation exposes a blocking defect
  that must be fixed before evidence can be truthful.
- New backend routes or frontend components.
- Portal repo changes.

## Risk Classification

Risk flags:

- Authorization: permission matrix proof.
- Data loss: DNG cancel can void linked charges.
- Audit/security: invariant and evidence capture.
- Existing behavior: M1 regression checks.
- Public contract: route and prop matrix verification.
- Weak proof: manual browser smoke must be recorded explicitly.

Hard gates:

- Do not claim a command passed unless it was run.
- Do not mark M2 done without recording invariant evidence or an explicit
  blocker.
- Do not hide browser-smoke gaps behind automated-test evidence.
- If validation fails, record the failure and route the fix to the owning child
  story.

## Work Phases

1. Run the backend M2 suite.
2. Re-run the M1 Student 360/search/shell regression suite.
3. Run frontend build and targeted lint.
4. Smoke the permission matrix:
   - overview-only user,
   - `create_finance_payments`,
   - `allocate_finance_payment`,
   - linked-charge DNG without `void_finance_charges`,
   - bridged-payment DNG blocking reason,
   - `focus=dng:<id>`.
5. Run finance invariant checks before and after exercising money state changes.
6. Update umbrella validation evidence.
7. Add Harness trace and story matrix evidence.

## Stop Conditions

Pause if:

- Any write-path validation creates new invariant violations.
- Permission matrix behavior is clickable-but-forbidden instead of hidden or
  disabled with reason.
- Browser smoke cannot reach a representative Student 360 fixture.
- The invariant command name differs and cannot be located.
