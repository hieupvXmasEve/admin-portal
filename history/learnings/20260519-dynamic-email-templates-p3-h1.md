# Learnings — dynamic-email-templates P3 / H1

**Feature slug:** dynamic-email-templates
**Phase:** P3, story H1 (handler hygiene precursor for M3b)
**Mode:** small (3 file ops, behavior-preserving)
**Closed:** 2026-05-19
**Source artifacts:** `history/dynamic-email-templates/current-story-pack-H1.md`, `h1-rollout-notes.md`

H1 was a tiny precursor between M3a and M3b. Two concrete lessons worth recording; neither warrants critical-pattern promotion (both are local/tribal rather than cross-feature).

---

## Patterns

### P1. `isset` is the wrong primitive when guard intent is "present AND non-empty"

**Situation.** M3a's Option α guard used bare `isset($payload['rendered_email']['rendered_subject']) && isset(...)`. `isset` returns true for empty strings → a payload carrying `rendered_subject: ''` would short-circuit and deliver a blank-subject email. The intent was "the caller pre-rendered a real subject," not "the key exists with any string value."

**Root cause.** PHP idiom drift. `isset` reads naturally as "is set" but accepts empty strings; the guard's mental model was "is set to something meaningful."

**Future rule.** When the guard's intent is "value is present AND non-empty," use `is_string($x) && $x !== ''` (or the typed equivalent). Reserve bare `isset` for "key exists at all" semantics — typically followed by a default-fallback (`$x = $payload['k'] ?? 'default'`), not by a return-the-value short-circuit. For short-circuits that bypass a renderer/registry/fallback, the empty-string check is mandatory; `isset` alone is a latent blank-content bug.

---

## Decisions

### D1. Doc-comment contract over handler-side sanitization

**Situation.** M3a + H1 chose to make `HandleOutboxEventAction::buildRenderedEmail` a passthrough (no HTML sanitization, no CRLF stripping) and to enforce the sanitize-before-emit contract via PHPDoc + caller-side discipline. Alternative: handler re-sanitizes every short-circuit path.

**Decision.** Doc-comment + caller responsibility. Reason: handler-side sanitization would corrupt legitimately pre-sanitized content (double-purification can strip valid tags, double HTML-encode entities, etc.). Caller already has the right context to choose the right sanitization (Purifier profile, CRLF stripping rule, etc.) — the handler doesn't.

**Future rule.** When a handler/passthrough receives content from typed/known caller paths, prefer "caller sanitizes, handler trusts" with explicit PHPDoc contract over "handler always sanitizes." Validating gate: the doc-comment must name (a) the contract, (b) the sanitization tool/profile callers should use, (c) the relevant critical-pattern entries. Without those three pieces the doc-comment is decorative.

---

## Failures

### F1. `./scripts/dev.sh artisan pint` is not a valid invocation in this repo

**Situation.** H1 worker spec said to run `./scripts/dev.sh artisan pint --test <files>`. Pint is not registered as an artisan command in this project (it's a composer-installed CLI, not a Laravel command). Worker discovered this and pivoted to `./scripts/dev.sh composer exec pint`.

**Root cause.** Spec author (me) assumed `./scripts/dev.sh artisan pint` works because some Laravel projects register Pint as an artisan command. This one doesn't — Pint runs via composer's `vendor/bin`.

**Future rule.** When writing worker specs that invoke linters/formatters, grep `composer.json` `scripts` block or check `vendor/bin/` for the actual binary path. The correct repo-local Pint invocation is `./scripts/dev.sh composer exec pint <args>`. Add to repo conventions doc if not already present.

---

## Promotion candidates → critical-patterns.md

None. P1 is a PHP idiom local to short-circuit guards — useful, but not a cross-feature pattern that should sit at the top of every discovery read. D1 is more situational than P1. F1 is repo-tribal knowledge — better placed in repo conventions docs (e.g. `docs/code-standards.md` or a future `docs/dev-loop.md`) than in `critical-patterns.md`.

Out-of-scope follow-up: consider adding `./scripts/dev.sh` valid-command list to `docs/code-standards.md` or as a `dev.sh --help` block so future worker specs can grep for the right invocation.
