# Lecturer Auth — Profile (/me) (Concise Doc)

Endpoint
- Method: GET
- Path: /api/v1/lecturer/auth/me
- Auth: Required — Authorization: Bearer <token> (Sanctum; scope: lecturer:access)
- Rate limit: inherited from lecturer.api.rate:lecturer-api (route group)

Input
- Headers
  - Authorization: Bearer <LECTURER_TOKEN>
- Query/body: none

TypeScript
- Request has no body
  type MeRequest = void;

Output (success 200)
- TypeScript
  interface Lecturer {
    // Serialized by LecturerResource — minimal stable shape below
    id: number;
    name: string;
    email: string;
    avatar_url?: string | null;
    is_active: boolean;
    employment_status: 'active' | 'employed' | 'contract_active' | string;
    last_login_at?: string | null; // ISO datetime
    // May include related data: campus, courseOfferings (with curriculumUnit, semester), etc.
  }

  interface ApiSuccess<T> {
    success: true;
    message: string;      // 'Profile retrieved successfully'
    data: T;
    timestamp: string;    // ISO datetime
  }

  type MeSuccess = ApiSuccess<Lecturer>;

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
    // Accept additional properties to remain forward-compatible
  }).passthrough();

  export const MeSuccessSchema = z.object({
    success: z.literal(true),
    message: z.string(),
    data: LecturerSchema,
    timestamp: z.string().datetime(),
  });

Output (errors)
- Common error envelope
  interface ApiError {
    success: false;
    message: string;
    error_code:
      | 'AUTHENTICATION_ERROR'
      | 'AUTHORIZATION_ERROR'
      | 'SERVER_ERROR';
    errors?: Record<string, unknown>;
    timestamp: string;
  }

  import { z } from 'zod';
  export const ApiErrorSchema = z.object({
    success: z.literal(false),
    message: z.string(),
    error_code: z.enum([
      'AUTHENTICATION_ERROR',
      'AUTHORIZATION_ERROR',
      'SERVER_ERROR',
    ]),
    errors: z.record(z.unknown()).optional(),
    timestamp: z.string().datetime(),
  });

- Status codes
  401 AUTHENTICATION_ERROR — invalid/expired/missing token
  403 AUTHORIZATION_ERROR — token lacks lecturer access
  500 SERVER_ERROR — unexpected error

Example
- Request
  GET /api/v1/lecturer/auth/me
  Authorization: Bearer <LECTURER_TOKEN>

- Success response (200)
  {
    "success": true,
    "message": "Profile retrieved successfully",
    "data": {
      "id": 123,
      "name": "Dr. Jane Doe",
      "email": "jane.doe@swin.edu",
      "avatar_url": null,
      "is_active": true,
      "employment_status": "active",
      "last_login_at": "2025-08-10T12:00:00Z"
    },
    "timestamp": "2025-08-10T12:34:56Z"
  }

- Error response (example 401)
  {
    "success": false,
    "message": "Authentication failed",
    "error_code": "AUTHENTICATION_ERROR",
    "timestamp": "2025-08-10T12:35:00Z"
  }

Notes for FE
- Always supply Authorization header with the Sanctum token received from login.
- Use the Zod schemas above with vee-validate for robust parsing.
- Backend may append extra fields/relations to the Lecturer; schema is passthrough to be forward-compatible.

