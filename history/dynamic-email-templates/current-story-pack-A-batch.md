# Current Story Pack: A-batch — Admin authoring surface (A1 + A2 + A3)

**Epic:** A. Authoring surface
**Stories:** A1 (web controller + Inertia pages skeleton) + A2 (API controller endpoints) + A3 (Vue editor wiring)
**Mode:** `standard_feature`
**Source:** [approach-p2.md](approach-p2.md) §2 + Risk Map rows on auth + UI
**CONTEXT.md decisions honored:** D3 (WYSIWYG + insert variable), D4 (edit-only, no delete), D7 (Super Admin only)

## Outcome

Super Admin opens `/admin/notification-templates`, sees a list of (campus × type_key)
rows, clicks "Edit" on one, lands on a TipTap WYSIWYG editor pre-populated with the
current `subject` + `body_html`. Variable picker chips read from
`NotificationTemplateTypeKey::availableVariables()`. Save sends to the API endpoint
where `UpdateNotificationTemplateRequest` (from B5) validates + sanitizes.

Three stories executed as one commit because they form a single Vue admin page family
that only makes sense end-to-end.

## Entry State

- B1, B2, B3, B5 done (dependencies for A-batch).
- B4 done (Octane safety wired — admin saves don't go stale).
- D-batch optional; doesn't gate A.
- No `app/Modules/Notification/Http/Web/Admin/NotificationTemplateController.php`.
- No `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php`.
- No `resources/js/pages/Admin/NotificationTemplate/` directory.
- No route under `admin/notification-templates`.
- `resources/js/components/EditorContent.vue` already exists (TipTap, has `commonVariables` prop + `insertVariable()` — `emailMode` style preset). REUSE.

## Exit State

### A1 — Web controller + Inertia pages
1. New `app/Modules/Notification/Http/Web/Admin/NotificationTemplateController.php`:
   - `index(): Response` — returns `Inertia::render('Admin/NotificationTemplate/Index', ['templates' => ...])` with a paginated grid of all templates (campus + type_key + updated_at + updated_by) ordered by campus then type_key.
   - `edit(NotificationEmailTemplate $template): Response` — returns `Inertia::render('Admin/NotificationTemplate/Edit', ['template' => ..., 'variables' => $template->type_key->availableVariables()])`.
   - Both use `$this->authorize('view', $template)` / `viewAny`.
2. New `resources/js/pages/Admin/NotificationTemplate/Index.vue` (reuse shadcn-vue Card + Table primitives from existing admin pages).
3. New `resources/js/pages/Admin/NotificationTemplate/Edit.vue` (uses `EditorContent.vue` with `emailMode` + `commonVariables` from props).

### A2 — API endpoints
4. New `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php`:
   - `update(UpdateNotificationTemplateRequest $request, NotificationEmailTemplate $template): JsonResponse`:
     - Calls `$template->update($request->validated() + ['updated_by_user_id' => $request->user()->id])`.
     - Returns `ApiResponse::success(['template' => $template->fresh()])`.
   - `variables(string $type_key): JsonResponse`:
     - Looks up `NotificationTemplateTypeKey::tryFrom($type_key)` → 404 if missing.
     - Returns `ApiResponse::success(['variables' => $case->availableVariables()])`.
5. Routes appended to `routes/web/notifications.php` for `index` + `edit` (Inertia) and `routes/api/admin.php` for `update` + `variables` + `preview` (placeholder for C1) + `test-send` (placeholder for C2).

### A3 — Vue editor wiring
6. `Edit.vue` populates `EditorContent.vue` with:
   - `:modelValue="form.body_html"`
   - `emailMode` enabled
   - `:commonVariables="variables.map(([name, meta]) => ({ name, description: meta.label }))"`
7. Subject input (plain `<Input>` from `@/components/ui/input`) above the editor; same variable picker chips wire `insertVariable` to the subject field too.
8. Save button uses Inertia's `useForm` PUT to `route('api.admin.notification-templates.update', { template: template.id })` — on success, toast via `vue-sonner` and refresh form.
9. On 422 from B5's validator, errors display inline (per existing Inertia error pattern).

### Composite
10. `./scripts/dev.sh test tests/Feature/Notification` — green (new tests for A1/A2 controllers below + B3/B5 already covering auth/validation).
11. `./scripts/dev.sh npm run type-check` — clean (no Vue TS errors).
12. `./scripts/dev.sh npm run lint` — clean.
13. `./scripts/dev.sh artisan ziggy:generate` — regenerates route helpers.
14. Manual smoke: Super Admin (`User::factory()->superAdmin()`) navigates to `/admin/notification-templates`, sees the list, opens one, edits subject + body via TipTap, clicks Save → toast OK → reload shows the saved content.

### Tests
15. New `tests/Feature/Notification/Http/Web/NotificationTemplateControllerTest.php` (3 cases):
    - **(a)** Super Admin GET `/admin/notification-templates` → 200; Inertia component = `Admin/NotificationTemplate/Index`.
    - **(b)** Super Admin GET `/admin/notification-templates/{id}/edit` → 200; Inertia component = `Admin/NotificationTemplate/Edit`; props contain the variables for that type.
    - **(c)** Non-super-admin → 403.
16. New `tests/Feature/Notification/Http/Api/NotificationTemplateApiTest.php` (3 cases):
    - **(a)** Super Admin PUT `/api/v1/admin/notification-templates/{id}` with valid payload → 200; row updated; `updated_by_user_id` set; ApiResponse envelope.
    - **(b)** Same with unknown `{{var}}` → 422 (B5 validator).
    - **(c)** Super Admin GET `/api/v1/admin/notification-templates/variables/payment_reminder` → 200; payload matches enum's `availableVariables()`.

## Files Likely Touched

| File | Action | Story |
|---|---|---|
| `app/Modules/Notification/Http/Web/Admin/NotificationTemplateController.php` | CREATE | A1 |
| `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` | CREATE | A2 |
| `routes/web/notifications.php` | EDIT — append admin/notification-templates routes | A1 |
| `routes/api/admin.php` | EDIT — append notification-templates API routes | A2 |
| `resources/js/pages/Admin/NotificationTemplate/Index.vue` | CREATE | A1 |
| `resources/js/pages/Admin/NotificationTemplate/Edit.vue` | CREATE | A1 + A3 |
| `tests/Feature/Notification/Http/Web/NotificationTemplateControllerTest.php` | CREATE | A1 |
| `tests/Feature/Notification/Http/Api/NotificationTemplateApiTest.php` | CREATE | A2 |

**Total: 6 CREATE + 2 EDIT = 8 file ops.**

## Feasibility Assumptions

| Assumption | Risk | Proof |
|---|---|---|
| Inertia v3 + Vue 3 admin page conventions clear | LOW | Existing `resources/js/pages/Admin/EmailTemplate/Index.vue` is the reference; same shadcn-vue primitives. |
| `EditorContent.vue` `emailMode` style preset works for the seeded HTML | LOW | EditorContent.vue:312-319 sets email-client-safe defaults. |
| Route-model binding for `NotificationEmailTemplate` works | LOW | Standard Laravel; no special config needed when the model is in a non-default namespace if the binding type-hint is explicit. |
| Variable picker dropdown shape compatible with `commonVariables` prop | LOW | EditorContent.vue:51 expects `Array<{name, description}>`. Adapter is one map. |
| `vue-sonner` is wired for toasts | LOW | Already in `package.json` (P1 discovery). Existing admin pages use it. |
| `ApiResponse::success` envelope per CLAUDE.md | LOW | Project convention; existing admin API controllers follow it. |

## Verification (Done-When)

1. `./scripts/dev.sh test tests/Feature/Notification/Http/Web/NotificationTemplateControllerTest.php` → 3 cases green.
2. `./scripts/dev.sh test tests/Feature/Notification/Http/Api/NotificationTemplateApiTest.php` → 3 cases green.
3. `./scripts/dev.sh test tests/Feature/Notification tests/Unit/Notification` → regression green.
4. `./scripts/dev.sh npm run type-check` → no errors.
5. `./scripts/dev.sh npm run lint` → no new errors.
6. `./scripts/dev.sh artisan about > /dev/null` exit 0.
7. `docker exec swinx-app-dev vendor/bin/pint --test <touched PHP files>` clean.

## Out of Scope

- **Preview pane** — that's C1.
- **Send test button** — that's C2.
- **Bulk operations** (multi-select, multi-edit) — not in D-decisions, not in CONTEXT.md.
- **CRUD beyond edit** — D4 says no delete; index + edit only.
- **Inline edit on index page** — rejected in approach-p2.md §7.

## Bead Mapping

Pending validation. Depends on B1+B3+B5 done. C1+C2 placeholders in routes/api/admin.php can be stub endpoints returning 501 until those packs ship. ~3 hours for the combined batch (one worker, sequential within A).
