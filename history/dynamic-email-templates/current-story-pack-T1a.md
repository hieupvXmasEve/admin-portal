# Current Story Pack: T1a — CSRF cluster batch fix

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** T · **Wave:** 2
**Mode:** high-risk (parent) · **Story risk:** LOW (mechanical) — but production-behavior gate required.
**Depends on:** S3 ✅ (`spike-S3-test-fail-root-cause.md`) — confirms 7 NotificationOpsControllerTest fails share "CSRF token mismatch" cause.

## Entry State

- 7 failing tests in `tests/Feature/Notification/NotificationOpsControllerTest.php` (lines 464, 495, 519, plus 4 others).
- All call `$this->actingAs($user)->postJson(route('admin.notifications.ops.*'))`.
- All collapse expected `200|422|403` → `500 "CSRF token mismatch"`.
- The admin notifications ops POST routes inherit `web` middleware group including `VerifyCsrfToken`.

## Exit State

- All 7 tests pass.
- `./scripts/dev.sh test --filter NotificationOpsControllerTest` returns 0 failures.
- Decision recorded in this file's "Fix taken" section: **route exemption** (preferred) OR **test-level `withoutMiddleware`** (fallback).
- No other tests regress: `./scripts/dev.sh test tests/Feature/Notification` green.

## File Ops Inventory — LOCKED to Option (a) per validating 2026-05-19

**Decision (validating 2026-05-19):** Route is intentionally in **web group** at `routes/web/notifications.php:22` (`Route::post('/deliveries/{delivery}/retry', ...)->name('deliveries.retry')`). Production Vue callers use Inertia `useForm` which sends XSRF-TOKEN automatically. Tests use `postJson` which doesn't carry the cookie. **Production CSRF behavior is correct; tests must bypass.**

**Chosen path: Option (a) — test-level `withoutMiddleware`.**

| Path | Op |
|---|---|
| `tests/Feature/Notification/NotificationOpsControllerTest.php` | edit (`beforeEach` adds `$this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)`) |

Total: 1 file. Well under limit.

**Option (b) explicitly rejected:** route-level `$except` would weaken production CSRF on a state-changing admin endpoint.

## Verification Commands

```bash
# Re-run the 7 failures
./scripts/dev.sh test --filter NotificationOpsControllerTest

# Ensure no regression in sibling tests
./scripts/dev.sh test tests/Feature/Notification

# Confirm route group inheritance for the admin ops routes
grep -rn "admin.notifications.ops\|notifications/ops" routes/

# Confirm production middleware is intentional (validating gate)
grep -n "VerifyCsrfToken\|verify_csrf" app/Http/Middleware/ bootstrap/app.php config/
```

## DAG Row

`[S3] → [T1a]` — independent lane; no downstream story depends on T1a closing.

## Critical Patterns Applied

- **Pattern (CLAUDE.md security):** CSRF protection on state-changing forms is a security requirement. T1a must NOT silence a real protection. If admin notifications ops routes are state-changing AND web-grouped, route exemption is wrong — they should move to API surface (Sanctum, no CSRF).
- **Pattern #8:** DAG row above.

## Feasibility Notes

- Spike already reproduced the failure. Fix size: 1 line per option.
- **Critical validating gate:** Is `VerifyCsrfToken` on these admin POST routes intentional? Three sub-questions:
  1. Are the admin ops POST routes called from a Vue page with Inertia `useForm` (web-group correct) or from a modal/drawer with `useApi` (API-group correct)?
  2. If web-group correct + `useForm` correct → Inertia client should send CSRF token automatically. Why does `postJson` in tests fail? → Test bypass needed (option a).
  3. If API-group correct + `useApi` correct → routes moved to wrong group. Migrate routes (NOT option a or b — a third fix).

Validating must answer (1)–(3) BEFORE picking an option.

## Handoff to Validating

Validating gates:
1. Inspect callers of the failing routes in `resources/js/` — Inertia `useForm` or `useApi`? Trace from `route('admin.notifications.ops.deliveries.retry', ...)`.
2. Decide option (a), (b), or (c) [route group migration]. Record decision in this pack.
3. Worker implements the chosen option; re-run verification.

## Fix Taken (worker fills)

```text
Decision: <option a | b | c>
Reasoning: <one paragraph>
File edited: <path>
Verification result: <PASS | FAIL with details>
```
