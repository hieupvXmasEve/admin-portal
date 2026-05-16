---
date: 2026-05-16
feature: dynamic-email-templates
categories: [pattern, decision, failure]
severity: standard
tags: [notifications, email, templates, octane, parity, observer, feature-flag, strangler-fig]
---

# Compounding: Dynamic Email Templates (Phase 1)

First feature on the rails. Phase 1 swapped 4 hard-coded `EmailContentProvider` HTML
bodies for a DB-backed table, behind a feature flag, with parity tests as the exit gate.
33 files / +2512 / -35 lines. 11 follow-ups queued in `.khuym/state.json:deferred_beads`.

---

## Patterns

### Learning: Provider-registry swap with feature-flag fallback (strangler-fig for renderers)

**Category:** pattern
**Severity:** critical
**Tags:** [rollout-safety, registry, feature-flag, strangler-fig]
**Applicable-when:** Replacing a hard-coded provider/strategy with a data-driven one
where production load is hard to fully simulate pre-swap (notifications, pricing rules,
content rendering, serialisers).

#### What Happened

`EmailContentRegistry::build()` binds the 4 finance keys to either `DbEmailContentProvider`
(when `config('notifications.use_db_templates', true)` is true) or the legacy
`*EmailContent.php` classes. Legacy classes stay in the repo for the 30-day sunset window
post-P2. Rollback is one env var, not a redeploy.

#### Root Cause / Key Insight

Reversibility is the gating constraint for any rendering-pipeline swap: the new path and
old path share the `EmailContentProvider` interface, so call sites do not move while
parity is being proved.

#### Recommendation for Future Work

When swapping a hard-coded strategy for a data-driven one, **always**: (a) ship behind a
flag with a named sunset condition, (b) keep the old code as live fallback target until
sunset, (c) make the flag a config key consumed by the registry / factory, not a
hard-coded branch at the call site.

---

### Learning: Per-(type, tenant) request-scoped memo inside the provider

**Category:** pattern
**Severity:** standard
**Tags:** [n+1-prevention, request-scoped-cache, drop-in-replacement]
**Applicable-when:** A DB-backed strategy replaces an in-memory one inside an existing
tight loop and you cannot move the lookup outside the loop without refactoring multiple
call sites.

#### What Happened

`DbEmailContentProvider` holds `private array $cache` keyed by `typeKey:campusId`,
populated on first `resolveTemplate()` call. The 4 finance Actions iterate N invoices
within one campus per batch — N lookups collapse to 1. The outer `EmailContentRegistry`
separately memoises one provider instance per `type_key`, preserving the cache across
multiple `resolve()` calls in one request.

#### Root Cause / Key Insight

The existing call-site pattern was "resolve registry once outside the loop, call
`htmlBody()` N times inside" — putting the cache inside the provider preserves that
pattern without forcing 4 call-site refactors.

#### Recommendation for Future Work

When the strategy interface returns row-bound data per call, push memoisation INTO the
provider, keyed on the inputs that vary. Add a query-log assertion test that proves the
memo: same key → 1 query; different key → N queries.

---

### Learning: Observer + Provisioner for per-tenant invariant rows

**Category:** pattern
**Severity:** critical
**Tags:** [tenant-bootstrap, observer, idempotent-provisioning, drift-prevention]
**Applicable-when:** Any feature that requires "exactly one row per (tenant, type)" and
tenants can be created at any time after the initial migration — settings, feature
flags, notification preferences, default roles, etc.

#### What Happened

The first cut shipped only a one-shot seed migration. Tests creating new campuses
failed; production admin flow would also fail. Fixed by extracting the seed defaults
into `NotificationEmailTemplateProvisioner::provisionForCampus(int)` (`firstOrCreate`,
idempotent), refactoring the migration to call it, and registering a
`CampusObserver::created()` that calls the same provisioner.

#### Root Cause / Key Insight

A seed migration is a one-shot bootstrap, not an invariant. If "row per (tenant, type)"
is an invariant, an observer must enforce it on every new tenant.

#### Recommendation for Future Work

Whenever validating sees a new table with `(tenant_id, type_key)` rows that must exist
for every tenant: require a named observer + a shared provisioner service before
approving execution. The migration calls the provisioner; the observer calls the
provisioner; the two never duplicate the defaults.

---

### Learning: Trait with paired raw + escaped variants instead of a boolean flag

**Category:** pattern
**Severity:** critical
**Tags:** [trait, xss-defence, template-rendering, api-shape]
**Applicable-when:** Extracting a string-substitution/templating helper that is consumed
by both HTML and non-HTML surfaces.

#### What Happened

`HasTemplateRendering` exposes `renderContent()` (raw, for subjects/text) AND
`renderContentEscaped()` (htmlspecialchars per value, for HTML bodies). The choice lives
at the call site (`DbEmailContentProvider::htmlBody()` picks escaped;
`subject()` picks raw). Mirrors the legacy heredoc + per-variable `htmlspecialchars`
protection.

#### Root Cause / Key Insight

A single method with `bool $escape` is a footgun — caller forgets, defaults wrong, XSS
ships. Two methods with no default force a deliberate choice.

#### Recommendation for Future Work

When a templating helper has both HTML and non-HTML targets, ship two methods, not one
with a flag. Name them so the escaping intent is the first thing the caller reads
(`*Escaped`, `*Safe`).

---

### Learning: Golden-file parity test as the strangler-swap exit gate

**Category:** pattern
**Severity:** standard
**Tags:** [parity-test, golden-file, strangler-exit-gate, validating-skill]
**Applicable-when:** Any strangler-fig swap where the new path must be observationally
identical to the old before retirement — rendering engines, serialisers, formatters,
calculators.

#### What Happened

`phase-1-contract.md` made byte-equivalent rendered output (after whitespace collapse)
the **single non-negotiable exit criterion** (Story S1.7). Validation's `READY WITH
CONSTRAINTS` carried Q1 forward as a story-level acceptance bead. The test caught a
real seeder typo (extra space before `{{semester_code}}`) on first run.

#### Root Cause / Key Insight

The exit gate forces empirical proof of byte-equivalence before any downstream work, so
a missed `{$var}` interpolation cannot silently change customer-facing output.

#### Recommendation for Future Work

For every strangler swap, write the parity test FIRST and make it the phase's exit
criterion. Use whitespace-normalised diff (`preg_replace('/\s+/', ' ', trim($s))`)
unless byte-strict matters. **Always include unsafe-char fixtures** — see failure entry
below.

---

## Decisions

### Learning: Reject "extend the obvious table"; create a domain-specific table when lifecycle semantics diverge

**Category:** decision
**Severity:** critical
**Tags:** [storage-design, separation-of-concerns, semantic-conflict]
**Applicable-when:** Two domains share a render mechanism but diverge on lifecycle
(versioning, delete-policy, type-openness, admin surface, audience).

#### What Happened

`approach.md` v1 proposed extending the existing `EmailTemplate` (used for academic
emails) by adding `campus_id` + 4 new finance `TYPE_*` constants. User overrode mid-flow
with: "this config nên sử dụng riêng cho từng notification email". Revised to a new
`notification_email_templates` table; render logic extracted to a shared trait.

The original "reuse `EmailTemplate`" decision was driven by the wrong axis (20 lines of
shared `str_replace` logic). The conflicting axis was lifecycle: `EmailTemplate` has
versioning, soft-delete, and a free-string `type` — all of which conflict with D4
(always-1-active), D5 (overwrite, no versions), and D8 (closed 4-key enum).

#### Root Cause / Key Insight

Storage decisions are about lifecycle semantics, not about reusing 20 lines of
substitution code. A trait costs less than a polluted shared table.

#### Recommendation for Future Work

When planning storage for a feature, list the lifecycle semantics first (delete-policy,
versioning, type-openness, scope, write-frequency, audience). If they diverge from the
"obvious" table even on ONE axis, create a new table and share only the truly
domain-neutral helper code via a trait or service.

---

### Learning: Feature flag MUST come with a named sunset condition

**Category:** decision
**Severity:** standard
**Tags:** [rollout-safety, feature-flag, sunset-condition]
**Applicable-when:** Replacing a render/output mechanism whose regressions land in
customer-visible surfaces.

#### What Happened

`config/notifications.php` defines `use_db_templates` with the explicit sunset rule in
the docblock: "30 days after P2 admin-editor ships, assuming zero rendering failures,
this flag + this config file + the legacy classes are removed in a follow-up cleanup
commit." Without a sunset, dual paths drift forever.

#### Root Cause / Key Insight

A flag without a sunset is a permanent cost. The cleanup task is described, dated, and
owned BEFORE the flag ships.

#### Recommendation for Future Work

Every feature flag needs three pinned attributes: (1) default value post-launch, (2)
sunset condition with measurable criteria, (3) the SHA-1 of the future cleanup task or a
linked bead. Bake into the rollout-checklist; reject flags missing any of the three.

---

### Learning: Skipping bead ceremony is acceptable for bounded features; review is the safety net

**Category:** decision
**Severity:** standard
**Tags:** [workflow-shortcut, contract-discipline, review-as-safety-net]
**Applicable-when:** Bounded feature with strong exit-state contract, single owner, low
parallelism opportunity. Do NOT skip when 3+ stories are genuinely parallel or
ownership is shared.

#### What Happened

After validation gates passed, the user opted to bypass the bead-creation step. P1
executed inline (orchestrator + sub-agent worker pattern instead of `khuym:swarming`).
33 files / +2512 lines / 7 stories shipped. Reviewing phase backfilled rigor — caught
XSS regression, missing observer, and queued 11 P2/P3 follow-ups in deferred_beads.

#### Root Cause / Key Insight

The bead ceremony's value scales with parallelism and shared ownership. For a single-
owner sequential phase with a strong contract, the contract + parity test are sufficient
controls. Review-phase catches what bead-review would have caught.

#### Recommendation for Future Work

Trust the bead-skip shortcut when: (a) phase contract has testable exit assertions, (b)
parity / golden-file tests are the demo, (c) one worker, (d) <=15 files. Otherwise spend
the bead ceremony — the file-ownership map prevents a class of conflict bugs that review
cannot economically backfill.

---

## Failures

### Learning: Parity test gave a false green on stored XSS regression

**Category:** failure
**Severity:** critical
**Tags:** [xss, test-fixtures, parity-tests, false-green]
**Applicable-when:** Any render/template parity test.

#### What Happened

S1.7's parity test used safe-char fixtures (`Nguyễn Văn A`, `5.000.000`). Both legacy
and new path produced byte-equivalent output — green. But the new `renderContent()`
silently dropped the `htmlspecialchars()` wrap the legacy heredoc had. An attacker-
controlled `student_name = "<script>"` (or even legitimate `O'Brien`) would corrupt the
HTML in production while parity stayed green.

Caught by reviewing's manual taint walk, not by validating or the parity test itself.
Fixed by `renderContentEscaped()` + 2 new unit tests using unsafe-char fixtures.

#### Root Cause / Key Insight

Parity asserts "new == old" without asking "is old safe?". When the legacy code was
trustworthy-by-omission (relied on data being safe), parity carries the omission
forward.

#### Recommendation for Future Work

**Every render/template parity test MUST include at least one fixture with `<`, `>`,
`"`, `&`, and `'` in every interpolated variable.** Validating gate: if no test fixture
has special chars, the readiness check fails. Make this a Pest custom expectation
(`->toBeSafelyEscapedAfterRendering()`) so it is impossible to forget.

---

### Learning: New-tenant invariant gap — design treated provisioning as one-shot

**Category:** failure
**Severity:** critical
**Tags:** [provisioning, tenant-invariant, observer-pattern, runtime-exception]
**Applicable-when:** Any per-tenant table whose rows must exist for every tenant for
the feature to function.

#### What Happened

Seeder migration iterated `Campus::all()` at migration time. Any campus created
afterwards (test factory, production admin) had zero rows. First reminder send threw
`RuntimeException` in `DbEmailContentProvider::resolveTemplate()`. Discovered during
quality-gate sweep — feature tests fail when they create new campuses.

#### Root Cause / Key Insight

Design treated "rows per (tenant, type)" as a data-migration concern, not a system
invariant. No observer / service maintained the invariant after the migration ran.

#### Recommendation for Future Work

When validating a per-tenant feature: explicitly require the planner to name the
observer + provisioner that maintains the invariant on tenant creation. Add to the
readiness checklist: **"For every per-tenant invariant, name the observer that
maintains it. A one-shot seeder alone is not enough."**

---

### Learning: Validating did not check sibling-call-site parity; pre-existing bug survived

**Category:** failure
**Severity:** standard
**Tags:** [pre-existing-bug, validation-gap, sibling-parity, type-key-mismatch]
**Applicable-when:** When touching one of N parallel call sites that take a magic-string
argument.

#### What Happened

`SendParentPaymentRemindersAction:35` resolves `'payment_reminder'` (student-facing key)
instead of `'parent_payment_reminder'` — parents receive the student-addressed template.
Pre-existing in HEAD before P1; validating's Q2 evidence walk verified `campus_id`
plumbing across all 4 Action call sites but didn't diff the `resolve()` argument.

#### Root Cause / Key Insight

Validating checked the contract the change touched (`campus_id` injection) but not the
contract the change relied on (correct `type_key`). Sibling call-site diff catches both.

#### Recommendation for Future Work

When validating a change that touches one of N sibling call sites, **diff the full
argument list across all N sites and flag any divergence**. Add to validating evidence
template: "Sibling call-site parity: line up arguments column-wise across all sites the
change touches; report any column where 1 site differs from the others."

---

### Learning: Singleton stateful provider is a dormant Octane staleness trap

**Category:** failure
**Severity:** standard (dormant in P1; becomes high in P2)
**Tags:** [octane, singleton-state, deferred-trap, request-scope]
**Applicable-when:** Any long-running PHP runtime (FrankenPHP, Octane, queue workers).

#### What Happened

`EmailContentRegistry` is bound as singleton via
`NotificationServiceProvider::$this->app->singleton(...)`. `DbEmailContentProvider`
memoises rows in `private array $cache` "for the request lifetime". Under
FrankenPHP/queue workers the singleton survives across requests — admin saves in P2
will appear stale until worker restart.

Caught in reviewing; queued as REV-P2-02. Dormant in P1 (no admin save UI yet).

#### Root Cause / Key Insight

"Request-scoped" was assumed to mean "container resolves once per request" but under
Octane the container itself persists across requests.

#### Recommendation for Future Work

Stateful providers under Octane/FrankenPHP MUST EITHER use `scoped()` binding with an
explicit `RequestHandled` reset hook, OR key their memo on a value that changes per
request (request id, config flag, fresh closure). Add to validating: **"Any singleton
holding mutable state crosses Octane request boundaries — name the reset hook or change
the binding."**

---

### Learning (process win): Parity test caught a transcription typo on first CI run

**Category:** failure (recorded as process win)
**Severity:** standard
**Tags:** [process-win, parity-test, transcription-error]
**Applicable-when:** Manual data migrations that transcribe strings.

#### What Happened

The seeder rewrote heredoc `{$var}` into mustache `{{var}}` by hand across 4 templates.
Initial cut had a stray space (`Tuition Fee {{semester_code}}` vs legacy `Tuition
Fee{$semesterCode}`). Parity test failed on first CI run; fixed in one commit; never
reached review.

#### Root Cause / Key Insight

Manual heredoc-to-mustache rewrite is whitespace-fragile. The parity test is exactly
the tool for catching this class of error deterministically.

#### Recommendation for Future Work

Keep golden-file parity tests on data migrations that transcribe strings. They are
cheap, they pay for themselves on transcription errors, and they justify the same
pattern next time.
