# Canvas LMS Integration - Quick Start Guide

## 🚀 Quick Start (5 Minutes)

### Step 1: Setup Developer Key in Canvas (2 min)

1. Login to Canvas as admin → **Admin** → **Developer Keys**
2. Click **+ Developer Key** → **+ API Key**
3. Fill in:
   - **Key Name**: "Swinburne Portal"
   - **Owner Email**: Your email
   - **Redirect URIs**: 
     ```
     https://your-domain.com/admin/canvas/oauth/callback
     http://localhost:8000/admin/canvas/oauth/callback  (for local dev)
     ```
4. **Save** and copy **Client ID** and **Client Secret** (shown only once!)

### Step 2: Configure Integration in Portal (1 min)

1. Login to portal as admin
2. Select your campus
3. Go to: **Admin** → **Canvas Integration** → **Add Integration**
4. Enter:
   - **Canvas URL**: `https://your-canvas-domain.com`
   - **Client ID**: (paste from Canvas)
   - **Client Secret**: (paste from Canvas)
5. Click **Create Integration**

### Step 3: Authorize & Sync (2 min)

1. Click **Authorize** button
2. Login to Canvas (if needed)
3. Click **Authorize** on Canvas permission page
4. You'll be redirected back to portal
5. Click **Sync Courses** button
6. Wait for sync to complete

### Step 4: Map Courses

1. Go to **Canvas Courses** tab
2. Find a Canvas course
3. Click **Map** button
4. Select corresponding local course offering
5. Click **Confirm**

**Done! 🎉** Your Canvas courses are now synced.

---

## 📍 Navigation

- **Integrations**: `/admin/canvas/integrations`
- **Courses**: `/admin/canvas/courses`

## 🔧 Environment Variables

Add to `.env`:
```env
CANVAS_DEFAULT_SCOPES=
CANVAS_API_TIMEOUT=30
```

## 🆘 Common Issues

### "Authorization Failed"
- Check Canvas URL is correct (with https://)
- Verify Client ID and Secret
- Ensure Redirect URI matches exactly in Canvas

### "Sync Failed"
- Click **Authorize** again
- Check Canvas is accessible
- Review error message in integration card

### "No Courses"
- Ensure courses are published in Canvas
- Check you have view permission in Canvas
- Try manual sync again

## 📚 Full Documentation

See [CANVAS_INTEGRATION.md](./CANVAS_INTEGRATION.md) for complete documentation.

## 🎯 Features

✅ OAuth2 authentication
✅ Automatic token refresh
✅ Course synchronization
✅ Course mapping to local offerings
✅ Real-time sync status
✅ Multi-campus support
✅ Encrypted credentials

## 🔒 Security

- All sensitive data (client_secret, tokens) are encrypted
- CSRF protection with state parameter
- Campus-scoped access control
- Admin-only access

## 🛠️ Tech Stack

- **Backend**: Laravel 12 + Services Pattern
- **Frontend**: Vue 3 + Inertia.js + reka-ui
- **API**: Canvas LMS REST API v1
- **Auth**: OAuth2 Authorization Code Flow
