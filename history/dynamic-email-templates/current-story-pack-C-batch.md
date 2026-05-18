# Current Story Pack: C-batch — Operator tools (C1 preview + C2 send-test)

**Epic:** C. Operator tools
**Stories:** C1 (server-rendered preview) + C2 (send-test to admin's own inbox)
**Mode:** `standard_feature`
**Source:** [approach-p2.md](approach-p2.md) §4 + Risk Map "Send-test rate limiting"
**CONTEXT.md decisions honored:** D9 (preview + send-test in v1)

## Outcome

Super Admin on the edit page sees a live Preview pane (server-rendered, byte-equal
to production output) and a "Send test" button that emails the rendered template
to their own inbox under a 5-per-minute rate limit. Both endpoints use the same
`HasTemplateRendering` trait as production to guarantee parity.

C1 and C2 batched because they share the admin Edit page surface and the same
sample-data source (`NotificationTemplateTypeKey::availableVariables()`).

## Entry State

- A-batch done: admin Edit page renders subject + body editor; placeholder API endpoints for preview + test-send return 501.
- B1 done: variable allow-list on enum.
- B3 done: super-admin Policy.
- B5 done: validation (not used by preview which renders DRAFT, not saved).
- `EmailService::sendSingleEmail($to, $subject, $html, $campusId)` exists (used by P1 finance reminders).
- `RateLimiter::for('uploads-*', ...)` pattern lives in `app/Providers/AppServiceProvider.php:69+` — model for `'notification-template-test-send'` limiter.

## Exit State

### C1 — Preview endpoint + PreviewPane.vue
1. `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` gains `preview(Request $request, NotificationEmailTemplate $template): JsonResponse`:
   - Authorized via `$this->authorize('preview', $template)`.
   - Reads `subject` + `body_html` from request body (the unsaved DRAFT in the editor) — falls back to `$template->subject` + `$template->body_html` if not provided.
   - Builds `$variables = $template->type_key->availableVariables()` → maps to `[name => sample]` array.
   - Runs the SAME render path as production: instantiates a transient `NotificationEmailTemplate` with the draft subject + body, calls `->render($sampleVariables)`.
   - Returns `ApiResponse::success(['rendered_subject' => ..., 'rendered_html' => ...])`.
2. New `resources/js/pages/Admin/NotificationTemplate/components/PreviewPane.vue`:
   - Watches the Edit page's `form.subject` + `form.body_html` with `useDebounceFn` (300ms).
   - On change → POST `/api/v1/admin/notification-templates/{id}/preview` → renders `rendered_html` inside an `<iframe srcdoc>` (sandboxed; no script execution).
   - Shows `rendered_subject` above the iframe in plain text.
3. `Edit.vue` includes `<PreviewPane :template="template" :draft="form" />` in a right-hand column (responsive: stacks below editor on narrow screens).

### C2 — Send-test endpoint + button
4. `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` gains `testSend(Request $request, NotificationEmailTemplate $template): JsonResponse`:
   - Authorized via `$this->authorize('testSend', $template)`.
   - Reads `subject` + `body_html` from request (draft, like preview).
   - Builds the sample-rendered subject + html (same as preview).
   - Calls `EmailService::sendSingleEmail($request->user()->email, '[TEST] '.$subject, $html, $template->campus_id)`.
   - Returns `ApiResponse::success(['sent_to' => $request->user()->email])`.
5. `AppServiceProvider::boot()` registers a new `RateLimiter::for('notification-template-test-send', fn (Request $r) => Limit::perMinute(5)->by($r->user()?->id ?: $r->ip()))`.
6. Route in `routes/api/admin.php` for `testSend` uses `->middleware('throttle:notification-template-test-send')`.
7. New `resources/js/pages/Admin/NotificationTemplate/components/TestSendButton.vue`:
   - Button + confirmation modal ("Send test email to YOUR_EMAIL?").
   - On confirm → POST `/api/v1/admin/notification-templates/{id}/test-send` → on 200 toast `Test email sent to {email}`; on 429 toast `Rate limit: 5 per minute, try again later`.

### Tests
8. New `tests/Feature/Notification/Http/Api/NotificationTemplatePreviewTest.php` (3 cases):
   - **(a)** Super Admin POST `/preview` with draft → 200; `rendered_html` substitutes sample variables.
   - **(b)** Preview output uses the SAME escaping as production `htmlBody()` (XSS-safe sample); pass a `<script>` in the sample is impossible because samples come from the enum, but assert that the renderer escapes via the `HasTemplateRendering::renderContentEscaped` path — i.e. assert `rendered_html` is byte-equal to what `DbEmailContentProvider::htmlBody()` would produce for the same input.
   - **(c)** Non-super-admin → 403.
9. New `tests/Feature/Notification/Http/Api/NotificationTemplateTestSendTest.php` (3 cases):
   - **(a)** Super Admin POST `/test-send` with draft → 200; `Mail::fake()` shows one mail sent to the admin's email with `[TEST]` subject prefix.
   - **(b)** 6th send within 1 minute → 429.
   - **(c)** Non-super-admin → 403.

## Files Likely Touched

| File | Action | Story |
|---|---|---|
| `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` | EDIT — add preview() + testSend() (A-batch created the file) | C1 + C2 |
| `app/Providers/AppServiceProvider.php` | EDIT — append `RateLimiter::for('notification-template-test-send', ...)` | C2 |
| `routes/api/admin.php` | EDIT — define routes for preview + test-send + throttle | C1 + C2 |
| `resources/js/pages/Admin/NotificationTemplate/components/PreviewPane.vue` | CREATE | C1 |
| `resources/js/pages/Admin/NotificationTemplate/components/TestSendButton.vue` | CREATE | C2 |
| `resources/js/pages/Admin/NotificationTemplate/Edit.vue` | EDIT — wire PreviewPane + TestSendButton | C1 + C2 |
| `tests/Feature/Notification/Http/Api/NotificationTemplatePreviewTest.php` | CREATE | C1 |
| `tests/Feature/Notification/Http/Api/NotificationTemplateTestSendTest.php` | CREATE | C2 |

**Total: 4 CREATE + 4 EDIT = 8 file ops.**

## Feasibility Assumptions

| Assumption | Risk | Proof |
|---|---|---|
| Preview render via transient model has parity with production | LOW | Both call `HasTemplateRendering::renderContent` and `renderContentEscaped` — proven byte-equivalent by P1 parity test. |
| `Mail::fake()` works with `EmailService::sendSingleEmail` | LOW | EmailService dispatches `SendSingleEmailJob` which uses Laravel Mail facade under the hood; `Mail::fake()` intercepts both. Worker may need to also `Bus::fake()` if the job is queued. |
| `RateLimiter::for(name)` with `throttle:name` middleware | LOW | Standard Laravel; existing AppServiceProvider has 5 such limiters. |
| Iframe-sandboxed preview prevents accidental script exec | LOW | `<iframe srcdoc=... sandbox>` is the standard pattern; no JS runs without `allow-scripts`. |
| 5/minute rate is appropriate for admin testing | LOW | Per approach-p2.md; tweakable post-feedback. |

## Verification (Done-When)

1. `./scripts/dev.sh test tests/Feature/Notification/Http/Api/NotificationTemplatePreviewTest.php` → 3 cases green.
2. `./scripts/dev.sh test tests/Feature/Notification/Http/Api/NotificationTemplateTestSendTest.php` → 3 cases green.
3. `./scripts/dev.sh test tests/Feature/Notification tests/Unit/Notification` → regression green.
4. `./scripts/dev.sh npm run type-check && ./scripts/dev.sh npm run lint` → clean.
5. `./scripts/dev.sh artisan about > /dev/null` exit 0.
6. Pint clean on touched PHP files.

## Out of Scope

- Preview for the SAVED template only (not the draft) — D9 spec is preview the unsaved draft, no other mode.
- Multiple recipients on test-send — D9 says admin's own inbox only.
- Persisting the test-send history (audit trail of who sent what when) — Spatie activitylog already captures admin saves; test-sends are ephemeral.
- A4 (`AuditableModel` already wires the audit log free for saves — no separate story).
- Octane-specific test-send queue concerns — covered by B4's reset hook.

## Bead Mapping

Pending validation. Depends on A-batch done. ~2 hours for the combined batch.
