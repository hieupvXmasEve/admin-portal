# Harness — Acceptance Evidence (S-009 Finance Audit Workspace)

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

Branch: `feat/s-009-finance-audit-workspace` (cut from `dev`). Implemented 2026-06-15
following `docs/superpowers/plans/2026-06-15-finance-audit-workspace.md` (pre-execution
review applied; see the plan's Revision log).

## Test baseline (Task 0)

- `tests/Feature/Finance` + `tests/Unit/Finance` baseline **before** this work:
  **26 failed / 397 passed** (matches memory `finance-test-suite-preexisting-failures`).
- **After** this work: **26 failed / 436 passed** (1834 assertions) — same failure count,
  **+39 net new passing tests**, **no new red**. The 26 failures are the pre-existing
  set (auto-allocate pages, EGC generation, payments-index rendering, DNG student
  access) — none in code this story touched.

## Targeted suite

`./scripts/dev.sh test --filter="Audit|Integrity|FinanceInvariant|LedgerTimeline"`
→ **42 passed (211 assertions)**, all green. Covers:

- Integrity engine: `FinanceAuditScope`, `FinanceInvariantRegistry` (15 invariants),
  `FinanceIntegrityAuditor` (global + scoped + error-surfacing + all-15-run-clean guard),
  command parity.
- Read layer: `ResolveFinanceAuditSearchQuery` (precedence: prefix-first, exact code
  outranks external_ref, DNG ids, charge prefix, external_ref-positive, cross-campus
  invisible, ambiguous, no bare-integer guess), `GetFinanceAuditGraphQuery` (fan-out
  edges, cache drift, ledger_entries, semester scope-bounding, orphan-node pruning),
  `FinanceLedgerTimelineBuilder` (signed, ordered), `FinanceAuditWarningBuilder`
  (invariant + cache_drift), permissions, FormRequest, workspace page (renders /
  denies 403 / cross-campus empty).

## Command parity (Task 5)

`./scripts/dev.sh artisan finance:audit-invariants --sample` runs cleanly against the
dev DB: byte-identical table, finds INV-6 (28, sample ids 1182,1343,1439,1184,1185) and
INV-13 (1, sample id 1067), 29 total offending. A failing invariant SQL is surfaced as
`ERROR` (never swallowed into a clean ✅) — verified by the auditor error test.

## Static analysis / format

- **Pint**: 12 changed PHP files pass (`--test`); one `ordered_imports` fix applied to
  the command and committed.
- **ESLint**: `Workspace.vue` and `menu-sidebar.ts` clean (exit 0).
- **Prettier**: both frontend files pass `--check` after formatting.
- **vue-tsc (`npm run type-check`)**: ⚠️ **could not run** — the whole-project type-check
  is OOM-killed (SIGKILL) by the dev container's memory cgroup even at
  `--max-old-space-size=6144`. This is a pre-existing environment limit (PHP tests run
  fine; project-wide vue-tsc does not fit in the container), not a defect in this code.
  Mitigated by clean per-file ESLint + the page test asserting the Inertia component and
  prop shape. **Run `npm run type-check` in an environment with more memory (e.g. host or
  CI) before merge.**

## Deferred / outstanding

- **Browser smoke (required by validation.md, NOT yet run):** the repo has no Playwright/
  E2E harness, and a real visit needs Super-Admin auth + campus session. HTTP/Inertia
  behavior (render, 403 without permission, cross-campus → empty/not-denied, deferred
  prop wiring) is proven by `FinanceAuditWorkspacePageTest`, but the visual rendering of
  the deferred graph/timeline/derived-balance/warning panels and the share-link reopen
  flow still need a manual or automated browser pass. **Remaining acceptance step.**
- **Export endpoint:** deferred by design (permission `export_finance_audit_workspace`
  seeded; the workspace renders a disabled Export affordance). No data-egress route or
  audit-log entry ships in this story — follow-up slice when an audit-log surface is
  chosen. `validation.md` rows 21/25 were softened to match.

## Review gates

- Slice 1 (shared integrity engine) — reviewed; reviewer empirically verified all 15 SQL
  ports produce identical global counts to the original; no Critical issues. Two Important
  follow-ups applied (15-invariant run-clean guard test; sample-error parity note).
- Slice 2 (permissions + read layer) — reviewed; campus isolation airtight across all six
  resolver branches, no N+1, bounded scope. Important follow-ups applied (orphan-node
  pruning, centralized cache-drift check in SettlementService, +resolver/scope coverage).
