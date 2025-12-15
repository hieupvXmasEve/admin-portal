# Academic Record Generation - Test Suite

## 📋 Overview

Comprehensive test suite for Academic Record Generation Service với 3 test files covering:
- Unit tests cho service logic
- Feature tests cho command integration  
- Edge cases và error scenarios

---

## 📂 Test Structure

```
tests/
├── Unit/Services/
│   ├── AcademicRecordGenerationServiceOptimizedTest.php  (14 tests)
│   └── AcademicRecordGenerationEdgeCasesTest.php          (16 tests)
└── Feature/Console/
    └── GenerateAcademicRecordsCommandTest.php             (13 tests)

Total: 43 tests
```

---

## 🧪 Test Coverage

### 1. Unit Tests - Service Logic (14 tests)

**File**: `tests/Unit/Services/AcademicRecordGenerationServiceOptimizedTest.php`

| Test | Description | Coverage |
|------|-------------|----------|
| `test_generates_academic_records_successfully` | Basic generation flow | Happy path |
| `test_calculates_attendance_percentage_correctly` | Attendance calculation (80%) | Business logic |
| `test_student_below_80_percent_fails_attendance_requirement` | Failed requirement (60%) | Validation |
| `test_creates_assessment_scores_for_attendance_components` | Assessment score creation | Integration |
| `test_handles_large_dataset_with_chunking` | 150 students (> chunk size) | Performance |
| `test_skips_existing_academic_records` | Idempotency check | Data integrity |
| `test_updates_existing_records_correctly` | Update flow | CRUD |
| `test_handles_students_with_no_attendance` | No data scenario | Edge case |
| `test_handles_course_without_syllabus_template` | Missing syllabus | Error handling |
| `test_progress_callback_is_called` | Progress tracking | UX |
| `test_handles_transaction_errors_gracefully` | Database errors | Error recovery |
| `test_bulk_upsert_updates_existing_scores` | Bulk update mechanism | Performance |

### 2. Edge Cases Tests (16 tests)

**File**: `tests/Unit/Services/AcademicRecordGenerationEdgeCasesTest.php`

| Test | Description | Scenario |
|------|-------------|----------|
| `test_handles_course_with_zero_sessions` | No sessions | Empty data |
| `test_handles_mixed_attendance_statuses` | present/late/excused/absent | Mixed status |
| `test_exactly_80_percent_attendance` | Boundary: 80.00% | Boundary |
| `test_just_below_80_percent_attendance` | Boundary: 79.90% | Boundary |
| `test_perfect_attendance` | 100% attendance | Maximum |
| `test_zero_attendance` | 0% attendance | Minimum |
| `test_student_in_multiple_courses` | 1 student, 3 courses | Multiple records |
| `test_multiple_attendance_components` | 2 attendance components | Complex syllabus |
| `test_soft_deleted_attendances_not_counted` | Deleted records | Soft delete |
| `test_soft_deleted_sessions_not_counted` | Deleted sessions | Soft delete |
| `test_decimal_precision_in_percentage` | 81.8181...% | Precision |
| `test_component_without_details_auto_creates` | Auto-create detail | Auto-fix |
| `test_concurrent_execution_idempotency` | Double run | Concurrency |

### 3. Feature Tests - Command Integration (13 tests)

**File**: `tests/Feature/Console/GenerateAcademicRecordsCommandTest.php`

| Test | Description | Command Flag |
|------|-------------|--------------|
| `test_command_runs_successfully_with_original_service` | Default command | (none) |
| `test_command_runs_successfully_with_optimized_service` | Optimized version | `--optimized` |
| `test_dry_run_does_not_create_records` | Test mode | `--dry-run` |
| `test_update_existing_records_flag_works` | Update flow | `--update-existing` |
| `test_update_existing_with_optimized_flag` | Update + optimized | `--update-existing --optimized` |
| `test_command_shows_memory_usage_stats` | Memory tracking | (output check) |
| `test_command_handles_no_attendance_data` | Empty data | Error handling |
| `test_command_shows_statistics_table` | Output format | UI |
| `test_optimized_command_handles_large_dataset` | 150 students | Performance |
| `test_dry_run_with_optimized_flag` | Combined flags | `--optimized --dry-run` |
| `test_command_handles_database_errors_gracefully` | Error recovery | Error handling |
| `test_multiple_runs_are_idempotent` | Idempotency | Data integrity |
| `test_command_execution_time_displayed` | Execution time | Monitoring |

---

## 🚀 Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test File
```bash
# Unit tests - Service logic
php artisan test tests/Unit/Services/AcademicRecordGenerationServiceOptimizedTest.php

# Edge cases
php artisan test tests/Unit/Services/AcademicRecordGenerationEdgeCasesTest.php

# Feature tests - Command
php artisan test tests/Feature/Console/GenerateAcademicRecordsCommandTest.php
```

### Run Specific Test Method
```bash
php artisan test --filter test_generates_academic_records_successfully
```

### Run with Coverage (requires Xdebug)
```bash
php artisan test --coverage
```

### Run with Parallel Execution (faster)
```bash
php artisan test --parallel
```

---

## 📊 Test Scenarios Covered

### ✅ Happy Path
- [x] Generate records successfully (small dataset)
- [x] Generate records with optimization (large dataset)
- [x] Update existing records
- [x] Calculate attendance correctly
- [x] Create assessment scores

### ⚠️ Edge Cases
- [x] 0% attendance (all absent)
- [x] 100% attendance (perfect)
- [x] Exactly 80% attendance (boundary)
- [x] 79.9% attendance (boundary)
- [x] Mixed attendance statuses (present/late/excused/absent)
- [x] Zero sessions in course
- [x] Student in multiple courses
- [x] Multiple attendance components
- [x] Decimal precision in percentages

### 🔧 Error Scenarios
- [x] No attendance data
- [x] Missing syllabus template
- [x] Invalid student data
- [x] Database transaction errors
- [x] Soft-deleted records
- [x] Concurrent execution

### 📈 Performance Tests
- [x] Large dataset (150 students)
- [x] Memory usage < 500MB
- [x] Chunking mechanism
- [x] Bulk operations
- [x] Query optimization

### 🎯 Integration Tests
- [x] Command execution
- [x] Progress bar display
- [x] Memory stats display
- [x] Dry run mode
- [x] Flag combinations
- [x] Idempotency

---

## 🧮 Test Data Patterns

### Small Dataset (for quick tests)
```php
students: 5
sessionsPerCourse: 10
totalAttendances: 50
executionTime: <1 second
```

### Medium Dataset (for standard tests)
```php
students: 50
sessionsPerCourse: 20
totalAttendances: 1,000
executionTime: 1-3 seconds
```

### Large Dataset (for performance tests)
```php
students: 150
sessionsPerCourse: 50
totalAttendances: 7,500
executionTime: 5-10 seconds
memoryUsage: <500MB
```

---

## 🔍 Assertions Used

### Database Assertions
```php
$this->assertDatabaseCount('academic_records', 5);
$this->assertDatabaseHas('academic_records', ['student_id' => 1]);
```

### Model Assertions
```php
$this->assertEquals(80.0, $record->attendance_percentage);
$this->assertTrue($record->meets_attendance_requirement);
$this->assertNotNull($score);
```

### Command Assertions
```php
$this->artisan('command')->expectsOutput('message')->assertExitCode(0);
$this->artisan('command')->expectsTable([...]);
```

### Performance Assertions
```php
$this->assertLessThan(500, $memoryUsed); // MB
$this->assertGreaterThan(0, $stats['created']);
```

---

## 🎯 Business Rules Tested

### Attendance Calculation
- ✅ Present = counted
- ✅ Late = counted
- ✅ Excused = counted
- ✅ Absent = not counted
- ✅ Percentage = (present_count / total_sessions) × 100
- ✅ Requirement = >= 80%

### Assessment Scoring
- ✅ Type must be 'attendance'
- ✅ Score = attendance percentage
- ✅ Status = 'graded' + 'final'
- ✅ Feedback includes Vietnamese text
- ✅ Auto-creates detail if missing

### Data Integrity
- ✅ Idempotent (safe to run multiple times)
- ✅ Skips existing records
- ✅ Updates on --update-existing
- ✅ Respects soft deletes
- ✅ Handles transaction errors

---

## 🐛 Known Issues / Limitations

### Test Environment
- ⚠️ Requires database connection
- ⚠️ Uses `RefreshDatabase` (slow on large migrations)
- ⚠️ Some tests may be slow with large datasets

### Coverage Gaps
- ⚠️ Mocking external dependencies not tested
- ⚠️ Real database timeout scenarios hard to simulate
- ⚠️ Actual concurrent writes not tested (race conditions)

---

## 📝 Adding New Tests

### Template for Unit Test
```php
public function test_your_scenario(): void
{
    // Arrange: Set up test data
    $testData = $this->createTestData(students: 5, sessionsPerCourse: 10);
    
    // Act: Execute the code
    $stats = $this->service->generateAcademicRecords();
    
    // Assert: Verify results
    $this->assertEquals(5, $stats['created']);
    $this->assertDatabaseCount('academic_records', 5);
}
```

### Template for Command Test
```php
public function test_command_scenario(): void
{
    // Arrange
    $this->createTestData(students: 3, sessionsPerCourse: 5);
    
    // Act & Assert
    $this->artisan('academic-records:generate --your-flag')
        ->expectsOutput('Expected message')
        ->assertExitCode(0);
}
```

---

## 🔄 Continuous Integration

### GitHub Actions (example)
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
      - name: Install Dependencies
        run: composer install
      - name: Run Tests
        run: php artisan test --parallel
```

---

## 📞 Troubleshooting

### Tests Failing?

**Check Database Connection**
```bash
php artisan migrate:fresh
```

**Clear Cache**
```bash
php artisan config:clear
php artisan cache:clear
```

**Check PHP Memory Limit**
```bash
php -i | grep memory_limit
# Should be >= 512M
```

**Run Single Test for Debugging**
```bash
php artisan test --filter test_name --stop-on-failure
```

---

## 📚 References

- [Laravel Testing Docs](https://laravel.com/docs/testing)
- [Pest PHP Docs](https://pestphp.com/)
- [PHPUnit Manual](https://phpunit.de/manual/current/en/index.html)

---

**Test Suite Version**: 1.0  
**Last Updated**: 2025-01-17  
**Total Tests**: 43  
**Expected Runtime**: ~30-60 seconds (all tests)
