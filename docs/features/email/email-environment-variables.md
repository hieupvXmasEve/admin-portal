# Email System Environment Variables

This document provides comprehensive documentation for all environment variables used by the SMTP email system in production environments.

## Table of Contents

- [Core SMTP Configuration](#core-smtp-configuration)
- [Email Queue Configuration](#email-queue-configuration)
- [Email Monitoring](#email-monitoring)
- [Email Security](#email-security)
- [Email Templates](#email-templates)
- [Notification Settings](#notification-settings)
- [Provider-Specific Configurations](#provider-specific-configurations)
- [Production Examples](#production-examples)

## Core SMTP Configuration

### Basic SMTP Settings

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `MAIL_MAILER` | Mail driver to use | `smtp` | Yes | `smtp` |
| `MAIL_HOST` | SMTP server hostname | - | Yes | `smtp.gmail.com` |
| `MAIL_PORT` | SMTP server port | `587` | Yes | `587` |
| `MAIL_USERNAME` | SMTP authentication username | - | Yes | `your-email@domain.com` |
| `MAIL_PASSWORD` | SMTP authentication password | - | Yes | `your-app-password` |
| `MAIL_ENCRYPTION` | Encryption method | `tls` | Yes | `tls` or `ssl` |
| `MAIL_FROM_ADDRESS` | Default sender email address | - | Yes | `noreply@yourdomain.com` |
| `MAIL_FROM_NAME` | Default sender name | `Laravel` | No | `Academic System` |

### Advanced SMTP Settings

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `MAIL_TIMEOUT` | Connection timeout in seconds | `60` | No | `120` |
| `MAIL_LOCAL_DOMAIN` | Local domain for EHLO command | - | No | `yourdomain.com` |
| `MAIL_VERIFY_PEER` | Verify SSL peer certificate | `true` | No | `false` |

## Email Queue Configuration

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `EMAIL_QUEUE_CONNECTION` | Queue connection for emails | `redis` | No | `redis` |
| `EMAIL_QUEUE_NAME` | Queue name for email jobs | `emails` | No | `high-priority-emails` |
| `EMAIL_RETRY_AFTER` | Retry failed jobs after (seconds) | `300` | No | `600` |
| `EMAIL_MAX_TRIES` | Maximum retry attempts | `3` | No | `5` |
| `EMAIL_TIMEOUT` | Job timeout in seconds | `60` | No | `120` |

## Email Monitoring

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `EMAIL_MONITORING_ENABLED` | Enable email monitoring | `true` | No | `false` |
| `EMAIL_LOG_RETENTION_DAYS` | Days to keep email logs | `90` | No | `180` |
| `EMAIL_ALERT_FAILURE_RATE` | Alert when failure rate exceeds (%) | `10` | No | `15` |
| `EMAIL_ALERT_QUEUE_BACKLOG` | Alert when queue exceeds count | `1000` | No | `500` |
| `EMAIL_ALERT_DAILY_LIMIT_USAGE` | Alert when daily limit usage exceeds (%) | `80` | No | `90` |
| `EMAIL_ALERT_RECIPIENTS` | Comma-separated alert recipients | - | No | `admin@domain.com,ops@domain.com` |

## Email Security

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `EMAIL_ENCRYPTION_KEY` | Key for encrypting email credentials | `APP_KEY` | No | `base64:your-key-here` |
| `EMAIL_ALLOWED_DOMAINS` | Comma-separated allowed domains | - | No | `yourdomain.com,partner.com` |
| `EMAIL_BLOCKED_DOMAINS` | Comma-separated blocked domains | - | No | `spam.com,blocked.net` |
| `EMAIL_RATE_LIMITING_ENABLED` | Enable rate limiting | `true` | No | `false` |
| `EMAIL_MAX_PER_MINUTE` | Max emails per minute | `60` | No | `100` |
| `EMAIL_MAX_PER_HOUR` | Max emails per hour | `1000` | No | `2000` |
| `EMAIL_MAX_PER_DAY` | Max emails per day | `10000` | No | `50000` |

## Email Templates

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `EMAIL_DEFAULT_LANGUAGE` | Default template language | `en` | No | `vi` |
| `EMAIL_SUPPORTED_LANGUAGES` | Comma-separated supported languages | `en,vi` | No | `en,vi,fr` |
| `EMAIL_TEMPLATE_CACHE_ENABLED` | Enable template caching | `true` | No | `false` |
| `EMAIL_TEMPLATE_CACHE_TTL` | Template cache TTL in seconds | `3600` | No | `7200` |
| `EMAIL_TEMPLATE_VERSIONING_ENABLED` | Enable template versioning | `true` | No | `false` |

## Notification Settings

### Academic Event Notifications

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `NOTIFY_COURSE_REGISTRATION` | Send course registration notifications | `true` | No | `false` |
| `NOTIFY_GRADE_PUBLISHED` | Send grade published notifications | `true` | No | `false` |
| `NOTIFY_ACADEMIC_HOLD` | Send academic hold notifications | `true` | No | `false` |
| `NOTIFY_ENROLLMENT_CONFIRMED` | Send enrollment confirmation notifications | `true` | No | `false` |
| `NOTIFY_ASSESSMENT_DEADLINE` | Send assessment deadline notifications | `true` | No | `false` |

### Reminder Schedules

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `REMINDER_ASSESSMENT_DEADLINE_DAYS` | Days before assessment deadline to send reminders | `7,3,1` | No | `14,7,3,1` |
| `REMINDER_REGISTRATION_DEADLINE_DAYS` | Days before registration deadline to send reminders | `14,7,3,1` | No | `21,14,7,3,1` |
| `REMINDER_GRADE_SUBMISSION_DAYS` | Days before grade submission deadline to send reminders | `5,2,1` | No | `7,3,1` |

## Provider-Specific Configurations

### Gmail/Google Workspace

```env
# Gmail SMTP Configuration
GMAIL_USERNAME=your-email@gmail.com
GMAIL_PASSWORD=your-app-specific-password
GMAIL_FROM_ADDRESS=noreply@yourdomain.com
GMAIL_FROM_NAME="Academic System"

# Use these in your main mail config
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=${GMAIL_USERNAME}
MAIL_PASSWORD=${GMAIL_PASSWORD}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=${GMAIL_FROM_ADDRESS}
MAIL_FROM_NAME=${GMAIL_FROM_NAME}
```

### Microsoft 365/Outlook

```env
# Outlook SMTP Configuration
OUTLOOK_USERNAME=your-email@outlook.com
OUTLOOK_PASSWORD=your-password
OUTLOOK_FROM_ADDRESS=noreply@yourdomain.com
OUTLOOK_FROM_NAME="Academic System"

# Use these in your main mail config
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=${OUTLOOK_USERNAME}
MAIL_PASSWORD=${OUTLOOK_PASSWORD}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=${OUTLOOK_FROM_ADDRESS}
MAIL_FROM_NAME=${OUTLOOK_FROM_NAME}
```

### SendGrid

```env
# SendGrid SMTP Configuration
SENDGRID_API_KEY=your-sendgrid-api-key
SENDGRID_FROM_ADDRESS=noreply@yourdomain.com
SENDGRID_FROM_NAME="Academic System"

# Use these in your main mail config
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=${SENDGRID_API_KEY}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=${SENDGRID_FROM_ADDRESS}
MAIL_FROM_NAME=${SENDGRID_FROM_NAME}
```

### Amazon SES

```env
# Amazon SES SMTP Configuration
SES_HOST=email-smtp.us-east-1.amazonaws.com
SES_USERNAME=your-ses-smtp-username
SES_PASSWORD=your-ses-smtp-password
SES_FROM_ADDRESS=noreply@yourdomain.com
SES_FROM_NAME="Academic System"

# Use these in your main mail config
MAIL_MAILER=smtp
MAIL_HOST=${SES_HOST}
MAIL_PORT=587
MAIL_USERNAME=${SES_USERNAME}
MAIL_PASSWORD=${SES_PASSWORD}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=${SES_FROM_ADDRESS}
MAIL_FROM_NAME=${SES_FROM_NAME}
```

## Production Examples

### High-Volume Production Environment

```env
# High-volume production configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Academic Management System"

# Queue configuration for high volume
EMAIL_QUEUE_CONNECTION=redis
EMAIL_QUEUE_NAME=high-priority-emails
EMAIL_MAX_TRIES=5
EMAIL_TIMEOUT=120

# Rate limiting for high volume
EMAIL_MAX_PER_MINUTE=200
EMAIL_MAX_PER_HOUR=5000
EMAIL_MAX_PER_DAY=100000

# Enhanced monitoring
EMAIL_MONITORING_ENABLED=true
EMAIL_LOG_RETENTION_DAYS=180
EMAIL_ALERT_RECIPIENTS=admin@yourdomain.com,ops@yourdomain.com
```

### Small Institution Environment

```env
# Small institution configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=system@yourinstitution.edu
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourinstitution.edu
MAIL_FROM_NAME="Your Institution Academic System"

# Conservative rate limiting
EMAIL_MAX_PER_MINUTE=10
EMAIL_MAX_PER_HOUR=300
EMAIL_MAX_PER_DAY=2000

# Basic monitoring
EMAIL_MONITORING_ENABLED=true
EMAIL_LOG_RETENTION_DAYS=90
EMAIL_ALERT_RECIPIENTS=admin@yourinstitution.edu
```

### Development/Testing Environment

```env
# Development configuration
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=test@example.com
MAIL_FROM_NAME="Test Academic System"

# Disable notifications in development
NOTIFY_COURSE_REGISTRATION=false
NOTIFY_GRADE_PUBLISHED=false
NOTIFY_ACADEMIC_HOLD=false
NOTIFY_ENROLLMENT_CONFIRMED=false
NOTIFY_ASSESSMENT_DEADLINE=false

# Disable rate limiting in development
EMAIL_RATE_LIMITING_ENABLED=false
```

## Security Best Practices

1. **Use App-Specific Passwords**: For Gmail and other providers, use app-specific passwords instead of your main account password.

2. **Encrypt Sensitive Variables**: Store sensitive variables like passwords in encrypted form or use a secrets management system.

3. **Limit Access**: Restrict access to environment files containing email credentials.

4. **Regular Rotation**: Regularly rotate SMTP passwords and API keys.

5. **Monitor Usage**: Keep track of email sending patterns to detect unusual activity.

6. **Use HTTPS**: Always use encrypted connections (TLS/SSL) for SMTP.

## Troubleshooting

### Common Issues

1. **Authentication Failed**: Check username/password and ensure app-specific passwords are used where required.

2. **Connection Timeout**: Increase `MAIL_TIMEOUT` value or check firewall settings.

3. **Rate Limiting**: Adjust rate limiting settings or upgrade your email service plan.

4. **Queue Backlog**: Increase queue workers or optimize email content.

### Debug Settings

```env
# Enable debug logging for email issues
LOG_LEVEL=debug
MAIL_LOG_CHANNEL=single

# Test email configuration
MAIL_MAILER=log  # Temporarily use log driver for testing
```

## Validation

Use the following command to validate your email configuration:

```bash
php artisan email:test-configuration
```

This command will:
- Test SMTP connection
- Validate environment variables
- Check rate limiting settings
- Verify queue configuration
- Test template rendering
