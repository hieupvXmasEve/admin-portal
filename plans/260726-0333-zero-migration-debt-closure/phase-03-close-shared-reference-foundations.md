---
title: "Phase 3: Close shared reference foundations"
status: todo
priority: P0
effort: XL
dependencies: [1]
---

# Phase 3: Close shared reference foundations

## Overview

Close residual consumers against the already accepted Identity, Institution,
Student Registry, and Admissions owner boundaries. Do not rebuild completed
owner workflows; add/repair a seam only where a verified consumer gap proves it.

## Requirements

- [ ] Remove all non-owner User, Student, Campus, Department, and Lecture actor imports.
- [ ] Shared contracts never accept/return Eloquent models or relationship builders.
- [ ] Replace Finance's `Student::observe(...)` coupling with an owner event/command seam.
- [ ] Close residual Identity permission/auth/password/verification/settings/profile HTTP debt.
- [ ] Preserve `cross_context_concrete_imports=0`.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Shared/Contracts/Identity` | Add/repair actor and access references |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Shared/Contracts/Institution` | Stabilize campus/department readers |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Shared/Contracts/StudentRegistry` | Stabilize student/guardian readers and events |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Identity` | Own user/access decisions |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Institution` | Own organizational facts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/StudentRegistry` | Own student lifecycle facts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Identity/Http` | Migrate residual auth/access/settings HTTP |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services/PermissionService.php` | Replace active legacy permission capability |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/web/auth.php` | Cut auth routes to Identity owner |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/web/settings.php` | Cut settings/profile routes to Identity owner |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Providers/FinanceServiceProvider.php` | Remove direct Student observer |

## Interface Checklist

- References expose stable scalar IDs/value objects and immutable snapshots only.
- Queries needing current authority call the owning context; reports may use documented projections.
- Events dispatch after commit, are idempotent, and carry no Eloquent instance.
- Campus scope, guardian proxy, lecturer grants, tokens, and user lifecycle remain owner-enforced.

## Dependency Map

`Identity + Institution + StudentRegistry contracts → Academic/Finance/Notification/AI consumers`

## Implementation Steps

1. Verify accepted issues 04–07, then classify only remaining User/Student/Campus/Department imports as owner-internal,
   current-authority read, historical projection, or invalid coupling.
2. Characterize authorization and lifecycle behavior before changing interfaces.
3. Repair `LecturerTeachingActor` so it no longer returns `HasMany`.
4. Reuse existing accepted readers/references/events; introduce only proven missing seams.
5. Migrate PermissionService, auth/settings routes, inline Identity validation, and portal contracts.
6. Switch remaining consumers by domain and delete direct imports in the same commits.
7. Freeze the ownership map and add an independent all-runtime-root ownership audit.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| User disabled or campus changed | Current-authority decisions update immediately |
| Guardian/lecturer grant revoked | Access denied across API and web |
| Student state transition | Finance event processed once after commit |
| Historical report | Stable projection, no live cross-context model query |
| Architecture scan | No Eloquent in contracts; concrete-import invariant stays zero |

## Success Criteria

- [ ] Foundation model findings are zero outside their genuine owner allowlist.
- [ ] Each ownership exemption has owner evidence and no non-owner runtime consumer.
- [ ] Identity HTTP/access debt and affected lecturer portal checks are closed.
- [ ] Existing public contract tests and identity/registry architecture tests pass.
- [ ] All dependent phases have documented, model-free interfaces available.

## Risks and Security

- Stale snapshots can grant access after revocation. Hard authorization gates must use
  fresh owner contracts; projections are limited to display/reporting.
