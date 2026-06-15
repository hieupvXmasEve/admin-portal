# Exec Plan

## Goal

Create a shared Finance lifecycle exception history page that shows searchable
append-only action history across lifecycle exception DNG payment requests,
reachable independently from Finance Operations and deep-linked from
`/finance/operations/lifecycle-exceptions`.

## Scope

In scope:

- New standalone history route and Inertia page for shared lifecycle exception
  action history.
- Finance Operations menu entry for history access even after rows leave the
  active exception queue.
- `View history` action from the lifecycle exceptions table and detail drawer
  that opens the shared history page with a DNG request or item filter applied.
- Append-only lifecycle exception history event model/table.
- Consistent acknowledge metadata across both lifecycle exception action paths.
- Correct attempt-vs-state-transition handling for DNG cancellation audit.
- Query/read model for paginated history events with DNG request summary,
  student summary, current review, and filter options.
- Updates to acknowledge/resolve actions so every staff or destructive action
  appends a history event.
- Authorization and campus-scope checks consistent with lifecycle exception
  review access.

Out of scope:

- Student portal or lecturer portal changes.
- Bulk history export.
- Replacing DNG payment request audit pages.
- New DNG provider behavior.
- Automatic write-off, refund, or settlement policy.
- Reworking the whole lifecycle exception list page beyond adding the history
  entry action.
- Making history reachable only through active lifecycle exception rows.

## Risk Classification

Risk flags:

- Authorization.
- Data model.
- Audit/security.
- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Audit-sensitive Finance staff decisions.
- Permission-gated Finance operations page.
- Destructive DNG cancellation and charge voiding must remain traceable.
- History must not be lost when current review state changes.

Portal impact:

- None.

## Work Phases

1. Discovery.
   - Confirm the current S-001 table/action implementation and exact action
     names.
   - Confirm permissions used by lifecycle exception list and DNG request
     detail pages.
   - Confirm whether campus scoping is already part of lifecycle exception
     queries.
2. Data model.
   - Add append-only history event migration and model.
   - Add enum/value object for event type if local patterns support it.
   - Add indexes for DNG request timeline and actor/time lookup.
3. Write path.
   - Update acknowledge and resolve actions to append events.
   - Record attempted and final events around destructive DNG/charge mutations.
   - Ensure attempted DNG cancel events cannot masquerade as committed review
     state transitions when the mutation transaction rolls back.
   - Preserve current review state as the latest queue state.
4. Read path.
   - Add shared history query and controller index action.
   - Add standalone route with named route helper.
   - Add filters for DNG request, DNG item id, student, actor, event type,
     review status, exception reason, and performed date range.
   - Ensure history can be searched after the DNG request is resolved or
     cancelled and no longer appears in the active exception queue.
5. UI.
   - Add `View history` action to lifecycle exceptions table and detail drawer.
   - Add Finance Operations menu entry for the standalone history page.
   - Add `LifecycleExceptionHistory.vue` with filters, paginated event table,
     row expansion or side panel, links, empty state, legacy state, and failure
     metadata display.
6. Verification.
   - Add feature/unit/frontend checks listed in `validation.md`.
   - Run targeted backend tests, frontend lint/format/type checks, and browser
     smoke for the new page.
7. Harness update.
   - Record trace, acceptance evidence, and backlog items for any follow-up
     audit/export needs.

## Stop Conditions

Pause for human confirmation if:

- Product wants to treat the latest review row as sufficient history.
- Product wants history to be accessible only from the active exception row.
- History is expected to include DNG provider raw payloads rather than workflow
  summaries.
- Permission requirements differ from lifecycle exception page access.
- Campus scoping cannot be proven from existing Finance operations patterns.
- A destructive action can complete without a durable history event.
- Validation requirements need to be weakened because fixtures are hard to
  construct.
