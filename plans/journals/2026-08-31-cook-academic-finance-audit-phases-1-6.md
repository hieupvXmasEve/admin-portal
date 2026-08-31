---
title: Cook academic-finance audit phases 1-6
date: 2026-08-31
summary: Landed P-01/P-03/P-04/P-05/Q-08; stopped before EGC Phase 7
---

# Cook academic-finance audit phases 1-6

Landed P-01/P-03/P-04/P-05/Q-08; stopped before EGC Phase 7.

## What happened

- Closed blocker plan `260831-1213` (`status: completed`) then cooked `260901-0155` phases 1–6.
- Phase 1: 7 audit-baseline tests. P-01/P-03/P-04/P-05 now green; P-02/P-12 still red (Phase 7).
- Phase 2: `AcademicDngPaymentProjectionSync` on reconcile/capture/bridge/resolve. Webhook still throws on syncer `failed`.
- Phase 3: `ResitFeeGate` live ledger for schedule + complete.
- Phase 4: typed `fee_outcome` disposition; retake paid-cancel 2 actions + ack; legacy text-match fallback logs.
- Phase 5: split rejects `supportsInstallments=false` via `ValidationException`.
- Phase 6 stop-gate: `calculatePreserveAmount()` already had PARTIAL = 50%. Owner: still delete PARTIAL. COURSES-scope settles linked charges; unlinked invoices excluded + `Log::warning`.

## Decision

- Stop cook after Phase 6. Phases 7–8 pending.
- Docs-site for retake cancel / defer PARTIAL deferred to Phase 8.
- Review 9/10, 0 critical. Warnings left: firstOrCreate payload freeze; programmatic retake cancel without `fee_outcome` forfeits; legacy PARTIAL `DeferCase` can throw in `calculatePreserveAmount`.

## Next steps

- Phase 7: EGC 50% of target charge net; stop creating flat 15M credit.
- Phase 8: dead code + `docs/audit-academic-finance.md` + docs-site.
- Optional: COURSES-scope positive linked-charge test; data-migrate leftover PARTIAL rows.

AgentWiki publish skipped

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
