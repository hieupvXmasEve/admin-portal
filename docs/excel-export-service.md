# Excel Export Service Documentation

## Overview

The `ExcelExportService` is a reusable service class for handling Excel exports across the application. It provides a consistent interface for exporting data to Excel format with various options.

## Location

- **Service Class**: `app/Services/ExcelExportService.php`
- **Example Export Class**: `app/Exports/BillingCycleInvoicesExport.php`

## Features

- **Download**: Export data and trigger immediate browser download
- **Store**: Save export files to storage disk
- **Raw**: Get export content as raw string
- **Filename Generation**: Automatic filename sanitization and timestamp generation
- **MIME Type Detection**: Automatic MIME type detection for different export formats

## Service Methods

### `download(object $exportClass, string $filename, string $writerType = 'xlsx'): BinaryFileResponse`

Export data to Excel and trigger browser download.

**Parameters:**
- `$exportClass`: Instance of an export class (must implement Maatwebsite\Excel concerns)
- `$filename`: Filename without extension
- `$writerType`: Export format (xlsx, csv, xls, ods, html, pdf)

**Returns:** `BinaryFileResponse` that triggers file download

**Example:**
```php
public function export(ExcelExportService $excelService)
{
    $export = new MyDataExport($data);
    return $excelService->download($export, 'my-export', 'xlsx');
}
```

### `store(object $exportClass, string $path, string $disk = 'local', string $writerType = 'xlsx'): bool`

Save export file to storage disk.

**Parameters:**
- `$exportClass`: Instance of an export class
- `$path`: Storage path (relative to storage/app)
- `$disk`: Storage disk name (local, s3, etc.)
- `$writerType`: Export format

**Returns:** `bool` - Success status

**Example:**
```php
$export = new MyDataExport($data);
$success = $excelService->store($export, 'exports/data.xlsx', 'local', 'xlsx');
```

### `raw(object $exportClass, string $writerType = 'xlsx'): string`

Get export content as raw string.

**Parameters:**
- `$exportClass`: Instance of an export class
- `$writerType`: Export format

**Returns:** `string` - Raw file content

**Example:**
```php
$export = new MyDataExport($data);
$content = $excelService->raw($export, 'xlsx');
```

### `generateFilenameWithTimestamp(string $prefix, string $extension = 'xlsx'): string`

Generate a filename with timestamp.

**Parameters:**
- `$prefix`: Filename prefix
- `$extension`: File extension (without dot)

**Returns:** `string` - Formatted filename with timestamp

**Example:**
```php
$filename = $excelService->generateFilenameWithTimestamp('billing_cycle_1_invoices');
// Output: billing_cycle_1_invoices_2025-10-12_143045.xlsx
```

### `getMimeType(string $writerType): string`

Get MIME type for export format.

**Parameters:**
- `$writerType`: Export format

**Returns:** `string` - MIME type

## Creating Export Classes

Export classes should be placed in `app/Exports/` and implement Maatwebsite\Excel concerns.

### Required Concerns

Common concerns to implement:
- `FromCollection` or `FromQuery`: Data source
- `WithHeadings`: Column headers
- `WithMapping`: Data transformation
- `WithStyles`: Excel styling
- `ShouldAutoSize` or `WithColumnWidths`: Column sizing
- `WithTitle`: Worksheet title (optional)

### Example Export Class

```php
<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MyDataExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected Collection $data;
    
    public function __construct(Collection $data)
    {
        $this->data = $data;
    }
    
    public function collection(): Collection
    {
        return $this->data;
    }
    
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Created At',
        ];
    }
    
    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->email,
            $row->created_at->format('Y-m-d H:i:s'),
        ];
    }
    
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
```

## Controller Integration

### Basic Usage

```php
use App\Services\ExcelExportService;
use App\Exports\MyDataExport;

class MyController extends Controller
{
    public function __construct(
        private ExcelExportService $excelExportService
    ) {}
    
    public function export(Request $request)
    {
        // Get data
        $data = MyModel::query()->get();
        
        // Create export instance
        $export = new MyDataExport($data);
        
        // Generate filename with timestamp
        $filename = $this->excelExportService->generateFilenameWithTimestamp('my_data');
        
        // Download
        return $this->excelExportService->download($export, $filename, 'xlsx');
    }
}
```

### With Filters

```php
public function export(Request $request)
{
    $validated = $request->validate([
        'status' => 'nullable|string',
        'category_id' => 'nullable|integer',
    ]);
    
    // Apply filters
    $query = MyModel::query();
    
    if (!empty($validated['status'])) {
        $query->where('status', $validated['status']);
    }
    
    if (!empty($validated['category_id'])) {
        $query->where('category_id', $validated['category_id']);
    }
    
    $data = $query->get();
    
    // Create export with filters metadata
    $export = new MyDataExport($data, $validated);
    
    // Generate filename
    $filename = $this->excelExportService->generateFilenameWithTimestamp('my_data_filtered');
    
    return $this->excelExportService->download($export, $filename);
}
```

## Frontend Integration (Vue 3)

### Export Button with Loading State

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Download, Loader2 } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

const isExporting = ref(false);

const handleExport = () => {
    isExporting.value = true;
    
    // Build export URL
    const exportUrl = route('my-resource.export');
    
    // Create hidden link and trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset loading state
    setTimeout(() => {
        isExporting.value = false;
        toast.success('Export started. Your download will begin shortly.');
    }, 500);
};
</script>

<template>
    <Button variant="outline" :disabled="isExporting" @click="handleExport">
        <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
        <Download v-else class="mr-2 h-4 w-4" />
        Export to Excel
    </Button>
</template>
```

### Export with Filters

```vue
<script setup lang="ts">
const handleExport = () => {
    isExporting.value = true;
    
    // Build export URL with filters
    const exportParams = new URLSearchParams();
    
    if (currentStatus.value !== 'all') {
        exportParams.append('status', currentStatus.value);
    }
    
    if (currentCategory.value !== 'all') {
        exportParams.append('category_id', currentCategory.value);
    }
    
    const exportUrl = route('my-resource.export');
    const urlWithParams = exportParams.toString() 
        ? `${exportUrl}?${exportParams.toString()}` 
        : exportUrl;
    
    // Trigger download
    const link = document.createElement('a');
    link.href = urlWithParams;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        isExporting.value = false;
        toast.success('Export started. Your download will begin shortly.');
    }, 500);
};
</script>
```

## Route Setup

### Web Routes

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('my-resource')->name('my-resource.')->group(function () {
        // Other routes...
        
        Route::get('{myResource}/export', [MyResourceController::class, 'export'])
            ->middleware('can:view_my_resource')
            ->name('export');
    });
});
```

## Styling Options

### Header Styling

```php
public function styles(Worksheet $sheet): array
{
    return [
        1 => [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ],
    ];
}
```

### Cell Borders

```php
use PhpOffice\PhpSpreadsheet\Style\Border;

public function styles(Worksheet $sheet): array
{
    $lastRow = $this->data->count() + 1;
    
    return [
        "A2:Z{$lastRow}" => [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ],
    ];
}
```

### Column Width

```php
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class MyDataExport implements WithColumnWidths
{
    public function columnWidths(): array
    {
        return [
            'A' => 10,  // ID
            'B' => 30,  // Name
            'C' => 35,  // Email
            'D' => 20,  // Created At
        ];
    }
}
```

## Example: Billing Cycle Invoices Export

**Controller Method:**
```php
public function exportInvoices(Request $request, BillingCycle $billingCycle): BinaryFileResponse
{
    $validated = $request->validate([
        'status' => 'nullable|string|in:all,draft,pending,paid,overdue,cancelled',
        'campus_id' => 'nullable|integer|exists:campuses,id',
        'search' => 'nullable|string|max:255',
    ]);

    $invoicesQuery = $billingCycle->invoices()
        ->with(['student', 'student.campus']);

    // Apply filters...
    
    $invoices = $invoicesQuery->orderByDesc('created_at')->get();

    $export = new BillingCycleInvoicesExport(
        $invoices,
        $billingCycle->name,
        $validated
    );

    $filename = $this->excelExportService->generateFilenameWithTimestamp(
        "billing_cycle_{$billingCycle->id}_invoices"
    );

    return $this->excelExportService->download($export, $filename, 'xlsx');
}
```

**Route:**
```php
Route::get('{billingCycle}/export', [BillingCycleController::class, 'exportInvoices'])
    ->middleware('can:view_billing_cycle')
    ->name('export');
```

## Best Practices

1. **Always sanitize filenames**: The service automatically sanitizes filenames, but avoid special characters in prefix
2. **Use timestamps**: Always use `generateFilenameWithTimestamp()` to avoid filename conflicts
3. **Implement proper styling**: Apply consistent styling across all exports (headers, borders, alignment)
4. **Handle large datasets**: For large datasets, consider using `FromQuery` instead of `FromCollection`
5. **Add filters metadata**: Include filter information in the export for context
6. **Loading states**: Always show loading state in the frontend during export
7. **Permission checks**: Always apply proper middleware/permission checks on export routes
8. **Format dates consistently**: Use consistent date formats across all exports
9. **Handle null values**: Always provide fallback values (e.g., 'N/A') for null data
10. **Currency formatting**: Format currency values consistently in the map method

## Supported Export Formats

- **xlsx**: Excel 2007+ format (recommended)
- **xls**: Legacy Excel format
- **csv**: Comma-separated values
- **ods**: OpenDocument Spreadsheet
- **html**: HTML table
- **pdf**: PDF format (requires additional setup)

## Dependencies

This service uses the [maatwebsite/excel](https://laravel-excel.com/) package:

```json
{
    "require": {
        "maatwebsite/excel": "^3.1"
    }
}
```

## Troubleshooting

### Memory Issues with Large Datasets

For very large datasets, use chunking:

```php
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class MyDataExport implements FromQuery, WithChunkReading
{
    public function query()
    {
        return MyModel::query();
    }
    
    public function chunkSize(): int
    {
        return 1000;
    }
}
```

### Timeout Issues

Increase PHP execution time in controller:

```php
public function export()
{
    set_time_limit(300); // 5 minutes
    
    // Export logic...
}
```

Or configure in `config/excel.php`:

```php
'exports' => [
    'chunk_size' => 1000,
    'pre_calculate_formulas' => false,
    'strict_null_comparison' => false,
],
```

