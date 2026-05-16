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
