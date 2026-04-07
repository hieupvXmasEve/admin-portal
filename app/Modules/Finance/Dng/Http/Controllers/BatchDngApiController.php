<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BatchDngApiController extends Controller
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'records' => 'required|array|min:1|max:100',
            'records.*.student_id' => 'required|integer|exists:students,id',
            'records.*.amount' => 'required|numeric|min:1',
            'records.*.type' => 'required|string|max:20',
            'records.*.description' => 'required|string|max:255',
            'records.*.estimate_time' => 'required|string|max:10',
        ]);

        // Use same campus_code as single DNG creation
        $campusCode = (string) config('services.dng.campus_code');

        // Load all students in one query
        $studentIds = collect($validated['records'])->pluck('student_id')->unique()->values();
        $students = Student::whereIn('id', $studentIds)->get()->keyBy('id');

        $batchRecords = [];
        foreach ($validated['records'] as $record) {
            $student = $students->get($record['student_id']);
            if (! $student) {
                continue;
            }

            $batchRecords[] = [
                'student_id' => $student->id,
                'student_code' => $student->student_id,
                'type' => $record['type'],
                'amount' => (float) $record['amount'],
                'item_id' => Str::uuid()->toString(),
                'student_name' => $student->full_name,
                'email' => $student->email ?? '',
                'estimate_time' => $record['estimate_time'],
                'description' => $record['description'],
                'note' => $record['description'], // DNG Note field mirrors description
                'student_address' => $student->address ?? $student->current_address_line ?? 'N/A',
                'cccd' => $student->national_id ?? '',
            ];
        }

        $result = $this->dngPaymentService->createAndPushBatch($batchRecords, $campusCode);

        return response()->json(['data' => $result], 201);
    }
}
