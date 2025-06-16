# Semester Activation System

## Tổng quan

Hệ thống quản lý activation của semester đã được triển khai với các business rules nghiêm ngặt để đảm bảo chỉ có duy nhất 1 semester active tại một thời điểm và tuân thủ các quy tắc kích hoạt.

## Business Rules

### 1. Unique Active Semester
- **Chỉ có duy nhất 1 semester được phép active tại một thời điểm**
- Khi activate một semester, tất cả semester khác sẽ tự động được deactivate

### 2. Activation Requirements
- **Chỉ được active trước ngày `start_date`**: Không thể kích hoạt semester đã bắt đầu
- **Chỉ được active semester có `start_date` gần nhất**: Không thể "nhảy" để active semester ở tương lai xa
- **Không thể active semester đã archived**: Semester archived không thể được kích hoạt lại

### 3. Auto-deactivation
- **Semester tự động deactivate sau `end_date`**: Hệ thống tự động deactivate các semester đã hết hạn
- **Chạy daily schedule**: Auto-deactivation chạy hàng ngày lúc 00:01

### 4. Status Change Restrictions
- **Không thể thay đổi active status trong thời gian học**: Không thể activate/deactivate khi thời gian hiện tại nằm giữa `start_date` và `end_date`
- **Cho phép thay đổi trước khi bắt đầu**: Có thể thay đổi active status trước `start_date`
- **Cho phép thay đổi sau khi kết thúc**: Có thể thay đổi active status sau `end_date`

## Implementation

### Model Methods

#### `Semester` Model - Activation Logic
```php
// Check if semester can be activated
public function canBeActivated(): bool

// Activate semester and deactivate others
public function activate(): bool

// Deactivate semester
public function deactivate(): bool

// Check if should be auto-deactivated
public function shouldBeDeactivated(): bool

// Check if can change active status (during semester period)
public function canChangeActiveStatus(): bool

// Get validation error message
public function getActivationError(): ?string

// Get active status change validation error
public function getActiveStatusChangeError(): ?string

// Static methods
public static function getActiveSemester(): ?self
public static function getNextActiveSemester(): ?self
public static function deactivateExpiredSemesters(): int
```

### Controller Methods

#### `SemesterController` - API Endpoints
```php
// POST /semesters/{semester}/activate
public function activate(Semester $semester, SemesterManagementService $service): JsonResponse

// POST /semesters/{semester}/deactivate  
public function deactivate(Semester $semester, SemesterManagementService $service): JsonResponse

// GET /semesters/activation-statuses
public function activationStatuses(SemesterManagementService $service): JsonResponse
```

### Service Methods

#### `SemesterManagementService` - Business Logic
```php
// Activate with validation
public function activateSemester(Semester $semester): array

// Deactivate semester
public function deactivateSemester(Semester $semester): array

// Get next activatable semester
public function getNextActivatableSemester(): ?Semester

// Get activation statuses for all semesters
public function getSemesterActivationStatuses(): array
```

### Console Command

#### `semester:deactivate-expired`
```bash
# Check and deactivate expired semesters
php artisan semester:deactivate-expired

# Dry run to see what would be deactivated
php artisan semester:deactivate-expired --dry-run
```

**Schedule**: Chạy tự động hàng ngày lúc 00:01 qua Laravel Scheduler

### Routes

```php
// Activation API routes (require edit_semester permission)
Route::post('semesters/{semester}/activate', [SemesterController::class, 'activate'])
    ->middleware('can:edit_semester');
    
Route::post('semesters/{semester}/deactivate', [SemesterController::class, 'deactivate'])
    ->middleware('can:edit_semester');
    
Route::get('semesters/activation-statuses', [SemesterController::class, 'activationStatuses'])
    ->middleware('can:view_semester');
```

## Usage Examples

### Backend Usage

```php
// Check if semester can be activated
if ($semester->canBeActivated()) {
    $semester->activate();
} else {
    $error = $semester->getActivationError();
    // Handle error
}

// Get currently active semester
$activeSemester = Semester::getActiveSemester();

// Get next semester that can be activated
$nextSemester = Semester::getNextActiveSemester();

// Auto-deactivate expired semesters
$deactivatedCount = Semester::deactivateExpiredSemesters();
```

### API Usage

```javascript
// Activate a semester
const response = await fetch('/semesters/1/activate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    }
});

const result = await response.json();
if (result.success) {
    console.log('Semester activated successfully');
} else {
    console.error('Activation failed:', result.message);
}

// Get activation statuses
const statusResponse = await fetch('/semesters/activation-statuses');
const statuses = await statusResponse.json();
```

## Error Messages

### Activation Errors
- `"Cannot activate an archived semester."`
- `"Cannot activate a semester that has already started."`
- `"No upcoming semesters available for activation."`
- `"Only the next semester ({semester_name}) can be activated."`

### Status Change Errors
- `"Cannot change active status during the semester period."`

### Status Types
- `active`: Semester hiện đang active
- `next`: Semester tiếp theo có thể được activate
- `future`: Semester trong tương lai (chưa thể activate)
- `started`: Semester đã bắt đầu (không thể activate)
- `archived`: Semester đã archived
- `inactive`: Semester không active

## Testing

Hệ thống đã được test đầy đủ với `SemesterActivationTest`:

- ✅ Chỉ 1 semester active tại một thời điểm
- ✅ Chỉ activate semester có start_date gần nhất
- ✅ Không activate semester đã bắt đầu
- ✅ Không activate semester archived
- ✅ Auto-deactivation sau end_date
- ✅ Không thể thay đổi active status trong thời gian học
- ✅ Cho phép thay đổi active status trước/sau thời gian học
- ✅ API endpoints hoạt động đúng
- ✅ Console command hoạt động đúng

## Monitoring

### Schedule Monitoring
```bash
# Check if scheduler is running
php artisan schedule:list

# Run scheduler manually for testing
php artisan schedule:run

# Check specific command
php artisan semester:deactivate-expired --dry-run
```

### Logs
- Activation/deactivation events được log trong Laravel logs
- Command execution được log với thông tin chi tiết
- API calls được log với user và timestamp

## Security

- **Permissions**: Activation requires `edit_semester` permission
- **Transaction safety**: Activation/deactivation operations sử dụng database transactions
- **Validation**: Đầy đủ validation trước khi thực hiện operations
- **Audit trail**: Tất cả operations được log để audit 
