# Phase 2: Backend — Controller + Routes

## Status: pending

## Tasks

### 2.1 Create `DngWorklistController`

**File:** `app/Modules/Finance/Http/Web/Admin/DngWorklistController.php`

**Methods:**

```php
class DngWorklistController extends Controller
{
    public function __construct(
        private ListDngWorklistQuery $worklistQuery,
        private CreateBatchDngFromChargesAction $createBatchAction,
    ) {}

    /**
     * Render the unified DNG worklist page.
     * Fee type is a required query param.
     */
    public function index(Request $request): Response
    {
        // Validate request params
        // Default fee_type = 'HP' if not provided
        // Call ListDngWorklistQuery
        // Return Inertia::render('Finance/Operations/DngWorklist', [...])
        // Include: students, filters, summary, feeTypeOptions, semesters, campuses
    }

    /**
     * Create batch DNG from charges for selected students.
     */
    public function store(StoreBatchDngFromChargesRequest $request): RedirectResponse
    {
        $result = $this->createBatchAction->handle($request->validated());

        $msg = "Đã tạo {$result['created']} DNG thành công.";
        if ($result['cancelled_old'] > 0) {
            $msg .= " Đã hủy {$result['cancelled_old']} DNG cũ.";
        }
        if ($result['failed'] > 0) {
            $msg .= " {$result['failed']} thất bại.";
        }

        Inertia::flash($result['failed'] > 0 ? 'warning' : 'success', $msg);

        return back();
    }
}
```

### 2.2 Add Routes

**File:** `app/Modules/Finance/routes/web.php` — inside operations group

```php
// Unified DNG Worklist (replaces BatchDng + RetakeCourse DNG + Settlement DNG)
Route::get('/dng-worklist', [DngWorklistController::class, 'index'])
    ->middleware('can:create_finance_payments')
    ->name('dng-worklist');
Route::post('/dng-worklist', [DngWorklistController::class, 'store'])
    ->middleware('can:create_finance_payments')
    ->name('dng-worklist.store');
```

### 2.3 Update Navigation Menu

- Replace "Tạo DNG hàng loạt" link → "DNG Worklist" pointing to new route
- Remove RetakeCourse charge page from Finance menu (if listed)
- Settlement page "Tạo DNG hàng loạt" button → link to new page

**File:** `resources/js/constants/menu-sidebar.ts` (or wherever menu is defined)

## Acceptance Criteria

- [ ] GET `/finance/operations/dng-worklist` renders page with fee_type filter
- [ ] GET `/finance/operations/dng-worklist?dng_fee_type=HL` shows retake charges
- [ ] POST `/finance/operations/dng-worklist` creates DNG batch from charges
- [ ] Routes protected by `can:create_finance_payments` permission
- [ ] Menu updated
