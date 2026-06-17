# Design

## Domain Model

No new money domain model is introduced.

Batch Studio remains the orchestration shell for bulk finance jobs:

- `BatchJobType::ChargeGeneration`
- `BatchPreviewLine`
- `BatchPreviewTokenService`
- Existing preview queries and write Actions:
  - `PreviewMajorChargeGenerationQuery`
  - `PreviewEgcChargeGenerationQuery`
  - `GenerateMajorChargesAction`
  - `GenerateEgcChargesAction`

Business rules:

- Preview numbers must equal committed numbers. Commit must re-resolve and
  recompute-compare through the Batch Studio preview token.
- HP/Tuition commit authorization uses `create_finance_charges`.
- EGC commit authorization uses `generate_egc_finance_charges`; an EGC-only
  operator must not be blocked by unrelated HP/Tuition permissions.
- Standalone HP/Tuition and EGC generation `POST` routes must not remain normal
  write paths after cutover.
- EGC support pages are not charge-generation command surfaces and remain
  separate.

## Application Flow

Primary flow:

1. Operator selects HP/Tuition or EGC from Finance Office navigation, Cockpit,
   Lookup, or a bookmarked compatibility URL.
2. The request lands in `finance.batch-studio.charges` with a prefilled
   `fee_category`.
3. Batch Studio Step 1 displays the selected fee category and semester context.
4. Step 2 previews via `finance.batch-studio.charges.preview`.
5. Step 3 confirms selected lines and due date.
6. Step 4 commits via `finance.batch-studio.charges.commit`; the server consumes
   the preview token and calls the existing generation Action.

Compatibility flow:

- `GET finance.major.charges.index` and `GET finance.egc.charges.index` redirect
  or forward to the Batch Studio charge wizard with safe prefill.
- If a repair-only standalone page is retained, it must be explicitly labelled
  as secondary and must not be present in primary navigation.
- `POST finance.major.charges.store` and `POST finance.egc.charges.store` either
  reject normal writes with a redirect/instruction or delegate through a Batch
  Studio-verified token path. They must not generate charges from a stale client
  snapshot.

## Interface Contract

| Route name | Method | Target state |
| --- | --- | --- |
| `finance.batch-studio.charges` | GET | Primary HP/Tuition and EGC generation wizard; supports safe `fee_category` prefill. |
| `finance.batch-studio.charges.preview` | POST | Supports `major` and `egc`; permission is checked per selected fee category. |
| `finance.batch-studio.charges.commit` | POST | Commits only with valid preview token and per-category permission. |
| `finance.major.charges.index` | GET | Compatibility redirect/forward to Batch Studio, or explicit repair-only deep link if approved. |
| `finance.major.charges.store` | POST | Retired/blocked compatibility path; must not directly generate normal charges. |
| `finance.egc.charges.index` | GET | Compatibility redirect/forward to Batch Studio, or explicit repair-only deep link if approved. |
| `finance.egc.charges.store` | POST | Retired/blocked compatibility path; must not directly generate normal EGC charges. |

Query/prefill contract:

- Allow only whitelisted query keys such as `fee_category`, `semester_id`, and
  explicit selected student ids if the current wizard can safely support them.
- Ignore or reject unknown prefill keys.
- Never accept amounts or charge lines from query params.

## Data Model

No database migration is expected.

No new persistent operation table is expected. If implementation proves Batch
Studio needs durable queued execution, pause this story and open a separate
accepted design because that would change the execution model.

## UI / Platform Impact

- Finance Office navigation sends HP/Tuition and EGC generation to Batch Studio.
- Batch Studio charge wizard may need clearer labels for the selected category:
  `Sinh HP/Tuition` and `Sinh phí EGC`.
- Legacy labels such as standalone `Generate HP (Tuition)` and `EGC · Generate
  Charges` must not remain primary navigation labels after the migration.
- EGC support pages remain visible according to their existing permissions.
- Use `financeRoutes.batchStudio.*` route helpers, not literal paths.

## Observability

- Existing Batch Studio result summary remains the operator-facing evidence.
- Tests must prove old direct POST paths cannot bypass Batch Studio token
  verification.
- Money invariants must be run after exercising HP/Tuition and EGC generation
  through Batch Studio, unless implementation is navigation-only and performs no
  write-capable smoke.

## Alternatives Considered

1. Keep standalone HP/Tuition and EGC generation as primary pages.
   - Rejected because operators keep two command surfaces for the same bulk job
     and can bypass the Batch Studio safety contract.
2. Delete old routes immediately.
   - Rejected because bookmarks, repair workflows, tests, and internal links
     need a compatibility window.
3. Rebuild HP/Tuition and EGC generation logic inside Batch Studio.
   - Rejected because Batch Studio must orchestrate existing Actions and queries,
     not create new money math.
