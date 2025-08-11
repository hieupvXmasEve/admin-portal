# Design Document

## Overview

The lecture import/export feature will extend the existing academic management system by adding bulk data operations for lecturer management. The design follows the established patterns from the user import/export implementation, ensuring consistency in architecture, user experience, and code organization.

The feature consists of two main components:
1. **Import System**: Allows administrators to upload Excel files and bulk create/update lecturer records
2. **Export System**: Enables administrators to download lecturer data in Excel format with filtering options

## Architecture

### Component Structure

```
app/Http/Controllers/Web/Lectures/
├── LectureImportController.php    # Handles import operations
└── LectureExportController.php    # Handles export operations

app/Services/
├── LectureExcelImportService.php  # Business logic for imports
└── LectureExcelExportService.php  # Business logic for exports

routes/web/
└── lectures.php                   # Extended with import/export routes

resources/js/pages/lectures/
├── Index.vue                      # Updated with import/export buttons
└── Import.vue                     # New import form page

app/Constants/
└── LectureRoutes.php             # Extended with new route constants
```

### Service Layer Architecture

The design leverages Laravel's service layer pattern to separate business logic from controllers:

- **LectureExcelImportService**: Handles file processing, data validation, and bulk insertion
- **LectureExcelExportService**: Manages data querying, formatting, and Excel generation
- **Controllers**: Thin layer handling HTTP requests/responses and delegating to services

## Components and Interfaces

### 1. LectureImportController

**Purpose**: Handle HTTP requests for import operations

**Key Methods**:
- `showImportForm()`: Display import form page
- `uploadFile(Request $request)`: Handle file upload and preview generation
- `previewImport(Request $request)`: Generate preview of import data
- `processImport(Request $request)`: Execute the import operation
- `downloadTemplate(string $format)`: Generate and download import templates
- `getImportHistory()`: Retrieve import operation history

**Validation Rules**:
```php
'file' => 'required|file|mimes:xlsx,xls|max:2048'
'duplicate_handling' => 'nullable|in:skip,update,error'
'create_missing_campuses' => 'nullable|boolean'
```

### 2. LectureExportController

**Purpose**: Handle HTTP requests for export operations

**Key Methods**:
- `exportExcel()`: Export all lecturer data
- `exportExcelWithCurrentFilters(Request $request)`: Export filtered lecturer data

**Response Format**:
- Returns `BinaryFileResponse` for successful downloads
- Returns `JsonResponse` with error details for failures

### 3. LectureExcelImportService

**Purpose**: Core business logic for processing Excel imports

**Key Methods**:
- `previewImportData(string $filePath, int $rows)`: Generate preview data
- `importLecturersFromExcel(string $filePath, array $options)`: Process full import
- `validateLecturerData(array $data)`: Validate individual lecturer records
- `handleDuplicates(array $data, string $strategy)`: Manage duplicate handling

**Data Processing Flow**:
1. Read Excel file using PhpSpreadsheet
2. Parse and normalize data
3. Validate against Lecture model rules
4. Handle duplicates based on strategy
5. Bulk insert/update records
6. Generate summary report

**Expected Excel Columns**:
```
employee_id*, first_name*, last_name*, email*, campus_code*, 
academic_rank*, hire_date*, employment_type*, employment_status*,
title, phone, mobile_phone, department, faculty, specialization,
expertise_areas, highest_degree, degree_field, alma_mater,
graduation_year, contract_start_date, contract_end_date,
preferred_teaching_days, max_teaching_hours_per_week,
teaching_modalities, office_address, office_phone, biography,
certifications, languages, hourly_rate, salary, is_active,
can_teach_online, is_available_for_assignment, notes
```

### 4. LectureExcelExportService

**Purpose**: Generate Excel exports of lecturer data

**Key Methods**:
- `exportLecturersToExcel(array $filters = [])`: Main export method
- `buildQuery(array $filters)`: Apply filters to lecturer query
- `formatDataForExport(Collection $lecturers)`: Format data for Excel output
- `generateExcelFile(array $data)`: Create Excel file using PhpSpreadsheet

**Export Data Structure**:
- All lecturer fields in human-readable format
- Related data (campus name, not just ID)
- Formatted dates and boolean values
- Array fields as comma-separated strings

### 5. Frontend Components

#### Updated Index.vue
**New Elements**:
- Import button with dropdown (Import Data, Download Template)
- Export button with dropdown (Export All, Export Filtered)
- Permission-based visibility using existing permission system

#### New Import.vue
**Features**:
- File upload with drag-and-drop
- Template download options
- Data preview table
- Import configuration options
- Progress indicators
- Result summary display

## Data Models

### Import Data Validation

The import service will validate data against the existing Lecture model validation rules:

**Required Fields**:
- employee_id (unique)
- first_name, last_name
- email (unique)
- campus_id (must exist in campuses table)
- academic_rank (enum validation)
- hire_date (date format)
- employment_type (enum validation)
- employment_status (enum validation)

**Complex Field Handling**:
- **Arrays**: expertise_areas, preferred_teaching_days, teaching_modalities, certifications, languages
  - Excel format: comma-separated values
  - Processing: split by comma, trim, validate individual values
- **Dates**: hire_date, contract_start_date, contract_end_date
  - Support multiple formats: Y-m-d, d/m/Y, m/d/Y
- **Booleans**: is_active, can_teach_online, is_available_for_assignment
  - Accept: true/false, 1/0, yes/no, y/n (case-insensitive)

### Template Generation

**Simple Template**: Basic lecturer information in single sheet
**Detailed Template**: Multiple sheets for complex relationships
**Relationship Template**: One row per lecturer-campus relationship

## Error Handling

### Import Error Handling

**File Upload Errors**:
- Invalid file format → Clear error message with supported formats
- File too large → Size limit information and suggestions
- Upload failure → Server error details and retry options

**Data Validation Errors**:
- Row-level validation → Detailed error report with row numbers and field issues
- Duplicate handling → Clear indication of skipped/updated records
- Foreign key violations → Specific messages about missing related records

**Processing Errors**:
- Memory limits → Chunked processing for large files
- Database errors → Transaction rollback and error logging
- Timeout issues → Progress tracking and resume capability

### Export Error Handling

**Query Errors**:
- Invalid filters → Validation and user feedback
- Large datasets → Memory management and streaming
- Permission issues → Clear access denied messages

**File Generation Errors**:
- Disk space → Error message and cleanup
- Excel generation → Fallback to CSV format
- Download failures → Retry mechanism

## Testing Strategy

### Unit Tests

**LectureExcelImportService Tests**:
- File parsing with various Excel formats
- Data validation with valid/invalid data sets
- Duplicate handling strategies
- Error scenarios and edge cases

**LectureExcelExportService Tests**:
- Query building with different filter combinations
- Data formatting and Excel generation
- Large dataset handling
- Memory usage optimization

### Integration Tests

**Controller Tests**:
- File upload and validation
- Import process end-to-end
- Export with various filters
- Permission enforcement

**Feature Tests**:
- Complete import workflow
- Template download functionality
- Export with real data
- Error handling scenarios

### Frontend Tests

**Component Tests**:
- Import form interactions
- File upload handling
- Preview display
- Progress indicators

**E2E Tests**:
- Complete import workflow
- Export functionality
- Error handling user experience
- Permission-based access control

## Security Considerations

### File Upload Security

- **File Type Validation**: Strict MIME type checking
- **File Size Limits**: Prevent DoS attacks through large files
- **Temporary File Management**: Secure storage and automatic cleanup
- **Path Traversal Prevention**: Sanitized file names and paths

### Data Validation Security

- **Input Sanitization**: All imported data sanitized before database insertion
- **SQL Injection Prevention**: Use of Eloquent ORM and parameterized queries
- **XSS Prevention**: Proper escaping of data in preview displays

### Permission Security

- **Route Protection**: All import/export routes protected by middleware
- **UI Security**: Buttons/links only visible to authorized users
- **API Security**: Permission checks on all endpoints

## Performance Considerations

### Import Performance

- **Chunked Processing**: Process large files in batches to manage memory
- **Database Optimization**: Use bulk insert operations where possible
- **Memory Management**: Stream processing for very large files
- **Progress Tracking**: Real-time feedback for long-running operations

### Export Performance

- **Query Optimization**: Efficient database queries with proper indexing
- **Memory Streaming**: Stream large exports directly to response
- **Caching**: Cache frequently exported data sets
- **Background Processing**: Queue large exports for background processing

## Implementation Notes

### Route Organization

Import/export routes will be added to `routes/web/lectures.php` following the pattern established in `routes/web/user.php`:

```php
// Import routes (before resource routes)
Route::get('lectures/import', [LectureImportController::class, 'showImportForm'])
    ->middleware('can:import_lecturer')
    ->name(LectureRoutes::IMPORT_FORM);

// Export routes
Route::get('lectures/export/excel', [LectureExportController::class, 'exportExcel'])
    ->middleware('can:export_lecturer')
    ->name(LectureRoutes::EXPORT_EXCEL);
```

### Permission Integration

The feature will integrate with the existing permission system:
- `import_lecturer`: Required for all import operations
- `export_lecturer`: Required for all export operations
- UI elements will use existing permission checking patterns

### Frontend Integration

The updated Index.vue will maintain consistency with existing UI patterns:
- Same button styling and positioning
- Consistent error handling and notifications
- Responsive design matching current layout
- Integration with existing toast notification system
