# S-001 Repair Curriculum Version Unit Add/Edit

## Status

implemented

## Lane

normal

## Product Contract

Staff with curriculum-unit create/edit/delete permissions can temporarily manage
units from the curriculum version Units summary tab regardless of the effective
semester date UI lock. Backend validation and duplicate checks remain unchanged.

## Relevant Product Docs

- `docs/rules/backend.md`
- `docs/rules/frontend.md`
- `docs/inertiajs-vue-info.md`

## Portal Impact

none

## Acceptance Criteria

- The `canEditOrDelete` computed block is commented for temporary restoration
  later.
- Add, edit, and delete buttons no longer use `canEditOrDelete`.
- Permission checks still control whether create/edit/delete buttons are shown.
- Duplicate units in the same curriculum version remain rejected by existing API
  validation.

## Design Notes

- Commands:
- Queries:
- API: `api.curriculum-units.store`, `api.curriculum-units.update`
- Tables: `curriculum_versions`, `curriculum_units`, `units`, `semesters`
- Domain rules: one unit per curriculum version; the effective-semester UI lock
  is temporarily bypassed for buttons.
- UI surfaces: `resources/js/pages/curriculum-versions/summary/Units.vue`

## Validation

| Layer       | Expected proof                                                      |
| ----------- | ------------------------------------------------------------------- |
| Unit        | Not expected for this controller-level fix.                         |
| Integration | Feature tests for create/update validation and duplicate rejection. |
| E2E         | Not expected unless browser access is available.                    |
| Platform    | Targeted format/lint for touched files.                             |
| Release     | Not expected.                                                       |

## Harness Delta

No harness changes expected.

## Evidence

- `docker exec -w /app swinx-vite-dev pnpm exec eslint resources/js/pages/curriculum-versions/summary/Units.vue`
  passed.
- `docker exec -w /app swinx-vite-dev pnpm exec prettier --check docs/stories/E-curriculum-version-units/S-001-repair-summary-unit-add-edit.md`
  passed.
- `git diff --check -- resources/js/pages/curriculum-versions/summary/Units.vue docs/stories/E-curriculum-version-units/S-001-repair-summary-unit-add-edit.md`
  passed.
- `./scripts/dev.sh npm exec prettier -- --check ...` and app-backed checks
  could not run because `swinx-app-dev` is restarting on an existing migration
  drift: table `academic_warning_settings` already exists.
- Prettier check for `resources/js/pages/curriculum-versions/summary/Units.vue`
  remains failing due broad pre-existing file formatting drift; auto-formatting
  would rewrite about 413 diff lines outside this temporary button change.
