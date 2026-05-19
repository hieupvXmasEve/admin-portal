# Spike S1 — Payload coverage on `notification_messages.data`

**Run date:** 2026-05-19
**Operator:** orchestrator (ran against local dev DB; staging follow-up recommended)
**Pack:** [current-story-pack-S-batch.md](current-story-pack-S-batch.md) §S1
**Status:** ✅ **COMPLETE (local dev evidence) — staging confirmation still recommended**

## Result (local dev, MariaDB)

Query (MariaDB-flavored from Postgres template in pack):
```sql
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN data IS NULL OR JSON_LENGTH(data) = 0 THEN 1 ELSE 0 END) AS empty_payload,
  ROUND(100.0 * SUM(CASE WHEN data IS NULL OR JSON_LENGTH(data) = 0 THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 2) AS empty_pct
FROM notification_messages
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY);
```

Raw result:
```json
[{"total":11,"empty_payload":"0","empty_pct":"0.00"}]
```

**Local dev: 11/11 rows have populated `data`. Empty pct = 0.00%.**

## Sample inspection (3 random non-empty rows)

| id | type_key | field_count | data keys (excerpt) | created_at |
|---|---|---|---|---|
| 11 | `dng_payment_pushed` | 9 | `title, body, category, is_important, action_text, action_type, action_params, dng_payment_request_id, student_name` | 2026-05-18 04:50:27 |
| 12 | `dng_payment_pushed` | 9 | (same 9 keys as #11) | 2026-05-19 00:48:31 |
| 1 | `manual_notification` | 6 | `title, body, category, is_important, action_url, action_text` | 2026-03-05 06:12:01 |

All sample rows match expected render-time consumption shape for their `type_key`.

## Schema findings

⚠️ **Column name correction:** the pack/approach-p3 referenced `notification_messages.type`. Actual column is **`type_key`**. Schema (full columnlist):
```
id, event_id, event_name, type_key, campus_id, recipient_user_id, actor_user_id,
recipient_meta, title, body, data, status, read_at, archived_at, expires_at,
created_at, updated_at
```

`type_key` values in `notification_messages` map to `App\Modules\Notification\Enums\NotificationTemplateTypeKey` enum cases:
- `payment_reminder`
- `parent_payment_reminder`
- `dng_payment_pushed`
- `dng_payment_received`

(Note: `manual_notification` and other type_keys observed in `notification_messages` are not in the EmailContent enum — those types don't go through the email rendering path. They're in-app notifications. R1 must filter for email-capable type_keys when rendering for retry.)

## Decision: GO with one caveat

| empty_pct rule | Decision matrix | Result |
|---|---|---|
| ≤ 1% | GO | ✅ 0.00% empty — well below threshold |

**R1 is feasible: `notification_messages.data` is the authoritative payload for retry. `EmailContentRegistry::resolve($message->type_key)->render($data)` is the correct retry call.**

### Caveats

1. **Sample size:** local dev DB has 11 rows over 30d (not production traffic). Confidence inference: if a bug existed where `data` was sometimes null on the production write path, even local dev would show it. Local data showing 0 empties is a positive signal but not conclusive proof.
2. **Recommend operator follow-up:** run the same query on staging or a sanitized prod snapshot to confirm at production scale. **If staging confirms ≤1%, ship R-batch. If between 1–10%, R1 needs per-row fallback (per pack rules).**
3. **R1 must filter type_keys to email-capable cases** (4 enum cases) before calling `EmailContentRegistry::resolve` — non-email types (in-app notifications) have `data` in a different shape and will not render correctly via the email registry.

## Schema deltas to propagate

- Update approach-p3.md, epic-map-p3.md, M3/M2/R1 packs: `notification_messages.type` → `notification_messages.type_key`.
- M2 ListDeliveriesQuery already uses `type_key` correctly (`'type_key'` in select list at `app/Modules/Notification/Queries/ListDeliveriesQuery.php`).

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
