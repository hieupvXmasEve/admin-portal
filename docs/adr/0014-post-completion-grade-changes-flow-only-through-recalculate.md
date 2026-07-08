# 0014 — Post-completion grade changes flow only through Recalculate

**Status:** Accepted
**Date:** 2026-07-03
**Owner:** Academic / Course Offering Cockpit

## Context

The cockpit Scores tab gains a "Sync from Canvas" action: staff select students (all or a subset), see a preview diff per assessment component (old score → new score), then apply. Canvas is the source of truth — apply re-fetches from Canvas rather than writing the previewed payload, and it overwrites existing component scores regardless of `score_status` (the preview is the safety net; `disputed` cells are badged).

The question was what happens after an offering is completed. A lecturer can still correct a grade in Canvas post-finalize — this is exactly the scenario the recalculate audit (issues 03/09) exists for. A standalone sync on a completed offering would leave component scores diverged from the finalized result (pass/fail, EGC level) until someone remembers to press Recalculate.

## Decision

- **Standalone Canvas grade sync is blocked on completed offerings.** The Scores tab sync action exists only while the offering is not completed.
- **Post-completion, Canvas grades enter Swinx only inside the Recalculate flow**: Recalculate gains a preview (dry-run) and an optional "pull latest grades from Canvas first" step. Canvas pull is student-selectable; the recalculation itself always covers the whole offering (selective input, whole-offering computation — idempotency guards keep unchanged students no-op).
- **Recalculate notifications become two-tier**: status-changed students (pass↔fail, EGC level change) get the strong notification; students whose final score changed without a status flip get a light "score updated" notification. Previously score-only changes were silent. The Recalculate preview lists which student receives which tier before anything is sent.

## Consequences

- No window where a completed offering's component scores contradict its finalized result — sync and recalculate are one previewed transaction post-completion.
- Manual re-typing of Canvas corrections is avoided without allowing a divergence-creating standalone sync.
- The two-tier notification is a behavior change to the existing Recalculate action and must be regression-tested against the notify-only-status-changed contract from ADR 0013 / issue 04.
- The completion action needs a real dry-run mode; the preview must never emit events, notifications, or writes.

## Alternatives rejected

- **Allow sync on completed offerings + banner nudging Recalculate**: leaves a persistent "new scores, old result" state and relies on staff discipline.
- **Auto-recalculate after sync**: merges two heavy side-effect sets (score overwrite + progression + student notifications) behind one button.
- **Hard block with no post-completion path**: defeats the documented correction scenario that Recalculate was built for.
