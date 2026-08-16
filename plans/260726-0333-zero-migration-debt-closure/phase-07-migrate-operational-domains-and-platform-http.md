---
phase: 7
title: "Phase 7: Migrate operational domains and platform HTTP"
status: pending
priority: P1
effort: XL
dependencies: [3, 5]
---

# Phase 7: Migrate operational domains and platform HTTP

<!-- Rescoped 2026-08-16 from measured inventory; supersedes the 2026-07-26 draft. -->

## Overview

Close Facilities, Engagement, Merchandise, Notification, Upload, Admissions, and
email/platform HTTP debt as owner-aligned vertical slices. This is the highest
raw-response and inline-validation density outside Academic and Finance.

Merchandise did not exist when the 2026-07-26 draft was written. It shipped on
2026-08-01 and the scanner already tags its 24 findings to this phase, so this
phase now owns it explicitly rather than by accident.

Engagement is the largest cluster here, not Notification or Upload as the draft
assumed.

## Measured scope (2026-08-16)

167 findings: 112 `shared_model_imports`, 17 `inline_request_validation`,
15 `direct_json_responses`, 12 `frozen_services`, 9 `frozen_controllers`,
2 `frozen_routes`.

The 144 non-frozen findings break down as:

| Cluster | Findings |
|---|---:|
| `Engagement` (Models 25, Http 11, Actions 6, Support 6, Queries 2) | 50 |
| `Merchandise` (Models 14, Http 5, Policies 2, Support 2) | 23 |
| `app/Http/Controllers/Api` platform shells | 12 |
| `Upload` (Http 7, Models 5, Policies 2) | 14 |
| `Notification` (Support 8, Models 5, Http 4) | 17 |
| `Facilities/Models` | 5 |
| `Admissions/Http` | 5 |
| Remaining platform controllers (financial import, scholarship shells, Web) | 6 |

Merchandise's twenty-fourth finding is a `missing_strict_types` entry tagged to
phase 9; leave it there rather than splitting the rule.

The remaining 23 findings are frozen shells for email, notification, gold, wallet,
guardian, and Admissions surfaces: `app/Http/Controllers/{EmailLogController,
Api/GoldTransactionController, Api/NotificationController, Api/StudentWalletController,
Api/V1/Admin/EmailConfigurationController, Api/V1/Admin/EmailController,
Web/Admin/NotificationController, Web/ApplicationGuardianController,
Web/EmailConfigurationController}.php`, the matching email, gold, and notification
services, `app/Services/Admissions/*`, `routes/web/notifications.php`, and
`routes/api/admin/notification.php`. The scanner tagged them to phase 9 until
`frozenShellPhase()` was corrected on 2026-08-16; they now tag here.

## Requirements

- [ ] Remove shared-model imports in Facilities, Engagement, Merchandise, Notification, and Upload.
- [ ] Bring Merchandise onto owner contracts. It was built after the boundary rules were
  set and has never been held to them, so it needs the same treatment as any other module,
  not a grandfather exemption.
- [ ] Replace raw responses and inline validation without envelope or status drift.
- [ ] Preserve recipient resolution, outbox retries, file authorization, and portal upload.
- [ ] Rehome Admissions application, document, guardian, and ingestion shells and the
  backfill dependency.
- [ ] Portal impact: student for Upload; serialize portal packages after phase 5.
- [ ] Resolve repository issue 19 review evidence.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Engagement` | Complete club, event, form, and support ownership |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Merchandise` | Bring the August module onto owner contracts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Facilities` | Complete building, room, and booking ownership |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Notification` | Replace recipient model access; canonicalize the ops API |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Upload` | Canonicalize upload and chunked HTTP contracts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Admissions` | Close residual application, guardian, and import shells |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services/Admissions` | Rehome the backfill dependency before phase 9 |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/Api` | Migrate the twelve remaining platform API shells |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/Api/V1/Admin` | Migrate email configuration, template, and sending |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages` | Migrate matching canonical pages |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` | Update Upload contract, types, and UI |

## Interface Checklist

- Recipient resolution consumes owner references or projections, not Eloquent models.
- FormRequests own validation and emit canonical validation errors.
- Upload authorization, MIME and size limits, chunk ownership, and cleanup remain enforced.
- Queue and outbox payloads contain stable IDs and survive deploy and retry.
- Merchandise redemption stays consistent with its shipped product decisions: no stock
  hold, transfer is an instant gift, overdue handling stays manual.

## Dependency Map

`reference foundations → owner page/HTTP slices`; Upload portal packages also depend
on phase 5's student portal contract baseline.

## Implementation Steps

1. Add missing HTTP envelope and authorization characterization tests.
2. Migrate Engagement clubs, events, forms, and support-request workflows; it is the
   largest cluster and sets the pattern for the rest.
3. Migrate Merchandise catalogue, variants, stock movement, and redemption orders.
4. Migrate Facilities rooms and bookings and their filtered pages.
5. Replace Notification recipient resolution and migrate ops and outbox pages.
6. Migrate Upload APIs, including the student portal-facing endpoint.
7. Close residual Admissions application, document, guardian, and ingestion HTTP and
   rehome the still-needed backfill implementation into Admissions.
8. Migrate the platform API shells and email configuration, template, and sending;
   Canvas remains solely in phase 4.
9. Drain and inspect serialized jobs, remove shells, and lower every affected ratchet.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Room collision and campus scope | Conflict rejected without partial write |
| Merchandise redemption and stock movement | Ledger consistent; no double-spend of gold |
| Notification retry | Same recipient once; durable failure evidence |
| External recipient | Canonical payload and history preserved |
| Unauthorized or chunked upload | Rejected; no orphaned file or chunk |
| Student portal upload | Contract and UI remain compatible |
| Email template validation | Canonical errors and no unsafe rendering |

## Success Criteria

- [ ] These domains have zero shared imports, frozen shells, raw responses, and inline validation.
- [ ] Merchandise is explicitly covered by that zero, not exempted for being new.
- [ ] Root frontend and affected student portal checks pass.
- [ ] Queue, outbox, and scheduled behavior is equivalent and issue 19 is accepted.

## Risks and Security

- Files, email, and external recipients expose sensitive data. Preserve tenant and campus
  scope, signed access, input sanitization, recipient privacy, retry idempotency, and audit logs.
- Merchandise moves a gold balance that behaves like currency. Treat redemption and
  transfer as money paths: transactional, idempotent, and audited.
