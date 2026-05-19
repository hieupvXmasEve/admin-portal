# Learnings — dynamic-email-templates P3 / M3a

**Feature slug:** dynamic-email-templates
**Phase:** P3 Wave-3, story M3a (test-send button → outbox emit)
**Mode:** high-risk (parent epic)
**Closed:** 2026-05-19
**Source artifacts:** `history/dynamic-email-templates/current-story-pack-M3a.md`, `m3-repair-memo.md`, validation reports (v1 reject, v2 approve), review report (3 P1 + 6 P2)

---

## Patterns

### P1. Outbox pipelines should accept a pre-rendered envelope key, not force registry rendering

**Situation.** The Notification outbox pipeline (`HandleOutboxEventAction → PersistIntentAction → SendNotificationDeliveryJob → RenderedEmailChannelAdapter`) re-rendered every email via `EmailContentRegistry` keyed on `type_key`. The admin "test-send" button renders the admin's DRAFT content (transient `NotificationEmailTemplate`), not the persisted one. Routing test-send through the registry would have delivered persisted content instead of draft, defeating the feature.

**Root cause.** Registry coupling assumed every emit is registry-bound. Ad-hoc rendered content had no envelope path.

**Future rule.** Outbox-style pipelines should support an "ad-hoc rendered" envelope key (`payload.rendered_email`) that bypasses the renderer. Implementing handler is a ~10-line guard at the top of `buildRenderedEmail`. Avoids polluting the type_key registry with one-off keys like `test_send_adhoc`. Generalizes to any future caller that has pre-rendered HTML (e.g. AI-composed messages, imported campaigns).

---

### P2. Pack split when one story has both proof-of-pattern and cross-module fan-out work

**Situation.** Original M3 pack bundled the test-send button (1 callsite, same module, low risk) with 6 Finance reminder callsites (cross-module, high risk, production traffic). One validation rejected the pack because the bundle made it impossible to assess feasibility without resolving the cross-module Contract decision first.

**Future rule.** When a migration story has both "same-module clean callsite" AND "cross-module fan-out" work, split. The same-module half proves the pattern on staging before the cross-module half touches production traffic. Pack file count is a useful early signal — when ops approach 10 with mixed risk profiles, split.

---

### P3. Sentinel-based negative assertions when positive branch has pre-existing test-infra gap

**Situation.** Option α handler has two branches: short-circuit fires (Case 1) and registry-path fallback (Case 2). Asserting "Case 2 produces registry-derived content" was impossible because `DbEmailContentProvider` requires a seeded `NotificationEmailTemplate` row, which the existing test infra doesn't provide (`HandleOutboxEventRenderedEmailTest:66` fails for the same reason — pre-existing).

**Future rule.** When the positive branch can't be asserted due to a pre-existing infra gap, use a unique-sentinel negative assertion (`rendered_subject !== 'SHORT-CIRCUIT-SENTINEL-XYZ'`) to prove the short-circuit branch did NOT fire. The sentinel must be unique enough that it can ONLY appear if the short-circuit took effect — that asymmetry makes a negative assertion logically strong. Document in code why the positive assertion was infeasible so a future contributor doesn't think the test is weak by laziness.

---

## Decisions

### D1. Cross-module dispatcher exception (F3 Option A)

**Situation.** CLAUDE.md says Finance must not reach `PublishDomainEventAction` directly — instead, depend on a Shared Contract. But 6 cross-module callers already do: `app/Modules/Academic/Actions/PublishCourseStageChangedNotificationAction.php:17`, `app/Modules/Finance/Services/PaymentService.php:13`, `app/Modules/Finance/Dng/Services/DngPaymentService.php:25`, `app/Modules/Finance/Dng/Services/DngWebhookService.php`, `app/Actions/Form/SubmitResponseAction.php:30`, multiple `app/Actions/Query/*.php`.

**Decision.** Option A — accept the established precedent. M3b emitters inject `PublishDomainEventAction` directly. Document the exception in `docs/rules/contracts.md`. A future cleanup epic can migrate ALL callers (current 6 + M3b's new 6) to a dispatcher Contract together.

**Why not Option B (add Contract now).** Adding a single-impl interface for one new caller is YAGNI; it doesn't migrate the 6 existing violations, so CLAUDE.md compliance gain is performative. Better to do both migrations in a dedicated cleanup story when the second implementation is needed.

**Future rule.** When an internal Action has ≥3 established cross-module callers, the de-facto contract IS the Action. Documenting the exception in `docs/rules/contracts.md` beats adding an interface with one implementation. A separate cleanup epic migrates all callers together if desired.

---

### D2. Repair memo as scope-discipline artifact when validation surfaces decisions

**Situation.** Validation produced 3 "BLOCKING" findings on M3. Planning needed to: (a) re-grep to verify each, (b) lock decisions for those that remained valid, (c) amend the pack. Doing this inline in the pack body would have mixed analysis with spec.

**Decision.** Write `m3-repair-memo.md` separately. Pack remains a clean spec; memo carries the decision audit trail.

**Future rule.** When validation rejects with ≥2 distinct findings, write a `<story>-repair-memo.md` to surface (a) verification result per finding, (b) the user-facing decision, (c) the locked option per decision. Pack edits become mechanical applications of the memo's locked choices.

---

## Failures

### F1. Validation v1 false-positives (F1 + F2 from validation report)

**Situation.** My first validation pass produced 3 BLOCKING findings on M3:
- F1: "type_keys `due_item_reminder` / `due_item_parent_reminder` missing from registry."
- F2: "`HandleOutboxEventAction::buildEmailData` would clobber Finance payload."
- F3: "Cross-module dispatcher Contract gap."

Re-check against code dropped F1 and F2:
- F1 invalidated: 4 Finance reminders ALL use `payment_reminder` / `parent_payment_reminder` type_keys (verified `SendDueItemRemindersAction.php:25`, etc.) — both registered.
- F2 invalidated: override path is gated on `data['dng_payment_request_id'] !== null`. Existing Finance `$contentData` doesn't carry that key. Concern → not blocker.

**Root cause.** I treated grep-light "the pack assumes X" findings as BLOCKING without re-grepping `app/Modules/Finance/Actions/Operations/Send*Reminders*.php` to verify the assumption was actually false.

**Future rule.** Validation's "BLOCKING" verdict MUST cite at minimum one grep / read result that proves the gap. If a re-grep invalidates the finding, downgrade or drop it before sending the pack back to planning. Don't ping-pong packs over false positives — each round costs a planning + validation cycle. Specifically: when a finding takes the form "the pack assumes X is registered/available/safe," the validator must run the grep that proves X is missing — not just note that the pack didn't include proof.

---

### F2. M3 v1 plan missed that testSend bypasses EmailContentRegistry

**Situation.** Validation v1 of M3a (after the split) initially passed all gates. But when I read `NotificationTemplateController::testSend` carefully, I found it renders draft body via a transient `NotificationEmailTemplate` + `$transient->render($sampleVariables)` — NOT via `EmailContentRegistry`. Routing through the existing handler path would have delivered persisted-template content, not the admin's draft. The pack's "no mapper change required" was correct but incomplete.

**Root cause.** I scout-read the controller's IMPORTS (`use App\Services\EmailService`) but not the BODY of `testSend` deeply enough to notice it bypasses the registry. Planning trusted the spike S2 caller-graph (which correctly identified the callsite) but didn't trace the render call shape at that callsite.

**Future rule.** During validating, for every caller listed in a migration spike, READ the body of the call site — not just the import header — to confirm the render/serialization path matches the new pipeline's assumptions. Specifically: when migrating a `sendEmail`-style callsite to an event-based pipeline, the validator must confirm where the subject/body content comes from at the callsite (registry, template render, request input, mailer template, etc.) — and that the new pipeline can produce equivalent content from the available inputs.

---

### F3. CRLF SMTP-header injection unguarded at validator level

**Situation.** `NotificationTemplateController::testSend` validator: `'subject' => ['nullable', 'string', 'max:500']`. No CRLF rejection. `$request->input('subject')` flows into `payload.rendered_email.rendered_subject` → `EmailService::sendSingleEmail` → SMTP `Subject:` header. `validateEmailContent` (`EmailService.php:183-209`) checks length + spam-phrase patterns but not CRLF. Symfony Mailer often rejects CRLF in headers, but relying on driver behavior is brittle.

**Root cause.** Validator was inherited from the pre-M3a code path (synchronous send via Symfony Mailer) and never reviewed for the new flow. M3a's threat model changed — admin-controlled subject now lands in the SMTP header via a different code path.

**Future rule.** When user-controlled input flows into an SMTP header (Subject:, To:, From:, Reply-To:, etc.), enforce CRLF rejection (`not_regex:/[\r\n]/`) at FormRequest / inline validator level. Don't rely on Symfony Mailer driver-specific encoding behavior. Validating + security-review must check this whenever a controller endpoint's email-flow path changes.

---

### F4. Rate-limiter cache leaks between Pest test files in narrow combined runs

**Situation.** `TestSendOutboxEmitTest.php` and `NotificationTemplateTestSendTest.php` both exercise the `notification-template-test-send` rate-limited route (5/min per user). Each file passes individually. Running them combined in a narrow Pest invocation (`./scripts/dev.sh test <file1> <file2> <file3>`) produces 429 failures on the second file's happy-path test because the rate-limiter cache from the first file's test-sends persisted across the file boundary. Broader test runs (`tests/Feature/Notification`) don't trigger this because Pest groups differently / forks per directory chunk.

**Root cause.** Tests don't `RateLimiter::clear(...)` in `beforeEach`. Cache survives within a single Pest process across files.

**Future rule.** Tests that exercise rate-limited routes MUST call `RateLimiter::clear($limiterKey . ':' . $user->id)` (or `Cache::store('cache')->flush()` if the limiter uses the default cache driver) in `beforeEach`. Without this, the suite is order-dependent and CI flakiness compounds as more tests touch the same limited route.

---

### F5. M3 pack misclaimed C1 Contract resolved the cross-module dispatcher gap

**Situation.** Original M3 pack §"Critical Patterns Applied" said "Finance imports Shared Contract namespace, NOT Notification internal." This conflated two unrelated contract concerns:
- C1 is `EmailContentProvider` — a CONTENT contract for rendering email body/subject. Used INSIDE `HandleOutboxEventAction`, not by Finance.
- The actual gap is that Finance needs to call `PublishDomainEventAction` (a DISPATCHER) from cross-module. C1 doesn't address this.

**Root cause.** Pack author (me) used "Contract" without specifying which surface (content vs dispatcher). Planning didn't notice; validation v1 caught it as F3 (the only finding that survived re-grep).

**Future rule.** When a pack claims "Contract X resolves cross-module reach," the pack MUST specify (a) which surface the contract abstracts (rendering, dispatch, query, write), (b) which existing caller path uses that contract, and (c) which new caller path will use it. Validating: any claim of "Contract resolves cross-module gap" requires citing both ends of the contract usage in the pack body or in a memo.

---

## Out-of-scope items recorded for compounding

- **Pre-existing test failure.** `HandleOutboxEventRenderedEmailTest.php:66` fails because `DbEmailContentProvider` returns null when no `NotificationEmailTemplate` row is seeded. Affects all tests using registry path. Needs a test fixture builder (e.g. `NotificationEmailTemplateFactory::for($typeKey, $campusId)`). Open separately from M3a.
- **Empty-string guard on rendered_email.** `isset` treats empty strings as set. Handler should reject empties explicitly. Open as M3b prerequisite — critical when M3b emits cross-module envelopes.
- **Outbox-row assertion depth in `TestSendOutboxEmitTest`** — currently misses pinning `event_name`, `aggregate_type`, `payload.type_key`, `payload.channels`. Open as test-hygiene story.
- **`runDeliveryJobWithSpy` recipient match.** Doesn't assert email lands at admin's address. Open with above.
- **Doc-comment on `buildRenderedEmail` re: caller-sanitize contract.** Critical reminder for M3b cross-module emitters. Open as P2.
- **Vue OutboxDetail.vue warning comment.** Future-proof against `v-html` use on `payload.rendered_email.rendered_html`. Open as P3.

---

## Promotion candidates → critical-patterns.md

| Pattern | Reason for promotion |
|---|---|
| D1 (cross-module dispatcher exception) | Recurs whenever an internal Action accumulates cross-module callers; CLAUDE.md letter-vs-spirit debate keeps surfacing without a documented threshold. |
| F1 (validation re-check discipline) | Already cost one planning+validation round-trip. Generalizable across all future validating sessions. |
| F3 (CRLF SMTP-header guard) | Security baseline. Applies to any email-flow controller across the repo (not just notifications). |
| P1 (pre-rendered envelope outbox primitive) | Outbox pattern is repo-wide infrastructure. M3b inherits this primitive; future AI-composed / imported messages will too. |

Promoting these 4. P2/P3/D2/F2/F4/F5 remain in this dated entry — useful, but more situational.
