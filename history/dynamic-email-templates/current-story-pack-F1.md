# Current Story Pack: F1 — P3 batch cleanup (F12–F16) — AMENDED 2026-05-19

**Feature:** dynamic-email-templates · **Phase:** P3 · **Epic:** F · **Wave:** 2
**Mode:** high-risk (parent) · **Story risk:** LOW (mechanical cleanup)
**Depends on:** none.

## Scope amendment (2026-05-19, post-validating)

Original pack assumed F9–F16. Re-reading `review-p2.md` shows the actual cleavage:
- **F9, F10:** "P2 — Defer (follow-up beads / packs)" — regression-guard test backfill (~2hr). **Becomes its own pack `F-tests.md` (not this F1).**
- **F11:** "P3 — Batch cleanup" but design-level (extract `ListNotificationTemplatesQuery` + move logic into `UpdateNotificationTemplateAction`). **Becomes its own small pack `F11.md`.**
- **F12–F16:** Genuinely mechanical 1-file edits. **THIS IS F1's scope.**

## Entry State

- `review-p2.md` §"P3 — Batch cleanup" lists F11–F16. F1 covers F12–F16 only.
- All 5 items are 1-file, ≤1-test edits:
  - **F12:** PSR-12 `use` statements moved to file top in `routes/api/admin.php:178` + `routes/web/notifications.php:27`
  - **F13:** Remove `handlePageSizeChange` dead no-op in `resources/js/pages/Admin/NotificationTemplate/Index.vue:72-74,149`
  - **F14:** Nest new admin route group under existing `require` (vs sibling top-level)
  - **F15:** `config/purifier.php` — clarify `custom_attributes` profile scope; auto-inject `rel="noopener noreferrer"` for `target="_blank"`
  - **F16:** Misc test gaps (subject `max:500`, rate-limiter registration, web ctrl 404 path, policy seed fragility) — review-p2 says "bundle with F9"; F1 inherits the test-level bits that are 1-line additions to existing tests; the rest moves to F-tests pack.

## Exit State

- Each F9–F16 line item from `review-p2.md` is either:
  - Closed with file edit + matching test, OR
  - Explicitly deferred with a one-line reason logged in this pack's "Outcome log".
- `./scripts/dev.sh test` full suite green (excluding pre-existing CSRF/finance fails that T1a + T1b own).
- `./scripts/dev.sh artisan pint --test` green.

## File Ops Inventory (F12–F16, 5 items, ≤7 files)

| F# | Path | Op |
|---|---|---|
| F12a | `routes/api/admin.php` | edit (move `use` to top, line ~178) |
| F12b | `routes/web/notifications.php` | edit (move `use` to top, line ~27) |
| F13 | `resources/js/pages/Admin/NotificationTemplate/Index.vue` | edit (remove `handlePageSizeChange` lines 72-74 + 149) |
| F14 | `routes/api/admin.php` OR `routes/web/notifications.php` (whichever holds the new admin route group) | edit (nest under existing `require`) |
| F15 | `config/purifier.php` | edit (custom_attributes scope + rel="noopener noreferrer" auto-inject) |
| F16 | `tests/Feature/Notification/*Test.php` (specific tests TBD per worker scout) | edit (1-line test additions only; complex test gaps spin into F-tests pack) |
| — | `history/dynamic-email-templates/current-story-pack-F1.md` | edit (Outcome log) |

Total: ≤7 files. Under 10-file pack limit.

## Verification Commands

```bash
# Inventory F9–F16 from P2 review
grep -n "^### F9\|^### F1[0-6]" history/dynamic-email-templates/review-p2.md

# Full test suite (must stay green for non-T-epic tests)
./scripts/dev.sh test --exclude-group=quarantined

# Lint
./scripts/dev.sh artisan pint --test
./scripts/dev.sh npm run lint
```

## DAG Row

`[F1]` — no predecessors, no downstream dependents. Parallel-safe with C1/M1/T1a.

## Critical Patterns Applied

- **Pattern #8:** DAG row above (independent lane).
- **Per-F-item:** if any F9–F16 touches render path or test fixtures, inherit Critical Pattern #1 (unsafe-char fixtures) and #7 (≥2-row fixtures).

## Feasibility Notes

- Pure cleanup. No new behavior.
- Risk: F9–F16 inventory may surface a non-mechanical item that needs its own pack. Validating must inspect each F-item description in `review-p2.md` before approving F1 as a batch.
- **Defer rule:** any F-item whose fix exceeds 1 file + 1 test → defer with reason, do NOT inflate F1 scope.

## Handoff to Validating

Validating gates:
1. Read `review-p2.md` §F9–F16. Confirm each is genuinely mechanical (1 file + ≤1 test).
2. Reject any item that is design-level (e.g., "consider refactoring X" — that becomes a new epic).
3. Approve only the mechanical subset; defer the rest with reason in this pack.

## Outcome Log (worker fills per F-item)

| F# | File touched | Status | Reason if deferred |
|---|---|---|---|
| F12a | `routes/api/admin.php` | closed | Inline `use` for `NotificationTemplateApiController` moved to top-of-file use block. |
| F12b | `routes/web/notifications.php` | already-done | All 3 `use` statements already at file top (lines 3-6). No inline use found. |
| F13 | `resources/js/pages/Admin/NotificationTemplate/Index.vue` | already-done | `handlePageSizeChange` no longer present in file (removed earlier in P2 F6 fix). Only `handlePaginationNavigate` remains. |
| F14 | `routes/api/admin.php` | closed | Sibling top-level `prefix('admin/notification-templates')` group (lines 180-185) nested under existing `require __DIR__ . '/admin/notification.php'` group with `prefix('notification-templates')->name('notification-templates.')` to preserve route names. |
| F15 | `config/purifier.php` | closed (partial) | Added clarifying docblock that `custom_attributes` applies globally to all profiles. HTMLPurifier does NOT have a 1-line auto-inject toggle for `rel="noopener noreferrer"` on `target="_blank"`; deferred behavioral injection — `Attr.AllowedRel` already permits noopener/noreferrer/nofollow per email_body profile. Documented limitation rather than implementing custom URI filter (exceeds 1-line scope). |
| F16 | — | deferred → F-tests pack | Multi-test grab-bag (subject max:500, rate-limiter registration, web ctrl 404, policy seed). Each sub-item touches a different test file; exceeds defer rule "1 file + 1 test". Bundle with F9/F10 in `current-story-pack-F-tests.md`. |

## Out-of-scope (spun into separate packs)

- **F9 + F10 + F16-complex** → new pack `current-story-pack-F-tests.md` (regression-guard test backfill, ~2hr per review-p2 estimate)
- **F11** → new pack `current-story-pack-F11.md` (extract `ListNotificationTemplatesQuery` + `UpdateNotificationTemplateAction` consolidation — design-level)
