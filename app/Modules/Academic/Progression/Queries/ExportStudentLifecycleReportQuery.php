<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Enums\AcademicProgressionEventType;
use App\Models\AcademicProgressionEvent;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Queries\Reporting\GetStudentStatusBySemesterQuery;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExportStudentLifecycleReportQuery
{
    private const LEVEL_EVENT_TYPES = [
        AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
        AcademicProgressionEventType::PLACEMENT_INITIALIZED,
    ];

    public function __construct(
        private readonly GetStudentStatusBySemesterQuery $statusBySemesterQuery,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'student_code',
            'student_name',
            'campus_name',
            'program_name',
            'intake_semester',
            'intake_year',
            'semester_code',
            'semester_name',
            'semester_start_date',
            'status_at_semester',
            'current_status',
            'gc_level_at_semester',
            'gc_block_1_level',
            'gc_block_1_result',
            'gc_block_2_level',
            'gc_block_2_result',
            'gc_current_level',
            'gc_starting_level',
            'latest_action_type',
            'latest_action_semester',
            'defer_start_semester',
            'dropout_semester',
            'ne',
        ];
    }

    public function rows(): Generator
    {
        $semesters = Semester::query()
            ->orderBy('start_date')
            ->get(['id', 'code', 'name', 'start_date']);

        $gcLevels = $this->loadGcLevels();
        $progressionByStudent = $this->loadProgressionEvents();
        $egcBlocksByStudentSemester = $this->loadEgcBlocks();

        foreach ($semesters as $semester) {
            $statusRows = $this->statusBySemesterQuery->handleExport(
                $semester->id,
                null,
                null,
            );

            foreach ($statusRows as $row) {
                yield $this->formatRow(
                    $row,
                    $semester,
                    $gcLevels,
                    $progressionByStudent,
                    $egcBlocksByStudentSemester,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int|string|null>
     */
    private function formatRow(
        array $row,
        Semester $semester,
        Collection $gcLevels,
        Collection $progressionByStudent,
        array $egcBlocksByStudentSemester,
    ): array {
        $studentId = (int) $row['id'];
        $gcSnapshot = $gcLevels->get($studentId);
        $egcBlocks = $this->resolveEgcBlocks($studentId, $semester->id, $egcBlocksByStudentSemester);

        return [
            $row['student_id'],
            $row['full_name'],
            $row['current_campus'],
            $row['program_name'],
            $row['intake_semester'],
            $row['intake_year'],
            $semester->code,
            $semester->name,
            $semester->start_date ? Carbon::parse($semester->start_date)->toDateString() : null,
            $row['status_at_selected_semester'],
            $row['current_status'],
            $this->resolveGcLevelAtSemester($studentId, $semester, $progressionByStudent),
            $egcBlocks['block_1_level'],
            $egcBlocks['block_1_result'],
            $egcBlocks['block_2_level'],
            $egcBlocks['block_2_result'],
            $gcSnapshot?->gc_current_level,
            $gcSnapshot?->gc_starting_level,
            $row['latest_action_type'],
            $row['latest_action_effective_semester'],
            $row['defer_start_semester'],
            $row['dropout_semester'],
            ! empty($row['ne']) ? 'yes' : 'no',
        ];
    }

    private function resolveGcLevelAtSemester(
        int $studentId,
        Semester $semester,
        Collection $progressionByStudent,
    ): ?int {
        if (! $semester->start_date) {
            return null;
        }

        $semesterStart = Carbon::parse($semester->start_date)->startOfDay();
        $events = $progressionByStudent->get($studentId, collect())
            ->filter(function (AcademicProgressionEvent $event) use ($semesterStart): bool {
                if (! $event->semester?->start_date) {
                    return false;
                }

                return Carbon::parse($event->semester->start_date)->startOfDay()->lte($semesterStart);
            })
            ->sortByDesc(fn (AcademicProgressionEvent $event): int => ($event->effective_at ?? $event->created_at)?->getTimestamp() ?? 0);

        $level = $events->first()?->to_english_level;

        return $level !== null ? (int) $level : null;
    }

    /**
     * @return array{
     *     block_1_level: int|null,
     *     block_1_result: string|null,
     *     block_2_level: int|null,
     *     block_2_result: string|null
     * }
     */
    private function resolveEgcBlocks(int $studentId, int $semesterId, array $egcBlocksByStudentSemester): array
    {
        $blocks = $egcBlocksByStudentSemester["{$studentId}-{$semesterId}"] ?? [];

        return [
            'block_1_level' => isset($blocks[1]) ? (int) $blocks[1]['level_number'] : null,
            'block_1_result' => $blocks[1]['result'] ?? null,
            'block_2_level' => isset($blocks[2]) ? (int) $blocks[2]['level_number'] : null,
            'block_2_result' => $blocks[2]['result'] ?? null,
        ];
    }

    private function loadGcLevels(): Collection
    {
        return Student::query()
            ->select(['id', 'gc_current_level', 'gc_starting_level'])
            ->get()
            ->keyBy('id');
    }

    private function loadProgressionEvents(): Collection
    {
        return AcademicProgressionEvent::query()
            ->whereIn('event_type', array_map(
                static fn (AcademicProgressionEventType $type): string => $type->value,
                self::LEVEL_EVENT_TYPES,
            ))
            ->with('semester:id,code,name,start_date')
            ->orderBy('effective_at')
            ->get()
            ->groupBy('student_id');
    }

    /**
     * @return array<string, array<int, array{level_number: int, result: string}>>
     */
    private function loadEgcBlocks(): array
    {
        $map = [];

        foreach (DB::table('egc_blocks')->orderBy('block_number')->get() as $block) {
            $key = "{$block->student_id}-{$block->semester_id}";
            $map[$key][(int) $block->block_number] = [
                'level_number' => (int) $block->level_number,
                'result' => (string) $block->result,
            ];
        }

        return $map;
    }
}
