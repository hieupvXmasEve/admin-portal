---
phase: 1
title: "Phase 1: Authorization Hardening"
status: done
priority: P1
effort: "1d"
dependencies: []
---

# Phase 1: Authorization Hardening

## Overview

Close the authorization drift on ALL routes in the scholarship route files — including the bulk financial-import sub-group — before any tuition-changing workflow builds on them. Prerequisite for all later phases.

<!-- red-team 2026-08-01: registry corrected (config/permission.php, not seeder grep); import routes added; role-grant step added -->

## Requirements

- Functional: every route in `student-scholarships.php` (incl. imports sub-group) gated by `can:` permissions; FormRequest authorization enabled.
- Grants (validation session 1): **super_admin only** for the pilot — `UpdatePermissionsSeeder::assignAllPermissionsToSuperAdmin` covers it after `permissions:sync`. Staff roles are granted later by explicit request. Staff hitting 403 post-P1 is EXPECTED behavior; rollout requires advance notice to current scholarship-screen users.
<!-- Updated: Validation Session 1 - grants scoped to super_admin; mirror-role step dropped -->

## Architecture

**Permission registry (verified):** permissions live in `config/permission.php` `access` array; `php artisan permissions:sync` (`app/Console/Commands/SyncPermissions.php`) upserts them into `permissions` and DELETES orphans; gates are defined by iterating that config (`app/Modules/Identity/Providers/IdentityServiceProvider.php:65-77`); grants come from `role_permissions ⋈ campus_user_roles` (`app/Modules/Identity/Support/EloquentCampusPermissionReader.php:19-32`). There is NO `Gate::before` super-bypass — an unlisted slug 403s everyone. `UpdatePermissionsSeeder` grants everything only to super_admin.

**Existing slugs (do NOT re-add):** `view_scholarship`, `assign_scholarship` (config/permission.php:357-358), `import_student_financial` (config/permission.php:359 — defined but consumed by no route today).

**New slugs to add to `config/permission.php`:**

| Slug | Used by |
|---|---|
| `remove_scholarship` | existing destroy route |
| `view_scholarship_adjustment` | P2-P5 |
| `manage_scholarship_adjustment_candidate` | P3 (manual add by MSSV incl. exception reason) |
| `manage_scholarship_interview` | P3 |
| `decide_scholarship_adjustment` | P3 (maker) |
| `approve_scholarship_adjustment` | P3/P5 (checker) |
| `apply_scholarship_adjustment_finance` | P2 (Finance apply/review queue) |
| `restore_scholarship` | P5 |
| `confirm_scholarship_adjustment_on_behalf` | P4 |

**Route gating (complete file coverage, not just CRUD):**

- index/show → `can:view_scholarship`; store → `can:assign_scholarship`; destroy → `can:remove_scholarship`.
- Imports sub-group (`routes/web/student-scholarships.php:23-35` — preview/import/template) → `can:import_student_financial`. These are the highest-blast-radius routes in the file (bulk mutation of student financial data, currently open to any verified user).
- Audit `routes/web/scholarships.php` the same way.

**Campus note:** `can:` alone proves permission "somewhere", not at a campus (`EloquentCampusPermissionReader.php:24-26` returns the all-campus union when session campus is null). Record-level campus checks are handled in P2/P3 via policy pattern `app/Policies/Admissions/StudentApplicationPolicy.php:34`; Phase 1 scope is route-level gating only.

## Related Code Files

- Modify: `config/permission.php` — add 9 new slugs to the appropriate `access` group.
- Modify: `routes/web/student-scholarships.php` — `can:` on every route incl. imports.
- Modify: `routes/web/scholarships.php` — same audit.
- Modify: `app/Http/Requests/AssignScholarshipRequest.php` — `authorize()` → `$this->user()->can('assign_scholarship')`.
- (No role-grant migration — super_admin-only pilot; `permissions:sync` + `UpdatePermissionsSeeder` suffice.)

## Implementation Steps

1. Add slugs to `config/permission.php`; run `php artisan permissions:sync` (+ `UpdatePermissionsSeeder` → super_admin gets all).
2. Gate every route in the file (verify coverage with `php artisan route:list` filtered to the two route files — assertion in test, not hand enumeration).
3. Enable `authorize()` in `AssignScholarshipRequest`.
4. Tests: 403 for non-super-admin on EVERY route (incl. imports) — expected pilot behavior; super_admin reaches everything; explicitly-granted role reaches gated route (grant mechanism sanity test).
5. Rollout note in PR description: staff lose scholarship-screen access until granted.

## Todo

- [x] Config slugs + sync
- [x] Role grants (super_admin via UpdatePermissionsSeeder at deploy)
- [x] All routes gated (route:list-driven test)
- [x] FormRequest authorize (AssignScholarshipRequest + ScholarshipRequest)
- [x] 403 + smoke coverage

## Completion Notes (2026-08-01)

- Implemented: config slugs (fee group + new `scholarship_adjustments` group), both route files fully gated, both FormRequests authorize via `assign_scholarship`.
- Extra fixes from code review: `ScholarshipController::index` ghost dot-slugs (`scholarships.create`/`scholarships.import` — never registered, always false) → `assign_scholarship`/`import_student_financial`; sidebar `menu-sidebar.ts` Student Scholarships item `assign_scholarship` → `view_scholarship` (matches index route gate).
- Tests: `tests/Feature/Scholarship/ScholarshipRouteAuthorizationTest.php` (4 tests) — coverage test also asserts every gate slug exists in `config/permission.access` (typo'd slug = permanent 403, no Gate::before). `ScholarshipTotalBasedFixedDiscountTest` updated to grant permissions (was relying on ungated routes).
- **Deploy runbook correction (review H1/H2):** `php artisan permissions:sync` only upserts permission rows — it does NOT grant to super_admin and does NOT delete orphans (both live in `UpdatePermissionsSeeder`). Deploy = `php artisan db:seed --class=UpdatePermissionsSeeder` THEN `php artisan cache:clear` (permission reader caches per-user codes for 24h; stale cache 403s super_admin on new gates).
- **Deferred (recorded):** Vue action buttons (Scholarships Index/Show, StudentScholarships Index) render unconditionally — view-only user clicking Delete gets 403 error page instead of hidden button. Route-level gating only per phase scope; wire `can` props in P2/P3 UI work.

## Success Criteria

- [ ] `route:list`-driven test proves zero ungated routes in both scholarship route files, including imports.
- [ ] Non-super-admin 403s everywhere (expected); super_admin works unchanged; grant mechanism proven by test.
- [ ] `permissions:sync` idempotent; no orphan deletion of unrelated slugs.

## Risk Assessment

- Staff 403 disruption is ACCEPTED (validation session 1) — mitigate with rollout notice, not grants.
- Hidden consumers (internal calls, MCP tools) → grep controller/route-name usages before gating.
- `StudentFinancialImportService.php:282-312` updates `scholarship_code` in place — gating the route contains the exposure; the award-mutation guard versus active adjustments is handled in Phase 2.
