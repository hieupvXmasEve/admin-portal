# Design

## Domain Model

Operations read models must represent real Finance state. Stub actions are not
valid operational outcomes.

## Application Flow

- Replace `FixBillingExceptionAction` with a real action or return a deliberate
  unsupported response and hide/disable the UI call.
- Replace `ListBillingExceptionsQuery` empty stub with the intended query or
  remove the page affordance.
- Align due calendar summary/list date windows.
- Push pagination and aggregation into SQL instead of loading full tables.
- Rewrite correlated `whereHas` / `whereDoesntHave` lifecycle predicates to a
  join or add a proven supporting index when query proof requires it.
- Apply allocation priority ordering where the parameter is accepted.
- Rename or relabel "Due Calendar" if product accepts the correction, or add
  clear IA text so it is not mistaken for a true calendar.

## Interface Contract

Existing web routes may stay, but response semantics must stop reporting fake
success. Inertia v3 flash should be used for web redirects where applicable.

## Data Model

No required migrations unless query performance needs a non-redundant composite
index from the review.

## UI / Platform Impact

Finance Operations pages should show accurate empty/error/success states.
Dangerous or unsupported actions should not appear as successful.

## Observability

Record query counts or explain plans for rewritten heavy queries where useful.

## Alternatives Considered

1. Leave stubs until a full redesign.
   - Rejected because the UI currently gives false success.
2. Hide all operation pages.
   - Rejected because many pages are valid and only need targeted fixes.
