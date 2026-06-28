# Program / Intake matching by canonical code

Status: done

## Parent

`.scratch/redesign-student-applications/PRD.md` · Decision: `docs/adr/0005-program-intake-matching-by-canonical-code.md`

## What to build

Make an Application's admission intent (**campus**, **Intended Program**, **Intake**) resolve unambiguously to the real `Program` / `Semester` / `CurriculumVersion` a Student is created under on Approve, so conversion is never silently wrong. Today these arrive as free text and are translated at Approve time through a hardcoded name→code map (`ProgramMappingService::PROGRAM_CODE_MAPPING`) that is already broken for real data (`"CS"`, `"IT"`).

End-to-end behavior:

- **Both channels send canonical codes.** `campus_code` = `Campus.code`, `intended_program` = `Program.code`, `intake` = `Semester.code`. Columns stay **string codes, BE-validated (no FK / no DB enum)** — consistent with the existing `campus_code` pattern.
- **Validate at the write boundary.** `IngestApplicationRequest` and the manual `Store/UpdateStudentApplicationRequest` make the three codes **`required` + `exists`** (`exists:campuses,code`, `exists:programs,code`, `exists:semesters,code`). Unknown/missing → `422` in the `ApiResponse` envelope (API) or a form error (web). `intended_specialization` is `exists:specializations,code` **only when supplied** (specialization is deferred — see ADR-0005).
- **Manual form → dropdowns.** `create.vue` / `edit.vue`: Program and Intake become selects that **show the name but submit the code** (campus is already a dropdown). No more free-text program/intake.
- **Curriculum resolved at Approve, exactly-one-or-block.** Rewrite the approve-time resolution: `Program::where('code', $intended_program)`, then resolve `CurriculumVersion` from `(program_id, semester_id [, specialization_id])` — **exactly one** match or the Approve is blocked with a clear staff message ("curriculum could not be uniquely determined"); replace the silent `->first()`. `intake_semester_id` comes from the matched `Semester`; `students.specialization_id` comes from the resolved Curriculum Version. **Delete `PROGRAM_CODE_MAPPING`** and the name→code translation.
- **One-time backfill of existing `intended_program`** (all rows currently store labels): converted rows (a linked Student exists — all 229 do, all with a `program_id`) take the Student's **actual `Program.code`**; the 12 unconverted `pending` rows map the known labels (`Công nghệ bán dẫn→SEMI`, `Trí tuệ nhân tạo→AI`, `Tài chính→FIN`, `Quản trị kinh doanh→BA`). `campus_code` / `intake` are already valid codes — no change. Provide a dry-run.

## Acceptance criteria

- [x] Ingestion + manual create/edit reject a missing/unknown `campus_code`/`intended_program`/`intake` with `422` / form error; valid codes pass. Same rules on both channels.
- [x] Manual create/edit submit Program and Intake as codes via dropdowns (name shown, code submitted).
- [x] Approve maps `intended_program` (code) → `Program`, resolves a single `CurriculumVersion` from program+semester, sets the Student's `program_id` / `curriculum_version_id` / `intake_semester_id` / `specialization_id` correctly.
- [x] Approve is **blocked with a clear message** (no Student created) when the curriculum resolves to zero or many versions.
- [x] `ProgramMappingService::PROGRAM_CODE_MAPPING` and the name→code translation are removed; resolution is by `Program.code`.
- [x] Backfill (`applications:normalize-program`, dry-run + `--apply`) normalizes `intended_program`: converted rows → Student's real `Program.code`; pending rows → mapped codes. Idempotent.
- [x] Feature tests cover: ingestion `422` on bad code, manual `422` parity, approve success, approve-blocked on ambiguous curriculum, and the backfill.

## Verified against real dev data

`applications:normalize-program` dry-run (241 rows): 229 normalized from linked Student, 12 mapped from known label, 0 unchanged, **0 left as a non-code** — every row ends on a canonical `Program.code`. Each program has exactly one Curriculum Version for `FALL2025`, so all 12 pending resolve on Approve. Run order in production: `applications:normalize-program --apply` (any time), then the new code-based validation/resolution applies to all new writes and approvals.

## Out of scope

- Specialization as a required/matching field (deferred; validated only when supplied — ADR-0005).
- Changing the `intended_program` / `intake` columns to FKs or DB enums.

## Blocked by

- `02-application-lifecycle-core` (approve path) — done.
- `08-crm-ingestion-full` (ingestion request) — done.
