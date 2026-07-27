---
title: Canvas Sync Operations
status: current
type: runbook
scope: Canvas synchronization and recovery operations
last_verified: "2026-07-25"
owner: Academic Module
audience:
  - academic administrators
  - support engineers
---

# Canvas Sync Operations

## Preconditions

Before a sync:

- the integration is active and has a valid access token or refresh token;
- the Canvas course catalog has been synced;
- the Canvas course is mapped to the intended local course offering;
- the offering has a syllabus template;
- Canvas SIS IDs or login IDs match local student codes;
- queue and application logs are available for diagnosis.

Use the named routes registered in `app/Modules/Academic/routes/web.php`
(`admin.canvas.*`); do not build literal URLs in new UI code.

## Course catalog and mapping

1. From Canvas Integrations, authorize the active integration if needed.
2. Run course sync for that integration.
3. From Canvas Courses, filter by mapping state and map the Canvas course to the
   local offering for the selected campus.
4. Confirm the local semester, unit, and section before saving the mapping.

Ignoring a Canvas course and unmapping a course are explicit alternatives.
Neither operation should be used to conceal an ambiguous mapping.

## Assignment structure sync

1. Open the mapped Canvas course.
2. Load the assignment sync summary.
3. Select the Canvas assignment groups that should become the local assessment
   structure.
4. Confirm their weights total 100 percent.
5. Apply assignment sync.

Important: applying selected groups replaces all assessment components on the
offering's Canvas-cloned syllabus template. The service also skips unpublished
Canvas assignments. If the offering is already marked Canvas-synced but has no
syllabus template, stop and repair that inconsistency before retrying.

## Grade sync

The preferred staff flow is the course offering Scores tab:

1. select the students to inspect;
2. run the Canvas sync preview;
3. review changed cells, course totals, and unmatched students;
4. apply the sync for the selected student IDs.

The apply endpoint fetches Canvas again; it does not trust values returned by
the preview. This prevents a stale preview payload from becoming a write
source.

Owners:

- `resources/js/pages/course-offerings/components/tabs/ScoresTab.vue`
- `app/Modules/Academic/Http/Web/Canvas/PreviewCanvasGradeSyncController.php`
- `app/Modules/Academic/Http/Web/Canvas/SyncCanvasGradeController.php`
- `app/Modules/Academic/Delivery/Support/CanvasGradeSyncService.php`

The legacy Canvas courses page also exposes whole-course grade sync. Prefer the
preview-first Scores-tab workflow when an operator needs to review changes or
limit the affected roster.

## Scheduled sync

`routes/console.php` schedules `academic-records:sync` daily. The scheduler and
queue workers must be running in production. The command owns its own course
chunking and deactivates integrations after persistent token failures.

For local command discovery or a manual run, use the Docker wrapper:

```bash
./scripts/dev.sh artisan help academic-records:sync
./scripts/dev.sh artisan academic-records:sync
```

## Troubleshooting

### Authorization or refresh fails

- Confirm the developer-key callback exactly matches the generated HTTPS
  callback.
- Check the encrypted credentials can still be decrypted with the current
  `APP_KEY`.
- Inspect `canvas_integrations.sync_error`.
- Authentication failures and repeated refresh timeouts can deactivate the
  integration; reauthorize only after fixing the cause.

### No courses appear

- Confirm an active integration exists.
- Run course catalog sync.
- Check the integration's `last_sync_at`, `sync_status`, and `sync_error`.
- Verify Canvas granted the configured scopes.

### Assignment sync is rejected

- Confirm a local offering and syllabus are present.
- Confirm the selected Canvas group weights total exactly 100 percent within
  the service tolerance.
- Re-read the summary before retrying; a successful apply replaces local
  components on the Canvas clone.

### Students are skipped

Compare the local student code with Canvas `sis_user_id` and `login_id`, after
trimming and case normalization. Do not work around a roster mismatch by
matching display names.

### Grade totals look wrong

Inspect the Canvas submission, local component/detail mapping, and the
syllabus's `grading_scheme` together. With a custom scheme, the custom
calculator owns the final result even when Canvas has a course total.

## Focused validation

```bash
./scripts/dev.sh artisan test --compact tests/Feature/Canvas
./scripts/dev.sh artisan test --compact \
  tests/Feature/CourseOffering/CanvasGradeSyncTest.php
./scripts/dev.sh artisan test --compact \
  tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php
```
