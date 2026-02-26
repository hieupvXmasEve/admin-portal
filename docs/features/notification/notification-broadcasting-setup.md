# Laravel Reverb WebSocket Setup and Testing Guide

## Overview

This guide covers the setup and testing of Laravel Reverb for the notification system. Reverb is Laravel's first-party WebSocket server that enables real-time broadcasting.

## Configuration

### Environment Variables

The following environment variables are configured in `.env`:

```env
# Broadcasting Configuration
BROADCAST_CONNECTION=reverb

# Reverb App Configuration
REVERB_APP_ID=432727
REVERB_APP_KEY=arsfe72jbofn80mcqt86
REVERB_APP_SECRET=vkjvxkg7nwmbpg1urquh
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb Server Configuration
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_SCALING_ENABLED=true
REVERB_MAX_CONNECTIONS=1000
REVERB_ENABLE_STATISTICS=true
REVERB_ENABLE_LOGGING=true
REVERB_ALLOWED_ORIGINS="*"

# Frontend Configuration
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Broadcasting Channels

The following private channels are configured for notifications:

- `notifications.{userId}` - Individual user notifications
- `campus.{campusId}` - Campus-wide notifications
- `role.{roleId}` - Role-based notifications
- `system-announcements` - System-wide announcements
- `admin-notifications` - Admin notification management
- `notification-analytics` - Analytics for administrators

## Starting the Reverb Server

### Development Environment

1. **Start the Reverb server:**
   ```bash
   php artisan reverb:start
   ```

2. **Start with custom host/port:**
   ```bash
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```

3. **Start in debug mode:**
   ```bash
   php artisan reverb:start --debug
   ```

### Production Environment

1. **Use a process manager like Supervisor:**
   ```ini
   [program:reverb]
   command=php /path/to/your/app/artisan reverb:start --host=0.0.0.0 --port=8080
   directory=/path/to/your/app
   autostart=true
   autorestart=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/path/to/your/app/storage/logs/reverb.log
   ```

2. **Or use systemd:**
   ```ini
   [Unit]
   Description=Laravel Reverb WebSocket Server
   After=network.target

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/path/to/your/app
   ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
   Restart=always
   RestartSec=3

   [Install]
   WantedBy=multi-user.target
   ```

## Testing the Setup

### 1. Command Line Test

Test the broadcasting configuration:

```bash
php artisan reverb:test --user-id=1
```

This command will:
- Verify broadcasting driver configuration
- Check Reverb connection settings
- Broadcast a test notification
- Verify channel authorization

### 2. Web Interface Test

1. **Start the Reverb server:**
   ```bash
   php artisan reverb:start
   ```

2. **Visit the test page:**
   ```
   http://localhost:8000/test-notifications
   ```

3. **Test the connection:**
   - Click "Connect to Echo"
   - Click "Send Test Notification"
   - Verify messages are received in real-time

### 3. API Test

Send a test broadcast via HTTP:

```bash
curl -X POST http://localhost:8000/test-broadcast \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Frontend Integration

### Laravel Echo Configuration

```typescript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }
})
```

### Listening for Notifications

```typescript
// Listen on private notification channel
echo.private(`notifications.${userId}`)
    .notification((notification) => {
        console.log('Received notification:', notification)
    })

// Listen for specific events
echo.private(`notifications.${userId}`)
    .listen('.test.notification', (data) => {
        console.log('Test notification:', data)
    })
```

## Troubleshooting

### Common Issues

1. **Connection Refused (ECONNREFUSED)**
   - Ensure Reverb server is running
   - Check host and port configuration
   - Verify firewall settings

2. **Authentication Failed**
   - Check broadcasting routes are loaded
   - Verify Sanctum token is valid
   - Ensure channel authorization is correct

3. **CORS Issues**
   - Update `REVERB_ALLOWED_ORIGINS` in .env
   - Check frontend origin matches allowed origins

4. **Port Already in Use (EADDRINUSE)**
   - Use a different port: `--port=8081`
   - Kill existing process on the port
   - Check for other services using the port

### Debug Commands

```bash
# Check Reverb configuration
php artisan config:show broadcasting

# Test broadcasting without server
php artisan reverb:test --user-id=1

# Check logs
tail -f storage/logs/laravel.log

# Check Reverb server logs
php artisan reverb:start --debug
```

## Performance Considerations

### Scaling

- Enable Redis scaling: `REVERB_SCALING_ENABLED=true`
- Configure Redis connection for scaling
- Use load balancer for multiple Reverb instances

### Monitoring

- Enable statistics: `REVERB_ENABLE_STATISTICS=true`
- Monitor connection count and message throughput
- Set up alerts for connection failures

### Security

- Use HTTPS in production: `REVERB_SCHEME=https`
- Configure proper allowed origins
- Implement proper authentication for channels
- Use SSL certificates for WebSocket connections

## Next Steps

1. Implement notification channel classes
2. Create notification service layer
3. Build Vue.js notification components
4. Set up automated event listeners
5. Add notification analytics and monitoring
