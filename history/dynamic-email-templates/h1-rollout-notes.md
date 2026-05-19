# H1 Rollout Notes — HandleOutboxEventAction hygiene (M3b precursor)

**Date:** 2026-05-19 · **Risk:** LOW · **Staging gate:** none required

## Implementation summary

3 files touched:

1. `app/Modules/Notification/Actions/HandleOutboxEventAction.php` — `buildRenderedEmail`:
   strengthened Option α guard from `isset`-only to reject empty strings, non-strings,
   null values, and non-array `rendered_email` entries. Replaced PHPDoc to include
   caller-sanitize contract (Purifier for HTML body, `not_regex:/[\r\n]/` for subject).
2. `tests/Feature/Modules/Notification/HandleOutboxEventPreRenderedTest.php` — added
   Case 5 (`empty-string rendered_subject falls through to registry path`).
3. `history/dynamic-email-templates/h1-rollout-notes.md` — this file.

## No staging gate

H1 is local-only hardening. No user-visible flow changes. All existing callers (Finance
reminders, DNG events, manual paths) do not set `payload.rendered_email` — they are
unaffected. Ship with the next backend deploy alongside or before M3b.

## Behavior verification

- Case 1: non-empty subject + html → short-circuit fires, verbatim delivery. ✓
- Case 5: empty-string subject → guard rejects, registry path runs. ✓
- Cases 2, 3, 4: absent/partial rendered_email → unchanged fallthrough behavior. ✓
- `TestSendOutboxEmitTest` (2/2): uses non-empty `'[TEST] '` prefix → unaffected. ✓

## Rollback

Revert 2 files: `HandleOutboxEventAction.php` and `HandleOutboxEventPreRenderedTest.php`.
The history note file can stay.

## M3b unblock

H1 merged → M3b validating gate #2 cleared (caller-sanitize contract documented;
empty-string guard symmetric). M3b's 4 Finance reminder emitters can rely on the
strengthened guard contract when constructing their outbox payloads.
