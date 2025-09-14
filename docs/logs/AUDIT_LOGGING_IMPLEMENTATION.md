# Audit Logging Implementation for High Priority Models

## Overview
This document describes the comprehensive audit logging implementation for critical academic data models using the `AuditableModel` base class with Spatie Activity Log.

## Implementation Status

### ✅ High Priority Models (Completed)
All high priority models have been successfully configured with comprehensive audit logging:

1. **Enrollment** - Student progression tracking
2. **CurriculumVersion** - Version control for academic programs  
3. **Specialization** - Program structure changes
4. **Unit** - Core academic units
5. **Attendance** - Student attendance records
6. **GraduationApplication** - Graduation processing

## Base Architecture

### AuditableModel Base Class
Location: `app/Models/AuditableModel.php`

Key Features:
- Three logging levels: MINIMAL, STANDARD, COMPREHENSIVE
- Campus-aware logging context
- Automatic field exclusion for sensitive data
- Customizable activity descriptions
- Enhanced properties with contextual information

## Model-Specific Implementations

### 1. Enrollment Model
**Logging Level:** COMPREHENSIVE

**Key Tracked Changes:**
- Student enrollment creation/updates
- Status changes (in_progress, completed, withdrawn)
- Semester progression tracking
- Curriculum version assignments

**Special Features:**
- Tracks student academic year calculation
- Monitors semester number progression (advance/repeat)
- Links to student campus context
- Records program and specialization details

**Logged Fields:**
```php
- student_id
- semester_id  
- curriculum_version_id
- semester_number
- status
- notes
```

### 2. CurriculumVersion Model
**Logging Level:** COMPREHENSIVE

**Key Tracked Changes:**
- Curriculum version creation and updates
- Activation/deactivation with student impact analysis
- Version code changes
- Semester effectiveness changes

**Special Features:**
- Tracks affected student counts
- Monitors curriculum structure (units by year/type)
- Records total credit points
- Links to program and specialization

**Logged Fields:**
```php
- program_id
- specialization_id
- version_code
- semester_id
- notes
- is_active
```

### 3. Specialization Model
**Logging Level:** COMPREHENSIVE

**Key Tracked Changes:**
- Specialization creation and modifications
- Active status changes with impact analysis
- Program transfers (rare but critical)
- Structure changes

**Special Features:**
- Tracks total and active student counts
- Records affected curriculum versions
- Monitors specialization unit structure
- Provides impact analysis for changes

**Logged Fields:**
```php
- program_id
- name
- code
- description
- is_active
```

### 4. Unit Model
**Logging Level:** COMPREHENSIVE

**Key Tracked Changes:**
- Unit creation and updates
- Credit point modifications (critical for graduation)
- Code changes (affects transcripts)
- Name changes

**Special Features:**
- Tracks curriculum usage statistics
- Records prerequisite information
- Monitors equivalency relationships
- Lists affected curriculum versions

**Logged Fields:**
```php
- code
- name
- credit_points
```

### 5. Attendance Model
**Logging Level:** COMPREHENSIVE

**Key Tracked Changes:**
- Attendance record creation/updates
- Status changes with impact analysis
- Excuse documentation
- Verification status
- Participation scoring

**Special Features:**
- Calculates attendance percentage
- Tracks location data (IP, coordinates)
- Records verification details
- Monitors grade impact

**Logged Fields:**
```php
- class_session_id
- student_id
- recorded_by_lecture_id
- status
- check_in_time
- check_out_time
- minutes_late
- minutes_present
- recording_method
- notes
- excuse_reason
- participation_score
- is_verified
- affects_grade
```

### 6. GraduationApplication Model
**Logging Level:** COMPREHENSIVE

**Key Tracked Changes:**
- Application submission and processing
- Status changes through graduation pipeline
- Requirements verification
- Fee payment tracking
- Diploma mailing

**Special Features:**
- Calculates processing time
- Tracks graduation timeline
- Monitors requirements completion
- Records academic summary (GPA, credits)
- Provides ceremony details

**Logged Fields:**
```php
- student_id
- application_date
- intended_graduation_date
- status
- application_type
- ceremony_participation
- application_fee_paid
- requirements_verified
- thesis_submitted
- final_transcript_ready
- approved_by_user_id
- approved_date
- rejection_reason
- ceremony_date
- diploma_mailed_date
```

## Common Logging Patterns

### 1. Status Change Tracking
All models track status changes with:
- Previous value (from)
- New value (to)
- Timestamp of change
- Impact analysis

Example:
```php
if ($this->isDirty('status') && $this->exists) {
    $properties['status_change'] = [
        'from' => $this->getOriginal('status'),
        'to' => $this->status,
        'changed_at' => now()->toDateTimeString(),
        'impact' => $this->getStatusChangeImpact(),
    ];
}
```

### 2. Relationship Context
Models include related entity information:
- Student details (name, email, campus)
- Program/specialization information
- Unit codes and names
- User who performed actions

### 3. Campus Context
All models inherit campus-aware logging:
- Automatic campus ID detection
- Session-based campus context
- Model field fallback
- User context fallback

### 4. Impact Analysis
Critical changes include impact analysis:
- Affected students count
- Related records that need updates
- Downstream effects
- Required actions

## Activity Log Storage

### Database Table: `activity_log`
Stores all audit logs with:
- Model type and ID (subject)
- Event type (created, updated, deleted)
- Properties (old values, new values, custom data)
- Causer (user who made the change)
- Campus context
- Timestamps

### Log Retrieval
```php
// Get all activities for a model
$enrollment->activities()->get();

// Get activities by event
$enrollment->activities()->where('event', 'updated')->get();

// Get activities with causer
$enrollment->activities()->with('causer')->get();
```

## Best Practices

### 1. Always Use Comprehensive Logging for Critical Data
- Student records
- Academic progress
- Graduation data
- Attendance records

### 2. Include Contextual Information
- Related entity names (not just IDs)
- Calculated values
- Impact assessments
- Timeline information

### 3. Track All Status Changes
- Previous and new values
- Timestamp of change
- User who made the change
- Reason for change (if applicable)

### 4. Provide Clear Descriptions
- Use human-readable event descriptions
- Include identifying information
- Make logs self-explanatory

## Monitoring and Reporting

### Key Metrics to Track
1. **Change Frequency**: How often critical data changes
2. **User Activity**: Who makes the most changes
3. **Error Patterns**: Failed operations or rollbacks
4. **Compliance**: Audit trail completeness

### Sample Queries

```php
// Get recent enrollment changes
Activity::where('subject_type', Enrollment::class)
    ->where('created_at', '>=', now()->subDays(7))
    ->orderBy('created_at', 'desc')
    ->get();

// Track graduation application processing
Activity::where('subject_type', GraduationApplication::class)
    ->where('event', 'updated')
    ->whereJsonContains('properties->status_change->to', 'approved')
    ->get();

// Monitor attendance verification
Activity::where('subject_type', Attendance::class)
    ->whereJsonContains('properties->verification_change->verified', true)
    ->get();
```

## Security Considerations

### 1. Sensitive Data Exclusion
The following fields are automatically excluded:
- password
- remember_token
- api_token
- oauth_provider_id

### 2. Access Control
- Only authorized users can view audit logs
- Implement role-based access for log viewing
- Consider log retention policies

### 3. Data Integrity
- Logs are immutable once created
- Use database constraints to prevent tampering
- Regular backups of audit data

## Future Enhancements

### Planned Improvements
1. **Real-time Notifications**: Alert on critical changes
2. **Audit Reports**: Automated compliance reports
3. **Data Analytics**: Trend analysis and insights
4. **Log Archival**: Long-term storage strategy
5. **Advanced Search**: Full-text search in audit logs

### Medium Priority Models (Next Phase)
- CurriculumUnit
- AssessmentComponent & AssessmentComponentDetail
- UnitPrerequisiteGroup & UnitPrerequisiteCondition
- EquivalentUnit
- Syllabus

## Testing Audit Logs

### Manual Testing
```php
// Test enrollment audit
$enrollment = Enrollment::first();
$enrollment->update(['status' => 'completed']);
$enrollment->activities()->latest()->first(); // Check the log

// Test with relationships
$enrollment->load('student', 'curriculumVersion');
$log = $enrollment->activities()->latest()->first();
dd($log->properties); // Inspect logged data
```

### Automated Testing
Create feature tests to verify:
- Logs are created for all CRUD operations
- Custom properties are included
- Campus context is captured
- Sensitive data is excluded

## Maintenance

### Regular Tasks
1. **Monitor Log Growth**: Check table size monthly
2. **Archive Old Logs**: Move logs > 1 year to archive
3. **Validate Completeness**: Ensure all critical operations are logged
4. **Review Access Logs**: Check who's viewing audit data
5. **Update Documentation**: Keep this document current

## Support and Troubleshooting

### Common Issues

**Issue: Logs not being created**
- Check model extends AuditableModel
- Verify LogsActivity trait is used
- Ensure database migrations are run

**Issue: Missing context data**
- Check relationships are loaded
- Verify campus session is set
- Ensure user is authenticated

**Issue: Performance impact**
- Consider async logging for high-volume operations
- Optimize database indexes
- Implement log batching if needed

## Conclusion
The comprehensive audit logging system provides complete traceability for all critical academic data operations. This ensures compliance, enables troubleshooting, and maintains data integrity throughout the system lifecycle.
