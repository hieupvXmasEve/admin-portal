# S-001 Filter Lecturers By Semester And Program

## Status

implemented

## Lane

normal

## Product Contract

Staff using the lecturers directory can narrow the list to lecturers who are assigned to course offerings in a selected semester and selected teaching program/unit type, so historical lecturers from older terms do not obscure currently relevant lecturers.

## Relevant Product Docs

- `docs/product/README.md`
- `docs/product/lecturers.md`
- `docs/rules/filtering.md`
- `docs/inertiajs-vue-info.md`

## Acceptance Criteria

- The `/lectures` list includes a semester filter.
- The `/lectures` list includes a program/unit-type filter that includes EGC when EGC course offerings exist.
- Filtering returns lecturers assigned to matching course offerings in the current campus.
- Existing search, employment status, employment type, pagination, delete, impersonation, and export-filtered workflows keep working.

## Design Notes

- Commands:
- Queries: Extend the existing lecturer index query with `whereHas('courseOfferings')` constraints.
- API: Inertia page props add filter values plus semester and program/unit-type options.
- Tables: no schema changes.
- Domain rules: Semester/program filtering is based on assigned `course_offerings` for the current campus; program is represented by `units.unit_type` because EGC is modeled there.
- UI surfaces: `resources/js/pages/lectures/Index.vue`.

## Validation

| Layer       | Expected proof                                                                                          |
| ----------- | ------------------------------------------------------------------------------------------------------- |
| Unit        | `tests/Feature/Lecture/ListLecturesQueryTest.php` covers semester + EGC/unit-type filtering.            |
| Integration | Targeted query test proves current-campus assignment filtering.                                         |
| E2E         | Not planned for this narrow list-filter change.                                                         |
| Platform    | Targeted Pint, ESLint, and Prettier passed. Repo-wide `vue-tsc` still fails on pre-existing type drift. |
| Release     | Manual smoke at `/lectures` if local server is available.                                               |

## Harness Delta

None planned.

## Evidence

- `./scripts/dev.sh test tests/Feature/Lecture/ListLecturesQueryTest.php` — passed, 2 tests / 7 assertions.
- `./scripts/dev.sh composer exec pint -- --test app/Http/Controllers/Web/LectureController.php app/Http/Controllers/Web/Lectures/LectureExportController.php app/Http/Requests/Lecture/ListLecturesRequest.php app/Queries/Lecture/ListLecturesQuery.php app/Services/LectureExcelExportService.php tests/Feature/Lecture/ListLecturesQueryTest.php` — passed.
- `./scripts/dev.sh npm exec eslint resources/js/pages/lectures/Index.vue` — passed.
- `./scripts/dev.sh npm exec prettier --check resources/js/pages/lectures/Index.vue docs/product/lecturers.md docs/stories/E-lecturer-directory/S-001-filter-lecturers-by-semester-program.md docs/TEST_MATRIX.md` — passed.
- `./scripts/dev.sh npm run type-check` — failed with Node heap OOM; rerun via `./scripts/dev.sh npm exec node --max-old-space-size=4096 ./node_modules/vue-tsc/bin/vue-tsc.js --noEmit` completed but failed on repo-wide pre-existing TypeScript errors, with no errors reported for `resources/js/pages/lectures/Index.vue`.
