<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use Illuminate\Database\Eloquent\Builder;

final class LifecycleDueItemPredicate
{
    /**
     * Resolves through the live enrollment projection rather than the
     * write-dead `students.status` column. {@see LifecycleDueExceptionReasonResolver::resolve()}
     * must migrate the same way, in the same commit — it is called
     * immediately after this gate and the persisted reason must agree with it.
     */
    public static function isLifecycleException(?Student $student): bool
    {
        // An unpersisted model (no id) cannot have a program_enrollments row;
        // its in-memory `status` attribute is all there is to go on.
        if ($student === null || $student->id === null) {
            return self::isLifecycleExceptionStatus($student?->status);
        }

        $status = app(StudentLifecycleStatusReader::class)->statusesFor([(int) $student->id])[(int) $student->id] ?? null;

        return self::isLifecycleExceptionStatus($status);
    }

    public static function isLifecycleExceptionStatus(?string $status): bool
    {
        if ($status === null) {
            return true;
        }

        return ! in_array($status, Student::FINANCIAL_STATUSES, true);
    }

    /**
     * `students.status` is a legacy column that program-enrollment transitions
     * don't write back to. Match the live enrollment projection (program_enrollments)
     * instead so a student who has actually progressed isn't silently dropped from
     * (or wrongly kept in) the DNG collection scope by a stale column.
     *
     * @param  Builder<DngPaymentRequest>  $query
     */
    public static function applyActiveCollectionScope(Builder $query, ?int $campusId = null): void
    {
        if (! self::hasStudentsJoin($query)) {
            $query->join('students', 'students.id', '=', 'dng_payment_requests.student_id');
        }

        self::whereFinancial($query)
            ->when($campusId, fn (Builder $campusQuery) => $campusQuery->where('students.campus_id', $campusId))
            ->select('dng_payment_requests.*');
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     */
    public static function applyLifecycleExceptionScope(Builder $query): void
    {
        $query->where(function (Builder $exceptionQuery): void {
            $exceptionQuery
                ->whereNull('student_id')
                ->orWhereDoesntHave('student')
                ->orWhereHas('student', function (Builder $studentQuery): void {
                    self::whereNotFinancial($studentQuery);
                });
        });
    }

    /**
     * "Financial" = has a materialized primary program_enrollments row that is
     * active with a financial study_stage, OR (only when the student has never
     * been materialized at all) the legacy `students.status` column says so.
     * The legacy fallback exists because materialization is lazy — some
     * students may not have a program_enrollments row yet.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query  a builder joined/scoped to the `students` table
     */
    private static function whereFinancial(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            $outer->whereExists(fn ($sub) => self::materializedFinancialSubquery($sub))
                ->orWhere(function (Builder $legacy): void {
                    $legacy->whereNotExists(fn ($sub) => self::primaryEnrollmentSubquery($sub))
                        ->whereIn('students.status', Student::FINANCIAL_STATUSES);
                });
        });
    }

    /**
     * Inverse of {@see self::whereFinancial()}, kept as its own predicate
     * (rather than a literal negation) so the materialized-vs-legacy branches
     * stay easy to read and reason about independently.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query  a builder joined/scoped to the `students` table
     */
    private static function whereNotFinancial(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            $outer->where(function (Builder $materialized): void {
                $materialized->whereExists(fn ($sub) => self::primaryEnrollmentSubquery($sub))
                    ->whereNotExists(fn ($sub) => self::materializedFinancialSubquery($sub));
            })->orWhere(function (Builder $legacy): void {
                $legacy->whereNotExists(fn ($sub) => self::primaryEnrollmentSubquery($sub))
                    ->whereNotIn('students.status', Student::FINANCIAL_STATUSES);
            });
        });
    }

    private static function primaryEnrollmentSubquery(\Illuminate\Database\Query\Builder $sub): \Illuminate\Database\Query\Builder
    {
        return $sub->from('program_enrollments')
            ->whereColumn('program_enrollments.student_id', 'students.id')
            ->where('program_enrollments.is_primary', true);
    }

    private static function materializedFinancialSubquery(\Illuminate\Database\Query\Builder $sub): \Illuminate\Database\Query\Builder
    {
        return self::primaryEnrollmentSubquery($sub)
            ->where('program_enrollments.enrollment_status', 'active')
            ->whereIn('program_enrollments.study_stage', Student::FINANCIAL_STATUSES);
    }

    public static function isOpenDueRequest(DngPaymentRequest $request): bool
    {
        return $request->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
            && $request->due_date !== null;
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     */
    private static function hasStudentsJoin(Builder $query): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === 'students') {
                return true;
            }
        }

        return false;
    }
}
