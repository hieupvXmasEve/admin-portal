# Validation

## Proof Strategy

Cover the changed query behavior with feature tests for index visibility, department filter behavior, current-campus scope, detail visibility, and department option restriction.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Not applicable. |
| Integration | Inbox index shows current-campus query responses from both HQ and ACA even when the actor belongs to no department; department filter narrows by selected department; other-campus responses stay hidden; detail route opens a current-campus query from another department. |
| E2E | Not required for this scoped controller/UI prop change. |
| Platform | Not applicable. |
| Performance | Existing pagination remains in place. |
| Logs/Audit | No new logs. |

## Fixtures

Feature tests create two campuses, HQ/ACA/other departments, query forms, form targets, students, responses, and an authenticated staff user with route permissions.

## Commands

```text
./scripts/dev.sh test tests/Feature/Form/AdminQueryInboxTest.php
./scripts/dev.sh npm run type-check
```

## Acceptance Evidence

- `./scripts/dev.sh test tests/Feature/Form/AdminQueryInboxTest.php` passed: 5 tests, 44 assertions.
- `./scripts/dev.sh composer exec pint -- --test app/Http/Controllers/Web/Admin/QueryController.php tests/Feature/Form/AdminQueryInboxTest.php` passed.
- `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Forms/Queries/Inbox.vue` passed.
- `./scripts/dev.sh npm exec -- eslint resources/js/pages/Forms/Queries/Inbox.vue` passed.
- `./scripts/dev.sh npm run type-check` failed with a Node heap OOM at the default heap limit. Retrying with `NODE_OPTIONS=--max-old-space-size=4096` completed far enough to report existing repo-wide TypeScript errors across unrelated frontend files, including `resources/js/app.ts`, dashboard/chart components, form-engine types, lecturer pages, room pages, unit pages, and other baseline surfaces.
