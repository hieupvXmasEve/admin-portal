# BusinessActionLogger Usage Guide

The `BusinessActionLogger` provides centralized logging for complex business operations that go beyond simple CRUD operations on individual models.

## Quick Start

### Basic Usage

```php
use App\Support\BusinessActionLogger;

// Simple business action logging
BusinessActionLogger::for('grade_entry', 'Processed grade entry')
    ->on($assessmentScore)
    ->withProperties([
        'old_score' => $oldScore,
        'new_score' => $newScore,
    ])
    ->log();
```

### Using Execute Method

The `execute()` method provides transaction handling, performance metrics, and automatic error logging:

```php
use App\Support\BusinessActionLogger;

$result = BusinessActionLogger::gradeEntry($score, 'update')
    ->withProperties([
        'old_score' => $oldScore,
        'new_score' => $newScore,
    ])
    ->execute(function () use ($score, $newScore) {
        // Your business logic here
        $score->update(['achieved_score' => $newScore]);
        
        // Recalculate related data
        $this->recalculateCourseGrade($score);
        
        return [
            'updated' => true,
            'new_score' => $newScore,
        ];
    });
```

## Predefined Business Actions

### Grade Entry

```php
// Single grade entry
BusinessActionLogger::gradeEntry($assessmentScore, 'update')
    ->withProperties([
        'old_score' => $oldScore,
        'new_score' => $newScore,
        'score_change' => $newScore - $oldScore,
    ])
    ->log();

// Bulk grade entry with batch logging
LogBatch::withinBatch(function () use ($scores) {
    BusinessActionLogger::bulkGradeEntry($scores, 'update')
        ->withProperties(['total_updates' => count($scores)])
        ->log();
        
    // Individual grade entries within the batch
    foreach ($scores as $score) {
        BusinessActionLogger::gradeEntry($score, 'update')
            ->withProperties(['bulk_operation' => true])
            ->log();
    }
});
```

### Course Withdrawal

```php
BusinessActionLogger::courseWithdrawal($registration, 'Academic difficulties')
    ->withProperties([
        'refund_amount' => $refundAmount,
        'withdrawal_date' => now()->toDateString(),
    ])
    ->execute(function () use ($registration, $refundAmount) {
        // Update registration status
        $registration->update(['status' => 'withdrawn']);
        
        // Process refund if applicable
        if ($refundAmount > 0) {
            $this->processRefund($registration, $refundAmount);
        }
        
        return ['withdrawn' => true, 'refund' => $refundAmount];
    });
```

### GPA Calculation

```php
BusinessActionLogger::gpaCalculation($student, 'semester')
    ->withProperties([
        'semester_id' => $semesterId,
        'trigger' => 'grade_finalization',
    ])
    ->execute(function () use ($student, $semesterId) {
        $gpa = $this->calculateGPA($student, $semesterId);
        
        // Update academic standing if needed
        $this->updateAcademicStanding($student, $gpa);
        
        return ['gpa' => $gpa];
    });
```

### Graduation Evaluation

```php
BusinessActionLogger::graduationEvaluation($student)
    ->withProperties([
        'evaluation_type' => 'full_audit',
        'program_id' => $student->program_id,
    ])
    ->execute(function () use ($student) {
        $requirements = $this->evaluateGraduationRequirements($student);
        
        if ($requirements['eligible']) {
            $student->update(['graduation_status' => 'eligible']);
        }
        
        return $requirements;
    });
```

### Academic Standing Changes

```php
BusinessActionLogger::academicStanding($student, $oldStatus, $newStatus)
    ->withProperties([
        'trigger' => 'gpa_calculation',
        'gpa' => $currentGPA,
        'required_gpa' => $requiredGPA,
    ])
    ->log();
```

## Advanced Features

### Batch Operations

```php
use Spatie\Activitylog\Facades\LogBatch;

LogBatch::withinBatch(function () {
    // All activities within this closure will share the same batch UUID
    
    BusinessActionLogger::for('bulk_enrollment', 'Processed bulk enrollment')
        ->withProperties(['student_count' => count($students)])
        ->log();
        
    foreach ($students as $student) {
        BusinessActionLogger::enrollment('create', $student)
            ->withProperties(['bulk_operation' => true])
            ->log();
    }
});
```

### Custom Business Actions

```php
BusinessActionLogger::for('custom_operation', 'My custom business operation')
    ->on($primaryModel)
    ->by($customUser)  // Override the causer
    ->affecting([$model1, $model2])  // Track affected models
    ->logName('custom_log_name')  // Override log name
    ->event('custom_event')  // Set custom event name
    ->withProperties([
        'operation_type' => 'bulk_update',
        'affected_count' => 100,
    ])
    ->execute(function () {
        // Your custom business logic
        return ['success' => true];
    });
```

### Performance Monitoring

The `execute()` method automatically tracks:
- Execution time
- Memory usage
- Success/failure status
- Error details (on failure)

```php
$result = BusinessActionLogger::for('expensive_operation', 'Heavy computation')
    ->execute(function () {
        // Long running operation
        sleep(5);
        return ['computed' => 'result'];
    });

// The activity log will include:
// - execution_time_ms: 5000.00
// - memory_usage_mb: 2.5
// - status: 'success'
// - result_summary: {type: 'array', count: 1, keys: ['computed']}
```

### Error Handling

```php
try {
    $result = BusinessActionLogger::for('risky_operation', 'Operation that might fail')
        ->execute(
            function () {
                // Business logic that might throw exception
                throw new Exception('Something went wrong');
            },
            function ($result) {
                // Success callback
                Log::info('Operation succeeded', $result);
            },
            function ($exception) {
                // Failure callback
                Log::error('Operation failed', ['error' => $exception->getMessage()]);
            }
        );
} catch (Exception $e) {
    // Exception is re-thrown after logging
}
```

## Querying Business Activities

### Get Activities by Action Type

```php
use App\Support\BusinessActionLogger;

// Get all grade entry activities
$gradeActivities = BusinessActionLogger::getActivitiesForAction('grade_entry');

// Get grade activities for specific student
$studentGrades = BusinessActionLogger::getActivitiesForAction('grade_entry', $student);
```

### Get Recent Business Activities

```php
// Get recent activities of all types
$recentActivities = BusinessActionLogger::getRecentActivities(100);

// Get recent GPA calculations only
$recentGPA = BusinessActionLogger::getRecentActivities(50, 'gpa_calculation');
```

### Get Activities by Batch

```php
// Get all activities from a specific batch operation
$batchActivities = BusinessActionLogger::getActivitiesByBatch($batchUuid);
```

## Integration with Existing Code

### In Controllers

```php
public function updateGrade(Request $request, AssessmentComponentDetailScore $score)
{
    $oldScore = $score->achieved_score;
    $newScore = $request->input('score');
    
    return BusinessActionLogger::gradeEntry($score, 'update')
        ->withProperties([
            'old_score' => $oldScore,
            'new_score' => $newScore,
            'updated_by' => auth()->user()->name,
        ])
        ->execute(function () use ($score, $request) {
            $score->update($request->validated());
            
            // Trigger grade recalculation
            event(new GradeUpdated($score));
            
            return $score->fresh();
        });
}
```

### In Services

```php
class GradeCalculationService
{
    public function calculateFinalGrade(int $studentId, int $courseOfferingId): array
    {
        $student = Student::findOrFail($studentId);
        $courseOffering = CourseOffering::findOrFail($courseOfferingId);
        
        return BusinessActionLogger::for('final_grade_calculation', 'Calculated final course grade')
            ->on($student)
            ->affecting($courseOffering)
            ->withProperties([
                'course_offering_id' => $courseOfferingId,
                'calculation_method' => 'weighted_average',
            ])
            ->execute(function () use ($student, $courseOffering) {
                // Complex grade calculation logic
                $finalGrade = $this->performGradeCalculation($student, $courseOffering);
                
                // Update academic record
                $this->updateAcademicRecord($student, $courseOffering, $finalGrade);
                
                return $finalGrade;
            });
    }
}
```

## Best Practices

### 1. Use Descriptive Action Types
```php
// Good
BusinessActionLogger::for('graduation_eligibility_check', 'Evaluated graduation eligibility');

// Less good
BusinessActionLogger::for('check', 'Did something');
```

### 2. Include Relevant Context
```php
BusinessActionLogger::gradeEntry($score, 'update')
    ->withProperties([
        'old_score' => $oldScore,
        'new_score' => $newScore,
        'score_change' => $newScore - $oldScore,
        'grading_rubric_used' => $rubricId,
        'grading_method' => 'manual',
    ])
    ->log();
```

### 3. Use Execute for Complex Operations
```php
// Preferred for multi-step operations
BusinessActionLogger::courseWithdrawal($registration, $reason)
    ->execute(function () use ($registration, $reason) {
        // Multiple related operations with transaction safety
    });

// Simple logging for single operations
BusinessActionLogger::gradeEntry($score, 'update')->log();
```

### 4. Track Affected Models
```php
BusinessActionLogger::programChange($student, $oldProgram, $newProgram)
    ->affecting([$oldProgram, $newProgram])
    ->withProperties([
        'change_reason' => $reason,
        'credits_transferred' => $transferredCredits,
    ])
    ->log();
```

## Campus-Aware Logging

All business action logs are automatically campus-aware:

```php
// Automatically includes campus context
BusinessActionLogger::gradeEntry($score, 'update')->log();

// Results in log_name like: "business_grade_entry_campus_1"
// And includes full campus context in properties
```

The logger automatically:
- Determines campus from the subject model or current session
- Sets appropriate log names with campus context
- Includes comprehensive campus information in properties
- Integrates with existing campus-aware logging system

This ensures all business action logs are properly categorized and filtered by campus for multi-tenant scenarios.
