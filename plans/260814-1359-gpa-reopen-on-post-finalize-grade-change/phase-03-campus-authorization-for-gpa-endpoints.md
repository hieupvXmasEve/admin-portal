---
phase: 3
title: "Campus authorization for GPA endpoints"
status: done
priority: P1
effort: "3h"
dependencies: []
---

# Phase 3: Campus authorization for GPA endpoints

## Overview

Pre-existing vulnerability found during red-team review, merged into this plan by user decision because phase 2 edits the same two surfaces. Both GPA endpoints accept `campus_id` from request input, validate it only as `exists:campuses,id`, and hardcode `authorize()` to `true`. An operator can read another campus's student PII and academic records, and can create official finalized GPA records for a campus they do not belong to. The repo already has the correct membership gate elsewhere.

Independent of the GPA staleness work, but shipping first or alongside it matters: phase 2 adds more academic data (stored GPA, divergence state) to the same unauthorized payload.

## Requirements

- Functional: `campus_id` on GPA preview and GPA finalize is rejected unless the authenticated user is a member of that campus.
- Functional: rejection is a 403/404 consistent with how the rest of the repo handles campus scope violations, not a validation error that leaks whether the campus exists.
- Functional: existing legitimate multi-campus operators keep working — the parameter is authorized, not removed.
- Non-functional: no behavior change for single-campus operators using the session campus.

## Architecture

Mirror the membership check the identity module already enforces on campus switching (`SelectCampusRequest:19-23`: `$this->user()->campuses()->where('campuses.id', $value)->exists()`). Two options, pick at implementation:

- **A (preferred):** move the check into `authorize()` on both FormRequests so it applies before validation and before the controller runs.
- **B:** keep FormRequests as-is and use the established controller-level helper (`assertCampus` / `assertCampusVisible`, cf. `CourseOfferingRegistrationController:38-43`, `FinanceStudentPaymentController:66`).

Prefer A: the vulnerability is that `authorize()` returns `true`, so fixing it where it is wrong is the smallest honest diff.

Second, one-line hardening in the same area: `RecalculateApplyController:49` returns raw `$e->getMessage()` to the client on the 500 branch, leaking driver/SQL detail. Replace with a generic message plus server-side logging.

## Related Code Files

- Modify: `app/Modules/Academic/Http/Requests/Gpa/FinalizeGpaRequest.php` (`authorize()`)
- Modify: `app/Modules/Academic/Http/Requests/Gpa/PreviewGpaFinalizationRequest.php` (`authorize()`)
- Modify: `app/Modules/Academic/Delivery/Http/Web/RecalculateApplyController.php` (stop echoing exception text)
- Read-only reference: `app/Modules/Identity/Http/Requests/Identity/SelectCampusRequest.php` (the gate being mirrored), `app/Modules/Academic/Http/Web/GpaManagementController.php:31,64` (where request input currently overrides session campus)
- Create test: `tests/Feature/Academic/Gpa/GpaEndpointCampusAuthorizationTest.php`
- Migration/route/frontend: none.

## Implementation Steps

1. Failing tests first: a user who is a member of campus 1 only receives 403 for `GET /academic/gpa/finalize?semester_id=X&campus_id=2` and for `POST /academic/gpa/finalize` with `campus_id=2`; a member of campus 2 succeeds; omitting `campus_id` still falls back to the session campus. (Include `_token`.)
2. Implement the membership check in both `authorize()` methods.
3. Replace the raw exception echo in `RecalculateApplyController` with a generic client message and a `Log::error` carrying the exception.
4. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Gpa`

## Todo

- [x] Tests red then green
- [x] Verified a non-member campus_id is rejected on both GET and POST
- [x] No raw exception text returned to clients from the recalculate endpoint

## Success Criteria

- [x] Cross-campus `campus_id` cannot read preview data or finalize GPA
- [x] Legitimate multi-campus operator flow unchanged

## Risk Assessment

- If any real operator currently relies on passing a campus they are not a member of, this will break their workflow — that is the intended correction, but it may surface as a support ticket. Check `campuses` membership coverage for GPA-permission holders on dev before shipping.
- Tightening `authorize()` also affects validation error shape; assert the expected status in tests rather than assuming 422 vs 403.
