# Design Document

## Overview

The Admin Schedule Management system provides administrators with a comprehensive interface to view and manage class schedules through a visual grid-based calendar layout. The system focuses on displaying class sessions in a weekly timetable format with filtering capabilities and the ability to edit session details through modal/drawer interfaces. The design emphasizes read-only browsing with controlled editing capabilities to prevent accidental schedule disruptions.

## Architecture

### System Components

The system follows the existing Laravel + Vue.js architecture pattern with the following key components:

- **Backend API Layer**: RESTful endpoints for schedule data retrieval and updates
- **Service Layer**: Business logic for schedule management and conflict validation
- **Frontend Components**: Vue.js components for grid display, filtering, and editing
- **Data Layer**: Existing database models with relationships

### Technology Stack

- **Backend**: Laravel 12 with PHP 8.4+, MySQL 8.0
- **Frontend**: Vue.js 3 with TypeScript, TailwindCSS, Reka-UI components
- **Authentication**: Laravel Sanctum for API authentication
- **Validation**: Backend form requests and frontend Zod schemas

## Components and Interfaces

### Backend Components

#### API Controller
```php
class AdminScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    public function show(ClassSession $session): JsonResponse  
    public function update(UpdateScheduleRequest $request, ClassSession $session): JsonResponse
}
```

#### Service Layer
```php
class AdminScheduleService
{
    public function getScheduleData(array $filters): Collection
    public function updateSession(ClassSession $session, array $data): ClassSession
    public function validateScheduleConflicts(ClassSession $session, array $newData): array
}
```

#### Form Requests
```php
class UpdateScheduleRequest extends FormRequest
{
    public function rules(): array
    public function authorize(): bool
}
```

#### API Resources
```php
class ScheduleSessionResource extends JsonResource
{
    public function toArray($request): array
}
```

### Frontend Components

#### Main Schedule Grid Component
```typescript
// ScheduleGridView.vue
interface ScheduleGridProps {
  filters: ScheduleFilters
  selectedWeek: Date
}

interface ScheduleSession {
  id: number
  title: string
  unitCode: string
  section: string
  lecturer: string
  room: string
  startTime: string
  endTime: string
  date: string
  campusName: string
}
```

#### Filter Panel Component
```typescript
// ScheduleFilterPanel.vue
interface ScheduleFilters {
  semesterId?: number
  lecturerId?: number
  roomId?: number
  dateRange?: {
    start: Date
    end: Date
  }
}
```

#### Session Edit Modal/Drawer
```typescript
// ScheduleEditDrawer.vue
interface SessionEditForm {
  sessionDate: string
  startTime: string
  endTime: string
  roomId: number
}
```

### API Endpoints

#### Schedule Management Endpoints
- `GET /api/admin/schedules` - Retrieve schedule data with filters
- `GET /api/admin/schedules/{session}` - Get detailed session information
- `PATCH /api/admin/schedules/{session}` - Update session details
- `GET /api/admin/schedules/filters` - Get filter options (semesters, lecturers, rooms)

#### Filter Data Endpoints
- `GET /api/admin/schedules/semesters` - Available semesters
- `GET /api/admin/schedules/lecturers` - Available lecturers
- `GET /api/admin/schedules/rooms` - Available rooms

## Data Models

### Primary Models Used

#### ClassSession Model (Existing)
```php
class ClassSession extends Model
{
    protected $fillable = [
        'session_date',
        'start_time', 
        'end_time',
        'room_id',
        // ... other fields
    ];
    
    // Relationships
    public function courseOffering(): BelongsTo
    public function lecture(): BelongsTo  
    public function room(): BelongsTo
}
```

#### Related Models
- **CourseOffering**: Links to curriculum units and provides course context
- **Lecture**: Provides lecturer information and availability
- **Room**: Room details and availability constraints
- **Semester**: Semester context for filtering
- **Campus**: Campus information (retrieved through relationships)

### Data Flow

1. **Schedule Retrieval**: Frontend requests schedule data with filters
2. **Data Processing**: Service layer processes filters and retrieves sessions with relationships
3. **Grid Population**: Frontend receives formatted data and populates the weekly grid
4. **Session Editing**: User clicks session → modal opens with current data
5. **Validation**: Frontend validates changes, backend validates conflicts
6. **Update**: Successful validation leads to database update and grid refresh

## Error Handling

### Backend Error Handling

#### Validation Errors
```php
// Form Request validation
public function rules(): array
{
    return [
        'session_date' => 'required|date',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'room_id' => 'required|exists:rooms,id'
    ];
}
```

#### Conflict Detection
```php
class AdminScheduleService
{
    public function validateScheduleConflicts(ClassSession $session, array $newData): array
    {
        $conflicts = [];
        
        // Check room conflicts
        $roomConflicts = $this->checkRoomConflicts($session, $newData);
        if ($roomConflicts->isNotEmpty()) {
            $conflicts['room'] = 'Room is already booked for this time slot';
        }
        
        // Check lecturer conflicts  
        $lecturerConflicts = $this->checkLecturerConflicts($session, $newData);
        if ($lecturerConflicts->isNotEmpty()) {
            $conflicts['lecturer'] = 'Lecturer has another session at this time';
        }
        
        return $conflicts;
    }
}
```

### Frontend Error Handling

#### Form Validation
```typescript
// Zod schema for session editing
const sessionEditSchema = z.object({
  sessionDate: z.string().min(1, 'Date is required'),
  startTime: z.string().regex(/^\d{2}:\d{2}$/, 'Invalid time format'),
  endTime: z.string().regex(/^\d{2}:\d{2}$/, 'Invalid time format'),
  roomId: z.number().min(1, 'Room is required')
}).refine(data => data.endTime > data.startTime, {
  message: 'End time must be after start time',
  path: ['endTime']
});
```

#### API Error Handling
```typescript
// Error handling in composables
const updateSession = async (sessionId: number, data: SessionEditForm) => {
  try {
    const response = await api.patch(`/admin/schedules/${sessionId}`, data);
    return response.data;
  } catch (error) {
    if (error.response?.status === 422) {
      // Validation errors
      throw new ValidationError(error.response.data.errors);
    } else if (error.response?.status === 409) {
      // Conflict errors
      throw new ConflictError(error.response.data.message);
    }
    throw new Error('Failed to update session');
  }
};
```

## Testing Strategy

### Backend Testing

#### Unit Tests
```php
class AdminScheduleServiceTest extends TestCase
{
    public function test_can_retrieve_schedule_data_with_filters()
    public function test_can_update_session_details()
    public function test_detects_room_conflicts()
    public function test_detects_lecturer_conflicts()
    public function test_validates_session_time_constraints()
}

class AdminScheduleControllerTest extends TestCase  
{
    public function test_index_returns_filtered_schedule_data()
    public function test_show_returns_session_details()
    public function test_update_validates_and_updates_session()
    public function test_update_returns_conflict_errors()
}
```

#### Feature Tests
```php
class AdminScheduleManagementTest extends TestCase
{
    public function test_admin_can_view_schedule_grid()
    public function test_admin_can_filter_by_semester()
    public function test_admin_can_filter_by_lecturer()
    public function test_admin_can_edit_session_details()
    public function test_admin_cannot_create_conflicting_sessions()
}
```

### Frontend Testing

#### Component Tests
```typescript
// ScheduleGridView.test.ts
describe('ScheduleGridView', () => {
  it('displays sessions in correct time slots')
  it('shows session details on click')
  it('applies filters correctly')
  it('handles empty time slots')
})

// ScheduleEditDrawer.test.ts  
describe('ScheduleEditDrawer', () => {
  it('loads current session data')
  it('validates form inputs')
  it('submits changes successfully')
  it('displays conflict errors')
})
```

#### Integration Tests
```typescript
// Schedule management flow tests
describe('Schedule Management Flow', () => {
  it('loads schedule data and displays grid')
  it('filters schedule by semester')
  it('edits session and updates grid')
  it('handles validation errors gracefully')
})
```

### Performance Testing

#### Database Query Optimization
- Index optimization for schedule queries with filters
- Eager loading of relationships to prevent N+1 queries
- Query result caching for filter options

#### Frontend Performance
- Virtual scrolling for large datasets
- Debounced filter inputs
- Optimistic updates for better UX

## Security Considerations

### Authorization
- Admin-only access to schedule management endpoints
- Permission-based access control using existing permission system
- Session-based authentication validation

### Data Validation
- Server-side validation for all schedule updates
- Conflict detection to prevent double-bookings
- Input sanitization and XSS prevention

### Audit Trail
- Log all schedule modifications with user information
- Track changes to critical session data
- Maintain history of schedule conflicts and resolutions
