<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkCreateExamResitAttemptsAction
{
    public function __construct(private readonly CreateExamResitAttemptAction $createAttempt) {}

    /**
     * Register exam-resit attempts for multiple records in one submit. Each
     * item runs through the same per-record eligibility + catalog-pricing
     * gate as the single-record flow — one ineligible record must not block
     * the rest of the batch, so failures are collected instead of thrown.
     * Every failure branch is caught here, not just the expected
     * ValidationException: letting an unexpected error propagate would 500
     * the whole request while earlier items in the batch already committed,
     * losing the success/failure report the operator needs to see.
     *
     * @param  list<array{student_id:int,academic_record_id:int,campus_id:int}>  $items
     * @return array{
     *   succeeded: list<array{student_id:int,academic_record_id:int}>,
     *   failed: list<array{student_id:int,academic_record_id:int,student_name:?string,reason:string}>,
     * }
     */
    public function handle(array $items, int $operationSemesterId, int $chargeSemesterId, ?string $notes = null): array
    {
        $studentNames = Student::query()
            ->whereIn('id', array_column($items, 'student_id'))
            ->pluck('full_name', 'id');

        $succeeded = [];
        $failed = [];

        foreach ($items as $item) {
            try {
                $this->createAttempt->run([
                    'student_id' => $item['student_id'],
                    'academic_record_id' => $item['academic_record_id'],
                    'operation_semester_id' => $operationSemesterId,
                    'charge_semester_id' => $chargeSemesterId,
                    'campus_id' => $item['campus_id'],
                    'notes' => $notes,
                ]);

                $succeeded[] = [
                    'student_id' => $item['student_id'],
                    'academic_record_id' => $item['academic_record_id'],
                ];
            } catch (ValidationException $exception) {
                $failed[] = [
                    'student_id' => $item['student_id'],
                    'academic_record_id' => $item['academic_record_id'],
                    'student_name' => $studentNames->get($item['student_id']),
                    'reason' => collect($exception->errors())->flatten()->first() ?? 'Không đủ điều kiện thi lại.',
                ];
            } catch (Throwable $exception) {
                Log::error('Bulk exam-resit registration item failed', [
                    'item' => $item,
                    'exception' => $exception,
                ]);

                $failed[] = [
                    'student_id' => $item['student_id'],
                    'academic_record_id' => $item['academic_record_id'],
                    'student_name' => $studentNames->get($item['student_id']),
                    'reason' => 'Lỗi hệ thống, vui lòng thử lại.',
                ];
            }
        }

        return compact('succeeded', 'failed');
    }
}
