# S-001 Lecturer GPA Report Validation

## Proof Strategy

Prove the calculation at query level and the protected web/export routes at
feature level. Frontend proof is targeted lint/format/type checking for touched
Vue and TypeScript files, with repo-wide type drift documented if it remains.

## Test Plan

| Layer       | Cases                                                                                                            |
| ----------- | ---------------------------------------------------------------------------------------------------------------- |
| Unit        | Query averages each class's Lecturer Evaluation rating first, then averages those class scores for the lecturer. |
| Integration | Web route defaults to active semester, respects current campus, and denies users without the chosen permission.  |
| Integration | Export route downloads an Excel workbook with lecturer rows and no student identifiers.                          |
| E2E         | Not planned for this slice unless local browser auth is already available.                                       |
| Platform    | No platform-specific proof required.                                                                             |
| Logs/Audit  | Export log contains report metadata only, not raw survey answers or student identifiers.                         |

## Fixtures

- Active semester and non-active semester.
- Current campus and another campus.
- One lecturer teaching two units with multiple sections.
- Course-scoped survey targets for those offerings.
- Lecturer Evaluation rating questions with deterministic numeric answers.
- Uneven response counts per section to prove the final GPA is class-weighted,
  not response-weighted.
- Non-Lecturer Evaluation rating questions to prove they are excluded.
- User permission mock for allowed and forbidden cases.

## Commands

```text
./scripts/dev.sh test tests/Feature/Lecture/LecturerGpaReportTest.php
./scripts/dev.sh composer exec pint -- --test <touched PHP files>
./scripts/dev.sh npm exec eslint <touched Vue/TS files>
./scripts/dev.sh npm exec prettier --check <touched Vue/TS/docs files>
./scripts/dev.sh npm exec node --max-old-space-size=4096 ./node_modules/vue-tsc/bin/vue-tsc.js --noEmit
```

## Acceptance Evidence

- `./scripts/dev.sh artisan route:list --name=lectures.lecturers-gpa`: passed,
  showing index and export routes.
- `./scripts/dev.sh test tests/Feature/Lecture/LecturerGpaReportTest.php`:
  passed, 3 tests / 43 assertions.
- Targeted Pint on touched PHP files: passed.
- Targeted ESLint on touched Vue/TS files: passed.
- Targeted Prettier check on touched Vue/TS/docs files: passed.
- `git diff --check`: passed.
- Repo-wide `vue-tsc --noEmit`: still fails on existing frontend typing drift;
  filtered log has no matches for the Lecturer GPA page, Survey Results
  aggregate link, route constants/helpers, or form target type changes.
