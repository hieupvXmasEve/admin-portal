---
id: ADR-0051
title: "Admin sidebar titles are English, reversing the 2026-06-18 Vietnamese business-naming decision"
status: accepted
owner: Frontend Team
last_verified: 2026-08-16
scope: architecture-decision
---

# Admin sidebar titles are English, reversing the 2026-06-18 Vietnamese business-naming decision

**Context.** `resources/js/constants/menu-sidebar.ts` mixes Vietnamese and English
titles. The Vietnamese titles in the Finance group — `Lập yêu cầu thanh toán DNG`,
`Hôm nay`, `Doanh thu`, `Sinh phí`, and others — are not accidental: they encode a
deliberate decision from commit `fa6da56a7` (2026-06-18), pinned by
`tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php:110,118-125`, which
asserts the Vietnamese labels are present and the English equivalents (`Generate
HP (Tuition)`, `EGC · Generate Charges`) are absent.

**Decision.** By user decision (2026-08-16), all admin sidebar menu titles and
group labels become English. This reverses the 2026-06-18 business-naming
decision for the Finance group specifically; every other group was already
English or is normalized to English in the same pass
(`plans/260816-2125-sidebar-menu-ia-restructure/`).

**Why.** The mixed-language sidebar was inconsistent with the rest of the admin
portal and with the `docs-site` user guide, which documents the menu in English
with `ko`/`zh` translations layered on top of an English source, not a Vietnamese
one. Keeping Vietnamese only in the Finance menu created a naming split with no
remaining rationale once the rest of the tree normalized.

**Scope.** This ADR covers menu **titles and group labels only**. Finance *page
bodies* (form labels, table headers, in-page copy) remain Vietnamese — retranslating
page content is out of scope here. Whether the app should later adopt real i18n
with a locale switch (the `docs-site` already carries `ko`/`zh` translations, which
argues for it) versus per-surface translation is an open question, tracked as
`plans/260816-2125-sidebar-menu-ia-restructure/plan.md`'s Open Question 1. It needs
its own plan.

**Consequence.** `FinanceOfficeCutoverTest` and `FinanceReportingShellTest` are
updated in the same commit as the rename (Phase 2 of the sidebar IA restructure
plan) — not as incidental test churn, but because the tests encode exactly the
decision being reversed here. The `Sinh HP/Tuition` / `Sinh phí EGC` assertions in
`FinanceOfficeCutoverTest:118-125` are removed rather than renamed: those strings
exist only inside a dead commented-out menu block being deleted in the same phase,
so the assertion has been passing vacuously against a comment, not live behavior.

## Alternatives considered

- **Keep Finance Vietnamese, English everywhere else.** Rejected — perpetuates the
  exact inconsistency this ADR removes, and gives the sidebar no single language
  contract a new contributor can rely on.
- **Full i18n now.** Rejected for this change — a locale-switching system is a
  larger, separate effort (Open Question 1) with its own scope; the menu title
  rename does not block it and should not be gated on it.
