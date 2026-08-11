---
phase: 3
title: "Frontend column set and visibility"
status: done
priority: P1
effort: "1.5d"
dependencies: [2]
---

# Phase 3: Frontend column set and visibility

<!-- Updated: Red Team Session 1 - 58-page blast radius, ColumnMeta augmentation, real table-width fix, versioned storage key -->

## Overview

Render the new CRM fields as columns, turn on the existing `DataTable`
column-toggle, give the toggle readable labels, and persist the user's choice in
localStorage.

## Requirements

- Functional: all ship-list columns defined; only today's 8 + document columns
  visible by default (D8), so existing users see no change until they opt in.
- Functional: hiding/showing a column survives reload, per browser (D4).
- Functional: English headers (D3); CRM document names stay Vietnamese.
- Non-functional: the table must actually scroll horizontally when many columns
  are enabled.
- Non-functional: the shared-component change must not silently alter ~58 other
  pages.

## Architecture

**Column-toggle already exists.** `DataTable.vue:154-176` renders a
`DropdownMenuCheckboxItem` per hideable column behind `showColumnToggle`.
`Index.vue:304` passes `false`. Flipping it is most of the feature.

### Blast radius of the label fix — 58 pages, not "several"

Measured: **69** `<DataTable` usages; **11** pass `:show-column-toggle="false"`.
So ~58 pages render this dropdown today with raw `column.id` labels
(`DataTable.vue:172`), and the item carries `class="capitalize"` tuned for
snake_case ids.

The change must therefore be **strictly additive**:

```ts
// prefer an explicit meta.label, then a plain-string header, else today's behavior
const columnLabel = (column: Column<TData>): string =>
    column.columnDef.meta?.label
    ?? (typeof column.columnDef.header === 'string' && column.columnDef.header !== ''
        ? column.columnDef.header
        : column.id);
```

Pages that declare string headers gain prose labels; pages with render-function
headers are unchanged. Verify `capitalize` does not mangle prose headers — drop
it when a label is used.

### `meta.label` needs a type augmentation

TanStack's `ColumnMeta` is an empty interface by design. Grep found **zero**
`declare module '@tanstack/vue-table'` augmentations and **zero** existing column
defs using `meta` in `resources/js`. Adding `meta: { label }` to a `ColumnDef`
literal fails type-check without an augmentation — and this phase's validation is
eslint-only (whole-project `type-check` OOMs in the dev container), so eslint
will not catch it and CI will go red.

The augmentation file is therefore an explicit deliverable, not an afterthought.

### Persistence

No column-visibility persistence exists in `resources/js` (verified). The only
localStorage precedent is `useAppearance.ts:43,69,79`. Create:

`resources/js/composables/use-table-column-visibility.ts`

Storage key must be **versioned** — `student-applications:columns:v1` — so a
future column-set change invalidates stored state instead of silently reapplying
a stale set. Prune unknown ids on read.

`DataTable.vue` owns `columnVisibility` as a local ref (line 41). Add an optional
`v-model:column-visibility` defaulting to the current internal ref, so no other
caller changes.

### Table width — the previously-claimed mitigation does not exist

`components/ui/table/Table.vue` wraps `<table class="w-full">` in a
`div.overflow-auto`. A `w-full` table does **not** overflow — it compresses. With
25+ columns enabled the browser squeezes columns to a few characters and wraps
vertically instead of scrolling, and the sticky-right `actions` pin
(`DataTable.vue:44`) stops behaving as a pin because nothing overflows.

Fix explicitly: `min-w-max` on the table (or per-column `whitespace-nowrap`) and
verify with 25 columns enabled. Scope the change so it does not alter the 58
other tables — prefer a `DataTable`-level opt-in prop over editing the shared
`ui/table` primitive.

### Default visible set

Explicit allow-list, not "everything on" (D8). Today's 8 (`full_name`,
`student_code`, `national_id`, `phone`, `intended_program`, `intake`, `status`,
`created_at`) + all document columns + `actions`. Everything new starts hidden.

## Related Code Files

- Create: `resources/js/composables/use-table-column-visibility.ts`
- Create: `resources/js/types/tanstack-table.d.ts` (ColumnMeta augmentation)
- Modify: `resources/js/components/DataTable.vue` (label resolution; optional `v-model:column-visibility`; opt-in min-width)
- Modify: `resources/js/pages/StudentApplications/Index.vue`

## Implementation Steps

1. Add the `ColumnMeta` augmentation with a `label?: string` field.
2. Add `columnLabel()` to `DataTable.vue`'s toggle dropdown, strictly additive.
   Spot-check a representative sample across the 58 affected pages — at minimum
   one with string headers, one with render-function headers, one with pinned
   columns.
3. Add the optional `columnVisibility` model, defaulting to the internal ref.
4. Add the opt-in horizontal-scroll/min-width behavior to `DataTable`.
5. Write `use-table-column-visibility.ts` with a versioned key and id pruning.
6. Extend `ApplicationRow` to match the phase-2 payload exactly.
7. Extend the `columns` computed, grouped in source order (identity → admission →
   academic → location → english → sync → finance).
8. Add cell slots only where raw output is wrong: null → `—`, `gpa` formatting,
   `crm_paid_amount` VND, `last_synced_at` date. Per **D12**, a `0.00` in
   `overall` or `gpa` means "no data" — render it as `—`, not `0`.
9. Flip `:show-column-toggle="true"` and wire the persisted model.
10. Verify against the running app with 25 columns enabled.

## Validation

```bash
./scripts/dev.sh npm exec eslint -- resources/js/pages/StudentApplications/Index.vue resources/js/components/DataTable.vue resources/js/composables/use-table-column-visibility.ts
```

Whole-project `npm run type-check` OOMs in `swinx-app-dev` — but this phase adds
a **type augmentation**, which eslint cannot validate. Run a scoped `vue-tsc` on
the touched files, or accept that CI is the gate and say so.

Manual: load the page, confirm the default view matches today, enable several
CRM columns, reload, confirm they persist; disable, reload, confirm.

## Success Criteria

- [ ] Toggle shows English labels, never raw ids like `crm_campus`
- [ ] Document columns show their Vietnamese catalog name
- [ ] Pages with render-function headers are visually unchanged
- [ ] Default visible set appears identical to today's page
- [ ] Visibility persists across reload; clearing localStorage restores defaults
- [ ] A removed column id does not corrupt stored state
- [ ] With 25 columns enabled the table **scrolls horizontally**; columns are not
      compressed and the actions column stays pinned
- [ ] Null values render `—`, and a `0.00` `overall`/`gpa` also renders `—` (D12)
- [ ] Type augmentation present; a scoped type-check of the touched files passes
- [ ] eslint clean

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Label change alters ~58 other pages | Strictly additive fallback chain; sample across header styles, not just 2-3 pages |
| `meta.label` fails CI type-check | Augmentation is an explicit deliverable; scoped type-check in validation |
| Table compresses instead of scrolling | Explicit min-width step + 25-column verification; the old "already handled" claim was false |
| Stored state survives a phase-2 revert and shows columns of `—` | Versioned storage key; **phase 3 must be reverted before or together with phase 2** — stated here because the Phases table records forward dependencies only |
| 40-entry dropdown is unusable | Group columns in source order; add separators only if it reads badly in practice |
