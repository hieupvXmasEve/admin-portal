<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BatchDngApiController extends Controller
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
        protected DngCampusCodeResolver $dngCampusCodeResolver,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'records' => 'required|array|min:1|max:100',
            'records.*.student_id' => 'required|integer|exists:students,id',
            'records.*.amount' => 'required|numeric|min:1',
            'records.*.type' => 'required|string|max:20',
            'records.*.description' => 'required|string|max:255',
            'records.*.semester_id' => 'required|integer|exists:semesters,id',
            'records.*.due_date' => 'required|date',
            'records.*.estimate_time' => 'required|string|max:10',
        ]);

        // Load all students in one query
        $studentIds = collect($validated['records'])->pluck('student_id')->unique()->values();
        $students = Student::with('campus')->whereIn('id', $studentIds)->get()->keyBy('id');

        $groupedRecords = [];
        foreach ($validated['records'] as $record) {
            $student = $students->get($record['student_id']);
            if (! $student) {
                continue;
            }

            $campusCode = $this->dngCampusCodeResolver->requireForStudent($student);

            $groupedRecords[$campusCode][] = [
                'student_id' => $student->id,
                'student_code' => $student->student_id,
                'type' => $record['type'],
                'amount' => (float) $record['amount'],
                'semester_id' => (int) $record['semester_id'],
                'due_date' => $record['due_date'],
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

        if ($groupedRecords === []) {
            throw ValidationException::withMessages([
                'records' => 'No valid students were found for DNG batch creation.',
            ]);
        }

        $result = ['created' => 0, 'failed' => 0];
        foreach ($groupedRecords as $campusCode => $records) {
            $batchResult = $this->dngPaymentService->createAndPushBatch($records, $campusCode);
            $result['created'] += $batchResult['created'];
            $result['failed'] += $batchResult['failed'];
        }

        return response()->json(['data' => $result], 201);
    }
}
