# Yearly KPI Data Model Assessment

Date: 2026-02-25
Scope: Yearly columns `Year intake`, `Intake Pre.Uni (GC)`, `INTAKE COURSE`, `DEFER`, `DO`, `CHANGE CAMPUS`, `GRADUATED`, `BB2 (waiting for graduation)`, `PENDING`, `DO-Transfer`, `Pending RATE`, `DF RATE`, `DO RATE`, `GRADUATED RATE`, `NE`.

## System boundaries

- In-scope source-of-truth entities:
  - `students` snapshot status + intake anchors (`intake`, `intake_gc`, `intake_major`, `status`) and campus/program ownership.
  - `student_action_logs` event ledger for defer/dropout/campus-transfer/admission-deferral with semester references.
  - `semesters` canonical time dimension (`id`, `code`, `start_date`, `end_date`) for semester→year derivation.
  - `enrollments` and `course_registrations` for activity/engagement presence.
- Out-of-scope for this KPI table (unless business explicitly asks): GPA, grading, finance ledgers, room/event modules.

## Support status by requested column

- Supported now (direct or safely derivable):
  - `Year intake`: derive from `students.intake` (cohort year) or `YEAR(semesters.start_date)` via `students.intake_gc/intake_major/intake_semester_id`.
  - `Intake Pre.Uni (GC)`: count students by `status='intake_pre_uni_gc'` or intake anchor `intake_gc` by year.
  - `INTAKE COURSE`: count students by `status='intake_course'`/`status='intake_major'` (requires rule choice).
  - `DEFER`: count by `student_action_logs.action_type='ACADEMIC_DEFER'` (event) or `students.status='deferred'` (snapshot).
  - `DO`: count by `student_action_logs.action_type='ACADEMIC_DROPOUT'` or `students.status='dropout'`.
  - `CHANGE CAMPUS`: count by `student_action_logs.action_type='CAMPUS_TRANSFER'`.
  - `PENDING`: count by `students.status='pending'`.
  - `DO-Transfer`: count by `students.status='dropout_transfer'`.
- Partially supported / ambiguous mapping:
  - `GRADUATED`: `students.status='graduated'` exists, but no explicit `graduated_at` date column to place graduation into an exact year unless using action/event proxies.
  - `BB2 (waiting for graduation)`: no explicit status or enum value `bb2`; likely needs mapping to `GraduationApplication` states or a new student status.
  - `NE`: no canonical field; likely “Not Enrolled” and can be derived only by rule (e.g., no enrollment + no course registration in selected year).
- Rates (`Pending RATE`, `DF RATE`, `DO RATE`, `GRADUATED RATE`): computable only after denominator is standardized.

## Keys and grain (semester/year)

- Stable keys available:
  - Student key: `students.id`.
  - Semester key: `semesters.id` across `students.intake_gc`, `students.intake_major`, `student_action_logs.*_semester_id`, `enrollments.semester_id`, `course_registrations.semester_id`.
- Year key options:
  - Cohort year: `students.intake` (int).
  - Calendar year: `YEAR(semesters.start_date)`.
- Current risk:
  - Mixed time grains in current model (snapshot status vs event logs vs semester membership) can produce double-counts or wrong year placement without a strict semantic contract.

## Missing mappings / blockers

- Missing canonical dictionary for metric semantics:
  - event-based vs end-of-year snapshot for each metric.
  - denominator definition for all rate columns.
- Missing `graduated_at` (or equivalent event) for precise graduation year slicing.
- Missing explicit representation of `BB2`.
- Missing formal definition of `NE`.
- Intake-course split ambiguity:
  - legacy `intake_course` field and newer `intake_major` can diverge unless migration invariant is enforced.

## Minimal model contract to make table reliable

- Add metric definition contract (single doc + SQL view contract):
  - `metric_name`, `source_table`, `predicate`, `time_key`, `denominator`.
- Add one of:
  - `students.graduated_at` date, or
  - mandatory `StudentActionType::GRADUATED` event in `student_action_logs`.
- Define `BB2` mapping (status enum or deterministic rule from `graduation_applications`).
- Define `NE` rule using `enrollments`/`course_registrations` for selected year.

## Verdict

- Current model can support ~10/15 columns with explicit rule choices.
- It cannot produce a defensible yearly KPI table end-to-end until `BB2`, `NE`, graduation-year timestamp, and rate denominator mappings are formally defined.

## Unresolved questions

- Is `Year intake` based on cohort intake (`students.intake`) or event year (`semesters.start_date`)?
- Should `GRADUATED` be event-year or current-snapshot-by-cohort?
- Exact business meaning of `BB2` and `NE`?
- Rate denominator: cohort size, active population, or yearly affected students?
