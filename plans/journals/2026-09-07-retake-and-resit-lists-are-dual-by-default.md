---
title: Retake and resit lists are dual by default
date: 2026-09-07
summary: Removed sequential grade_failed routing; hide only while the other lane occupies the original academic record.
---

# Retake and resit lists are dual by default

## What happened
Staff expected SUMMER2026 grade_failed students on `/retake-course/create`. Slice 2 hid them until a completed/no-show resit.

## Decision
Both create lists show finalized failed records. Unfinished thi lại hides học lại. Non-cancelled học lại (including enrolled) hides thi lại, keyed by original_academic_record_id. Cancelled reopens both. Concurrent creates lock the academic record.

## Next steps
Reload `/retake-course/create?failed_semester_id=3` on HN. Commit if the list looks right.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
