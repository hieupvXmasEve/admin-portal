---
title: "Zero Debt Work Package Template"
status: template
---

# Zero Debt Work Package Template

No implementation phase may ship as one mega-PR. Create one row per independently
reviewable owner/workflow and freeze it before editing code.

| Field | Required content |
|---|---|
| Package ID / owner / workflow | Stable identifier and one accountable domain |
| Exact manifest | Every file and runtime entry point; one terminal owner per file |
| Debt delta | Rule, exact before count, expected delta, required after count |
| Interfaces | Named contracts, DTOs, events, routes, payloads, provider protocols |
| Dependencies | Package IDs and accepted evidence, not only phase numbers |
| Impact | Portal, data, queue, scheduler, provider, import/export, security |
| Characterization | Exact test files and current behavior locked before change |
| Verification | Exact commands, test scenarios, architecture and inventory gates |
| Rollback | Reversible code path or forward recovery, with stop condition |
| Merge gate | Required reviewers/evidence and ratchet update in the same PR |

Packages with data writes also require environment, selection/query, record IDs,
before/after totals, dry-run, idempotency rerun, backup/recovery, and named approval.
