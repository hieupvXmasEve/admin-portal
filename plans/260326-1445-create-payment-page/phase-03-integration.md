---
phase: 3
title: "Integration: Index button, permission sync, smoke test"
status: pending
effort: 0.5h
---

# Phase 3 — Integration

## Overview

Wire the Create Payment page into the existing UI, sync permissions, and verify the full flow.

## Files to Modify

- `resources/js/pages/Finance/Payments/Index.vue` — add "Create Payment" button in header
- Database: run permission seeder/sync

## Implementation Steps

### 1. Add "Create Payment" button to Index.vue

In `Index.vue` template, inside the header `<div class="flex gap-2">` block (line ~113), add before the existing buttons:

```vue
<Link :href="route('finance.payments.create')">
    <Button>
        <Plus class="mr-2 h-4 w-4" />
        Create Payment
    </Button>
</Link>
```

Import `Plus` from `lucide-vue-next` in the script section.

### 2. Sync permissions

Run inside Docker:
```bash
docker exec $(docker ps -qf "name=swinx" | head -1) php artisan permission:sync-all
```

Or if no sync command, add `create_finance_payment` to the relevant role(s) via tinker/seeder.

### 3. Verify route registration

```bash
docker exec $(docker ps -qf "name=swinx" | head -1) php artisan route:list --name=finance.payments
```

Expected new routes:
- `GET finance/payments/create` → `finance.payments.create`
- `POST finance/payments` → `finance.payments.store`
- `GET finance/payments/{student}/charges` → `finance.payments.student-charges`

### 4. Smoke test flow

1. Navigate to `/finance/payments` — verify "Create Payment" button visible
2. Click it — verify `/finance/payments/create` loads
3. Search and select a student — verify balance summary appears
4. **Manual mode**: enter amount, select method, submit — verify redirect to Show page
5. **Balance mode**: toggle to balance, check charges, verify amount auto-calculates, submit with auto-allocate — verify payment + allocations created
6. Test validation: submit without student, submit with 0 amount, submit with future date
7. Test unauthorized: user without `create_finance_payment` permission gets 403

### 5. Build check

```bash
pnpm run type-check
pnpm run build
```

## Todo List

- [ ] Add "Create Payment" button to `Index.vue`
- [ ] Sync `create_finance_payment` permission to roles
- [ ] Verify routes registered correctly
- [ ] Smoke test manual mode flow
- [ ] Smoke test balance-based mode flow
- [ ] Verify validation errors display
- [ ] Verify authorization (403 for unauthorized)
- [ ] Run `pnpm run type-check` — no errors
- [ ] Run `pnpm run build` — no errors
- [ ] Run `vendor/bin/pint --dirty` — code formatted

## Success Criteria

- Full create-to-show flow works end-to-end
- Both modes (manual + balance-based) functional
- No TS or build errors
- Permission-gated properly
