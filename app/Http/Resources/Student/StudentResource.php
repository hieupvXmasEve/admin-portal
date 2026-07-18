<?php

declare(strict_types=1);

namespace App\Http\Resources\Student;

use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

class StudentResource extends JsonResource
{
    public static function collection($resource): AnonymousResourceCollection
    {
        self::preloadProgramEnrollmentSummaries($resource);

        return parent::collection($resource);
    }

    /** @param iterable<int, Model> $students */
    public static function preloadProgramEnrollmentSummaries(iterable $students): void
    {
        $studentCollection = $students instanceof AbstractPaginator
            ? $students->getCollection()
            : collect($students);
        $summaries = app(ProgramEnrollmentReader::class)->forStudentIds(
            $studentCollection->pluck('id')->map(static fn (int|string $id): int => (int) $id)->all(),
        );

        $studentCollection->each(static function (Model $student) use ($summaries): void {
            if (isset($summaries[$student->getKey()])) {
                $student->setRelation('programEnrollmentSummary', $summaries[$student->getKey()]);
            }
        });
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $programEnrollment = $this->resource->relationLoaded('programEnrollmentSummary')
            ? $this->resource->getRelation('programEnrollmentSummary')
            : app(ProgramEnrollmentReader::class)->forStudentId((int) $this->id);
        $lifecycleStatus = $programEnrollment->legacyCompatibleStatus();

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $lifecycleStatus,
            'academic_status' => $programEnrollment->enrollmentStatus,
            'admission_date' => $this->admission_date?->format('Y-m-d'),
            'admission_notes' => $this->admission_notes,
            // 'expected_graduation_date' => $this->expected_graduation_date?->format('Y-m-d'),

            // Related data
            'campus_id' => $this->campus_id,
            'program_id' => $this->program_id,
            'specialization_id' => $this->specialization_id,
            'curriculum_version_id' => $this->curriculum_version_id,

            // Relationships (when loaded)
            'campus' => $this->whenLoaded('campus', function () {
                return [
                    'id' => $this->campus->id,
                    'name' => $this->campus->name,
                    'code' => $this->campus->code,
                ];
            }),

            'program' => $this->whenLoaded('program', function () {
                return [
                    'id' => $this->program->id,
                    'name' => $this->program->name,
                    'code' => $this->program->code,
                ];
            }),

            'specialization' => $this->whenLoaded('specialization', function () {
                return [
                    'id' => $this->specialization->id,
                    'name' => $this->specialization->name,
                ];
            }),

            'curriculum_version' => $this->whenLoaded('curriculumVersion', function () {
                return [
                    'id' => $this->curriculumVersion->id,
                    'name' => $this->curriculumVersion->name,
                    'version' => $this->curriculumVersion->version,
                ];
            }),

            // Computed fields
            'display_name' => $this->full_name.' ('.$this->student_id.')',
            'status_label' => ucfirst(str_replace('_', ' ', $lifecycleStatus)),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
