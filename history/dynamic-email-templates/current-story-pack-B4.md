# Current Story Pack: B4 — Octane / queue-worker safety for EmailContentRegistry

**Epic:** B. Save-time guards
**Story id:** B4
**Mode:** `standard_feature`
**Phase:** P2
**Source:** [epic-map-p2.md](epic-map-p2.md), [approach-p2.md](approach-p2.md), [history/learnings/critical-patterns.md](../learnings/critical-patterns.md) (Critical Pattern #3)

## Outcome

`EmailContentRegistry` and `DbEmailContentProvider` no longer leak cached state across requests / jobs in long-running PHP runtimes (FrankenPHP today; Octane when deployed). Two-pronged fix:

1. **Flag-aware cache key** — `EmailContentRegistry::$resolved` keys on `(type_key, flag-value)` so toggling `config('notifications.use_db_templates')` rebuilds the binding. (REV-P2-06)
2. **Reset hook** — registry + provider expose `reset()`; service provider listens on `RequestHandled` and `JobProcessed` and clears caches at the request/job boundary. (REV-P2-02)

Resolves deferred beads **REV-P2-02** (Octane lifecycle) + **REV-P2-06** (memo key includes flag value). Honors Critical Pattern #3 ("singleton stateful providers leak across requests under Octane/FrankenPHP").

## Entry State

Observable truth before B4 starts (post-B1 commit `01809a36`):

- `app/Modules/Notification/EmailContent/EmailContentRegistry.php` (~80 lines):
  - `private array $resolved = []` cache keyed ONLY by `type_key`.
  - `resolve($typeKey)` reads/writes `$resolved` by `$typeKey`.
  - `build($typeKey)` reads `config('notifications.use_db_templates', true)` to choose new vs legacy provider.
  - No `reset()` method.
- `app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php` (~95 lines):
  - `private array $cache = []` keyed by `"$typeKey:$campusId"`.
  - No `reset()` method.
- `app/Modules/Notification/Providers/NotificationServiceProvider.php`:
  - `register()` binds `EmailContentRegistry` as `singleton()` (line 19).
  - `boot()` registers `Campus::observe(CampusObserver::class)` only. No `Event::listen` calls anywhere in the codebase yet.
- `composer.lock` has `laravel/octane ^2` but `config/octane.php` does NOT exist — Octane is installed but NOT deployed. FrankenPHP is the dev runtime per `.env.example`.

## Exit State

Testable truth after B4 completes:

1. `EmailContentRegistry::$resolved` cache key is composite: format `"$typeKey:$flagState"` where `$flagState ∈ {'db', 'legacy'}` derived from `config('notifications.use_db_templates')`. Toggling the flag mid-request returns a fresh provider build, NOT the cached one. The `:` separator is **intentional** — matches `DbEmailContentProvider::$cache`'s `"$typeKey:$campusId"` shape; `type_key` values are snake_case enum strings (`payment_reminder` etc.) so no collision risk.

2. `EmailContentRegistry::reset(): void` exists and:
   - Clears `$this->resolved = []`.
   - Iterates the prior `$resolved` values; if any implements a `reset()` method (duck-typed via `method_exists($p, 'reset')`), calls it. This avoids tight coupling to `DbEmailContentProvider`'s class while still resetting its inner row cache.

3. `DbEmailContentProvider::reset(): void` exists and clears `$this->cache = []`.

4. `NotificationServiceProvider::boot()` registers TWO event listeners (after the existing `Campus::observe` line):
   - `Event::listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, fn () => app(EmailContentRegistry::class)->reset())` — clears at HTTP request boundary (FrankenPHP / future Octane).
   - `Event::listen(\Illuminate\Queue\Events\JobProcessed::class, fn () => app(EmailContentRegistry::class)->reset())` — clears at queue-job boundary (the 4 reminder Actions run as queued jobs in production).

5. New feature test
   `tests/Feature/Notification/EmailContent/EmailContentRegistryResetTest.php` asserts:
   - **(a)** Calling `resolve('payment_reminder')` twice in a row returns the SAME instance (memo works).
   - **(b)** Calling `reset()` between two `resolve()` calls returns DIFFERENT instances.
   - **(c)** After `reset()`, the `DbEmailContentProvider::$cache` is empty (proven by query-log count: pre-reset 1 query for `(type, campus)`, post-reset 1 more query for the same key).
   - **(d)** Toggling `config(['notifications.use_db_templates' => false])` between two `resolve()` calls returns a `PaymentReminderEmailContent` (legacy class) the second time, NOT the cached `DbEmailContentProvider`.

6. New feature test
   `tests/Feature/Notification/EmailContent/EmailContentRegistryEventResetTest.php` asserts:
   - **(a)** Dispatching `event(new RequestHandled($request, $response))` from a test causes the registry to reset (verified by `resolve()` returning a new instance afterward).
   - **(b)** Same for `JobProcessed`.

   Construction hint (the codebase has no prior `event(new ...)` test patterns):
   ```php
   use Illuminate\Foundation\Http\Events\RequestHandled;
   use Illuminate\Http\Request;
   use Illuminate\Http\Response;

   event(new RequestHandled(Request::create('/'), new Response()));
   ```
   For `JobProcessed`, construct with the queue connection name + a real or mocked job:
   ```php
   use Illuminate\Queue\Events\JobProcessed;

   // Sync driver job mock is easiest; see Laravel framework's tests/Queue/QueueWorkerTest for shape.
   event(new JobProcessed('sync', $mockJob));
   ```

7. All P1 tests still green:
   - `tests/Unit/Notification` — 17 tests / 194 assertions.
   - `tests/Feature/Notification/EmailContent` — 8 tests / 14 assertions (parity + provider tests).
   - `tests/Unit/Notification/Enums/NotificationTemplateTypeKeyVariablesTest` — 5 tests / 156 assertions.

8. `./scripts/dev.sh artisan about > /dev/null` exits 0 (no missing-class fatal on boot).

9. `docker exec swinx-app-dev vendor/bin/pint --test <touched files>` clean.

## Files Likely Touched

Disjoint write surface; one worker can complete cold.

| File | Action |
|---|---|
| `app/Modules/Notification/EmailContent/EmailContentRegistry.php` | EDIT — (a) change `$resolved` cache key to composite `"$typeKey:$flag"`; (b) add `reset(): void` method; (c) extract flag-state helper `private function flagState(): string` returning `'db'|'legacy'` |
| `app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php` | EDIT — add `public function reset(): void { $this->cache = []; }` |
| `app/Modules/Notification/Providers/NotificationServiceProvider.php` | EDIT — `use Event` + `use Illuminate\Foundation\Http\Events\RequestHandled` + `use Illuminate\Queue\Events\JobProcessed`; add 2 `Event::listen` calls in `boot()` after the existing `Campus::observe` line |
| `tests/Feature/Notification/EmailContent/EmailContentRegistryResetTest.php` | CREATE — Pest test, cases (a)/(b)/(c)/(d) |
| `tests/Feature/Notification/EmailContent/EmailContentRegistryEventResetTest.php` | CREATE — Pest test, cases (a)/(b) |

**Total: 3 EDIT + 2 CREATE = 5 file ops.**

## Feasibility Assumptions

| Assumption | Risk | Proof needed |
|---|---|---|
| **`RequestHandled` event fires under FrankenPHP** | LOW | Standard Laravel event; fires on every HTTP terminate regardless of runtime. Confirmed by Laravel docs; no FrankenPHP-specific quirks. |
| **`JobProcessed` event fires after every queued job** | LOW | Standard Laravel queue event; fires in both sync and async drivers. Per Laravel docs. |
| **Duck-typed `reset()` on the cached provider survives legacy class swap** | LOW | The legacy `*EmailContent.php` classes have NO state (per-call `$data`). They don't implement or need `reset()`. The `method_exists` guard skips them safely. |
| **Composite cache key does not break the per-request memo benefit** | LOW | Within one request, the flag value is stable (config doesn't change mid-request in production). The composite key is purely a test-isolation + defensive measure. |
| **No existing test in the suite depends on the registry surviving across feature-test method boundaries** | LOW | Pest uses fresh-app per test. `RefreshDatabase` already wipes state. Any test that DOES depend on cross-test state was already broken. |
| **Octane deployment is not in scope** | LOW | `config/octane.php` doesn't exist; B4 only needs `RequestHandled` + `JobProcessed`. Octane-specific events (`Laravel\Octane\Events\RequestTerminated`) can be added in a follow-up when Octane goes live. |

No MEDIUM or HIGH risks. No spike needed.

## Verification (Done-When Sequence)

Worker runs these IN ORDER. Stop on first failure.

1. `./scripts/dev.sh test tests/Feature/Notification/EmailContent/EmailContentRegistryResetTest.php` — green (4 cases a/b/c/d).
2. `./scripts/dev.sh test tests/Feature/Notification/EmailContent/EmailContentRegistryEventResetTest.php` — green (2 cases a/b).
3. `./scripts/dev.sh test tests/Unit/Notification tests/Feature/Notification/EmailContent` — green (P1 + B1 regression net intact).
4. `./scripts/dev.sh artisan about > /dev/null` — exit 0.
5. `docker exec swinx-app-dev vendor/bin/pint --test app/Modules/Notification/EmailContent/EmailContentRegistry.php app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php app/Modules/Notification/Providers/NotificationServiceProvider.php tests/Feature/Notification/EmailContent/EmailContentRegistryResetTest.php tests/Feature/Notification/EmailContent/EmailContentRegistryEventResetTest.php` — clean.
6. `grep -rn "Event::listen.*RequestHandled\|Event::listen.*JobProcessed" --include='*.php' app/Modules/Notification/Providers` — returns the 2 listeners just added.

If any step fails, do NOT progress. Re-read the contract.

## Out of Scope

- **Octane-specific events** (`Laravel\Octane\Events\*`). Octane is installed but not deployed. Add when `config/octane.php` lands.
- **Switching the `singleton()` binding to `scoped()`**. The skill's reference says scoped is one option but it requires every consumer to be rebuilt per request — that's a wider blast radius than B4 needs. The reset hook achieves the same correctness with smaller surface change.
- **Locking down `EmailContentRegistry::has()`** to be flag-aware. The flag controls which provider class to bind, not which type_keys exist. `has()` semantics are unchanged.
- **Octane state-reset config** (`config/octane.php` `'listeners' => [RequestReceived::class => [...]]`). Defer to whoever sets up Octane.
- **Other P2 stories** (B2 sanitizer, B3 policy, B5 FormRequest, A1-A3 UI, C1-C2 operator tools, D1-D3 cleanup).

## Bead Mapping

**Status: pending validation.** No bead created yet. After `khuym:validating` clears B4 readiness, one execution bead:

```text
BR-dynamic-email-templates-p2-B4   octane/queue-worker safety for EmailContentRegistry
```

No story prerequisites. ~45 min focused work for one worker. Provisional file ownership matches the table above. Acceptance criteria = the 9 Exit-State assertions in this pack.
