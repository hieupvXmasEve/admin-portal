# Epic Map: Dynamic Email Templates — Phase 2 (Super Admin Editor)

**Mode:** `standard_feature`
**Source:** [CONTEXT.md](CONTEXT.md) D1-D9, [approach-p2.md](approach-p2.md), P1 deferred_beads in [.khuym/state.json](../../.khuym/state.json)
**Architecture / reality basis:** P1 shipped 2026-05-16 as commit `07b6a3cf`. Storage table, `DbEmailContentProvider`, observer, provisioner, parity test, and feature-flag fallback are all live on `dev`. `EditorContent.vue` (TipTap WYSIWYG with variable picker) already exists. `User::hasSystemRole('super_admin')` is the super-admin check pattern.

## Feature Outcome (when all epics finish)

Super Admin signs in, opens `/admin/notification-templates`, picks a campus + one of the 4 type_keys, edits subject + HTML body in a TipTap WYSIWYG with a variable picker fed from `NotificationTemplateTypeKey::availableVariables()`. Save validates that every `{{var}}` is on the allow-list, sanitizes the HTML via `mews/purifier`, audits via `AuditableModel`. A Preview pane shows the server-rendered output with sample data. A "Send test" button delivers the rendered email to the admin's own inbox. Non-super-admin users get 403 on every endpoint. The next batch reminder run for that campus picks up the edit immediately (Octane-safe). All 11 P1 follow-ups land in the same commit window.

## Epics

| Epic | Capability/Risk Area | Why It Exists | Stories | Proof Needed |
|---|---|---|---|---|
| **A. Authoring surface** | Inertia admin page + Vue editor + save flow | The visible product value. Without it, P1's storage swap delivers nothing user-facing. | A1, A2, A3 | Super Admin loads `/admin/notification-templates`, edits a template, saves; next reminder run uses the edit. |
| **B. Save-time guards** | Variable allow-list validation + HTML sanitization + super-admin policy + Octane safety | Admin write surface introduces XSS, unknown-var, unauthorized-edit, and stale-cache risks. None of these are theoretical once Epic A opens. | B1, B2, B3, B4, B5 | (a) save with `{{unknown_var}}` → 422; (b) save with `<script>` → sanitized; (c) non-super-admin → 403; (d) admin save → next request reads fresh row. |
| **C. Operator tools** | Server-rendered preview + send-test endpoint | Without preview admin saves blindly; without send-test admin cannot smoke-check before students get blasted. | C1, C2 | (a) Preview renders the unsaved draft byte-for-byte the same as production would; (b) send-test delivers a `[TEST]`-prefixed email to the admin's own inbox under a per-user 5/min rate limit. |
| **D. P1 follow-ups carried into P2** | Wrong type_key + cascade-delete safety + missing tests + log hygiene | Small, related quality debt from P1 review (deferred_beads REV-P2-01, -03, -07, -08, -09, -10, -11). Bundling avoids a tiny separate planning round. | D1, D2, D3 | All deferred_beads (except REV-P2-04 + -05 which become A/B stories) close. |

REV-P2-02 + REV-P2-06 are absorbed into Epic B Story B4 (Octane safety). REV-P2-05 is absorbed into Epic B Story B1 (variable schema relocation). REV-P2-04 stays out of scope per [approach-p2.md](approach-p2.md).

## Story Queue

| Story | Epic | Outcome | Depends On | Feasibility Status |
|---|---|---|---|---|
| **B1** | B | `NotificationTemplateTypeKey` enum exposes `availableVariables()`; legacy classes drop the `EmailVariableSchema` interface; consistency test rewritten | none | LOW risk; pure refactor. Pending validating Q4 (grep for outside consumers). |
| **B4** | B | `EmailContentRegistry` cache key includes flag value; `reset()` method wired to `RequestHandled` listener; provider stale-cache test | none | MEDIUM risk; carries Critical Pattern #3. Pending validating Q1 (Octane wiring + `RequestHandled` listener convention). |
| **B2** | B | `mews/purifier ^3.4` installed; `config/purifier.php` with `email_body` allow-list; round-trip snapshot test on the 4 seeded bodies + unsafe-fixture test | none | MEDIUM risk. Pending validating Q2 (style preservation across all 4 bodies). |
| **B3** | B | `NotificationTemplatePolicy` (`update`, `preview`, `testSend`) gated on `hasSystemRole('super_admin')`; registered in `AuthServiceProvider` | none | LOW risk. Pending validating Q3 (super-admin without campus context). |
| **B5** | B | `UpdateNotificationTemplateRequest` FormRequest: validate subject + body_html shape, regex `{{var}}` against allow-list (rejects unknown), runs Purifier in `passedValidation()` | B1, B2, B3 | LOW risk; mechanical. |
| **A1** | A | `NotificationTemplateController` (web): `index()` + `edit()` returning Inertia pages | B3 | LOW risk; conventional. |
| **A2** | A | `NotificationTemplateController` (api/admin): `update()`, `variables()` (GET allow-list by type) | A1, B1, B5 | LOW risk. |
| **A3** | A | `Index.vue` + `Edit.vue` + variable-picker wiring (reuses `EditorContent.vue`); subject input with allow-list chip toolbar | A2 | LOW risk; UI assembly. |
| **C1** | C | `preview()` endpoint + `PreviewPane.vue` rendering unsaved draft via shared `HasTemplateRendering` trait + sample data from enum | A2, B1 | LOW risk. |
| **C2** | C | `testSend()` endpoint + `TestSendButton.vue` + per-user rate limit (5/min) | A2, B3 | LOW risk. Pending validating Q5 (rate-limiter convention). |
| **D1** | D | `SendParentPaymentRemindersAction:35` fix (`payment_reminder` → `parent_payment_reminder`); test updated | none | LOW risk; pre-existing bug. |
| **D2** | D | New migration: `restrictOnDelete` on `notification_email_templates.campus_id` (drop+re-add FK) | none | LOW risk. |
| **D3** | D | Test backfill: `DbEmailContentProvider` exception-path tests; `campus_id`-reached-provider assertions in 4 finance Action tests; new test files for `SendDueItem*`; `Log::error` + `use Log;` patches in `SendDueItemParentRemindersAction` | D1 | LOW risk; mechanical. |

Total: **13 stories** across 4 epics.

## Critical-path & parallelism

```text
Critical path (foundations first):
  B1 (variable enum) ────┐
                          ├──→ B5 (FormRequest) ──→ A2 (api update) ──→ A3 (Vue editor) ──→ C1 (preview)
  B2 (purifier) ────────┘                                                                       │
                                                                                                 │
                                                                                                 ├──→ C2 (send-test)
  B3 (policy)  ──────────────────→ A1 (web ctrl) ──→ (A3) ─────────────────────────────────────┘

  B4 (Octane reset)  — parallel — — — — — — — — — — — — — — — — — — — — — — — — — — — — — — — ┘

  D1, D2, D3 — fully parallel; no deps on Epic A/B/C
```

Realistic swarm width = **3-4** if `khuym:swarming` is used:
- Lane 1: B1 → B5 → A2 → A3 → C1 → C2 (the main editor chain)
- Lane 2: B2 → wired into B5 (sanitizer)
- Lane 3: B3 → A1 (auth + page skeleton)
- Lane 4: B4 (Octane) + D1/D2/D3 (cleanup) — all touch unrelated files

## Current Story To Prepare

**Recommended: `B1` — Variable allow-list relocation to `NotificationTemplateTypeKey` enum.**

Why this one first:

1. **Smallest believable foundation.** Touches 4 type-class files + 1 enum + 1 test + delete 1 interface. ~30 minutes of focused work.
2. **Unblocks the largest fan-out.** Every downstream story that needs the variable list (B5 FormRequest, A2 variables endpoint, A3 Vue picker, C1 preview sample data) reads from one new method. Land it first, parallel everything else.
3. **Concrete proof of the architectural decision** to relocate per validation Q4. If grep finds an outside consumer mid-execution, the story pack can revise before downstream stories commit.
4. **Pure refactor with existing tests.** `EmailVariableSchemaConsistencyTest` becomes the regression net for B1.

Testable exit for B1:
- `NotificationTemplateTypeKey::PaymentReminder->availableVariables()` returns the same keys as the legacy `PaymentReminderEmailContent::availableVariables()` did.
- All 4 type cases pass the same data-coverage assertion.
- `EmailVariableSchema` interface file no longer exists in `app/Modules/Notification/EmailContent/Contracts/`.
- 4 `*EmailContent.php` classes still implement `EmailContentProvider` but NOT `EmailVariableSchema`.
- `./scripts/dev.sh test tests/Unit/Notification` green.

## Approval Summary

- **Mode:** `standard_feature` — multi-layer auth/UI/sanitizer work; not 3 files; not high-risk.
- **Shape:** Epic Map — 4 capability/risk areas are orthogonal, not sequential milestones.
- **13 stories** across 4 epics. Foundation = B1 (variable enum). Critical-path = B1→B5→A2→A3→C1→C2.
- **Current story to prepare next (post-approval):** `current-story-pack-B1.md` — variable allow-list relocation.
- **Validating must clear 5 questions** before bead creation (see [approach-p2.md](approach-p2.md) §Validating Questions).
- **Out of scope:** REV-P2-04 (typed RenderContext refactor), legacy-class deletion (waits for 30-day sunset), all CONTEXT.md `Deferred Ideas`.

## Stop

Planning has chosen the smallest work shape. Approve it before current story prep. Tough work uses an epic map; beads wait until feasibility passes.
