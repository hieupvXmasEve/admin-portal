# Critical Patterns

High-leverage lessons promoted from per-feature learnings. Future Khuym
exploring/planning/validating sessions should read this file **before** discovery.

Promotion criteria: affects more than one future feature, would prevent meaningful
waste, and is generalizable.

---

## [20260516] Parity tests need unsafe-character fixtures or they false-green on XSS

**Category:** failure
**Feature:** dynamic-email-templates
**Tags:** [xss, test-fixtures, parity-tests, false-green]

A render-path parity test (new vs legacy renderer) asserted byte-equivalence with
safe-char fixtures (`Nguyễn Văn A`, `5.000.000`) and went green. But the new path had
silently dropped `htmlspecialchars()` per-variable — a real stored-XSS regression that
parity could not see because the legacy code's `data-is-safe` assumption was carried
forward. Caught later by a manual taint walk in review.

**Future rule:** every render-path parity test MUST include at least one fixture
containing `<`, `>`, `"`, `&`, and `'` in every interpolated variable. If no fixture
has special chars, validating's readiness check fails. Prefer a shared Pest expectation
`->toBeSafelyEscapedAfterRendering()` so it's impossible to forget.

**Full entry:** [history/learnings/20260516-dynamic-email-templates.md](20260516-dynamic-email-templates.md)

---

## [20260516] Per-tenant invariants need an observer + provisioner, not just a seeder

**Category:** failure → pattern
**Feature:** dynamic-email-templates
**Tags:** [tenant-bootstrap, observer, runtime-exception, provisioning]

A one-shot seed migration that iterated `Campus::all()` left every campus created AFTER
the migration ran with zero `notification_email_templates` rows — production admin
flow and test factories both broke with `RuntimeException` on the first email send.

**Future rule:** when a new table has `(tenant_id, type_key)` rows that MUST exist for
every tenant, validating must require a **named observer** (e.g. `CampusObserver`) + a
**shared provisioner service** (e.g. `NotificationEmailTemplateProvisioner`) before
approving execution. The seed migration and the observer call the same provisioner —
defaults can never drift between them.

**Full entry:** [history/learnings/20260516-dynamic-email-templates.md](20260516-dynamic-email-templates.md)

---

## [20260516] Singleton stateful providers leak across requests under Octane/FrankenPHP

**Category:** failure (dormant)
**Feature:** dynamic-email-templates
**Tags:** [octane, frankenphp, singleton-state, request-scope]

`EmailContentRegistry` bound as singleton + `DbEmailContentProvider` with a private
`$cache` "request-scoped" memo. Under long-running PHP runtimes (FrankenPHP, Octane,
queue workers) the singleton survives across requests, so the in-memory cache silently
serves stale rows after an admin save until the worker restarts.

**Future rule:** any singleton holding mutable state must EITHER use `app->scoped()`
with an explicit `RequestHandled` reset hook, OR key its memo on a value that changes
per request (request id, config flag, fresh closure). Validating gate: "Name the reset
hook or change the binding."

**Full entry:** [history/learnings/20260516-dynamic-email-templates.md](20260516-dynamic-email-templates.md)

---

## [20260516] Storage decisions are about lifecycle, not shared 20-line helpers

**Category:** decision
**Feature:** dynamic-email-templates
**Tags:** [storage-design, separation-of-concerns]

The original plan extended the existing `EmailTemplate` table because both domains
needed the same `{{var}}` `str_replace` logic. Conflicting lifecycle semantics
(versioning, free-string type, soft-delete on one side; closed enum, single active row,
overwrite + audit on the other) made the merge wrong even though the 20 shared lines
were trivially reusable. The user overrode mid-planning; revised storage = new
dedicated table + shared trait.

**Future rule:** when planning storage, **list the lifecycle semantics first**
(delete-policy, versioning, type-openness, scope, write-frequency, audience). If they
diverge from the "obvious" table even on ONE axis, create a new table. Share only the
domain-neutral helper via a trait or service. A trait costs less than a polluted
shared table.

**Full entry:** [history/learnings/20260516-dynamic-email-templates.md](20260516-dynamic-email-templates.md)

---

## [20260517] Inertia `useForm` ↔ response-shape pairing is a runtime-only contract

**Category:** pattern
**Feature:** dynamic-email-templates-p2
**Tags:** [inertia-v3, form-helper, response-shape, runtime-only-failure]

A Vue page used `useForm().put()` against a Laravel route returning `ApiResponse::success()`
(raw JSON, no `X-Inertia` header). The Inertia client threw "All Inertia requests must
receive a valid Inertia response" — Save UX broken on every click. Controller-level tests
via `putJson()` bypassed the Inertia client and went green. Discovered only at review.

**Future rule:** for every new Vue form, name the (form-helper, target-route,
response-shape) triple in the story pack and reject mismatches at validation.
Non-navigating modal/drawer forms → `useApi` + vee-validate + Zod hitting JSON API.
Page-level navigating forms → `useForm` hitting a web route that returns `back()` with
`Inertia::flash()`. Controller-level HTTP tests do not catch this mismatch — only an
Inertia-client roundtrip or a static (helper, shape) check does.

**Full entry:** [history/learnings/20260517-dynamic-email-templates-p2.md](20260517-dynamic-email-templates-p2.md)

---

## [20260517] Sibling-endpoint sanitization parity (extends Critical Pattern #1)

**Category:** pattern → failure
**Feature:** dynamic-email-templates-p2
**Tags:** [xss, sanitization, parity-gap, sibling-endpoints]

A save endpoint sanitized HTML in its `FormRequest::passedValidation()`. Two sibling
endpoints (`preview()`, `testSend()`) accepted the same draft `body_html` and rendered/
emailed it raw — the FormRequest barrier sat only on the save route. Generalizes Critical
Pattern #1 (parity tests need unsafe fixtures) from render-path to sanitization-barrier.

**Future rule:** when N sibling endpoints accept the same untrusted payload, build a
column-wise sanitization matrix (endpoint × untrusted-field × sanitizer-called) and
require every cell to be `Y`. Extract a shared Action (e.g. `RenderDraftEmailTemplateAction`)
so the sanitization step cannot be skipped per-endpoint. Validating gate: "list every
endpoint that ingests this payload; confirm each one calls the sanitizer."

**Full entry:** [history/learnings/20260517-dynamic-email-templates-p2.md](20260517-dynamic-email-templates-p2.md)

---

## [20260517] Per-recipient loops need ≥2-row fixtures or display fields decouple silently

**Category:** failure
**Feature:** dynamic-email-templates-p2
**Tags:** [loop-pairing, multi-row-relations, test-fixture-coverage, regression-via-cleanup]

`SendParentPaymentRemindersAction` looped over recipient emails but resolved
`parent_name` once via `relation->first()` outside the loop. For students with ≥2
parent profiles, parent B received mail addressed "Dear Parent A." The D-batch test
fixture used a single parent profile so it stayed green. Bug introduced by a cleanup
that touched the loop but not the name-pairing.

**Future rule:** for any per-recipient loop where display fields come from a relation,
the test fixture MUST include ≥2 rows of that relation. Make this a Pest expectation
(`->withMultipleRowsFixture()`) or a validating gate flagging single-row fixtures on
per-recipient code paths. When a cleanup touches loop semantics, re-run with a
≥2-row fixture before declaring done.

**Full entry:** [history/learnings/20260517-dynamic-email-templates-p2.md](20260517-dynamic-email-templates-p2.md)

---

## [20260517] Pack-as-bead with DAG when `.beads/` is absent

**Category:** pattern
**Feature:** dynamic-email-templates-p2
**Tags:** [swarming, story-packs, parallelism, dependency-graph]

P2 ran 13 stories in 6 packs across 4 swarm waves with no `.beads/` and no `bv` tool.
Each `current-story-pack-*.md` carried bead-equivalent fields (entry/exit state, file
ops list, verification commands, feasibility table) and the epic map declared the
critical-path DAG. Zero re-planning across all 6 packs.

**Future rule:** when swarming without `.beads/`, treat `current-story-pack-*.md` as
the bead unit. Each pack MUST have: entry-state, exit-state, file ops <10, named
verification commands, and a row in a critical-path DAG. Reject packs that exceed
10 file ops or omit the DAG row. The orchestrator then encodes any MINOR validation
flag directly into the pack's worker prompt (workers don't re-read validation reports).

**Full entry:** [history/learnings/20260517-dynamic-email-templates-p2.md](20260517-dynamic-email-templates-p2.md)

---

## [20260517] Model `booted()` updating-guard is cheaper than `$fillable` surgery for immutable identity columns

**Category:** pattern
**Feature:** dynamic-email-templates-p2
**Tags:** [mass-assignment, model-guards, immutability, defense-in-depth]

Identity columns (`campus_id`, `type_key`) needed to stay mass-assignable for CREATE
(provisioner, factories, transient render path — 12 callers) but must never change
after CREATE. Removing them from `$fillable` would break every legitimate caller.
The cheaper fix: model-level `static::booted() { static::updating(fn ...) }` guard
that throws `LogicException` on `isDirty('campus_id') || isDirty('type_key')`. Fires
on UPDATE only, so CREATE goes through untouched.

**Future rule:** when identity columns must stay in `$fillable` for CREATE callers,
add a paired `static::booted()` `updating` guard that throws on `isDirty()` for those
columns. Defense at the layer where the invariant lives (row identity) is both cheaper
AND more correct than defense at the input layer (which drifts as callers are added).

**Full entry:** [history/learnings/20260517-dynamic-email-templates-p2.md](20260517-dynamic-email-templates-p2.md)

---

## [20260517] Inline orchestrator-fix beats worker-respawn for test-scaffold mechanic stalls

**Category:** decision
**Feature:** dynamic-email-templates-p2
**Tags:** [worker-rescue, test-scaffold, respawn-economics, swarming]

A swarm worker stalled debugging a FormRequest standalone-invocation test scaffold —
3 separate scaffold bugs (debug throw left in production code; `setParameter()` returns
void so an arrow-fn returned null; missing `setRedirector(app('redirect'))` to bypass
HTTP redirect machinery). Watchdog killed the worker at 10min. Orchestrator fixed all
3 inline rather than respawning; worker artifact preserved, tests shipped 5/5 green
after 4 inline edits.

**Future rule:** distinguish worker-blocker categories: (1) feature-logic confusion →
respawn with better context, (2) framework/scaffold mechanics → orchestrator fixes
inline, worker artifact preserved. Respawn wastes a full context-restore cycle for
mechanical fixes the orchestrator can do in seconds. Separately: never ship a test
scaffold in a worker prompt unless the orchestrator has run the snippet locally
first — arrow-fn one-liners over fluent chains can silently swallow `void` returns.

**Full entry:** [history/learnings/20260517-dynamic-email-templates-p2.md](20260517-dynamic-email-templates-p2.md)

---

## [20260519] Validating's grep-step must scout schema precedent before approving "use existing pattern"

**Category:** pattern
**Feature:** non-academic-charge-generation
**Tags:** [validating, schema-grep, precedent-discovery, campus-resolver]

Approach.md called for "campus-scoped writes" using "the existing pattern." The actual precedent — `app('campus')->id` resolved via container, NOT `session('current_campus_id')` — lived only in `GenerateBatchChargesAction.php:55-61`. Validating's inline grep step also caught 3 other schema constraints (`semester_id NOT NULL`, closed `charge_type` enum, missing unique index on `(student_id, charge_type, semester_id)`, universal `Invoice::createFromCharge` integration) that approach-level reasoning would have hand-waved. Worker shipped first try because the pack named file:line for every claim.

**Future rule:** validating's exit-gate requires `Precedents-cited: N >= claims-made: N` and a `Schema-constraints-verified` sub-table where each row is `<constraint> | <file:line> | <pasted excerpt>`. Reject any approach.md naming a pattern without a file:line citation; `grep -n` and paste, do not summarize. "Use existing X" is a noop instruction unless the precedent is cited concretely.

**Full entry:** [history/learnings/20260519-non-academic-charge-generation.md](20260519-non-academic-charge-generation.md)

---

## [20260519] TypeScript wrapper signatures lag transport capability — "useApi can't do X" is usually a type-widening tax

**Category:** failure → pattern
**Feature:** non-academic-charge-generation
**Tags:** [typescript, useapi, formdata, wrapper-types, transport-layer]

Worker wrote raw `fetch()` for a FormData upload despite a pack constraint requiring `useApi.post()`. Justification: "useApi is JSON-only." `useApi`'s transport (`beforeFetch` in `createFetch`) actually handles FormData natively and strips `Content-Type` correctly — the real blocker was the TS signature `post(data: Record<string, any>)` rejecting `FormData`. A 1-line union widening (`Record<string, any> | FormData`) would unblock every future uploader. The bypass also silently dropped CSRF, auth-header, and error-normalization plumbing the wrapper provides for free.

**Future rule:** when a worker claims an internal composable "doesn't support" a payload shape: (1) open the transport body (`beforeFetch`/interceptors) and grep for the payload type before accepting the claim; (2) prefer widening the wrapper signature over a bypass; (3) reject any PR that introduces raw `fetch()` for a payload the wrapper's transport can already carry. Validating gate: "If pack requires `useApi` and worker used `fetch`, the worker prompt must include a one-line proof the transport can't carry the payload — otherwise widen the signature."

**Full entry:** [history/learnings/20260519-non-academic-charge-generation.md](20260519-non-academic-charge-generation.md)

---

## [20260519] Campus scope fallback to `null` is a silent cross-tenant write

**Category:** failure
**Feature:** non-academic-charge-generation
**Tags:** [campus-scoping, multi-tenant, fallback-null, runtime-exception]

An Action fell back to `null` campus_id when `app()->bound('campus')` was false: `$campusId = app()->bound('campus') ? app('campus')->id : null`. With `campus_id = null` in the WHERE clause for existing-charge dedup, the scope check effectively disappeared — concurrent admin actions across campuses could write into each other's data via the same Action. `null` campus_id is indistinguishable from "intentionally global" rows at the query layer; either the WHERE misses everything or it matches the null-campus partition. Either way, the dedup invariant breaks and writes leak. Fix: throw `RuntimeException` when unbound.

**Future rule:** for any Action that writes campus-bound (or any tenant-scoped) rows: resolve the scope via `app('campus')->id` and let the container's missing-binding exception propagate, OR add an explicit `if (!app()->bound('campus')) throw new RuntimeException(...)` at the top. Never `?? null` on a required scope variable. Validating gate for any Action touching tenant-scoped tables: "name the scope resolution line and confirm it throws (not nulls) on missing binding."

**Full entry:** [history/learnings/20260519-non-academic-charge-generation.md](20260519-non-academic-charge-generation.md)
