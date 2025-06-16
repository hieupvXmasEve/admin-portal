# Google Login API

## Overview
The Google Login API allows students to authenticate using their Google accounts via OAuth2.

## Endpoint
```
POST /api/v1/auth/login/google
```

## Request Headers
```
Content-Type: application/json
Accept: application/json
```

## Request Body
```json
{
  "access_token": "google_access_token_here",
  "device_name": "Student Portal App" // Optional
}
```

## Success Response (200)
```json
{
  "success": true,
  "message": "Google login successful",
  "data": {
    "student": {
      "id": 1,
      "student_id": "SW25001",
      "full_name": "John Doe",
      "email": "john.doe@student.edu",
      "enrollment_status": "active",
      "campus": "Main Campus",
      "program": "Computer Science"
    },
    "token": "bearer_token_here",
    "token_type": "Bearer"
  }
}
```

## New Student Registration (201)
If the student doesn't exist and registration is enabled:
```json
{
  "success": true,
  "message": "Account created successfully. Please wait for admin approval.",
  "data": {
    "student": {
      "id": 1,
      "full_name": "John Doe",
      "email": "john.doe@student.edu",
      "status": "inactive"
    }
  }
}
```

## Error Responses

### Invalid Token (401)
```json
{
  "success": false,
  "message": "Invalid Google access token"
}
```

### Registration Disabled (404)
```json
{
  "success": false,
  "message": "No account found with this email. Student registration is not available."
}
```

### Account Inactive (403)
```json
{
  "success": false,
  "message": "Account is not active"
}
```

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "access_token": ["The access token field is required."]
  }
}
```

## Implementation Notes

1. **Google OAuth Setup**: Ensure Google OAuth is configured in `config/services.php`
2. **Student Registration**: Control via `app.allow_student_registration` config
3. **Account Activation**: New accounts require admin activation
4. **Token Management**: Uses Laravel Sanctum for API authentication

## Frontend Integration Example

```javascript
// Get Google access token from Google OAuth
const googleAccessToken = await getGoogleAccessToken();

// Login with Google
const response = await fetch('/api/v1/auth/login/google', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    access_token: googleAccessToken,
    device_name: 'Student Portal'
  })
});

const data = await response.json();

if (data.success) {
  // Store token for future API calls
  localStorage.setItem('auth_token', data.data.token);
  
  // Redirect to dashboard
  window.location.href = '/dashboard';
} else {
  // Handle error
  console.error(data.message);
}
``` 
