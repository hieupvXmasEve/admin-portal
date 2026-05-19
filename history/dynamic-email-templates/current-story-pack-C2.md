# Current Story Pack: C2 — RenderDraftEmailTemplateAction

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** C · **Wave:** 3
**Mode:** high-risk (parent) · **Story risk:** LOW (refactor + matrix test)
**Depends on:** C1 ✅

## Entry State

- F2 fix (P2 swarming) sanitized `preview()` + `testSend()` inline in `NotificationTemplateController`.
- F7 deferred to P3 as "extract shared Action so sanitization cannot be skipped per-endpoint" (per `review-p2.md` §F7).
- Critical Pattern #6 (sibling-endpoint sanitization parity matrix) needs to be reified as test.
- M3 will introduce new render call sites (test-send button reroute + outbox-emit render paths) — without C2 they bypass the sanitizer barrier.

## Exit State

- New `app/Modules/Notification/Actions/RenderDraftEmailTemplateAction.php` accepts `(NotificationTemplateTypeKey $type, string $subject, string $bodyHtml, array $data)` → returns sanitized + rendered `RenderedEmailDraft` DTO.
- `NotificationTemplateController::preview()` calls this Action (replaces inline Purifier + render).
- `NotificationTemplateController::testSend()` calls this Action (replaces inline Purifier + render).
- New `tests/Feature/Notification/SanitizationMatrixTest.php` — for each endpoint that ingests untrusted `body_html`, asserts the sanitizer was invoked. Failing if a new endpoint slips through without calling the Action.
- `./scripts/dev.sh test tests/Feature/Notification` green.

## File Ops Inventory

| Path | Op |
|---|---|
| `app/Modules/Notification/Actions/RenderDraftEmailTemplateAction.php` | create |
| `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` | edit (preview + testSend route through Action) |
| `tests/Feature/Notification/SanitizationMatrixTest.php` | create (matrix test) |
| `tests/Feature/Notification/NotificationTemplateControllerTest.php` (existing preview/testSend tests) | edit (assertions now go through Action) |

Total: 4 files. Well under limit.

## DAG Row

`[C1 ✅] → [C2]; M3 may consume C2's Action if its render path needs sanitization.`

## Critical Patterns Applied

- **#1 unsafe-char fixtures** — matrix test ingests `<>"&'`/`<script>`/`<meta http-equiv="refresh">` in every untrusted field.
- **#6 sibling-endpoint sanitization parity** — this Action IS the reification.
- **#8 pack-as-bead** — DAG row above.

## Feasibility Notes

- Pure refactor + new test. No production behavior change.
- Risk: existing inline Purifier call in `preview()` / `testSend()` may have endpoint-specific options (allowlist profile) — Action must accept profile name as param OR default to `email_body`.

## Handoff to Validating

Validating gates:
1. Inspect existing `preview()` + `testSend()` inline sanitization — does each use the same Purifier profile?
2. Confirm `RenderedEmailDraft` DTO shape: subject, htmlBody, textBody, plus any rendered-metadata fields needed by callers.
3. Matrix test design: enumerate endpoints via route list, not hand-written set, so new endpoints fail-closed.
