# Current Story Pack: C1 — EmailContentProvider Contract

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** C · **Wave:** 2 (CRITICAL PATH)
**Mode:** high-risk (parent) · **Story risk:** LOW (interface extraction)
**Depends on:** S2 ✅ (`spike-S2-emailservice-callers.md`) — confirmed 6 Finance Actions need this Contract before M3.

## Entry State

- `app/Modules/Notification/EmailContent/EmailContentRegistry.php` exists with concrete `resolve(string $type): EmailContentProvider` shape.
- Existing `EmailContentProvider` interface at `app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php` (verify line).
- Existing `DbEmailContentProvider` and 4 type-class implementations.
- No `app/Shared/Contracts/Notification/` directory yet (verify).
- `CLAUDE.md` Forbidden Patterns rule: cross-module reads must use a Contract under `app/Shared/Contracts/{Domain}/`.

## Exit State

- New `app/Shared/Contracts/Notification/EmailContentProvider.php` Contract (interface).
- Old `app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php` re-exports the Shared Contract OR is deleted with all implementations updated.
- `EmailContentRegistry` returns instances satisfying the Shared Contract.
- Finance Actions (`SendParentPaymentRemindersAction`, `SendPaymentRemindersAction`, `SendDueItemRemindersAction`, `SendDueItemParentRemindersAction`) type-hint against the Shared Contract — they may STILL call `app(EmailService::class)->sendSingleEmail` in this story (M3 migrates the call shape; C1 only fixes the Contract layer).
- `./scripts/dev.sh test tests/Unit/Notification` + `tests/Feature/Notification` green.
- `./scripts/dev.sh artisan pint` + `npm run type-check` green.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Shared/Contracts/Notification/EmailContentProvider.php` | create |
| `app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php` | edit (extends/aliases Shared) OR delete |
| `app/Modules/Notification/EmailContent/EmailContentRegistry.php` | edit (return type → Shared Contract) |
| `app/Modules/Notification/EmailContent/DbEmailContentProvider.php` | edit (implements Shared Contract) |
| `app/Modules/Notification/EmailContent/*EmailContent.php` (4 classes) | edit (implements Shared Contract) |
| `tests/Unit/Notification/EmailContentRegistryTest.php` | edit (type-hint assertion against Shared) |

Total: ~7–8 edits. Under 10-file pack limit.

## Verification Commands

```bash
# Type-check the Contract layer
./scripts/dev.sh artisan pint --test
./scripts/dev.sh npm run type-check

# Tests
./scripts/dev.sh test tests/Unit/Notification
./scripts/dev.sh test tests/Feature/Notification

# Grep: no module reaches into Notification module's internal Contracts after C1
grep -rn "Modules\\\\Notification\\\\EmailContent\\\\Contracts" app/Modules/Finance/ app/Modules/Academic/
# Expected: zero matches (Finance/Academic must import from app/Shared/Contracts/Notification/)
```

## DAG Row

`[S2] → [C1] → {[M3], [C2]}` — C1 is hard predecessor.

## Critical Patterns Applied

- **Pattern #8 (pack-as-bead DAG):** this row.
- **Pattern (CLAUDE.md Forbidden):** cross-module reads must use `app/Shared/Contracts/`.
- **Pattern #3 (Octane):** Contract is stateless interface — no singleton risk introduced.

## Feasibility Notes

- Pure interface extraction. Existing concrete implementation is unchanged.
- Risk: if existing `EmailContentProvider` interface has implementations outside the Notification module (unlikely per S2 grep — no such matches), the rename cascades. Validating must re-grep before approving.

## Handoff to Validating

Validating gates:
1. Confirm no implementor lives outside `app/Modules/Notification/`.
2. Confirm Finance/Academic don't currently import the old Notification Contract namespace (grep above).
3. Confirm Pint + type-check green path.
