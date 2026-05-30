# S-001 Course Survey Class Result Download

## Status

implemented

## Lane

normal

## Product Contract

Admin/staff users who can view aggregate survey results can download the same aggregate course survey results for an individual class from the existing aggregate result page.

## Relevant Product Docs

- `docs/project-overview-pdr.md`
- `docs/code-standards.md`
- `docs/design-guidelines.md`
- `docs/inertiajs-vue-info.md`

## Acceptance Criteria

- The aggregate result page for a course survey run exposes a download action for that class.
- The download route uses the same aggregate-result authorization as the existing aggregate page.
- The exported file includes course/run metadata, overall KPIs, per-section rating summaries, and per-question aggregate results without adding raw student identifiers.
- The UI uses route helpers and existing button/icon primitives.

## Design Notes

- Commands:
- Queries:
- API: add a GET download endpoint under `forms.admin.results`.
- Tables: no schema changes expected.
- Domain rules: export the same aggregate data already visible on the admin aggregate page.
- UI surfaces: `resources/js/pages/Forms/Admin/results/Aggregate.vue`.

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | Export data mapping covered through download fake assertion. |
| Integration | Feature test for authorized download response and route middleware. |
| E2E | Not required for this slice. |
| Platform | No platform-specific proof required for this slice; targeted Vue lint/format checks cover the touched page. |
| Release | Targeted Laravel/PHP checks plus documented repo-wide type-check gap. |

## Harness Delta

No harness changes planned.

## Evidence

- `./scripts/dev.sh artisan route:list --name=forms.admin.results` — passed; download route registered.
- `./scripts/dev.sh artisan test tests/Feature/Form/SurveyResultDownloadTest.php` — passed, 2 tests / 12 assertions.
- `./scripts/dev.sh composer exec pint -- --test app/Exports/SurveyRunAggregateExport.php app/Http/Controllers/Web/Admin/SurveyResultController.php tests/Feature/Form/SurveyResultDownloadTest.php routes/web/forms.php` — passed.
- `./scripts/dev.sh npm exec eslint resources/js/pages/Forms/Admin/results/Aggregate.vue` — passed.
- `./scripts/dev.sh npm exec prettier --check resources/js/pages/Forms/Admin/results/Aggregate.vue` — passed.
- `./scripts/dev.sh npm exec node --max-old-space-size=4096 ./node_modules/vue-tsc/bin/vue-tsc.js --noEmit` — failed on pre-existing repo-wide TypeScript errors outside this slice, including `resources/js/app.ts`, missing `TeachingAssignment` type modules, dashboard chart modules, and other legacy typing drift.
