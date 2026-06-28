# Decision model: many-students roster, authorize both streams, missing-decision report

Status: ready-for-agent

## Parent

`.scratch/student-hub/PRD.md`

## What to build

Make **Decision** a first-class governance document per ADR-0008, demoable on its own through a missing-decision report (no Hub needed yet).

End-to-end behavior:

- Add a **Decision ↔ Student many-to-many roster**: one Decision covers many Students. This roster also represents **informational Decisions** — a Student can be attached to a Decision with no status change.
- Add a nullable **authorizing-decision reference to `AcademicProgressionEvent`** (`StudentActionLog` already has one). The event reference records authorization provenance; the roster records coverage.
- **Backfill** the roster from existing `StudentActionLog.decision_id` links without loss; keep those links intact.
- Implement the **soft requires-decision rule**: transitions in `{defer, resume, dropout, campus_transfer, intake_gc (→ intake_pre_uni_gc), intake_course}` need a Decision *eventually* but may be recorded **without one** (no creation-time block). `admission_deferral` and pure progression records (English-level change, IELTS) never require a Decision.
- Add a **missing-decision report** (management page) listing requires-decision transitions that still lack a Decision; attaching a Decision removes the row. Mirror the existing missing-documents report shape under academic-progression.

## Acceptance criteria

- [ ] One Decision can cover many Students; an informational Decision attaches Students with no transition.
- [ ] `AcademicProgressionEvent` can cite an authorizing Decision; `StudentActionLog` keeps its existing reference.
- [ ] Existing `StudentActionLog.decision_id` links are backfilled into the roster with no loss.
- [ ] A requires-decision transition can be recorded with no Decision attached.
- [ ] The missing-decision report lists requires-decision transitions lacking a Decision; attaching a Decision removes the row; `admission_deferral` and level/IELTS records never appear there.
- [ ] Tests at the Action seam (optional-decision, many-students roster, backfill) and Query seam (missing-decision report), following prior art `StudentDecisionBulkLinkTest`, `StudentActionEgcDeferBlockTest`, and the missing-documents query.

## Blocked by

None - can start immediately (runs in parallel with issue 01).
