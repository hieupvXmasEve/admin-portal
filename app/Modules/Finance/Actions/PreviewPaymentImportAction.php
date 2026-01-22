<?php

namespace App\Modules\Finance\Actions;

use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class PreviewPaymentImportAction
{
    public function run(UploadedFile $file): array
    {
        $rows = Excel::toCollection(new class implements \Maatwebsite\Excel\Concerns\ToCollection {
            public function collection(Collection $rows) {}
        }, $file)->first();

        // Remove header row if needed (assuming first row is header if string)
        if ($rows->count() > 0 && !is_numeric($rows->first()[1])) {
             $rows->shift();
        }

        $preview = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $index => $row) {
            // Expected columns: 0: Student Code, 1: Amount, 2: Paid At (Y-m-d), 3: External Ref, 4: Notes
            $studentCode = trim((string) ($row[0] ?? ''));
            $amount = $row[1] ?? 0;
            $paidAt = $row[2] ?? now()->format('Y-m-d');
            $ref = trim((string) ($row[3] ?? ''));
            $notes = $row[4] ?? '';

            $errors = [];
            
            // Validate Student
            $student = Student::where('student_id', $studentCode)->first();
            if (!$student) {
                $errors[] = "Student code '{$studentCode}' not found.";
            }

            // Validate Amount
            if (!is_numeric($amount) || $amount <= 0) {
                $errors[] = "Invalid amount: {$amount}";
            }

            // Validate Date
            try {
                $paidAtDate = Carbon::parse($paidAt);
            } catch (\Exception $e) {
                $errors[] = "Invalid date: {$paidAt}";
                $paidAtDate = null;
            }

            // Validate Duplicate
            if ($ref && Payment::where('source', 'import')->where('external_ref', $ref)->exists()) {
                $errors[] = "Duplicate external ref: {$ref}";
            }

            if (empty($errors)) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $preview[] = [
                'row' => $index + 2, // 1-based + header
                'student_code' => $studentCode,
                'student_name' => $student ? $student->full_name : null,
                'student_id' => $student ? $student->id : null,
                'amount' => $amount,
                'paid_at' => $paidAtDate ? $paidAtDate->format('Y-m-d H:i:s') : $paidAt,
                'external_ref' => $ref,
                'notes' => $notes,
                'status' => empty($errors) ? 'valid' : 'error',
                'errors' => $errors,
            ];
        }

        return [
            'rows' => $preview,
            'summary' => [
                'total' => count($preview),
                'valid' => $validCount,
                'invalid' => $invalidCount,
            ],
        ];
    }
}
