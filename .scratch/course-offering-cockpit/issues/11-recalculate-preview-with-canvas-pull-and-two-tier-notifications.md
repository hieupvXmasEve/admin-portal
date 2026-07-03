# Recalculate preview with optional Canvas pull and two-tier notifications

Status: ready-for-agent

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## Origin

Grilling session 2026-07-03. Decisions recorded in `docs/adr/0014-post-completion-grade-changes-flow-only-through-recalculate.md`. Builds on the preview infrastructure from issue 10 (`10-canvas-grade-sync-with-preview-in-scores-tab.md`).

## What to build

Replace the Recalculate confirm-dialog on completed offerings with a **preview-first flow**, and make it the only path for post-completion grade changes (ADR 0014).

1. **Optional Canvas pull.** The Recalculate dialog offers "pull latest grades from Canvas first" (only when a `mapped` Canvas mapping exists). Pull is **student-selectable** (same picker semantics as issue 10); the recalculation itself always covers the **whole offering** — selective input, whole-offering computation. Existing idempotency guards keep unchanged students no-op.
2. **Preview (dry-run of the completion action).** Before applying, staff see:
   - If pulling: the Canvas score diff (reuse issue 10's diff table + unmatched section).
   - Result changes per student: `final_percentage` / letter grade old → new, pass/fail flips, EGC level changes (promotion or demotion per issue 09's revert path).
   - **Which notification tier each student would receive** (see 3), and who receives nothing.
   - The dry-run must produce zero side effects: no writes, no `AcademicProgressionEvent`, no notifications, no outbox entries.
3. **Two-tier notifications (behavior change).** Current behavior notifies only status-changed students; score-only changes are silent.
   - **Strong tier** — status changed (pass↔fail) and/or EGC level changed: existing notification, including the promotion/demotion copy from issue 09.
   - **Light tier** — final score changed but status did not (e.g. 85% → 80%, still passing): new "your score was updated from X to Y" notification.
   - No change → no notification. Repeated recalculate with no score change sends nothing (regression-test alongside the existing idempotency guard).
4. **Apply.** One transaction: (optional) Canvas pull for the selected students → full recalculate → events + notifications exactly as previewed in kind (values are recomputed at apply time; Canvas is re-fetched, the preview payload is never replayed).
5. **Permissions.** The whole flow — including the embedded Canvas pull — is gated by the existing `recalculate_course_offering` permission only. Staff do not additionally need `sync_course_grades` to use the pull option inside Recalculate.

## Implementation notes

- `MarkCourseOfferingCompletedAction` (recalculate path) and `EgcLevelProgressionService` need a dry-run mode that computes and returns the outcome (per-student result diff, progression changes, notification tier) without persisting or dispatching. Prefer threading an explicit flag/collector over transaction-rollback tricks — the queue is Redis, so queued notifications would NOT roll back with the DB transaction.
- The previous-status map already fed into `processEgcProgression()` is the natural seed for computing tiers.
- Notification copy for the light tier goes through the Notification V2 outbox pipeline like the existing ones; campus-scoped.
- Frontend: extend the cockpit Recalculate action in `ScoresTab.vue` to open the preview drawer (reusing issue 10's component) instead of the bare confirm dialog.
- Standalone sync endpoints from issue 10 must reject completed offerings — verify that guard lands there, not here.

## Acceptance criteria

- [ ] Recalculate on a completed offering opens a preview showing result changes, EGC level changes, and per-student notification tier; nothing is persisted or dispatched by the preview.
- [ ] With Canvas pull enabled, the preview also shows the score diff and unmatched students; apply pulls only the selected students, then recalculates the whole offering.
- [ ] Score-only change → light notification; status/EGC change → strong notification; no change → no notification.
- [ ] Repeated recalculate with no changes: no duplicate events, no notifications (idempotency regression).
- [ ] Whole flow gated by `recalculate_course_offering` alone; Canvas rule blockers (ADR 0013) still apply before recalculate is available.
- [ ] Feature tests cover: dry-run purity (no writes/events/notifications), tier assignment matrix (score-only / status / EGC-level / no-change), pull-then-recalculate transaction, permission 403.

## Blocked by

- `10-canvas-grade-sync-with-preview-in-scores-tab.md` — reuses its diff-table component, student picker, and `CanvasGradeSyncService` dry-run/filter modes.
