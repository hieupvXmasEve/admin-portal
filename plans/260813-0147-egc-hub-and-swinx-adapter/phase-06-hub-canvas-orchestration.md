---
phase: 6
title: "Hub Canvas Orchestration"
status: pending
priority: P1
effort: "6d"
dependencies: [4]
---

# Phase 6: Hub Canvas Orchestration

## Overview
Port Canvas integration from Swinx, adapt to hub domain: provision courses/sections/enrollments (students + teachers) on the single shared Canvas, pull component scores in bulk.

## Requirements
- Functional:
  - OAuth connect + token refresh (single Canvas integration record)
  - Provision: EgcClass → Canvas course (or section); enroll students (active members) + lecturer as Teacher; suspend → Canvas enrollment deactivate; reactivate → restore
  - Grade pull: bulk submissions per course → `component_scores` (student, class, component, score, graded_at); manual re-sync per class/student
  - Preview-before-apply diff view (port existing pattern)
- Non-functional: throttle + paginate against Canvas API rate limits (2000+ students); all Canvas jobs queued with retry/backoff; failures land on monitor

## Architecture
Port from Swinx `app/Modules/Academic/Delivery/Support/Canvas/` — `CanvasHttpClient`, `CanvasApiService`, `CanvasTokenService` nearly as-is; rewrite `CanvasGradeSyncService` mapping to hub models (no AssessmentComponentDetail — hub stores raw component scores keyed by Canvas assignment id + name; schools map on their side). Course mapping table `class_canvas_mappings` (class_id, canvas_course_id, section_id).

## Related Code Files (egc-hub/)
- Create: `app/Services/Canvas/{CanvasHttpClient,CanvasApiService,CanvasTokenService}.php` (ported from Swinx, source path noted in header comment removed — plain port)
- Create: `app/Services/Canvas/{CanvasProvisioningService,CanvasScorePullService}.php`
- Create: `app/Models/{CanvasIntegration,ClassCanvasMapping,ComponentScore}.php` + migrations
- Create: `app/Jobs/Canvas/{ProvisionClassJob,PullClassScoresJob,DeactivateEnrollmentJob}.php`
- Create: `app/Http/Controllers/Admin/CanvasController.php` + `resources/js/pages/Admin/Canvas/*.vue`
- Create: Pest tests with fake HTTP client: provisioning idempotency, throttle behavior, suspend→deactivate, score upsert

## Implementation Steps
1. Port HTTP client + token service; verify against Canvas sandbox/test instance.
2. Provisioning service: create-or-match course/section, sync enrollments (diff-based, idempotent).
3. Wire suspend/reactivate transitions (phase 2 hooks) to enrollment deactivate/restore jobs.
4. Score pull job: paginated bulk submissions, upsert ComponentScore, emit `scores.updated` event per class batch.
5. Admin UI: integration status, per-class mapping, manual re-sync buttons, preview diff.

## Success Criteria
- [ ] Provision 50 classes / 2000 enrollments without rate-limit ban (throttle verified)
- [ ] Re-running provisioning changes nothing (idempotent diff)
- [ ] Score pull populates ComponentScore + emits events; re-pull after Canvas edit updates rows
- [ ] Suspend deactivates Canvas enrollment within one queue cycle

## Risk Assessment
- Canvas API quirks differ between instances → validate against the real shared Canvas early (first week of phase).
- Teacher Canvas accounts may need SIS matching — confirm email-based matching with EGC org.
