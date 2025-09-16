# Login as Student Feature

## Overview

The "Login as Student" feature allows administrators to quickly access the student portal by logging in as a specific student. This feature is useful for:

- Testing student functionality
- Troubleshooting student-specific issues  
- Providing direct support to students
- Demonstrating features to stakeholders

## Implementation

### Frontend Components

The feature has been added to the Students Management page (`resources/js/pages/students/Index.vue`) with:

1. **Login Button**: A new action button in the data table actions column
2. **API Integration**: Uses the existing student authentication API endpoint
3. **Confirmation Dialog**: Asks for confirmation before proceeding
4. **Error Handling**: Provides helpful error messages
5. **Portal Redirect**: Opens the student portal in a new tab with authentication token

### Configuration

The feature requires the `VITE_APP_URL_FE` environment variable to be set:

```env
VITE_APP_URL_FE=http://localhost:3000
```

This URL points to your student portal frontend application.

## Usage

1. Navigate to the Students Management page
2. Find the student you want to login as
3. Click the "Login" icon (🔑) in the actions column
4. Confirm the action in the dialog
5. The student portal will open in a new tab with the student logged in

### Button States

- **Enabled**: For students with `active` status
- **Disabled**: For students with non-active status (inactive, suspended, etc.)

## Technical Details

### API Endpoint

The feature uses a dedicated admin impersonation endpoint:

```
POST /api/admin/students/impersonate
```

**Request Body:**
```json
{
    "email": "student@example.com",
    "device_name": "Admin Portal - Student Impersonation",
    "purpose": "support"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "student": {
            "id": 123,
            "student_id": "STU001",
            "full_name": "John Doe",
            "email": "student@example.com",
            "status": "active",
            "campus": "Main Campus",
            "program": "Computer Science",
            "specialization": "Software Engineering",
            "avatar_url": null
        },
        "token": "impersonation_token",
        "token_type": "Bearer",
        "expires_at": "2024-01-01T02:00:00.000000Z",
        "impersonation_info": {
            "impersonated_by": {
                "id": 1,
                "name": "Admin User",
                "email": "admin@example.com"
            },
            "impersonated_at": "2024-01-01T00:00:00.000000Z",
            "purpose": "support"
        }
    },
    "message": "Student impersonation token generated successfully"
}
```

### URL Format

The generated portal URL includes:

```
{STUDENT_PORTAL_URL}?token={ENCODED_TOKEN}&redirect=dashboard
```

### Security Considerations

**Current Implementation Features:**
- ✅ Dedicated admin impersonation API (no student credentials required)
- ✅ Comprehensive audit logging for all impersonation activities
- ✅ Role-based authorization checks (admin/staff permissions required)
- ✅ Time-limited impersonation tokens (2-hour expiration)
- ✅ Student status and academic hold validation
- ✅ Purpose tracking for compliance

**Security Features:**

1. **Dedicated Impersonation API:**
   ```php
   POST /api/admin/students/impersonate
   ```
   - ✅ Generates tokens without requiring student passwords
   - ✅ Includes admin authentication and authorization
   - ✅ Logs all impersonation activities with full context

2. **Comprehensive Audit Trail:**
   - ✅ Logs who performed the impersonation (admin details)
   - ✅ Logs when and why the impersonation occurred
   - ✅ Tracks IP address and user agent
   - ✅ Records device name and purpose
   - ✅ Logs student and admin information

3. **Role-Based Access Control:**
   - ✅ Restricts feature to specific admin roles
   - ✅ Campus-based restrictions (via existing middleware)
   - ✅ Multiple permission levels supported
   - ✅ Proper authorization failure handling

4. **Enhanced Security:**
   - ✅ Time-limited impersonation tokens (2 hours)
   - ✅ Student activity validation (active status required)
   - ✅ Academic hold checking
   - ✅ Detailed error handling

**Additional Security Recommendations:**

1. **Session Management:**
   - [ ] Track active impersonation sessions
   - [ ] Allow admins to terminate sessions
   - [ ] Send email notifications to students (optional)

2. **Enhanced Monitoring:**
   - [ ] Dashboard for impersonation activities
   - [ ] Automated alerts for suspicious patterns
   - [ ] Regular audit reports

3. **Additional Safeguards:**
   - [ ] Rate limiting for impersonation requests
   - [ ] Two-factor authentication requirement for impersonation
   - [ ] Time-based restrictions (e.g., business hours only)

## Environment Configuration

Make sure the following environment variable is set in your `.env` file:

```env
# Student Portal Frontend URL
VITE_APP_URL_FE=http://localhost:3000
```

For production environments:
```env
VITE_APP_URL_FE=https://your-student-portal.domain.com
```

## Error Handling

The implementation includes comprehensive error handling:

- **Invalid Credentials**: Provides helpful message about credential setup
- **Network Errors**: Shows generic network error message  
- **API Errors**: Displays specific error messages from the API
- **Permission Errors**: Indicates authorization issues

## Future Enhancements

1. **Dedicated Admin Impersonation Endpoint**
2. **Audit Logging System**
3. **Time-Limited Impersonation Sessions**
4. **Role-Based Access Control**
5. **Student Notification System**
6. **Impersonation Session Management**

## Troubleshooting

**Issue**: "Invalid credentials" error
- **Cause**: Student doesn't have proper password set
- **Solution**: Implement dedicated impersonation API or ensure students have default passwords

**Issue**: Portal doesn't open
- **Cause**: `VITE_APP_URL_FE` not set or incorrect
- **Solution**: Check environment variable configuration

**Issue**: Token not recognized by student portal  
- **Cause**: Token format mismatch or portal configuration
- **Solution**: Verify token handling in student portal application

## Implementation Checklist

### Core Features ✅ Complete
- [x] Add login button to students index page
- [x] Create dedicated admin impersonation API
- [x] Add environment variable configuration
- [x] Include comprehensive error handling and user feedback
- [x] Add confirmation dialog with clear messaging
- [x] Document the feature thoroughly

### Security & Authorization ✅ Complete
- [x] Implement role-based access control
- [x] Add comprehensive audit logging
- [x] Validate student status and academic holds
- [x] Add purpose tracking for compliance
- [x] Implement time-limited tokens (2-hour expiration)
- [x] Add IP address and user agent tracking

### Backend Implementation ✅ Complete
- [x] `AdminStudentImpersonationController` - Main controller logic
- [x] `AdminStudentImpersonationRequest` - Request validation and authorization
- [x] Admin API routes with proper middleware
- [x] Comprehensive logging and error handling

### Frontend Implementation ✅ Complete
- [x] Updated Students Index page with impersonation button
- [x] API integration with new impersonation endpoint
- [x] Enhanced error handling with specific error cases
- [x] User feedback with toast notifications
- [x] Portal redirect with token parameter

### Future Enhancements 📋 Recommended
- [ ] Impersonation session dashboard
- [ ] Active session management and termination
- [ ] Email notifications to students (optional)
- [ ] Rate limiting for impersonation requests
- [ ] Automated audit reports
- [ ] Two-factor authentication requirement
