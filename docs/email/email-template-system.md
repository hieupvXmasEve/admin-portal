# Email Template System Documentation

## Overview

The Email Template System provides comprehensive template management for academic email communications with versioning, rollback capabilities, and variable substitution.

## Features

- **Template Management**: Create, update, and manage email templates
- **Version Control**: Full versioning system with rollback capabilities
- **Variable Substitution**: Dynamic content using template variables
- **Academic Templates**: Pre-built templates for common academic scenarios
- **Template Validation**: Syntax checking and variable validation
- **Command Line Tools**: Manage templates via Artisan commands

## Available Templates

### Academic Email Templates

1. **Welcome Email** (`welcome`)
   - Sent to new students upon enrollment
   - Variables: `user_name`, `course_name`, `semester`, `system_name`, `login_url`

2. **Grade Notification** (`grade_notification`)
   - Sent when grades are published
   - Variables: `student_name`, `assessment_name`, `course_name`, `grade`, `total_points`

3. **Course Registration Confirmation** (`course_registration`)
   - Sent upon successful course registration
   - Variables: `student_name`, `course_name`, `course_code`, `semester`, `registration_date`

4. **Academic Hold Notification** (`academic_hold`)
   - Sent when academic holds are placed
   - Variables: `student_name`, `hold_type`, `hold_reason`, `contact_info`

5. **Enrollment Confirmation** (`enrollment_confirmation`)
   - Sent when student enrollment is confirmed
   - Variables: `student_name`, `course_name`, `course_code`, `semester`, `instructor_name`, `schedule`, `location`

6. **Assessment Deadline Reminder** (`assessment_deadline`)
   - Sent as deadline reminders
   - Variables: `assessment_name`, `course_name`, `deadline`, `submission_link`

7. **System Announcement** (`system_announcement`)
   - Used for system-wide announcements
   - Variables: `announcement_title`, `announcement_body`, `effective_date`

8. **Grade Submission Reminder** (`reminder`)
   - Sent to lecturers for grade submission deadlines
   - Variables: `lecturer_name`, `course_name`, `course_code`, `assessment_name`, `deadline`, `student_count`

9. **Course Registration Opening** (`system_announcement`)
   - Sent when registration periods open
   - Variables: `student_name`, `semester`, `registration_start`, `registration_end`, `registration_url`

10. **Academic Progress Report** (`grade_notification`)
    - Sent with academic progress summaries
    - Variables: `student_name`, `semester`, `current_gpa`, `cumulative_gpa`, `credits_completed`, `academic_standing`

11. **Lecturer Course Assignment** (`system_announcement`)
    - Sent when lecturers are assigned to courses
    - Variables: `lecturer_name`, `course_name`, `course_code`, `semester`, `schedule`, `location`, `student_count`

## Usage

### Using Templates in Code

```php
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;

// Get a template
$template = EmailTemplate::getLatestVersion('Welcome Email');

// Render with variables
$rendered = $template->render([
    'user_name' => 'John Doe',
    'course_name' => 'Computer Science 101',
    'semester' => 'Fall 2024',
    'system_name' => 'University Portal',
    'login_url' => 'https://portal.university.edu'
]);

// Use with EmailService
$emailService = app(EmailTemplateService::class);
$emailService->sendUsingTemplate(
    'Welcome Email',
    'john.doe@university.edu',
    $variables
);
```

### Template Versioning

```php
use App\Services\EmailTemplateVersioningService;

$versioningService = app(EmailTemplateVersioningService::class);

// Create new version
$newVersion = $versioningService->createNewVersion($template, [
    'subject' => 'Updated Subject {{name}}',
    'html_content' => '<h1>Updated Content</h1>',
    'is_active' => true
]);

// Rollback to previous version
$rolledBack = $versioningService->rollbackToVersion('Welcome Email', 2);

// Get all versions
$versions = $versioningService->getTemplateVersions('Welcome Email');
```

### Command Line Management

```bash
# List all templates
php artisan email-template:manage list

# View template versions
php artisan email-template:manage versions --template="Welcome Email"

# Rollback to specific version
php artisan email-template:manage rollback --template="Welcome Email" --template-version=2

# Archive old versions (keep only 5 most recent)
php artisan email-template:manage archive --template="Welcome Email" --keep=5

# Duplicate a template
php artisan email-template:manage duplicate --template="Welcome Email" --new-name="Custom Welcome"
```

## Template Variables

### Variable Syntax
Variables use double curly braces: `{{variable_name}}`

### Common Variables
- `user_name` / `student_name` / `lecturer_name`: User names
- `course_name` / `course_code`: Course information
- `semester`: Academic semester
- `system_name`: Institution/system name
- `grade` / `assessment_name`: Grade-related information
- `deadline` / `due_date`: Date information
- `login_url` / `registration_url`: System URLs

### Variable Validation
Templates automatically validate that all required variables are provided:

```php
$template = EmailTemplate::find(1);
$missingVars = $template->validateVariables(['name' => 'John']);
// Returns array of missing variable names
```

## Template Structure

### HTML Templates
- Full HTML structure with inline CSS
- Responsive design considerations
- Institutional branding elements
- Proper accessibility markup

### Text Templates
- Plain text alternative for HTML emails
- Same variable substitution
- Readable formatting without HTML

## Best Practices

1. **Variable Naming**: Use descriptive, consistent variable names
2. **Content Structure**: Keep templates modular and reusable
3. **Version Control**: Create new versions for significant changes
4. **Testing**: Always test templates with sample data
5. **Accessibility**: Ensure templates are accessible to all users
6. **Branding**: Maintain consistent institutional branding

## Security Considerations

- Template content is sanitized to prevent XSS
- Variable values are escaped during rendering
- Access control for template modification
- Audit logging for all template changes

## Performance

- Templates are cached for improved performance
- Variable substitution is optimized for bulk operations
- Database queries are optimized with proper indexing
- Old versions can be archived to maintain performance
