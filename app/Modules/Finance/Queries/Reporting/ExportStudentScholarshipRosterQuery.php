<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ExportStudentScholarshipRosterQuery
{
    public function __construct(
        private readonly StudentReferenceReader $studentReferences,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly CampusReferenceReader $campusReferences,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'student_code',
            'student_name',
            'student_email',
            'campus_name',
            'program_name',
            'intake',
            'has_scholarship',
            'scholarship_code',
            'scholarship_name',
            'scholarship_type',
            'scholarship_amount',
            'total_amount',
            'total_terms',
            'valid_from',
            'valid_until',
            'scholarship_is_active',
            'awarded_at',
            'award_notes',
        ];
    }

    public function cursor(): LazyCollection
    {
        $campuses = collect($this->campusReferences->all())->keyBy('id');

        return LazyCollection::make(function () use ($campuses): iterable {
            $studentChunks = LazyCollection::make(
                fn () => yield from $this->studentReferences->stream(),
            )->chunk(500);

            foreach ($studentChunks as $students) {
                $studentIds = $students
                    ->map(static fn (StudentReference $student): int => $student->id)
                    ->all();
                $enrollments = $this->programEnrollments->forStudentIds($studentIds);
                $awards = $this->buildAwardQuery($studentIds)->get()->groupBy('student_id');

                foreach ($students as $student) {
                    $studentAwards = $awards->get($student->id, collect([null]));
                    if ($studentAwards->isEmpty()) {
                        $studentAwards = collect([null]);
                    }

                    foreach ($studentAwards as $award) {
                        $enrollment = $enrollments[$student->id];
                        $campus = $campuses->get($student->campusId);

                        yield (object) [
                            'student_code' => $student->studentCode,
                            'student_name' => $student->fullName,
                            'student_email' => $student->email,
                            'campus_name' => $campus?->name,
                            'program_name' => $enrollment->programName,
                            'intake' => $student->cohort,
                            'scholarship_code' => $award?->scholarship_code,
                            'scholarship_name' => $award?->scholarship_name,
                            'scholarship_type' => $award?->scholarship_type,
                            'scholarship_amount' => $award?->scholarship_amount,
                            'total_amount' => $award?->total_amount,
                            'total_terms' => $award?->total_terms,
                            'valid_from' => $award?->valid_from,
                            'valid_until' => $award?->valid_until,
                            'scholarship_is_active' => $award?->scholarship_is_active,
                            'awarded_at' => $award?->awarded_at,
                            'award_notes' => $award?->award_notes,
                        ];
                    }
                }
            }
        });
    }

    /**
     * @return list<int|string|null>
     */
    public function formatRow(object $row): array
    {
        return [
            $row->student_code,
            $row->student_name,
            $row->student_email,
            $row->campus_name,
            $row->program_name,
            $row->intake,
            $row->scholarship_code !== null ? 'yes' : 'no',
            $row->scholarship_code,
            $row->scholarship_name,
            $row->scholarship_type,
            $row->scholarship_amount,
            $row->total_amount,
            $row->total_terms,
            $row->valid_from,
            $row->valid_until,
            $this->formatBoolean($row->scholarship_is_active),
            $row->awarded_at,
            $row->award_notes,
        ];
    }

    /** @param list<int> $studentIds */
    private function buildAwardQuery(array $studentIds): Builder
    {
        return DB::table('student_scholarship_awards as ssa')
            ->leftJoin('scholarship_definitions as sd', 'sd.code', '=', 'ssa.scholarship_code')
            ->whereIn('ssa.student_id', $studentIds)
            ->select([
                'ssa.student_id',
                'ssa.scholarship_code',
                'sd.name as scholarship_name',
                'sd.type as scholarship_type',
                'sd.amount as scholarship_amount',
                'sd.total_amount',
                'sd.total_terms',
                'sd.valid_from',
                'sd.valid_until',
                'sd.is_active as scholarship_is_active',
                'ssa.awarded_at',
                'ssa.notes as award_notes',
            ]);
    }

    private function formatBoolean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
    }
}
