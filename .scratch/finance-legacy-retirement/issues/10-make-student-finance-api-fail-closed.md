# 10 — Make Student Finance API fail closed

Status: completed

Portal impact: student

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Make student-facing charge, invoice, and fee-summary reads use one canonical Finance query/presentation boundary. Invalid Settlement Positions must never be coerced into zero or presented as paid, and request validation must remain outside the controller orchestration layer.

Keep the student API contract and the student portal implementation aligned. If nullable amounts or review metadata are part of the supported contract, document and render those states explicitly rather than inventing a monetary answer.

## Acceptance criteria

- [x] Invalid or unavailable Settlement Positions return a stable fail-closed representation and are never reported as `0`, paid, or otherwise settled through numeric coercion.
- [x] Charge, invoice, and fee-summary endpoints use the same canonical mapping for gross, discount, cash, credit, remaining collectible, status, and review evidence.
- [x] Cached invoice status cannot override an invalid canonical Settlement Position in a student-facing response.
- [x] Changed student endpoint validation uses dedicated Form Requests; controllers only validate through those requests, call the Finance query, and return the response.
- [x] The canonical student Finance query exposes the standard `handle()` entry point and does not require controllers to assemble business state.
- [x] Student API documentation and the matching student portal types, stores, composables, pages, and empty/error states are inspected and updated together where the contract requires it.
- [x] Contract and feature tests cover valid partial payment, credit, paid, void, invalid, and unavailable positions without changing correct monetary totals.

## Blocked by

- [Issue 09 — Restore a parseable Finance baseline](09-restore-parseable-finance-baseline.md)

## Verification

- Added `GetStudentFinancePresentationQuery` as the single current-position presentation boundary for student balance, overview, charge, and invoice reads. Invalid positions expose null monetary fields, `invalid` status, and stable review evidence; cached invoice statuses are considered only after the canonical position is valid.
- Added dedicated student Finance Form Requests for balance, charge list, invoice list, and overview filters. The affected controller methods now only obtain the authenticated student, call the query, and return the API envelope.
- Updated `docs/api/student/finance.md` and the matching student portal Finance types/page. The portal now renders the unavailable message instead of formatting nullable charge or invoice money as zero, including invoice detail lines.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/StudentFinancePresentationTest.php tests/Feature/Finance/Student360/StudentFinanceSettlementSummaryTest.php` passed: 8 tests, 85 assertions. Coverage includes partial cash/credit, paid, void, invalid, unavailable, and request validation.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed. Student portal `pnpm typecheck` and `pnpm build` passed with pre-existing Nuxt warnings. Portal `pnpm lint` remains blocked before linting by the existing missing `pnpm-workspace.yaml` ESLint configuration error. `./scripts/dev.sh test` still exits 255 with no output, matching the known repository baseline gap.

## Comments

- 2026-07-15: Completed the student Finance fail-closed cutover and portal contract alignment.
