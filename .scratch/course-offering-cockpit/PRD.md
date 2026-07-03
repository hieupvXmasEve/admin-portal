# PRD: Course Offering Cockpit

Status: ready-for-agent

> Decisions behind this PRD: `docs/adr/0013-course-offering-detail-is-operational-cockpit.md`. Domain terms: `CONTEXT.md` (Course Offering Cockpit, Readiness blocker, EGC, Academic Attendance Rate).

## Problem Statement

Academic staff (Cán Bộ Đào Tạo) managing one course offering must jump between many unrelated surfaces — the Course Offering detail page, the Attendance pages, Course Statistics pages, and Canvas administration (~44 distinct entry points touch a single offering). They cannot tell which page owns the next action. The UI guesses lifecycle state on the frontend and its "ready to finalize" hints disagree with the stricter backend completion rules, so staff hit unexplained failures. The most important lifecycle action — finalizing a course — has no UI at all, and Canvas sync state silently changes how grades are aggregated without any warning. The same score grid is rendered by two different pages that drift apart.

## Solution

Make the Course Offering detail page the **Course Offering Cockpit**: the only operational surface for one course offering. The cockpit renders its lifecycle stage, **readiness blockers**, and available actions from a single backend read model contract — the frontend never infers operational state. Attendance recording moves into the cockpit; the standalone Attendance pages and Course Statistics become report/export-only surfaces that deep-link back to the cockpit. Finalize and Recalculate become first-class, permission-gated cockpit actions, and an unsynced Canvas-mapped offering hard-blocks completion instead of silently falling back to manual grades.

Delivered in three phases: (A) operational-state read model + completion actions + Canvas rule, (B) attendance recording absorbed into the cockpit, (C) duplicate-surface retirement and menu cleanup.

## User Stories

1. As an academic staff member, I want the cockpit to show the offering's lifecycle stage (setup → registration → teaching → grading → completed / cancelled) as computed by the backend, so that what I see always matches what the system will actually allow.
2. As an academic staff member, I want to see a list of readiness blockers (e.g. "3 sessions without recorded attendance", "2 sessions have only auto-system attendance", "Canvas-mapped but not synced"), so that I know exactly what to fix before I can finalize.
3. As an academic staff member, I want each blocker to reference the specific sessions or objects it concerns, so that I don't have to hunt for which session is missing attendance.
4. As an academic staff member, I want a Finalize button in the cockpit that is disabled with an explanation while blockers remain, so that I understand why I can't complete the course yet.
5. As an academic staff member with the complete permission, I want to finalize a course directly from the cockpit once blockers clear, so that I never need developer help or hidden API calls.
6. As an academic staff member with the recalculate permission, I want a Recalculate action on already-completed offerings with a confirmation dialog describing its side effects, so that I can safely correct grades after completion.
7. As an academic staff member without the complete/recalculate permission, I want those actions hidden entirely, so that the cockpit only offers me what I'm allowed to do.
8. As an academic staff member, I want the blockers and available actions to refresh automatically after every action I take in the cockpit (including modal/API actions), so that the UI never shows stale state.
9. As an academic staff member, I want a clear warning when an offering is Canvas-mapped but not yet synced, so that I'm not surprised that completion is blocked.
10. As an academic staff member, I want offerings that don't use Canvas (no mapping, or an ignored mapping) to be unaffected by the Canvas rule, so that non-Canvas courses finalize without extra ceremony.
11. As an academic staff member, I want to record attendance for a session directly from the cockpit's sessions view, so that I don't have to switch to a separate Attendance menu for routine per-class work. _(Phase B)_
12. As an academic staff member, I want per-session attendance status visible in the cockpit (recorded / not recorded / auto-system only), so that attendance readiness and attendance recording live in the same place. _(Phase B)_
13. As an academic staff member, I want the scores grid in exactly one place (the cockpit's scores tab), so that I never wonder which of two score pages is correct. _(Phase C)_
14. As an academic staff member, I want old links to the Course Statistics per-offering scores page to redirect to the cockpit scores tab, so that my bookmarks keep working. _(Phase C)_
15. As a reporting user, I want Course Statistics to keep its aggregate views, drill-downs, and Excel exports (attendance grid, combined stats), so that reporting is unaffected by the cockpit work.
16. As a reporting user, I want report pages to deep-link to the cockpit when action is needed, so that reading a report flows naturally into acting on it.
17. As a lecturer, I want my existing attendance API flow to keep working unchanged, so that the staff-side reorganization doesn't disrupt my class routine.
18. As a student, I want to receive a completion notification only once — and on recalculation only if my pass/fail status actually changed — so that I'm not spammed by repeated finalizations.
19. As an EGC student, I want recalculation to re-evaluate my level progression against my previous status correctly, so that a grade correction moves me to the right level without duplicate progression events.
20. As an administrator, I want completion and recalculation to be separate permissions assignable per role, so that routine finalization can be broad while recalculation stays tightly held.

## Implementation Decisions

- **Read model, derived not persisted.** A new Academic module Query derives the operational state (lifecycle stage, readiness blockers, available actions) from existing data (`course_status`, sessions, attendance, Canvas mapping). No new lifecycle column, no migration for state.
- **Single contract, single delivery path.** The cockpit page receives one `operational_state` Inertia prop. After every mutating action — including API-modal actions — the frontend refreshes it via an Inertia partial reload of only that prop. No parallel JSON endpoint for the same data.
- **Frontend inference is deleted, not mirrored.** The existing Vue lifecycle/warning computation in the cockpit header is removed and replaced by rendering the backend contract.
- **Blocker shape.** Each readiness blocker carries a stable machine code, a human message, and references to the offending objects (e.g. session ids). Blockers are the single vocabulary for "why an action is disabled".
- **Action semantics.** `available_actions` lists each action with an allowed flag and `blocked_by` codes referencing blockers. State-blocked actions are sent (UI disables and explains); permission-denied actions are omitted entirely.
- **Canvas completion rule (business-rule change).** Finalize and Recalculate are both blocked when a Canvas mapping with status `mapped` exists and the offering is not synced. `pending` and `ignored` mappings do not block. Applies going forward only; already-completed offerings are untouched. Enforced in the completion Action (backend), surfaced as a blocker (frontend).
- **Permissions.** Two new permissions following the repo's `verb_entity` convention: `complete_course_offering` (finalize; granted by default to roles holding `edit_course_offering`) and `recalculate_course_offering` (stricter, assigned narrowly).
- **Recalculate exposure is gated on an audit.** Existing recalculate semantics are kept (survey not re-attached; only status-changed students notified; EGC progression receives the previous-status map), but the EGC progression recalculate path must be deep-audited (level demotion/promotion when scores change) before the Recalculate button ships at the end of phase A.
- **Attendance ownership (phase B).** Per-offering attendance recording moves into the cockpit sessions view. The standalone staff Attendance pages are demoted to cross-offering reporting. The lecturer attendance API is a separate surface and is not modified.
- **Duplicate-surface retirement (phase C).** The Course Statistics per-offering assessment-scores page is retired and redirects to the cockpit scores tab. Excel exports stay under Course Statistics. Sidebar menus are cleaned up to match the ownership boundaries.
- **Phasing.** A (read model + Finalize/Recalculate + Canvas rule) → B (attendance absorption) → C (dedupe + menu cleanup). Each phase ships independently.

## Testing Decisions

- **One seam: HTTP feature tests (Pest).** All behavior is verified at the HTTP layer — no new seams, no unit tests duplicating the same behavior, no browser/E2E infrastructure.
  - The cockpit detail page response is asserted for the `operational_state` prop across data scenarios: fresh offering (setup), sessions without recorded attendance, sessions with only auto-system attendance, Canvas mapped-but-unsynced, fully ready, completed, cancelled.
  - Finalize/recalculate endpoints are asserted for: real backend blocking (attendance blockers, Canvas blocker), 403 for missing `complete_course_offering` / `recalculate_course_offering`, success paths, and recalculate notifying only status-changed students.
  - Phase B adds attendance-recording endpoint tests through the same seam.
- **Test external behavior only** — response props, status codes, database state, dispatched notifications. Never internal method calls or private structure.
- **Prior art:** the repo's existing Pest feature tests (`tests/Feature`), using model factories with custom states where available.
- **Known harness gotchas to respect:** CSRF is active in feature tests (include `_token`); run test files/subdirectories explicitly (the whole Academic directory aborts on a pre-existing failure); never use `--env=testing` (it targets the dev database).
- Frontend changes are covered by the existing quality gates (`vue-tsc` type-check, eslint) — no new frontend test seam.

## Out of Scope

- Any change to the lecturer attendance API or lecturer/student experiences.
- Playwright/browser E2E infrastructure.
- Redesigning Course Statistics index/unit aggregate pages or its Excel exports (they stay as-is, minus the retired per-offering scores page).
- Changing completion business logic beyond the Canvas blocker rule (grade formulas, EGC progression rules, notification content are untouched).
- Refactoring the legacy completion/statistics mega-services beyond what the read model requires.
- Backfilling or reprocessing offerings completed before the Canvas rule lands.
- Roster/session/survey management changes (already in the cockpit; unchanged by this PRD).

## Further Notes

- The authoritative decision record is ADR 0013; if implementation uncovers a conflict with it, surface the conflict rather than silently deviating.
- Glossary terms to use verbatim in issues, tests, and code discussion: **Course Offering Cockpit**, **Readiness blocker** (see `CONTEXT.md`).
- The EGC recalculate audit (phase A precondition for the Recalculate button) is investigation work — if it finds unsafe behavior, file a follow-up issue rather than expanding phase A.
- Pattern precedent for `available_actions` exists in the Finance module and the Academic retake-registration query; follow the established shape rather than inventing a new one.
