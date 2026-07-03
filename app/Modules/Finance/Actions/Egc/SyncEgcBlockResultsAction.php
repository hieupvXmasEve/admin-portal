<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\AcademicRecord;
use App\Models\EgcBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncEgcBlockResultsAction
{
    /**
     * Sync EGC block results from academic_records for a given semester.
     *
     * Resolution rules:
     * - override_pass = true → result = pass
     * - else: latest is_passed by recorded_at DESC
     * - attendance_percentage from the resolved record
     */
    public static function run(int $semesterId): array
    {
        $blocks = EgcBlock::where('semester_id', $semesterId)
            ->orderBy('student_id')
            ->orderBy('block_number')
            ->get();

        $synced = 0;
        $skipped = [];

        foreach ($blocks->groupBy('student_id') as $studentId => $studentBlocks) {
            $resolvedByBlock = self::resolveResultsForStudent((int) $studentId, $semesterId, $studentBlocks);

            foreach ($studentBlocks as $block) {
                $resolved = $resolvedByBlock[$block->id] ?? null;

                if ($resolved === null) {
                    continue;
                }

                if (
                    $resolved['result'] === EgcBlock::RESULT_FAIL
                    && self::hasImmediateNextBlockProgressionEvidence($block)
                ) {
                    $skipped[] = self::skippedRow($block, 'failed_result_conflicts_with_later_egc_progression');

                    continue;
                }

                $block->update([
                    'result' => $resolved['result'],
                    'attendance_rate' => $resolved['attendance_rate'],
                    'synced_at' => now(),
                ]);

                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'total' => $blocks->count(),
            'skipped' => $skipped,
            'reconciliation' => ReconcileEgcChargesAfterSyncAction::run($semesterId),
        ];
    }

    /**
     * Match blocks to EGC registrations by semester order, then resolve each attempt from the
     * academic record tied to that specific offering. This keeps duplicate same-level attempts separate.
     *
     * @return array<int, array{result: string, attendance_rate: float|null}|null>
     */
    private static function resolveResultsForStudent(int $studentId, int $semesterId, Collection $blocks): array
    {
        $registrations = DB::table('course_registrations')
            ->join('course_offerings', 'course_registrations.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_registrations.student_id', $studentId)
            ->where('course_offerings.semester_id', $semesterId)
            ->where('units.unit_type', 'egc')
            ->orderBy('course_registrations.id')
            ->select([
                'course_registrations.id as registration_id',
                'course_offerings.id as course_offering_id',
            ])
            ->get()
            ->values();

        $resolvedByBlock = [];

        foreach ($blocks->sortBy('block_number')->values() as $index => $block) {
            $registration = $registrations->get($index);

            if ($registration === null) {
                $resolvedByBlock[$block->id] = self::resolveResult($studentId, $semesterId, $block->level_number);

                continue;
            }

            $resolvedByBlock[$block->id] = self::resolveOfferingResult($studentId, $semesterId, (int) $registration->course_offering_id);
        }

        return $resolvedByBlock;
    }

    /**
     * Resolve the block result for a student + semester + level from academic_records.
     * Also used by backfill command.
     *
     * @return array{result: string, attendance_rate: float|null}|null
     */
    public static function resolveResult(int $studentId, int $semesterId, int $levelNumber): ?array
    {
        $records = AcademicRecord::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereHas('unit', fn ($q) => $q->where('unit_type', 'egc')->where('level', $levelNumber))
            ->orderByDesc('id')
            ->get();

        return self::resolveFromRecords($records);
    }

    /**
     * @return array{result: string, attendance_rate: float|null}|null
     */
    private static function resolveOfferingResult(int $studentId, int $semesterId, int $courseOfferingId): ?array
    {
        $records = AcademicRecord::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('course_offering_id', $courseOfferingId)
            ->orderByDesc('id')
            ->get();

        return self::resolveFromRecords($records);
    }

    /**
     * @param  Collection<int, AcademicRecord>  $records
     * @return array{result: string, attendance_rate: float|null}|null
     */
    private static function resolveFromRecords(Collection $records): ?array
    {
        if ($records->isEmpty()) {
            return null;
        }

        $overrideRecord = $records->firstWhere('override_pass', true);
        if ($overrideRecord !== null) {
            return [
                'result' => EgcBlock::RESULT_PASS,
                'attendance_rate' => $overrideRecord->attendance_percentage,
            ];
        }

        $latest = $records->first();
        if ($latest->completion_status === 'in_progress') {
            return [
                'result' => EgcBlock::RESULT_PENDING,
                'attendance_rate' => null,
            ];
        }

        return [
            'result' => $latest->is_passed ? EgcBlock::RESULT_PASS : EgcBlock::RESULT_FAIL,
            'attendance_rate' => $latest->attendance_percentage,
        ];
    }

    private static function hasImmediateNextBlockProgressionEvidence(EgcBlock $block): bool
    {
        $nextBlock = self::immediateNextBlock($block);

        return $nextBlock instanceof EgcBlock
            && (int) $nextBlock->level_number > (int) $block->level_number;
    }

    private static function immediateNextBlock(EgcBlock $block): ?EgcBlock
    {
        if ((int) $block->block_number === 1) {
            return EgcBlock::query()
                ->where('student_id', $block->student_id)
                ->where('semester_id', $block->semester_id)
                ->where('block_number', 2)
                ->whereKeyNot($block->id)
                ->first();
        }

        if ((int) $block->block_number !== 2) {
            return null;
        }

        $sourceStartDate = DB::table('semesters')
            ->where('id', $block->semester_id)
            ->value('start_date');

        if ($sourceStartDate === null) {
            return null;
        }

        $nextSemesterId = DB::table('semesters')
            ->where('id', '!=', $block->semester_id)
            ->where('is_archived', false)
            ->where('start_date', '>', $sourceStartDate)
            ->orderBy('start_date')
            ->orderBy('id')
            ->value('id');

        if ($nextSemesterId === null) {
            return null;
        }

        return EgcBlock::query()
            ->where('student_id', $block->student_id)
            ->where('semester_id', $nextSemesterId)
            ->where('block_number', 1)
            ->whereKeyNot($block->id)
            ->first();
    }

    private static function skippedRow(EgcBlock $block, string $reason): array
    {
        return [
            'student_id' => (int) $block->student_id,
            'source_egc_block_id' => (int) $block->id,
            'block_number' => (int) $block->block_number,
            'level_number' => (int) $block->level_number,
            'reason' => $reason,
        ];
    }
}
