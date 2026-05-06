# Phase 3: Frontend — DNG Worklist Page

## Status: pending

## Tasks

### 3.1 Create `DngWorklist.vue`

**File:** `resources/js/pages/Finance/Operations/DngWorklist.vue`

**Props (from controller):**
```typescript
interface Props {
    students: PaginatedResponse<DngWorklistStudent>;
    filters: {
        dng_fee_type: string;
        search?: string;
        campus_id?: number | null;
        semester_id?: number | null;
        dng_status?: string;
        per_page?: number;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
    summary: {
        total_students: number;
        total_balance: number;
        students_with_active_dng: number;
        students_without_dng: number;
    };
    feeTypeOptions: { value: string; label: string }[];
    semesters: { id: number; name: string; code: string }[];
    campuses: { id: number; name: string; code: string }[];
}

interface DngWorklistStudent {
    student_id: number;
    student_code: string;
    student_name: string;
    campus_name: string;
    charge_count: number;
    total_amount: number;
    total_paid: number;
    total_discount: number;
    balance: number;
    active_dng: {
        id: number;
        status: string;
        amount: number;
        created_at: string;
    } | null;
    charges: {
        id: number;
        charge_type: string;
        amount: number;
        balance: number;
        description: string;
        semester: string;
    }[];
}
```

### 3.2 Page Layout

```
┌────────────────────────────────────────────────────────────┐
│  ← Back    DNG Worklist                     [Push DNG (N)] │
├────────────────────────────────────────────────────────────┤
│  Fee Type: [HP ▾]   Search: [______]   Campus: [▾]        │
│  Semester: [▾]      DNG Status: [▾]    [Clear filters]    │
├────────────────────────────────────────────────────────────┤
│  Summary Cards: Total SV | Total Balance | Has DNG | No DNG│
├────────────────────────────────────────────────────────────┤
│  [☐] Student     | Charges | Balance    | DNG Status | Amt │
│  [☑] Nguyen A    | 2       | 15,000,000 | ⚪ No DNG  |[___]│
│      └─ tuition_term: 10M (bal: 10M)                      │
│      └─ course_fee: 5M (bal: 5M)                           │
│  [☑] Tran B      | 1       | 7,500,000  | 🟢 Pushed  |[___]│
│  [ ] Le C        | 3       | 0          | —          | —   │
├────────────────────────────────────────────────────────────┤
│  Pagination                                                │
└────────────────────────────────────────────────────────────┘
```

### 3.3 Key Components

**Fee Type Selector (required):**
- Select component bound to `dng_fee_type` filter
- On change → reload page with new fee_type filter (Inertia visit)
- `immediateFields: ['dng_fee_type']` in useDataTable

**Student Table:**
- Expandable rows showing charge breakdown
- Checkbox selection (only students with balance > 0)
- Amount column: editable input, defaults to balance
- DNG status badge:
  - No DNG → gray "Chưa tạo"
  - Pending → yellow "Pending"
  - Pushed → green "Đã gửi"
  - If active_dng exists but amount ≠ balance → orange "Cần cập nhật"

**Batch Push Form:**
- Top-right button "Push DNG (N selected)"
- Opens dialog with:
  - Description (required)
  - Due date (required, DatePicker)
  - Semester (required, auto-selected if filter is set)
  - Estimate time (MM/YY picker, reuse existing pattern)
  - Summary: N students, total amount
  - Warning if any selected student has existing active DNG (will be cancelled)
- Submit → POST to `dng-worklist.store`
- Use `useForm` from Inertia (page form)

### 3.4 useDataTable Config

```typescript
useDataTable<DngWorklistFilters>({
    baseUrl: route('finance.operations.dng-worklist'),
    initialFilters: {
        dng_fee_type: props.filters.dng_fee_type ?? 'HP',
        search: props.filters.search ?? '',
        campus_id: props.filters.campus_id ?? null,
        semester_id: props.filters.semester_id ?? null,
        dng_status: props.filters.dng_status ?? 'all',
        per_page: props.filters.per_page ?? 50,
        sort: props.filters.sort ?? null,
        direction: props.filters.direction ?? null,
    },
    defaultValues: { ... },
    only: ['students', 'summary', 'filters'],
    debounce: 300,
    immediateFields: ['dng_fee_type', 'campus_id', 'semester_id', 'dng_status'],
});
```

### 3.5 HL Fee_Type: Approved Registration Indicator

When `dng_fee_type = HL`, students may have `needs_charge_creation = true` (approved retake
registration without charge yet). Show this in the UI:

- Badge: "Chưa có charge" (orange) next to student name
- Info tooltip: "Hệ thống sẽ tự tạo charge khi push DNG"
- These students are selectable — on push, backend auto-creates charge first

**Expandable row for HL with pending registration:**
```
└─ ⚠ Retake: COS10007 - Computing Essentials (approved, chưa có charge)
   Fee: 7,500,000 đ → charge sẽ tự tạo khi push DNG
```

## Acceptance Criteria

- [ ] Fee_type selector drives the entire page data
- [ ] Student table shows correct balance per student
- [ ] Expandable rows show individual charge breakdown
- [ ] Batch push creates DNG with charge linking
- [ ] Active DNG status displayed per student
- [ ] Warning shown when overwriting existing DNG
- [ ] Amount editable per student
- [ ] HL fee_type: shows students with approved registrations needing charge creation
- [ ] HL fee_type: indicator differentiates "has charge" vs "needs charge creation"
- [ ] Type-check passes
