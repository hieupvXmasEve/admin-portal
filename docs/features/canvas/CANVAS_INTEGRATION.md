# Canvas LMS Integration Guide

## Overview
This integration allows you to sync courses from Canvas LMS to your portal using OAuth2 authentication.

## Setup Instructions

### 1. Create Developer Key in Canvas

1. Log in to your Canvas instance as an administrator
2. Navigate to **Admin** → **Developer Keys**
3. Click **+ Developer Key** → **+ API Key**
4. Fill in the required information:
   - **Key Name**: Your Portal Name (e.g., "Swinburne Portal")
   - **Owner Email**: Your admin email
   - **Redirect URIs**: Add your callback URL
     ```
     https://your-domain.com/admin/canvas/oauth/callback
     ```
   - **Redirect URI (Legacy)**: Same as above (for older Canvas versions)
   - **Vendor Code**: Your organization name
   - **Icon URL**: (Optional) Your portal logo URL

5. **Scopes**: Select the following scopes:
   - `url:GET|/api/v1/courses` - List courses
   - `url:GET|/api/v1/courses/:course_id` - Get course details
   - `url:GET|/api/v1/courses/:course_id/users` - List course users
   - `url:GET|/api/v1/courses/:course_id/assignments` - List assignments

6. Click **Save**
7. Copy the **Client ID** and **Client Secret** (shown only once)

### 2. Configure Portal Integration

1. Log in to your portal as an administrator
2. Select your campus
3. Navigate to **Admin** → **Canvas Integration**
4. Click **Add Integration**
5. Fill in the form:
   - **Campus**: Select your campus
   - **Canvas URL**: Your Canvas instance URL (e.g., `https://canvas.instructure.com`)
   - **Client ID**: Paste from Canvas Developer Key
   - **Client Secret**: Paste from Canvas Developer Key

6. Click **Save**

### 3. Authorize Integration

1. After creating the integration, click **Authorize**
2. You will be redirected to Canvas
3. Log in with your Canvas account (if not already logged in)
4. Click **Authorize** to grant access
5. You will be redirected back to the portal

### 4. Sync Courses

1. Once authorized, click **Sync Courses**
2. Wait for the sync to complete
3. Navigate to **Canvas Courses** to view synced courses

### 5. Map Canvas Courses to Local Courses

1. Go to **Canvas Courses**
2. Find a Canvas course you want to map
3. Click **Map Course**
4. Search and select the corresponding local course offering
5. Click **Confirm**

## Features

### Canvas Integration Management
- Create/delete Canvas integrations per campus
- OAuth2 authorization flow
- Token management (automatic refresh)
- Toggle integration active/inactive
- View sync status and last sync time

### Course Synchronization
- Sync all active published courses from Canvas
- View Canvas course details (code, name, enrollment)
- Track sync status: pending, mapped, ignored
- Manual refresh for individual courses

### Course Mapping
- Map Canvas courses to local course offerings
- Search local courses by code or name
- Unmap courses
- Ignore courses (exclude from future syncs)
- View mapping status with badges

## API Endpoints

### Integration Management
- `GET /admin/canvas/integrations` - List integrations
- `POST /admin/canvas/integrations` - Create integration
- `DELETE /admin/canvas/integrations/{id}` - Delete integration
- `POST /admin/canvas/integrations/{id}/toggle` - Toggle active status

### OAuth Flow
- `GET /admin/canvas/oauth/redirect/{id}` - Initiate OAuth
- `GET /admin/canvas/oauth/callback` - OAuth callback
- `POST /admin/canvas/oauth/revoke/{id}` - Revoke token

### Course Sync
- `POST /admin/canvas/sync/{id}` - Sync courses
- `GET /admin/canvas/courses` - List Canvas courses
- `POST /admin/canvas/courses/map` - Map course
- `POST /admin/canvas/courses/{id}/unmap` - Unmap course
- `POST /admin/canvas/courses/{id}/ignore` - Ignore course

### Syllabus Template Reuse Guard

Mapping a Canvas course reserves the syllabus template currently assigned to
the local course offering. Course offering create/edit dropdowns exclude
Canvas-reserved templates, and backend validation rejects direct attempts to
reuse them for another class. The reservation starts at mapping time; assignment
sync does not need to run first. Duplicate offering is also blocked for a
Canvas-reserved syllabus template.

## Database Schema

### canvas_integrations
- `id` - Primary key
- `campus_id` - Foreign key to campuses
- `canvas_url` - Canvas instance URL
- `client_id` - OAuth client ID
- `client_secret` - Encrypted OAuth client secret
- `access_token` - Encrypted OAuth access token
- `refresh_token` - Encrypted OAuth refresh token
- `token_expires_at` - Token expiration timestamp
- `is_active` - Active status
- `sync_status` - Sync status (idle/syncing/completed/failed)
- `last_sync_at` - Last sync timestamp
- `sync_error` - Last sync error message

### canvas_course_mappings
- `id` - Primary key
- `canvas_integration_id` - Foreign key to canvas_integrations
- `canvas_course_id` - Canvas course ID
- `canvas_course_code` - Canvas course code
- `canvas_course_name` - Canvas course name
- `course_offering_id` - Foreign key to course_offerings (nullable)
- `canvas_data` - Full Canvas course data (JSON)
- `sync_status` - Mapping status (pending/mapped/ignored)
- `last_synced_at` - Last sync timestamp

## Security

### Token Encryption
All sensitive data is encrypted using Laravel's encryption:
- `client_secret`
- `access_token`
- `refresh_token`

### CSRF Protection
OAuth state parameter is used to prevent CSRF attacks during the authorization flow.

### Campus Scoping
All integrations are scoped to a specific campus. Users can only access integrations for their selected campus.

### Permission Requirements
- Admin users can manage Canvas integrations
- Requires `campus.selected` middleware

## Troubleshooting

### Authorization Failed
- Verify Canvas URL is correct
- Check Client ID and Client Secret
- Ensure redirect URI matches exactly in Canvas Developer Key
- Check Canvas instance is accessible

### Sync Failed
- Verify access token is valid (check token expiration)
- Try revoking and re-authorizing
- Check Canvas API is accessible
- Review sync error message in integration details

### Courses Not Appearing
- Ensure courses are published in Canvas
- Check courses are in active enrollment state
- Verify you have permission to view courses in Canvas
- Try manual sync

### Mapping Issues
- Ensure local course offering exists
- Check campus matches
- Verify course offering is active

## Environment Variables

Add to your `.env` file:

```env
# Canvas LMS Integration
CANVAS_DEFAULT_SCOPES=
CANVAS_API_TIMEOUT=30
```

## Support

For issues or questions:
1. Check Canvas Developer Key configuration
2. Review Laravel logs: `storage/logs/laravel.log`
3. Check network requests in browser DevTools
4. Verify Canvas API status

## Technical Details

### OAuth2 Flow
1. User clicks "Authorize"
2. Portal redirects to Canvas with client_id, redirect_uri, state
3. User logs in to Canvas and grants permission
4. Canvas redirects back with authorization code
5. Portal exchanges code for access_token and refresh_token
6. Tokens are encrypted and stored in database

### Token Refresh
- Tokens expire after 1 hour (3600 seconds)
- Portal automatically refreshes tokens when needed
- Refresh happens before API requests if token expires in < 5 minutes

### API Rate Limiting
- Canvas has rate limits (varies by instance)
- Portal uses pagination (100 items per page) to reduce API calls
- Failed requests are logged for debugging
