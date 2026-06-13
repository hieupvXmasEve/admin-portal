# Exec Plan

## Goal

Create a dedicated Finance lifecycle exception review page for overdue/open DNG
items tied to students outside normal billable lifecycle status, while keeping
Due Calendar focused on active reminder operations.

## Scope

In scope:

- New Finance operations page for lifecycle due exceptions.
- Shared lifecycle predicate so Due Calendar list, summary, export, and reminder
  sends agree on which rows are normal active collection.
- Query/read model for DNG requests excluded by lifecycle status.
- Review state/audit record for acknowledgement, keep-as-debt, routing, and
  resolution.
- Optional destructive resolution actions only after impact preview, permission
  checks, current-state checks, and reason capture.
- Menu/sidebar entry with Finance operations permission gating.

Out of scope:

- Student portal or lecturer portal changes.
- Provider protocol changes for DNG.
- Automatic bulk cancellation or bulk voiding by status.
- Refunds.
- Replacing the existing DNG payment request audit page.
- Replacing the full settlement worklist.

## Risk Classification

Risk flags:

- Authorization.
- Data model.
- Audit/security.
- External systems.
- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- DNG provider cancellation behavior.
- Possible finance charge voiding/data loss.
- Audit-sensitive debt/offboarding decisions.
- Permission-gated finance operations.

Portal impact:

- None.

## Work Phases

1. Discovery.
   - Confirm the exact DNG statuses considered open for review.
   - Confirm whether `Student::FINANCIAL_STATUSES` is the final active
     collection predicate or whether additional statuses should be included.
   - Confirm existing finance permissions and whether a new view permission is
     needed.
   - Count current local/production candidates by student status before changing
     behavior.
2. Read model and Due Calendar guard.
   - Extract shared active/lifecycle exception predicate.
   - Update Due Calendar list and summary queries to exclude exceptions.
   - Update reminder actions to skip crafted exception item ids.
3. Exception page.
   - Add list and summary queries.
   - Add web route/controller and Inertia page.
   - Add sidebar entry and route helper usage.
4. Review persistence.
   - Add review table/model if persistent acknowledge/keep/routed statuses are
     required.
   - Add actions for acknowledgement and non-destructive resolution.
5. Destructive resolution.
   - Add impact preview and hard permission checks.
   - Reuse existing DNG cancellation and finance void actions.
   - Require staff reason and store audit metadata.
6. Verification.
   - Run targeted Finance operation tests, DNG cancellation regressions, Pint,
     frontend lint/format/type checks, and browser smoke for the new page.
7. Harness update.
   - Record trace, acceptance evidence, and any ADR if a new debt/offboarding
     policy or permission model is accepted.

## Stop Conditions

Pause for human confirmation if:

- Staff want to auto-cancel all deferred/dropout DNG requests.
- `deferred` should still receive generic reminders for any fee type.
- Dropout/offboarding debt policy is not explicit enough to choose a resolution
  action.
- A DNG request is already paid, bridged, reconciled, or has late webhook risk.
- Charge voiding would affect multiple invoice lines, installments, retake
  registrations, or historical payments.
- Permission requirements cannot be expressed with existing Finance permissions.
- Validation requirements need to be weakened because fixtures are hard to
  construct.
