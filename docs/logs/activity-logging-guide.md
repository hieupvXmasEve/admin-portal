# Activity Logging System Documentation

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Quick Start Guide](#quick-start-guide)
4. [Campus-Aware Logging](#campus-aware-logging)
5. [Model Implementation](#model-implementation)
6. [Viewing Activity Logs](#viewing-activity-logs)
7. [Advanced Configuration](#advanced-configuration)
8. [Troubleshooting](#troubleshooting)
9. [Best Practices](#best-practices)

## Overview

The Activity Logging System provides comprehensive audit trails for all database changes in the academic management system. Built on top of [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog/), it offers:

- **Campus-aware logging**: All activities are automatically categorized by campus
- **Three logging levels**: Minimal, Standard, and Comprehensive
- **Enhanced context**: Rich metadata including user information, IP addresses, and academic context
- **User-friendly interface**: Vue.js-based activity log viewer with filtering and search

### Key Features

- ✅ **Automatic campus tagging** for multi-tenant isolation
- ✅ **Comprehensive change tracking** with old/new value comparison
- ✅ **Role-based access control** for viewing activity logs
- ✅ **Real-time activity monitoring** with advanced filtering
- ✅ **Security-focused logging** for sensitive operations
- ✅ **Performance optimized** with efficient database queries

---

## Architecture

### Core Components

```
├── app/Support/CampusLogContext.php          # Campus context helper
├── app/Models/AuditableModel.php             # Base auditable model
├── app/Models/UserAuditableModel.php         # User-specific auditable base
├── app/Models/StudentAuditableModel.php      # Student-specific auditable base
├── app/Http/Controllers/Web/ActivityLogController.php  # Log viewer controller
└── resources/js/pages/systems/ActivityLogs.vue        # Frontend interface
```

### Database Schema

The system uses three main tables:
- `activity_log`: Main activity storage (provided by spatie/laravel-activitylog)
- `campuses`: Campus information for categorization
- Standard model tables with soft deletes support

---

## Quick Start Guide

### For New Team Members

#### 1. Understanding the Logging Levels

**Minimal Logging** (`LOG_LEVEL_MINIMAL`)
- Only logs critical status changes
- Best for: High-frequency models with minimal audit requirements
```php
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_MINIMAL;
}
```

**Standard Logging** (`LOG_LEVEL_STANDARD`) - Default
- Logs key business fields and status changes  
- Best for: Most business models
```php
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_STANDARD;
}
```

**Comprehensive Logging** (`LOG_LEVEL_COMPREHENSIVE`)
- Logs all fillable fields (excluding sensitive data)
- Best for: Security-critical models, student records, financial data
```php
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_COMPREHENSIVE;
}
```

#### 2. How Activities Are Generated

Activities are **automatically created** when:
- Models are created (`created` event)
- Models are updated (`updated` event) 
- Models are deleted (`deleted` event)
- Models are restored (`restored` event)

**No manual intervention required** - just use your models normally:

```php
// This automatically creates an activity log
$user = User::find(1);
$user->update(['name' => 'New Name']);

// This also creates an activity log
$program = Program::create(['name' => 'New Program', 'code' => 'NP001']);
```

#### 3. Viewing Activity Logs

**For Regular Users:**
- Navigate to: **System → Activity Logs**
- See only activities from your accessible campuses
- Use search and filters to find specific activities

**For System Administrators:**
- Navigate to: **System → Activity Logs**
- See all activities across all campuses
- Use campus filter to focus on specific campuses
- Access to enhanced activity details

---

## Campus-Aware Logging

### Automatic Campus Detection

The system automatically determines campus context from:

1. **Session Campus ID** (most common)
   ```php
   session('current_campus_id') // → 1
   ```

2. **Model's Campus Field**
   ```php
   $student->campus_id // → 2
   ```

3. **User's Campus Context**
   ```php
   CampusLogContext::getCurrentCampusId() // → 3
   ```

### Log Naming Convention

All activities use campus-aware naming:

- ✅ `user_campus_1` - User activity at Campus ID 1
- ✅ `student_campus_2` - Student activity at Campus ID 2  
- ✅ `program_system` - System-wide program activity
- ❌ `default` - Old naming (should not appear in new logs)

### Campus Context Properties

Every activity includes rich campus information:

```json
{
  "campus_id": 1,
  "campus_code": "HN",
  "campus_name": "Asia Hà Nội",
  "campus_address": "Số 1 Đường Trần Đặng Ninh...",
  "acting_user_campus": 1,
  "academic_year": "2025-2026",
  "local_timestamp": "2025-08-07T15:21:13.390698Z",
  "session_id": "abc123...",
  "ip_address": "127.0.0.1",
  "user_agent": "Mozilla/5.0...",
  "user_id": 5,
  "user_email": "admin@example.com",
  "user_name": "John Admin"
}
```

---

## Model Implementation

### Currently Implemented Models

#### High Priority Models (Comprehensive Logging)
- ✅ **Student** - Full academic record tracking
- ✅ **CourseRegistration** - Enrollment and grade changes
- ✅ **CourseOffering** - Course scheduling changes
- ✅ **Semester** - Academic calendar changes
- ✅ **StudentApplication** - Application workflow tracking

#### Security Models (Comprehensive Logging)
- ✅ **User** - Account changes
- ✅ **Role** - Role management
- ✅ **Permission** - Permission changes
- ✅ **CampusUserRole** - Role assignments (security-critical)
- ✅ **RolePermission** - Permission grants/revocations

#### Business Models (Standard Logging)
- ✅ **Program** - Academic program changes
- ✅ **Campus** - Infrastructure changes

### Adding Logging to New Models

#### For Regular Models

1. **Change parent class from `Model` to `AuditableModel`:**

```php
// Before
use Illuminate\Database\Eloquent\Model;
class MyModel extends Model { }

// After  
use App\Models\AuditableModel;
class MyModel extends AuditableModel { }
```

2. **Customize logging (optional):**

```php
class MyModel extends AuditableModel
{
    // Set logging level
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    // Customize logged fields
    protected function getStandardLogFields(): array
    {
        return ['name', 'code', 'status', 'important_field'];
    }

    // Exclude sensitive fields
    protected function getExcludedLogFields(): array
    {
        return ['password', 'secret_token', 'internal_notes'];
    }

    // Custom activity descriptions
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->name ?? "ID {$this->id}";
        
        return match ($eventName) {
            'created' => "Created new record: {$identifier}",
            'updated' => "Updated record: {$identifier}",
            'deleted' => "Deleted record: {$identifier}",
            default => "{$eventName} record: {$identifier}",
        };
    }

    // Add custom properties to logs
    protected function getCustomLogProperties(): array
    {
        return [
            'category' => $this->category,
            'priority' => $this->priority,
            'related_count' => $this->relatedItems()->count(),
        ];
    }
}
```

#### For User/Authentication Models

Use `UserAuditableModel` instead:

```php
use App\Models\UserAuditableModel;

class MyAuthModel extends UserAuditableModel
{
    // Your model implementation
}
```

#### For Student Models

Use `StudentAuditableModel` for student-related authentication models:

```php
use App\Models\StudentAuditableModel;

class MyStudentModel extends StudentAuditableModel
{
    // Your model implementation
}
```

### Example: Adding Logging to Building Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Building extends AuditableModel
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'campus_id'];

    // Use comprehensive logging for infrastructure
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    // Custom identifier
    protected function getIdentifierForLog(): string
    {
        return $this->name ?? $this->code ?? "Building ID {$this->id}";
    }

    // Custom activity descriptions
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();
        
        return match ($eventName) {
            'created' => "Added new building: {$identifier}",
            'updated' => "Modified building: {$identifier}",
            'deleted' => "Removed building: {$identifier}",
            default => "{$eventName} building: {$identifier}",
        };
    }

    // Add building-specific context
    protected function getCustomLogProperties(): array
    {
        return [
            'building_code' => $this->code,
            'campus_id' => $this->campus_id,
            'room_count' => $this->rooms()->count(),
        ];
    }
}
```

---

## Viewing Activity Logs

### Accessing the Activity Log Interface

**URL:** `/systems/activity-logs`
**Route:** `system.activity-logs.index`
**Menu:** System → Activity Logs

### User Interface Features

#### For Regular Users
- **Campus Context**: Shows current campus in header
- **Filtered View**: Only sees activities from accessible campuses
- **Basic Filters**: Search, subject type, event type

#### For System Administrators
- **Campus Selection**: Dropdown to filter by specific campus
- **Cross-Campus View**: Can see all activities or filter by campus
- **Enhanced Context**: Additional campus and user information

### Activity Table Columns

| Column | Description | Example |
|--------|-------------|---------|
| **ID** | Unique activity identifier | `#12345` |
| **Description** | Human-readable activity description | `Updated student: John Doe (ST001)` |
| **Event** | Type of database operation | `created`, `updated`, `deleted` |
| **Subject Type** | Model that was changed | `App\Models\Student` |
| **Subject** | Specific record that changed | `John Doe` |
| **Causer** | User who made the change | `Admin User` |
| **Campus** | Campus where activity occurred | `Asia Hà Nội (HN)` |
| **Created At** | When the activity happened | `2025-08-07 15:30:45` |

### Activity Details Modal

Click on any activity row to see comprehensive details:

#### Basic Information
- Activity ID, description, event type
- Subject and causer information
- Timestamps

#### Campus Context
- Campus name, code, address
- Academic year and local timestamp
- Acting user campus (if different)

#### Change Information
- List of changed fields as badges
- Side-by-side comparison of old vs new values
- Change count and operation type

#### Full Properties
- Complete JSON of all logged properties
- Technical details for debugging

### Search and Filtering

#### Search Functionality
The search box supports:
- Activity descriptions
- Log names
- Campus names
- User names
- Causer names

**Example searches:**
- `"John Doe"` - Find activities involving John Doe
- `"student"` - Find all student-related activities
- `"updated"` - Find all update operations
- `"campus 1"` - Find activities at Campus ID 1

#### Available Filters

1. **Subject Type Filter**
   - Filter by model type (e.g., `App\Models\User`)
   - Shows only available types based on your access

2. **Event Filter**
   - `created` - Record creation
   - `updated` - Record updates  
   - `deleted` - Record deletion
   - `restored` - Record restoration

3. **Campus Filter** (System Admins Only)
   - `All Campuses` - See activities from all campuses
   - Specific campus selection
   - Shows campus name and code

#### Clear Filters
- **Clear Button**: Appears when any filters are active
- **Reset All**: Clears search, filters, and pagination

---

## Advanced Configuration

### Campus Log Context Helper

The `CampusLogContext` helper provides centralized campus information management:

```php
use App\Support\CampusLogContext;

// Get comprehensive campus context
$context = CampusLogContext::getCampusContext();

// Get current campus ID
$campusId = CampusLogContext::getCurrentCampusId();

// Get campus-specific log name
$logName = CampusLogContext::getLogName('User', $campusId);

// Check if user is system admin
$isAdmin = CampusLogContext::isSystemAdmin();

// Get accessible campus IDs for current user
$campusIds = CampusLogContext::getAccessibleCampusIds();

// Enhance activity properties with campus context
$enhanced = CampusLogContext::enhanceLogProperties($existingProperties);
```

### Custom Activity Logging

For complex scenarios, you can manually log activities:

```php
use Spatie\Activitylog\Models\Activity;

// Manual activity logging
activity()
    ->performedOn($model)
    ->causedBy($user)
    ->withProperties([
        'custom_field' => 'custom_value',
        'additional_context' => 'important_info'
    ])
    ->useLogName(CampusLogContext::getLogName('CustomModel'))
    ->log('Custom activity description');

// Log without a subject model
activity()
    ->causedBy(auth()->user())
    ->withProperties(CampusLogContext::getCampusContext())
    ->useLogName('system_operation')
    ->log('System maintenance completed');
```

### Performance Optimization

#### Database Indexes

The system automatically optimizes queries using:
- Index on `log_name` for campus filtering
- Index on `created_at` for time-based queries
- Composite indexes for common filter combinations

#### Caching

Campus information is cached for performance:

```php
// Clear campus cache after campus changes
CampusLogContext::clearCache($campusId);

// Clear all campus cache
CampusLogContext::clearCache();
```

### Extending Base Models

You can extend the base auditable models for specific needs:

```php
// Custom auditable model with additional features
abstract class CustomAuditableModel extends AuditableModel
{
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getCustomLogProperties(): array
    {
        return array_merge(parent::getCustomLogProperties(), [
            'custom_field' => $this->calculateCustomField(),
            'environment' => app()->environment(),
        ]);
    }
    
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getCustomIdentifier();
        
        return match ($eventName) {
            'created' => "Created {$this->getModelType()}: {$identifier}",
            'updated' => "Updated {$this->getModelType()}: {$identifier}",
            'deleted' => "Deleted {$this->getModelType()}: {$identifier}",
            default => parent::getDescriptionForEvent($eventName),
        };
    }

    abstract protected function getModelType(): string;
    abstract protected function getCustomIdentifier(): string;
}
```

---

## Troubleshooting

### Common Issues

#### 1. Activities Not Being Logged

**Symptom:** No activities appear in the log after model changes

**Possible Causes & Solutions:**

- **Model doesn't extend AuditableModel**
  ```php
  // Wrong ❌
  class MyModel extends Model { }
  
  // Correct ✅
  class MyModel extends AuditableModel { }
  ```

- **Activity logging is disabled**
  ```php
  // Check if shouldSkipLogging returns true
  protected function shouldSkipLogging(): bool
  {
      return false; // Make sure this returns false
  }
  ```

- **No actual changes made**
  ```php
  // This won't log anything if name is already 'John'
  $user->update(['name' => 'John']);
  
  // Only dirty attributes are logged
  $user->name = 'Jane'; // This will log
  $user->save();
  ```

#### 2. Missing Campus Context

**Symptom:** Activities have `log_name` as "default" instead of campus-specific names

**Possible Causes & Solutions:**

- **No campus session set**
  ```php
  // Make sure campus is selected
  session(['current_campus_id' => 1]);
  ```

- **Model doesn't have campus_id and session is empty**
  ```php
  // Either set model's campus_id or session campus
  $model->campus_id = 1; // OR
  session(['current_campus_id' => 1]);
  ```

- **Using old Model class instead of AuditableModel**
  ```php
  // Update to use AuditableModel
  class MyModel extends AuditableModel { }
  ```

#### 3. Permission Issues

**Symptom:** Can't access activity logs or see limited activities

**Possible Causes & Solutions:**

- **Missing permissions**
  ```php
  // User needs 'view_system_log' permission
  $user->hasPermission('view_system_log'); // Should return true
  ```

- **Campus access restrictions**
  ```php
  // Check accessible campuses
  $campusIds = CampusLogContext::getAccessibleCampusIds();
  // Should include the campuses you expect to see
  ```

#### 4. Performance Issues

**Symptom:** Activity log page loads slowly

**Possible Solutions:**

- **Add database indexes**
  ```sql
  -- Add index on log_name for campus filtering
  ALTER TABLE activity_log ADD INDEX idx_log_name (log_name);
  
  -- Add composite index for common filters
  ALTER TABLE activity_log ADD INDEX idx_subject_event (subject_type, event);
  ```

- **Limit query results**
  ```php
  // Use pagination and filters to limit results
  // Default pagination is 15 items per page
  ```

- **Clear old logs periodically**
  ```php
  // Clean up old activity logs (run as scheduled task)
  Activity::where('created_at', '<', now()->subDays(365))->delete();
  ```

### Debugging Tools

#### Check Activity Log Contents

```php
// Get recent activities
$activities = \Spatie\Activitylog\Models\Activity::latest()->take(10)->get();

foreach ($activities as $activity) {
    echo "ID: {$activity->id}\n";
    echo "Log Name: {$activity->log_name}\n";
    echo "Description: {$activity->description}\n";
    echo "Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n";
    echo "---\n";
}
```

#### Test Campus Context

```php
// Test campus context helper
use App\Support\CampusLogContext;

session(['current_campus_id' => 1]);

$context = CampusLogContext::getCampusContext();
print_r($context);

$logName = CampusLogContext::getLogName('TestModel', 1);
echo "Log Name: {$logName}\n"; // Should be: testmodel_campus_1
```

#### Verify Model Implementation

```php
// Test model logging
$model = new MyModel(['name' => 'Test']);
$model->save(); // Should create activity

// Check if activity was created
$activity = \Spatie\Activitylog\Models\Activity::latest()->first();
echo "Latest activity: " . $activity->description . "\n";
```

---

## Best Practices

### Security Considerations

#### 1. Sensitive Data Handling

Always exclude sensitive fields from logging:

```php
protected function getExcludedLogFields(): array
{
    return [
        'password',           // Never log passwords
        'remember_token',     // Never log tokens
        'api_token',         // Never log API keys
        'oauth_provider_id', // Don't log OAuth IDs
        'ssn',               // Never log SSNs
        'credit_card',       // Never log financial data
        'internal_notes',    // Don't log internal comments
    ];
}
```

#### 2. Access Control

Implement proper access controls:

```php
// In your User model, implement access control methods
public function hasSystemRole(string $roleCode): bool
{
    return $this->campusRoles()->where('code', $roleCode)->exists();
}

public function getAccessibleCampuses()
{
    return $this->campuses()->select('id', 'name', 'code');
}
```

#### 3. Audit Trail Integrity

- **Never delete activity logs** in production
- **Archive old logs** instead of deleting them
- **Monitor for suspicious activity patterns**
- **Regularly backup activity logs**

### Performance Guidelines

#### 1. Logging Level Selection

Choose appropriate logging levels based on model importance:

```php
// High-frequency, low-importance models
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_MINIMAL; // Only critical changes
}

// Standard business models
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_STANDARD; // Key fields + status
}

// Critical/sensitive models
protected function getLoggingLevel(): string
{
    return static::LOG_LEVEL_COMPREHENSIVE; // All relevant fields
}
```

#### 2. Database Maintenance

Regularly maintain the activity log table:

```php
// Create scheduled task for log cleanup
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Archive logs older than 2 years
    $schedule->command('activitylog:clean --days=730')->monthly();
}
```

#### 3. Efficient Querying

Use appropriate indexes and query optimization:

```sql
-- Recommended indexes for activity_log table
CREATE INDEX idx_activity_log_name ON activity_log (log_name);
CREATE INDEX idx_activity_created_at ON activity_log (created_at);
CREATE INDEX idx_activity_subject ON activity_log (subject_type, subject_id);
CREATE INDEX idx_activity_causer ON activity_log (causer_type, causer_id);
```

### Development Workflow

#### 1. Adding New Models

When adding a new model that needs logging:

1. **Extend the appropriate base class**
2. **Define logging level** based on model importance
3. **Customize logged fields** if needed
4. **Test logging** with create/update/delete operations
5. **Verify campus context** is properly applied
6. **Update documentation** if needed

#### 2. Testing Activity Logs

Always test your logging implementation:

```php
// In your feature tests
public function test_model_creates_activity_log()
{
    $model = MyModel::create(['name' => 'Test']);
    
    $this->assertDatabaseHas('activity_log', [
        'subject_type' => MyModel::class,
        'subject_id' => $model->id,
        'event' => 'created',
    ]);
    
    $activity = Activity::latest()->first();
    $this->assertStringContains('campus_', $activity->log_name);
    $this->assertArrayHasKey('campus_id', $activity->properties);
}
```

#### 3. Code Review Checklist

When reviewing code that includes activity logging:

- ✅ Model extends appropriate auditable base class
- ✅ Sensitive fields are excluded from logging
- ✅ Logging level is appropriate for model importance
- ✅ Custom descriptions are meaningful and consistent
- ✅ Campus context is properly maintained
- ✅ Performance impact is considered
- ✅ Tests include activity log verification

### Maintenance and Monitoring

#### 1. Regular Monitoring

Monitor your activity logs for:

- **Unusual activity patterns** (mass deletions, bulk updates)
- **Performance degradation** (slow queries, large result sets)
- **Storage growth** (disk space usage)
- **Security issues** (unauthorized access attempts)

#### 2. Backup Strategy

- **Include activity logs** in regular database backups
- **Test restore procedures** including activity log data
- **Consider separate archival** for very old logs
- **Document retention policies** for compliance

#### 3. Documentation Updates

Keep documentation current:

- **Update this guide** when adding new features
- **Document custom implementations** for complex models
- **Maintain troubleshooting section** with common issues
- **Share knowledge** with team members

---

## Conclusion

The Activity Logging System provides comprehensive audit capabilities for the academic management system. By following this guide, team members can:

- ✅ **Understand** how the logging system works
- ✅ **Implement** logging for new models correctly
- ✅ **Use** the activity log interface effectively  
- ✅ **Troubleshoot** common issues independently
- ✅ **Maintain** the system for optimal performance

For questions or issues not covered in this guide, please consult the technical team or refer to the [spatie/laravel-activitylog documentation](https://spatie.be/docs/laravel-activitylog/).

---

*Last updated: August 7, 2025*
*Version: 1.0*