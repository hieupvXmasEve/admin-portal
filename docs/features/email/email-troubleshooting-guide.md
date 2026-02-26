# Email System Troubleshooting Guide

This guide provides comprehensive troubleshooting procedures for common email system issues and their solutions.

## Table of Contents

- [Quick Diagnostics](#quick-diagnostics)
- [SMTP Connection Issues](#smtp-connection-issues)
- [Email Delivery Problems](#email-delivery-problems)
- [Queue Processing Issues](#queue-processing-issues)
- [Template Rendering Problems](#template-rendering-problems)
- [Performance Issues](#performance-issues)
- [Security and Authentication](#security-and-authentication)
- [Database and Storage Issues](#database-and-storage-issues)
- [Monitoring and Alerting](#monitoring-and-alerting)
- [Emergency Procedures](#emergency-procedures)
- [Preventive Maintenance](#preventive-maintenance)

## Quick Diagnostics

### System Health Check

Run the comprehensive validation script to quickly identify issues:

```bash
# Run full email system validation
./scripts/validate-email-deployment.sh

# Run with verbose output for detailed information
./scripts/validate-email-deployment.sh --verbose
```

### Basic Email Configuration Test

```bash
# Test email configuration
php artisan email:test-configuration --verbose

# Test SMTP connection only
php artisan email:test-configuration --smtp-only

# Send test email
php artisan email:test-configuration --to=admin@yourdomain.com
```

### Quick Status Checks

```bash
# Check queue status
php artisan queue:work --verbose --timeout=10

# Check failed jobs
php artisan queue:failed

# Check email logs
tail -f storage/logs/laravel.log | grep -i email

# Check Redis connection (if using Redis queue)
redis-cli ping
```

## SMTP Connection Issues

### Symptoms
- Emails not being sent
- Connection timeout errors
- Authentication failures
- SSL/TLS handshake errors

### Diagnostic Steps

#### 1. Test SMTP Connection Manually

```bash
# Test basic connectivity
telnet smtp.gmail.com 587

# Test with OpenSSL for TLS
openssl s_client -connect smtp.gmail.com:587 -starttls smtp
```

#### 2. Verify SMTP Configuration

```bash
# Check current mail configuration
php artisan tinker
>>> config('mail.mailers.smtp')
>>> config('mail.from')
```

#### 3. Test with Different Settings

```bash
# Test with different encryption
MAIL_ENCRYPTION=ssl php artisan email:test-configuration --smtp-only

# Test with different port
MAIL_PORT=465 php artisan email:test-configuration --smtp-only
```

### Common Solutions

#### Authentication Failures

**Problem**: SMTP authentication failed

**Solutions**:
1. **Gmail**: Use App-Specific Password
   ```bash
   # Generate app password at: https://myaccount.google.com/apppasswords
   MAIL_PASSWORD="your-16-character-app-password"
   ```

2. **Outlook/Office 365**: Enable SMTP AUTH
   ```bash
   # Ensure SMTP AUTH is enabled in Office 365 admin center
   MAIL_HOST=smtp.office365.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   ```

3. **Two-Factor Authentication**: Disable or use app passwords

#### Connection Timeouts

**Problem**: Connection timeout errors

**Solutions**:
1. **Increase Timeout**:
   ```env
   MAIL_TIMEOUT=120
   ```

2. **Check Firewall**:
   ```bash
   # Test port connectivity
   nc -zv smtp.gmail.com 587
   
   # Check iptables rules
   sudo iptables -L | grep -i smtp
   ```

3. **Try Alternative Ports**:
   ```env
   # Try port 465 with SSL
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   
   # Try port 25 (if not blocked)
   MAIL_PORT=25
   MAIL_ENCRYPTION=null
   ```

#### SSL/TLS Issues

**Problem**: SSL certificate verification failed

**Solutions**:
1. **Update CA Certificates**:
   ```bash
   # Ubuntu/Debian
   sudo apt-get update && sudo apt-get install ca-certificates
   
   # CentOS/RHEL
   sudo yum update ca-certificates
   ```

2. **Disable SSL Verification** (not recommended for production):
   ```env
   MAIL_VERIFY_PEER=false
   ```

3. **Use Correct Encryption Method**:
   ```env
   # For port 587
   MAIL_ENCRYPTION=tls
   
   # For port 465
   MAIL_ENCRYPTION=ssl
   ```

### Provider-Specific Issues

#### Gmail Issues

```bash
# Common Gmail settings
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password  # Not your regular password!
```

**Troubleshooting**:
- Enable "Less secure app access" (not recommended)
- Use App-Specific Passwords (recommended)
- Check Google Account security settings

#### Outlook/Office 365 Issues

```bash
# Common Outlook settings
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
```

**Troubleshooting**:
- Ensure SMTP AUTH is enabled
- Check conditional access policies
- Verify account is not blocked

#### SendGrid Issues

```bash
# SendGrid settings
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

**Troubleshooting**:
- Verify API key permissions
- Check sender authentication
- Review SendGrid activity logs

## Email Delivery Problems

### Symptoms
- Emails sent but not received
- High bounce rates
- Emails going to spam
- Delayed delivery

### Diagnostic Steps

#### 1. Check Email Logs

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep -i "email\|mail"

# Check email-specific logs
php artisan tinker
>>> \App\Models\EmailLog::where('status', 'failed')->latest()->take(10)->get()
```

#### 2. Verify Email Status

```bash
# Check specific email status
php artisan tinker
>>> $email = \App\Models\EmailLog::find(123);
>>> echo $email->status;
>>> echo $email->error_message;
```

#### 3. Test Email Delivery

```bash
# Send test email to different providers
php artisan email:test-configuration --to=test@gmail.com
php artisan email:test-configuration --to=test@outlook.com
php artisan email:test-configuration --to=test@yahoo.com
```

### Common Solutions

#### High Bounce Rates

**Problem**: Many emails bouncing back

**Solutions**:
1. **Clean Email Lists**:
   ```php
   // Remove invalid email addresses
   $invalidEmails = \App\Models\EmailLog::where('status', 'bounced')
       ->pluck('recipient')
       ->unique();
   
   // Update user records or create suppression list
   ```

2. **Validate Email Addresses**:
   ```php
   // Add email validation
   use Illuminate\Support\Facades\Validator;
   
   $validator = Validator::make(['email' => $email], [
       'email' => 'required|email:rfc,dns'
   ]);
   ```

3. **Implement Bounce Handling**:
   ```php
   // Create bounce handler
   public function handleBounce($bounceData)
   {
       $email = $bounceData['recipient'];
       
       // Mark email as bounced
       EmailLog::where('recipient', $email)
           ->update(['status' => 'bounced']);
       
       // Add to suppression list
       SuppressionList::create(['email' => $email]);
   }
   ```

#### Emails Going to Spam

**Problem**: Emails ending up in spam folders

**Solutions**:
1. **Configure SPF Record**:
   ```dns
   # Add to DNS
   yourdomain.com. IN TXT "v=spf1 include:_spf.google.com ~all"
   ```

2. **Set up DKIM**:
   ```dns
   # Add DKIM record to DNS
   selector._domainkey.yourdomain.com. IN TXT "v=DKIM1; k=rsa; p=your-public-key"
   ```

3. **Configure DMARC**:
   ```dns
   # Add DMARC record
   _dmarc.yourdomain.com. IN TXT "v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com"
   ```

4. **Improve Email Content**:
   - Avoid spam trigger words
   - Use proper HTML structure
   - Include plain text version
   - Add unsubscribe links

#### Delayed Delivery

**Problem**: Emails taking too long to deliver

**Solutions**:
1. **Check Queue Processing**:
   ```bash
   # Monitor queue
   php artisan queue:work --verbose
   
   # Check queue size
   php artisan queue:size
   ```

2. **Increase Queue Workers**:
   ```bash
   # Start multiple workers
   php artisan queue:work --queue=emails &
   php artisan queue:work --queue=emails &
   php artisan queue:work --queue=emails &
   ```

3. **Optimize SMTP Settings**:
   ```env
   # Reduce timeout
   MAIL_TIMEOUT=30
   
   # Use connection pooling if available
   MAIL_POOL_SIZE=5
   ```

## Queue Processing Issues

### Symptoms
- Emails stuck in queue
- Queue workers not processing jobs
- High memory usage by workers
- Failed jobs accumulating

### Diagnostic Steps

#### 1. Check Queue Status

```bash
# Check queue size
php artisan queue:size

# List failed jobs
php artisan queue:failed

# Monitor queue processing
php artisan queue:work --verbose --timeout=60
```

#### 2. Check Worker Processes

```bash
# Check running workers
ps aux | grep "queue:work"

# Check supervisor status (if using supervisor)
sudo supervisorctl status
```

#### 3. Check Redis Connection (if using Redis)

```bash
# Test Redis connection
redis-cli ping

# Check Redis memory usage
redis-cli info memory

# Monitor Redis commands
redis-cli monitor
```

### Common Solutions

#### Queue Workers Not Running

**Problem**: No queue workers processing jobs

**Solutions**:
1. **Start Queue Workers**:
   ```bash
   # Start worker manually
   php artisan queue:work --verbose
   
   # Start with specific queue
   php artisan queue:work --queue=emails
   ```

2. **Set up Supervisor** (recommended for production):
   ```ini
   # /etc/supervisor/conf.d/laravel-worker.conf
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/your/app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=3
   redirect_stderr=true
   stdout_logfile=/path/to/your/app/storage/logs/worker.log
   stopwaitsecs=3600
   ```

3. **Restart Workers**:
   ```bash
   # Restart all workers
   php artisan queue:restart
   
   # Restart supervisor workers
   sudo supervisorctl restart laravel-worker:*
   ```

#### High Memory Usage

**Problem**: Queue workers consuming too much memory

**Solutions**:
1. **Set Memory Limits**:
   ```bash
   # Start worker with memory limit
   php artisan queue:work --memory=512
   ```

2. **Restart Workers Periodically**:
   ```bash
   # Restart after processing 1000 jobs
   php artisan queue:work --max-jobs=1000
   
   # Restart after 1 hour
   php artisan queue:work --max-time=3600
   ```

3. **Optimize Email Processing**:
   ```php
   // In email job class
   public function handle()
   {
       // Process email
       $this->sendEmail();
       
       // Clear memory
       gc_collect_cycles();
   }
   ```

#### Failed Jobs Accumulating

**Problem**: Many failed jobs in the queue

**Solutions**:
1. **Retry Failed Jobs**:
   ```bash
   # Retry all failed jobs
   php artisan queue:retry all
   
   # Retry specific job
   php artisan queue:retry 123
   ```

2. **Clear Failed Jobs**:
   ```bash
   # Clear all failed jobs
   php artisan queue:flush
   
   # Clear specific failed job
   php artisan queue:forget 123
   ```

3. **Investigate Failure Causes**:
   ```bash
   # Check failed job details
   php artisan queue:failed
   
   # Check logs for error patterns
   grep -i "failed" storage/logs/laravel.log
   ```

## Template Rendering Problems

### Symptoms
- Templates not rendering correctly
- Missing variables in emails
- HTML formatting issues
- Template compilation errors

### Diagnostic Steps

#### 1. Test Template Rendering

```bash
# Test template in Tinker
php artisan tinker
>>> $template = \App\Models\EmailTemplate::find(1);
>>> $service = app(\App\Services\EmailTemplateService::class);
>>> $rendered = $service->renderTemplate($template->id, ['name' => 'Test']);
>>> echo $rendered['html_content'];
```

#### 2. Check Template Variables

```php
// Verify template variables
$template = EmailTemplate::find(1);
$variables = $template->variables;
$content = $template->html_content;

// Check for undefined variables
preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
$usedVariables = $matches[1];
$missingVariables = array_diff($usedVariables, $variables);
```

### Common Solutions

#### Missing Variables

**Problem**: Template variables not being replaced

**Solutions**:
1. **Check Variable Names**:
   ```php
   // Ensure variable names match exactly
   $variables = [
       'student_name' => 'John Doe',  // Not 'studentName' or 'name'
       'course_name' => 'CS 101'      // Not 'courseName' or 'course'
   ];
   ```

2. **Add Default Values**:
   ```php
   // In template service
   public function renderTemplate($templateId, $variables = [])
   {
       $defaults = [
           'student_name' => 'Student',
           'institution_name' => config('app.name'),
           'contact_email' => config('mail.from.address')
       ];
       
       $variables = array_merge($defaults, $variables);
       
       // Continue with rendering...
   }
   ```

3. **Validate Variables Before Rendering**:
   ```php
   public function validateTemplateVariables($template, $variables)
   {
       $requiredVars = $template->variables;
       $missingVars = array_diff($requiredVars, array_keys($variables));
       
       if (!empty($missingVars)) {
           throw new \Exception('Missing variables: ' . implode(', ', $missingVars));
       }
   }
   ```

#### HTML Formatting Issues

**Problem**: Emails not displaying correctly in email clients

**Solutions**:
1. **Use Inline CSS**:
   ```html
   <!-- Instead of external CSS -->
   <div style="color: #333; font-family: Arial, sans-serif;">
       Content here
   </div>
   ```

2. **Use Email-Safe HTML**:
   ```html
   <!-- Use tables for layout -->
   <table width="100%" cellpadding="0" cellspacing="0">
       <tr>
           <td style="padding: 20px;">
               Content here
           </td>
       </tr>
   </table>
   ```

3. **Test Across Email Clients**:
   ```bash
   # Use email testing services
   # - Litmus
   # - Email on Acid
   # - Mail Tester
   ```

#### Template Compilation Errors

**Problem**: Template fails to compile

**Solutions**:
1. **Check Template Syntax**:
   ```php
   // Validate template syntax
   try {
       $blade = app('view')->getEngineResolver()->resolve('blade');
       $compiled = $blade->get($templatePath);
   } catch (\Exception $e) {
       // Handle compilation error
       Log::error('Template compilation failed: ' . $e->getMessage());
   }
   ```

2. **Escape Special Characters**:
   ```html
   <!-- Properly escape variables -->
   <p>Hello {{ $student_name }}</p>
   
   <!-- For raw HTML (be careful) -->
   <div>{!! $html_content !!}</div>
   ```

## Performance Issues

### Symptoms
- Slow email sending
- High server load during email operations
- Database performance degradation
- Memory exhaustion

### Diagnostic Steps

#### 1. Monitor Performance Metrics

```bash
# Check system resources
top
htop
iostat -x 1

# Check database performance
mysql -e "SHOW PROCESSLIST;"
mysql -e "SHOW ENGINE INNODB STATUS\G"

# Check Redis performance (if using Redis)
redis-cli --latency
redis-cli info stats
```

#### 2. Profile Email Operations

```php
// Add timing to email operations
$start = microtime(true);

// Send email
$emailService->sendEmail($data);

$duration = microtime(true) - $start;
Log::info("Email sent in {$duration} seconds");
```

### Common Solutions

#### Slow Email Sending

**Problem**: Individual emails taking too long to send

**Solutions**:
1. **Optimize SMTP Connection**:
   ```env
   # Reduce timeout
   MAIL_TIMEOUT=30
   
   # Use persistent connections if available
   MAIL_PERSISTENT=true
   ```

2. **Use Connection Pooling**:
   ```php
   // Implement connection pooling
   class SmtpConnectionPool
   {
       private static $connections = [];
       
       public static function getConnection($config)
       {
           $key = md5(serialize($config));
           
           if (!isset(self::$connections[$key])) {
               self::$connections[$key] = new SmtpConnection($config);
           }
           
           return self::$connections[$key];
       }
   }
   ```

3. **Batch Email Operations**:
   ```php
   // Send emails in batches
   $recipients = collect($allRecipients)->chunk(100);
   
   foreach ($recipients as $batch) {
       dispatch(new SendBulkEmailJob($batch));
   }
   ```

#### High Database Load

**Problem**: Email operations causing database performance issues

**Solutions**:
1. **Optimize Email Log Queries**:
   ```php
   // Add database indexes
   Schema::table('email_logs', function (Blueprint $table) {
       $table->index(['status', 'created_at']);
       $table->index(['recipient', 'created_at']);
       $table->index(['template_id', 'created_at']);
   });
   ```

2. **Implement Log Rotation**:
   ```php
   // Clean up old email logs
   EmailLog::where('created_at', '<', now()->subDays(90))->delete();
   ```

3. **Use Read Replicas**:
   ```php
   // Configure read replica for email logs
   'email_logs' => [
       'driver' => 'mysql',
       'read' => [
           'host' => ['192.168.1.2'],
       ],
       'write' => [
           'host' => ['192.168.1.1'],
       ],
       // ... other config
   ];
   ```

#### Memory Issues

**Problem**: Email operations consuming too much memory

**Solutions**:
1. **Process Large Lists in Chunks**:
   ```php
   // Instead of loading all recipients at once
   User::where('active', true)->chunk(1000, function ($users) {
       foreach ($users as $user) {
           dispatch(new SendEmailJob($user));
       }
   });
   ```

2. **Optimize Template Caching**:
   ```php
   // Cache compiled templates
   public function renderTemplate($templateId, $variables)
   {
       $cacheKey = "template.{$templateId}." . md5(serialize($variables));
       
       return Cache::remember($cacheKey, 3600, function () use ($templateId, $variables) {
           return $this->compileTemplate($templateId, $variables);
       });
   }
   ```

3. **Use Streaming for Large Operations**:
   ```php
   // Stream large result sets
   User::where('active', true)->cursor()->each(function ($user) {
       dispatch(new SendEmailJob($user));
   });
   ```

## Security and Authentication

### Symptoms
- Unauthorized access to email system
- Credential theft or exposure
- Email spoofing or phishing
- Data breaches through email

### Diagnostic Steps

#### 1. Check Access Logs

```bash
# Check web server access logs
tail -f /var/log/nginx/access.log | grep -i email

# Check application logs for authentication
grep -i "auth\|login" storage/logs/laravel.log
```

#### 2. Verify Permissions

```php
// Check user permissions
$user = auth()->user();
$canSendEmails = $user->can('send-emails');
$canManageTemplates = $user->can('manage-email-templates');
```

#### 3. Audit Email Activity

```php
// Check for suspicious email activity
$suspiciousActivity = EmailLog::where('created_at', '>', now()->subHours(24))
    ->where('sender', 'not like', '%@yourdomain.com')
    ->get();
```

### Common Solutions

#### Secure SMTP Credentials

**Problem**: SMTP credentials exposed or compromised

**Solutions**:
1. **Use Environment Variables**:
   ```env
   # Never commit credentials to version control
   MAIL_PASSWORD=your-secure-password
   ```

2. **Encrypt Credentials in Database**:
   ```php
   // Encrypt SMTP passwords
   protected $casts = [
       'password' => 'encrypted',
   ];
   ```

3. **Use Secrets Management**:
   ```php
   // Use AWS Secrets Manager, Azure Key Vault, etc.
   $password = app('secrets')->get('smtp-password');
   ```

4. **Rotate Credentials Regularly**:
   ```bash
   # Set up automated credential rotation
   php artisan email:rotate-credentials
   ```

#### Implement Access Controls

**Problem**: Unauthorized access to email functionality

**Solutions**:
1. **Role-Based Permissions**:
   ```php
   // Define email permissions
   Gate::define('send-bulk-email', function ($user) {
       return $user->hasRole('admin') || $user->hasRole('email-manager');
   });
   
   Gate::define('manage-email-templates', function ($user) {
       return $user->hasRole('admin');
   });
   ```

2. **API Rate Limiting**:
   ```php
   // Limit email API requests
   Route::middleware(['auth:sanctum', 'throttle:email'])
       ->group(function () {
           Route::post('/email/send', [EmailController::class, 'send']);
       });
   ```

3. **IP Whitelisting**:
   ```php
   // Restrict email management to specific IPs
   Route::middleware(['auth', 'ip.whitelist:192.168.1.0/24'])
       ->group(function () {
           Route::resource('email-configs', EmailConfigController::class);
       });
   ```

#### Prevent Email Spoofing

**Problem**: Emails being spoofed or forged

**Solutions**:
1. **Implement SPF**:
   ```dns
   yourdomain.com. IN TXT "v=spf1 include:_spf.google.com ~all"
   ```

2. **Set up DKIM**:
   ```php
   // Configure DKIM signing
   'dkim' => [
       'domain' => 'yourdomain.com',
       'selector' => 'default',
       'private_key' => storage_path('dkim/private.key'),
   ],
   ```

3. **Configure DMARC**:
   ```dns
   _dmarc.yourdomain.com. IN TXT "v=DMARC1; p=reject; rua=mailto:dmarc@yourdomain.com"
   ```

## Database and Storage Issues

### Symptoms
- Email logs not being saved
- Template storage failures
- Database connection errors
- Storage space issues

### Diagnostic Steps

#### 1. Check Database Connection

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check database status
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
```

#### 2. Check Storage Space

```bash
# Check disk usage
df -h

# Check specific directories
du -sh storage/
du -sh storage/logs/
du -sh storage/app/
```

#### 3. Verify Table Structure

```bash
# Check email tables
php artisan tinker
>>> Schema::hasTable('email_logs');
>>> Schema::hasTable('email_templates');
>>> Schema::hasTable('email_configurations');
```

### Common Solutions

#### Database Connection Issues

**Problem**: Cannot connect to database

**Solutions**:
1. **Check Database Configuration**:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

2. **Test Connection**:
   ```bash
   # Test MySQL connection
   mysql -h 127.0.0.1 -u your_username -p your_database
   
   # Test from application
   php artisan tinker
   >>> DB::select('SELECT 1');
   ```

3. **Check Connection Limits**:
   ```sql
   -- Check MySQL connection limits
   SHOW VARIABLES LIKE 'max_connections';
   SHOW STATUS LIKE 'Threads_connected';
   ```

#### Storage Space Issues

**Problem**: Running out of disk space

**Solutions**:
1. **Clean Up Old Logs**:
   ```bash
   # Remove old Laravel logs
   find storage/logs -name "*.log" -mtime +30 -delete
   
   # Clean up email logs
   php artisan email:cleanup-logs --days=90
   ```

2. **Implement Log Rotation**:
   ```bash
   # Configure logrotate
   # /etc/logrotate.d/laravel
   /path/to/your/app/storage/logs/*.log {
       daily
       missingok
       rotate 52
       compress
       delaycompress
       notifempty
       create 644 www-data www-data
   }
   ```

3. **Archive Old Data**:
   ```php
   // Archive old email logs
   $oldLogs = EmailLog::where('created_at', '<', now()->subMonths(6))->get();
   
   // Export to file
   $csv = $oldLogs->toCsv();
   Storage::put('archives/email_logs_' . date('Y-m-d') . '.csv', $csv);
   
   // Delete from database
   EmailLog::where('created_at', '<', now()->subMonths(6))->delete();
   ```

## Monitoring and Alerting

### Setting Up Monitoring

#### 1. Email Metrics Dashboard

```php
// Create email metrics endpoint
Route::get('/admin/email/metrics', function () {
    return [
        'today' => [
            'sent' => EmailLog::whereDate('created_at', today())->count(),
            'delivered' => EmailLog::whereDate('created_at', today())->where('status', 'delivered')->count(),
            'failed' => EmailLog::whereDate('created_at', today())->where('status', 'failed')->count(),
        ],
        'queue_size' => Queue::size('emails'),
        'failed_jobs' => Queue::size('failed'),
    ];
});
```

#### 2. Health Check Endpoint

```php
// Email system health check
Route::get('/health/email', function () {
    $health = [
        'status' => 'healthy',
        'checks' => []
    ];
    
    // Check SMTP connection
    try {
        Mail::getSwiftMailer()->getTransport()->start();
        $health['checks']['smtp'] = 'ok';
    } catch (Exception $e) {
        $health['checks']['smtp'] = 'failed';
        $health['status'] = 'unhealthy';
    }
    
    // Check queue
    $queueSize = Queue::size('emails');
    $health['checks']['queue'] = $queueSize < 1000 ? 'ok' : 'warning';
    
    // Check database
    try {
        EmailLog::count();
        $health['checks']['database'] = 'ok';
    } catch (Exception $e) {
        $health['checks']['database'] = 'failed';
        $health['status'] = 'unhealthy';
    }
    
    return response()->json($health);
});
```

#### 3. Automated Alerts

```php
// Email system alerts
class EmailSystemMonitor
{
    public function checkFailureRate()
    {
        $total = EmailLog::whereDate('created_at', today())->count();
        $failed = EmailLog::whereDate('created_at', today())->where('status', 'failed')->count();
        
        $failureRate = $total > 0 ? ($failed / $total) * 100 : 0;
        
        if ($failureRate > 10) {
            $this->sendAlert("High email failure rate: {$failureRate}%");
        }
    }
    
    public function checkQueueBacklog()
    {
        $queueSize = Queue::size('emails');
        
        if ($queueSize > 1000) {
            $this->sendAlert("Email queue backlog: {$queueSize} jobs");
        }
    }
    
    private function sendAlert($message)
    {
        // Send alert to administrators
        Mail::to(config('email.alert_recipients'))
            ->send(new SystemAlert($message));
    }
}
```

## Emergency Procedures

### Email System Failure

#### 1. Immediate Response

```bash
# Stop email processing
php artisan down --message="Email system maintenance"

# Stop queue workers
sudo supervisorctl stop laravel-worker:*

# Switch to log driver (emails won't be sent)
sed -i 's/MAIL_MAILER=smtp/MAIL_MAILER=log/' .env
```

#### 2. Investigate Issue

```bash
# Check recent logs
tail -n 100 storage/logs/laravel.log

# Check failed jobs
php artisan queue:failed

# Test SMTP connection
php artisan email:test-configuration --smtp-only
```

#### 3. Recovery Steps

```bash
# Fix identified issues
# Update configuration, restart services, etc.

# Test email functionality
php artisan email:test-configuration --to=admin@yourdomain.com

# Switch back to SMTP
sed -i 's/MAIL_MAILER=log/MAIL_MAILER=smtp/' .env

# Start queue workers
sudo supervisorctl start laravel-worker:*

# Bring application back online
php artisan up
```

### Data Recovery

#### 1. Backup Email Logs

```bash
# Create backup of email logs
mysqldump -u username -p database_name email_logs > email_logs_backup.sql

# Backup email templates
mysqldump -u username -p database_name email_templates > email_templates_backup.sql
```

#### 2. Restore from Backup

```bash
# Restore email logs
mysql -u username -p database_name < email_logs_backup.sql

# Restore email templates
mysql -u username -p database_name < email_templates_backup.sql
```

## Preventive Maintenance

### Daily Tasks

```bash
#!/bin/bash
# Daily email system maintenance

# Check queue status
php artisan queue:size

# Check failed jobs
php artisan queue:failed | wc -l

# Check disk space
df -h | grep -E "(storage|var)"

# Check email logs for errors
grep -i "error\|failed" storage/logs/laravel.log | tail -10
```

### Weekly Tasks

```bash
#!/bin/bash
# Weekly email system maintenance

# Clean up old logs
find storage/logs -name "*.log" -mtime +7 -delete

# Retry failed jobs (if appropriate)
php artisan queue:retry all

# Check email delivery rates
php artisan email:delivery-report --days=7

# Update email templates if needed
php artisan email:sync-templates
```

### Monthly Tasks

```bash
#!/bin/bash
# Monthly email system maintenance

# Archive old email logs
php artisan email:archive-logs --months=3

# Review and update SMTP configurations
php artisan email:review-configs

# Check for security updates
composer audit

# Review email performance metrics
php artisan email:performance-report --month
```

---

This troubleshooting guide provides comprehensive solutions for common email system issues. For complex problems or system-specific issues, consult with your system administrator or technical support team.
