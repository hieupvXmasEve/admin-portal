# Phase 4: Settlement + Deprecated Pages Cleanup

## Status: pending

## Tasks

### 4.1 Remove DNG UI from Settlement.vue

**File:** `resources/js/pages/Finance/Operations/Settlement.vue`

**Remove:**
- DNG batch dialog (`dngBatchDialogOpen`, `dngBatchStudent`, `submitDngBatch`, etc.)
- "Tạo DNG" button per student row
- DNG-related state variables and functions
- `useApi` import (if only used for DNG)
- Fee type breakdown display in dialog
- Estimate time picker (only used for DNG)

**Keep:**
- Settlement worklist table
- Apply settlement batch action
- Summary cards
- Search/filter/pagination

### 4.2 Update Settlement Action Column

**Replace DNG button with link to new page:**
```vue
<!-- Old: Tạo DNG button + dialog -->
<!-- New: Link to DNG Worklist -->
<Link :href="route('finance.operations.dng-worklist', { search: row.original.student_code })">
    <Button variant="outline" size="sm">
        <Send class="mr-2 h-3.5 w-3.5" />
        Xem DNG
    </Button>
</Link>
```

### 4.3 Update "Tạo DNG hàng loạt" Button

**Change link target:**
```vue
<!-- Old -->
<Link :href="route('finance.operations.batch-dng')">
<!-- New -->
<Link :href="route('finance.operations.dng-worklist')">
```

### 4.4 Remove Unused Imports from Settlement.vue

After removing DNG dialog, clean up unused imports:
- `Dialog`, `DialogContent`, `DialogFooter`, etc. (if only used for DNG)
- `useApi`
- `CalendarIcon`, `Send`, etc.
- `DatePicker` (if only used in DNG dialog)
- `Popover` components (if only used for estimate time picker)

### 4.5 Remove `Payments/Create` Page

**Files to remove:**
- `resources/js/pages/Finance/Payments/Create.vue`

**Routes to remove** (in `app/Modules/Finance/routes/web.php`):
```php
// Remove:
Route::get('/create', [PaymentController::class, 'create'])->...->name('create');
Route::get('/{student}/dng-data', [PaymentController::class, 'getStudentDngData'])->...->name('student-dng-data');
```

**Controller cleanup** (`PaymentController.php`):
- Remove `create()` method
- Remove `getStudentDngData()` method
- Keep `index()` and other payment management methods

**API routes to remove** (in `app/Modules/Finance/routes/api.php`):
```php
// Remove or deprecate:
Route::post('/payment-requests', [DngPaymentController::class, 'store'])->name('payment-requests.store');
```

**Menu/nav:** Remove "Tạo DNG" link from payments section if any.

**Check for incoming links:**
- Search for `route('finance.payments.create')` usage across the codebase
- Search for `route('finance.payments.student-dng-data')` usage
- Update or remove any references

### 4.6 Remove `RetakeCourse/Index.vue` Page

**Files to remove:**
- `resources/js/pages/Finance/RetakeCourse/Index.vue`

**Routes to remove/update** (in `app/Modules/Finance/routes/web.php`):
```php
// Remove index route (replaced by DNG worklist with fee_type=HL):
Route::get('/', [RetakeCourseChargeController::class, 'index'])->...->name('index');

// Remove store route (absorbed by CreateBatchDngFromChargesAction):
Route::post('/charge', [RetakeCourseChargeController::class, 'store'])->...->name('charge.store');

// Remove storeSimple route (absorbed by CreateBatchDngFromChargesAction):
Route::post('/charge/simple', [RetakeCourseChargeController::class, 'storeSimple'])->...->name('charge.store-simple');
```

**Controller:** `RetakeCourseChargeController` can be fully removed after routes are gone.

**Check for incoming links:**
- Search for `route('finance.retake-course.index')` usage
- Search for `route('finance.retake-course.charge.store')` usage
- Search for `route('finance.retake-course.charge.store-simple')` usage

### 4.7 Remove `BatchDng.vue` Page

**Files to remove:**
- `resources/js/pages/Finance/Operations/BatchDng.vue`

**Routes to remove** (in `app/Modules/Finance/routes/web.php`):
```php
Route::get('/batch-dng', [BatchDngPageController::class, 'show'])->...->name('batch-dng');
```

**Controller:** `BatchDngPageController` fully removed.

**API route:**
```php
// Remove batch DNG API (replaced by charge-linked action):
// POST /api/v1/finance/dng/batch → BatchDngApiController::store
```

### 4.8 Update Navigation Menu

**File:** Find menu definition (likely `resources/js/constants/menu-sidebar.ts` or similar)

- Replace "Tạo DNG hàng loạt" → "DNG Worklist" pointing to `finance.operations.dng-worklist`
- Remove "Học lại - Tạo phí" entry under Finance
- Remove "Tạo DNG" entry under Payments (if exists)

## Acceptance Criteria

- [ ] Settlement page has no DNG creation UI
- [ ] "Tạo DNG hàng loạt" button links to new DNG Worklist page
- [ ] Apply settlement functionality unchanged
- [ ] `Payments/Create` page fully removed — no dangling route references
- [ ] `RetakeCourse/Index` page fully removed — no dangling route references
- [ ] `BatchDng` page fully removed — no dangling route references
- [ ] All deprecated controllers and API endpoints removed
- [ ] Navigation menu updated
- [ ] No unused imports across modified files
- [ ] Type-check passes
- [ ] No broken links (search for removed route names)
