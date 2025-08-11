# Lecturer Google Login API

This document describes the Lecturer Google OAuth login endpoint, as implemented in:
- routes/api/v1/lecturer.php
- app/Http/Controllers/Api/V1/Lecturer/AuthController.php (method: loginWithGoogle)

## Overview

Allows an existing lecturer to authenticate using a Google OAuth access token. On success, returns a Sanctum API token for accessing protected lecturer endpoints.

- Method: POST
- Path: /api/v1/lecturer/auth/login/google
- Rate limiting: lecturer.api.rate:lecturer-auth
- Authentication: Not required for this endpoint

## Request

Content-Type: application/json

Body fields:
- access_token (string, required): Google OAuth 2.0 access token obtained from client-side Google Sign-In flow.
- device_name (string, optional, max 255): A friendly name for the device; defaults to "Lecturer Portal (Google)" if omitted.

Example request body:
{
  "access_token": "{{GOOGLE_ACCESS_TOKEN}}",
  "device_name": "MacBook Pro"
}

Notes:
- The service validates the token by calling https://www.googleapis.com/oauth2/v1/userinfo with the provided access token.
- The lecturer is matched by the email field returned by Google userinfo. New accounts are NOT auto-created; the email must already exist in the Lecture model.

## Successful Response

Status: 200 OK

Body:
{
  "success": true,
  "message": "Google login successful",
  "data": {
    "lecturer": { /* LecturerResource fields */ },
    "token": "<sanctum_token>",
    "token_type": "Bearer",
    "expires_in": 525600
  }
}

Notes:
- token is a Laravel Sanctum plain-text token. Store it securely on the client.
- Use token_type and token as Authorization header for subsequent requests: Authorization: Bearer <token>
- expires_in is in minutes (defaults to config('sanctum.expiration', 525600)).

## Error Responses

- 400 Bad Request (Validation)
  Triggered when required fields are missing or invalid.
  {
    "success": false,
    "message": "Validation error",
    "errors": {
      "access_token": ["The access token field is required."]
    }
  }

- 401 Unauthorized (Authentication)
  When the Google access token is invalid or user info cannot be retrieved.
  {
    "success": false,
    "message": "Invalid Google access token"
  }
  or
  {
    "success": false,
    "message": "Unable to retrieve user information from Google"
  }

- 403 Forbidden (Authorization)
  When the lecturer account is inactive or employment status is not allowed.
  {
    "success": false,
    "message": "Account is inactive. Please contact administration."
  }
  or
  {
    "success": false,
    "message": "Account access restricted. Please contact HR."
  }

- 404 Not Found
  When no lecturer account is found matching the Google email.
  {
    "success": false,
    "message": "No lecturer account found with this email. Please contact your administrator."
  }

- 429 Too Many Requests
  May be returned by the rate limiting middleware (lecturer.api.rate:lecturer-auth). The exact payload depends on the middleware configuration.

- 500 Internal Server Error
  For unexpected errors while contacting Google or processing the login.
  {
    "success": false,
    "message": "Failed to authenticate with Google: <details>"
  }

## cURL Example

Avoid pasting secrets inline. Export them to environment variables first.

# Set the Google token securely (example placeholder)
# export GOOGLE_ACCESS_TOKEN=...

curl -sS \
  -X POST \
  -H "Content-Type: application/json" \
  "${API_BASE_URL:-http://localhost:8000}/api/v1/lecturer/auth/login/google" \
  -d "$(jq -n --arg t "$GOOGLE_ACCESS_TOKEN" --arg d "Lecturer Portal (Google)" '{access_token: $t, device_name: $d}')"

# On success, save the returned token for subsequent calls
# export LECTURER_TOKEN=... # value from data.token

# Example of using the token to call a protected route
curl -sS \
  -H "Authorization: Bearer $LECTURER_TOKEN" \
  "${API_BASE_URL:-http://localhost:8000}/api/v1/lecturer/auth/me"

## Implementation Notes

- Controller: App/Http/Controllers/Api/V1/Lecturer/AuthController@loginWithGoogle
- Validates access_token and optional device_name via Validator.
- Uses Illuminate\Support\Facades\Http to call Google userinfo endpoint with the provided access token.
- Matches existing lecturer by email. If oauth_provider_id is empty, stores Google provider details (provider name, id, avatar, email_verified_at).
- Verifies lecturer account status (is_active and allowed employment_status values: active, employed, contract_active).
- Updates last_login_at and issues a Sanctum token with scope ["lecturer:access"].

## Related Endpoints

- POST /api/v1/lecturer/auth/login — Email/password login
- POST /api/v1/lecturer/auth/refresh — Refresh current token (requires Authorization)
- POST /api/v1/lecturer/auth/logout — Logout and revoke current token
- GET  /api/v1/lecturer/auth/me — Get current lecturer profile (requires Authorization)

