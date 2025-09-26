# Student Portal - Event Management API Documentation

## Overview

This document describes the Event Management API endpoints available for the Student Portal. These APIs allow students to discover events, register for participation, manage their registrations, and track their event history.

**Base URL**: `/api/v1`  
**Authentication**: Bearer Token (Student Authentication)  
**Content-Type**: `application/json`

## TypeScript Interfaces

### Core Types

```typescript
// Event Status Types
type EventStatus = 'draft' | 'published' | 'cancelled' | 'completed';
type ParticipationStatus = 'registered' | 'checked_in' | 'completed' | 'cancelled';
type OrganizerType = 'school';

// Campus Interface
interface Campus {
    id: number;
    name: string;
    code: string;
}

// Creator Interface
interface Creator {
    id: number;
    name: string;
}

// Event Flags Interface
interface EventFlags {
    is_published: boolean;
    is_cancelled: boolean;
    is_completed: boolean;
    is_draft: boolean;
    can_register: boolean;
    can_check_in: boolean;
    has_started: boolean;
    has_ended: boolean;
    has_reached_capacity: boolean;
}

// Event Participation Stats Interface
interface EventParticipationStats {
    registered_count: number;
    checked_in_count: number;
    completed_count: number;
    available_spots: number | null;
    participation_rate: number;
}

// Student's Participation in Event Interface
interface MyParticipation {
    id: number;
    status: ParticipationStatus;
    status_display: string;
    registered_at: string;
    checkin_time: string | null;
    gold_awarded: boolean;
    awarded_at: string | null;
    can_cancel: boolean;
    can_check_in: boolean;
}

// Event Timing Interface
interface EventTiming {
    duration_minutes: number;
    starts_in_minutes: number | null;
    ends_in_minutes: number | null;
    is_today: boolean;
    is_tomorrow: boolean;
    is_this_week: boolean;
}

// Base Event Interface (from API response)
interface Event {
    id: number;
    title: string;
    description: string | null;
    location: string;
    start_time: string; // YYYY-MM-DD HH:mm:ss format
    end_time: string; // YYYY-MM-DD HH:mm:ss format
    start_time_iso: string; // ISO 8601 datetime
    end_time_iso: string; // ISO 8601 datetime
    gold_reward_amount: number;
    max_participants: number | null;
    status: EventStatus;
    status_display: string;
    qr_code: string;
    organizer_type: OrganizerType;
    organizer_id: number;
    published_at: string | null;
    cancelled_at: string | null;
    completed_at: string | null;
    campus?: Campus;
    creator?: Creator;
    flags: EventFlags;
    participation: EventParticipationStats;
    my_participation: MyParticipation | null;
    statistics?: any; // Event statistics (when loaded)
    timing: EventTiming;
    created_at: string;
    updated_at: string;
}

// Student's event participation
interface EventParticipation {
    id: number;
    event_id: number;
    student_id: number;
    status: ParticipationStatus;
    registered_at: string;
    checkin_time: string | null;
    gold_awarded: boolean;
    awarded_at: string | null;
    created_at: string;
    updated_at: string;
    event: Event;
}

// Pagination wrapper
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

// API Response wrapper
interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data?: T;
    errors?: Record<string, string[]>;
}
```

### Request/Response Types

```typescript
// Event Discovery
interface EventListParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: EventStatus;
    time_filter?: 'upcoming' | 'ongoing' | 'past';
}

interface EventListResponse {
    success: boolean;
    message: string;
    data: {
        events: Event[];
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number;
            to: number;
        };
    };
}

// Event Registration
interface EventRegistrationRequest {
    // No body required - student ID from auth token
}

interface EventRegistrationResponse
    extends ApiResponse<{
        participation: EventParticipation;
        message: string;
    }> {}

// My Events
interface MyEventsParams {
    page?: number;
    per_page?: number;
    status?: ParticipationStatus;
    start_date?: string;
    end_date?: string;
}

interface MyEventsResponse extends PaginatedResponse<EventParticipation> {}

// My Events Response
interface MyEventsResponse {
    success: boolean;
    message: string;
    data: {
        participations: EventParticipation[];
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number;
            to: number;
        };
    };
}
```

## API Endpoints

### 1. Event Discovery

#### Get Events List

```typescript
GET / api / v1 / events;
```

**Description**: Retrieve a paginated list of published events for the student's campus.

**Query Parameters**:

```typescript
interface EventListParams {
    page?: number; // Page number (default: 1)
    per_page?: number; // Items per page (default: 15, max: 100)
    search?: string; // Search in title and description
    start_date?: string; // Filter events starting from date (YYYY-MM-DD)
    end_date?: string; // Filter events ending before date (YYYY-MM-DD)
    location?: string; // Filter by location
    has_rewards?: boolean; // Filter events with gold rewards
}
```

**Response**:

```typescript
{
  "success": true,
  "message": "Events retrieved successfully",
  "data": {
    "events": [
      {
        "id": 1,
        "title": "Campus Tech Talk 2024",
        "description": "Annual technology conference featuring industry leaders",
        "location": "Main Auditorium",
        "start_time": "2024-03-15 09:00:00",
        "end_time": "2024-03-15 17:00:00",
        "start_time_iso": "2024-03-15T09:00:00.000000Z",
        "end_time_iso": "2024-03-15T17:00:00.000000Z",
        "gold_reward_amount": 50.0,
        "max_participants": 200,
        "status": "published",
        "status_display": "Published",
        "qr_code": "EVT_2024_001_ABC123",
        "organizer_type": "school",
        "organizer_id": 1,
        "published_at": "2024-03-01 10:00:00",
        "cancelled_at": null,
        "completed_at": null,
        "campus": {
          "id": 1,
          "name": "Main Campus",
          "code": "MAIN"
        },
        "creator": {
          "id": 100,
          "name": "Admin User"
        },
        "flags": {
          "is_published": true,
          "is_cancelled": false,
          "is_completed": false,
          "is_draft": false,
          "can_register": true,
          "can_check_in": false,
          "has_started": false,
          "has_ended": false,
          "has_reached_capacity": false
        },
        "participation": {
          "registered_count": 45,
          "checked_in_count": 0,
          "completed_count": 0,
          "available_spots": 155,
          "participation_rate": 22.5
        },
        "my_participation": null, // or participation object if student is registered
        "timing": {
          "duration_minutes": 480,
          "starts_in_minutes": 7200,
          "ends_in_minutes": null,
          "is_today": false,
          "is_tomorrow": false,
          "is_this_week": true
        },
        "created_at": "2024-02-28 14:30:00",
        "updated_at": "2024-03-01 10:00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 15,
      "total": 42,
      "from": 1,
      "to": 15
    }
  }
}
```

#### Get Event Details

```typescript
GET / api / v1 / events / { id };
```

**Description**: Retrieve detailed information about a specific event.

**Path Parameters**:

- `id` (number): Event ID

**Response**:

```typescript
{
  "success": true,
  "message": "Event details retrieved successfully",
  "data": {
    "id": 1,
    "campus_id": 1,
    "title": "Campus Tech Talk 2024",
    "description": "Annual technology conference featuring industry leaders...",
    "start_time": "2024-03-15T09:00:00Z",
    "end_time": "2024-03-15T17:00:00Z",
    "location": "Main Auditorium",
    "gold_reward_amount": 50.00,
    "max_participants": 200,
    "qr_code": "EVT_2024_001_ABC123",
    "organizer_type": "school",
    "organizer_id": 1,
    "status": "published",
    "published_at": "2024-03-01T10:00:00Z",
    "cancelled_at": null,
    "completed_at": null,
    "created_at": "2024-02-28T14:30:00Z",
    "updated_at": "2024-03-01T10:00:00Z",
    "is_registered": true,
    "registration_status": "registered",
    "registered_count": 46,
    "can_register": false,
    "is_full": false,
    "organizer_name": "Main Campus"
  }
}
```

### 2. Event Registration

#### Register for Event

```typescript
POST / api / v1 / events / { id } / register;
```

**Description**: Register the authenticated student for an event.

**Path Parameters**:

- `id` (number): Event ID

**Request Body**: Empty (student ID extracted from auth token)

**Response**:

```typescript
{
  "success": true,
  "message": "Successfully registered for event",
  "data": {
    "participation": {
      "id": 123,
      "event_id": 1,
      "student_id": 456,
      "status": "registered",
      "registered_at": "2024-03-10T14:30:00Z",
      "checkin_time": null,
      "gold_awarded": false,
      "awarded_at": null,
      "created_at": "2024-03-10T14:30:00Z",
      "updated_at": "2024-03-10T14:30:00Z",
      "event": {
        "id": 1,
        "title": "Campus Tech Talk 2024",
        "start_time": "2024-03-15T09:00:00Z",
        "end_time": "2024-03-15T17:00:00Z",
        "location": "Main Auditorium",
        "gold_reward_amount": 50.00
      }
    }
  }
}
```

**Error Responses**:

```typescript
// Event at capacity
{
  "success": false,
  "message": "Event has reached maximum capacity",
  "errors": {
    "capacity": ["This event is full and no longer accepting registrations"]
  }
}

// Already registered
{
  "success": false,
  "message": "Already registered for this event",
  "errors": {
    "registration": ["You are already registered for this event"]
  }
}

// Event not available for registration
{
  "success": false,
  "message": "Event is not available for registration",
  "errors": {
    "status": ["This event is not currently accepting registrations"]
  }
}
```

#### Unregister from Event

```typescript
DELETE / api / v1 / events / { id } / register;
```

**Description**: Cancel the student's registration for an event.

**Path Parameters**:

- `id` (number): Event ID

**Response**:

```typescript
{
  "success": true,
  "message": "Successfully unregistered from event",
  "data": {
    "event_id": 1,
    "student_id": 456,
    "unregistered_at": "2024-03-12T10:15:00Z"
  }
}
```

**Error Responses**:

```typescript
// Not registered
{
  "success": false,
  "message": "Not registered for this event",
  "errors": {
    "registration": ["You are not registered for this event"]
  }
}

// Cannot unregister (already checked in)
{
  "success": false,
  "message": "Cannot unregister after check-in",
  "errors": {
    "status": ["You cannot unregister after being checked in to the event"]
  }
}
```

### 3. Student Participation Management

#### Get My Event Participations

```typescript
GET / api / v1 / events / my / participations;
```

**Description**: Retrieve the student's event participation history.

**Query Parameters**:

```typescript
interface MyEventsParams {
    page?: number; // Page number (default: 1)
    per_page?: number; // Items per page (default: 15, max: 50)
    status?: ParticipationStatus; // Filter by participation status
    date_from?: string; // Filter events from date (YYYY-MM-DD)
    date_to?: string; // Filter events to date (YYYY-MM-DD)
}
```

**Response**:

```typescript
{
  "success": true,
  "message": "Event participations retrieved successfully",
  "data": {
    "participations": [
      {
        "id": 123,
        "event_id": 1,
        "student_id": 456,
        "status": "completed",
        "registered_at": "2024-03-10T14:30:00Z",
        "checkin_time": "2024-03-15T08:45:00Z",
        "checkin_device_info": {
          "device_type": "mobile",
          "user_agent": "Mozilla/5.0...",
          "ip_address": "192.168.1.100"
        },
        "checkin_staff_id": 789,
        "gold_awarded": true,
        "awarded_at": "2024-03-15T18:00:00Z",
        "created_at": "2024-03-10T14:30:00Z",
        "updated_at": "2024-03-15T18:00:00Z",
        "event": {
          "id": 1,
          "campus_id": 1,
          "title": "Campus Tech Talk 2024",
          "description": "Annual technology conference...",
          "start_time": "2024-03-15T09:00:00Z",
          "end_time": "2024-03-15T17:00:00Z",
          "location": "Main Auditorium",
          "gold_reward_amount": 50.00,
          "max_participants": 200,
          "qr_code": "EVT_2024_001_ABC123",
          "status": "completed",
          "organizer_type": "school",
          "organizer_id": 1,
          "created_at": "2024-02-28T14:30:00Z",
          "updated_at": "2024-03-15T18:00:00Z"
        },
        "checkin_staff": {
          "id": 789,
          "name": "John Staff",
          "email": "john.staff@university.edu"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 2,
      "per_page": 15,
      "total": 18,
      "from": 1,
      "to": 15
    }
  }
}
```

#### Get My Event Participation Details

```typescript
GET / api / v1 / events / my / { event_id };
```

**Description**: Get detailed information about the student's participation in a specific event.

**Path Parameters**:

- `event_id` (number): Event ID (not participation ID)

**Response**:

```typescript
{
  "success": true,
  "message": "Event participation details retrieved successfully",
  "data": {
    "participation": {
      "id": 123,
      "event_id": 1,
      "student_id": 456,
      "status": "registered",
      "registered_at": "2024-03-10T14:30:00Z",
      "checkin_time": null,
      "checkin_device_info": null,
      "checkin_staff_id": null,
      "gold_awarded": false,
      "awarded_at": null,
      "created_at": "2024-03-10T14:30:00Z",
      "updated_at": "2024-03-10T14:30:00Z",
      "event": {
        "id": 1,
        "campus_id": 1,
        "title": "Campus Tech Talk 2024",
        "description": "Annual technology conference featuring industry leaders...",
        "start_time": "2024-03-15T09:00:00Z",
        "end_time": "2024-03-15T17:00:00Z",
        "location": "Main Auditorium",
        "gold_reward_amount": 50.00,
        "max_participants": 200,
        "qr_code": "EVT_2024_001_ABC123",
        "organizer_type": "school",
        "organizer_id": 1,
        "status": "published",
        "published_at": "2024-03-01T10:00:00Z",
        "cancelled_at": null,
        "completed_at": null,
        "created_at": "2024-02-28T14:30:00Z",
        "updated_at": "2024-03-01T10:00:00Z",
        "creator": {
          "id": 100,
          "name": "Admin User",
          "email": "admin@university.edu"
        },
        "campus": {
          "id": 1,
          "name": "Main Campus",
          "code": "MAIN"
        }
      },
      "checkin_staff": null
    }
  }
}
```

## Error Handling

### Standard Error Response Format

```typescript
interface ErrorResponse {
    success: false;
    message: string;
    errors?: Record<string, string[]>;
    code?: string;
}
```

### Common Error Codes

- `401` - Unauthorized (invalid or missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (event or participation not found)
- `422` - Validation Error (invalid request data)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

### Example Error Responses

```typescript
// Validation Error
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "per_page": ["The per page field must not be greater than 100"]
  }
}

// Authentication Error
{
  "success": false,
  "message": "Unauthenticated",
  "code": "INVALID_TOKEN"
}

// Not Found Error
{
  "success": false,
  "message": "Event not found",
  "code": "EVENT_NOT_FOUND"
}
```

## Rate Limiting

All API endpoints are subject to rate limiting:

- **General endpoints**: 60 requests per minute per user
- **Registration endpoints**: 10 requests per minute per user
- **Statistics endpoints**: 30 requests per minute per user

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
```
