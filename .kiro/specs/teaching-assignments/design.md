# Design Document

## Overview

The Teaching Assignments module is a comprehensive administrative interface that enables academic administrators to manage lecturer assignments to course offerings within the existing Laravel-based academic management system. The module integrates seamlessly with the current database schema, utilizing existing relationships between lecturers (`lectures`), course offerings (`course_offerings`), semesters (`semesters`), curriculum units (`curriculum_units`), and units (`units`).

The design follows the established patterns in the codebase, including the service-repository pattern, Laravel resource transformers, Vue.js frontend with TypeScript, and the existing permission system. The module provides real-time conflict detection, comprehensive search and filtering capabilities, and export functionality while maintaining data integrity through proper validation and authorization.

## Architecture

### Backend Architecture

The backend follows the established Laravel architecture patterns:

- **Controller Layer**: `TeachingAssignmentController` handles HTTP requests and delegates business logic to services
- **Service Layer**: `TeachingAssignmentService` contains core business logic for assignment management and conflict detection
- **Repository Layer**: Utilizes existing Eloquent models with custom query scopes for data access
- **Resource Layer**: API resource transformers for consistent data formatting
- **Validation Layer**: Form request classes for input validation
- **Authorization Layer**: Integration with existing permission system

### Frontend Architecture

The frontend follows the established Vue.js 3 + TypeScript patterns:

- **Pages**: Main teaching assignments interface with data table and assignment modals
- **Components**: Reusable components for lecturer selection, conflict display, and export functionality
- **Composables**: Custom hooks for data fetching, state management, and business logic
- **Stores**: Pinia stores for centralized state management
- **Types**: TypeScript interfaces matching backend data structures

### Database Integration

The module leverages existing database relationships without requiring schema changes:

- **Primary Tables**: `lectures`, `course_offerings`, `semesters`, `curriculum_units`, `units`, `campuses`
- **Relationship Tables**: Existing foreign key relationships are utilized
- **Permission Tables**: `campus_user_roles`, `role_permissions` for authorization

## Components and Interfaces

### Backend Components

#### TeachingAssignmentController
```php
class TeachingAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    public function assign(AssignLecturerRequest $request): JsonResponse
    public function unassign(int $courseOfferingId): JsonResponse
    public function availableLecturers(int $courseOfferingId): JsonResponse
    public function checkConflicts(ConflictCheckRequest $request): JsonResponse
    public function export(ExportRequest $request): BinaryFileResponse
}
```

#### TeachingAssignmentService
```php
class TeachingAssignmentService
{
    public function getAssignments(array $filters): LengthAwarePaginator
    public function assignLecturer(int $courseOfferingId, int $lecturerId): bool
    public function unassignLecturer(int $courseOfferingId): bool
    public function getAvailableLecturers(int $courseOfferingId, array $filters): Collection
    public function checkScheduleConflicts(int $lecturerId, int $courseOfferingId): array
    public function exportAssignments(array $filters, string $format): string
}
```

#### Form Request Classes
```php
class AssignLecturerRequest extends FormRequest
{
    public function rules(): array
    public function authorize(): bool
}

class ConflictCheckRequest extends FormRequest
{
    public function rules(): array
}

class ExportRequest extends FormRequest
{
    public function rules(): array
}
```

#### API Resources
```php
class TeachingAssignmentResource extends JsonResource
{
    public function toArray($request): array
}

class AvailableLecturerResource extends JsonResource
{
    public function toArray($request): array
}

class ConflictResource extends JsonResource
{
    public function toArray($request): array
}
```

### Frontend Components

#### Main Page Component
```typescript
// TeachingAssignments.vue
interface TeachingAssignmentsProps {
  initialSemester?: number
}

interface TeachingAssignmentData {
  assignments: PaginatedResponse<TeachingAssignment>
  loading: boolean
  filters: AssignmentFilters
  selectedAssignment: TeachingAssignment | null
}
```

#### Assignment Modal Component
```typescript
// AssignmentModal.vue
interface AssignmentModalProps {
  courseOffering: CourseOffering
  isOpen: boolean
}

interface AssignmentModalEmits {
  close: []
  assigned: [lecturerId: number]
}
```

#### Lecturer Selection Component
```typescript
// LecturerSelector.vue
interface LecturerSelectorProps {
  courseOfferingId: number
  currentLecturerId?: number
}

interface LecturerSelectorData {
  availableLecturers: AvailableLecturer[]
  selectedLecturerId: number | null
  conflicts: ScheduleConflict[]
}
```

#### Conflict Display Component
```typescript
// ConflictDisplay.vue
interface ConflictDisplayProps {
  conflicts: ScheduleConflict[]
}
```

### Data Models and Interfaces

#### TypeScript Interfaces
```typescript
interface TeachingAssignment {
  id: number
  semester: Semester
  unit: Unit
  sectionCode: string
  lecturer: Lecturer | null
  scheduleDays: string[]
  scheduleTimeStart: string
  scheduleTimeEnd: string
  deliveryMode: string
  enrollmentStatus: string
  currentEnrollment: number
  maxCapacity: number
  location: string
}

interface AvailableLecturer {
  id: number
  employeeId: string
  fullName: string
  displayName: string
  department: string
  faculty: string
  academicRank: string
  employmentStatus: string
  isAvailableForAssignment: boolean
  currentCourseLoad: number
}

interface ScheduleConflict {
  conflictingCourseId: number
  unitCode: string
  unitName: string
  sectionCode: string
  scheduleDays: string[]
  scheduleTimeStart: string
  scheduleTimeEnd: string
  conflictType: 'time_overlap' | 'same_time'
}

interface AssignmentFilters {
  semesterId?: number
  campusId?: number
  faculty?: string
  department?: string
  unitCode?: string
  lecturerName?: string
  enrollmentStatus?: string
  assignmentStatus?: 'assigned' | 'unassigned' | 'all'
}
```

## Data Models

### Existing Model Enhancements

The design leverages existing Eloquent models with additional query scopes and methods:

#### Lecture Model Enhancements
```php
// Additional scopes for teaching assignments
public function scopeAvailableForTeaching(Builder $query, int $courseOfferingId): void
public function scopeWithCurrentLoad(Builder $query): void
public function scopeByFacultyAndDepartment(Builder $query, ?string $faculty, ?string $department): void

// Additional methods
public function hasScheduleConflictWith(CourseOffering $courseOffering): bool
public function getConflictingCourses(CourseOffering $courseOffering): Collection
public function getCurrentSemesterLoad(): int
```

#### CourseOffering Model Enhancements
```php
// Additional scopes
public function scopeWithAssignmentDetails(Builder $query): void
public function scopeByAssignmentStatus(Builder $query, string $status): void
public function scopeForTeachingAssignments(Builder $query): void

// Additional methods
public function getAssignmentPriority(): string
public function canBeAssignedTo(Lecture $lecturer): bool
public function getScheduleConflictsWith(Lecture $lecturer): Collection
```

### Data Transformation

#### Assignment List Data Structure
```php
[
    'id' => 'course_offering_id',
    'semester' => [
        'id' => 'semester_id',
        'name' => 'semester_name',
        'code' => 'semester_code'
    ],
    'unit' => [
        'id' => 'unit_id',
        'code' => 'unit_code',
        'name' => 'unit_name',
        'creditPoints' => 'credit_points'
    ],
    'sectionCode' => 'section_code',
    'lecturer' => [
        'id' => 'lecture_id',
        'employeeId' => 'employee_id',
        'fullName' => 'full_name',
        'displayName' => 'display_name',
        'department' => 'department',
        'faculty' => 'faculty'
    ] | null,
    'schedule' => [
        'days' => ['Monday', 'Wednesday'],
        'timeStart' => '09:00',
        'timeEnd' => '10:30',
        'location' => 'Room A101'
    ],
    'enrollment' => [
        'current' => 25,
        'maximum' => 30,
        'status' => 'open'
    ],
    'deliveryMode' => 'in_person',
    'assignmentStatus' => 'assigned' | 'unassigned' | 'urgent'
]
```

## Error Handling

### Backend Error Handling

#### Validation Errors
- **Invalid Course Offering**: Return 404 with appropriate message
- **Invalid Lecturer**: Return 422 with validation errors
- **Schedule Conflicts**: Return 422 with detailed conflict information
- **Permission Denied**: Return 403 with authorization error

#### Business Logic Errors
- **Lecturer Unavailable**: Custom exception with availability details
- **Assignment Conflicts**: Custom exception with conflict details
- **Database Constraints**: Handle foreign key violations gracefully

#### Error Response Format
```php
[
    'message' => 'Human readable error message',
    'errors' => [
        'field_name' => ['Specific validation error']
    ],
    'data' => [
        'conflicts' => [...], // For schedule conflicts
        'suggestions' => [...] // Alternative lecturers
    ]
]
```

### Frontend Error Handling

#### API Error Handling
- **Network Errors**: Display retry mechanism with user-friendly message
- **Validation Errors**: Show field-specific error messages
- **Authorization Errors**: Redirect to login or show permission denied message
- **Conflict Errors**: Display detailed conflict information with resolution options

#### User Experience
- **Loading States**: Show appropriate loading indicators during API calls
- **Success Feedback**: Display confirmation messages for successful operations
- **Error Recovery**: Provide clear actions for error resolution
- **Optimistic Updates**: Update UI immediately with rollback on failure

## Testing Strategy

### Backend Testing

#### Unit Tests
- **Service Layer**: Test business logic, conflict detection, and data transformation
- **Model Methods**: Test custom scopes, relationships, and computed properties
- **Validation**: Test form request validation rules and custom validators

#### Feature Tests
- **API Endpoints**: Test all controller methods with various scenarios
- **Authorization**: Test permission-based access control
- **Database Interactions**: Test data integrity and constraint handling

#### Integration Tests
- **Conflict Detection**: Test complex scheduling conflict scenarios
- **Export Functionality**: Test data export in different formats
- **Permission Integration**: Test role-based access across different user types

### Frontend Testing

#### Component Tests
- **Assignment Table**: Test data display, sorting, and filtering
- **Assignment Modal**: Test lecturer selection and conflict display
- **Search and Filters**: Test real-time filtering and search functionality

#### Integration Tests
- **API Integration**: Test data fetching and state management
- **User Workflows**: Test complete assignment workflows
- **Error Scenarios**: Test error handling and recovery

#### End-to-End Tests
- **Complete Assignment Flow**: Test full lecturer assignment process
- **Conflict Resolution**: Test conflict detection and resolution workflow
- **Export Functionality**: Test report generation and download

### Test Data Strategy

#### Database Seeding
- **Test Lecturers**: Create lecturers with various availability and constraints
- **Test Course Offerings**: Create offerings with different schedules and requirements
- **Test Conflicts**: Set up scenarios with known scheduling conflicts
- **Permission Data**: Create test users with different permission levels

#### Mock Data
- **API Responses**: Mock API responses for frontend testing
- **Conflict Scenarios**: Mock various conflict detection scenarios
- **Export Data**: Mock export functionality for testing

## Performance Considerations

### Database Optimization

#### Query Optimization
- **Eager Loading**: Load related models (semester, unit, lecturer) in single queries
- **Selective Loading**: Only load required fields for list views
- **Indexed Queries**: Utilize existing database indexes for filtering and sorting

#### Caching Strategy
- **Available Lecturers**: Cache frequently accessed lecturer availability data
- **Semester Data**: Cache active semester information
- **Permission Data**: Cache user permissions for authorization checks

### Frontend Optimization

#### Data Loading
- **Pagination**: Implement server-side pagination for large datasets
- **Lazy Loading**: Load assignment details on demand
- **Debounced Search**: Implement search debouncing to reduce API calls

#### State Management
- **Selective Updates**: Update only changed data in the store
- **Optimistic Updates**: Provide immediate feedback with rollback capability
- **Memory Management**: Clean up unused data and event listeners

### Scalability Considerations

#### Backend Scalability
- **Service Separation**: Separate conflict detection into dedicated service
- **Queue Processing**: Use queues for heavy operations like bulk exports
- **Database Partitioning**: Consider partitioning by semester for large datasets

#### Frontend Scalability
- **Component Lazy Loading**: Load components on demand
- **Virtual Scrolling**: Implement virtual scrolling for large lists
- **Progressive Loading**: Load data progressively as needed
