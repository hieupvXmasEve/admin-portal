---
phase: 3
title: "IA restructure from holder sets"
status: done
priority: P1
effort: "4h"
dependencies: [2]
---

# Phase 3: IA restructure from holder sets

## Overview

Rebuild `mainNavGroups` into the 10-group tree from `plan.md`. Every move is
driven by the per-permission holder sets confirmed in Phase 1 — not by domain
name similarity, which is what produced the wrong IA in the first version of this
plan.

## Requirements

Functional:
- Group order and membership match the target IA table in `plan.md`.
- Four of the five Discounts items fold into Finance; `Scholarship Adjustments`
  goes to Academic instead.
- `Campus Operations` splits into `Campus` (Rooms & Bookings, Events) and
  `Store & Clubs` (Clubs, Merchandise).
- `Faculty & Teaching` becomes an `Academic Operations` subgroup.
- `Students` and `Reports & Audits` keep their exact current contents (ADR-0007).

Non-functional:
- The multiset of `href` values is unchanged from `baseline_commit`.
- Every `requiredPermissions` array travels with its item unchanged — moved, never
  retyped.
- No wrapper item carries `requiredPermissions`.

## Architecture

Placement follows holder sets, which the first version of this plan got wrong by
bucketing permissions on name prefixes. The two corrections that matter:

**Scholarship Adjustments does not belong in Finance.**
`view_scholarship_adjustment` is held by `ACA|HQ|SA`, and `Cán Bộ Đào tạo` holds
**zero** `finance_*` permissions. Folding it into Finance would render, for the
second-largest persona, a top-level group labelled "Finance" containing exactly
one item. ADR-0026 independently assigns Progression Actions to Academic and
money to Finance. The other four Discounts items are all `HQ|SA` and do belong in
Finance.

**The Campus Operations split axis was wrong.** `view_room` (`ACA|DIR|HQ|ADM|SA`)
and `view_event` (`ACA|DIR|HQ|SA`) have nearly identical holder sets, so
separating them into "Facilities" and "Campus Life" would split a coherent set
and hand four personas a one-item group. The real boundary is the *booking
workflow* — `view_room_booking`, `create_room_booking`, `approve_room_booking` are
all `ADM|SA`. Rooms and Events stay together under `Campus`; the pure-`HQ|SA`
commerce cluster (Clubs, Merchandise, Redemption Orders) becomes `Store & Clubs`.

**Wrapper permissions are inert.** `usePermissions.ts:36-44` returns early for any
item carrying `children`, so a wrapper's own `requiredPermissions` is never
evaluated — the check at `:46` is unreachable for parents. A wrapper that carries
one reads in review as a guard and is not. This phase creates new wrappers, so the
invariant must hold: **wrappers (`href: '#'` with `children`) never carry
`requiredPermissions`.**

**Array order is semantic.** `NavMain.vue:30-58` iterates in array order and
returns on the first exact match, so reordering groups can change which item wins
the active highlight when two share an href prefix.

## Related Code Files

- Modify: `resources/js/constants/menu-sidebar.ts`

## Implementation Steps

1. **Fold Faculty into Academic Operations.** Move the three `lecturer*` items
   verbatim into a new subgroup at the end of `Academic Operations`:

   ```ts
   {
       title: 'Faculty',
       href: '#',
       icon: Users,
       children: [ /* Lecturer List, Lecturer Hours, Lecturer GPA — verbatim */ ],
   }
   ```

   Delete the emptied `Faculty & Teaching` group. Accepted cost: `ACA`/`DIR` gain
   one click to reach lecturer screens. Justified because `view_lecturer`
   (`ACA|DIR|SA`) is a strict subset of the group's owners.

2. **Move `Scholarship Adjustments` to Academic Operations >
   Grades & Performance.** It sits alongside GPA Management, GPA History, and
   Warning Center — the progression-outcome screens it follows from. Validation
   decision 3 confirmed the app placement wins over the `docs-site`
   classification; Phase 5 moves the docs page to match.

2b. **Move `Course Statistics` to Academic Operations > Course Delivery**, after
   `Course Offering List`. `CourseStatistics/Index.vue:24-25` reports
   `average_attendance` **and** `average_grade` per course offering, so it is a
   per-offering summary rather than an attendance screen (validation decision 4).
   The `Attendance` subgroup is left with two items: `Attendance Summary` and
   `Failed Students`.

3. **Fold the remaining four Discounts items into Finance** as a subgroup placed
   after `Exceptions` and before `Lookup & Audit`:

   ```ts
   {
       title: 'Discounts & Funding',
       href: '#',
       icon: Award,
       children: [ /* Tuition Plans, Scholarships, Student Scholarships,
                      Vouchers — verbatim */ ],
   }
   ```

   Delete the old top-level group and its redundant wrapper item (its `label`,
   its item `title`, and the submenu heading were all the same string).

4. **Split Campus Operations into two groups:**
   - `Campus` — the `Room Management` subgroup verbatim (keep the subgroup; with
     Events beside it the wrapper earns its place), plus the `Events` subgroup
     verbatim.
   - `Store & Clubs` — the `Clubs` item verbatim and the `Merchandise` subgroup
     verbatim.

5. **Reorder groups** to: Overview, Academic Operations, Students,
   Reports & Audits, Finance, Store & Clubs, Campus, Forms & Surveys,
   Communications, Administration.

6. **Rename** `Student Services` -> `Students` and `Forms & Quality` ->
   `Forms & Surveys` (the group holds forms, runs, surveys, and an inbox —
   nothing that reads as "quality").

7. **Leave `CRM Value Mappings` in `Students`** (validation decision 7). It maps
   CRM values for the Student Applications flow, so it stays beside the feature
   it serves; moving it to Administration would give HQ a one-item group.

8. Keep the existing ADR-0007 comment blocks on `Students` and `Reports & Audits`
   — that reasoning still holds. Add a short comment at each new wrapper noting
   that `requiredPermissions` on a wrapper is not evaluated.

## Success Criteria

- [ ] `mainNavGroups` has exactly 10 groups in the target order
- [ ] `Faculty & Teaching`, `Discounts & Funding`, and `Campus Operations` no
      longer exist as top-level groups
- [ ] `Scholarship Adjustments` is under Academic Operations, not Finance
- [ ] `Course Statistics` is under Course Delivery; `Attendance` has two items
- [ ] `CRM Value Mappings` is still in `Students`
- [ ] `href` multiset identical to `baseline_commit`; `sort | uniq -d` empty
- [ ] Every moved item's `requiredPermissions` unchanged (`git diff` shows the
      lines moved, not edited)
- [ ] No wrapper item carries `requiredPermissions`
- [ ] No duplicate sibling `title`; no duplicate group `label`
- [ ] `./scripts/dev.sh npm run lint -- --max-warnings=0 resources/js/constants/menu-sidebar.ts` clean
- [ ] Finance test trio still green

## Risk Assessment

The dominant risk is an item lost or duplicated while moving blocks — a missing
screen is invisible until someone goes looking for it, and a duplicate is
invisible to a set comparison. Phase 4 formalizes the multiset gate; run it at
the end of this phase too, not only at the end.

If Phase 1 rebuilt the holder-set table from production and it differs from dev,
re-derive every move in this phase before implementing. Do not implement the
table as written on dev-only evidence.
