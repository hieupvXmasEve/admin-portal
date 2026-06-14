# Academic Grading Rule Engine Story Set

Source reference: `docs/features/academic/Grading_Schemes_Metropolia.md`

This epic completes a configurable grading-rule capability for schools that
share the Swinx repository but run separate databases. The feature must support
Metropolia-style grading without changing existing weighted-percentage behavior
for deployments that do not configure a custom scheme.

## Umbrella Story

| Story id | Scope | Contract |
| --- | --- | --- |
| `GRADING-INIT-001-academic-grading-rule-engine` | Complete feature coordination: backend engine, scheme authoring path, operator UX, portal/API contract, recalculation, and rollout validation | This file |

## Story Order

| Order | Story id | Story packet | Main scope | Depends on |
| --- | --- | --- | --- | --- |
| 1 | `S-001-syllabus-campus-grading-rule-engine` | `S-001-syllabus-campus-grading-rule-engine/` | Backend/configuration-first slice: `grading_scheme` storage, default calculator, `metropolia_v1`, aggregation/finalization integration, GPA source config, Canvas boundary, docs | None |
| 2 | `S-002-metropolia-scheme-pack-and-mapping` | `S-002-metropolia-scheme-pack-and-mapping/` | Convert `Grading_Schemes_Metropolia.md` into concrete JSON scheme fixtures, map component codes per syllabus, and add deterministic import/seed guidance per school database | 001 |
| 3 | `S-003-syllabus-grading-scheme-admin-ui` | `S-003-syllabus-grading-scheme-admin-ui/` | Add guarded admin UI to view/edit/validate `grading_scheme` on syllabus templates, with preview calculation for sample scores | 001, 002 |
| 4 | `S-004-grade-breakdown-api-and-portal-display` | `S-004-grade-breakdown-api-and-portal-display/` | Expose safe grade breakdown fields where needed and update affected student/lecturer portal types/views without changing unrelated portal behavior | 001, 003 if UI copy depends on scheme labels |
| 5 | `S-005-grading-recalculation-ops` | `S-005-grading-recalculation-ops/` | Add a controlled recalculation command/workflow for existing academic records, with dry-run, per-course targeting, audit evidence, and rollback guidance | 001, 002 |
| 6 | `S-006-grading-rollout-validation` | `S-006-grading-rollout-validation/` | Validate a full Metropolia pilot database: scheme coverage, sample student outcomes, Canvas boundary behavior, GPA source, portal display, and operator docs | 002, 003, 004, 005 |

## Detailed Plans

| Story id | Plan |
| --- | --- |
| `S-001-syllabus-campus-grading-rule-engine` | `docs/superpowers/plans/2026-06-14-syllabus-campus-grading-rule-engine.md` |
| `S-002-metropolia-scheme-pack-and-mapping` | `docs/superpowers/plans/2026-06-15-metropolia-scheme-pack-and-mapping.md` |
| `S-003-syllabus-grading-scheme-admin-ui` | `docs/superpowers/plans/2026-06-15-syllabus-grading-scheme-admin-ui.md` |
| `S-004-grade-breakdown-api-and-portal-display` | `docs/superpowers/plans/2026-06-15-grade-breakdown-api-and-portal-display.md` |
| `S-005-grading-recalculation-ops` | `docs/superpowers/plans/2026-06-15-grading-recalculation-ops.md` |
| `S-006-grading-rollout-validation` | `docs/superpowers/plans/2026-06-15-grading-rollout-validation.md` |

## Feature Exit Criteria

- Default weighted-percentage courses produce the same results as before.
- Metropolia-configured syllabi calculate numeric 0-5 and pass/fail outcomes
  from declarative schemes.
- Final grades are auditable through `academic_records.grade_breakdown`.
- Course finalization, course registrations, credits earned, and GPA use the
  correct source for the deployment database.
- Canvas-synced custom-scheme courses do not let Canvas total grades overwrite
  local rule-engine results.
- Academic admins have an approved way to configure and validate schemes.
- Student and lecturer portals display the resulting grades without contract
  drift.
- Historical recalculation is controlled, targeted, and evidence-backed.

## Shared Rules

- Portal impact is `both` once API response shape or portal display changes.
  Run `./scripts/portal-status.sh` before touching `FE/student-nuxt` or
  `FE/lecturer-nuxt`.
- Do not introduce shared-database tenant isolation in this epic. Schools share
  code, not a database, so scheme selection is per deployment database and
  syllabus template.
- Keep `grading_scheme = null` as the compatibility path.
- Do not build the admin rule editor before the backend calculation contract is
  tested.
- Do not run historical recalculation until target courses, expected outcomes,
  and rollback evidence are documented.
- Use Docker wrappers for validation commands.
