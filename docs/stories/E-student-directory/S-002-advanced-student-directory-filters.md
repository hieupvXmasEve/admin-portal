# S-002 Advanced Student Directory Filters

## Status

implemented

## Lane

normal

## Product Contract

Staff using `/students` can open an advanced-filter sheet and combine
multi-select program, specialization, status, and intake-semester filters.
Applied filters render as individually removable chips and continue to affect
pagination and filtered export within the current campus.

## Relevant Product Docs

- `docs/rules/filtering.md`
- `docs/rules/frontend.md`
- `docs/design-guidelines.md`
- `docs/inertiajs-vue-info.md`

## Acceptance Criteria

- The main toolbar remains compact: general search, pasted student codes, an
  advanced-filter trigger, active-filter count, and clear-all action.
- Staff can select multiple programs, specializations, student statuses, and
  intake semesters from a right-side sheet and apply the draft selection once.
- Specialization options are limited to selected programs when programs are
  selected. Removing a program also removes selected specializations belonging
  to that program.
- Applied advanced filters render as removable chips. Removing a chip updates
  results without clearing unrelated filters.
- Advanced filters compose with student-code and general-search filters, remain
  scoped to the current campus, survive pagination, and are included in
  filtered export.
- Invalid array payloads and invalid option identifiers are rejected by request
  validation.

## Design Notes

- Commands: none.
- Queries: extend `ListStudentsQuery` and `ExportStudentsQuery` with
  `program_ids[]`, `specialization_ids[]`, `statuses[]`, and
  `intake_semester_ids[]`.
- API: extend the existing `GET /students` and `GET /students/export` query
  contracts; keep the existing `student_ids[]` contract.
- Tables: no schema changes.
- Domain rules: intake means `students.intake_semester_id`; all filters are
  campus-scoped through the existing student base query.
- UI surfaces: add the existing `Sheet`, `Checkbox`, and `Badge` primitives to
  `resources/js/pages/students/Index.vue`. Keep draft sheet state local and
  apply it through `useDataTable.apply()` as one navigation.

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | Focused query proof for multi-select filter composition, campus scope, and filtered-export parity. |
| Integration | Feature test for `/students` array validation and Inertia props for option lists and selected filters. |
| E2E | Manual browser smoke for sheet open/apply, specialization dependency, chip removal, clear-all, pagination, and export URL preservation if local app access is available. |
| Platform | Not required. |
| Release | Targeted PHP tests, targeted Pint, frontend ESLint/Prettier, production build, and `git diff --check`; report repo-wide type-check baseline separately. |

## Harness Delta

Add this story to the Harness matrix and record validation evidence after
implementation. No architecture decision is needed because this extends the
existing student-directory list-filter contract and reuses established UI and
`useDataTable` primitives.

## Evidence

- `./scripts/dev.sh test tests/Feature/Academic/ListStudentsQueryTest.php tests/Feature/Academic/StudentDirectoryFilterTest.php`
  passed: 11 tests, 72 assertions.
- Targeted Pint, ESLint, Prettier, production build, and `git diff --check`
  passed.
- Repo-wide `vue-tsc` completed with the existing typing baseline: 505
  diagnostic lines, with no diagnostics in the changed student-directory
  frontend files.
- Automated browser smoke was not completed because the container-browser
  route still redirects to login and cannot reach the app's container-local
  Vite `localhost:5173` URL.
