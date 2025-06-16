# CRUD Implementation Guide with Import/Export

This guide outlines the standardized approach for implementing full CRUD functionality with import/export capabilities, based on the user management implementation patterns.

## Overview

When implementing a new entity (e.g., classes, attendance), follow this structured approach:

1. **Core CRUD Controller** - Main entity operations
2. **Import Controller** - File upload and processing
3. **Export Controller** - Data export functionality  
4. **Service Classes** - Business logic separation
5. **Routes** - Organized route structure
6. **Frontend Components** - Vue.js interfaces
7. **Tests** - Comprehensive testing

## 1. Core CRUD Controller

### File: `app/Http/Controllers/{Entity}Controller.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\{Entity};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class {Entity}Controller extends Controller
{
    public function index(Request $request, {Entity} ${entity})
    {
        // Validate input parameters
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'filter.field1' => 'string|max:255',
            'filter.field2' => 'string|max:255',
        ]);

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;

        $query = ${entity}->newQuery()->orderBy('id', 'desc');

        // Global search implementation
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('field1', 'like', "%{$search}%")
                    ->orWhere('field2', 'like', "%{$search}%");
            });
        }

        // Column-specific filters
        if (!empty($validated['filter'])) {
            foreach ($validated['filter'] as $column => $value) {
                if (!empty($value) && in_array($column, ['field1', 'field2'])) {
                    $query->where($column, 'like', "%{$value}%");
                }
            }
        }

        ${entities} = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('{entities}/Index', [
            '{entities}' => Inertia::deepMerge(${entities}),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'field1' => $validated['filter']['field1'] ?? null,
                'field2' => $validated['filter']['field2'] ?? null,
            ],
        ]);
    }

    public function create()
    {
        // Load any required relationships/options
        return Inertia::render('{entities}/Add', [
            'options' => $this->getFormOptions()
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Store request data:', $request->all());
        
        $validated = $request->validate([
            'field1' => 'required|string|max:255',
            'field2' => 'required|string|max:255',
            // Add validation rules for all fields
        ]);

        ${entity} = {Entity}::create($validated);

        // Handle relationships if needed
        $this->handleRelationships(${entity}, $validated);

        return redirect()->route('{entities}')->with('success', '{Entity} created successfully!');
    }

    public function edit({Entity} ${entity})
    {
        return Inertia::render('{entities}/Edit', [
            '{entity}' => ${entity},
            'options' => $this->getFormOptions()
        ]);
    }

    public function update(Request $request, {Entity} ${entity})
    {
        Log::info('Update request data:', $request->all());
        
        $validated = $request->validate([
            'field1' => 'required|string|max:255',
            'field2' => 'required|string|max:255',
            // Add validation rules
        ]);

        ${entity}->update($validated);

        // Handle relationship updates
        $this->handleRelationships(${entity}, $validated);

        return redirect()->route('{entities}')->with('success', '{Entity} updated successfully!');
    }

    public function destroy({Entity} ${entity})
    {
        // Handle soft deletes or relationship cleanup
        ${entity}->delete();

        return redirect()->route('{entities}')->with('success', '{Entity} deleted successfully!');
    }

    private function getFormOptions(): array
    {
        // Return options needed for forms (dropdowns, etc.)
        return [];
    }

    private function handleRelationships({Entity} ${entity}, array $validated): void
    {
        // Handle any relationship creation/updates
    }
}
```

## 2. Import Controller

### File: `app/Http/Controllers/{Entity}ImportController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\{Entity}ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class {Entity}ImportController extends Controller
{
    public function __construct(
        private readonly {Entity}ExcelImportService $importService
    ) {}

    public function showImportForm(): InertiaResponse
    {
        return Inertia::render('{entities}/Import', [
            'maxFileSize' => config('import.max_file_size', '10MB'),
            'allowedExtensions' => config('import.allowed_extensions', ['xlsx', 'xls']),
            'availableFormats' => [
                'simple' => 'Simple Format',
                'detailed' => 'Detailed Format',
                'relationship' => 'Relationship Format'
            ]
        ]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:2048',
            'duplicate_handling' => 'nullable|in:skip,update,error'
        ]);

        try {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            $uploadDir = storage_path('app/temp/imports');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file->move($uploadDir, $filename);
            $path = 'temp/imports/' . $filename;
            $fullPath = storage_path('app/' . $path);

            // Get preview data
            $preview = $this->importService->previewImportData($fullPath, 5);

            return response()->json([
                'success' => true,
                'file_path' => $path,
                'filename' => $file->getClientOriginalName(),
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            Log::error('File upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function processImport(Request $request): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string',
            'duplicate_handling' => 'nullable|in:skip,update,error'
        ]);

        try {
            $fullPath = storage_path('app/' . $request->file_path);
            
            $options = [
                'duplicate_handling' => $request->duplicate_handling ?? 'update'
            ];

            $result = $this->importService->import{Entities}FromExcel($fullPath, $options);

            // Clean up temporary file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadTemplate(string $format)
    {
        $templates = [
            'simple' => '{entities}_simple_template.xlsx',
            'detailed' => '{entities}_detailed_template.xlsx',
            'relationship' => '{entities}_relationship_template.xlsx'
        ];

        if (!isset($templates[$format])) {
            abort(404, 'Template not found');
        }

        $templatePath = $this->importService->generateTemplate($format);

        return response()->download($templatePath, $templates[$format]);
    }
}
```

## 3. Export Controller

### File: `app/Http/Controllers/{Entity}ExportController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\{Entity}ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class {Entity}ExportController extends Controller
{
    public function __construct(
        private readonly {Entity}ExcelExportService $exportService
    ) {}

    public function exportExcel(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255',
            // Add other filter validations
        ]);

        try {
            $filePath = $this->exportService->export{Entities}ToExcel($validated);
            $downloadName = '{entities}_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

            return response()->download($filePath, $downloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Excel export failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Export failed. Please try again later.'
            ], 500);
        }
    }
}
```

## 4. Service Classes

### Import Service: `app/Services/{Entity}ExcelImportService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Entity};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class {Entity}ExcelImportService
{
    private array $errors = [];
    private array $warnings = [];
    private int $processedRows = 0;
    private int $successfulRows = 0;
    private int $failedRows = 0;

    public function import{Entities}FromExcel(string $filePath, array $options = []): array
    {
        $this->resetCounters();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $format = $this->detectFormat($spreadsheet);

            DB::beginTransaction();

            switch ($format) {
                case 'simple':
                    $this->processSimpleFormat($spreadsheet, $options);
                    break;
                case 'detailed':
                    $this->processDetailedFormat($spreadsheet, $options);
                    break;
                case 'relationship':
                    $this->processRelationshipFormat($spreadsheet, $options);
                    break;
                default:
                    throw new \Exception('Unable to detect import format');
            }

            DB::commit();
            return $this->generateImportReport();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function previewImportData(string $filePath, int $previewRows = 10): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $format = $this->detectFormat($spreadsheet);

        $preview = [
            'format' => $format,
            'sheets' => [],
            'estimated_records' => 0
        ];

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $data = $worksheet->toArray(null, true, true, true);
            $headers = !empty($data) ? array_shift($data) : [];

            $preview['sheets'][] = [
                'name' => $worksheet->getTitle(),
                'headers' => $headers,
                'data' => array_slice($data, 0, $previewRows),
                'total_rows' => count($data)
            ];
        }

        return $preview;
    }

    private function processSimpleFormat($spreadsheet, array $options): void
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray(null, true, true, true);
        
        if (empty($data)) {
            throw new \Exception('No data found in the worksheet');
        }

        $headers = array_shift($data);
        
        foreach ($data as $rowIndex => $row) {
            $this->processedRows++;
            $actualRowNumber = $rowIndex + 2;

            try {
                $entityData = $this->mapRow($headers, $row);
                $this->createOrUpdate{Entity}($entityData, $actualRowNumber, $options);
                $this->successfulRows++;
            } catch (\Exception $e) {
                $this->failedRows++;
                $this->errors[] = [
                    'row' => $actualRowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }
    }

    private function createOrUpdate{Entity}(array $entityData, int $rowNumber, array $options): {Entity}
    {
        $validator = Validator::make($entityData, [
            // Add validation rules
        ]);

        if ($validator->fails()) {
            throw new \Exception('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        // Handle duplicates based on options
        $duplicateHandling = $options['duplicate_handling'] ?? 'update';
        
        // Implementation depends on your unique identifier logic

        return {Entity}::create($entityData);
    }

    // Additional helper methods...
}
```

### Export Service: `app/Services/{Entity}ExcelExportService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Entity};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class {Entity}ExcelExportService
{
    public function export{Entities}ToExcel(array $filters = []): string
    {
        ${entities} = $this->get{Entities}WithRelations($filters);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Create multiple sheets
        $this->createSummarySheet($spreadsheet, ${entities});
        $this->createDetailedSheet($spreadsheet, ${entities});
        
        $spreadsheet->setActiveSheetIndex(0);

        $fileName = '{entities}_export_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/temp/' . $fileName);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    private function createSummarySheet(Spreadsheet $spreadsheet, $entities): void
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('{Entities} Summary');

        $headers = ['ID', 'Field1', 'Field2', 'Created At'];
        $worksheet->fromArray($headers, null, 'A1');

        // Apply styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ]
        ];
        $worksheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($entities as $entity) {
            $worksheet->fromArray([
                $entity->id,
                $entity->field1,
                $entity->field2,
                $entity->created_at->format('Y-m-d H:i:s')
            ], null, "A{$row}");
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'D') as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
```

## 5. Routes Structure

### File: `routes/{entity}.php`

```php
<?php

use App\Http\Controllers\{Entity}Controller;
use App\Http\Controllers\{Entity}ImportController;
use App\Http\Controllers\{Entity}ExportController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Import routes (must come BEFORE resource routes)
    Route::prefix('{entities}')->group(function () {
        Route::prefix('import')->name('{entities}.import.')->group(function () {
            Route::get('/', [{Entity}ImportController::class, 'showImportForm'])
                ->middleware('can:import_{entity}')
                ->name('form');

            Route::post('/upload', [{Entity}ImportController::class, 'uploadFile'])
                ->middleware('can:import_{entity}')
                ->name('upload');

            Route::post('/process', [{Entity}ImportController::class, 'processImport'])
                ->middleware('can:import_{entity}')
                ->name('process');
        });

        Route::prefix('templates')->name('{entities}.templates.')->group(function () {
            Route::get('/{format}', [{Entity}ImportController::class, 'downloadTemplate'])
                ->middleware('can:import_{entity}')
                ->name('download')
                ->where('format', 'simple|detailed|relationship');
        });

        Route::get('/export/excel', [{Entity}ExportController::class, 'exportExcel'])
            ->middleware('can:view_{entity}')
            ->name('{entities}.export.excel');
    });

    // Resource routes
    RoutePermissionHelper::resourceWithPermissions(
        prefix: '{entities}',
        controller: {Entity}Controller::class,
        module: '{entities}'
    );
});
```

## 6. Model Structure

### File: `app/Models/{Entity}.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class {Entity} extends Model
{
    use HasFactory;

    protected $fillable = [
        'field1',
        'field2',
        // Add all mass assignable fields
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // Add other casting rules
    ];

    // Define relationships
    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }

    public function childModels(): HasMany
    {
        return $this->hasMany(ChildModel::class);
    }
}
```

## 7. Frontend Components

### Index Component: `resources/js/pages/{entities}/Index.vue`

```vue
<template>
    <div>
        <!-- Use standardized DataTable component -->
        <DataTable 
            :data="{entities}"
            :columns="columns"
            :filters="filters"
            @filter-change="handleFilterChange"
        />
        
        <!-- Use standardized Pagination component -->
        <DataPagination 
            :data="{entities}"
            @page-change="handlePageChange"
        />
    </div>
</template>

<script setup>
import DataTable from '@/components/DataTable.vue'
import DataPagination from '@/components/DataPagination.vue'
import { defineProps } from 'vue'

const props = defineProps({
    {entities}: Object,
    filters: Object
})

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'field1', label: 'Field 1', sortable: true, filterable: true },
    { key: 'field2', label: 'Field 2', sortable: true, filterable: true },
    { key: 'actions', label: 'Actions', sortable: false }
]

// Implementation methods...
</script>
```

## 8. Testing Structure

### Feature Test: `tests/Feature/{Entity}Test.php`

```php
<?php

namespace Tests\Feature;

use App\Models\{Entity};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {Entity}Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = User::factory()->create();
        session(['permissions' => ['view_{entity}', 'add_{entity}', 'edit_{entity}']]);
        $this->actingAs($user);
    }

    public function test_can_view_index()
    {
        $response = $this->get('/{entities}');
        $response->assertStatus(200);
    }

    public function test_can_create_{entity}()
    {
        $data = {Entity}::factory()->make()->toArray();
        
        $response = $this->post('/{entities}', $data);
        
        $response->assertRedirect('/{entities}');
        $this->assertDatabaseHas('{entities}', $data);
    }

    // Add more tests for update, delete, import, export...
}
```

## 9. Configuration

### Config: `config/import.php`

```php
<?php

return [
    'max_file_size' => env('IMPORT_MAX_FILE_SIZE', '10MB'),
    'allowed_extensions' => ['xlsx', 'xls'],
    'default_password' => env('IMPORT_DEFAULT_PASSWORD', 'TempPassword123!'),
    'duplicate_handling' => env('IMPORT_DUPLICATE_HANDLING', 'update'),
];
```

## 10. Implementation Checklist

When implementing a new entity, follow this checklist:

- [ ] Create Model with proper relationships and fillable fields
- [ ] Create Migration with proper indexes and constraints
- [ ] Create Factory for testing
- [ ] Implement main CRUD Controller
- [ ] Implement Import Controller with file handling
- [ ] Implement Export Controller with Excel generation
- [ ] Create Import Service with format detection and processing
- [ ] Create Export Service with multi-sheet Excel creation
- [ ] Set up Routes with proper grouping and permissions
- [ ] Create Vue.js components (Index, Add, Edit, Import) using DataTable/DataPagination
- [ ] Write Feature Tests for all functionality
- [ ] Create Seeder if needed
- [ ] Update permissions system
- [ ] Add validation rules
- [ ] Implement proper error handling and logging

## Best Practices

1. **Separation of Concerns**: Keep controllers thin, move business logic to services
2. **Consistent Validation**: Use Form Request classes for complex validation
3. **Error Handling**: Always log errors and provide user-friendly messages
4. **Performance**: Use eager loading, pagination, and proper indexing
5. **Security**: Implement proper authorization and input validation
6. **Testing**: Write comprehensive tests for all functionality
7. **Documentation**: Document complex business logic and API endpoints
8. **Code Reuse**: Extract common functionality into traits or helper classes

This guide provides a solid foundation for implementing robust CRUD functionality with import/export capabilities while maintaining consistency across your application. 
