---
id: ADR-0045
title: "Store the EGC defer start block as audit metadata"
status: accepted
date: 2026-05-31
owner: Platform Team
last_verified: 2026-09-05
scope: architecture-decision
---

# Store the EGC defer start block as audit metadata

## Context

`student_action_logs.from_semester_id` identifies the source semester, but does
not identify whether an EGC defer began in Block 1 or Block 2. Inferring that
fact from `egc_blocks.block_number` is unsafe because those records are runtime
finance/progression data and permit values beyond two.

## Decision

Store nullable `egc_defer_from_block_number` on `student_action_logs`.

- Valid values are `1` and `2`.
- It applies only to EGC `ACADEMIC_DEFER` actions whose current lifecycle
  status is `intake_pre_uni_gc` (`legacyCompatibleStatus()`: active enrollment
  with EGC study stage). Do not compare `enrollment_status` to
  `intake_pre_uni_gc`; that column is `active`/`deferred`/`dropout`/`dropout_transfer`/`withdrawn`/`graduated`.
- New web-recorded EGC defer actions in that current status require an
  explicit value. Additional defer while already deferred does not.
- Existing EGC defer actions backfill to Block 1 as a deterministic default and
  may be corrected individually.
- Major-program defer actions keep the field null.
- The field is reporting metadata only and does not affect tuition, charges, or
  course registration.

## Consequences

Reports can filter by source semester and source block without coupling audit
history to mutable runtime records. The historical default is intentionally
approximate until staff corrections are made. Additional defer while already
deferred stores a null block, so those rows do not match the Student Actions
audit "EGC Block" filter; the original EGC defer row still does.
