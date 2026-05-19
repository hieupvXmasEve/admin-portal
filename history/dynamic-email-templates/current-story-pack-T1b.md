# Current Story Pack: T1b — Finance auth backfill

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** T · **Wave:** 3
**Mode:** high-risk (parent) · **Story risk:** MEDIUM (per-test scope unknown until setUp check)
**Depends on:** S3 ✅ (`spike-S3-test-fail-root-cause.md`) — confirmed cluster cause = tests skip `actingAs($student, 'sanctum')`.

## Entry State

- ~30 finance tests fail with `401 Unauthorized` when hitting `auth:sanctum`-guarded student API routes.
- Sample: `tests/Feature/Finance/StudentFinanceDngAccessTest.php` lines 101 + 121 (3 fails in this file alone).
- Hypothesis (S3): either a shared `TestCase::setUp` regression OR ~30 individual mechanical actor-add per test.

## Exit State

- All previously-401-ing finance tests pass.
- Either:
  - **Batch path:** a single setUp change in `tests/TestCase.php` or `tests/Feature/Finance/TestCase.php` restores the missing actor-context; ≤2 file edits total.
  - **Per-test path:** every failing test adds `$this->actingAs($student, 'sanctum')` (or matching guard) before its HTTP call; ≤30 small edits across ≤30 files.
- Any test that resists either path within budget → quarantined via T2 with reason logged.
- `./scripts/dev.sh test tests/Feature/Finance` returns 0 failures.

## Story Phases (worker mandatory order)

**Phase 1 (10-min timebox):** Inspect `tests/TestCase.php` and any `tests/Feature/Finance/*TestCase.php`. Compare to a known-passing student API test (e.g. `tests/Feature/Student/*Test.php` — find one). If passing tests share a setup primitive failing finance tests lack → BATCH PATH (single edit, ~10 min). Else → PER-TEST PATH.

**Phase 2 (batch path):** Apply the single setUp/trait change. Run `./scripts/dev.sh test tests/Feature/Finance` to confirm.

**Phase 2 (per-test path):** Enumerate failing tests via `./scripts/dev.sh test tests/Feature/Finance 2>&1 | grep FAILED`. For each, add `$this->actingAs($student, 'sanctum')` before the HTTP call. Budget: 5 min/test, 30 tests max = 2.5h hard cap. Workers exceeding 5 min on a single test → quarantine.

**Phase 3:** Update T2 quarantine log (`history/dynamic-email-templates/quarantine-log.md` — new file) for any test that couldn't be repaired in budget.

## File Ops Inventory

**Batch path:**

| Path | Op |
|---|---|
| `tests/TestCase.php` OR `tests/Feature/Finance/TestCase.php` | edit (restore actor-context primitive) |

**Per-test path:**

| Path | Op |
|---|---|
| `tests/Feature/Finance/**/*Test.php` (failing subset) | edit (add `actingAs`) |
| `history/dynamic-email-templates/quarantine-log.md` (T2) | create (only if any test exceeds budget) |

Both paths: ≤30 file edits hard cap; worker must split into T1b-a + T1b-b if exceeded.

## DAG Row

`[S3 ✅] → [T1b]; [T1b] → [T2 (conditional quarantine)]` — independent lane.

## Critical Patterns Applied

- **#8 pack-as-bead** — DAG row above.
- **CLAUDE.md security** — actor context must use correct guard (`sanctum` for student, `web` for admin). Worker must verify guard per route before adding `actingAs(...)`.

## Feasibility Notes

- 30× scope spread depending on Phase 1 result. Workers MUST do Phase 1 before committing to either path.
- Risk: shared setUp change has wide blast radius — Phase 2 (batch) requires full `tests/Feature/Finance` re-run to confirm no test regresses from added actor-context.

## Handoff to Validating

Validating gates:
1. Confirm `tests/TestCase.php` is the actual base and identify the actor-context primitive used by passing student tests.
2. Confirm `auth:sanctum` is the correct guard for student API routes (vs `student-api` or other named guard).
3. Confirm `actingAs($student, 'sanctum')` syntax matches the project's existing pattern (some Laravel versions use `actingAs($student, 'sanctum')` vs `Sanctum::actingAs($student, ['*'])`).
