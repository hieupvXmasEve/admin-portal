# Current Story Pack: H1 — HandleOutboxEventAction hygiene (M3b precursor)

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** H (hygiene precursor)
**Mode:** small (low risk, behavior-preserving, ~3 file ops)
**Story risk:** LOW
**Depends on:** M3a swarm DONE locally (no staging-gate required for H1 — pure hardening of a path M3b will rely on)
**Source:** P2 review items deferred from M3a review (see `history/learnings/20260519-dynamic-email-templates-p3-m3a.md` §"Out-of-scope items")

## Why H1 exists

M3a's review surfaced 2 P2 hygiene items on `HandleOutboxEventAction::buildRenderedEmail` that protect future cross-module emitters. M3b adds 4 new cross-module emitters (Finance reminders). Landing the hygiene now is cheaper than retrofitting after M3b ships.

## Entry State

- `HandleOutboxEventAction.php:121-130` has the Option α short-circuit guard added in M3a:
  ```php
  if (isset($envelope->payload['rendered_email']['rendered_subject'])
      && isset($envelope->payload['rendered_email']['rendered_html'])) {
      return [ ... ];
  }
  ```
- `isset` returns true for empty strings — a payload carrying `rendered_subject: ''` would short-circuit and deliver a blank-subject email.
- No doc-comment names the caller-sanitize contract. Future cross-module emitters could pass un-sanitized HTML into `payload.rendered_email.rendered_html` and the handler would deliver it verbatim.

## Exit State

- **Empty-string guard.** Guard rejects empty strings as well as missing keys. Either `!== ''` checks alongside `isset`, or a single `match` on a normalized struct. Implementation choice up to worker; preserve existing behavior for valid non-empty payloads.
- **Caller-sanitize contract doc-comment.** PHPDoc on `buildRenderedEmail` explicitly states: "Callers MUST sanitize `rendered_html` before placing it in the envelope. The handler is a passthrough, not a sanitizer. The `RenderedEmailChannelAdapter` will deliver the HTML verbatim. For draft-content paths, use `Purifier::clean(..., 'email_body')` before envelope construction. For registry-resolved content, the existing `DbEmailContentProvider::htmlBody()` already escapes per-variable."
- Existing `HandleOutboxEventPreRenderedTest` Cases 1, 2, 3, 4 still pass.
- New test: payload with `rendered_subject: ''` falls through to registry path (does NOT short-circuit with empty subject).
- `./scripts/dev.sh test tests/Feature/Modules/Notification/HandleOutboxEventPreRenderedTest.php` green.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Modules/Notification/Actions/HandleOutboxEventAction.php` | edit — guard + PHPDoc |
| `tests/Feature/Modules/Notification/HandleOutboxEventPreRenderedTest.php` | edit — add empty-string case |
| `history/dynamic-email-templates/h1-rollout-notes.md` | create — 5-line summary, no staging gate |

Total: **3 file ops.** Trivial.

## DAG Row

`[M3a swarm-DONE locally] → [H1] → [M3b validating]`

H1 does NOT require M3a staging-green — it hardens the handler M3a already added. M3b's staging-gate stays intact.

## Critical Patterns Applied

- **P1 (outbox accepts pre-rendered envelope)** — H1 tightens the contract this pattern depends on. Doc-comment makes the caller-sanitize contract explicit for M3b and future emitters.

## Feasibility Notes

- Trivial scope. Behavior-preserving for all current callers (M3a's test-send sets a non-empty subject; existing Finance/DNG/manual paths don't set `rendered_email` at all and won't hit the guard).
- No staging risk — H1 doesn't change any user-visible flow. Local-green is sufficient.
- Octane safety preserved (still a pure read of `$envelope->payload`).

## Handoff to Validating

Validating gates:
1. Confirm empty-string guard is symmetric: rejects `''` AND missing key, accepts non-empty string.
2. Confirm doc-comment names the caller-sanitize contract clearly enough that a future cross-module emitter author can read it once and know what to do.
3. Confirm M3a's existing 4 test cases still pass.
4. Confirm new test case (empty `rendered_subject`) correctly demonstrates fallthrough.
