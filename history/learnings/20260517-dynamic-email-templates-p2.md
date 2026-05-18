---
date: 2026-05-17
feature: dynamic-email-templates-p2
categories: [pattern, decision, failure]
severity: standard
tags: [inertia-v3, swarming, sanitization, mass-assignment, worker-orchestration, iframe-sandbox, multi-row-fixtures, review-triage]
---

# Compounding: Dynamic Email Templates (Phase 2 — Super Admin Editor)

P2 added the admin authoring surface (Inertia editor + sanitizer + preview + send-test + super-admin Policy + Octane safety) on top of P1's storage swap. 33 files / 17 new findings across 4 swarm waves. Closes 10 of 11 deferred_beads from P1; queues 4 P2 follow-ups + 6 P3 cleanups; promotes 6 lessons to critical-patterns.md.

---

## Patterns

### Learning: Pack-as-bead with DAG when `.beads/` is absent

**Category:** pattern
**Severity:** critical
**Tags:** [swarming, story-packs, parallelism, dependency-graph]
**Applicable-when:** A standard_feature has 4+ orthogonal capability areas, `.beads/` is empty, and bead-equivalent structure is needed for parallel workers.

#### What Happened

P2's epic-map-p2.md declared 13 stories grouped into 6 packs (B2, B3, B5, A-batch, C-batch, D-batch) with an explicit critical-path DAG: `D-batch ‖ B2 ‖ B3 → B5 → A-batch → C-batch`. Each pack file carried bead-equivalent fields (outcome, entry/exit state, file list, verification commands, feasibility table, 3–9 file ops). All 6 packs landed in sequence across 4 swarm waves with zero re-planning.

#### Root Cause / Key Insight

Packs that encode their own entry/exit/verification let workers pick up cold without holding plan-wide context; a DAG row in epic-map enforces ordering without inter-pack chatter.

#### Recommendation for Future Work

When swarming without `.beads/`, treat `current-story-pack-*.md` as the bead unit. Each pack MUST have: entry-state, exit-state, file ops <10, named verification commands, and a row in a critical-path DAG. Reject packs that exceed 10 file ops or omit the DAG row.

---

### Learning: Inertia `useForm` ↔ response-shape pairing is a runtime-only contract

**Category:** pattern (with paired failure)
**Severity:** critical
**Tags:** [inertia-v3, form-helper, response-shape, runtime-only-failure]
**Applicable-when:** Building any Vue form that hits a Laravel endpoint in an Inertia v3 project.

#### What Happened

A-batch's Edit.vue used `useForm().put()` pointing at the API admin route returning `ApiResponse::success([...])` (raw JSON, no `X-Inertia` header). The Save button always appeared to fail because the Inertia client threw "All Inertia requests must receive a valid Inertia response." API tests via `putJson()` bypassed the client and went green. Caught only at review (F1). Fix added a web PUT endpoint returning `back()` with `Inertia::flash('success', ...)`.

#### Root Cause / Key Insight

`useForm` requires Inertia render/redirect/back responses. Mounting it on an `ApiResponse` JSON endpoint produces a green test suite and a broken UI. The mismatch is invisible to controller-level tests because they bypass the Inertia client.

#### Recommendation for Future Work

For every new Vue form, name the form helper (`useForm` vs `useApi`/`useApiRequest`) AND the response shape (Inertia render/redirect vs ApiResponse JSON) in the story pack. Reject mismatches at validation. Non-navigating modal/drawer forms → `useApi` + vee-validate + Zod hitting JSON API. Page-level navigating forms → `useForm` hitting a web route that returns `back()` + `Inertia::flash`.

---

### Learning: Sibling-endpoint sanitization parity gap

**Category:** pattern (extends P1 Critical Pattern #1)
**Severity:** critical
**Tags:** [xss, sanitization, parity-gap, sibling-endpoints]
**Applicable-when:** Two or more endpoints accept the same untrusted field shape but only one routes it through the sanitization barrier.

#### What Happened

B5's `UpdateNotificationTemplateRequest::passedValidation()` ran `Purifier::clean()` on save. C-batch's `preview()` and `testSend()` in `NotificationTemplateController` accepted the same draft `body_html` and rendered/emailed it raw. Save-path test never exercised the draft path. Caught at review by F2; fix added `Purifier::clean($draftBody, 'email_body')` at entry of both draft methods.

#### Root Cause / Key Insight

The sanitization barrier lived in a FormRequest used only by the save endpoint; sibling endpoints reading the same field bypassed it. Critical Pattern #1 (parity tests need unsafe fixtures) targets render-path parity — this generalizes the same shape to sanitization barriers.

#### Recommendation for Future Work

When N sibling endpoints accept the same untrusted payload, build a column-wise sanitization matrix (endpoint × untrusted-field × sanitizer-called) and require every cell to be `Y`. Extract a shared Action (e.g. `RenderDraftEmailTemplateAction`) so the sanitization step cannot be skipped per-endpoint. Validating gate: "List every endpoint that ingests this payload; confirm each calls the sanitizer."

---

### Learning: Model `booted()` updating-guard pins per-row identity columns as immutable

**Category:** pattern
**Severity:** standard
**Tags:** [mass-assignment, model-guards, immutability, defense-in-depth]
**Applicable-when:** A model has identity columns (e.g. `tenant_id`, `type_key`) that must stay in `$fillable` for CREATE callers but must never change after CREATE.

#### What Happened

F4 flagged `campus_id` + `type_key` in `$fillable` on `NotificationEmailTemplate` as a mass-assignment risk (safe today, one rule addition away from cross-tenant write). Refactoring 12 callers (provisioner, seed, factories, transient render path) to use `forceFill()` was out of scope. Fix added `static::booted()` with `static::updating(fn ($t) => throw new LogicException if isDirty('campus_id') || isDirty('type_key'))`. The guard fires on `updating` (not `saving`) so CREATE goes through untouched.

#### Root Cause / Key Insight

Removing columns from `$fillable` breaks every legitimate CREATE caller; the cheaper, semantically-correct guarantee is a model-layer guard that lets CREATE through but blocks UPDATE on identity columns.

#### Recommendation for Future Work

When identity columns must stay mass-assignable for CREATE, add a `static::booted() { static::updating(fn ...) }` guard that throws `LogicException` on `isDirty()` for those columns. Document why each column needs CREATE-time fillability in the model docblock.

---

### Learning: Worker-time MINOR flags resolve constraints without pack rewrites

**Category:** pattern
**Severity:** standard
**Tags:** [validation, bead-review, worker-time-resolution]
**Applicable-when:** Validation finds ambiguities that depend on facts only visible during execution (seeded data shape, framework auto-discovery behavior, route declarations from a sibling pack).

#### What Happened

validation-report-p2.md issued 4 MINOR flags (BR-D1 parent_name placeholder, BR-B3 explicit `Gate::policy`, BR-B5 route-coupling order, BR-C-batch `render()` method on transient model). All 4 were marked "worker-time decisions, not pack rewrites" — kept validation atomic at "READY WITH CONSTRAINTS" rather than looping back to planning. The orchestrator then inlined each flag's resolution constraint directly into the corresponding story-pack worker prompt (see Decision D2).

#### Root Cause / Key Insight

Not every ambiguity warrants re-planning; some are cheaper to resolve with a grep at execution time. Forcing all unknowns through planning inflates validation churn.

#### Recommendation for Future Work

When validating, classify ambiguities as CRITICAL (block, return to planning) or MINOR (constraint recorded for worker pickup). For MINOR, write a one-line resolution rule the worker can follow (e.g. "grep seeded body for `{{parent_name}}`; if absent, skip").

---

### Learning: `iframe sandbox="allow-same-origin"` is a footgun for admin preview surfaces

**Category:** pattern
**Severity:** standard
**Tags:** [iframe-sandbox, csp, preview-surface, referer-leak]
**Applicable-when:** An admin surface previews untrusted HTML in an `<iframe srcdoc>`.

#### What Happened

C-batch's PreviewPane.vue set `sandbox="allow-same-origin"` on the draft preview iframe. F3 flagged that `<meta http-equiv="refresh" content="0;url=https://attacker/">` in the draft body navigates the iframe and leaks the admin Referer (with full admin URL incl. query). Fix dropped to bare `sandbox=""`.

#### Root Cause / Key Insight

`allow-same-origin` was included reflexively but enables same-origin navigations from inside the sandboxed frame, defeating the security purpose. The canonical bypass is `<meta refresh>`.

#### Recommendation for Future Work

For admin preview iframes rendering untrusted HTML, default `sandbox=""` (most-restrictive). Only add `allow-*` tokens with a documented justification per token. Never include `allow-same-origin` unless the iframe content is trusted-origin.

---

## Decisions

### Learning: Cheaper defense-in-depth at the right layer beats wider refactor

**Category:** decision
**Severity:** standard
**Tags:** [mass-assignment, blast-radius, model-guard, refactor-economics]
**Applicable-when:** A vulnerability has two fixes — one at the entry layer (FormRequest, validator) requiring an N-caller refactor, one at the data layer (model `booted()`) localized to 1 file.

#### What Happened

F4 had two options: (a) remove `campus_id`/`type_key` from `$fillable` (blast radius: 12 callers), (b) add a `booted()` `updating` guard throwing `LogicException` if those columns change post-create. Chose the model guard — same protection, 1 file, no out-of-scope refactor.

#### Root Cause / Key Insight

Defense at the layer where the invariant actually lives (the row's immutability) is both cheaper AND more correct than defense at the input layer (which can drift as new callers appear).

#### Recommendation for Future Work

When fixing a mass-assignment/invariant violation, evaluate model-level `booted()` guards before `$fillable` surgery. Pick the layer that matches the invariant's semantics, not the layer the bug was reported at.

---

### Learning: Worker prompts must encode validating's MINOR flags inline

**Category:** decision
**Severity:** standard
**Tags:** [worker-handoff, swarming, context-isolation, validation-handoff]
**Applicable-when:** Dispatching parallel workers on bead/pack-equivalents after validating produces MINOR worker-time flags.

#### What Happened

Validating produced 4 BR-* MINOR flags. All 4 were resolved cleanly at worker time because the orchestrator encoded each as an explicit constraint inside the corresponding story-pack worker prompt, not buried in `validation-report-p2.md`.

#### Root Cause / Key Insight

Workers receive isolated context per the orchestration protocol; a flag that sits in a separate validation document is invisible to them.

#### Recommendation for Future Work

When validating returns MINOR flags, the orchestrator MUST inline each flag's resolution constraint into the relevant pack/worker prompt before swarm dispatch. "Worker should check the validation report" without inlining = bug guaranteed.

---

### Learning: Reviewer severity must match workflow definition, not specialist judgment

**Category:** decision
**Severity:** standard
**Tags:** [review-triage, severity-calibration, false-p1, workflow-discipline]
**Applicable-when:** A specialist reviewer (test-coverage, security, etc.) rates findings at higher severity than the workflow severity rubric supports.

#### What Happened

The test-coverage agent rated 4 test-gap findings as P1. The orchestrator reclassified to P2 per workflow definition (P1 = production blocker / security breach / data loss). The security reviewer independently confirmed the code those tests would guard is correct today. Reclassification was correct: missing regression guards on verified-correct code is not a ship-blocker.

#### Root Cause / Key Insight

Specialists optimize for their domain (the test-coverage agent treats any untested branch as critical); workflow severity is calibrated for ship/no-ship decisions. The orchestrator owns the calibration.

#### Recommendation for Future Work

The reviewing skill MUST run a severity-reclassification pass against the rubric after specialist outputs. Default: regression-guard test gaps on verified-correct code → P2 max, never P1.

---

### Learning: Inline-fix-now + defer-extraction is the right move when extraction needs DTO/contract design

**Category:** decision
**Severity:** standard
**Tags:** [refactor-deferral, security-fix-vs-cleanup, two-pass-fix]
**Applicable-when:** Sibling endpoints share a duplicated unsafe code path; the right architectural fix is extraction but extraction needs design (DTO shape, error envelope, contract).

#### What Happened

F2 had two options: (a) inline 2-line `Purifier::clean()` at both endpoint entries now, (b) extract `RenderDraftEmailTemplateAction` consolidating sanitize+render+error envelope. Chose (a) inline + defer (b) as F7. Inline patch keeps prod safe immediately; extraction gets proper design time.

#### Root Cause / Key Insight

Security fixes have a different time budget than architectural cleanups. Bundling them creates pressure to ship the cleanup half-designed OR delays the security fix.

#### Recommendation for Future Work

For sibling-call-site security gaps: ship the inline patch in the same commit window; queue extraction as a named follow-up bead with DTO/contract design as part of the bead scope. Never block a security fix on a refactor.

---

### Learning: Inline orchestrator-fix beats worker-respawn when failure is test-scaffold mechanics

**Category:** decision
**Severity:** standard
**Tags:** [worker-rescue, test-scaffold, respawn-economics, formrequest-testing]
**Applicable-when:** A swarm worker stalls on infrastructure mechanics (test harness, scaffold, framework lifecycle), not on the actual feature logic.

#### What Happened

B5 worker stalled debugging FormRequest standalone-invocation pattern — 3 separate scaffold bugs (leftover debug throw, `setParameter` returns void so arrow-fn returned null, missing `Accept: application/json` + `setRedirector(app('redirect'))` to bypass HTTP redirect machinery). The orchestrator fixed all 3 inline rather than respawning. Worker artifact was preserved; tests shipped 5/5 green after the 4 inline edits.

#### Root Cause / Key Insight

Respawning a worker on test-scaffold bugs wastes a full context-restore cycle to re-debug the same mechanics. The orchestrator already has the full picture and the fix is mechanical, not judgmental.

#### Recommendation for Future Work

Distinguish worker-blocker categories: (1) feature-logic confusion → respawn with better context, (2) framework/scaffold mechanics → orchestrator fixes inline, worker resumes. Pre-commit B5-class scaffold patterns (standalone FormRequest invocation) into a reusable test helper for the next feature.

---

## Failures

### Learning: D1 cleanup paired wrong parent name with wrong email — single-row fixture missed it

**Category:** failure
**Severity:** critical
**Tags:** [loop-pairing, multi-row-relations, test-fixture-coverage, regression-via-cleanup]
**Applicable-when:** An Action loops over recipients but pulls a per-recipient display field from `relation->first()` outside the loop.

#### What Happened

`SendParentPaymentRemindersAction:59` used `$student->parentProfiles->first()?->user?->full_name` as `parent_name` while iterating over `extractParentEmails`. For students with ≥2 parent profiles, the email-to and the name-in-body decoupled: parent B receives mail addressed "Dear Parent A." D1 introduced this during the `payment_reminder` → `parent_payment_reminder` cleanup; D-batch test used a single-parent fixture so it stayed green. Caught at review (F5). Fix refactored helper to return `Collection<{email, name}>` and paired per-recipient in the foreach.

#### Root Cause / Key Insight

When recipient-list extraction and display-field resolution diverge in iteration shape, only a ≥2-row fixture catches the mismatch. Single-row factories are not regression-equivalent for per-recipient loops.

#### Recommendation for Future Work

For any per-recipient loop where display fields come from a relation, the test fixture MUST include ≥2 rows of that relation. Make this a Pest expectation `->withMultipleRowsFixture()` or add a validating gate that flags single-row fixtures on per-recipient code paths.

---

### Learning: Orchestrator-provided test scaffold in B5 prompt stalled worker

**Category:** failure
**Severity:** standard
**Tags:** [worker-prompt-hygiene, test-scaffold, route-resolver, orchestrator-handoff]
**Applicable-when:** Orchestrator prescribes a test pattern in a worker prompt that was never executed locally first.

#### What Happened

B5 prompt prescribed `setRouteResolver(fn () => (new Route)->bind()->setParameter(...))`. `Route::setParameter()` returns `void`; the arrow function returned null; route resolver returned null; `$this->route('template')` returned null; FormRequest `authorize()` crashed with a confusing "trying to call `can()` on null." Worker stalled ~10min until watchdog killed.

#### Root Cause / Key Insight

Arrow-function bodies silently swallow `void` returns from fluent chains. Any orchestrator-authored snippet involving fluent chains with void terminators must be verified end-to-end before being pasted into prompts.

#### Recommendation for Future Work

The orchestrator never ships test scaffolds in worker prompts unverified — run the snippet locally or link to a known-green precedent. For any fluent-chain helper, end with an explicit `return $obj;` line, never an arrow-fn one-liner that may terminate on `void`.

---

### Learning: Pre-existing baseline-red tests need a quarantine, not repeated rediscovery

**Category:** failure
**Severity:** standard
**Tags:** [baseline-red, workflow-gap, test-triage, ownership]
**Applicable-when:** A test suite has known-failing tests that are not currently owned.

#### What Happened

7 pre-existing CSRF failures in `NotificationOpsControllerTest` were present throughout P1 + P2. Every worker (B2, B3, A-batch, C-batch) independently rediscovered them via `git stash` to confirm "not caused by my changes." That's 4 wasted triage cycles in P2 alone.

#### Root Cause / Key Insight

Known-failing tests with no owner force every worker to re-prove non-causation. The workflow has no quarantine mechanism (skip-with-reason, baseline-red registry, etc.).

#### Recommendation for Future Work

Add a `tests/baseline-red.md` registry listing known-failing tests with a reason and a tracking bead. Either mark the tests `markTestSkipped('baseline-red: see baseline-red.md#csrf-ops')` until owned, or add a CI matcher that filters baseline-red from the green-bar count. Workers stop spending triage cycles on un-owned red.

---

### Learning: `$fillable` is the wrong layer for invariants

**Category:** failure
**Severity:** standard
**Tags:** [mass-assignment, defense-in-depth, immutable-fields, model-design]
**Applicable-when:** A model has fields that must be immutable post-create (tenant_id, type_key) but ships them in `$fillable` because the provisioner uses `Model::create()`.

#### What Happened

`NotificationEmailTemplate` had `campus_id` + `type_key` in `$fillable`. Safe today only because the FormRequest allows only `subject`/`body_html`. One future rule addition / one new caller using `->update($request->all())` = cross-tenant write. The original B-pack design never flagged this.

#### Root Cause / Key Insight

`$fillable` is a convenience surface that changes whenever a caller is added. Identity fields need a model-level guard or `$guarded` + explicit `forceFill()`.

#### Recommendation for Future Work

Validating gate for any new model: list fields that are immutable post-create; reject if they appear in `$fillable` without a paired `updating` guard (see paired Pattern entry for the guard recipe).

---

## Cross-Phase Continuity Notes

- **Pattern #1 (P1 promoted)** "parity tests need unsafe-character fixtures" generalized in P2 via F2/P4 to "sibling-endpoint sanitization parity". Combined rule: when any sanitization or escaping step lives in a barrier, the barrier must be diffed column-wise across all endpoints that ingest the same payload, not just the one the barrier was written for.
- **Pattern #3 (P1 promoted)** "singleton stateful providers leak across requests" was resolved in P2 commit `c625be42` (B4). The `RequestHandled` reset hook + flag-aware cache key shipped. The Octane-trap pattern stays valid for the next stateful singleton.
- **REV-P2-04** (typed `RenderContext` value object on `EmailContentProvider` interface) remains explicitly out of scope. It pairs with F8 (cross-module `app/Shared/Contracts/Notification/` gap) — both queued for a future architectural sprint that touches Finance's coupling to Notification.
- **F1 / Inertia useForm ↔ response-shape mismatch** is the first surfaced instance of a class of v3-shape surprises that will recur. Add to the validating checklist for every Vue form pack going forward.
