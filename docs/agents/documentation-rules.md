# Documentation Boundaries & Anti-Bloat Rules

**Core principle:** knowledge is kept small by **discipline + file boundaries**, not automation. These skills do **not** auto-archive, auto-summarize, or rotate files. Every kind of knowledge has exactly **one home** — never dump everything into one file.

## Where each thing lives (one home per knowledge type)

| File / dir | Holds | Must NOT hold |
|---|---|---|
| `CONTEXT.md` | Stable **business glossary** only (ubiquitous language). 1–2 sentences per term. | Implementation detail, bug logs, task status, API specs, dated notes, decisions. |
| `docs/adr/` | **Architectural decisions** (ADRs). Short — 1–3 sentences is fine. | Glossary terms, task tracking, feature specs. |
| `docs/prd/` | Large, long-lived **feature specs** that have stabilized and outgrown `.scratch/`. | Day-to-day task status, code history. |
| `.scratch/<feature-slug>/` | The **issue tracker**: tasks, PRDs-in-progress, status (`Status:` line), checklists. | Stable domain terms, permanent architecture decisions. |
| commits / PRs | The real **code history** and rationale per change. | — |
| `/handoff` output | **Temporary** session handoff only. Reference by path/URL. | Anything already in a PRD, ADR, issue, commit, or diff. |

## The four discipline rules

1. **No duplication.** Never copy content that already lives elsewhere. Reference it by path or URL. (`/handoff`, PRDs, issues all follow this.)
2. **`CONTEXT.md` = glossary, not a notebook.** Only stable business terms, max 1–2 sentences each. No implementation detail, no dates, no "fixed bug X" notes.
3. **ADRs only when warranted.** Create an ADR in `docs/adr/` only if the decision is (a) hard to reverse, (b) would surprise someone without context, and (c) is the result of a real trade-off. Keep it short. Mark old ones `superseded`/`deprecated` instead of deleting.
4. **Create lazily.** Don't pre-create docs in bulk. Add a glossary term when it's actually resolved; create the first ADR only when a real decision needs recording.

## When a file grows too large

- **`CONTEXT.md` too big** → split by context: add `CONTEXT-MAP.md` at root pointing to per-module files (e.g. `app/Modules/Finance/CONTEXT.md`). Don't keep growing one file. Update `docs/agents/domain.md` to multi-context when this happens.
- **A `.scratch/` feature is done** → archive or delete it; it's not permanent.

## Periodic cleanup (the missing automation — do it by hand)

After each major feature, or roughly monthly, run a docs cleanup pass:

- Split any oversized `CONTEXT.md` by context.
- Remove duplication across docs.
- Mark resolved/replaced ADRs as `deprecated` / `superseded`.
- Archive finished PRDs and completed `.scratch/<feature>/` directories.
