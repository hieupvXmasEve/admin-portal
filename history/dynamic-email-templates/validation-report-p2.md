# Validation Report — Phase 2 (Super Admin Editor + Cleanup)

**Date:** 2026-05-17
**Mode:** `standard_feature`
**Shape under review:** [epic-map-p2.md](epic-map-p2.md) + 6 story packs (B2, B3, B5, A-batch, C-batch, D-batch)
**Inputs read:** [CONTEXT.md](CONTEXT.md), [approach-p2.md](approach-p2.md), [epic-map-p2.md](epic-map-p2.md), all 6 `current-story-pack-*.md`, P1 commits `07b6a3cf`, `e1ba651f`, `c625be42`
**P2 progress before validating:** B1 + B4 committed (`e1ba651f`, `c625be42`). 6 packs remain.

## Reality Gate Report

```
Mode:           standard_feature
Current work:   P2 remaining batch — 6 story packs (B2, B3, B5, A-batch, C-batch, D-batch)
MODE FIT:       PASS
REPO FIT:       PASS
ASSUMPTIONS:    PASS
SMALLER PATH:   PASS
PROOF SURFACE:  PASS
Decision:       proceed to feasibility matrix
```

Evidence:

- **Mode fit:** Multi-layer change (composer dep + config + Policy + FormRequest + 2 controllers + 4 Vue files + 1 migration + 6 test files). Direct/spike/small_change cannot carry it. Bounded blast radius (no external service, no auth-system change, feature-flag rollback from P1 still applies) so `high_risk_feature` would over-ceremony.
- **Repo fit:** Every seam assumed by the packs was inspected:
  - [NotificationServiceProvider.php:30](app/Modules/Notification/Providers/NotificationServiceProvider.php) — `Event::listen(RequestHandled::class, ...)` already wired by B4; B2/B3/B5/A/C/D inherit Octane safety for free.
  - [User.php:271](app/Models/User.php) — `hasSystemRole($roleCode)` runs `campusRoles()->where('code', $roleCode)->exists()`; campus-agnostic.
  - [NotificationTemplateTypeKey.php:20-22](app/Modules/Notification/Enums/NotificationTemplateTypeKey.php) — `availableVariables()` method exists; cases for `payment_reminder` and `parent_payment_reminder` confirmed.
  - [AppServiceProvider.php:69+](app/Providers/AppServiceProvider.php) — 7 existing `RateLimiter::for('uploads-*', fn (Request $r) => Limit::perMinute(N)->by($r->user()?->id ?: $r->ip()))` limiters; C2 follows the same shape verbatim.
  - [SendParentPaymentRemindersAction.php:35](app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php) — confirmed `->resolve('payment_reminder')` bug; D1 1-line fix is correct.
  - `routes/web/notifications.php` + `routes/api/admin.php` both exist; A-batch appends routes, no new file family.
  - `resources/js/components/EditorContent.vue` ships with `emailMode` + `commonVariables` props (P1 baseline).
  - `mews/purifier` NOT in `composer.lock`; only transitive `ezyang/htmlpurifier 4.19.0`. B2 work needed.
- **Smaller path:** Packs are already the smallest believable units (3-9 file ops each). No pack collapses further without losing testable exit.
- **Proof surface:** Every pack ships its own verification block — `./scripts/dev.sh test ...`, pint, `artisan about`. Every risk row has a named test.

## Feasibility Matrix

| Pack | Part / Assumption | Risk | Proof Required | Evidence | Result |
|---|---|---|---|---|---|
| **B2** | `mews/purifier ^3.4` clean install on L13 + PHP 8.4 | LOW | `composer require` runs; `Purifier` facade resolves | Packagist `mews/purifier 3.4.4` declares `illuminate/support: ^5.8 \| ... \| ^13.0` (verified P1 Q3); transitive `ezyang/htmlpurifier 4.19.0` already in lock | READY |
| **B2** | Round-trip preserves 4 seeded HTML bodies (inline style + tables) | MEDIUM | Snapshot test `assertEquals($before, Purifier::clean($before, 'email_body'))` on all 4 rows | Test file `tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php` is the spike-equivalent built into the pack | READY (built-in proof) |
| **B2** | XSS payloads stripped | LOW | Unsafe-fixture test asserts `<script>`/`javascript:`/`onerror=` removed | HTMLPurifier default URI.AllowedSchemes excludes `javascript:`; pack ships explicit test (Critical Pattern #1 honored) | READY |
| **B3** | `hasSystemRole('super_admin')` campus-agnostic | LOW | Code-walk of `User::hasSystemRole` | [User.php:271-274](app/Models/User.php) confirmed: `$this->campusRoles()->where('code', $roleCode)->exists()` ignores campus_id | READY |
| **B3** | Policy auto-discovery for module namespace | LOW | `Gate::getPolicyFor(NotificationEmailTemplate::class)` returns non-null | Laravel 13 default discovery is `App\Models\X` → `App\Policies\XPolicy`. Module namespace requires explicit `Gate::policy()`. Pack already documents the fallback path | READY WITH CONSTRAINT (use explicit `Gate::policy()` in `AppServiceProvider::boot()` or `NotificationServiceProvider::boot()`) |
| **B5** | Route-model binding `{template}` resolves `NotificationEmailTemplate` | LOW | A2 declares the binding (`Route::put('.../{template}', ...)`); standard Laravel | A2 pack §A2 declares route shape; B5 depends on A2 at execution time | READY (cross-story coupling — execute B5 only after A2's routes land) |
| **B5** | `passedValidation()` runs Purifier before controller reads `validated()` | LOW | Laravel lifecycle: `passedValidation` fires after rules pass, before controller resolves; `merge()` writes into request | Standard FormRequest pattern documented in Laravel core | READY |
| **A-batch** | Inertia v3 + Vue 3 admin page conventions | LOW | Existing `resources/js/pages/Admin/EmailTemplate/Index.vue` reference; CLAUDE.md Inertia v3 rules followed | Pack reuses existing Vue page family; `EditorContent.vue` exists with `emailMode` + `commonVariables` | READY |
| **A-batch** | `ApiResponse::success` envelope | LOW | Existing admin API controllers use it (CLAUDE.md mandates) | Confirmed in CLAUDE.md "Forbidden Patterns" table | READY |
| **A-batch** | `vue-sonner` toast wired | LOW | Already in `package.json` (P1 discovery) | Existing admin pages call it | READY |
| **A-batch** | Ziggy regen | LOW | `php artisan ziggy:generate` auto-runs in dev | Standard project convention | READY |
| **C-batch** | Preview render parity with production | LOW | Both call `HasTemplateRendering::renderContent` / `renderContentEscaped` | P1 parity test proves byte-equivalence; pack notes "transient NotificationEmailTemplate" uses the same trait | READY |
| **C-batch** | `Mail::fake()` intercepts `EmailService::sendSingleEmail` | LOW | EmailService dispatches via Laravel Mail facade; `Mail::fake()` works | Standard Laravel; pack notes optional `Bus::fake()` for queued path | READY |
| **C-batch** | `RateLimiter::for('notification-template-test-send', ...)` + `throttle:` middleware | LOW | 7 existing limiters in `AppServiceProvider` follow identical shape | [AppServiceProvider.php:69+](app/Providers/AppServiceProvider.php) — `uploads-list`, `uploads-info`, `uploads-delete`, `uploads-serve`, `uploads-single`, `uploads-multiple`, `uploads-validate` | READY |
| **C-batch** | `<iframe srcdoc sandbox>` blocks script exec without `allow-scripts` | LOW | Standard browser sandbox; pack uses default sandbox flags | Whatwg HTML iframe sandbox spec | READY |
| **D1** | `parent_payment_reminder` enum case + seeded row exist | LOW | `NotificationTemplateTypeKey::ParentPaymentReminder = 'parent_payment_reminder'` confirmed; seed migration covers all 4 type_keys | [NotificationTemplateTypeKey.php:22](app/Modules/Notification/Enums/NotificationTemplateTypeKey.php) | READY |
| **D1** | `parent_name` placeholder strategy | LOW | Pack documents fallback to `'Quý Phụ Huynh'` generic salutation | Worker should verify seeded `parent_payment_reminder` body's placeholders match before deciding whether to add `parent_name` to `$contentData`; safest: leave it out, rely on generic salutation in body | READY WITH CONSTRAINT (worker greps seeded body for `{{parent_name}}` and decides) |
| **D2** | MySQL FK drop+re-add `restrictOnDelete()` | LOW | Standard `Schema::table` → `dropForeign(['campus_id'])` → re-add | Standard Laravel migration pattern | READY |
| **D3** | `Mail::fake()` + `config(['notifications.use_db_templates' => true])` for finance Action tests | LOW | P1 feature flag pattern still active; existing finance tests already toggle config | Existing test conventions | READY |
| **D3** | `parentProfiles` relation on `Student` for SendDueItemParent test fixture | LOW | Confirmed by code-walk of `SendParentPaymentRemindersAction:28` (`student.parentProfiles.user`) and pack reuses same factory pattern | [SendParentPaymentRemindersAction.php:28](app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php) | READY |

### Feasibility Result

**READY WITH CONSTRAINTS.** No spike required; no `NO` result.

Recorded constraints carried into execution:

1. **B3 constraint:** Use explicit `Gate::policy(NotificationEmailTemplate::class, NotificationTemplatePolicy::class)` in `NotificationServiceProvider::boot()` — do not rely on auto-discovery for module namespaces.
2. **B5 constraint:** B5 reads `$this->route('template')` — A2 routes must declare `{template}` and route-model bind to `NotificationEmailTemplate`. Schedule B5 after A2.
3. **D1 constraint:** Worker greps `database/migrations/2026_05_16_170100_seed_notification_email_templates.php` for `{{parent_name}}` in the `parent_payment_reminder` body. If absent, do NOT add `parent_name` to `$contentData` — generic salutation in the seeded body is sufficient. If present, add `'parent_name' => $student->parentProfiles->first()?->user?->full_name ?? 'Quý Phụ Huynh'`.

## Validating Question Resolution (from approach-p2.md §Validating Questions)

| Q | Question | Evidence | Outcome |
|---|---|---|---|
| Q1 | Octane reset hook wiring + `RequestHandled` listener convention | [NotificationServiceProvider.php:30](app/Modules/Notification/Providers/NotificationServiceProvider.php) already uses `Event::listen(RequestHandled::class, ...)` + `JobProcessed::class`. `laravel/octane` package not installed; FrankenPHP runtime per CLAUDE.md may run without Octane wrapper. The listener fires under standard request lifecycle too — B4 work is safe regardless. | **RESOLVED** — answered by B4 commit `c625be42` |
| Q2 | Purifier inline-style preservation across 4 seeded bodies | B2 ships round-trip snapshot test on the 4 rows as built-in spike. Failure blocks B2's verification gate. | **DEFERRED TO B2 EXECUTION** — built-in proof |
| Q3 | Super-admin without campus context | [User.php:271-274](app/Models/User.php) — `campusRoles()->where('code', $roleCode)->exists()`; no campus_id filter | **RESOLVED** |
| Q4 | `EmailVariableSchema` outside consumers | `grep -rn 'EmailVariableSchema' app/ tests/ resources/ database/` → zero matches; interface deleted in B1 commit `e1ba651f` | **RESOLVED** |
| Q5 | Rate-limiter convention | [AppServiceProvider.php:69+](app/Providers/AppServiceProvider.php) — 7 limiters following `RateLimiter::for(name, fn (Request $r) => Limit::perMinute(N)->by($r->user()?->id ?: $r->ip()))`; `throttle:name` middleware on routes | **RESOLVED** |

All 5 planning questions cleared; only Q2 carries a built-in execution spike that the B2 pack already encapsulates.

## Integration Readiness

**PASS.**

- **Cross-pack stitching:** B2 + B3 + D-batch are zero-dependency (parallel-safe). B5 reads from B1 (done) + B2 + B3 (this batch). A-batch reads from B1 + B3 + B5. C-batch reads from A-batch. D-batch fully orthogonal.
- **Order:** D-batch + B2 + B3 in parallel → B5 → A-batch → C-batch. Matches state.json `next_action`.
- **Backend boundary:** every API endpoint returns `ApiResponse::success/error`; no `response()->json` violations.
- **Feature flag:** P1's `NOTIFICATIONS_USE_DB_TEMPLATES=false` rollback still works — A-batch saves become inert and legacy classes serve email content; full reversibility maintained.
- **Auth surface:** Single super-admin Policy gates all 5 abilities (`viewAny`, `view`, `update`, `preview`, `testSend`). No new role codes, no new middleware.
- **Existing pages untouched:** P1's storage separation (dedicated `notification_email_templates` table) means `/systems/email-templates` admin UI is not affected; A-batch creates a sibling page at `/admin/notification-templates`.

## Current Work Readiness (per pack)

Each pack has:
- testable exit (named test files + `./scripts/dev.sh test ...` gates)
- blocking assumptions proven or constrained (above)
- believable integration (orthogonal, or explicit cross-coupling documented)
- concrete verification (Pint, `artisan about`, regression suite)
- worker-sized scope (3–9 file ops per pack)

**PASS** for all 6 packs.

## Bead Review (Pack-as-Bead Review)

This project uses **story-packs as the bead-equivalent unit** (per state.json `next_action` and the proven swarming pattern: "one focused worker subagent per pack"). `.beads/` directory remains empty; story packs carry outcome/entry/exit/files/verification fields equivalent to bead structure.

**Packs reviewed:** B2, B3, B5, A-batch, C-batch, D-batch (6 packs).

```text
CRITICAL FLAGS: none

MINOR FLAGS:
  BR-D1     D1 pack hand-waves `parent_name` salutation strategy.
            Evidence: pack says `fall back to 'Quý Phụ Huynh'` but doesn't pin whether
            seeded `parent_payment_reminder` body uses `{{parent_name}}` placeholder.
            Suggestion: worker greps seeded body before adding the field; if placeholder
            absent, do not add `parent_name` to `$contentData`.

  BR-B3     B3 pack mentions Policy auto-discovery for module namespace.
            Evidence: Laravel 13 default convention does NOT auto-discover
            `App\Modules\Notification\Models\X` → `App\Modules\Notification\Policies\XPolicy`.
            Suggestion: worker skips the auto-discovery attempt and goes directly to
            explicit `Gate::policy(...)` in `NotificationServiceProvider::boot()`.

  BR-B5     B5 implicit cross-coupling on A2's route-model binding shape.
            Evidence: pack says `$this->route('template')` but doesn't gate the order.
            Suggestion: execute B5 only after A2 routes are in place; OR
            ship B5 with a stub route in A-batch and wire after.

  BR-C-batch  Preview endpoint builds a transient NotificationEmailTemplate model.
            Evidence: pack says "instantiates a transient NotificationEmailTemplate".
            Suggestion: confirm `render()` exists on the model (via HasTemplateRendering trait
            from P1); if missing, use a static helper that takes `(subject, body, vars)`.

CLEAN PACKS: B2, A-batch, D2, D3
REVISIONS MADE: none (minor flags are worker-time decisions, not pack rewrites)
SUMMARY: All 6 packs are worker-pickup-cold ready. The 4 minor flags are constraints to be
checked at execution time, not artifact rewrites. No CRITICAL gates blocking approval.
```

## Unresolved Concerns

- **Concern A (D1 `parent_name` strategy):** documented in MINOR flag BR-D1. Worker resolves in execution.
- **Concern B (Policy auto-discovery vs explicit):** documented in MINOR flag BR-B3. Worker uses explicit `Gate::policy()` to be safe.
- **Concern C (B5 cross-coupling on A2):** documented in MINOR flag BR-B5. Execution order enforces it.
- **Concern D (Preview transient model `render()` method):** documented in MINOR flag BR-C-batch. Worker confirms during C-batch execution.

None of these require returning to planning.

## Decision

```
READY WITH CONSTRAINTS - 4 MINOR worker-time flags
```

**Outcome:** all 6 packs feasibility-clear and structurally ready.

```
VALIDATION COMPLETE - APPROVAL REQUIRED BEFORE EXECUTION
Mode:                          standard_feature
Work:                          P2 remaining batch (6 packs: B2, B3, B5, A-batch, C-batch, D-batch)
Current story/work:            6 packs reviewed as one unit
Reality gate:                  PASS
Feasibility:                   READY WITH CONSTRAINTS
Structure:                     PASS (0 iterations)
Spikes:                        none required (Q2 built into B2)
Integration readiness:         PASS
Bead review:                   done — 4 MINOR, 0 CRITICAL
Current story/work readiness:  PASS
Unresolved concerns:           4 MINOR worker-time flags (D1 parent_name, B3 explicit Gate::policy, B5 order coupling, C-batch transient render() method)
Approve execution for this work? (yes/no)
```

**Recommended execution order:** D-batch ‖ B2 ‖ B3 → B5 → A-batch → C-batch.

On approval, hand off to `khuym:swarming` for worker dispatch.
