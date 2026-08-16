---
phase: 2
title: "Dead code, English naming, and test contract updates"
status: done
priority: P1
effort: "4h"
dependencies: [1]
---

# Phase 2: Dead code, English naming, and test contract updates

## Overview

Remove the dead code, normalize every title to English, and update the three Pest
tests that assert on this file's literal contents — all in one commit, because
the tests and the file are a single contract. The group tree shape does not
change in this phase, so the Phase 3 restructure diff stays readable.

## Requirements

Functional:
- Commented `Finance Legacy (Old UI)` block removed.
- Unused `mainNavItems` export removed.
- Every `title` and `label` is English.
- `FinanceOfficeCutoverTest` and `FinanceReportingShellTest` assert the new
  English labels, in the same commit as the rename.
- No `href`, `icon`, or `requiredPermissions` value changes.

Non-functional:
- Orphaned icon imports removed.
- Finance test suite no worse than its known baseline.

## Architecture

Three tests read this file with `file_get_contents(base_path(...))` and assert on
literal strings. They are copy contracts, not incidental coupling:

| Test | Line | Asserts |
|------|------|---------|
| `FinanceOfficeCutoverTest` | 110 | contains `Lập yêu cầu thanh toán DNG` |
| `FinanceOfficeCutoverTest` | 118-125 | contains `Sinh HP/Tuition`, `Sinh phí EGC`; **not** `Generate HP (Tuition)`, `EGC · Generate Charges` |
| `FinanceReportingShellTest` | 82, 90 | contains `Finance Reporting` |
| `DetailRouteContractTest` | 111 | reads the file |

Two different situations, handled differently:

- `Sinh HP/Tuition` and `Sinh phí EGC` exist **only inside the commented Legacy
  block**. That assertion has been passing against a comment since those items
  were retired — it protects nothing live. Deleting the block does not break a
  real contract; it exposes an assertion that already stopped testing anything.
  The assertion is removed, not preserved.
- `Lập yêu cầu thanh toán DNG` is a live label and a real contract. Renaming it
  reverses a deliberate decision, which is why Phase 1 requires an ADR first.
- `Finance Reporting` is live and there is no reason to rename it. The IA table
  keeps `Finance Reporting` as the final title.

`title`/`label` are Vue `v-for` keys (`NavMain.vue:85,89`,
`NavMenuItem.vue:112,133`), not display-only strings, so renames must preserve
sibling uniqueness.

## Related Code Files

- Modify: `resources/js/constants/menu-sidebar.ts`
- Modify: `tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php`
- Modify: `tests/Feature/Finance/Reporting/FinanceReportingShellTest.php`

## Implementation Steps

1. Delete the commented `Finance Legacy (Old UI)` group (currently lines
   440-469; locate by the `Finance Legacy (Old UI)` string, not by line number).

2. Delete the `mainNavItems` export at the end of the file. Confirm with
   `grep -rn "mainNavItems" resources/js` that only `AppHeader.vue`'s own local
   declaration remains.

3. Rename the Vietnamese titles. Match on the string, not on a line number —
   step 1 shifts every line below it.

   | Current | English |
   |---------|---------|
   | `Thi lại` | `Exam Resit` |
   | `Lịch thi lại` | `Exam Resit Schedule` |
   | `Hôm nay` | `Today` |
   | `Doanh thu` | `Revenue` |
   | `Sinh phí` | `Fee Generation` |
   | `EGC · Kết quả & học lại` | `EGC Block Results & Retakes` |
   | `Thu & Đối soát` | `Collections & Settlement` |
   | `Lập yêu cầu thanh toán DNG` | `Create DNG Payment Request` |
   | `Ngoại lệ` | `Exceptions` |
   | `Tra cứu & Audit` | `Lookup & Audit` |
   | `DNG · Cần kiểm tra` | `DNG Receipts To Review` |

   Leave `Finance Reporting` and `EGC - Carry Forward` unchanged — both are
   already English, and `Finance Reporting` is pinned by
   `FinanceReportingShellTest:90`.

4. Fix the two misleading English labels:
   - `Attendance & Completion` -> `Attendance` (no child in the subgroup is about
     completion)
   - `Finance Office (New UI)` -> `Finance` (the Legacy group is gone as of step 1;
     migration state is not permanent nav copy)

5. **Update the test contracts in this same commit.**
   - `FinanceOfficeCutoverTest:110` -> assert `Create DNG Payment Request`
   - `FinanceOfficeCutoverTest:118-125` -> remove the `Sinh HP/Tuition` and
     `Sinh phí EGC` assertions entirely; they referenced a deleted comment. Keep
     the `phaseShortcuts` assertions in that test, which do cover live behavior.
   - `FinanceReportingShellTest` needs no change if `Finance Reporting` is kept.
   Reference the Phase 1 ADR in the commit body so the reversal is traceable.

6. Remove orphaned icon imports: after all deletions, grep each identifier in the
   import block against the rest of the file body and drop the unreferenced ones.
   Do not work from a guessed list — `Play` and `CheckSquare` appear only inside
   the deleted comment and were never imported.

7. Verify no two siblings share a `title` and no two groups share a `label`, since
   these are render keys.

## Success Criteria

- [ ] No commented `NavGroup` remains
- [ ] `grep -rn "mainNavItems" resources/js` returns only `AppHeader.vue`
- [ ] No Vietnamese remains in any `title`/`label`. Verify with an explicit word
      list (`Hôm nay`, `Doanh thu`, `Sinh phí`, `Thu`, `Đối soát`, `Ngoại lệ`,
      `Tra cứu`, `Thi lại`, `Lập`, `Cần kiểm tra`, `Kết quả`) — a non-ASCII scan
      alone misses `Doanh thu` and `Sinh phí`, which are pure ASCII
- [ ] `git diff` touches only `title`, `label`, imports, deletions, and the two
      test files — no `href` or `requiredPermissions` line modified
- [ ] No duplicate sibling `title`; no duplicate group `label`
- [ ] `./scripts/dev.sh artisan test tests/Feature/Finance/Cutover tests/Feature/Finance/Reporting tests/Feature/Finance/DetailRouteContractTest.php` green
- [ ] `./scripts/dev.sh npm run lint -- --max-warnings=0 resources/js/constants/menu-sidebar.ts` clean
- [ ] `href` multiset unchanged from `baseline_commit`

## Risk Assessment

`no-unused-vars` is a warning under the repo's eslint config, so eslint exits 0
with an orphaned import still present — hence `--max-warnings=0` in the gate.
Bare `npx`/`npm` is forbidden by `.claude/rules/development-rules.md`; route
through `./scripts/dev.sh`.

The Finance suite has a known pre-existing baseline of failures. Record the
before/after counts for the three named test files specifically, so a new red is
not absorbed into the existing noise.
