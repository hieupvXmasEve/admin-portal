<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Models\StudentApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentApplicationController extends Controller
{
    public function store(StoreStudentApplicationRequest $request): JsonResponse
    {
        $students = $request->validated()['students'];
        
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($students, &$created, &$updated, &$errors) {
            foreach ($students as $index => $studentData) {
                try {
                    $existingStudent = StudentApplication::where('email', $studentData['email'])
                        ->orWhere(function ($query) use ($studentData) {
                            if (!empty($studentData['national_id'])) {
                                $query->where('national_id', $studentData['national_id']);
                            }
                        })
                        ->first();

                    if ($existingStudent) {
                        $existingStudent->update($studentData);
                        $updated++;
                    } else {
                        StudentApplication::create($studentData);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'email' => $studentData['email'] ?? 'N/A',
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        return response()->json([
            'message' => 'Student applications processed successfully',
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'total_processed' => count($students),
            'total_failed' => count($errors)
        ]);
    }
}
