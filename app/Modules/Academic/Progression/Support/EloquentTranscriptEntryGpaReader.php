<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Semester;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\DTO\TranscriptEntryGpaData;
use App\Shared\Contracts\Academic\TranscriptEntryGpaReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentTranscriptEntryGpaReader implements TranscriptEntryGpaReader
{
    public function forSemester(int $studentId, int|string $semesterId): array
    {
        return $this->base($studentId)->where('semester_id', $semesterId)->get()->map(fn (TranscriptEntry $entry) => $this->data($entry))->all();
    }

    public function throughSemester(int $studentId, int|string|null $semesterId): array
    {
        $query = $this->base($studentId);
        if ($semesterId !== null) {
            $target = Semester::query()->find($semesterId);
            $target?->start_date !== null
                ? $query->whereIn('semester_id', Semester::query()->where('start_date', '<=', $target->start_date)->select('id'))
                : $query->where('semester_id', '<=', $semesterId);
        }

        return $query->get()->map(fn (TranscriptEntry $entry) => $this->data($entry))->all();
    }

    private function base(int $studentId): Builder
    {
        return TranscriptEntry::query()->where('student_id', $studentId)->where('excluded_from_gpa', false)->where('credit_points', '>', 0);
    }

    private function data(TranscriptEntry $entry): TranscriptEntryGpaData
    {
        return new TranscriptEntryGpaData(
            (float) $entry->final_percentage,
            $entry->final_letter_grade,
            (float) $entry->credit_points,
            (float) $entry->credit_points_earned,
            (float) $entry->quality_points,
            $entry->is_passed,
        );
    }
}
