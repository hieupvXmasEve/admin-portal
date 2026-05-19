# Current Story Pack: S-batch (Spike feasibility — S1 + S2 + S3)

**Feature:** dynamic-email-templates
**Phase:** P3
**Epic:** S (Spike)
**Mode:** high-risk
**Type:** read-only investigation pack
**Timebox:** 4h total (per [approach-p3.md](approach-p3.md) §Mode gate)
**Depends on:** none (this IS the foundation)

## Entry State

- `history/dynamic-email-templates/CONTEXT-p3.md` D1–D6 locked
- `history/dynamic-email-templates/approach-p3.md` documents 7 validating questions
- `history/dynamic-email-templates/epic-map-p3.md` lists R/M/T downstream epics with `Depends On: S1/S2/S3`
- No source code edits permitted in this pack
- All output lands under `history/dynamic-email-templates/spike-*.md`

## Exit State (all three must land)

- `history/dynamic-email-templates/spike-S1-payload-coverage.md`
- `history/dynamic-email-templates/spike-S2-emailservice-callers.md`
- `history/dynamic-email-templates/spike-S3-test-fail-root-cause.md`
- A short consolidated `history/dynamic-email-templates/spike-decision-matrix.md` resolving each downstream epic's `Depends On` to GO / REVISIT-SCOPE / STOP

## Story S1 — Payload coverage on `notification_messages.data`

**Goal:** Confirm approach-p3 §"D1 is already implementable" against real data.

**Steps:**

1. Connect to staging DB via `./scripts/dev.sh artisan tinker` or `psql` (staging credentials only — no production access).
2. Run:
   ```sql
   SELECT
     COUNT(*) AS total,
     COUNT(*) FILTER (WHERE data IS NULL OR data::text = '{}') AS empty_payload,
     ROUND(100.0 * COUNT(*) FILTER (WHERE data IS NULL OR data::text = '{}') / NULLIF(COUNT(*), 0), 2) AS empty_pct
   FROM notification_messages
   WHERE created_at > NOW() - INTERVAL '30 days';
   ```
3. Spot-check 3 random non-empty rows: confirm `data` keys match what `EmailContentRegistry::resolve($type)->render($data)` would expect for that `type`.
4. Record:
   - Total rows in window
   - Empty-payload count and percentage
   - Sample row IDs inspected
   - Interpretation: ≤1% empty_pct → GO; 1–10% → REVISIT-SCOPE (extend D4 cutoff logic to per-row check); >10% → STOP, return to exploring.

**Exit criteria:**

- `spike-S1-payload-coverage.md` contains the query, raw result, sample inspection table, and a single GO / REVISIT-SCOPE / STOP recommendation.

## Story S2 — `EmailService::send*` caller graph

**Goal:** Size M3 (legacy-send migration) scope before swarming. Find every direct caller across the repo, classified by module.

**Steps:**

1. `grep -rn "EmailService" app/ --include="*.php"` and `grep -rn "EmailService" resources/ --include="*.vue" --include="*.ts"` (the latter should return empty; sanity check).
2. For each match, classify by:
   - **Module:** legacy (`app/Http/Controllers/Api/V1/Admin/`, `app/Jobs/`, `app/Services/`), Notification (`app/Modules/Notification/`), Finance (`app/Modules/Finance/`), Academic (`app/Modules/Academic/`), other.
   - **Call shape:** `sendSingleEmail`, `sendBulkEmail`, channel adapter (`EmailChannelAdapter`, `RenderedEmailChannelAdapter`), DI constructor only (no call), or import only (dead).
3. **Note already discovered (pre-pack scout, 2026-05-19):** Finance module Actions (`SendParentPaymentRemindersAction:34`, `SendPaymentRemindersAction:28`, `SendDueItemRemindersAction:24`, plus siblings) resolve `app(EmailService::class)` directly. This is a cross-module reach that violates `CLAUDE.md` Forbidden Patterns and forces C1 (Contract) before M3.
4. Output a table:

   | File:line | Module | Call shape | Migration target |
   |---|---|---|---|
   | … | … | sendSingleEmail | outbox emit via Contract |
   | … | … | constructor DI only | leave; refactor in C1 |

5. Decision: if cross-module callers > 5 → M3 grows; require C1 to ship first; epic-map dependency `M3 → C1` becomes hard.

**Exit criteria:**

- `spike-S2-emailservice-callers.md` contains the full classified table and a GO / REVISIT-SCOPE recommendation for M3.

## Story S3 — CSRF + finance test-fail shared-cause probe

**Goal:** Decide whether T epic is 1 batch fix or 37 individual fixes.

**Steps:**

1. Pick 1 of the 7 NotificationOpsController CSRF fails. Reproduce in isolation: `./scripts/dev.sh test --filter <Test::method>`.
2. Pick 1 of the ~30 finance fails (start with `StudentFinanceDngAccessTest`).
3. For each, capture:
   - Full failure trace (stderr to a file under `spike-S3-*.md`).
   - First 10 stack frames.
   - Test setup (does it `actingAs`? does it depend on session state? CSRF token?).
4. Compare: do both fails point to the same setup primitive (e.g., shared `TestCase::setUp` session-seed pattern from P2 story B5)?
5. Record hypothesis Y/N + estimated fix size:
   - **Y, shared:** 1 setUp change, ~30min fix → T1 collapses to 1 commit.
   - **N, separate:** budget 1.5h to investigate root cause per category; if >2 categories, propose T2 quarantine for any test not repaired within budget.

**Exit criteria:**

- `spike-S3-test-fail-root-cause.md` contains both failure traces, comparison memo, and GO / REVISIT-SCOPE / STOP for T epic.

## File Ops Inventory

This is a read-only spike pack. **No code files modified.** Only new artifacts:

| Path | Op |
|---|---|
| `history/dynamic-email-templates/spike-S1-payload-coverage.md` | create |
| `history/dynamic-email-templates/spike-S2-emailservice-callers.md` | create |
| `history/dynamic-email-templates/spike-S3-test-fail-root-cause.md` | create |
| `history/dynamic-email-templates/spike-decision-matrix.md` | create |

Total: 4 new markdown files, 0 code edits. Under the 10-file pack limit (Critical Pattern #8).

## Verification Commands

```bash
# S1 — run staging payload-coverage query (operator runs locally via tinker or psql)
./scripts/dev.sh artisan tinker
# >>> \DB::select("SELECT COUNT(*) FILTER (WHERE data IS NULL OR data::text = '{}')::float / COUNT(*) FROM notification_messages WHERE created_at > NOW() - INTERVAL '30 days'");

# S2 — caller-graph grep
grep -rn "EmailService" app/ --include="*.php"
grep -rn "use App\\\\Services\\\\EmailService" app/ --include="*.php"

# S3 — reproduce CSRF + finance fail in isolation
./scripts/dev.sh test --filter NotificationOpsControllerTest -- --stop-on-failure 2>&1 | head -60
./scripts/dev.sh test --filter StudentFinanceDngAccessTest -- --stop-on-failure 2>&1 | head -60
```

## DAG Row (Critical Pattern #8)

```text
[S-batch]──┬──→ [R-batch] (R1 needs S1)
           ├──→ [M-batch] (M1/M3 needs S2; C1 may run in parallel)
           ├──→ [T-batch] (T1 needs S3)
           └──→ [C-batch] (C1 has no spike dep; can run parallel)
```

## Feasibility Table

| Question (approach-p3.md) | Resolved By | Status |
|---|---|---|
| Spike #1: payload populated on ≥99%? | S1 | OPEN — this pack resolves |
| Spike #2: every `EmailService::send*` caller? | S2 | OPEN — partial scout confirms cross-module callers exist |
| Spike #3: CSRF + finance fails shared root cause? | S3 | OPEN — this pack resolves |
| Schema: is `notification_messages.type` sufficient as `template_key`? | S1 sample-check | OPEN |
| PII retention for `notification_event_outbox.payload`? | Stakeholder (out-of-band) | DEFERRED — not blocking spike |
| Retire date for `email_logs`? | Stakeholder (out-of-band) | DEFERRED — not blocking spike |
| Octane singleton-binding pattern for new retry wiring? | C-batch (post-spike) | DEFERRED — relevant to R1, not spike |

## Exit Decision Matrix Template

After spikes land, `spike-decision-matrix.md` records:

| Downstream story | S1 result | S2 result | S3 result | Status |
|---|---|---|---|---|
| R1 | … | n/a | n/a | GO / REVISIT / STOP |
| M3 | n/a | … | n/a | GO / REVISIT / STOP |
| T1 | n/a | n/a | … | GO / REVISIT / STOP |

## Handoff Note

Spike batch ships read-only artifacts only. No bead reservations needed — workers operate on a read-only branch of `history/dynamic-email-templates/`. Validating reviews this pack before swarming. Once all three spikes land + decision matrix is filled, validating either approves swarming on Wave 2 packs (C1, R1, M1, T1) OR returns the matrix to planning if any STOP appears.
