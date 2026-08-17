# Student cohort (khóa) config + finance cohort retirement — 2026-08-17

## Context

Placement-worklist session surfaced that the Students index "Intake" column
showed `K0` for every converted student. Investigation: `students.intake` is a
cohort number (K1, K2…), but `RegisterAdmittedStudentAction` hard-coded
`'intake' => 0` — only the legacy import batch ever had a real value (K1).
Meanwhile Finance's Fee Monitor and Collection Progress had grown a "Cohort"
filter/breakdown on the same dead column, bucketing 74 students as "Cohort 0".

## Decisions (user)

- Cohort is a real business concept — keep the column, but the number must be
  **configured explicitly** on `/student-applications/crm-mappings` next to the
  target intake, never inferred from semester ids.
- Finance reporting drops cohort entirely; show intake semester names instead.
  Student exports keep their intake column.
- **No data seed**: the post-deploy block on approvals is deliberate — a
  `manage_crm_value_mapping` holder declares the current round's khóa once on
  the CRM mapping screen (user chose not to guess the number).

## What shipped (commits 6b9740ef1, ab85927ef)

- `CrmMappingSettings::get/setIntakeCohort` (row `kind='intake_cohort'`,
  `crm_value='__default__'`); non-numeric `local_code` reads as unconfigured.
- Conversion readiness blocks with a distinct message until a cohort exists;
  `AdmittedStudentIdentity` carries `cohort`; `RegisterAdmittedStudentAction`
  stamps it (0 stays the "not declared" sentinel).
- Fee Monitor + Collection Progress: cohort filter/options/row/`by_cohort`
  removed end-to-end (queries, FormRequests, controller shells, Vue pages, AI
  MetricCatalog entries). Rows and the by-intake breakdown now show semester
  names via a FE `intakeLabelById` map.

## Review findings worth remembering

- Code review caught: pint drift in test imports, untested controller store
  path (added HTTP test), `(int) 'abc'` → 0 satisfying the readiness gate
  (guarded), stale first-entry-routing comment in StudentApplications/Index.vue.
- `app/Services/StudentApplicationService.php` is fully dead (zero refs) but
  lives in `config/migration_debt_paths.php` — deletion belongs to the
  zero-migration-debt program, so the DTO's `cohort` param stays optional.
- 24 pre-existing tests approve applications; all needed
  `setIntakeCohort(1)` in their `beforeEach` once the gate landed.

## Follow-ups

- 74 existing K0 students: backfill needs per-round khóa numbers nobody has
  declared yet — blocked on business input, not code.
- Release note: after deploy, approvals block until khóa is set on the CRM
  mapping screen (owner: whoever holds `manage_crm_value_mapping`).
