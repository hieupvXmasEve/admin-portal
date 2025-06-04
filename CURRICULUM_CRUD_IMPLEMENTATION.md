# Curriculum Management CRUD Implementation

This document describes the complete CRUD (Create, Read, Update, Delete) implementation for the curriculum management system. The implementation includes optimized and user-friendly UI components built with Laravel, Vue.js, and Inertia.js.

## Overview

The curriculum management system includes three main entities:
1. **Programs** - Academic degree programs (Bachelor, Master, PhD)
2. **Curriculum Versions** - Versioned curriculum structures for programs and specializations
3. **Curriculum Units** - Individual units within curriculum versions

## Backend Implementation

### Controllers

#### 1. ProgramController
**File**: `app/Http/Controllers/ProgramController.php`

**Features**:
- Full CRUD operations with validation
- Search and filtering by degree level
- Bulk delete operations
- Relationship counts (specializations, curriculum versions)
- Permission-based access control

**Key Methods**:
```php
public function index(Request $request): Response
public function create(): Response  
public function store(StoreProgramRequest $request): RedirectResponse
public function show(Program $program): Response
public function edit(Program $program): Response
public function update(UpdateProgramRequest $request, Program $program): RedirectResponse
public function destroy(Program $program): RedirectResponse
public function search(Request $request) // API endpoint
public function bulkDelete(Request $request) // API endpoint
```

#### 2. CurriculumVersionController
**File**: `app/Http/Controllers/CurriculumVersionController.php`

**Features**:
- CRUD operations with program/specialization relationships
- Dynamic specialization loading based on program selection
- Scope-based filtering (program-level vs specialization-level)
- Effective semester tracking
- Bulk operations

**Key Methods**:
```php
public function index(Request $request): Response
public function create(): Response
public function store(StoreCurriculumVersionRequest $request): RedirectResponse
public function show(CurriculumVersion $curriculumVersion): Response
public function edit(CurriculumVersion $curriculumVersion): Response
public function update(UpdateCurriculumVersionRequest $request, CurriculumVersion $curriculumVersion): RedirectResponse
public function destroy(CurriculumVersion $curriculumVersion): RedirectResponse
public function getSpecializationsByProgram(Request $request) // API endpoint
public function bulkDelete(Request $request) // API endpoint
```

#### 3. CurriculumUnitController
**File**: `app/Http/Controllers/CurriculumUnitController.php`

**Features**:
- CRUD for curriculum units with unit type classification
- Semester ordering and compulsory status management
- Advanced filtering by curriculum version, unit type, semester order
- Unit relationship management
- Bulk operations

**Key Methods**:
```php
public function index(Request $request): Response
public function create(): Response
public function store(StoreCurriculumUnitRequest $request): RedirectResponse
public function show(CurriculumUnit $curriculumUnit): Response
public function edit(CurriculumUnit $curriculumUnit): Response
public function update(UpdateCurriculumUnitRequest $request, CurriculumUnit $curriculumUnit): RedirectResponse
public function destroy(CurriculumUnit $curriculumUnit): RedirectResponse
public function getUnitsByCurriculumVersion(Request $request) // API endpoint
public function bulkDelete(Request $request) // API endpoint
```

### Form Request Validation

#### StoreProgramRequest & UpdateProgramRequest
```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255', 'unique:programs,name'],
        'degree_level' => ['required', 'string', 'in:bachelor,master,phd'],
    ];
}
```

#### StoreCurriculumVersionRequest & UpdateCurriculumVersionRequest
```php
public function rules(): array
{
    return [
        'program_id' => ['required', 'exists:programs,id'],
        'specialization_id' => ['nullable', 'exists:specializations,id'],
        'version_code' => ['required', 'string', 'max:50'],
        'effective_from_semester_id' => ['nullable', 'exists:semesters,id'],
        'scope' => ['nullable', 'string', 'in:program,specialization'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ];
}
```

#### StoreCurriculumUnitRequest & UpdateCurriculumUnitRequest
```php
public function rules(): array
{
    return [
        'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
        'unit_id' => ['required', 'exists:units,id'],
        'unit_type_id' => ['nullable', 'exists:curriculum_unit_types,id'],
        'semester_order' => ['nullable', 'integer', 'min:1', 'max:12'],
        'is_compulsory' => ['boolean'],
        'note' => ['nullable', 'string', 'max:1000'],
    ];
}
```

### Routes Configuration
**File**: `routes/curriculum.php`

```php
// Web routes for Inertia.js pages
Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'programs',
        controller: ProgramController::class,
        module: 'programs'
    );

    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'curriculum-versions',
        controller: CurriculumVersionController::class,
        module: 'curriculum_versions'
    );

    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'curriculum-units',
        controller: CurriculumUnitController::class,
        module: 'curriculum_units'
    );
});

// API routes for AJAX calls
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {
    Route::prefix('programs')->name('programs.')->group(function () {
        Route::get('search', [ProgramController::class, 'search'])->name('search');
        Route::delete('bulk-delete', [ProgramController::class, 'bulkDelete'])->name('bulk-delete');
    });

    Route::prefix('curriculum-versions')->name('curriculum-versions.')->group(function () {
        Route::get('specializations-by-program', [CurriculumVersionController::class, 'getSpecializationsByProgram'])
            ->name('specializations-by-program');
        Route::delete('bulk-delete', [CurriculumVersionController::class, 'bulkDelete'])->name('bulk-delete');
    });

    Route::prefix('curriculum-units')->name('curriculum-units.')->group(function () {
        Route::get('by-curriculum-version', [CurriculumUnitController::class, 'getUnitsByCurriculumVersion'])
            ->name('by-curriculum-version');
        Route::delete('bulk-delete', [CurriculumUnitController::class, 'bulkDelete'])->name('bulk-delete');
    });
});
```

## Frontend Implementation

### Vue.js Components Structure

#### 1. Programs Management
**Base Path**: `resources/js/pages/programs/`

**Components**:
- `Index.vue` - Programs listing with search, filters, and bulk actions
- `Create.vue` - Program creation form
- `Edit.vue` - Program editing form  
- `Show.vue` - Program details view

**Key Features**:
- Advanced search and filtering
- Degree level badges with color coding
- Responsive table with action dropdowns
- Bulk delete with confirmation dialogs
- Real-time validation feedback
- Toast notifications for user feedback

#### 2. Curriculum Versions Management
**Base Path**: `resources/js/pages/curriculum-versions/`

**Components**:
- `Index.vue` - Curriculum versions listing
- `Create.vue` - Creation form with dynamic specialization loading
- `Edit.vue` - Editing form
- `Show.vue` - Detailed view with curriculum units

**Key Features**:
- Program/specialization hierarchical selection
- Scope-based filtering (program vs specialization level)
- Effective semester selection
- Curriculum units preview
- Advanced filtering and search

#### 3. Curriculum Units Management
**Base Path**: `resources/js/pages/curriculum-units/`

**Components**:
- `Index.vue` - Units listing with advanced filters
- `Create.vue` - Unit assignment form
- `Edit.vue` - Unit modification form
- `Show.vue` - Unit details

**Key Features**:
- Unit type classification (core, elective, major)
- Semester ordering (1-12)
- Compulsory status toggle
- Curriculum version filtering
- Unit search and selection
- Notes and annotations

### UI/UX Design Principles

#### 1. Consistent Design System
- **shadcn/vue** components for consistent styling
- **TailwindCSS** for responsive design
- **Lucide icons** for visual consistency
- **Card-based layouts** for content organization

#### 2. Navigation and Layout
```vue
<AppShell>
  <template #header>
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Title</h1>
        <p class="text-sm text-gray-600">Description</p>
      </div>
      <div class="flex space-x-3">
        <!-- Action buttons -->
      </div>
    </div>
  </template>
  <!-- Content -->
</AppShell>
```

#### 3. Data Tables
- **Responsive tables** with horizontal scrolling
- **Action dropdowns** for row-level operations
- **Bulk selection** with checkbox controls
- **Pagination** with DataPagination component
- **Loading states** during data fetching

#### 4. Forms
- **Real-time validation** with error display
- **Dynamic field loading** (e.g., specializations based on program)
- **Required field indicators** (*)
- **Loading states** on form submission
- **Success/error notifications**

#### 5. Filtering and Search
```vue
<Card>
  <CardContent class="p-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <Label for="search">Search</Label>
        <Input v-model="filters.search" placeholder="Search..." />
      </div>
      <div>
        <Label for="filter">Filter</Label>
        <Select v-model="filters.category">
          <!-- Options -->
        </Select>
      </div>
      <div class="flex items-end space-x-2">
        <Button @click="applyFilters">Search</Button>
        <Button @click="clearFilters" variant="ghost">Clear</Button>
      </div>
    </div>
  </CardContent>
</Card>
```

## User Experience Features

### 1. Responsive Design
- **Mobile-first approach** with responsive breakpoints
- **Touch-friendly interactions** for mobile devices
- **Optimized layouts** for different screen sizes

### 2. Performance Optimization
- **Debounced search** to reduce API calls
- **Lazy loading** for large datasets
- **Efficient pagination** with query string preservation
- **Optimistic UI updates** where appropriate

### 3. Accessibility
- **Semantic HTML** structure
- **ARIA labels** for screen readers
- **Keyboard navigation** support
- **High contrast** color schemes

### 4. User Feedback
- **Toast notifications** for actions (success/error)
- **Loading spinners** during async operations
- **Confirmation dialogs** for destructive actions
- **Form validation messages** with clear instructions

### 5. Data Visualization
- **Badge components** for status indicators
- **Progress indicators** for completion status
- **Color-coded categories** for quick identification
- **Count displays** for relationship data

## Permission System Integration

### 1. Role-Based Access Control
```php
// In controllers
$this->authorize('create', Program::class);

// In blade/vue templates
@can('edit_program')
  <EditButton />
@endcan
```

### 2. Frontend Permission Checking
```vue
<script setup>
const can = (permission: string) => {
  const permissions = page.props.permissions || []
  return permissions.includes(permission)
}
</script>

<template>
  <Button v-if="can('create_program')" @click="create">
    Create Program
  </Button>
</template>
```

### 3. Route Protection
```php
RoutePermissionHelper::resourceWithPermissions(
    prefix: 'programs',
    controller: ProgramController::class,
    module: 'programs'
);
```

## API Endpoints

### Programs API
- `GET /api/programs/search?q=term` - Search programs
- `DELETE /api/programs/bulk-delete` - Bulk delete programs

### Curriculum Versions API  
- `GET /api/curriculum-versions/specializations-by-program?program_id=1` - Get specializations
- `DELETE /api/curriculum-versions/bulk-delete` - Bulk delete versions

### Curriculum Units API
- `GET /api/curriculum-units/by-curriculum-version?curriculum_version_id=1` - Get units
- `DELETE /api/curriculum-units/bulk-delete` - Bulk delete units

## Error Handling

### 1. Validation Errors
```php
public function store(StoreProgramRequest $request)
{
    // Validation happens automatically
    // Errors returned to frontend via Inertia
}
```

### 2. Frontend Error Display
```vue
<Input 
  v-model="form.name"
  :class="{ 'border-red-500': errors.name }"
/>
<p v-if="errors.name" class="text-sm text-red-600">
  {{ errors.name }}
</p>
```

### 3. Exception Handling
```php
try {
    DB::beginTransaction();
    // Operations
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    Log::error('Operation failed: ' . $e->getMessage());
    return back()->withErrors(['error' => 'Operation failed']);
}
```

## Testing Strategy

### 1. Backend Testing
```php
// Feature tests
it('can create a program', function () {
    $response = $this->post('/programs', [
        'name' => 'Computer Science',
        'degree_level' => 'bachelor'
    ]);
    
    $response->assertRedirect('/programs');
    $this->assertDatabaseHas('programs', ['name' => 'Computer Science']);
});

// Validation tests
it('validates required fields', function () {
    $response = $this->post('/programs', []);
    $response->assertSessionHasErrors(['name', 'degree_level']);
});
```

### 2. Frontend Testing
```javascript
// Component tests with Vue Test Utils
import { mount } from '@vue/test-utils'
import ProgramIndex from '@/pages/programs/Index.vue'

test('displays programs correctly', () => {
  const wrapper = mount(ProgramIndex, {
    props: { programs: mockPrograms }
  })
  
  expect(wrapper.text()).toContain('Computer Science')
})
```

## Installation and Setup

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Seed Database
```bash
php artisan db:seed --class=CurriculumUnitTypeSeeder
php artisan db:seed --class=CurriculumSchemaSeeder
```

### 3. Install Frontend Dependencies
```bash
npm install
npm run dev
```

### 4. Set Permissions
Configure permissions in your permission system for:
- `create_program`, `edit_program`, `delete_program`, `view_program`
- `create_curriculum_version`, `edit_curriculum_version`, `delete_curriculum_version`, `view_curriculum_version`  
- `create_curriculum_unit`, `edit_curriculum_unit`, `delete_curriculum_unit`, `view_curriculum_unit`

## Future Enhancements

### 1. Advanced Features
- **Import/Export functionality** for bulk operations
- **Version comparison** for curriculum versions
- **Audit trails** for change tracking
- **Advanced reporting** and analytics

### 2. UI Improvements
- **Drag-and-drop** for curriculum unit ordering
- **Visual curriculum builder** with flowcharts
- **Dashboard widgets** for quick insights
- **Dark mode** support

### 3. Performance Optimizations
- **Caching strategies** for frequently accessed data
- **Background jobs** for heavy operations
- **Real-time updates** with WebSockets
- **Progressive loading** for large datasets

This implementation provides a comprehensive, user-friendly curriculum management system that follows Laravel and Vue.js best practices while maintaining excellent user experience and performance. 
