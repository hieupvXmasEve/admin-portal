---
phase: 3
title: "Harden the shim guard"
status: pending
priority: P1
effort: "2h"
dependencies: [1]
---

# Phase 3: Harden the shim guard

<!-- New phase, added after red-team session 1: the phase-1 guard has three evasions and one blind spot that would let phase 4 delete a shim with a live caller still standing -->

## Overview

Fix the four defects red-team found in phase 1's
`tests/Feature/Architecture/DeprecatedModelShimArchTest.php` **before** phase 4
relies on it to prove a shim is safe to delete. Pure test work: no shim deleted,
no caller swept, no migration.

The guard as shipped would have let phase 4 delete `app/Models/UploadRecord.php`
while `app/Models/Answer.php` still imported it — a production fatal on a path
the guard structurally cannot see.

## Requirements

- Functional: the guard detects a shim importer anywhere under `app/`, including inside `app/Models/`.
- Functional: the guard detects a reintroduced shim in a subdirectory and a reintroduced shim written as a subclass rather than `class_alias`.
- Functional: the import assertion does not weaken as models leave `SHIMMED_MODELS`.
- Functional: the guard has no configuration in which it degenerates into a tautology.
- Non-functional: no production code touched. No shim deleted.
- Non-functional: each fix proven by a scratch violation that turns the test red, then reverted.

## Architecture

Four independent defects, all in `tests/Feature/Architecture/DeprecatedModelShimArchTest.php`.

### D1 — `app/Models/` is skipped wholesale, hiding a real caller

Line 184: `if (str_starts_with($relativePath, 'app/Models/')) { continue; }`.
The intent was to skip the shim files themselves, which legitimately name
`App\Models\X`. The effect is to skip **every** file in that directory,
including non-shim models.

`app/Models/Answer.php` is a real model, not a shim, and it holds a live
relation:

```php
use App\Models\UploadRecord;              // :10
return $this->hasMany(UploadRecord::class, 'answer_id');   // :61
```

This caller appears in no count, no phase file list, and no guard output. Every
phase's re-measure command compounds it with `| grep -v '^app/Models/'`.

**Fix:** skip only files that actually contain `class_alias(`, not the directory.
Enumerated the blast radius: `Answer.php` is the **only** such file today, so
the corrected baseline is 93, not 92.

### D2 — Shim detection is non-recursive and text-only

Line 140 is `glob($modelsDir.'/*.php')` — one level — and line 142 matches the
literal string `class_alias(`. Both are evadable:

- `app/Models/Engagement/Club.php` — a subdirectory, never globbed.
- `class Club extends \App\Modules\Engagement\Models\Club {}` — no `class_alias(`
  anywhere, so it passes.

The subclass form is **strictly worse** than a `class_alias`: it creates a
genuinely distinct class, so `get_class()` and `getMorphClass()` return
`App\Models\Club` again and the persisted-FQCN problem returns in full.

**Fix:** assert on behavior, not text. For each of the 30 names,
`expect(class_exists('App\Models\X'))->toBeFalse()` once that name is swept.
Keep a recursive `RecursiveDirectoryIterator` scan for `class_alias(` as the
allow-list check for names not yet swept.

### D3 — Deleting a shim from `SHIMMED_MODELS` blinds the import guard to it

Lines 163-164 build the import regex *from* `SHIMMED_MODELS`. So the moment
phase 4 removes a model from that list, missed callers of that model become
invisible — precisely inverting the plan's own mitigation ("the phase-1 guard
fails on anything missed").

**Fix:** decouple. Keep a fixed `ALL_MIGRATED_MODELS` const of all 30 names for
the import regex, permanently. `SHIMMED_MODELS` shrinks only as the
still-shimmed allow-list.

### D4 — The empty-list early return is a tautology

Phase 5's original instruction added
`if (SHIMMED_MODELS === []) { expect(true)->toBeTrue(); return; }`, which makes
the import guard return before scanning anything, forever. With D3 fixed this is
unnecessary: the regex is built from the fixed 30-name list, which is never
empty.

### Also: withdraw V2-a

The original phase files told each module phase to **delete** the
shim-must-exist block from its placement arch test, on the reasoning that the
phase-1 guard already covers it repo-wide. It does not. That block holds two
assertions (`UploadModelPlacementArchTest.php:27-41`):

```php
expect(file_exists($legacyPath))->toBeTrue("Expected shim to remain ...");
expect($contents)->toContain('class_alias(')
    ->not->toContain("class {$model} extends");
```

The second is the guard against re-declaring a real duplicate model — the exact
regression the parent plan `260809-1557` existed to prevent — and the phase-1
guard's `class_alias(` grep does not replace it.

**Corrected decision (V3-a, supersedes V2-a):** *invert*, do not delete. Change
`toBeTrue()` → `toBeFalse()` for swept models and drop the `toContain('class_alias(')`
line, keeping `not->toContain("class {$model} extends")`. One line changed per
test instead of a block removed. Phase 4 applies this per module; this phase only
records the decision.

## Related Code Files

- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` (all four defects)
- Modify: nothing else. The six placement arch tests are edited in phase 4, per model, using V3-a.

## Implementation Steps

1. **Fix D1.** Replace the directory skip with a shim-file skip:

   ```php
   $contents = file_get_contents($file->getPathname()) ?: '';
   if (str_contains($contents, 'class_alias(') && str_starts_with($relativePath, 'app/Models/')) {
       continue;
   }
   ```

   Re-measure the baseline; add `app/Models/Answer.php`. Expect 93 entries.

2. **Fix D3 first, then D2** — D2's behavioral assertion needs the fixed name
   list. Add:

   ```php
   // All 30 models moved by 260809-1557. Never shrinks — the import guard must
   // keep watching a model after its shim is gone, or a missed caller becomes
   // invisible exactly when the shim stops covering for it.
   const ALL_MIGRATED_MODELS = [ /* the 30 names */ ];
   ```

   Build the import regex from `ALL_MIGRATED_MODELS`. Leave `SHIMMED_MODELS` as
   the still-shimmed allow-list only.

3. **Fix D2.** Make the shim scan recursive, and add a third assertion: for every
   name in `ALL_MIGRATED_MODELS` that is *not* in `SHIMMED_MODELS`, assert
   `class_exists('App\Models\<Name>') === false`. That catches the subclass form,
   the subdirectory form, and a `class_alias` reintroduced anywhere.

4. **Drop D4's early return** from phase 5's plan (already reflected in that file).

5. **Prove each fix fails on a real violation**, then revert the scratch file:

   | Defect | Scratch violation | Expected |
   |---|---|---|
   | D1 | add `use App\Models\Room;` to a new `app/Models/ZzProbe.php` (no `class_alias`) | import assertion red |
   | D2a | create `app/Models/Sub/Club.php` with `class_alias(` | shim assertion red |
   | D2b | create `app/Models/ZzClub.php` with `class ZzClub extends \App\Modules\Engagement\Models\Club {}` — then the real test: after a name is swept, re-add `app/Models/<Name>.php` as a subclass | `class_exists` assertion red |
   | D3 | remove a name from `SHIMMED_MODELS` and confirm its importers are still flagged | import assertion still red |

6. **Record the corrected baseline count** (93) in `plan.md`'s Evidence Base.

## Success Criteria

- [ ] `app/Models/Answer.php` appears in the guard's importer baseline; count is 93
- [ ] Guard flags a shim importer located inside `app/Models/` (D1 proven red, then reverted)
- [ ] Guard flags a `class_alias` shim in a subdirectory (D2a proven red, then reverted)
- [ ] Guard flags a subclass-style reintroduction via `class_exists` (D2b proven red, then reverted)
- [ ] Import regex built from a fixed 30-name const, not from `SHIMMED_MODELS` (D3 proven: removing a name does not blind the guard to it)
- [ ] No configuration of the guard is a tautology — no early return, no empty alternation
- [ ] V3-a recorded, superseding V2-a; no placement arch test edited yet
- [ ] `tests/Feature/Architecture` matches its 5-failure baseline; zero production files changed

## Risk Assessment

| Risk | Mitigation |
|---|---|
| An empty alternation from a shrunk list matches every `App\Models\` reference and floods the test | D3 makes the list fixed at 30 and never empty; success criteria require proving a non-shim reference is not flagged |
| `class_exists` assertion triggers autoload side effects in the test process | It is a pure existence check on classes the app already autoloads; no instantiation. If a name resolves via a stale optimized classmap in CI, that is itself the signal the assertion exists to give |
| The 93rd caller (`Answer.php`) turns out not to be the only one hidden by D1 | Step 1 re-measures the whole repo after the fix rather than trusting the enumerated single case |
| Hardening the guard reddens it before phase 4 can act | Intended and bounded: only the scratch violations go red, and they are reverted in the same step |
