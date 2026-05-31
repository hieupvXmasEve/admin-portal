# S-001 Filter Students By Multiple Codes

## Status

implemented

## Lane

normal

## Product Contract

Staff using `/students` can paste or enter multiple student codes into a
dedicated filter and receive the matching students from the current campus.
The existing general search, program, status, sort, pagination, clear-filter,
and filtered-export behavior continue to work.

## Relevant Product Docs

- `docs/rules/filtering.md`
- `docs/rules/frontend.md`
- `docs/design-guidelines.md`
- `docs/inertiajs-vue-info.md`

## Acceptance Criteria

- Staff can paste student codes separated by whitespace, commas, semicolons, or
  new lines into the student-code filter.
- Entered codes render as removable tags, with blank and duplicate values
  removed before navigation.
- Student-code filtering uses exact matches, remains scoped to the current
  campus, and composes with the existing general search, program, and status
  filters.
- Pagination links and filtered export preserve the selected student codes.
- Invalid filter payloads are rejected by request validation.

## Design Notes

- Commands: none.
- Queries: extend the existing student list query and `ExportStudentsQuery`.
- API: add optional `student_ids[]` query parameters to the existing
  `GET /students` and `GET /students/export` routes.
- Tables: no schema changes.
- Domain rules: student codes are campus-scoped exact identifiers; the general
  search remains a fuzzy search across code, name, and email.
- UI surfaces: migrate `resources/js/pages/students/Index.vue` from the frozen
  `useInertiaFilters` composable to `useDataTable`, then add the existing
  `TagsInput` primitive for multi-code filtering.

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | Focused query proof for exact multi-code filtering and campus scope. |
| Integration | Feature test for `/students` validation, filter composition, and Inertia filter props; export query test for filtered export parity. |
| E2E | Manual browser smoke for paste, tag removal, clear, pagination, and export URL preservation if local app access is available. |
| Platform | Not required. |
| Release | Targeted PHP tests, targeted Pint, frontend type-check/lint/format checks where available, and `git diff --check`. |

## Harness Delta

Add this story to the Harness matrix and record validation evidence after
implementation. No architecture decision is needed because the change extends
the existing list-filter contract with the repository's standard
`useDataTable` pattern.

## Evidence

- `./scripts/dev.sh test tests/Feature/Academic/ListStudentsQueryTest.php tests/Feature/Academic/StudentDirectoryFilterTest.php`
  passed: 6 tests, 25 assertions.
- Targeted Pint, ESLint, Prettier, and `git diff --check` passed.
- Repo-wide `vue-tsc` completed with a 4 GB Node heap and still reports
  pre-existing typing drift outside the changed student-directory frontend
  files.
- Automated browser smoke could not be completed from the container browser:
  the app redirects to login and its Vite URL points at container-local
  `localhost:5173`.
