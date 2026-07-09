# 02 - Pricing Operations CRUD and coverage warning

Status: ready-for-human
Depends on: 01
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0027-obligation-type-registry-is-code-owned.md
Portal impact: none

## What to build

Build the minimal Pricing Operations staff workflow for Finance-owned pricing rules. Staff can view pricing rules by obligation type, create immutable rule versions, activate or deactivate versions, set effective dates, and provide raw `facts_match` JSON. The workflow must warn when a registry type that needs runtime pricing has no active rule so a missing pricing row is visible before an intake path fails in production.

## Acceptance criteria

- [x] Staff can list, create, activate, and deactivate pricing rule versions without editing historical versions in place.
- [x] The pricing catalog continues to choose the active effective rule and stamps the selected rule version into accepted obligations.
- [x] A coverage warning is shown when a registry-priced type has no active pricing rule.
- [x] A seed or parity command can load baseline pricing rows for local and production parity without duplicating registry behavior facts.
- [x] Feature tests cover the pricing rule lifecycle, coverage warning, and intake failure prevention path.

## Blocked by

- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/01-code-owned-obligation-type-registry.md`
