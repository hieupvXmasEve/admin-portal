# Exec Plan

## Goal

Standardize Finance UI foundations and repair transaction lifecycle navigation
without mixing in the BOD dashboard.

## Scope

In scope:

- `UI-LC-1` through `UI-LC-7`.
- `UI-STD-1` through `UI-STD-7` as touched page groups are refactored.
- `UI-SAFE-1`, `UI-SAFE-2`, `UI-SAFE-4`, `UI-SAFE-5` if not completed in story
  001.
- `UI-MON-*`, `UI-CON-*`, and Vietnamese terminology from C8.1.

Out of scope:

- BOD oversight page.
- Finance role/permission model decisions beyond existing page gates.
- Backend money calculation rewrites.

## Risk Classification

Risk flags:

- Authorization.
- Public contracts.
- Existing behavior.
- Weak proof.

Hard gates:

- Permission-gated finance actions.
- Money-affecting UI actions.

Portal impact:

- None.

## Work Phases

1. Discovery: map Finance pages by page group and identify required ids for
   lifecycle links.
2. Build or standardize shared finance formatting/status/confirm helpers.
3. Refactor one page group at a time, starting with Charges/Invoices/Payments.
4. Replace temporary manual allocation with a safe selector + confirm or a
   disabled state linked to the follow-up story.
5. Replace raw `fetch`, `window.confirm`, verified route/url drift, and
   duplicate formatters on touched pages. Do not repeat corrected findings such
   as claiming `ExceptionsQueue` uses a literal URL when it already uses
   `route(...)`.
6. Add lifecycle links charge/invoice/payment/DNG/settlement.
7. Translate touched Finance text to approved Vietnamese terms.
8. Run frontend checks and browser smoke.

## Stop Conditions

Pause for human confirmation if:

- Staff role permissions in production are unclear and affect visibility.
- UI-ROLE-1 remains unresolved; do not claim admin/staff role parity is fixed
  until production role assignment has been audited.
- A lifecycle link requires backend data not safely exposed yet.
- A page rewrite becomes larger than one reviewable PR.
- Type-check baseline blocks proving touched files and cannot be isolated.
