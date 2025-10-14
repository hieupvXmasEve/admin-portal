# Student Portal - Clubs API Documentation

## Overview

APIs for club discovery, membership management, and club administration in the Student Portal.

**Base URL**: `/api/v1`  
**Authentication**: Bearer Token (Student)  
**Content-Type**: `application/json`

---

## TypeScript Types

```typescript
// Club Status & Roles
type ClubStatus = 'active' | 'inactive';
type MemberRole = 'president' | 'vice_president' | 'secretary' | 'treasurer' | 'member';
type MemberStatus = 'active' | 'pending' | 'rejected' | 'left' | 'banned';

// Campus
interface Campus {
    id: number;
    name: string;
    code: string;
}

// Student (minimal)
interface Student {
    id: number;
    student_code: string;
    full_name: string;
    first_name: string;
    last_name: string;
    email: string;
    avatar_url: string | null;
}

// Club President
interface ClubPresident {
    id: number;
    student_id: number;
    student?: Student;
}

// My Membership Status
interface MyMembership {
    id: number | null;
    role: MemberRole | null;
    role_display: string | null;
    status: MemberStatus | null;
    status_display: string | null;
    joined_at: string | null;
    can_apply: boolean;
}

// Club
interface Club {
    id: number;
    name: string;
    description: string | null;
    founded_date: string | null; // YYYY-MM-DD
    status: ClubStatus;
    status_display: string;
    images: {
        avatar_url: string | null;
        thumbnail_url: string | null;
        cover_url: string | null;
    };
    contact: {
        email: string | null;
        phone: string | null;
    };
    social_links: Record<string, string>;
    achievements: string[];
    campus?: Campus;
    president?: ClubPresident;
    member_count?: number;
    my_membership?: MyMembership;
    created_at: string;
    updated_at: string;
}

// Club Member
interface ClubMember {
    id: number;
    role: MemberRole;
    role_display: string;
    status: MemberStatus;
    status_display: string;
    application_notes: string | null;
    responsibilities: string[];
    participation_score: number;
    joined_at: string | null;
    left_at: string | null;
    last_active_at: string | null;
    student?: Student;
    club?: {
        id: number;
        name: string;
        avatar_url: string | null;
    };
    approver?: Student | null;
    created_at: string;
    updated_at: string;
}

// Club Management Dashboard
interface ClubManagementData {
    club: Club;
    members: ClubMember[];
    pending_applications: ClubMember[];
    statistics: {
        total_members: number;
        pending_applications: number;
        officers: number;
    };
    recent_activities: RoleChangeActivity[];
}

// Role Change Activity
interface RoleChangeActivity {
    id: number;
    old_role: MemberRole | null;
    old_role_display: string | null;
    new_role: MemberRole;
    new_role_display: string;
    change_reason: string | null;
    started_at: string;
    ended_at: string | null;
    member: {
        id: number;
        student: Student;
    };
    changed_by: Student | null;
}

// API Response
interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data?: T;
    errors?: Record<string, string[]>;
}

// Paginated Response
interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}
```

---

## Endpoints

### 1. List Clubs

**GET** `/clubs`

Get all clubs with search/filter.

**Query Parameters:**
- `search` (string, optional) - Search by name or description
- `status` (string, optional) - Filter by status: `active`, `inactive`
- `per_page` (number, optional, default: 15, max: 50)

**Response:**
```typescript
ApiResponse<PaginatedResponse<Club>>
```

**Example:**
```typescript
const { data } = await api.get('/clubs', {
    search: 'tech',
    status: 'active',
    per_page: 15
});
```

---

### 2. Get Club Details

**GET** `/clubs/{club}`

Get detailed club information including membership status.

**Response:**
```typescript
ApiResponse<Club>
```

---

### 3. My Memberships

**GET** `/clubs/my-memberships`

Get all clubs the current student has joined or applied to.

**Query Parameters:**
- `status` (string, optional) - Filter by membership status
- `per_page` (number, optional, default: 15, max: 50)

**Response:**
```typescript
ApiResponse<PaginatedResponse<ClubMember>>
```

---

### 4. Apply for Membership

**POST** `/clubs/{club}/apply`

Submit a membership application.

**Request Body:**
```typescript
{
    application_notes?: string; // Optional notes
}
```

**Response:**
```typescript
ApiResponse<ClubMember>
```

---

## Club Management Endpoints (President Only)

### 5. Management Dashboard

**GET** `/clubs/{club}/manage`

Get club management data including members, pending applications, and statistics.

**Authorization:** President only

**Response:**
```typescript
ApiResponse<ClubManagementData>
```

---

### 6. Update Club Info

**PUT** `/clubs/{club}`

Update club information.

**Authorization:** President only

**Request Body:**
```typescript
{
    name?: string;
    description?: string;
    founded_date?: string; // YYYY-MM-DD
    contact_email?: string;
    contact_phone?: string;
    social_links?: Record<string, string>;
    achievements?: string[];
}
```

**Response:**
```typescript
ApiResponse<Club>
```

---

### 7. List Members

**GET** `/clubs/{club}/members`

Get club members with filters.

**Authorization:** President only

**Query Parameters:**
- `status` (string, optional) - Filter by status: `active`, `pending`, `rejected`, `left`, `banned`
- `role` (string, optional) - Filter by role: `president`, `vice_president`, `secretary`, `treasurer`, `member`
- `per_page` (number, optional, default: 15, max: 50)

**Response:**
```typescript
ApiResponse<PaginatedResponse<ClubMember>>
```

---

### 8. Approve Member

**PUT** `/clubs/{club}/members/{member}/approve`

Approve a pending membership application.

**Authorization:** President only

**Response:**
```typescript
ApiResponse<ClubMember>
```

---

### 9. Reject Member

**PUT** `/clubs/{club}/members/{member}/reject`

Reject a pending membership application.

**Authorization:** President only

**Request Body:**
```typescript
{
    rejection_reason?: string; // Optional reason
}
```

**Response:**
```typescript
ApiResponse<ClubMember>
```

---

### 10. Update Member Role

**PUT** `/clubs/{club}/members/{member}/role`

Update a member's role.

**Authorization:** President only

**Request Body:**
```typescript
{
    role: MemberRole; // Required: 'vice_president' | 'secretary' | 'treasurer' | 'member'
    responsibilities?: string; // Optional
    change_reason?: string; // Optional
}
```

**Response:**
```typescript
ApiResponse<ClubMember>
```

---

## Error Responses

All endpoints may return error responses:

```typescript
// 400 Bad Request
{
    success: false,
    message: "Validation failed",
    errors: {
        field_name: ["Error message"]
    }
}

// 401 Unauthorized
{
    success: false,
    message: "Unauthenticated"
}

// 403 Forbidden
{
    success: false,
    message: "You are not authorized to manage this club"
}

// 404 Not Found
{
    success: false,
    message: "Club not found"
}

// 422 Business Logic Error
{
    success: false,
    message: "You have already applied to this club",
    errors: {
        membership: ["Already exists"]
    }
}

// 500 Server Error
{
    success: false,
    message: "Failed to retrieve clubs"
}
```

---

## Usage Examples

### Using with `useApi()` composable:

```typescript
import { useApi } from '@/composables/useApi';

const api = useApi();

// List clubs
const { data } = await api.get('/clubs', {
    search: 'tech',
    status: 'active'
});

// Apply for membership
await api.post(`/clubs/${clubId}/apply`, {
    application_notes: 'I am interested in joining this club'
});

// Approve member (president only)
await api.put(`/clubs/${clubId}/members/${memberId}/approve`);
```

### Using with Inertia.js router:

```typescript
import { router } from '@inertiajs/vue3';

router.visit('/clubs', {
    data: { search: 'tech', status: 'active' },
    preserveState: true,
    preserveScroll: true,
});
```

---

## Notes

- All datetime strings are in `YYYY-MM-DD HH:mm:ss` format (server timezone)
- The `my_membership` field in `Club` response shows current user's membership status
- President can manage all members except cannot remove themselves
- Cannot change role to `president` via update role endpoint (use assign president endpoint instead)
- Pagination links include full URLs for navigation
