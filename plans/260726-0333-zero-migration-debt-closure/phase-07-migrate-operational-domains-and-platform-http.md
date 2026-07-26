---
title: "Phase 7: Migrate operational domains and platform HTTP"
status: todo
priority: P1
effort: XL
dependencies: [3, 5]
---

# Phase 7: Migrate operational domains and platform HTTP

## Overview

Close Facilities, Engagement, Notification, Upload, Admissions, email/platform HTTP
debt as owner-aligned vertical slices. This phase handles the highest-density
raw-response and inline-validation clusters outside Academic and Finance.

## Requirements

- [ ] Remove shared-model imports in Facilities, Engagement, Notification, and Upload.
- [ ] Replace raw responses and inline validation without envelope or status drift.
- [ ] Preserve recipient resolution, outbox retries, file authorization, and portal upload.
- [ ] Rehome Admissions application/document/guardian/ingestion shells and its backfill dependency.
- [ ] Portal impact: student for Upload; serialize portal packages after phase 5.
- [ ] Resolve repository issue 19 review evidence.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Facilities` | Complete building/room/booking ownership |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Engagement` | Complete club/event/form/support ownership |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Notification` | Replace recipient model access; canonicalize ops API |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload` | Canonicalize upload/chunked HTTP contracts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Admissions` | Close residual application/guardian/import shells |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services/Admissions` | Rehome the backfill dependency before phase 9 |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/Api/V1/Admin` | Migrate email configuration/template/sending |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages` | Migrate matching canonical pages |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` | Update Upload contract/types/UI |

## Interface Checklist

- Recipient resolution consumes owner references/projections, not Eloquent models.
- FormRequests own validation and emit canonical validation errors.
- Upload authorization, MIME/size limits, chunk ownership, and cleanup remain enforced.
- Queue/outbox payloads contain stable IDs and survive deploy/retry.

## Dependency Map

`reference foundations → owner page/HTTP slices`; Upload portal packages also depend
on phase 5's student portal contract baseline.

## Implementation Steps

1. Add missing HTTP envelope and authorization characterization tests.
2. Migrate Facilities rooms/bookings and their filtered pages.
3. Migrate Engagement clubs/events/forms/support-request workflows.
4. Replace Notification recipient resolution and migrate ops/outbox pages.
5. Migrate Upload APIs, including the student portal-facing endpoint.
6. Close residual Admissions application/document/guardian/ingestion HTTP and
   rehome the still-needed backfill implementation into Admissions.
7. Migrate email configuration/template/sending; Canvas remains solely in phase 4.
8. Drain/inspect serialized jobs, remove shells, and lower every affected ratchet.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Room collision and campus scope | Conflict rejected without partial write |
| Notification retry | Same recipient once; durable failure evidence |
| External recipient | Canonical payload/history preserved |
| Unauthorized/chunked upload | Rejected; no orphaned file/chunk |
| Student portal upload | Contract and UI remain compatible |
| Email template validation | Canonical errors and no unsafe rendering |

## Success Criteria

- [ ] These domains have zero shared imports, frozen shells, raw responses, and inline validation.
- [ ] Root frontend and affected student portal checks pass.
- [ ] Queue/outbox and scheduled behavior is equivalent and issue 19 is accepted.

## Risks and Security

- Files, email, and external recipients expose sensitive data. Preserve tenant/campus scope,
  signed access, input sanitization, recipient privacy, retry idempotency, and audit logs.
