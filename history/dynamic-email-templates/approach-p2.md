# Approach - Phase 2 (Super Admin editor + guards)

**Date:** 2026-05-16
**Mode:** `standard_feature`
**Shape:** Epic Map → Current Story Pack (not phase plan; capability areas are orthogonal)
**Source:** [CONTEXT.md](CONTEXT.md) D1-D9, [phase-plan.md](phase-plan.md) P2 row, [history/learnings/critical-patterns.md](../learnings/critical-patterns.md)
**P1 status:** complete, committed `07b6a3cf` on `dev`; 11 follow-ups queued in `.khuym/state.json:deferred_beads`

## Why `standard_feature` (not smaller / larger)

- Smaller modes rejected:
  - `direct_task` / `small_change`: touches routes, controller (web + api), FormRequest, Policy, sanitizer dep, Inertia page family, Vue components — well over 3 files, multi-layer.
  - `spike`: no single yes/no question gates the path; sanitizer + super-admin auth + variable-picker contract are all known patterns.
- Larger mode (`high_risk_feature`) rejected:
  - Blast radius is bounded to one new admin route family; existing `/systems/email-templates` is untouched (P1 already proved separation via the dedicated `notification_email_templates` table).
  - Feature flag rollback path from P1 still applies — flip `NOTIFICATIONS_USE_DB_TEMPLATES=false` and the new admin UI's saves become inert (legacy classes serve email content).
  - No new external service or hard-to-reverse data change.

## Why Epic Map (not phase plan)

P2 has four orthogonal capability areas — admin authoring surface, save-time guards, operator tools, and P1 cleanup. They do not sequence as observable milestones the way P1's "parity then authoring" did. An epic map captures the capability/risk shape without forcing artificial milestones.

## Critical patterns honored (from `history/learnings/critical-patterns.md`)

- **Pattern #1 (parity tests need unsafe fixtures):** purifier round-trip test ships with unsafe-character fixtures (HTML special chars + payloads designed to inject markup).
- **Pattern #2 (per-tenant invariants need observer):** N/A in P2 — P1 already shipped `CampusObserver` + `NotificationEmailTemplateProvisioner`; P2 builds on top.
- **Pattern #3 (singleton stateful providers leak under Octane):** **MUST FIX in P2** (REV-P2-02 + REV-P2-06). Bind `EmailContentRegistry` `scoped()` with explicit reset on `RequestHandled`, OR key the resolved provider cache on `(typeKey, flag-value)` and add a `reset()` method called from the boundary hook. Once admin can save templates, the singleton serving stale rows is no longer theoretical.
- **Pattern #4 (storage = lifecycle):** P1 applied; P2 builds on the dedicated `notification_email_templates` table.

## Recommended Approach

### 1. Variable allow-list relocation (resolves REV-P2-05)

Move `availableVariables()` off the 4 legacy `*EmailContent.php` classes (scheduled for deletion in cleanup task) into one of:

- **Preferred:** a method on `NotificationTemplateTypeKey` enum (`availableVariables(): array<string, array{label, sample}>`). PHP 8.1 backed enums support methods; this co-locates the schema with the type identifier and survives the legacy-class deletion.
- Alternative: dedicated `VariableSchemaRegistry` keyed by `type_key`. More indirection for no gain in P2.

Adopt the enum-method form. Drop the `EmailVariableSchema` interface (the 4 legacy classes no longer need to implement it).

### 2. Inertia admin page family at `/admin/notification-templates`

Routes added to `routes/web/notifications.php` under the existing
`Route::middleware(['auth', 'verified'])->prefix('admin')` group (matches the
`admin.notifications.*` naming convention there). Files:

- `app/Modules/Notification/Http/Web/Admin/NotificationTemplateController.php` — `index()` + `edit($campusId, $typeKey)` returning Inertia pages.
- `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` — `update`, `preview`, `testSend`, `variables` JSON endpoints.
- `app/Modules/Notification/Http/Requests/UpdateNotificationTemplateRequest.php` — FormRequest validating subject + body_html + rejecting unknown `{{var}}` per D8 + super-admin policy gate.
- `app/Modules/Notification/Policies/NotificationTemplatePolicy.php` — `update`, `preview`, `testSend` methods, all gated to `$user->hasSystemRole('super_admin')`.
- `resources/js/pages/Admin/NotificationTemplate/Index.vue` — list view (campus × type_key grid).
- `resources/js/pages/Admin/NotificationTemplate/Edit.vue` — uses `EditorContent.vue` with `commonVariables` fed by the variables endpoint.
- `resources/js/pages/Admin/NotificationTemplate/components/PreviewPane.vue` — server-rendered preview via `POST /api/v1/admin/notification-templates/preview` with sample data from `NotificationTemplateTypeKey::availableVariables()`.
- `resources/js/pages/Admin/NotificationTemplate/components/TestSendButton.vue` — button + confirmation modal.

### 3. HTML sanitizer

`composer require mews/purifier:^3.4` + publish `config/purifier.php` with the email allow-list config:

```php
'email_body' => [
    'HTML.Allowed' => 'p,br,strong,b,em,i,u,s,a[href|target|rel|style],img[src|alt|width|height|style],ul,ol,li,table,thead,tbody,tr,th[style|colspan|rowspan],td[style|colspan|rowspan],blockquote,hr,h1[style],h2[style],h3[style],span[style],div[style]',
    'CSS.AllowedProperties' => 'color,background-color,font-family,font-size,font-weight,font-style,text-align,text-decoration,line-height,margin,margin-top,margin-bottom,margin-left,margin-right,padding,padding-top,padding-bottom,padding-left,padding-right,border,border-collapse,border-color,border-style,border-width,border-left,border-right,border-top,border-bottom,width,height,max-width',
    'AutoFormat.RemoveEmpty' => false,
    'Attr.AllowedFrameTargets' => ['_blank'],
],
```

Wired in `UpdateNotificationTemplateRequest::passedValidation()` via `Purifier::clean($body_html, 'email_body')` BEFORE the controller writes. Round-trip snapshot test on the 4 seeded bodies asserts `assertEquals($before, Purifier::clean($before, 'email_body'))` and a separate unsafe-fixture test proves XSS payloads ARE stripped/escaped.

### 4. Operator tools: preview + send-test

- **Preview** — `POST /api/v1/admin/notification-templates/preview` accepts `{type_key, subject, body_html}` (NOT yet saved) + auto-fills sample data from `NotificationTemplateTypeKey::availableVariables()`. Returns `{rendered_subject, rendered_html}` via the same `HasTemplateRendering` trait the production provider uses. Guarantees parity with production render.
- **Send-test** — `POST /api/v1/admin/notification-templates/{id}/test-send` renders the SAVED template with sample data, prefixes subject with `[TEST]`, sends via `EmailService::sendSingleEmail` to the logged-in admin's `email` only. Rate-limit to 5/min per user (reuse Laravel `RateLimiter` per existing convention in routes).

### 5. Octane safety (resolves REV-P2-02 + REV-P2-06)

Two-pronged:

- `EmailContentRegistry::$resolved` cache key changes from `$typeKey` to `$typeKey.':'.($this->useDbTemplates() ? 'db' : 'legacy')` so flipping the flag mid-request rebuilds the binding.
- `EmailContentRegistry` gains a `reset(): void` method that clears `$resolved` + delegates to each cached `DbEmailContentProvider::reset()` (which clears its `$cache` array). Register via `Octane::tick(...)` or `Event::listen(RequestHandled::class, ...)` in `NotificationServiceProvider::boot()` — pick whichever the repo already uses. If neither is wired, register both safely (`if (class_exists(Octane::class))` guard).

If discovery during execution shows Octane isn't deployed yet (FrankenPHP only? or just `php artisan serve`?), document the constraint and ship the cache-key fix alone — the reset hook becomes a "ship before turning Octane on" follow-up. Validating will confirm.

### 6. P1 cleanup carried into P2

The following deferred_beads land in P2 (small, related, low-risk):

- **REV-P2-01**: Fix wrong `type_key` in `SendParentPaymentRemindersAction:35` (`'payment_reminder'` → `'parent_payment_reminder'`). Pre-existing bug; one line + test.
- **REV-P2-03**: Migration changes `cascadeOnDelete()` → `restrictOnDelete()` on `notification_email_templates.campus_id`. New migration (not in-place edit) so production rolls forward cleanly.
- **REV-P2-07**: Add `DbEmailContentProvider` exception-path tests (missing `campus_id`, missing row).
- **REV-P2-08**: Add `campus_id`-reaches-provider assertion to the 4 finance Action tests.
- **REV-P2-09**: Add minimal feature tests for `SendDueItemRemindersAction` + `SendDueItemParentRemindersAction` (currently 0).
- **REV-P2-10**: Add `Log::error` to silent catch in `SendDueItemParentRemindersAction:140-151`.
- **REV-P2-11**: Replace `\Log::` with `use Log;` in `SendDueItemParentRemindersAction`.

**Deferred past P2** (not in scope):
- **REV-P2-04** (typed `RenderContext` value object on `EmailContentProvider` interface). Real architecture debt but a multi-week refactor of the interface and its 5 callers; do not bundle into P2.

### 7. Rejected alternatives

- **Tightly coupling preview to a TipTap server-side renderer:** rejected. The preview must use the same `HasTemplateRendering` trait that production calls, otherwise admin sees a different output than students. Server-side `render($variables)` is the source of truth.
- **Inline edit on the index page (no separate Edit route):** rejected. Editor surface needs vertical real estate (toolbar + variable picker + preview pane); inline cramming hurts UX and complicates state.
- **Custom HTML sanitizer hand-rolled in PHP:** rejected. `mews/purifier` is Laravel 13 compatible + actively maintained + the underlying `ezyang/htmlpurifier` is the standard. Hand-rolled sanitizer is a known-foot-gun anti-pattern.
- **Run preview and send-test through the same endpoint:** rejected. Different auth needs (send-test mutates external state via SMTP), different rate-limit envelopes, different request shapes.

## Risk Map

| Component | Level | Reason | Proof Needed |
|---|---|---|---|
| Sanitizer config preserves email-client styles | **MEDIUM** | Wrong config strips inline `style=`, table emails collapse in Gmail/Outlook | Round-trip snapshot test on the 4 seeded bodies; `assertEquals` after `Purifier::clean()`. Unsafe-fixture test confirms XSS payloads ARE removed. |
| Octane scoping invariant | **MEDIUM** | Critical Pattern #3 (carry-over from P1 review). Stale templates served after admin save until worker restart. | Feature test that resets the registry between two `htmlBody(['campus_id'=>X])` calls and confirms a fresh DB read. Plus a manual code-walk on the `RequestHandled` listener wiring. |
| Variable allow-list relocation (enum vs current legacy class) | **LOW** | Touches 4 legacy classes + 1 enum + ~2 consumers. | Existing `EmailVariableSchemaConsistencyTest` still passes after relocation; new test asserts `NotificationTemplateTypeKey::PaymentReminder->availableVariables()` returns the same keys. |
| Super-admin Policy correctness | **LOW** | Plain Policy + Gate per Swinx convention (`hasSystemRole('super_admin')`). | Feature test: non-super-admin → 403; super-admin → 200; unauth'd → 302 login. |
| FormRequest variable-allow-list validation | **LOW** | Regex parsing the saved body for `{{var}}` not in allow-list. | Feature test: save body with `{{unknown_var}}` → 422 with field error. Save body with allow-listed vars → 200. |
| Test isolation when toggling flag in tests | **LOW** | REV-P2-06 — current memo cache doesn't include flag in key. | Provider test toggles flag mid-test and confirms different instance class is returned. |
| Send-test rate limiting | **LOW** | Admin could spam-send. | Feature test: 6 sends in 1 min → 429. |
| Pre-existing wrong type_key fix | **LOW** | One-line change in `SendParentPaymentRemindersAction:35` + new assertion in test. | Test asserts parents receive output containing `parent_name` placeholder substituted, not the student template. |
| Cascade-delete migration | **LOW** | New migration adds `restrictOnDelete`. MySQL supports drop+re-add of FK. | Migration runs forward + backward cleanly on dev DB. |

## Likely File / Order Boundaries

Order = epics A → B → C in parallel where possible; D (cleanup) interleaved.

1. **Variable schema relocation (Epic B foundation)**
   - `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php` — add `availableVariables(): array` method.
   - 4 × `app/Modules/Notification/EmailContent/Types/*EmailContent.php` — remove `implements EmailVariableSchema` + the `availableVariables()` method bodies (they're on the enum now).
   - `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php` — delete (interface no longer used).
   - Update `tests/Unit/Notification/EmailContent/EmailVariableSchemaConsistencyTest.php` → renamed/refactored to test enum methods.

2. **Octane safety (Epic B foundation)**
   - `app/Modules/Notification/EmailContent/EmailContentRegistry.php` — cache key + `reset()` method.
   - `app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php` — `reset()` method clears `$cache`.
   - `app/Modules/Notification/Providers/NotificationServiceProvider.php` — listener on `RequestHandled` calls `EmailContentRegistry::reset()`.
   - New feature test for stale-template prevention.

3. **Authorization (Epic B)**
   - `app/Modules/Notification/Policies/NotificationTemplatePolicy.php` (`update`, `preview`, `testSend`).
   - Register policy in `app/Providers/AuthServiceProvider.php` (or whatever existing convention uses).

4. **Sanitizer (Epic B)**
   - `composer require mews/purifier:^3.4`
   - `config/purifier.php` (`vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"` then edit).
   - Snapshot test in `tests/Feature/Notification/EmailContent/SanitizerRoundtripTest.php`.

5. **Backend admin endpoints (Epic A + Epic C)**
   - `app/Modules/Notification/Http/Web/Admin/NotificationTemplateController.php` (index, edit).
   - `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` (update, preview, testSend, variables).
   - `app/Modules/Notification/Http/Requests/UpdateNotificationTemplateRequest.php`.
   - Append routes to `routes/web/notifications.php` and `routes/api/admin.php`.

6. **Inertia pages + Vue components (Epic A)**
   - `resources/js/pages/Admin/NotificationTemplate/Index.vue`
   - `resources/js/pages/Admin/NotificationTemplate/Edit.vue`
   - `resources/js/pages/Admin/NotificationTemplate/components/PreviewPane.vue`
   - `resources/js/pages/Admin/NotificationTemplate/components/TestSendButton.vue`
   - Ziggy regen via `php artisan ziggy:generate` (auto-runs in dev).

7. **P1 cleanup (Epic D, parallelisable with all of the above)**
   - One-line fix to `SendParentPaymentRemindersAction:35`.
   - New migration `2026_05_*_change_notification_email_templates_campus_fk_to_restrict.php`.
   - Add 2 exception-path tests to `DbEmailContentProviderTest`.
   - Add `campus_id` assertion to 4 finance Action tests (those that exist) + new test files for `SendDueItem*`.
   - `Log::error` patch + `use Log;` patch in `SendDueItemParentRemindersAction`.

## Validating Questions (For `khuym:validating`)

These need feasibility evidence before execution beads are created:

1. **Octane reset hook wiring:** is `Event::listen(RequestHandled::class, ...)` already used elsewhere in the repo? If FrankenPHP/Octane is not yet deployed, can we ship the cache-key change alone and defer the reset listener until Octane goes live? *Proof: grep for `RequestHandled` listeners + check `composer.json` for `laravel/octane`.*

2. **Purifier inline-style preservation:** does `mews/purifier 3.4.x` with our `email_body` config preserve all inline styles used in the 4 seeded bodies? *Proof: round-trip snapshot test before P2 closes.*

3. **Super-admin auth path:** does `$user->hasSystemRole('super_admin')` work for users WITHOUT a campus context (the policy doesn't have a `$campus_id` argument in `update($user, $template)`)? *Proof: code-walk of `User::hasSystemRole` + a 403/200 feature test.*

4. **Variable allow-list relocation completeness:** does any code OUTSIDE the 4 `*EmailContent.php` classes consume the `EmailVariableSchema` interface? *Proof: grep for `EmailVariableSchema` after preliminary planning; the interface was only just added in P1 so the surface should be tiny.*

5. **Rate-limiter conventions:** how do existing admin endpoints in this repo apply per-user rate limits? *Proof: grep for `RateLimiter::for(` + `throttle:` middleware usage.*

## Relevant Learnings (carried from P1)

- Critical Pattern #1: every parity/sanitization test ships with unsafe-char fixtures.
- Critical Pattern #3: any singleton-bound stateful provider gets a `reset()` hook before Octane goes live.
- Decision from P1: "feature flag MUST come with a named sunset condition" — P2 doesn't add new flags, but the existing `notifications.use_db_templates` sunset starts ticking 30 days after P2 ships.

## Out Of Scope (deferred past P2)

- Anything in [CONTEXT.md](CONTEXT.md) `## Deferred Ideas`.
- REV-P2-04 (typed `RenderContext` value object) — multi-week interface refactor.
- Deletion of legacy `EmailContent\Types\*EmailContent.php` classes — only after the 30-day sunset window per P1's documented condition.
- Multi-language template variants (D2: keep gộp EN+VI single body).
- Versioning / draft-publish (D5: overwrite + audit).
- Adding new email events beyond the 4 type_keys (D1).
