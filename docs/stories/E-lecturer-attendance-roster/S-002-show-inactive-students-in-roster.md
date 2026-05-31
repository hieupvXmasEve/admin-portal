# Story: Show Inactive Students In Lecturer Rosters

## Requirement

Lecturers still need to see students who entered a class and later became DE
(`deferred`, `dropout`, `dropout_transfer`, or `inactive`) so they can control
the full class history. These students must appear in course student lists and
attendance screens with a clear inactive/DE status indicator.

## Target Behavior

- Lecturer course student list includes both active and inactive roster
  students.
- Lecturer attendance screen includes inactive roster students.
- Inactive roster students are visually marked as deferred/dropout/inactive and
  are not treated as active attendees for expected/actual attendance counts.
- Historical registrations and attendance rows remain intact.

## Portal Impact

lecturer

## Affected Surfaces

- `GET /api/v1/lecturer/courses/{courseOffering}/students`
- `GET /api/v1/lecturer/attendance/sessions/{session}`
- Lecturer Nuxt course students tab.
- Lecturer Nuxt class session attendance list.

## Validation

- Backend feature tests cover active and inactive roster students appearing in
  lecturer responses with active counts unchanged.
- Lecturer FE typecheck passes after type/component updates.
- API docs describe inactive roster metadata.

## Evidence

- `./scripts/dev.sh test tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php` passed: 4 tests, 37 assertions.
- `./scripts/dev.sh composer exec pint -- <touched PHP files>` passed and fixed formatting.
- `cd FE/lecturer-nuxt && pnpm typecheck` passed with existing Nuxt duplicate import/component warnings.
- `cd FE/lecturer-nuxt && pnpm build` passed with existing Nuxt/browser data warnings.
- `cd FE/lecturer-nuxt && pnpm exec eslint <touched FE files>` passed with a baseline-browser-mapping age warning.
- Follow-up UI split: class session attendance now renders active/markable students in the main attendance list and DE/dropout/inactive students in a separate locked section. `cd FE/lecturer-nuxt && pnpm exec eslint app/components/attendance/StudentAttendanceList.vue shared/types/attendance.ts`, `pnpm typecheck`, `pnpm build`, and `git -C FE/lecturer-nuxt diff --check` passed with existing Nuxt warnings.
- Full `cd FE/lecturer-nuxt && pnpm lint` is blocked by the existing `pnpm/json-enforce-catalog` rule because this nested repo has no `pnpm-workspace.yaml`.
- `git diff --check` passed for Swinx and `FE/lecturer-nuxt`.
