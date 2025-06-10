# 🔧 Google OAuth Configuration Fix

## 🚨 Problem

When running the application in Docker (port 8080), Google OAuth login fails with a 500 Internal Server Error because the callback URL doesn't match the configured redirect URI in Google Cloud Console.

## ✅ Solution Applied

### 1. Updated Environment Configuration

**File:** `.env.docker.production`

```bash
# Changed from:
APP_URL=https://localhost
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=true

# To:
APP_URL=http://127.0.0.1:8080
SESSION_DOMAIN=127.0.0.1
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8080,localhost:8080

# Redirect URI (already correct):
GOOGLE_REDIRECT_URI=http://127.0.0.1:8080/auth/google/callback
```

### 2. Updated SocialController Error Handling

**File:** `app/Http/Controllers/Auth/SocialController.php`

```php
// Added proper exception handling for InvalidStateException
use Laravel\Socialite\Two\InvalidStateException;

public function callback(Request $request)
{
    try {
        $user_social = Socialite::driver('google')->user();
        // ... existing logic
    } catch (InvalidStateException $e) {
        // Handle invalid state exception - redirect back to login
        return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
    } catch (\Exception $e) {
        // Handle other exceptions
        return redirect()->route('login')->with('error', 'Authentication error: ' . $e->getMessage());
    }
}
```

### 3. Root Cause Analysis

The **InvalidStateException** was caused by:

1. **Session Configuration Issues**:
   - `SESSION_SECURE_COOKIE=true` but using HTTP (not HTTPS)
   - `SESSION_DOMAIN=localhost` didn't match `127.0.0.1:8080`
   - Session state couldn't be properly maintained across OAuth flow

2. **URL Mismatch**:
   - `APP_URL=https://localhost` vs actual `http://127.0.0.1:8080`
   - Google OAuth state validation failed due to domain/protocol mismatch

### 4. Verified Application Configuration

Current configuration in the application:
- **APP_URL**: `http://127.0.0.1:8080`
- **Google Redirect URI**: `http://127.0.0.1:8080/auth/google/callback`
- **Google Client ID**: `345290200984-nlncugvfbd8bsrrt57surrbbq9o00an6.apps.googleusercontent.com`
- **Session Domain**: `127.0.0.1`
- **Secure Cookie**: `false` (for HTTP)

## 🌐 Required Google Cloud Console Updates

You need to update your Google OAuth 2.0 Client ID configuration:

### Step 1: Access Google Cloud Console
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project
3. Navigate to **APIs & Services** > **Credentials**

### Step 2: Edit OAuth 2.0 Client ID
1. Find your OAuth 2.0 Client ID: `345290200984-nlncugvfbd8bsrrt57surrbbq9o00an6.apps.googleusercontent.com`
2. Click the **Edit** button (pencil icon)

### Step 3: Update Authorized JavaScript Origins
Add these URLs to **Authorized JavaScript origins**:
```
http://127.0.0.1:8080
http://localhost:8080
```

### Step 4: Update Authorized Redirect URIs
Add these URLs to **Authorized redirect URIs**:
```
http://127.0.0.1:8080/auth/google/callback
http://localhost:8080/auth/google/callback
```

### Step 5: Save Changes
Click **Save** to apply the changes.

## 🧪 Testing

### Automated Test
Run the test script to verify configuration:
```bash
./test-google-oauth.sh
```

### Manual Test
1. Open browser and go to: `http://localhost:8080`
2. Click on Google Login button
3. Complete Google OAuth flow
4. Should redirect back successfully without 500 error

## 📋 Environment Comparison

| Environment | URL | Port | OAuth Redirect |
|-------------|-----|------|----------------|
| `php artisan serve` | `http://127.0.0.1:8000` | 8000 | `http://127.0.0.1:8000/auth/google/callback` |
| Docker Production | `http://127.0.0.1:8080` | 8080 | `http://127.0.0.1:8080/auth/google/callback` |

## 🔍 Verification Commands

Check current configuration:
```bash
# Check APP_URL
docker exec swinx-app php artisan tinker --execute="echo config('app.url');"

# Check Google redirect URI
docker exec swinx-app php artisan tinker --execute="echo config('services.google.redirect');"

# Check Google client ID
docker exec swinx-app php artisan tinker --execute="echo config('services.google.client_id');"
```

## 🚨 Troubleshooting

### If OAuth still fails:

1. **Clear application cache:**
   ```bash
   docker exec swinx-app php artisan config:clear
   docker exec swinx-app php artisan cache:clear
   ```

2. **Check Google Cloud Console settings:**
   - Ensure all URLs are added correctly
   - Check for typos in URLs
   - Verify the OAuth client is enabled

3. **Check application logs:**
   ```bash
   docker logs swinx-app --tail 50
   ```

4. **Verify container is running:**
   ```bash
   ./production-deploy.sh status
   ```

### Common Issues:

- **URL mismatch**: Ensure Google Console URLs exactly match application URLs
- **HTTPS vs HTTP**: Make sure protocol (http/https) matches
- **Port numbers**: Verify port 8080 is included in all URLs
- **Trailing slashes**: Remove any trailing slashes from URLs

## 📝 Notes

- The application automatically uses the `GOOGLE_REDIRECT_URI` environment variable
- Changes to `.env.docker.production` require container restart
- Google OAuth changes may take a few minutes to propagate

## ✅ Success Indicators

When properly configured, you should see:
- ✅ Google login button works
- ✅ OAuth redirect completes successfully
- ✅ User is logged in and redirected to dashboard
- ✅ No 500 errors in browser console

---

**Configuration updated successfully! 🎉**

Remember to update Google Cloud Console settings to complete the fix.
