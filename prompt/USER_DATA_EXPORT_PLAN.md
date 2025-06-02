# User Data Export Plan - Excel Export

## Overview
This plan outlines the implementation for exporting user data to Excel format, including their associated campuses and roles. The system supports many-to-many relationships where users can belong to multiple campuses and have different roles in each campus.

## Database Structure Analysis

### Current Tables
- **users**: Basic user information (id, name, email, password, etc.)
- **campuses**: Campus information (id, name, code, address)
- **roles**: Role definitions (id, name) - 11 predefined roles
- **permissions**: Permission definitions with hierarchical structure
- **campus_user_roles**: Junction table linking users, campuses, and roles
- **role_permissions**: Junction table linking roles and permissions

### Relationships
- User ↔ Campus: Many-to-Many through `campus_user_roles`
- User ↔ Role: Many-to-Many through `campus_user_roles` (context-specific per campus)
- Role ↔ Permission: Many-to-Many through `role_permissions`

## Export Requirements

### Data to Export
1. **User Basic Information**
   - ID, Name, Email
   - Created/Updated timestamps
   - Email verification status

2. **Campus Associations**
   - Campus ID, Name, Code, Address
   - Role in each campus
   - Assignment timestamps

3. **Role Information**
   - Role ID, Name
   - Associated permissions (if needed)

4. **Aggregated Data**
   - Total campuses per user
   - Unique roles across all campuses
   - Permission summary

## Implementation Plan

### Phase 1: Backend Implementation

#### 1.1 Create Excel Export Service
**File**: `app/Services/UserExcelExportService.php`
- Handle data aggregation and Excel formatting
- Create multiple worksheets for different data views
- Implement filtering and pagination for large datasets
- Generate charts and summaries

#### 1.2 Create Export Controller
**File**: `app/Http/Controllers/UserExportController.php`
- Handle Excel export requests
- Validate parameters
- Return downloadable Excel files
- Support background processing for large exports

#### 1.3 Create Export Resource
**File**: `app/Http/Resources/UserExportResource.php`
- Format user data for Excel export
- Include nested campus and role information
- Handle data transformation for Excel sheets

#### 1.4 Add Export Routes
**File**: `routes/export.php`
- Excel export endpoints with proper authentication
- Support query parameters for filtering
- Background job status endpoints

### Phase 2: Database Optimizations

#### 2.1 Add Database Indexes
```sql
-- Optimize campus_user_roles queries
CREATE INDEX idx_campus_user_roles_user_campus ON campus_user_roles(user_id, campus_id);
CREATE INDEX idx_campus_user_roles_campus_role ON campus_user_roles(campus_id, role_id);
CREATE INDEX idx_campus_user_roles_user_role ON campus_user_roles(user_id, role_id);

-- Optimize role_permissions queries
CREATE INDEX idx_role_permissions_role ON role_permissions(role_id);
CREATE INDEX idx_role_permissions_permission ON role_permissions(permission_id);
```

#### 2.2 Create Optimized Queries
- Use eager loading to prevent N+1 queries
- Implement efficient joins for large datasets
- Cache frequently accessed role and permission data

### Phase 3: Frontend Implementation

#### 3.1 Export Interface
**File**: `resources/js/pages/users/ExcelExport.vue`
- Excel export configuration form
- Filter options (campus, role, date range)
- Worksheet selection options
- Progress indicator for large exports
- Download status and file management

#### 3.2 Export Button Integration
- Add Excel export functionality to user list page
- Bulk export options with filters
- Individual user export capability

### Phase 4: Excel Export Structure

#### 4.1 Multi-Sheet Excel Workbook Structure

**Sheet 1: Users Summary**
| User ID | Name | Email | Email Verified | Campus Count | Total Roles | Created At | Updated At |
|---------|------|-------|----------------|--------------|-------------|------------|------------|
| 1 | John Doe | john@example.com | Yes | 2 | 3 | 2025-01-01 | 2025-01-15 |

**Sheet 2: User Campus Roles (Detailed)**
| User ID | User Name | User Email | Campus ID | Campus Name | Campus Code | Role ID | Role Name | Assigned At |
|---------|-----------|------------|-----------|-------------|-------------|---------|-----------|-------------|
| 1 | John Doe | john@example.com | 1 | Campus A | CA | 1 | Super Admin | 2025-01-01 |
| 1 | John Doe | john@example.com | 2 | Campus B | CB | 2 | Giám Đốc Đào Tạo | 2025-01-02 |

**Sheet 3: Campus Overview**
| Campus ID | Campus Name | Campus Code | Address | Total Users | Active Roles |
|-----------|-------------|-------------|---------|-------------|--------------|
| 1 | Campus A | CA | 123 Main St | 25 | Super Admin, Giám Đốc Đào Tạo |

**Sheet 4: Role Distribution**
| Role ID | Role Name | Total Users | Campuses Used | Most Common Campus |
|---------|-----------|-------------|---------------|-------------------|
| 1 | Super Admin | 5 | 3 | Campus A |
| 2 | Giám Đốc Đào Tạo | 8 | 2 | Campus B |

**Sheet 5: User Permissions (Optional)**
| User ID | User Name | Campus | Role | Permission Code | Permission Name |
|---------|-----------|---------|------|----------------|-----------------|
| 1 | John Doe | Campus A | Super Admin | view_user | View User |

#### 4.2 Excel Formatting Features
- **Headers**: Bold, colored background, frozen panes
- **Data Validation**: Dropdown lists for status fields
- **Conditional Formatting**: Highlight users with multiple roles
- **Charts**: Role distribution pie chart, campus user count bar chart
- **Filters**: Auto-filters on all data sheets
- **Protection**: Lock formula cells, allow data entry only in specific areas

## Technical Implementation Details

### Service Class Methods
```php
class UserExcelExportService
{
    public function exportUsersToExcel($filters = [])
    public function getUsersWithCampusesAndRoles($filters = [])
    public function createUsersSummarySheet($users)
    public function createDetailedRolesSheet($users)
    public function createCampusOverviewSheet()
    public function createRoleDistributionSheet()
    public function createPermissionsSheet($users)
    public function addChartsAndFormatting($spreadsheet)
    public function applyFilters($query, $filters)
}
```

### Controller Methods
```php
class UserExportController
{
    public function exportExcel(Request $request)
    public function downloadExcel($jobId)
    public function getExportStatus($jobId)
    public function exportExcelBackground(Request $request)
}
```

### Excel Export Job
```php
class ExportUsersToExcelJob implements ShouldQueue
{
    public function handle()
    {
        // Process large exports in background
        // Update progress in cache/database
        // Send notification when complete
    }
}
```

### Query Optimization
```php
// Optimized query with eager loading for Excel export
User::with([
    'campusRoles.campus:id,name,code,address',
    'campusRoles.role:id,name',
    'campusRoles.role.permissions:id,name,code'
])
->when($campusFilter, function($query) use ($campusFilter) {
    $query->whereHas('campuses', function($q) use ($campusFilter) {
        $q->whereIn('campus_id', $campusFilter);
    });
})
->when($roleFilter, function($query) use ($roleFilter) {
    $query->whereHas('campusRoles', function($q) use ($roleFilter) {
        $q->whereIn('role_id', $roleFilter);
    });
})
->when($dateRange, function($query) use ($dateRange) {
    $query->whereBetween('created_at', $dateRange);
})
->orderBy('name')
->chunk(1000, function($users) {
    // Process in chunks for memory efficiency
});
```

## Excel-Specific Features

### 1. Advanced Formatting
```php
// Header styling
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
    'borders' => ['allBorders' => ['borderStyle' => 'thin']],
    'alignment' => ['horizontal' => 'center', 'vertical' => 'center']
];

// Data validation for status columns
$validation = $worksheet->getCell('D2')->getDataValidation();
$validation->setType(DataValidation::TYPE_LIST);
$validation->setFormula1('"Yes,No"');
```

### 2. Charts and Visualizations
```php
// Role distribution pie chart
$chart = new PieChart();
$chart->setTitle('Role Distribution Across All Campuses');
$dataSeriesLabels = [new DataSeriesValues('String', 'RoleDistribution!$B$2:$B$12')];
$dataSeriesValues = [new DataSeriesValues('Number', 'RoleDistribution!$C$2:$C$12')];
```

### 3. Dynamic Formulas
```php
// Auto-calculate totals and summaries
$worksheet->setCellValue('G2', '=COUNTIF(UserCampusRoles!$A:$A,A2)'); // Campus count per user
$worksheet->setCellValue('H2', '=COUNTIFS(UserCampusRoles!$A:$A,A2,UserCampusRoles!$G:$G,"<>")'); // Role count per user
```

## Security Considerations

### 1. Access Control
- Implement proper authorization checks using existing role system
- Role-based access to export functionality
- Audit logging for export activities

### 2. Data Protection
- Exclude sensitive data (passwords, tokens)
- Implement data masking for non-admin users
- Rate limiting for export requests

### 3. File Security
- Temporary file cleanup after download
- Secure file storage with expiration
- Download token validation

## Performance Considerations

### 1. Large Dataset Handling
- Implement chunked processing for exports >1000 users
- Background job processing with Laravel Queue
- Progress tracking and email notifications

### 2. Memory Management
- Use PhpSpreadsheet streaming writer for large files
- Process data in chunks to avoid memory limits
- Implement garbage collection between chunks

### 3. Caching Strategy
- Cache role and permission data during export
- Cache campus information
- Use Redis for export job progress tracking

## Testing Strategy

### 1. Unit Tests
```php
// Test service methods
public function test_excel_export_service_creates_correct_sheets()
public function test_data_formatting_for_excel()
public function test_filter_application()
```

### 2. Integration Tests
```php
// Test full export workflow
public function test_excel_export_download()
public function test_background_export_job()
public function test_export_with_filters()
```

### 3. Performance Tests
- Test exports with 10k+ users
- Memory usage monitoring
- File generation time benchmarks

## Deployment Checklist

### 1. Dependencies Installation
```bash
composer require maatwebsite/excel
composer require phpoffice/phpspreadsheet
```

### 2. Configuration
```php
// config/excel.php
'exports' => [
    'chunk_size' => 1000,
    'pre_calculate_formulas' => true,
    'enable_cell_caching' => true,
]
```

### 3. Queue Configuration
```bash
# Setup Redis for job queues
php artisan queue:table
php artisan migrate
```

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── UserExportController.php
│   └── Resources/
│       └── UserExportResource.php
├── Services/
│   └── UserExcelExportService.php
├── Jobs/
│   └── ExportUsersToExcelJob.php
└── Exports/
    ├── UsersExport.php
    ├── UserCampusRolesExport.php
    └── CampusOverviewExport.php

resources/
└── js/
    └── pages/
        └── users/
            ├── ExcelExport.vue
            └── components/
                ├── ExportForm.vue
                ├── ExportProgress.vue
                └── ExportHistory.vue

routes/
└── export.php

tests/
├── Unit/
│   └── UserExcelExportServiceTest.php
└── Feature/
    └── UserExcelExportTest.php
```
## Dependencies

### Required PHP Packages
```json
{
    "maatwebsite/excel": "^3.1",
    "phpoffice/phpspreadsheet": "^1.29",
    "spatie/laravel-query-builder": "^5.0"
}
```

### Frontend Dependencies
```json
{
    "file-saver": "^2.0.5",
    "vue-sonner": "^2.0.0"
}
```

## Sample Excel Output Structure

The generated Excel file will contain:
- **5 worksheets** with different data perspectives
- **Formatted headers** with company branding colors
- **Auto-filters** on all data tables
- **Charts** showing role and campus distribution
- **Conditional formatting** for better data visualization
- **Data validation** for consistent data entry
- **Protected formulas** to maintain data integrity

## Conclusion

This Excel-focused plan provides a comprehensive approach to exporting user data with rich formatting, multiple data views, and advanced Excel features. The implementation emphasizes performance, security, and user experience while leveraging Laravel's robust ecosystem and PhpSpreadsheet's powerful Excel generation capabilities. 