# Decision covers many students and authorizes both lifecycle streams

**Context.** A Decision was previously modelled as a nullable `decision_id` hanging off a single `StudentActionLog`. That shape cannot express three real needs: (a) one formal decision (quyết định) covers **many** students at once; (b) some transitions that require a decision are **progression events** (into `intake_pre_uni_gc` / `intake_course`), not status actions, so an action-only link misses them; (c) some decisions are **purely informational** — they attach to students without changing any status.

**Decision.** Model **Decision** as a first-class governance document:

- **Decision ↔ Student is many-to-many** (a roster pivot). One decision covers many students; this roster is also what makes purely-informational decisions representable.
- **Lifecycle events in both streams carry an authorizing decision reference.** `StudentActionLog` keeps its `decision_id`; `AcademicProgressionEvent` gains the same nullable reference. The event link records *authorization provenance* ("this transition was authorized by that decision").
- **A defined "requires-decision" set needs an authorizing Decision, but the Decision may be attached after the transition — it is NOT a creation-time block.** The set: academic defer, resume, dropout, campus transfer, transition into `intake_pre_uni_gc` (intake_gc), and transition into `intake_course`. The transition can be recorded immediately (status changes first; the signed quyết định is often entered later); a **missing-decision report** then surfaces transitions in this set that still lack a Decision so staff can backfill. This mirrors the existing missing-documents report. Admission deferral and pure progression records (English-level change, IELTS) never require a Decision.
- **Informational decisions** are a roster with no authorized event.

**Consequences.**

- The pivot is the document's roster (source of truth for "which students this decision covers"); the event's `decision_id` is the source of truth for "which transition this decision authorized." Both exist on purpose and are not redundant.
- Creating an authorized event for a student should also ensure that student is on the decision's roster.
- The per-student hub shows, for one student, both the decisions covering them (from the roster) and which action/transition each one authorized.
