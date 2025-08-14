# Email System Deployment Checklist

This checklist ensures proper deployment and configuration of the SMTP email system in production environments.

## Pre-Deployment Checklist

### 1. Environment Configuration ✅

- [ ] **SMTP Provider Setup**
  - [ ] Choose email provider (Gmail, Outlook, SendGrid, etc.)
  - [ ] Create email account or obtain API credentials
  - [ ] Configure SPF, DKIM, and DMARC records (if using custom domain)
  - [ ] Test SMTP credentials manually

- [ ] **Environment Variables**
  - [ ] Copy `.env.example` to `.env` (or use production template)
  - [ ] Set all required email environment variables
  - [ ] Configure SMTP settings (`MAIL_HOST`, `MAIL_PORT`, etc.)
  - [ ] Set secure passwords and API keys
  - [ ] Configure rate limiting settings
  - [ ] Set monitoring and alerting recipients

- [ ] **Security Configuration**
  - [ ] Use TLS/SSL encryption (`MAIL_ENCRYPTION=tls`)
  - [ ] Set strong, unique passwords
  - [ ] Enable rate limiting (`EMAIL_RATE_LIMITING_ENABLED=true`)
  - [ ] Configure allowed/blocked domains if needed
  - [ ] Set `APP_DEBUG=false` in production

### 2. Database Preparation ✅

- [ ] **Run Migrations**
  ```bash
  php artisan migrate --force
  ```

- [ ] **Verify Tables**
  - [ ] `email_configurations` table exists
  - [ ] `email_templates` table exists
  - [ ] `email_logs` table exists
  - [ ] `user_email_preferences` table exists

- [ ] **Seed Default Data**
  ```bash
  php artisan db:seed --class=EmailTemplateSeeder
  ```

### 3. Queue Configuration ✅

- [ ] **Queue Setup**
  - [ ] Configure queue connection (Redis recommended)
  - [ ] Set `QUEUE_CONNECTION=redis` (not `sync`)
  - [ ] Configure Redis connection settings
  - [ ] Test Redis connectivity

- [ ] **Queue Workers**
  - [ ] Set up queue worker processes
  - [ ] Configure supervisor or systemd for worker management
  - [ ] Test queue worker startup
  - [ ] Configure worker restart policies

### 4. File Permissions ✅

- [ ] **Storage Directories**
  - [ ] Ensure `storage/logs` is writable
  - [ ] Ensure `storage/app` is writable
  - [ ] Create `storage/app/email-logs` directory if needed
  - [ ] Set proper ownership (www-data or appropriate user)

- [ ] **Log Rotation**
  - [ ] Configure log rotation for email logs
  - [ ] Set up cleanup for old email logs
  - [ ] Configure disk space monitoring

## Deployment Process

### 1. Code Deployment ✅

- [ ] **Deploy Application Code**
  ```bash
  git pull origin main
  composer install --no-dev --optimize-autoloader
  npm run build
  ```

- [ ] **Laravel Optimization**
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  ```

### 2. Email System Validation ✅

- [ ] **Run Validation Script**
  ```bash
  ./scripts/validate-email-deployment.sh
  ```

- [ ] **Test Email Configuration**
  ```bash
  php artisan email:test-configuration --to=admin@yourdomain.com --verbose
  ```

- [ ] **Verify Services**
  - [ ] Check all email services are resolvable
  - [ ] Verify SMTP connection
  - [ ] Test queue processing
  - [ ] Validate email templates

### 3. Production Testing ✅

- [ ] **Send Test Emails**
  - [ ] Send single test email
  - [ ] Test bulk email functionality
  - [ ] Verify email templates render correctly
  - [ ] Test notification system

- [ ] **Monitor Initial Performance**
  - [ ] Check email queue processing
  - [ ] Monitor email delivery rates
  - [ ] Verify logging is working
  - [ ] Test error handling

## Post-Deployment Verification

### 1. Functional Testing ✅

- [ ] **Email Sending**
  - [ ] Single email sending works
  - [ ] Bulk email sending works
  - [ ] Email templates render correctly
  - [ ] Attachments work (if applicable)

- [ ] **Automated Notifications**
  - [ ] Course registration notifications
  - [ ] Grade published notifications
  - [ ] Academic hold notifications
  - [ ] Assessment deadline reminders

- [ ] **User Preferences**
  - [ ] Users can manage email preferences
  - [ ] Opt-out functionality works
  - [ ] Preference enforcement works

### 2. Performance Monitoring ✅

- [ ] **Queue Performance**
  - [ ] Queue workers are processing emails
  - [ ] No significant queue backlog
  - [ ] Failed jobs are being retried appropriately
  - [ ] Queue metrics are being collected

- [ ] **Email Delivery**
  - [ ] Emails are being delivered successfully
  - [ ] Delivery times are acceptable
  - [ ] Bounce handling is working
  - [ ] Rate limiting is effective

### 3. Monitoring Setup ✅

- [ ] **Logging**
  - [ ] Email logs are being created
  - [ ] Error logs are being captured
  - [ ] Log rotation is working
  - [ ] Log monitoring is set up

- [ ] **Alerting**
  - [ ] High failure rate alerts
  - [ ] Queue backlog alerts
  - [ ] Daily limit usage alerts
  - [ ] SMTP connection failure alerts

## Maintenance Tasks

### Daily ✅

- [ ] **Monitor Email Metrics**
  - [ ] Check daily sending volume
  - [ ] Review failure rates
  - [ ] Monitor queue backlog
  - [ ] Check error logs

### Weekly ✅

- [ ] **Performance Review**
  - [ ] Analyze email delivery performance
  - [ ] Review bounce rates
  - [ ] Check template usage
  - [ ] Monitor user preferences changes

### Monthly ✅

- [ ] **System Maintenance**
  - [ ] Clean up old email logs
  - [ ] Review and update email templates
  - [ ] Update SMTP credentials if needed
  - [ ] Review rate limiting settings

### Quarterly ✅

- [ ] **Security Review**
  - [ ] Rotate SMTP passwords
  - [ ] Review access permissions
  - [ ] Update security configurations
  - [ ] Audit email usage patterns

## Troubleshooting Guide

### Common Issues

#### SMTP Connection Failures
```bash
# Test SMTP connection
php artisan email:test-configuration --smtp-only --verbose

# Check firewall settings
telnet smtp.yourdomain.com 587

# Verify credentials
# Check provider-specific requirements (app passwords, etc.)
```

#### Queue Not Processing
```bash
# Check queue workers
ps aux | grep queue:work

# Restart queue workers
php artisan queue:restart

# Check Redis connection
redis-cli ping
```

#### High Bounce Rates
- Review email content for spam triggers
- Check SPF/DKIM/DMARC records
- Verify sender reputation
- Review recipient list quality

#### Performance Issues
- Monitor queue worker count
- Check Redis memory usage
- Review email template complexity
- Optimize database queries

### Emergency Procedures

#### Stop Email Sending
```bash
# Put application in maintenance mode
php artisan down

# Stop queue workers
supervisorctl stop laravel-worker:*

# Clear email queue (if necessary)
php artisan queue:clear emails
```

#### Restore Email Service
```bash
# Start queue workers
supervisorctl start laravel-worker:*

# Bring application back online
php artisan up

# Test email functionality
php artisan email:test-configuration --to=admin@yourdomain.com
```

## Rollback Plan

### If Email System Fails

1. **Immediate Actions**
   - [ ] Switch to log driver: `MAIL_MAILER=log`
   - [ ] Stop queue workers to prevent further failures
   - [ ] Alert administrators about the issue

2. **Investigation**
   - [ ] Check email logs for error patterns
   - [ ] Verify SMTP provider status
   - [ ] Test network connectivity
   - [ ] Review recent configuration changes

3. **Recovery**
   - [ ] Fix identified issues
   - [ ] Test configuration with validation script
   - [ ] Gradually restore email functionality
   - [ ] Monitor closely for continued issues

## Success Criteria

The email system deployment is considered successful when:

- [ ] All validation tests pass
- [ ] Test emails are delivered successfully
- [ ] Automated notifications are working
- [ ] Queue processing is stable
- [ ] Monitoring and alerting are functional
- [ ] Performance meets requirements
- [ ] Security measures are in place
- [ ] Documentation is complete and accessible

## Sign-off

- [ ] **Technical Lead**: Email system functionality verified
- [ ] **System Administrator**: Infrastructure and monitoring confirmed
- [ ] **Security Officer**: Security configurations approved
- [ ] **Project Manager**: Deployment completed successfully

---

**Deployment Date**: _______________  
**Deployed By**: _______________  
**Reviewed By**: _______________  
**Environment**: _______________
