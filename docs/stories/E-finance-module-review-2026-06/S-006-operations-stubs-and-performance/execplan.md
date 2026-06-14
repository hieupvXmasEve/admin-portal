# Exec Plan

## Goal

Make Finance Operations pages truthful and scalable enough for normal staff use.

## Scope

In scope:

- `FIN-20`, `FIN-21`, `FIN-22`, `FIN-26`, `FIN-27`, `FIN-28`, `FIN-29`.
- Query rewrites listed in `DB-22`, plus `DB-23` where lifecycle predicates
  cause correlated-subquery performance risk.
- `UI-IA-1` Due Calendar naming/IA correction.
- Non-redundant indexes only when needed after query proof.

Out of scope:

- Lifecycle exception story implementation if already tracked separately.
- BOD oversight charts.
- Ledger source-of-truth refactor except as a dependency.

## Risk Classification

Risk flags:

- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Staff-facing money operations.
- Fake-success action removal.

Portal impact:

- None.

## Work Phases

1. Discovery: map the wired routes, UI callers, query classes, and current data
   volume for each operations page.
2. Decide per stub: implement real behavior or return unsupported and remove UI
   action.
3. Fix due calendar summary/list window mismatch.
4. Rewrite `ListDueItemsQuery` and other heavy queries to paginate/aggregate in
   SQL.
5. Rewrite or index lifecycle `whereHas` predicates where query proof shows
   correlated-subquery risk.
6. Align dashboard counts with canonical settlement math.
7. Rename/relabel Due Calendar as a DNG due reminder queue if product accepts
   the terminology change.
8. Make allocation priority input affect ordering or remove it from the
   contract.
9. Add targeted tests for each fixed operation/query.
10. Run frontend checks for touched pages.

## Stop Conditions

Pause for human confirmation if:

- "Fix exception" requires a business policy not documented yet.
- Query rewrite depends on story 002 canonical balance work.
- Removing a fake-success action would disrupt an active staff workflow.
- A performance fix requires a migration broader than this story.
- Product wants to keep the "Due Calendar" label despite the page being a list.
