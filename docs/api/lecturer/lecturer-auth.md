# Lecturer Auth — Google Login (Concise Doc)

Endpoint
- Method: POST
- Path: /api/v1/lecturer/auth/login/google
- Auth: Public (returns Sanctum token)
- Rate limit: lecturer-auth

Input (request body)
- TypeScript
  interface GoogleLoginInput {
    access_token: string; // Google OAuth 2.0 access token
    device_name?: string; // default: "Lecturer Portal (Google)"
  }

- Zod (FE validation)
  import { z } from 'zod';
  export const GoogleLoginInputSchema = z.object({
    access_token: z.string().min(1),
    device_name: z.string().max(255).optional(),
  });
  export type GoogleLoginInput = z.infer<typeof GoogleLoginInputSchema>;

Output (success 200)
- TypeScript
  interface Lecturer {
    // Serialized by LecturerResource — fields may expand; FE should treat as shape below at minimum
    id: number;
    name: string;
    email: string;
    avatar_url?: string | null;
    is_active: boolean;
    employment_status: 'active' | 'employed' | 'contract_active' | string;
    last_login_at?: string | null; // ISO datetime
    // ...additional fields may exist
  }

  interface LecturerLoginData {
    lecturer: Lecturer;
    token: string;        // Sanctum token
    token_type: 'Bearer';
    expires_in: number;   // minutes (default 525600)
  }

  interface ApiSuccess<T> {
    success: true;
    message: string;      // 'Google login successful'
    data: T;
    timestamp: string;    // ISO datetime
  }

  type GoogleLoginSuccess = ApiSuccess<LecturerLoginData>;

- Zod (FE parsing)
  import { z } from 'zod';
  export const LecturerSchema = z.object({
    id: z.number(),
    name: z.string(),
    email: z.string().email(),
    avatar_url: z.string().url().nullable().optional(),
    is_active: z.boolean(),
    employment_status: z.string(),
    last_login_at: z.string().datetime().nullable().optional(),
  }).passthrough();

  export const GoogleLoginSuccessSchema = z.object({
    success: z.literal(true),
    message: z.string(),
    data: z.object({
      lecturer: LecturerSchema,
      token: z.string(),
      token_type: z.literal('Bearer'),
      expires_in: z.number(),
    }),
    timestamp: z.string().datetime(),
  });

Output (errors)
- Common error envelope
  interface ApiError {
    success: false;
    message: string;
    error_code:
      | 'VALIDATION_ERROR'
      | 'AUTHENTICATION_ERROR'
      | 'AUTHORIZATION_ERROR'
      | 'NOT_FOUND'
      | 'RATE_LIMIT_ERROR'
      | 'SERVER_ERROR';
    errors?: Record<string, unknown>;
    timestamp: string;
  }

  export const ApiErrorSchema = z.object({
    success: z.literal(false),
    message: z.string(),
    error_code: z.enum([
      'VALIDATION_ERROR',
      'AUTHENTICATION_ERROR',
      'AUTHORIZATION_ERROR',
      'NOT_FOUND',
      'RATE_LIMIT_ERROR',
      'SERVER_ERROR',
    ]),
    errors: z.record(z.unknown()).optional(),
    timestamp: z.string().datetime(),
  });

- Status codes
  400 VALIDATION_ERROR — missing/invalid fields
  401 AUTHENTICATION_ERROR — invalid Google token / cannot fetch userinfo
  403 AUTHORIZATION_ERROR — account inactive or restricted
  404 NOT_FOUND — no lecturer account for email
  429 RATE_LIMIT_ERROR — too many requests
  500 SERVER_ERROR — unexpected error

Example
- Request
  POST /api/v1/lecturer/auth/login/google
  Content-Type: application/json
  {
    "access_token": "{{GOOGLE_ACCESS_TOKEN}}",
    "device_name": "MacBook Pro"
  }

- Success response (200)
  {
    "success": true,
    "message": "Google login successful",
    "data": {
      "lecturer": {
        "id": 123,
        "name": "Dr. Jane Doe",
        "email": "jane.doe@swin.edu",
        "avatar_url": null,
        "is_active": true,
        "employment_status": "active",
        "last_login_at": "2025-08-10T12:00:00Z"
      },
      "token": "<sanctum_token>",
      "token_type": "Bearer",
      "expires_in": 525600
    },
    "timestamp": "2025-08-10T12:34:56Z"
  }

- Error response (example 404)
  {
    "success": false,
    "message": "No lecturer account found with this email. Please contact your administrator.",
    "error_code": "NOT_FOUND",
    "timestamp": "2025-08-10T12:35:00Z"
  }

Notes for FE
- Use Authorization: Bearer <token> for subsequent protected endpoints.
- Prefer Zod schemas above with vee-validate for form flows.
- Backend may add fields to Lecturer; parsing schema is passthrough to be forward-compatible.

