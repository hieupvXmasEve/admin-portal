# Spike S1 — Payload coverage on `notification_messages.data`

**Run date:** 2026-05-19
**Operator:** orchestrator → **HANDOFF to operator** (needs staging DB access)
**Pack:** [current-story-pack-S-batch.md](current-story-pack-S-batch.md) §S1
**Status:** ⏸️ **BLOCKED — operator must run on staging DB**

## Why blocked

Orchestrator has no staging DB credentials. Local dev DB is freshly migrated and does not represent 30 days of production-equivalent traffic — running the query locally would only return data from current test runs and would not answer the spike question.

## What the operator must do

1. Connect to staging DB (preferred) or the most production-representative environment:
   ```bash
   ./scripts/dev.sh artisan tinker
   # or: psql via tunnel to staging
   ```

2. Run the coverage query:
   ```sql
   SELECT
     COUNT(*) AS total,
     COUNT(*) FILTER (WHERE data IS NULL OR data::text = '{}') AS empty_payload,
     ROUND(100.0 * COUNT(*) FILTER (WHERE data IS NULL OR data::text = '{}') / NULLIF(COUNT(*), 0), 2) AS empty_pct
   FROM notification_messages
   WHERE created_at > NOW() - INTERVAL '30 days';
   ```

3. Spot-check 3 non-empty rows:
   ```sql
   SELECT id, type, data, created_at
   FROM notification_messages
   WHERE data IS NOT NULL AND data::text <> '{}'
   ORDER BY RANDOM()
   LIMIT 3;
   ```

4. Confirm for each sampled row that the `data` keys match what `EmailContentRegistry::resolve($type)->render($data)` would consume. Cross-reference against `app/Modules/Notification/EmailContent/` content provider for the corresponding `type`.

5. Paste the query result + sample row JSON into this file (redact PII — student names, parent contacts — replace with `<REDACTED>`).

## Decision matrix to fill

| empty_pct result | Decision | Action |
|---|---|---|
| **≤ 1%** | GO | R1 may use `$message->data` as authoritative payload; D4 cutoff = "any row created before P3 deploy timestamp" is sufficient. |
| **1–10%** | REVISIT-SCOPE | R1 must add per-row guard: if `data` empty, fall back to `notification_event_outbox.payload` lookup; if outbox row also missing, surface the D4 tooltip. |
| **> 10%** | STOP | Return to exploring. D4 needs redesign — payload presence is not reliable enough to base Retry on `data` alone. |

## Sample inspection table (operator fills)

| Sample row ID | type | data keys | Render-time keys expected | Match? |
|---|---|---|---|---|
| `<redacted-id-1>` | … | … | … | … |
| `<redacted-id-2>` | … | … | … | … |
| `<redacted-id-3>` | … | … | … | … |

## Operator template

Once the query runs, paste below and fill the GO / REVISIT / STOP line:

```text
Query date: <ISO date>
Total rows (last 30d): <N>
Empty-payload rows: <N>
Empty pct: <X.XX%>
Sample row inspection: <see table above>
DECISION: <GO | REVISIT-SCOPE | STOP>
Reasoning: <one paragraph>
```

## Why S1 cannot block the rest of the spike batch

S1 only gates **R-epic** (Retry correctness). S2 and S3 are independent and already complete. The decision matrix can record S1 as `PENDING-OPERATOR` and still resolve M, C, T epic dependencies. R-epic stays parked behind S1.

## Unresolved Questions

- Does staging DB have realistic 30-day traffic, or is staging too thin to be representative? If staging is thin, fall back to a sanitized prod-snapshot or a longer window.
