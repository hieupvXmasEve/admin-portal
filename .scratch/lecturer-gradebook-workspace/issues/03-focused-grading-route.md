# 03 - Focused Grading Route

Status: done
Portal impact: lecturer

## Goal

Move focused assessment detail grading into the Gradebook mental model.

## Requirements

- Add route/page `/course/{courseId}/gradebook/items/{detailId}`.
- Do not require `componentId` in the route.
- Keep the existing focused grading UI behavior, using the detail grade table endpoint if still appropriate.
- Back button goes to `/course/{courseId}?tab=gradebook&view={returnView}`.
- `returnView` may be `to-grade` or `matrix`; default is `to-grade`.
- `To Grade` item action opens the new route with `returnView=to-grade`.
- Matrix can open focused grading with `returnView=matrix`.
- Remove route usage for the old assessment component placeholder.
- No backward route compatibility is required.
- Fix point input clamp to use `assessmentDetail.max_points` instead of hardcoded 100.

## Done

- Old component placeholder is not reachable from current navigation.
- Focused grading route works from both `To Grade` and `Matrix`.
- Point entry respects each detail's max points.
