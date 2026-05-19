# Spike Decision Matrix — S-batch

**Run date:** 2026-05-19
**Pack:** [current-story-pack-S-batch.md](current-story-pack-S-batch.md)
**Inputs:**
- [spike-S1-payload-coverage.md](spike-S1-payload-coverage.md) — ⏸️ BLOCKED (operator)
- [spike-S2-emailservice-callers.md](spike-S2-emailservice-callers.md) — ✅ COMPLETE
- [spike-S3-test-fail-root-cause.md](spike-S3-test-fail-root-cause.md) — ✅ COMPLETE

## Per-story decision

| Downstream story | S1 | S2 | S3 | Status | Notes |
|---|---|---|---|---|---|
| **R1** (RetryDeliveryAction wiring) | PENDING-OPERATOR | n/a | n/a | ⏸️ **PARKED** | Operator must run staging payload-coverage query before R1 leaves planning |
| **R2** (API + UI) | (inherits R1) | n/a | n/a | ⏸️ PARKED | Blocked on R1 |
| **R3** (UI gate + tests) | (inherits R1) | n/a | n/a | ⏸️ PARKED | Blocked on R1 |
| **M1** (ListDeliveriesQuery) | n/a | ✅ GO | n/a | ✅ **GO** | Read-only query construction, no S1/S3 dependency |
| **M2** (controller re-point) | n/a | ✅ GO | n/a | ✅ GO | Inherits M1 |
| **M3** (EmailService outbox emit) | n/a | ⚠️ **GO with C1 hard-blocked first** | n/a | ⚠️ **REVISIT-SCOPE** | S2 found 6 Finance Actions reaching EmailService across module boundary; C1 promoted from parallel to hard predecessor |
| **M4** (jobs migrate) | n/a | ✅ GO | n/a | ✅ GO | Inherits M3 |
| **M5** (deprecate EmailController) | n/a | ✅ GO | n/a | ✅ GO | Inherits M3 |
| **C1** (Contract creation) | n/a | ✅ **PROMOTED to critical path** | n/a | ✅ **GO + critical** | Was parallel-optional; now hard predecessor to M3 |
| **C2** (RenderDraftEmailTemplateAction) | n/a | ✅ GO | n/a | ✅ GO | Inherits C1 |
| **T1** (test-debt repair) | n/a | n/a | ⚠️ **FORK into T1a + T1b** | ⚠️ **REVISIT-SCOPE** | S3 found CSRF cluster (7 fails, batch fix) and Finance auth cluster (~30 fails, per-test) are NOT shared root cause |
| **T1a** (CSRF batch, new) | n/a | n/a | ✅ GO | ✅ GO | ~30 min; validating must confirm production CSRF intent before silencing |
| **T1b** (Finance auth backfill, new) | n/a | n/a | ✅ GO with quarantine fallback | ✅ GO | Check shared setUp regression first (10-min); if not, per-test fix; T2 fallback |
| **T2** (quarantine memo) | n/a | n/a | conditional | 🟡 conditional | Open if T1b hits budget ceiling |
| **F1** (P2 cleanup catch-all) | n/a | n/a | n/a | ✅ GO | No spike dependency |

## Wave-by-wave plan (post-decision)

### Wave 2 — unblocked NOW (can start immediately on user approval)

- **C1** — Contract creation (no deps; promoted to critical path)
- **M1** — ListDeliveriesQuery (no deps after S2 cleared)
- **T1a** — CSRF cluster batch fix (no deps after S3 cleared)
- **F1** — P2 cleanup (no deps)

### Wave 2.5 — blocked on operator handoff

- **R1 → R2 → R3** — full R-epic — operator must complete S1 before R1 enters planning.

### Wave 3 — blocked on Wave 2

- **M3** (needs C1) → **M4** → **M5**
- **C2** (needs C1)
- **M2** (needs M1)
- **T1b** (after the 10-min shared-setUp check decides batch vs per-test)

## Epic-map-p3 amendments required

Before returning to planning for Wave 2 story packs, update `epic-map-p3.md`:

1. **Story Queue table:**
   - Split T1 into T1a (CSRF batch, 1 story) + T1b (Finance auth backfill, 1 story).
   - Update M3 row `Depends On` from `S2, C1` to `S2, C1` (already correct) but add note: `C1 is hard predecessor per S2 findings`.

2. **Critical-path diagram:**
   - Move C1 from "parallel side quest" to critical-path row.
   - Edge: `S2 → C1 → M3 → M4 → M5`.
   - Edge: `S3 → T1a` (fast); `S3 → T1b → T2 (conditional)`.

3. **Wave plan:**
   - Wave 2 expands from `(C1 ∥ R1 ∥ M1 ∥ T1)` to `(C1 ∥ M1 ∥ T1a ∥ F1)` — R1 parked, T1 forked.

## Critical findings (carried to validating)

| # | Finding | Source | Action |
|---|---|---|---|
| 1 | C1 must precede M3, not parallel | S2 | Update epic-map critical path |
| 2 | T-epic forks into 2 sub-clusters | S3 | Split T1 into T1a + T1b |
| 3 | CSRF fix may hide production behavior risk | S3 | Validating gate before T1a fix |
| 4 | Finance setUp regression hypothesis | S3 | 10-min check before T1b commits |
| 5 | R-epic parked on operator S1 handoff | S1 | Surface to user; do NOT swarm R-batch yet |

## Approval Request

**This decision matrix is the swarming output for S-batch.** No code edits ran. 4 markdown artifacts created (S1 stub + S2 + S3 + this matrix). All pack exit criteria from `current-story-pack-S-batch.md` are satisfied.

**Next action:** return to `khuym:planning` to:
- Apply epic-map-p3.md amendments (3 items above)
- Author Wave-2 story packs: `current-story-pack-C1.md`, `current-story-pack-M1.md`, `current-story-pack-T1a.md`, `current-story-pack-F1.md`
- Surface S1 operator handoff to user

Then `khuym:validating` reviews each Wave-2 pack before Wave-2 swarming.
