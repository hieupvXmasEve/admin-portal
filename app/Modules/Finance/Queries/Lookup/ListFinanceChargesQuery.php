<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Lookup;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListFinanceChargesQuery
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    /** Columns the client may sort by (anything else falls back to the default). */
    private const SORTABLE = ['amount', 'effective_at', 'status', 'charge_type', 'created_at'];

    /**
     * @return array{items: LengthAwarePaginator}
     */
    public function handle(Request $request, ?int $studentId = null): array
    {
        $query = FinanceCharge::query()->with(['semester', 'createdBy']);

        $resolvedStudentId = $studentId ?? ($request->filled('student_id') ? (int) $request->input('student_id') : null);
        if ($resolvedStudentId !== null) {
            $query->where('student_id', $resolvedStudentId);
        }

        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $studentIds = $this->studentReferences->idsMatchingSearch($term);
            $query->where(function ($query) use ($term, $studentIds): void {
                $query->where('description', 'like', "%{$term}%")
                    ->orWhereIn('student_id', $studentIds);
            });
        }

        $semesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : FinanceSemesterContextResolver::selectedId();

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        $chargeType = $request->input('charge_type');
        if ($request->filled('charge_type') && $chargeType !== 'all') {
            $query->where('charge_type', $chargeType);
        }

        $status = $request->input('status');
        if ($request->filled('status') && $status !== 'all') {
            $query->where('status', $status);
        }

        $sort = (string) $request->input('sort', '');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('effective_at');
        }

        $perPage = (int) $request->input('per_page', 20);

        $items = $query->paginate($perPage)->withQueryString();
        $studentReferences = $this->studentReferences->findMany(
            $items->getCollection()
                ->pluck('student_id')
                ->map(static fn (int|string $studentId): int => (int) $studentId)
                ->all(),
        );

        $items->setCollection($items->getCollection()->map(
            static function (FinanceCharge $charge) use ($studentReferences): FinanceCharge {
                $charge->setRelation('student', self::studentPayload($studentReferences[(int) $charge->student_id] ?? null));

                return $charge;
            },
        ));

        return ['items' => $items];
    }

    /**
     * @return array{id: int, full_name: string, student_id: string, email: string|null}|null
     */
    private static function studentPayload(?StudentReference $student): ?array
    {
        if ($student === null) {
            return null;
        }

        return [
            'id' => $student->id,
            'full_name' => $student->fullName,
            'student_id' => $student->studentCode,
            'email' => $student->email,
        ];
    }
}
