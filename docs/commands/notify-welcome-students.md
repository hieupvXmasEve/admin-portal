# Send Welcome Notifications to New Students

## Command Overview

The `notify:welcome-students` command automatically sends welcome notifications to newly admitted students who haven't received one yet.

## Usage

```bash
php artisan notify:welcome-students [options]
```

### Options

- `--dry-run`: Preview what would be processed without making any changes
- `--since=YYYY-MM-DD`: Send notifications to students admitted since a specific date
- `--days=N`: Send notifications to students admitted within the last N days (default: 30)

## Examples

### Basic Usage
```bash
# Send welcome notifications to students admitted in the last 30 days
php artisan notify:welcome-students

# Preview without sending notifications
php artisan notify:welcome-students --dry-run

# Send to students admitted in the last 7 days
php artisan notify:welcome-students --days=7

# Send to students admitted since August 23, 2025
php artisan notify:welcome-students --since=2025-08-23
```

## Student Selection Criteria

The command identifies "new students" based on:

1. **Admission Date**: Students admitted on or after the specified date
2. **Status**: Students with the following statuses are included:
   - `active`: Fully active students
   - `intake_pre_uni_gc`: Students in pre-university graduate certificate intake
   - `intake_course`: Students in course intake phase
   - `pending`: Students with pending status

3. **Idempotency**: Students who have already received a welcome notification are excluded

## Notification Details

Each welcome notification includes:
- **Category**: System notification
- **Title**: "Welcome to Swinburne!"
- **Message**: Personalized welcome message with student's name
- **Channels**: Database and broadcast (for real-time updates)
- **Data**: Student ID, admission date, and welcome type
- **Expiration**: Welcome messages never expire

## Performance Features

- **Chunked Processing**: Processes students in batches of 100 to avoid memory issues
- **Progress Bar**: Shows real-time processing progress
- **Error Handling**: Continues processing even if individual notifications fail
- **Detailed Reports**: Shows summary of successful and failed notifications

## Safety Features

- **Dry Run Mode**: Test the command without making changes
- **Idempotency**: Prevents duplicate notifications to the same student
- **Error Recovery**: Handles individual failures without stopping the entire process

## Output Example

```
Looking for students admitted since: 2025-08-23 00:00:00
Found 226 new student(s) to notify.
Processing students: 226/226 [████████████████████████████] 100% - Success: 226

Processing complete!
+-----------------+-------+
| Metric          | Count |
+-----------------+-------+
| Total Students  | 226   |
| Processed       | 226   |
| Successful      | 226   |
| Failed          | 0     |
+-----------------+-------+
```

## Scheduling

To automatically send welcome notifications to new students, add this to your `routes/console.php`:

```php
// Send welcome notifications to new students daily
Schedule::command('notify:welcome-students --days=1')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();
```

## Error Handling

- **Invalid Date Format**: Returns error code 1 with helpful message
- **Database Connection Issues**: Handles gracefully with error messages
- **Individual Notification Failures**: Logs errors but continues processing
- **Memory Issues**: Uses chunked processing to prevent memory exhaustion

## Implementation Details

### Models Used
- `App\Models\Student`: Source of student data
- `App\Models\Notification`: Target for notification records
- `App\Enums\NotificationCategory`: Notification categorization

### Key Methods
- `determineCutoffDate()`: Calculates the date threshold for "new" students
- `getNewStudentsQuery()`: Builds the query for eligible students
- `sendWelcomeNotification()`: Creates notification records with idempotency
- `showDryRunPreview()`: Displays preview for dry-run mode

### Database Relationships
- Students have a morphMany relationship with notifications
- Notifications use polymorphic relationships to link to students

## Troubleshooting

### No Students Found
- Check if students have `admission_date` set
- Verify student statuses match the included statuses
- Confirm the date range includes the expected period

### Performance Issues
- The command processes in chunks, so large datasets should be handled efficiently
- Use `--dry-run` first to estimate processing time
- Consider running during off-peak hours for large batches

### Duplicate Notifications
- The command includes idempotency checks
- If duplicates occur, check the notification matching logic
- Verify that the `title` and `category` fields are consistent