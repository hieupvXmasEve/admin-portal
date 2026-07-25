# Close the repository-wide Migration Debt inventory

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Perform the final evidence-driven closure after all tracer slices land. Re-run and classify the complete inventory, retire only approved dead compatibility paths, shrink temporary allowlists, verify data and public contracts, and make the repository's current-state documentation match the landed ownership model.

## Acceptance criteria

- [ ] Every original and newly discovered inventory item is classified as migrated, permanently retained with an owner, approved dead code, or explicitly blocked with evidence.
- [ ] No unexplained frozen-zone runtime path, shared Eloquent business-state dependency, compatibility adapter, deprecated frontend pattern, migration command, or architecture allowlist remains.
- [ ] No data-affecting action or compatibility removal occurred without its recorded approval checkpoint.
- [ ] Full backend, architecture, frontend, scheduler/queue, import/export, reporting, student portal, and lecturer portal gates pass or have an explicitly owned environmental blocker.
- [ ] Current-state architecture and glossary documentation describe the landed system without duplicating implementation history.

## Blocked by

- All issues 02 through 22 in this feature.
