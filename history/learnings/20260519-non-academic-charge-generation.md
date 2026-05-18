---
date: 2026-05-19
feature: non-academic-charge-generation
categories: [pattern, decision, failure]
severity: standard
tags: [campus-scoping, csv-upload, mime-validation, formula-injection, useapi, typescript-signature, validating-grep, lifecycle-storage, worker-pause]
---

# Compounding: Non-Academic Charge Generation (Pack NAC-1, BHYT)

Pack NAC-1 refactored `finance/operations/generate-charges` from a 920-line bulk-EGC page to a 301-line non-academic charge generator (BHYT first). 15 file ops, 21+6=27 tests green. Worker paused twice mid-flight; review surfaced 8 P2 findings (zero P1). 5 learnings synthesized; 3 promoted to critical-patterns.md.

---

## Patterns

### Learning: Validating's grep-step must scout schema precedent before approving "use existing pattern"

**Category:** pattern
**Severity:** critical
**Tags:** [validating, schema-grep, precedent-discovery, campus-resolver]
**Applicable-when:** approach.md says "use the existing X pattern" but the concrete pattern lives in code, not docs.

#### What Happened

Approach.md called for "campus-scoped writes." The actual precedent — `app('campus')->id` resolved via container, NOT `session('current_campus_id')` — lived only in `app/Modules/Finance/Actions/GenerateBatchChargesAction.php:55-61`. Validating's inline grep step caught this, plus 3 other schema constraints (`semester_id NOT NULL`, `charge_type` enum closed, no unique constraint on `(student_id, charge_type, semester_id)`, universal `Invoice::createFromCharge` integration) that approach.md would have hand-waved. Worker shipped on first try because the precedent was named in the pack.

#### Root Cause / Key Insight

"Use the existing pattern" is a noop instruction unless the validating step names file:line for the precedent. Approach-level docs lag code; only grep proves what production actually does. Schema constraints (NOT NULL, closed enums, missing unique indexes) are invisible to approach-level reasoning but trip workers at runtime.

#### Recommendation for Future Work

Validating must produce a precedent table for every "use existing X" claim: column 1 = pattern name, column 2 = file:line, column 3 = exact call signature copied from source. Reject any approach.md that names a pattern without a file:line citation. Schema constraints (NOT NULL columns, closed enums, missing unique indexes, downstream integration points like `Invoice::createFromCharge`) get their own validating sub-table — `grep -n` and paste, do not summarize.

**Future rule:** validating's exit-gate requires `Precedents-cited: N >= claims-made: N` and `Schema-constraints-verified: <list with file:line>`.

---

### Learning: Mime validation in Laravel 13 is a two-rule problem (`File::types` + `mimetypes:` string-rule)

**Category:** pattern
**Severity:** standard
**Tags:** [file-upload, mime-validation, laravel-13, security, csv]
**Applicable-when:** Any FormRequest accepting user-uploaded structured files (CSV, XLSX, JSON, etc.).

#### What Happened

P2-B flagged that `File::types(['csv','txt'])` checks extension only and is trivially spoofable (rename `evil.exe` to `evil.csv`). Worker reached for `File::mimetypes(['text/csv'])` — does not exist in Laravel 13.6's `Illuminate\Validation\Rules\File`. Fix landed as two paired rules: `File::types([...])` for extension allowlist plus the string-rule form `'mimetypes:text/csv,text/plain,application/csv'` for actual MIME sniff. Both required because string-rule `mimetypes` alone lets random `text/plain` through without an extension check.

#### Root Cause / Key Insight

Laravel's fluent `File::` builder has not yet absorbed the older string-rule `mimetypes:` validator. They live at different API layers and must be combined. Worker's first attempt assumed API parity that does not exist.

#### Recommendation for Future Work

For any file upload in this project: pair extension and MIME via two rules — `File::types(['csv','txt'])->max(2048)` plus `'mimetypes:text/csv,text/plain,application/csv'` (or domain equivalent). Document the pairing in `docs/rules/api-interaction.md` so workers don't rediscover `File::mimetypes()` doesn't exist.

**Future rule:** any FormRequest with `File::types(...)` MUST also carry a `mimetypes:` string rule listing the matching IANA types. Validating gate: "name both rules or reject."

---

## Failures

### Learning: TypeScript wrapper signatures lag transport capability — "useApi can't do X" is usually a type-widening tax

**Category:** failure
**Severity:** standard
**Tags:** [typescript, useapi, formdata, wrapper-types, transport-layer]
**Applicable-when:** A worker claims an internal composable "doesn't support" a payload shape and reaches for raw `fetch()`.

#### What Happened

Worker wrote raw `fetch('/api/...', { method: 'POST', body: formData, credentials: 'include' })` in the Vue uploader despite a pack constraint requiring `useApi.post()`. Justification: "useApi is JSON-only." Investigation showed `useApi`'s transport layer (`beforeFetch` in `createFetch`) handles FormData natively and strips `Content-Type` correctly. The real blocker was the TypeScript signature `post(data: Record<string, any>)` rejecting `FormData`. A 1-line union widening (`Record<string, any> | FormData`) unblocks every future uploader.

Worse: after orchestrator fixed it, a regression slipped — worker dropped `credentials: 'include'` at the top level of `createFetch` options where it doesn't belong (already set correctly inside `beforeFetch`). Orchestrator fixed inline.

#### Root Cause / Key Insight

Wrapper composables get typed conservatively on day one and drift behind their transport layer for years. "Composable X doesn't support payload Y" is almost always a signature problem, not a transport problem. Reading the wrapper's `beforeFetch`/interceptor body takes 30 seconds and beats reinventing auth, CSRF, error normalization, and abort logic in a raw `fetch()`.

#### Recommendation for Future Work

When a worker claims an internal composable "doesn't support" a payload shape: (1) open the composable's transport body and grep for the payload type before accepting the claim; (2) prefer widening the wrapper signature over a bypass; (3) reject any PR that introduces raw `fetch()` for a payload the wrapper's transport can already carry.

**Future rule:** validating's frontend gate: "If pack requires `useApi` and worker used `fetch`, the worker prompt must include a one-line proof that the transport layer can't carry the payload — otherwise widen the signature." Bypasses also drop CSRF, auth-header, and error-normalization plumbing silently.

---

### Learning: Campus scope fallback to `null` is a silent cross-tenant write

**Category:** failure
**Severity:** critical
**Tags:** [campus-scoping, multi-tenant, fallback-null, runtime-exception]
**Applicable-when:** Any Action that writes campus-bound rows and reads `app('campus')` (or any per-request scope binding).

#### What Happened

P2-D found the Action fell back to `null` campus_id when `app()->bound('campus')` was false: `$campusId = app()->bound('campus') ? app('campus')->id : null`. With `campus_id = null` in the WHERE clause for existing-charge dedup, the scope check effectively disappeared — concurrent admin actions across campuses could write into each other's data via the same Action. Fix: throw `RuntimeException('Campus context missing for non-academic charge generation')` when unbound. No more null fallback; cron jobs and out-of-request callers must bind campus explicitly.

#### Root Cause / Key Insight

`null` campus_id is indistinguishable from "intentionally global" rows at the query layer — the WHERE clause `WHERE campus_id = ?` with `?=null` matches nothing (or with `<=>` matches the null-campus partition). Either way, the dedup invariant breaks and writes leak. Fallback-to-null on a required scope variable is always wrong; the safe default is throw.

#### Recommendation for Future Work

For any Action that writes campus-bound rows: resolve campus via `app('campus')->id` and let the container's missing-binding exception propagate, OR add an explicit `if (!app()->bound('campus')) throw new RuntimeException(...)` at the top of the Action. Never `?? null`. Audit: grep for `app()->bound('campus')` paired with `?` and review every match.

**Future rule:** validating gate for any Action touching tenant-scoped tables: "name the campus resolution line and confirm it throws (not nulls) on missing binding."

---

### Learning: CSV formula injection is an output-stage threat that input validation misses

**Category:** failure
**Severity:** standard
**Tags:** [csv-injection, formula-injection, excel-export, downstream-taint, security-review]
**Applicable-when:** User-supplied text from CSV uploads survives into any cell-rendering downstream surface (Excel export, Google Sheets paste, email-as-HTML).

#### What Happened

P2-C: student_codes uploaded via CSV were not regex-validated. Codes starting with `=`, `+`, `-`, or `@` would survive into the `skipped` response payload's "reason" strings and downstream into Excel exports of skip reports — where Excel would auto-evaluate them as formulas (`=HYPERLINK(...)`, `=cmd|...`). Input validation focused on length and existence in the DB; the output surface (Excel export of the skip log) was where the threat actually lived. Reviewer caught it by tracing the data downstream, not by reading the FormRequest.

#### Root Cause / Key Insight

Security review on input ("is this code a valid student code?") doesn't see the output surface ("does this string end up in a CSV/XLSX that Excel will open?"). Formula injection requires the reviewer to trace user-supplied strings through every export pipeline. The input validator and the export renderer are in different files, and threat-modeling at input stage misses it.

#### Recommendation for Future Work

For any field whose raw value can land in an Excel/CSV/Sheets cell, validate it at input with `regex:/^[^=+\-@]/` AND prefix it at export time with a single quote (`'`). Defense in depth: the regex stops malicious uploads, the export prefix protects against any field that bypasses the regex (e.g. via update paths added later). Reviewers must trace untrusted strings to every export, not stop at input validation.

**Future rule:** security checklist gets an explicit row: "list every export sink (Excel, CSV, PDF tables, Sheets API) this field can reach; confirm formula prefix or input regex at each."

---

## Decisions Validated

### Learning: Critical-pattern #4 ("storage is lifecycle, not 20-line helpers") correctly applied

**Category:** decision
**Severity:** standard
**Tags:** [lifecycle-storage, manual-fee, charge-type-enum, separation-of-concerns]
**Applicable-when:** A new feature has an "obvious" reuse path that would extend an existing table.

#### What Happened

Initial brainstorm: piggyback BHYT charges onto `manual_fee` rows with `description='BHYT'`. Validating rejected this on lifecycle grounds — `manual_fee` is free-string description, single-row-per-issue, no semester binding, no batch dedup; BHYT charges are closed enum `charge_type`, per-semester, batch-generated, cron-rerunnable with dedup. Same `amount + student_id` columns, completely different lifecycles. Outcome: dedicated `charge_type='bhyt'` enum value + reuse of existing `non_academic_charges` shape. No new table, but no lifecycle pollution either.

#### Root Cause / Key Insight

Critical Pattern #4 from 20260516 worked as intended: listing lifecycle axes (write-frequency, dedup-policy, scope, audience) before storage decision exposed the mismatch. Reusing the column shape is fine; reusing the lifecycle semantics would have been wrong.

#### Recommendation for Future Work

Continue applying the lifecycle axes checklist from Critical Pattern #4 at validating stage. Add this case as a worked example in the rule: "reusing column shape ≠ reusing lifecycle; an enum extension on a per-semester table is the right shape, a free-string `description='BHYT'` hack is not."

---

## Critical-Pattern Promotions

Promoted to `critical-patterns.md`:

1. **Validating's grep-step must scout schema precedent before approving "use existing pattern"** — affects every future feature, prevents approach-level hand-waving from shipping bugs.
2. **TypeScript wrapper signatures lag transport capability** — touches every frontend pack using `useApi`/`useForm`/`useApiRequest`.
3. **Campus scope fallback to `null` is a silent cross-tenant write** — multi-tenant invariant, applies to every Action touching campus-bound tables.

Not promoted (single-feature scope or already covered):
- Mime two-rule pattern (Laravel-version-specific, document in `docs/rules/api-interaction.md` instead)
- CSV formula injection (security-checklist addition, not architectural pattern)
- Lifecycle-storage decision (already Critical Pattern #4; this run validates it)

---

## Unresolved Questions

- Should `useApi` ship a typed `postForm(formData: FormData)` variant rather than widening `post()`? Pending frontend lead.
- Worker-pause behavior: pack NAC-1 paused twice without explicit blockers. Is this a context-budget symptom or a watchdog config issue? Needs orchestrator instrumentation.
- Should `app('campus')` resolution be enforced via a custom `RequiresCampus` middleware/trait instead of per-Action throws? Defer to next finance pack.
