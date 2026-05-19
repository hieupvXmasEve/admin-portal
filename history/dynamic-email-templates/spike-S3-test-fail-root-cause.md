# Spike S3 — Test-fail root-cause probe

**Run date:** 2026-05-19
**Operator:** orchestrator (Bash, dockerized test runner)
**Pack:** [current-story-pack-S-batch.md](current-story-pack-S-batch.md) §S3
**Status:** ✅ COMPLETE — **root causes NOT shared**

## Commands

```bash
./scripts/dev.sh test --filter NotificationOpsControllerTest
./scripts/dev.sh test --filter StudentFinanceDngAccessTest
```

## Results

### NotificationOpsControllerTest — 7 failed / 21 passed (28 total)

**Common failure signature (all 7):**
```
The following errors occurred during the last request:
{
  "success": false,
  "message": "CSRF token mismatch.",
  "errors": [{ "code": "SERVER_ERROR", ... }]
}
Expected response status code [200|422|403] but received 500.
```

**Failure pattern:**
- Every failing test calls `$this->actingAs($user)->postJson(route('admin.notifications.ops...'))`.
- The route is hit by a CSRF middleware that rejects the `postJson` request even though `postJson` should bypass CSRF in test context.
- Verified failing assertions span 200 (success), 422 (validation), and 403 (auth) — all collapsed to 500 by the same CSRF interceptor.

**Sample failure line refs:**
- `tests/Feature/Notification/NotificationOpsControllerTest.php:464` (expected 200, got 500)
- `tests/Feature/Notification/NotificationOpsControllerTest.php:495` (expected 422, got 500)
- `tests/Feature/Notification/NotificationOpsControllerTest.php:519` (expected 403, got 500)

**Hypothesis:** Admin POST routes under `routes/web/*` (Inertia surface) inherit the `web` middleware group which includes `VerifyCsrfToken`. `postJson` triggers JSON content negotiation but NOT a CSRF exemption. Tests pass on GET routes (21/28 green); only POST/PATCH/DELETE fail.

**Fix size estimate:** 1 batch fix. Options:
- (a) `$this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)` in the test's `beforeEach` — 1 line.
- (b) Add the admin ops POST routes to `VerifyCsrfToken::$except` — 1 array push.
- (c) Move the routes to API surface (Sanctum guard) — larger refactor, defer.

→ **T1 batch fix likely <30 min for the CSRF cluster.**

### StudentFinanceDngAccessTest — 3 failed (sample from suite)

**Failure signatures (different from CSRF):**
```
Failure 1 (line 101):
  Expected response status code [200] but received 401.
  Body: { "success": false, "message": "Unauthorized" }

Failure 2 (line 121):
  Expected response status code [200] but received 401.
  Body: { "success": false, "message": "Unauthorized" }
```

**Failure pattern:**
- Tests call `$this->postJson(...)` / `$this->getJson(...)` against `/api/v1/student/...` endpoints **without an `actingAs($student)` call**.
- Endpoint guarded by `auth:sanctum` returns 401 because no authentication is established in the test.
- Test fixture omits the auth-setup step entirely.

**Hypothesis:** These tests predate (or skipped) the actor middleware enforcement on student API routes. Either:
- (a) Test was written when route was public, route later got auth → test rotted.
- (b) Test relies on a session/cookie pattern from earlier suite that doesn't apply to JSON API.

**Fix size estimate:**
- Per test: add `$this->actingAs($student, 'sanctum')` (or matching guard) — 1 line each.
- Sample shows 3 failures in this file. Full finance suite reportedly has ~30 fails (per P2 validation report). Probable spread: each test missing actor setup → mechanical fix per test but **not** a single batch fix.

→ **T1 finance cluster ≈ 30 individual edits, 5–10 min each, ~3–5h total.** Exceeds 4h spike budget for analysis; opens T2 quarantine option for any straggler.

## Comparison memo

| Dimension | NotificationOps CSRF cluster | Finance auth cluster |
|---|---|---|
| Symptom | 500 with "CSRF token mismatch" | 401 with "Unauthorized" |
| Trigger | POST/PATCH/DELETE on web-grouped admin routes | Any verb on `auth:sanctum` student routes |
| Root cause | Web middleware group inherits VerifyCsrfToken | Tests skip `actingAs($student, 'sanctum')` |
| Fix shape | 1 batch (middleware exemption OR test withoutMiddleware) | Per-test actor setup (~30 lines across ~30 tests) |
| Shared cause? | **NO** |

**Conclusion: NOT shared.** The two failure clusters require different fixes. T epic forks into T1a (CSRF batch) + T1b (finance auth backfill).

## Decision: REVISIT-SCOPE for T-epic

Original epic-map T epic assumed possible single batch fix. S3 falsifies that assumption.

| Sub-cluster | Stories | Effort | Recommendation |
|---|---|---|---|
| **T1a — CSRF cluster (7 fails)** | 1 story | ~30 min | GO — fast win |
| **T1b — Finance auth cluster (~30 fails)** | 1 story OR per-file packs | 3–5h | GO with T2 quarantine fallback for any test that resists 5-min mechanical fix |
| **T2 quarantine** | conditional | per-test | Open if any test needs deeper investigation than mechanical actor add |

### Epic-map-p3 amendment proposed

Split T1 into T1a + T1b in the story queue. Adjust budget: T1a = 30min one-shot; T1b = full story-sized work. T2 stays as fallback.

## Critical findings

1. **CSRF cluster is mechanical** — but the right fix is likely **(b) route exemption**, not (a) test-level `withoutMiddleware`. The latter hides the production risk. Validating gate for T1a: confirm production behavior is intentional (JSON POST → CSRF check is unusual on JSON-API routes) before merely silencing the test.

2. **Finance auth cluster suggests broader rot** — if 30 tests skip `actingAs($student, 'sanctum')`, the testCase base class may once have provided implicit auth that was removed. Worth a 10-min check of `tests/TestCase.php` / `tests/Feature/Finance/TestCase.php` / `setUp` chain BEFORE writing 30 individual fixes. If a shared `setUp` regression caused this, T1b collapses back to 1 fix.

3. **Failure counts may have grown** — P2 said "~30 finance fails", S3 sampled `StudentFinanceDngAccessTest` (3 fails) only. Before T1b commits, run `./scripts/dev.sh test tests/Feature/Finance 2>&1 | grep -E "FAILED|failed"` to get the current count. The number may be 30, may be more.

## Unresolved Questions

- Is the CSRF middleware on these admin POST routes intentional? (Production decision; validating must check before T1a fix.)
- Does the finance test suite have a regressed `setUp` that explains the 401s in one stroke? (10-min check before committing to per-test fix.)
- Are there other test failures outside the two sampled files? (Run full suite as part of T-epic kickoff.)
