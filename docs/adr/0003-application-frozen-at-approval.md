---
id: ADR-0003
title: "Application data is frozen at approval"
status: accepted
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Application data is frozen at approval

While an Application is `pending`, it is fully mutable — the CRM ingestion API upserts (overwrites) any field, including the single English-test result. At **Approve**, the final state is snapshotted into the Student and the Application is **frozen**: subsequent CRM updates to an `approved`/`rejected` Application are rejected with `409 Conflict`.

This is why no field-level change history is kept (the snapshot at approval is the record of truth), and it gives the CRM a clear contract: correct data before approval, not after. Re-opening for edits requires a Revoke back to `pending`.
