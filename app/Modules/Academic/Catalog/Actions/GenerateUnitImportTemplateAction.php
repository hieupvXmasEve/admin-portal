<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class GenerateUnitImportTemplateAction
{
    /** @var array<string, string> */
    private const FILES = [
        'simple' => 'units_simple_template.xlsx',
        'detailed' => 'units_detailed_template.xlsx',
        'complete' => 'units_complete_template.xlsx',
        'combined' => 'units_syllabus_combined_template.xlsx',
    ];

    public function handle(string $format): string
    {
        if (! isset(self::FILES[$format])) {
            throw new InvalidArgumentException('Template not found');
        }

        $spreadsheet = new Spreadsheet;
        $this->addSheet($spreadsheet->getActiveSheet(), 'Units', ['Code*', 'Name*', 'Credit Points*'], [
            ['CS101', 'Introduction to Computer Science', '12.5'],
            ['CS201', 'Data Structures and Algorithms', '12.5'],
            ['MATH101', 'Calculus I', '15.0'],
        ]);

        if ($format !== 'simple') {
            $this->addSheet($spreadsheet->createSheet(), 'Prerequisites', [
                'Unit Code*', 'Group Logic*', 'Group Description', 'Condition Type*',
                'Required Unit Code', 'Required Credits', 'Free Text',
            ], [
                ['CS201', 'AND', 'Basic programming prerequisites', 'prerequisite', 'CS101', '', ''],
            ]);
        }

        if (in_array($format, ['complete', 'combined'], true)) {
            $this->addSheet($spreadsheet->createSheet(), 'Equivalents', [
                'Unit Code*', 'Equivalent Unit Code*', 'Reason', 'Valid From Semester',
            ], [['CS101', 'PROG101', 'Same programming content', '2024-S1']]);
        }

        if ($format === 'combined') {
            $this->addSheet($spreadsheet->createSheet(), 'Syllabus', [
                'Unit Code*', 'Version', 'Description', 'Total Hours', 'Hours Per Session', 'Effective From Semester', 'Is Active*',
            ], [['CS101', 'v1.0', 'Introduction to programming concepts and software development fundamentals', '120', '3', '2024-S1', 'TRUE']]);
            $this->addSheet($spreadsheet->createSheet(), 'Assessment Components', [
                'Unit Code*', 'Syllabus Version', 'Component Name*', 'Weight*', 'Type*', 'Required for Final Exam*',
            ], [['CS101', 'v1.0', 'Final Exam', '50', 'exam', 'TRUE']]);
            $this->addSheet($spreadsheet->createSheet(), 'Assessment Details', [
                'Unit Code*', 'Syllabus Version', 'Component Name*', 'Detail Name*', 'Weight',
            ], [['CS101', 'v1.0', 'Final Exam', 'Written section', '100']]);
        }

        $this->addSheet($spreadsheet->createSheet(), 'Instructions', ['Unit Import Template Instructions'], [
            ['Fields marked with * are required.'],
            ['Do not modify header rows; remove sample data before importing.'],
            ['Credit Points must be numeric. Use the supported worksheet names exactly.'],
        ]);
        $spreadsheet->setActiveSheetIndex(0);

        $directory = storage_path('app/temp/templates');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.self::FILES[$format];
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function addSheet(Worksheet $sheet, string $title, array $headers, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
