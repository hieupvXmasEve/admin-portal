# Current Story Pack: B3 — `NotificationTemplatePolicy` super-admin gate

**Epic:** B. Save-time guards
**Story:** B3
**Mode:** `standard_feature`
**Source:** [approach-p2.md](approach-p2.md) §2 (admin surface) + Risk Map row "Super-admin Policy correctness"
**CONTEXT.md decision honored:** D7 (Super Admin only)

## Outcome

`NotificationTemplatePolicy` gates every admin write/read action on
`NotificationEmailTemplate` to users with `hasSystemRole('super_admin')`.
Policy registered for auto-discovery. Foundation for A1 (Inertia admin
controller) and A2 (API controller).

## Entry State

- `User::hasSystemRole(string $code): bool` exists ([User.php:271](app/Models/User.php)). Returns true if the user has the role at ANY campus.
- Role code `super_admin` is seeded by [RoleAndPermissionSeeder.php:68](database/seeders/InitialSetup/RoleAndPermissionSeeder.php) (`['id' => 1, 'name' => 'Super Admin', 'code' => 'super_admin']`).
- No `app/Modules/Notification/Policies/NotificationTemplatePolicy.php` exists.
- No `app/Providers/AuthServiceProvider.php` exists; Laravel 13 prefers Policy auto-discovery (model namespace mirror) — `App\Modules\Notification\Models\NotificationEmailTemplate` → `App\Modules\Notification\Policies\NotificationEmailTemplatePolicy`. If auto-discovery fails, register in `AppServiceProvider::boot()` via `Gate::policy(NotificationEmailTemplate::class, NotificationTemplatePolicy::class)`.

## Exit State

1. New `app/Modules/Notification/Policies/NotificationTemplatePolicy.php`:
   - `viewAny(User $user): bool` → `$user->hasSystemRole('super_admin')`
   - `view(User $user, NotificationEmailTemplate $t): bool` → same
   - `update(User $user, NotificationEmailTemplate $t): bool` → same
   - `preview(User $user, NotificationEmailTemplate $t): bool` → same
   - `testSend(User $user, NotificationEmailTemplate $t): bool` → same
   - `declare(strict_types=1)`; full type hints; class-level docblock referencing D7.
2. Policy registered: either Laravel 13 auto-discovery picks it up (preferred — name suffix `Policy` in same module namespace) OR explicit `Gate::policy(...)` in `AppServiceProvider::boot()` or `NotificationServiceProvider::boot()`. Validating Q3 from approach-p2.md resolved in story execution.
3. New `tests/Feature/Notification/Policies/NotificationTemplatePolicyTest.php` Pest feature test (`RefreshDatabase`):
   - **(a)** Super-admin user → `Gate::allows('update', $template)` returns true for all 5 abilities.
   - **(b)** User without super_admin → `Gate::allows('update', $template)` returns false; controller route hit returns 403.
   - **(c)** Unauth'd request → 302 to login.
4. `./scripts/dev.sh test tests/Feature/Notification/Policies/NotificationTemplatePolicyTest.php` green.
5. `./scripts/dev.sh artisan about > /dev/null` exit 0.
6. `docker exec swinx-app-dev vendor/bin/pint --test app/Modules/Notification/Policies/NotificationTemplatePolicy.php tests/Feature/Notification/Policies/NotificationTemplatePolicyTest.php` clean.

## Files Likely Touched

| File | Action |
|---|---|
| `app/Modules/Notification/Policies/NotificationTemplatePolicy.php` | CREATE |
| `app/Providers/AppServiceProvider.php` OR `app/Modules/Notification/Providers/NotificationServiceProvider.php` | EDIT only if auto-discovery doesn't pick up |
| `tests/Feature/Notification/Policies/NotificationTemplatePolicyTest.php` | CREATE |

**Total: 2 CREATE + ≤1 EDIT = 3 file ops max.**

## Feasibility Assumptions

| Assumption | Risk | Proof |
|---|---|---|
| Laravel 13 Policy auto-discovery works for module namespaces | LOW-MEDIUM | Laravel 13 default convention is `App\Models\<Foo>` → `App\Policies\<Foo>Policy`. Module namespaces require explicit registration. Worker should TRY auto-discovery first via the convention mapper; if `Gate::getPolicyFor(NotificationEmailTemplate::class)` returns null, fall back to explicit registration. |
| `hasSystemRole('super_admin')` works without campus context | LOW | [User.php:271-274](app/Models/User.php) calls `$this->campusRoles()->where('code', $roleCode)->exists()` — campus_id ignored. Super_admin role checked across all campuses. |
| Factory for super-admin User exists or is easy to create | LOW | Standard Laravel factory + `attachRole` pattern. |

## Verification (Done-When)

1. `./scripts/dev.sh test tests/Feature/Notification/Policies/NotificationTemplatePolicyTest.php` → 3 cases green.
2. `./scripts/dev.sh test tests/Unit/Notification tests/Feature/Notification` → regression green.
3. `./scripts/dev.sh artisan about > /dev/null` exit 0.
4. `docker exec swinx-app-dev vendor/bin/pint --test <touched files>` clean.

## Out of Scope

- The HTTP controller wiring (`$this->authorize('update', $template)`) — that's A1/A2.
- Adding new role codes — `super_admin` already seeded.
- Multi-campus restriction (Super Admin edits ANY campus) — per D7.

## Bead Mapping

Pending validation. ~30 min.
