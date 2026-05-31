# Validation

## Proof Strategy

Prove that payment reconciliation stays idempotent, safe cases sync
automatically, and unsafe class-removal cases are surfaced without silent
reenrollment.

## Test Plan

| Layer         | Cases                                                                                                           |
| ------------- | --------------------------------------------------------------------------------------------------------------- |
| Unit          | Derived reconciliation state maps status, charge, and course-registration combinations correctly.               |
| Integration   | Fully paid `payment_pending` registration transitions to paid and waits when no class registration exists.      |
| Integration   | Fully paid `paid` registration with no link remains waiting until staff adds a class registration.              |
| Integration   | Existing active target `CourseRegistration` is reused and marked retake.                                        |
| Integration   | Creating a matching active `CourseRegistration` after payment links the paid retake registration.               |
| Integration   | Operation-state filters use the derived payment/class state instead of the raw retake registration status.      |
| Integration   | Summary counts stay scoped to the page filters but independent from the selected operation-state tab.           |
| Integration   | Retake list filter props keep scalar defaults and URL query params so Vue does not read native array methods.   |
| Integration   | `enrolled` registration with dropped/withdrawn/missing linked class opens an exception and does not auto-readd. |
| Integration   | Manual `readd_to_class` creates or links a roster-active registration and resolves the exception with reason.   |
| Integration   | Manual `hold` keeps the exception visible but acknowledged.                                                     |
| Integration   | Reconciliation command is idempotent across repeated runs.                                                      |
| Frontend      | Retake list renders separate payment and class chips, quick filters, and row actions without text overflow.     |
| Authorization | Users without manage permission cannot resolve enrollment exceptions.                                           |
| Logs/Audit    | Resolution metadata includes acting user, reason, old/new registration ids, and action.                         |

## Fixtures

- Student with fully paid retake charge and no `course_registration_id`.
- Student with fully paid retake charge and existing active target
  `CourseRegistration`.
- Student with `enrolled` retake registration whose linked course registration is
  `dropped`.
- Student with `enrolled` retake registration whose linked course registration is
  missing.
- Student with linked registration for the wrong offering.

## Commands

```text
./scripts/dev.sh test tests/Feature/Academic/RetakeCourse
./scripts/dev.sh test tests/Feature/Finance/Dng/ProcessDngWebhookJobTest.php --filter=retake
./scripts/dev.sh composer exec pint -- --dirty
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
```

## Acceptance Evidence

- `./scripts/dev.sh test tests/Feature/Academic/RetakeCourse/RetakeCourseRegistrationControllerTest.php tests/Feature/Academic/RetakeCourse/ListRetakeCourseRegistrationsQueryTest.php tests/Feature/Academic/RetakeCourse/AutoEnrollRetakeCourseActionTest.php tests/Feature/Academic/RetakeCourse/SyncPaidRetakeRegistrationsActionTest.php`
  passed: 15 tests, 120 assertions.
- `./scripts/dev.sh test tests/Feature/Finance/Dng/ProcessDngWebhookJobTest.php --filter=retake`
  passed: 2 tests, 6 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty` passed.
- `./scripts/dev.sh npm exec eslint -- resources/js/pages/Academic/RetakeCourse/Index.vue`
  passed.
- `./scripts/dev.sh npm exec eslint -- resources/js/composables/useDataTable.ts resources/js/pages/Academic/RetakeCourse/Index.vue`
  passed after switching `useDataTable` filter navigation to explicit URL query
  params.
- `./scripts/dev.sh npm exec prettier --check resources/js/composables/useDataTable.ts resources/js/pages/Academic/RetakeCourse/Index.vue`
  passed.
- `git diff --check -- app/Modules/Academic/Http/Web/RetakeCourseRegistrationController.php app/Modules/Academic/Queries/ListRetakeCourseRegistrationsQuery.php resources/js/composables/useDataTable.ts resources/js/pages/Academic/RetakeCourse/Index.vue tests/Feature/Academic/RetakeCourse/RetakeCourseRegistrationControllerTest.php tests/Feature/Academic/RetakeCourse/ListRetakeCourseRegistrationsQueryTest.php`
  passed.
- `./scripts/dev.sh artisan route:list --name=academic.retake-course` showed
  the new `academic.retake-course.sync` route.
- `./scripts/dev.sh npm run build` passed with the existing large-chunk warning.
- `./scripts/dev.sh npm exec env NODE_OPTIONS=--max-old-space-size=4096 vue-tsc --noEmit`
  still fails on existing repo-wide type drift. No diagnostics remain for
  `resources/js/pages/Academic/RetakeCourse/Index.vue`; the remaining
  `Academic/RetakeCourse/Create.vue` diagnostic pre-existed this story.
