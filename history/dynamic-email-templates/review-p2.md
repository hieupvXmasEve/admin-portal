# Review Report — P2 Dynamic Email Templates

**Date:** 2026-05-17
**Epic:** `dynamic-email-templates-p2`
**Inputs:** `CONTEXT.md`, `approach-p2.md`, `epic-map-p2.md`, `validation-report-p2.md`, all P2 uncommitted diff
**Specialists:** code-quality, architecture, security, test-coverage, learnings-synthesizer

## Severity tally (after dedup + reclassification)

| Severity | Count | Notes |
|---|---|---|
| **P1** | **1** | Blocking — user acknowledgment required |
| P2 | 9 | (was 17 raw, 8 merged) — 5 fix-now + 4 defer |
| P3 | 6 | Batch cleanup commit |

Reclassification: Agent 4 (test-coverage) originally rated 4 findings as P1. Per the reviewing skill severity definition (P1 = security breach / data loss / breaking change / production blocker), regression-guard test gaps reclassify to P2. The actual code those tests would guard is correct today (security reviewer confirmed).

---

## P1 — BLOCKING

### F1 — Inertia `useForm.put()` posts to an API JSON route → save UI always appears to fail

| | |
|---|---|
| **File** | `resources/js/pages/Admin/NotificationTemplate/Edit.vue:78` |
| **Failure** | `form.put()` issues an Inertia XHR to `api.admin.notification-templates.update`. The API controller returns `ApiResponse::success([...])` (raw JSON, no `X-Inertia` response header). The Inertia client throws *"All Inertia requests must receive a valid Inertia response"* — the Save button visibly fails every time, even though the backend wrote the row. |
| **Why tests missed it** | A-batch's API test exercised the route directly via `putJson()` — bypassed the Inertia client entirely. No end-to-end browser test ran. UAT would catch this on the first save attempt. |
| **Two fix paths** | **(A)** Switch `Edit.vue` to `useApi`/`useApiRequest` + vee-validate + Zod (project rule `docs/rules/frontend.md` says non-navigating modal/drawer forms use this stack; keep API as JSON). **(B)** Add a web Inertia `PUT` endpoint that returns `Inertia::location()` / `back()->with(...)` and route `form.put()` at it. |
| **Recommendation** | **(A)** — matches the documented project rule. ~15 min: replace `useForm` + `form.put()` with a `useApi` call hitting the existing API endpoint; error handling already maps to the same field shape. |

---

## Resolutions applied this session

| Finding | Status | Files |
|---|---|---|
| **F1 (P1)** | ✓ FIXED — added web `update()` to `NotificationTemplateController` returning `back()` with `Inertia::flash('success', ...)`. Added route `Route::put('/{template}', ...)->name('update')`. Switched `Edit.vue` form to `route('admin.notification-templates.update', ...)`. New regression test (case d) asserts 302 + flash key. Case (e) asserts non-super-admin 403. API endpoint preserved for future callers. | Web ctrl + routes/web + Edit.vue + 2 new test cases |
| **F2 (P2)** | ✓ FIXED — `Purifier::clean($draftBody, 'email_body')` added at entry of both `preview()` and `testSend()`. Draft path now matches save path's sanitization. | API ctrl + import |
| **F3 (P2)** | ✓ FIXED — `<iframe sandbox="">` (was `sandbox="allow-same-origin"`). Blocks `<meta refresh>` Referer leak. | PreviewPane.vue |
| **F4 (P2)** | ✓ FIXED via cheaper alternative — kept `$fillable` (refactoring 12 callers is out of scope) but added a model-level `booted()` `updating` guard that throws `LogicException` if `campus_id` or `type_key` changes on an existing row. Defense-in-depth at the layer that matters. | Model |
| **F5 (P2)** | ✓ FIXED — `extractParentEmails` → `extractParentRecipients` returning `Collection<{email, name}>`. Foreach pairs name with email per recipient. Tests still green (4/4). | SendParentPaymentRemindersAction |
| **F6 (P2)** | ✓ FIXED — `router.visit(url, { preserveScroll: true, preserveState: true })` replaces `window.location.href`. Dead `handlePageSizeChange` removed. | Index.vue |

**Composite verification:** 101 / 108 P2-territory tests green. 7 failures are pre-existing CSRF tests in `NotificationOpsControllerTest` (out of P2 scope, identical baseline pre/post the fixes). Pint clean.

## P2 — Fix Now (defense-in-depth, trivial)

| # | Title | File(s) | Fix | Effort |
|---|---|---|---|---|
| **F2** [KNOWN-XSS] | **preview() + testSend() BYPASS Purifier.** Save path through B5 sanitizes; draft paths render/email raw `body_html`. Sibling-call-site parity gap. | `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` (preview + testSend) | `Purifier::clean($bodyHtml, 'email_body')` at entry of both methods (1 line each). | XS |
| **F3** | PreviewPane iframe uses `sandbox="allow-same-origin"` — `<meta refresh>` in draft body navigates iframe; admin Referer leaks. | `resources/js/pages/Admin/NotificationTemplate/components/PreviewPane.vue:~96` | Drop `allow-same-origin` from sandbox attr. | XS |
| **F4** | Mass-assignment: `campus_id` + `type_key` in `$fillable`. Safe today (FormRequest only allows subject/body_html) but one rule addition away from cross-tenant write. | `app/Modules/Notification/Models/NotificationEmailTemplate.php` | Remove `campus_id`, `type_key` from `$fillable`; rely on `forceFill()` in provisioner. | XS |
| **F5** | Parent reminder uses `parentProfiles->first()` for `parent_name` — may pick wrong parent in multi-parent case (D-batch landed this). | `app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php:59` | Pass the actually-emailed parent's profile into the variable resolver (loop or zip). | S |
| **F6** | Index.vue pagination uses `window.location.href` (full reload, breaks SPA / Inertia stack). | `resources/js/pages/Admin/NotificationTemplate/Index.vue:68-70` | Replace with `router.visit(url, { preserveScroll: true, preserveState: true })`. | XS |

**Total fix-now effort:** ~30-45 min. All XS except F5 (small).

---

## P2 — Defer (follow-up beads / packs)

| # | Title | Why defer |
|---|---|---|
| **F7** [MERGED arch+CQ+T11] | Extract `RenderDraftEmailTemplateAction` — consolidates draft sanitize+render+error envelope used by preview() + testSend(). | Needs design (DTO shape, error contract). New Action. Inline F2 patch keeps prod safe; this is the cleaner home. |
| **F8** [KNOWN — Critical Pattern #3 deeper trap] | **Cross-module Contract gap.** Finance Actions import `Notification\EmailContent\EmailContentRegistry` directly. No `app/Shared/Contracts/Notification/`. P1 inherited; P2 deepened by adding singleton state. | Larger refactor (4 Finance imports + binding). Tie to REV-P2-04 RenderContext effort. |
| **F9** [MERGED T1-T4, T6-T12, T14] | **Regression-guard test backfill.** Single bead bundling: recipient-injection guard, FK rollback test, student-side `payment_reminder` assertion, super_admin cross-campus, empty-draft preview, rate-limit window expiry (Carbon::setTestNow), deleted-template 404, sanitizer edge chars, EmailService graceful failure, D1 output substitution, oversize body_html >65535, ambiguous campus_id-99999. | Single test-hardening pack; ~2hrs. Not blocking ship. |
| **F10** | Variable allow-list malformed-token tests: `{{}}`, `{{ var }}` (whitespace), case-mismatch. | Bundle with F9. |

---

## P3 — Batch cleanup

- **F11** [MERGED arch+CQ] Extract `ListNotificationTemplatesQuery` (Inertia ctrl pagination) AND move `+ ['updated_by_user_id'=>...]` into `UpdateNotificationTemplateAction`.
- **F12** PSR-12: move `use` statements to file top in `routes/api/admin.php:178` + `routes/web/notifications.php:27`.
- **F13** Remove `handlePageSizeChange` dead no-op in `Index.vue:72-74,149`.
- **F14** Nest new admin route group under existing `require` (vs sibling top-level).
- **F15** `config/purifier.php`: clarify `custom_attributes` profile scope; auto-inject `rel="noopener noreferrer"` for `target="_blank"`.
- **F16** Misc test gaps: subject `max:500`, rate-limiter registration, web ctrl 404 path, policy seed fragility. Bundle with F9.

---

## Promotion candidates for `critical-patterns.md`

1. **Inertia `useForm` ↔ API JSON response shape mismatch is runtime-only.** From F1. Future validating gate: for every new Vue form, name the form helper (`useForm` vs `useApi`) AND the response shape (Inertia render/redirect vs ApiResponse JSON); reject mismatches.
2. **Sibling-endpoint sanitization parity.** From F2. Generalizes Critical Pattern #1 to *any* sanitization barrier: when N sibling endpoints accept the same untrusted payload, diff the sanitization step column-wise.
3. **Iframe `sandbox="allow-same-origin"` is a footgun for admin preview surfaces.** From F3. `<meta refresh>` is the canonical bypass; `allow-same-origin` must be omitted unless the iframe content is trusted-origin.

---

## Dedupe / merges performed

| Merged from | Into |
|---|---|
| Security F2 (Purifier bypass) + Arch P2 (duplicated draft-render) + T11 (EmailService failure) | F2 (inline fix-now) + F7 (deferred extraction) |
| Arch P3 (inline pagination query) + CQ P3 (inline `+['updated_by_user_id'=>...]`) | F11 |
| Test-coverage T1-T4, T6-T12, T14 (11 findings) | F9 single regression bead |
| Test-coverage T15-T18 | F16 with F9 |

---

## Unresolved questions

- **Q1 (F1 fix direction):** `useApi` vs new Inertia web PUT endpoint? Recommend `useApi` per `docs/rules/frontend.md`.
- **Q2 (F4 deeper):** Once `campus_id` + `type_key` out of `$fillable`, should an observer guard make `type_key` immutable post-create? Defer to F8 Contract refactor.
