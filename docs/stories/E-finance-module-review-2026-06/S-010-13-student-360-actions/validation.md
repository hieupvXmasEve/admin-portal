# Validation

## Proof Strategy

Prove that the action UI renders by permission, uses the route/helper contracts,
and prevents unsafe destructive submit states before backend validation.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | None; no frontend unit runner exists. |
| Integration | Backend contracts covered by `FIN-REV-010-09`, `010-10`, and `010-11`. |
| E2E | Permission matrix smoke: actions hidden/visible/disabled by permission and state. |
| E2E | Record-payment drawer submits and closes on success. |
| E2E | Allocation preview loads candidates and applies through existing route. |
| E2E | DNG cancel drawer blocks missing void permission, blocking reasons, missing reason, and missing acknowledgement. |
| E2E | `focus=dng:<id>` highlights the intended card/section. |
| Platform | Desktop/mobile drawer layout does not overflow or hide controls. |
| Logs/Audit | Final invariant evidence belongs to `FIN-REV-010-14`. |

## Fixtures

- Student with unapplied payment id.
- Student with active pending/pushed DNG.
- DNG with linked charges.
- DNG with bridged payment/blocking reason.
- Users with incremental permission sets:
  `view_finance_student_overview`, `create_finance_payments`,
  `allocate_finance_payment`, `void_finance_charges`.

## Commands

```text
./scripts/dev.sh npm run lint -- resources/js/components/finance/student360/ resources/js/pages/Finance/Student360/Show.vue
```

## Acceptance Evidence

| Check | Result | Notes |
| --- | --- | --- |
| Targeted ESLint | PASS | `npm exec eslint` on `student360/` components + `Show.vue` + route helpers — 0 errors |
| Backend contracts | PASS (pre-existing) | Covered by S-010-09/10/11 feature tests |
| Browser smoke / permission matrix | Recorded in S-010-14 | Server contracts PASS; rendered UI manual |
| Platform drawer layout | Manual | Documented in umbrella M2 browser smoke |
