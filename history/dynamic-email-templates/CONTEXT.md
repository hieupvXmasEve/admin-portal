# Dynamic Email Templates - Context

**Feature slug:** dynamic-email-templates
**Date:** 2026-05-16
**Exploring session:** complete
**Scope:** Standard
**Domain types:** SEE, READ, ORGANIZE

## Feature Boundary

Replace the four hard-coded HTML email bodies in `app/Modules/Notification/EmailContent/Types/`
(`payment_reminder`, `parent_payment_reminder`, `dng_payment_pushed`, `dng_payment_received`)
with database-backed templates that Super Admins can edit through a WYSIWYG admin screen.
Each campus has its own bilingual (EN+VI) template per type, fed by a backend-defined
variable allow-list. Out of scope: adding new email events, academic/identity templates,
version history, or any per-language separation.

## Locked Decisions

These are fixed. Planning must implement them exactly.

- **D1:** V1 scope = exactly the 4 existing types registered in
  `App\Modules\Notification\EmailContent\EmailContentRegistry`
  (`payment_reminder`, `parent_payment_reminder`, `dng_payment_pushed`,
  `dng_payment_received`). No new email events are introduced. "Tạo phí thành công" maps
  to existing `dng_payment_pushed`; "thanh toán phí thành công" maps to
  `dng_payment_received`; "nhắc nhở nộp học phí" maps to `payment_reminder` +
  `parent_payment_reminder`.

- **D2:** Each template stores **one combined EN+VI body** plus one combined subject,
  matching today's layout (EN block above, VI block below). No locale split, no per-language
  rows.

- **D3:** Admin editor = **WYSIWYG rich-text editor** with an "Insert variable" picker.
  Admin must not need to write HTML. Subject is a plain-text input with the same variable
  picker.

- **D4:** Lifecycle = **always exactly one active record per `(campus_id, type_key)`**.
  No delete, no disable, no draft state. Seeded automatically on migration; admin only edits.

- **D5:** Save = **overwrite in place** with audit trail through `AuditableModel`.
  No version history, no rollback in v1.

- **D6:** Templates are **campus-scoped**. Storage key is `(campus_id, type_key)`. Each
  campus has its own copy seeded from the current hard-coded HTML.

- **D7:** Authorization = **Super Admin only**. Super Admin edits templates of any campus;
  no Finance Officer or other role gets access in v1.

- **D8:** Variable allow-list = **backend-owned per `type_key`**. Save validates the
  rendered template (subject + body) and **rejects** if any `{{var}}` is not in the
  allow-list for that type. Allow-list variables are not individually required — admin can
  omit any variable from the layout.

- **D9:** Editor screen ships with **Preview pane** (render with BE-provided sample data)
  and **Send test email** (delivers preview to the editing admin's own email). No
  "Restore to default" in v1.

## Specific Ideas And References

- Existing hard-coded HTML in
  [PaymentReminderEmailContent.php](app/Modules/Notification/EmailContent/Types/PaymentReminderEmailContent.php)
  is the canonical source for the seeded `payment_reminder` body — EN block on top, VI
  block below, bordered table for invoice/balance/due-date, payment-step instructions, the
  `[Asia Việt Nam]` brand prefix in subject. The other three types follow the same shape.
- Allow-list inferred from current providers — e.g. `payment_reminder` exposes
  `student_name`, `student_code`, `semester_code`, `invoice_code`, `balance_formatted`,
  `due_date`. Planning must enumerate the full list per type from each provider's
  `htmlBody($data)` consumers.
- "Tạo phí thành công" in the user's vocabulary = today's `dng_payment_pushed`
  (DNG request created → student notified there is a payable invoice).

## Existing Code Context

### Reusable Assets

- [app/Modules/Notification/EmailContent/EmailContentRegistry.php](app/Modules/Notification/EmailContent/EmailContentRegistry.php) — registry mapping
  `type_key → EmailContentProvider class`. Single integration seam; replace resolved
  providers with a DB-backed implementation and the rest of the pipeline stays intact.
- [app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php](app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php) — interface (`subject`, `htmlBody`, `textBody`); a single DB-backed
  provider can satisfy it for all four types.
- [app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php](app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php) — already sends pre-rendered
  subject+html via `EmailService::sendSingleEmail` with `campus_id` context. No change
  needed downstream.
- [app/Models/EmailTemplate.php](app/Models/EmailTemplate.php) — pre-existing template model with `{{var}}` rendering,
  version/parent_id columns, `extractVariables()`, `AuditableModel` base. Planning to
  decide whether to extend (add `campus_id`, repurpose `type` to registry keys, ignore
  unused version columns) or create a separate `notification_templates` table.
- [app/Mail/GenericEmail.php](app/Mail/GenericEmail.php) + [resources/views/emails/generic.blade.php](resources/views/emails/generic.blade.php) — generic envelope used by `EmailService`.
- `AuditableModel` base class — drop-in audit log per D5.

### Established Patterns

- **Registry pattern**: `EmailContentRegistry` already centralizes type→provider binding;
  swap the bound class without touching call sites.
- **Inertia v3 + Vue 3 + shadcn-vue** for admin screens (see
  [docs/design-guidelines.md](docs/design-guidelines.md) and existing admin pages).
- **Campus scoping**: most finance/notification tables already carry `campus_id`; follow
  the same pattern (FK + unique index on `(campus_id, type_key)` + scoping in Policy).
- **ApiResponse envelope** for any JSON admin endpoints (per `docs/rules/backend.md`).
- **Action/Query split** under `app/Modules/Notification/` per `docs/rules/architecture.md`.

### Integration Points

- Producers that call `EmailContentRegistry::resolve($typeKey)`:
  - [app/Modules/Notification/Actions/HandleOutboxEventAction.php](app/Modules/Notification/Actions/HandleOutboxEventAction.php) (main path; pre-renders subject+html
    into `NotificationDelivery` rows).
  - [app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php](app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php).
  - [app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php](app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php).
  - [app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php](app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php).
  - [app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php](app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php).
  All resolve a `type_key` with `$data` array and call `subject()` / `htmlBody()` /
  `textBody()`. Each call site already knows the recipient's `campus_id` via the
  student/parent relation — planning will confirm it is passed to the provider so the
  right campus template is selected.
- Admin email screen scaffolding exists at
  [app/Http/Controllers/Api/V1/Admin/EmailController.php](app/Http/Controllers/Api/V1/Admin/EmailController.php) (currently around the
  `EmailTemplate` model, not the finance content). Reuse the admin URL/feature-flag
  patterns; do not assume the existing CRUD covers this use case.

## Canonical References

- [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md) — module layout, naming, Laravel 13 + Inertia v3 rules.
- [docs/system-architecture.md](docs/system-architecture.md) — module boundaries; templates belong under
  `app/Modules/Notification/` (storage + admin endpoints) since the registry lives there;
  Finance only consumes via the registry.
- [docs/rules/architecture.md](docs/rules/architecture.md) — Actions vs Queries vs Http split.
- [docs/rules/naming.md](docs/rules/naming.md) — table/column/Action/Controller conventions.
- [docs/code-standards.md](docs/code-standards.md) — coding gates.
- [docs/design-guidelines.md](docs/design-guidelines.md) — FE component standards (shadcn-vue primitives,
  Inertia patterns).
- [docs/inertiajs-vue-info.md](docs/inertiajs-vue-info.md) — v3 API constraints for the admin page.

## Outstanding Questions

### Resolve Before Planning

(none — all product decisions are locked above)

### Deferred To Planning

- [ ] Storage model decision: **extend `EmailTemplate`** (add `campus_id`, repurpose
      `type` to registry keys, ignore unused version columns) **vs new
      `notification_templates` table** scoped to the Notification module — planner
      evaluates impact on existing `EmailTemplate` consumers, AuditableModel wiring, and
      migration cost.
- [ ] Allow-list source of truth: extend `EmailContentProvider` (or sibling interface)
      with `availableVariables(): array<string, array{label: string, sample: mixed}>` so
      both validator (save) and FE variable picker + preview consume the same map.
- [ ] Seeding plan: extract current PHP HTML bodies into a database seeder/migration,
      generate one row per `(existing campus, type_key)` so every campus boots with a
      working default.
- [ ] WYSIWYG editor library choice (TipTap vs CKEditor vs Tiptap+Vue starter) — must
      support HTML output, inline styles (current emails rely on them), and a
      custom "Insert variable" toolbar action; respect `docs/inertiajs-vue-info.md`
      bundling/SSR considerations.
- [ ] Subject input UX: plain-text field with the same variable picker; ensure variable
      validation runs on subject too.
- [ ] HTML sanitization on save: WYSIWYG output is admin-authored but reaches external
      recipients — settle on an allow-list HTML sanitizer (eg. `mews/purifier` or
      `voku/anti-xss`) and document what tags/styles are kept (the existing templates use
      `table`, inline `style=`, `<a>`).
- [ ] Test-send mechanism: reuse `EmailService::sendSingleEmail` with the editing admin's
      email and a `[TEST]` subject prefix; decide whether sample data comes from BE
      (one canonical sample set per type) or from the current admin draft data.
- [ ] Preview rendering: same `render()` pipeline as production send to guarantee parity
      (likely shared service method) so what admin sees = what student gets.
- [ ] Migration of currently in-flight outbox events: confirm that swapping
      `EmailContentRegistry` bindings after deploy will not break pending
      `NotificationDelivery` rows (rendered fields are already stored, so old in-flight
      rows keep their pre-rendered HTML — should be safe; planner confirms).
- [ ] After dynamic templates are live and verified, do we delete the four
      `EmailContent\Types\*EmailContent.php` classes, or keep them as a code-level
      reference for the seeded defaults? (Affects deletion safety + future re-seed.)
- [ ] Authorization wiring: which Gate/Policy name and middleware to use for the
      Super Admin-only restriction; align with existing super-admin permission
      conventions.

## Deferred Ideas

- "Restore to default" button — out of v1 per D9.
- Per-language (locale) record split — out per D2.
- Version history + rollback UI — out per D5; AuditableModel is enough for audit.
- Disable/delete template flow — out per D4.
- Adding new email events ("Charge created", refund, refund success, etc.) — out per D1.
- Extending dynamic templates to academic/identity emails (welcome, grade, hold,
  enrollment confirmation, etc.) — out per D1.
- Plain-text body variant alongside HTML — out (current providers also return null for
  text body).
- Per-program or per-role template overrides — not requested.
- Inline assets / attachments (QR images, invoice PDFs) — not requested; today's
  rendered emails are pure HTML.
- Role-based access beyond Super Admin (Finance Officer, etc.) — out per D7.

## Handoff Note

CONTEXT.md is the source of truth. Decision IDs D1-D9 are stable. Planning reads locked
decisions, code context, canonical references, and the Deferred-To-Planning questions
before proposing phases/stories. Validating and reviewing must use D1-D9 for coverage
and UAT.
