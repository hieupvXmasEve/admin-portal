# Introduce Student Registry through the Finance Student overview

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Establish Student Registry ownership of stable Student Identity and expose a minimal Student Reference through the existing Finance Student search/overview flow. Finance staff must retain the same ability to find and identify a Student, while Finance stops reading identity, contact, and campus data directly from the Student God Model for this tracer surface.

## Acceptance criteria

- [x] Student Registry owns stable identifiers, display/contact data, campus affiliation, and account linkage used by the tracer.
- [x] The Student Reference contract exposes only correlation and display facts required by consumers, with no academic lifecycle or money state.
- [x] Finance Student search and overview resolve identity through the Registry contract and retain current campus scoping, authorization, filters, and results.
- [ ] Existing student-facing identity/API behavior remains compatible and student portal checks pass.
- [x] Registry and Finance behavior tests cover found, missing, unauthorized, and cross-campus Students.
- [x] Architecture tests prohibit new Finance reads of Registry-owned identity persistence outside approved adapters.
- [x] The transitional Student model remains in place for unmigrated consumers and is not physically moved in this issue.

## Blocked by

- [Issue 02: Own Institution Campus and Department through the Institution admin flow](02-own-campus-and-department-through-institution.md)

## Verification

- Passed: `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- Passed: focused Registry, Finance search, Student 360 authorization/campus, and architecture tests.
- Passed: `./scripts/dev.sh npm run type-check` and `./scripts/dev.sh npm run lint`.
- Passed with existing warnings: `cd FE/student-nuxt && pnpm typecheck && pnpm build`.
- Blocked: `cd FE/student-nuxt && pnpm lint` fails before linting because `eslint-plugin-pnpm` requires a missing `pnpm-workspace.yaml`.
- Known unrelated baseline failures: Student 360 review-signal cases require an absent `DngPaymentRequest::financeCharge` relationship; three Finance audit graph fixtures lack required currency data. Repository `format:check` also reports formatting across pre-existing untouched frontend files.

## Comments

- 2026-07-18: Added the Student Registry reference contract and Eloquent adapter. Finance search, Student 360, and its deferred ledger now resolve student identity/campus through the Registry boundary; lifecycle status and academic status use the existing Academic owner contract. Review found no remaining implementation or standards gaps. The issue is held for human follow-up because the required student portal lint is blocked by its workspace configuration.
