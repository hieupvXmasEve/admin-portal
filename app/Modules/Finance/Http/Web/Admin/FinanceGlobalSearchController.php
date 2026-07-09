<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Audit\ResolveFinanceAuditSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON global finance search for the ⌘K palette. Reuses the audit resolver
 * (campus-scoped, deterministic precedence) and maps every resolved target to a
 * Student 360 deep link. Non-student targets carry a `focus=<type>:<id>` param.
 */
class FinanceGlobalSearchController extends Controller
{
    public function search(Request $request, ResolveFinanceAuditSearchQuery $resolver): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $resolution = $resolver->handle($q, $this->currentCampusId());

        return ApiResponse::success([
            'status' => $resolution['status'],
            'results' => $this->toResults($resolution),
        ]);
    }

    /**
     * @param  array{status:string,target:array{type:string,id:int}|null,matches:list<array{type:string,id:int,label:string,sublabel:string}>}  $resolution
     * @return list<array{type:string,id:int,label:string,sublabel:string,url:string}>
     */
    private function toResults(array $resolution): array
    {
        if ($resolution['status'] === 'single' && $resolution['target'] !== null) {
            $result = $this->targetToResult($resolution['target']);

            return $result !== null ? [$result] : [];
        }

        // Ambiguous student matches → already student-typed, map straight through.
        return array_values(array_filter(array_map(
            fn (array $match) => $this->targetToResult($match, $match['label'] ?? null, $match['sublabel'] ?? null),
            $resolution['matches'],
        )));
    }

    /**
     * @param  array{type:string,id:int}  $target
     * @return array{type:string,id:int,label:string,sublabel:string,url:string}|null
     */
    private function targetToResult(array $target, ?string $label = null, ?string $sublabel = null): ?array
    {
        $studentId = $this->ownerStudentId($target);
        if ($studentId === null) {
            return null;
        }

        $type = (string) $target['type'];
        $id = (int) $target['id'];
        $focus = $type !== 'student' ? "{$type}:{$id}" : null;

        $student = Student::find($studentId);

        return [
            'type' => $type,
            'id' => $studentId, // navigate to the owning student
            'label' => $label ?? (string) ($student?->full_name ?? ''),
            'sublabel' => $sublabel ?? (string) ($student?->student_id ?? ''),
            'url' => route('finance.students.overview', $focus
                ? ['student' => $studentId, 'focus' => $focus]
                : ['student' => $studentId]),
        ];
    }

    private function ownerStudentId(array $target): ?int
    {
        $type = (string) ($target['type'] ?? '');
        $id = (int) ($target['id'] ?? 0);

        $studentId = match ($type) {
            'student' => $id,
            'invoice' => StudentInvoice::find($id)?->student_id,
            'payment' => Payment::find($id)?->student_id,
            'charge' => FinanceCharge::find($id)?->student_id,
            'dng' => DngPaymentRequest::find($id)?->student_id,
            default => null,
        };

        return $studentId !== null ? (int) $studentId : null;
    }

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');

        return $campus?->id !== null ? (int) $campus->id : null;
    }
}
