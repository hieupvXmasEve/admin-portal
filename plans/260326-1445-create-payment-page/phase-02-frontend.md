---
phase: 2
title: "Frontend: Create.vue Page"
status: pending
effort: 2h
---

# Phase 2 — Frontend: Create Payment Page

## Context Links

- Pattern ref: `resources/js/pages/Finance/Charges/Create.vue`
- Student picker: `resources/js/components/StudentCombobox.vue`
- Show page types: `resources/js/pages/Finance/Payments/Show.vue`
- Composable: `resources/js/composables/useApi.ts`
- Format utils: `resources/js/utils/format.ts`

## Overview

Create `resources/js/pages/Finance/Payments/Create.vue` — a full-page form with student selection, balance summary, two entry modes (manual vs balance-based), and standard payment fields.

## Files to Create

- `resources/js/pages/Finance/Payments/Create.vue`

## Architecture

```
Create.vue
├── Header (back button + title)
├── Main content (lg:col-span-2)
│   ├── Card: Student Selection (StudentCombobox)
│   │   └── Balance summary card (shown after selection, fetched via API)
│   ├── Card: Payment Mode Toggle (manual | balance-based)
│   │   └── If balance-based: Outstanding charges table with checkboxes
│   └── Card: Payment Details
│       ├── Amount (auto-filled in balance mode, editable in manual)
│       ├── Method (Select)
│       ├── Source (Input, optional)
│       ├── External Ref (Input, optional)
│       ├── Paid At (date input, defaults to today)
│       └── Notes (Textarea, optional)
└── Sidebar (lg:col-span-1)
    ├── Card: Actions (Submit + Cancel buttons)
    └── Card: Summary (dynamic totals)
```

## Implementation Steps

### 1. Page props and types

```ts
interface PaymentMethod {
    value: string;
    label: string;
}

interface OutstandingCharge {
    id: number;
    description: string;
    charge_type: string;
    amount: number;
    balance: number;
    semester: string | null;
}

interface BalanceSummary {
    total_charges: number;
    total_credits: number;
    net_charges: number;
    total_paid: number;
    balance: number;
    unapplied_credit: number;
    status: string;
}

const props = defineProps<{
    paymentMethods: PaymentMethod[];
}>();
```

### 2. Form setup with `useForm`

```ts
const form = useForm({
    student_id: null as number | null,
    amount: null as number | null,
    method: 'cash',
    source: '',
    external_ref: '',
    paid_at: new Date().toISOString().split('T')[0], // today
    notes: '',
    auto_allocate: false,
});
```

### 3. Student selection + data fetching

Use `StudentCombobox` component. On `@select` event:
- Store selected student ref
- Call `GET /finance/payments/{studentId}/charges` via `useApi`
- Populate `balanceSummary` and `outstandingCharges` refs
- Reset form amount and mode

```ts
const selectedStudent = ref<Student | null>(null);
const balanceSummary = ref<BalanceSummary | null>(null);
const outstandingCharges = ref<OutstandingCharge[]>([]);
const loadingCharges = ref(false);
const mode = ref<'manual' | 'balance'>('manual');

const api = useApi();

const onStudentSelect = async (student: Student | null) => {
    selectedStudent.value = student;
    form.student_id = student?.id ?? null;
    balanceSummary.value = null;
    outstandingCharges.value = [];
    selectedChargeIds.value = [];

    if (!student) return;

    loadingCharges.value = true;
    try {
        const res = await api.get(`/finance/payments/${student.id}/charges`);
        if (res.data.value) {
            balanceSummary.value = res.data.value.balance;
            outstandingCharges.value = res.data.value.charges;
        }
    } finally {
        loadingCharges.value = false;
    }
};
```

### 4. Balance summary display

After student selected, show a compact Card:

| Label | Value |
|-------|-------|
| Total Charges | `formatCurrency(net_charges)` |
| Total Paid | `formatCurrency(total_paid)` |
| **Outstanding** | `formatCurrency(balance)` — red if > 0 |
| Unapplied Credit | `formatCurrency(unapplied_credit)` — green |

### 5. Mode toggle

Simple two-button toggle or `Tabs` component:
- **Manual**: Amount field is editable, `auto_allocate` = false
- **Balance-based**: Show charges table with checkboxes, amount auto-calculated from selected charges' `balance`, `auto_allocate` = true

```ts
const selectedChargeIds = ref<number[]>([]);

const computedAmount = computed(() => {
    if (mode.value === 'balance') {
        return outstandingCharges.value
            .filter(c => selectedChargeIds.value.includes(c.id))
            .reduce((sum, c) => sum + c.balance, 0);
    }
    return form.amount;
});

watch(mode, (newMode) => {
    if (newMode === 'balance') {
        form.auto_allocate = true;
    } else {
        form.auto_allocate = false;
        selectedChargeIds.value = [];
    }
});

watch(computedAmount, (val) => {
    if (mode.value === 'balance') {
        form.amount = val;
    }
});
```

### 6. Outstanding charges table (balance mode)

Table columns: Checkbox | Description | Semester | Charge Amount | Remaining Balance

- Use `Checkbox` from `@/components/ui/checkbox`
- "Select All" checkbox in header
- Only show charges with `balance > 0`
- Each row toggles its ID in `selectedChargeIds`

### 7. Payment details card

Standard form fields matching `Show.vue` display:
- **Amount**: `NumberField` component (same pattern as Charges/Create.vue), disabled in balance mode
- **Method**: `Select` with `paymentMethods` prop
- **Source**: `Input` (optional)
- **External Ref**: `Input` (optional)
- **Paid At**: `Input type="date"`, default today
- **Notes**: `Textarea` (optional)

### 8. Sidebar

**Actions card**:
```vue
<Button type="submit" class="w-full"
    :disabled="form.processing || !form.student_id || !form.amount">
    Record Payment
</Button>
<Link :href="route('finance.payments.index')">
    <Button type="button" variant="outline" class="w-full">Cancel</Button>
</Link>
```

**Summary card** (dynamic):
- Selected student name
- Mode label
- Amount to record
- Number of charges selected (if balance mode)

### 9. Form submission

```ts
const handleSubmit = () => {
    form.post(route('finance.payments.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Payment recorded successfully'),
        onError: () => toast.error('Failed to record payment'),
    });
};
```

### 10. UI components used

All from existing `@/components/ui/`:
- `Card`, `CardContent`, `CardHeader`, `CardTitle`, `CardDescription`
- `Button`, `Input`, `Label`, `Textarea`, `Badge`, `Separator`
- `Select`, `SelectContent`, `SelectItem`, `SelectTrigger`, `SelectValue`
- `NumberField`, `NumberFieldContent`, `NumberFieldInput`
- `Table`, `TableBody`, `TableCell`, `TableHead`, `TableHeader`, `TableRow`
- `Checkbox`

External:
- `StudentCombobox` from `@/components/StudentCombobox.vue`
- Icons: `ArrowLeft`, `Loader2`, `Check` from `lucide-vue-next`

## Todo List

- [ ] Create `resources/js/pages/Finance/Payments/Create.vue`
- [ ] Student selection section with `StudentCombobox`
- [ ] Balance summary display after student selection
- [ ] Mode toggle (manual / balance-based)
- [ ] Outstanding charges table with checkboxes (balance mode)
- [ ] Payment details form fields
- [ ] Sidebar with submit/cancel and summary
- [ ] Form submission with `useForm`
- [ ] Error display for all fields (`form.errors.*`)
- [ ] Loading states for charges fetch

## Success Criteria

- Page renders at `/finance/payments/create`
- Student search works via existing `StudentCombobox`
- Balance summary appears after student selection
- Manual mode: amount editable, no auto-allocate
- Balance mode: charges shown, checkboxes control amount, auto-allocate enabled
- Form submits, redirects to Show page with success toast
- Validation errors display inline
- Responsive layout (single column on mobile, 3-col grid on desktop)

## Risk Assessment

- **`StudentCombobox` emits**: Verify `@select` emits full student object with `id` — confirmed from source
- **API response shape**: `getStudentCharges` returns `{ balance: {...}, charges: [...] }` — ensure backend matches
- **NumberField VND formatting**: Follow exact pattern from `Charges/Create.vue` line 185-199
