# Validation Report - Dynamic Email Templates

**Date:** 2026-05-16
**Mode:** `standard_feature`
**Shape under review:** [phase-plan.md](phase-plan.md) (P1 + P2)
**Inputs read:** [CONTEXT.md](CONTEXT.md), [discovery.md](discovery.md), [approach.md](approach.md), [phase-plan.md](phase-plan.md)

## Reality Gate Report

```
Mode:           standard_feature
Current work:   P1 - DB-backed render parity (replace 4 EmailContentRegistry providers with a DB-backed provider + seed)
MODE FIT:       PASS
REPO FIT:       PASS
ASSUMPTIONS:    PASS WITH CONSTRAINTS
SMALLER PATH:   PASS
PROOF SURFACE:  PASS
Decision:       proceed to feasibility matrix
```

Evidence:

- **Mode fit:** Multi-layer change (DB migration + seeder + provider swap + 4 Action edits + sanitizer dep + admin UI), `small_change` insufficient. Blast radius reversible at the provider-binding level (rebind 4 keys), `high_risk_feature` overstates.
- **Repo fit:** Every assumed seam exists and was inspected -
  - `EmailContentRegistry` ([EmailContentRegistry.php](app/Modules/Notification/EmailContent/EmailContentRegistry.php))
  - `EmailContentProvider` interface ([EmailContentProvider.php](app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php))
  - 4 Type providers under [app/Modules/Notification/EmailContent/Types/](app/Modules/Notification/EmailContent/Types/)
  - `EmailTemplate` model with `render()` + `extractVariables()` ([EmailTemplate.php:114-166](app/Models/EmailTemplate.php))
  - `AuditableModel` ([AuditableModel.php](app/Models/AuditableModel.php))
  - `EditorContent.vue` with `commonVariables` + `insertVariable()` ([EditorContent.vue:51-66, 185-187](resources/js/components/EditorContent.vue))
  - `HandleOutboxEventAction::buildRenderedEmail()` already wraps the resolve+render in try/catch with logged fallback ([HandleOutboxEventAction.php:113-137](app/Modules/Notification/Actions/HandleOutboxEventAction.php))
  - `RenderedEmailChannelAdapter` reads pre-rendered fields ([RenderedEmailChannelAdapter.php:18-37](app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php))
- **Smaller path:** A spike has no single yes/no question gating the path; integration seam is already proven by the existing try/catch fallback. Direct/small modes can't carry the schema change.
- **Proof surface:** Each validating question maps to a concrete artifact (golden-file test, code walk, snapshot test, composable demo).

## Feasibility Matrix

| Part / Assumption | Risk | Proof Required | Evidence | Result |
|---|---|---|---|---|
| **Q1. Render parity post-swap** | MEDIUM | Seeded `{{var}}` template renders byte-equivalent to legacy provider output for a fixed fixture | The render mechanism (`str_replace("{{$key}}", value, $content)`) currently lives in `EmailTemplate::renderContent()` ([EmailTemplate.php:130-137](app/Models/EmailTemplate.php)). After the 2026-05-16 user revision the same logic is **extracted into `App\Modules\Notification\Concerns\HasTemplateRendering` trait**, consumed by both `EmailTemplate` (existing) and `NotificationEmailTemplate` (new). Identical mechanism to legacy heredoc interpolation. Parity reduces to seed-content fidelity, not engine semantics. **Action:** P1 ships a golden-file test seeded from each `*EmailContent.php::htmlBody($fixture)` and asserts `assertEquals($legacy, $dbRendered)` with whitespace-normalised diff. | **READY (with golden-file test bead in P1)** |
| **Q2. Campus context flow** | LOW (was MEDIUM) | Each of 4 Actions has `$student->campus_id` accessible at the `subject()/htmlBody()` call site | All 4 Actions already pass `campusId: $student->campus_id` to `EmailService::sendSingleEmail`: [SendPaymentRemindersAction.php:63](app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php), [SendParentPaymentRemindersAction.php:74](app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php), [SendDueItemRemindersAction.php:81+146](app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php), [SendDueItemParentRemindersAction.php:81+143](app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php). Adding `'campus_id' => $student->campus_id` to the existing `$contentData` array literal is a 1-line edit per Action. | **READY** |
| **Q3. Sanitizer compatibility** | LOW (was MEDIUM) | `mews/purifier` installs cleanly on Laravel 13 + PHP 8.3 and preserves inline `style=` for table emails | Packagist `p2/mews/purifier.json`: **3.4.4** -> `illuminate/support: ^5.8 \| ... \| ^13.0`, `php: ^7.2 \| ^8.0`. Underlying `ezyang/htmlpurifier v4.19.0` -> php up to 8.5. **Constraint:** default config strips inline CSS; must configure `'CSS.AllowedProperties'` to keep the email-client-critical properties (`border, border-collapse, color, padding, margin, font-family, font-size, text-align, width, background-color`) and `'HTML.Allowed'` to keep `style` attributes. P1 ships a snapshot test: for each seeded body, `assertEquals(\Purifier::clean($body), $body)` after the config is in place. | **READY WITH CONSTRAINT** (purifier config required in P1) |
| **Q4. Variable endpoint shape for FE** | LOW | `EditorContent.vue` can be fed per-type variable list from a single GET response | Confirmed by code read: prop `commonVariables: Array<{ name: string; description: string }>` (EditorContent.vue:51), default fallback (EditorContent.vue:61-66), drives `insertVariable(variable.name)` in the Quick-insert pills (EditorContent.vue:185-187, 282-285). Feeding from `GET /admin/notification-templates/variables/{type_key}` is a one-liner: `<EditorContent :commonVariables="variables" emailMode>`. | **READY** |
| **Q5. Render-once-outside-loop performance (new from probe)** | MEDIUM | DB provider does not introduce N+1 query when Actions iterate invoices | The 4 finance Actions resolve `EmailContentRegistry` **once before the loop** (e.g. [SendPaymentRemindersAction.php:29](app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php)), then call `subject($data)/htmlBody($data)` N times in the loop. A naive `DbEmailContentProvider` would query `email_templates` per call. **Mitigation:** the provider memoises by `(type_key, campus_id)` in a request-scoped array. Batches typically iterate within one campus (the operator runs reminders campus-by-campus), so the memo usually has 1 hit per batch. **Action:** include a `Cache::driver('array')` or simple property cache inside the provider. Bead-level. | **READY WITH CONSTRAINT** (per-request memo required in P1) |
| **Q6. Outbox `$data` campus injection (new)** | LOW | `HandleOutboxEventAction::buildEmailData()` can put `campus_id` into `$data` | `buildEmailData()` ([HandleOutboxEventAction.php:145-167](app/Modules/Notification/Actions/HandleOutboxEventAction.php)) already receives `DomainEventEnvelope $envelope` with `$envelope->campusId` (line 42). Adding `$data['campus_id'] = $envelope->campusId;` before return is a 1-line edit. | **READY** |

### Feasibility Result

**READY WITH CONSTRAINTS.** No `NO` result, no spike required.

Recorded constraints carried into execution:

1. **Q1 constraint:** P1 must include a golden-file parity test with a frozen fixture per type_key; failure blocks P1 demo.
2. **Q3 constraint:** `config/purifier.php` ships with explicit `HTML.Allowed`, `CSS.AllowedProperties`, and `Attr.AllowedFrameTargets`; snapshot test guards the four seeded bodies.
3. **Q5 constraint:** `DbEmailContentProvider` memoises template lookups by `(type_key, campus_id)` per request lifecycle.

## Integration Readiness

**PASS.**

- The `EmailContentRegistry::resolve()` -> `EmailContentProvider::subject()/htmlBody()/textBody()` contract is unchanged; the new `DbEmailContentProvider` satisfies the same interface. No call-site signature change.
- `RenderedEmailChannelAdapter::send()` reads pre-rendered fields from `NotificationDelivery` ([RenderedEmailChannelAdapter.php:18-37](app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php)); in-flight outbox events after deploy still ship with their captured HTML. No backwards-compatibility break.
- `HandleOutboxEventAction::buildRenderedEmail()` already has try/catch fallback ([HandleOutboxEventAction.php:128-136](app/Modules/Notification/Actions/HandleOutboxEventAction.php)) - if the DB lookup fails on any type_key, the registry still has the old PHP provider class binding **available** (we keep them as fallback through P1 and remove only after P2 verifies).
- `AuditableModel` already wires Spatie activitylog with campus-aware log name; D5 audit trail is free with no extra wiring.

## Current Story / Work Readiness

**NOT YET — mode-required artifact missing.**

- The phase plan was presented and is implicitly approved by your `/validating` invocation (I will flip `approved_gates.work_shape` and `approved_gates.phase_plan` to `true` in state).
- **Planning skill step 6 ("Prep: after approval, write only current story/work artifacts") was not yet run.** The mode-required `phase-1-contract.md` + `story-map-phase-1.md` do not exist in `history/dynamic-email-templates/`.
- Per `references/validation-reference.md`:
  - Step 6 requires "entry, exit, verification, scope, and assumptions are executable" -- needs the contract.
  - Step 7: "if beads are required but absent after READY, return to planning to create only current-story/work beads, then resume."

Required follow-up before approval is reachable:

1. Return to `khuym:planning` to write **only** P1 artifacts:
   - `history/dynamic-email-templates/phase-1-contract.md` (entry/exit/demo/stories/out-of-scope/success/pivot)
   - `history/dynamic-email-templates/story-map-phase-1.md` (dependency diagram, story table, story-to-bead mapping pending)
2. P1 stories (informational - planner finalises):
   - S1.1 - schema + types extension (`add campus_id + 4 finance TYPE_*` to `email_templates`)
   - S1.2 - per-type `availableVariables()` schema on each `EmailContentProvider`
   - S1.3 - seeder migration pulling current HTML into `(campus_id, type)` rows
   - S1.4 - `DbEmailContentProvider` + registry rebind + per-request memo
   - S1.5 - `campus_id` injection into `HandleOutboxEventAction::buildEmailData()` + 4 finance Actions
   - S1.6 - golden-file parity test (Q1 proof)
   - S1.7 - `mews/purifier` install + email allow-list config + round-trip snapshot test (Q3 proof)
3. Return here for bead review against the story map.

## Bead Review

**N/A this iteration.** `.beads/` is empty; beads are created only after current-story/work artifacts pass readiness (step 7 of the validation protocol). Bead review happens on the next validating pass after planning writes the P1 contract + story map.

## Unresolved Concerns

- **Concern A (timing of legacy class deletion):** [approach.md](approach.md) keeps the 4 `EmailContent\Types\*EmailContent.php` classes for golden-file reference + `availableVariables()` schema. Cleanup is a post-P2 follow-up. Planning should record this explicitly in the P2 contract or carry it to the deferred list.
- **Concern B (rollout safety net):** approach.md mentions a feature-flag escape to fall back to legacy providers; the env/config key was not named. Planning should pick the key (e.g. `notifications.use_db_templates`) and document its default + when it can be removed (after P2 ships and one billing cycle of reminders passes cleanly).
- **Concern C (existing `/systems/email-templates` admin UI):** ~~that page also lists `EmailTemplate` rows. After adding the 4 finance `TYPE_*` constants, those rows will appear there too unless we filter.~~ **RESOLVED (2026-05-16) by storage revision.** Finance templates now live in a separate `notification_email_templates` table; the existing `EmailTemplate`-backed page is untouched and needs no filter. Zero code change required on the academic admin UI.

Concerns A and B are addressed inline in `phase-1-contract.md`. Concern C is closed.

## Decision

```
READY WITH CONSTRAINTS - blocked on planning artifacts only
```

**Outcome:** feasibility passes. Cannot issue final approval until `phase-1-contract.md` + `story-map-phase-1.md` exist.

Hand off back to `khuym:planning` with the request: "Write P1 current-phase contract + story map; address concerns A/B/C in the contract." Then resume validating for bead review + approval.
