# Email System API Documentation

This document provides comprehensive API documentation for the SMTP email system services and endpoints.

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Email Services API](#email-services-api)
- [SMTP Configuration API](#smtp-configuration-api)
- [Email Template API](#email-template-api)
- [Email Logging API](#email-logging-api)
- [User Preferences API](#user-preferences-api)
- [Notification API](#notification-api)
- [Monitoring API](#monitoring-api)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Examples](#examples)

## Overview

The Email System API provides programmatic access to all email functionality including:

- Sending single and bulk emails
- Managing SMTP configurations
- Creating and managing email templates
- Accessing email logs and analytics
- Managing user email preferences
- Triggering automated notifications

### Base URL
```
https://your-domain.com/api/v1/email
```

### Content Type
All API requests should use `application/json` content type.

### Response Format
All API responses follow a consistent JSON format:

```json
{
    "success": true,
    "data": {},
    "message": "Operation completed successfully",
    "meta": {
        "timestamp": "2024-01-15T10:30:00Z",
        "version": "1.0"
    }
}
```

## Authentication

All API endpoints require authentication using Laravel Sanctum tokens.

### Obtaining Access Token

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "admin@example.com",
    "password": "your-password"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "token": "1|abc123def456...",
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com"
        }
    }
}
```

### Using Access Token

Include the token in the Authorization header:

```http
Authorization: Bearer 1|abc123def456...
```

## Email Services API

### Send Single Email

Send an email to a single recipient.

```http
POST /api/v1/email/send
Authorization: Bearer {token}
Content-Type: application/json

{
    "to": "recipient@example.com",
    "subject": "Email Subject",
    "content": "Email content here",
    "template_id": 1,
    "variables": {
        "name": "John Doe",
        "course": "Computer Science"
    },
    "attachments": [
        {
            "name": "document.pdf",
            "path": "/path/to/document.pdf"
        }
    ]
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "email_id": "email_123456",
        "status": "queued",
        "recipient": "recipient@example.com",
        "subject": "Email Subject",
        "queued_at": "2024-01-15T10:30:00Z"
    },
    "message": "Email queued for delivery"
}
```

### Send Bulk Email

Send emails to multiple recipients.

```http
POST /api/v1/email/send-bulk
Authorization: Bearer {token}
Content-Type: application/json

{
    "recipients": [
        {
            "email": "user1@example.com",
            "variables": {"name": "User One"}
        },
        {
            "email": "user2@example.com",
            "variables": {"name": "User Two"}
        }
    ],
    "subject": "Bulk Email Subject",
    "template_id": 1,
    "schedule_at": "2024-01-15T14:00:00Z"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "batch_id": "batch_789012",
        "total_recipients": 2,
        "status": "scheduled",
        "scheduled_at": "2024-01-15T14:00:00Z"
    },
    "message": "Bulk email scheduled successfully"
}
```

### Get Email Status

Check the status of a sent email.

```http
GET /api/v1/email/status/{email_id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "email_id": "email_123456",
        "status": "delivered",
        "recipient": "recipient@example.com",
        "sent_at": "2024-01-15T10:31:00Z",
        "delivered_at": "2024-01-15T10:31:15Z",
        "attempts": 1
    }
}
```

## SMTP Configuration API

### List SMTP Configurations

```http
GET /api/v1/email/smtp-configs
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Gmail SMTP",
            "host": "smtp.gmail.com",
            "port": 587,
            "encryption": "tls",
            "from_address": "noreply@example.com",
            "from_name": "Academic System",
            "is_active": true,
            "daily_limit": 500,
            "rate_limit": 100
        }
    ]
}
```

### Create SMTP Configuration

```http
POST /api/v1/email/smtp-configs
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "SendGrid SMTP",
    "host": "smtp.sendgrid.net",
    "port": 587,
    "username": "apikey",
    "password": "your-api-key",
    "encryption": "tls",
    "from_address": "noreply@example.com",
    "from_name": "Academic System",
    "daily_limit": 1000,
    "rate_limit": 200,
    "is_active": true
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "name": "SendGrid SMTP",
        "host": "smtp.sendgrid.net",
        "port": 587,
        "encryption": "tls",
        "from_address": "noreply@example.com",
        "from_name": "Academic System",
        "is_active": true,
        "daily_limit": 1000,
        "rate_limit": 200,
        "created_at": "2024-01-15T10:30:00Z"
    },
    "message": "SMTP configuration created successfully"
}
```

### Test SMTP Configuration

```http
POST /api/v1/email/smtp-configs/{id}/test
Authorization: Bearer {token}
Content-Type: application/json

{
    "test_email": "admin@example.com"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "connection_status": "success",
        "test_email_sent": true,
        "response_time": 1.23,
        "smtp_response": "250 OK"
    },
    "message": "SMTP configuration test successful"
}
```

### Update SMTP Configuration

```http
PUT /api/v1/email/smtp-configs/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Updated Gmail SMTP",
    "daily_limit": 750,
    "rate_limit": 150
}
```

### Delete SMTP Configuration

```http
DELETE /api/v1/email/smtp-configs/{id}
Authorization: Bearer {token}
```

## Email Template API

### List Email Templates

```http
GET /api/v1/email/templates
Authorization: Bearer {token}
```

**Query Parameters:**
- `type` - Filter by template type
- `active` - Filter by active status
- `page` - Page number for pagination
- `per_page` - Items per page

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Welcome Email",
            "type": "welcome",
            "subject": "Welcome to {{institution_name}}",
            "html_content": "<h1>Welcome {{student_name}}!</h1>",
            "text_content": "Welcome {{student_name}}!",
            "variables": ["student_name", "institution_name"],
            "is_active": true,
            "version": 1,
            "created_at": "2024-01-15T10:30:00Z",
            "updated_at": "2024-01-15T10:30:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 25,
        "last_page": 2
    }
}
```

### Get Email Template

```http
GET /api/v1/email/templates/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Welcome Email",
        "type": "welcome",
        "subject": "Welcome to {{institution_name}}",
        "html_content": "<h1>Welcome {{student_name}}!</h1>",
        "text_content": "Welcome {{student_name}}!",
        "variables": ["student_name", "institution_name"],
        "is_active": true,
        "version": 1,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z"
    }
}
```

### Create Email Template

```http
POST /api/v1/email/templates
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Grade Notification",
    "type": "grade_notification",
    "subject": "Grade Published for {{course_name}}",
    "html_content": "<h1>Grade Published</h1><p>Your grade for {{course_name}} is {{grade}}</p>",
    "text_content": "Grade Published\n\nYour grade for {{course_name}} is {{grade}}",
    "variables": ["course_name", "grade", "student_name"],
    "is_active": true
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "name": "Grade Notification",
        "type": "grade_notification",
        "subject": "Grade Published for {{course_name}}",
        "html_content": "<h1>Grade Published</h1><p>Your grade for {{course_name}} is {{grade}}</p>",
        "text_content": "Grade Published\n\nYour grade for {{course_name}} is {{grade}}",
        "variables": ["course_name", "grade", "student_name"],
        "is_active": true,
        "version": 1,
        "created_at": "2024-01-15T10:30:00Z"
    },
    "message": "Email template created successfully"
}
```

### Preview Email Template

```http
POST /api/v1/email/templates/{id}/preview
Authorization: Bearer {token}
Content-Type: application/json

{
    "variables": {
        "student_name": "John Doe",
        "course_name": "Computer Science 101",
        "grade": "A"
    }
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "subject": "Grade Published for Computer Science 101",
        "html_content": "<h1>Grade Published</h1><p>Your grade for Computer Science 101 is A</p>",
        "text_content": "Grade Published\n\nYour grade for Computer Science 101 is A"
    }
}
```

### Update Email Template

```http
PUT /api/v1/email/templates/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "subject": "Updated: Grade Published for {{course_name}}",
    "html_content": "<h1>Grade Update</h1><p>Your grade for {{course_name}} is {{grade}}</p>"
}
```

### Delete Email Template

```http
DELETE /api/v1/email/templates/{id}
Authorization: Bearer {token}
```

## Email Logging API

### Get Email Logs

```http
GET /api/v1/email/logs
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` - Filter by status (sent, delivered, failed, bounced)
- `recipient` - Filter by recipient email
- `date_from` - Start date (YYYY-MM-DD)
- `date_to` - End date (YYYY-MM-DD)
- `template_id` - Filter by template ID
- `page` - Page number
- `per_page` - Items per page

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "recipient": "student@example.com",
            "sender": "noreply@example.com",
            "subject": "Welcome to Academic System",
            "template_id": 1,
            "status": "delivered",
            "sent_at": "2024-01-15T10:30:00Z",
            "delivered_at": "2024-01-15T10:30:15Z",
            "retry_count": 0,
            "error_message": null
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 1250,
        "last_page": 25
    }
}
```

### Get Email Log Details

```http
GET /api/v1/email/logs/{id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "recipient": "student@example.com",
        "sender": "noreply@example.com",
        "subject": "Welcome to Academic System",
        "template_id": 1,
        "template_name": "Welcome Email",
        "status": "delivered",
        "sent_at": "2024-01-15T10:30:00Z",
        "delivered_at": "2024-01-15T10:30:15Z",
        "failed_at": null,
        "retry_count": 0,
        "error_message": null,
        "metadata": {
            "smtp_config_id": 1,
            "queue_job_id": "job_123456",
            "user_agent": "Laravel Mailer"
        }
    }
}
```

## User Preferences API

### Get User Email Preferences

```http
GET /api/v1/email/preferences/{user_id}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": 123,
            "notification_type": "course_registration",
            "is_enabled": true,
            "frequency": "immediate",
            "last_sent_at": "2024-01-15T10:30:00Z"
        },
        {
            "id": 2,
            "user_id": 123,
            "notification_type": "grade_published",
            "is_enabled": true,
            "frequency": "immediate",
            "last_sent_at": null
        }
    ]
}
```

### Update User Email Preferences

```http
PUT /api/v1/email/preferences/{user_id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "preferences": [
        {
            "notification_type": "course_registration",
            "is_enabled": true,
            "frequency": "immediate"
        },
        {
            "notification_type": "grade_published",
            "is_enabled": false,
            "frequency": "daily"
        }
    ]
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "updated_preferences": 2,
        "user_id": 123
    },
    "message": "Email preferences updated successfully"
}
```

### Bulk Update Preferences

```http
POST /api/v1/email/preferences/bulk-update
Authorization: Bearer {token}
Content-Type: application/json

{
    "user_ids": [123, 124, 125],
    "notification_type": "system_announcements",
    "is_enabled": true,
    "frequency": "immediate"
}
```

## Notification API

### Trigger Manual Notification

```http
POST /api/v1/email/notifications/trigger
Authorization: Bearer {token}
Content-Type: application/json

{
    "event_type": "grade_published",
    "recipients": [
        {
            "user_id": 123,
            "email": "student@example.com"
        }
    ],
    "data": {
        "course_name": "Computer Science 101",
        "grade": "A",
        "instructor": "Dr. Smith"
    }
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "notification_id": "notif_789012",
        "event_type": "grade_published",
        "recipients_count": 1,
        "status": "queued",
        "queued_at": "2024-01-15T10:30:00Z"
    },
    "message": "Notification triggered successfully"
}
```

### Get Notification Status

```http
GET /api/v1/email/notifications/{notification_id}/status
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "notification_id": "notif_789012",
        "event_type": "grade_published",
        "status": "completed",
        "total_recipients": 1,
        "sent_count": 1,
        "failed_count": 0,
        "started_at": "2024-01-15T10:30:00Z",
        "completed_at": "2024-01-15T10:30:30Z"
    }
}
```

### List Notification Types

```http
GET /api/v1/email/notifications/types
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "type": "course_registration",
            "name": "Course Registration",
            "description": "Notifications related to course registration",
            "template_id": 1,
            "is_enabled": true
        },
        {
            "type": "grade_published",
            "name": "Grade Published",
            "description": "Notifications when grades are published",
            "template_id": 2,
            "is_enabled": true
        }
    ]
}
```

## Monitoring API

### Get Email Statistics

```http
GET /api/v1/email/stats
Authorization: Bearer {token}
```

**Query Parameters:**
- `period` - Time period (today, week, month, year)
- `date_from` - Start date
- `date_to` - End date

**Response:**
```json
{
    "success": true,
    "data": {
        "period": "today",
        "total_sent": 1250,
        "total_delivered": 1200,
        "total_failed": 30,
        "total_bounced": 20,
        "delivery_rate": 96.0,
        "bounce_rate": 1.6,
        "failure_rate": 2.4,
        "by_template": [
            {
                "template_id": 1,
                "template_name": "Welcome Email",
                "sent": 500,
                "delivered": 485
            }
        ],
        "by_hour": [
            {
                "hour": "10:00",
                "sent": 150,
                "delivered": 145
            }
        ]
    }
}
```

### Get Queue Status

```http
GET /api/v1/email/queue/status
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "pending_jobs": 25,
        "failed_jobs": 3,
        "processed_jobs": 1500,
        "average_processing_time": 2.5,
        "workers_active": 3,
        "queue_health": "healthy"
    }
}
```

### Get System Health

```http
GET /api/v1/email/health
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "overall_status": "healthy",
        "smtp_connections": {
            "status": "healthy",
            "active_configs": 2,
            "failed_configs": 0
        },
        "queue_system": {
            "status": "healthy",
            "pending_jobs": 25,
            "failed_jobs": 3
        },
        "database": {
            "status": "healthy",
            "connection_time": 0.05
        },
        "storage": {
            "status": "healthy",
            "disk_usage": "45%"
        }
    }
}
```

## Error Handling

### Error Response Format

All API errors follow a consistent format:

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "email": ["The email field is required."],
            "subject": ["The subject field is required."]
        }
    },
    "meta": {
        "timestamp": "2024-01-15T10:30:00Z",
        "request_id": "req_123456"
    }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `AUTHENTICATION_ERROR` | 401 | Invalid or missing authentication |
| `AUTHORIZATION_ERROR` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `SMTP_ERROR` | 500 | SMTP connection or sending error |
| `QUEUE_ERROR` | 500 | Queue processing error |
| `RATE_LIMIT_EXCEEDED` | 429 | Rate limit exceeded |
| `TEMPLATE_ERROR` | 422 | Template rendering error |
| `CONFIGURATION_ERROR` | 500 | System configuration error |

### Error Handling Best Practices

1. **Always check the `success` field** in responses
2. **Handle rate limiting** with exponential backoff
3. **Log error details** for debugging
4. **Implement retry logic** for transient errors
5. **Validate data** before sending requests

## Rate Limiting

### Rate Limits

| Endpoint | Limit | Window |
|----------|-------|--------|
| `/api/v1/email/send` | 100 requests | per minute |
| `/api/v1/email/send-bulk` | 10 requests | per minute |
| `/api/v1/email/templates` | 200 requests | per minute |
| `/api/v1/email/logs` | 500 requests | per minute |
| All other endpoints | 300 requests | per minute |

### Rate Limit Headers

Rate limit information is included in response headers:

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1642248600
```

### Handling Rate Limits

When rate limit is exceeded, the API returns:

```json
{
    "success": false,
    "error": {
        "code": "RATE_LIMIT_EXCEEDED",
        "message": "Too many requests. Please try again later.",
        "retry_after": 60
    }
}
```

## Examples

### Complete Email Sending Example

```javascript
// Send a welcome email with template
const sendWelcomeEmail = async (studentData) => {
    try {
        const response = await fetch('/api/v1/email/send', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                to: studentData.email,
                template_id: 1, // Welcome email template
                variables: {
                    student_name: studentData.name,
                    student_code: studentData.code,
                    institution_name: 'Academic University',
                    login_url: 'https://portal.university.edu'
                }
            })
        });

        const result = await response.json();
        
        if (result.success) {
            console.log('Email queued:', result.data.email_id);
            return result.data.email_id;
        } else {
            console.error('Email failed:', result.error);
            throw new Error(result.error.message);
        }
    } catch (error) {
        console.error('API request failed:', error);
        throw error;
    }
};
```

### Bulk Email with Error Handling

```javascript
const sendBulkGradeNotifications = async (students) => {
    const recipients = students.map(student => ({
        email: student.email,
        variables: {
            student_name: student.name,
            course_name: student.course,
            grade: student.grade,
            instructor: student.instructor
        }
    }));

    try {
        const response = await fetch('/api/v1/email/send-bulk', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                recipients: recipients,
                template_id: 2, // Grade notification template
                subject: 'Grade Published for {{course_name}}'
            })
        });

        const result = await response.json();
        
        if (result.success) {
            console.log(`Bulk email scheduled: ${result.data.batch_id}`);
            
            // Monitor batch status
            const batchId = result.data.batch_id;
            const checkStatus = setInterval(async () => {
                const statusResponse = await fetch(`/api/v1/email/batch/${batchId}/status`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                const statusResult = await statusResponse.json();
                
                if (statusResult.data.status === 'completed') {
                    console.log('Batch completed:', statusResult.data);
                    clearInterval(checkStatus);
                }
            }, 30000); // Check every 30 seconds
            
        } else {
            console.error('Bulk email failed:', result.error);
        }
    } catch (error) {
        console.error('Bulk email request failed:', error);
    }
};
```

### Template Management Example

```javascript
const createAndTestTemplate = async (templateData) => {
    try {
        // Create template
        const createResponse = await fetch('/api/v1/email/templates', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(templateData)
        });

        const createResult = await createResponse.json();
        
        if (!createResult.success) {
            throw new Error('Template creation failed');
        }

        const templateId = createResult.data.id;
        console.log('Template created:', templateId);

        // Preview template
        const previewResponse = await fetch(`/api/v1/email/templates/${templateId}/preview`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                variables: {
                    student_name: 'John Doe',
                    course_name: 'Computer Science 101'
                }
            })
        });

        const previewResult = await previewResponse.json();
        
        if (previewResult.success) {
            console.log('Template preview:', previewResult.data);
        }

        return templateId;
    } catch (error) {
        console.error('Template management failed:', error);
        throw error;
    }
};
```

---

This API documentation provides comprehensive information for integrating with the email system. For additional support or advanced use cases, consult the system administrator or technical documentation.
