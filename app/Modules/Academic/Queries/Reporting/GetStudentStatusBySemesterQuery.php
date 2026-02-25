<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries\Reporting;

use App\Enums\StudentActionType;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetStudentStatusBySemesterQuery
{
    public function handle(
        int $selectedSemesterId,
        ?int $campusId = null,
        ?string $currentStatus = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        $selectedSemester = Semester::query()->findOrFail($selectedSemesterId);
        $selectedStart = Carbon::parse($selectedSemester->start_date)->startOfDay();

        $studentsQuery = $this->buildStudentsQuery($selectedStart, $campusId, $currentStatus)
            ->orderBy('intake_semester_id')
            ->orderBy('student_id');

        $paginator = $studentsQuery->paginate($perPage)->withQueryString();
        $rows = $this->mapStudents(collect($paginator->items()), $selectedStart);

        return $paginator->setCollection($rows);
    }

    public function handleExport(
        int $selectedSemesterId,
        ?int $campusId = null,
        ?string $currentStatus = null
    ): Collection {
        $selectedSemester = Semester::query()->findOrFail($selectedSemesterId);
        $selectedStart = Carbon::parse($selectedSemester->start_date)->startOfDay();

        $students = $this->buildStudentsQuery($selectedStart, $campusId, $currentStatus)
            ->orderBy('intake_semester_id')
            ->orderBy('student_id')
            ->get();

        return $this->mapStudents($students, $selectedStart);
    }

    private function buildStudentsQuery(Carbon $selectedStart, ?int $campusId, ?string $currentStatus): Builder
    {
        return Student::query()
            ->with(['intakeSemester:id,code,name,start_date', 'campus:id,name,code', 'program:id,name'])
            ->select([
                'id',
                'student_id',
                'full_name',
                'campus_id',
                'program_id',
                'intake_semester_id',
                'intake_gc',
                'intake_major',
                'intake_course',
                'status',
                'user_id',
                'updated_at',
            ])
            ->whereHas('intakeSemester', function ($query) use ($selectedStart) {
                $query->whereDate('start_date', '<=', $selectedStart->toDateString());
            })
            ->when($campusId, function ($query, $currentCampusId) {
                $query->where('campus_id', $currentCampusId);
            })
            ->when($currentStatus, function ($query, $status) {
                $query->where('status', $status);
            });
    }

    private function mapStudents(Collection $students, Carbon $selectedStart): Collection
    {
        $studentIds = $students->pluck('id')->all();

        if (empty($studentIds)) {
            return collect();
        }

        $actionsByStudent = StudentActionLog::query()
            ->whereIn('student_id', $studentIds)
            ->with([
                'fromSemester:id,code,name,start_date',
                'returnSemester:id,code,name,start_date',
                'intendedIntakeSemester:id,code,name,start_date',
                'dropoutSemester:id,code,name,start_date',
                'effectiveSemester:id,code,name,start_date',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        return $students->map(function (Student $student) use ($actionsByStudent, $selectedStart) {
            $actions = $actionsByStudent->get($student->id, collect());

            $latestStatusAction = null;
            $latestStatusActionStart = null;
            $latestActionAtSelected = null;
            $latestActionAtSelectedStart = null;

            foreach ($actions as $action) {
                $relevantSemester = $this->resolveRelevantSemester($action);
                if (! $relevantSemester?->start_date) {
                    continue;
                }

                $relevantStart = Carbon::parse($relevantSemester->start_date)->startOfDay();
                if ($relevantStart->gt($selectedStart)) {
                    continue;
                }

                if ($latestActionAtSelectedStart === null || $relevantStart->gt($latestActionAtSelectedStart) || (
                    $relevantStart->equalTo($latestActionAtSelectedStart) && $action->id > $latestActionAtSelected->id
                )) {
                    $latestActionAtSelected = $action;
                    $latestActionAtSelectedStart = $relevantStart;
                }

                $newStatus = $action->new_status;
                if (empty($newStatus)) {
                    continue;
                }

                if ($latestStatusActionStart === null || $relevantStart->gt($latestStatusActionStart) || (
                    $relevantStart->equalTo($latestStatusActionStart) && $action->id > $latestStatusAction->id
                )) {
                    $latestStatusAction = $action;
                    $latestStatusActionStart = $relevantStart;
                }
            }

            $statusAtSelectedSemester = $latestStatusAction?->new_status ?? $this->resolveInitialStatus($student);
            $latestAction = $latestActionAtSelected;

            $deferStartSemester = $actions->reverse()->first(function (StudentActionLog $action) use ($selectedStart) {
                return $this->actionTypeValue($action) === StudentActionType::ACADEMIC_DEFER->value
                    && $this->actionAppliesOnOrBeforeSemester($action, $selectedStart);
            })?->fromSemester?->code;

            $dropoutSemester = $actions->reverse()->first(function (StudentActionLog $action) use ($selectedStart) {
                return $this->actionTypeValue($action) === StudentActionType::ACADEMIC_DROPOUT->value
                    && $this->actionAppliesOnOrBeforeSemester($action, $selectedStart);
            })?->dropoutSemester?->code;

            return [
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'program_name' => $student->program?->name,
                'intake_semester' => $student->intakeSemester?->code,
                'intake_year' => $student->intakeSemester?->start_date
                    ? (int) Carbon::parse($student->intakeSemester->start_date)->year
                    : null,
                'current_campus' => $student->campus?->name,
                'status_at_selected_semester' => $statusAtSelectedSemester,
                'current_status' => $student->status,
                'latest_action_type' => $this->actionTypeValue($latestAction),
                'latest_action_effective_semester' => $this->resolveRelevantSemester($latestAction)?->code,
                'ne' => $student->user_id !== null,
                'defer_start_semester' => $deferStartSemester,
                'dropout_semester' => $dropoutSemester,
                'updated_at' => $student->updated_at?->toDateTimeString(),
            ];
        });
    }

    private function resolveInitialStatus(Student $student): string
    {
        if (in_array($student->status, ['pending', 'admission_deferred'], true)) {
            return $student->status;
        }

        if ($student->intake_gc !== null && $student->intake_gc === $student->intake_semester_id) {
            return 'intake_pre_uni_gc';
        }

        if ($student->intake_major !== null && $student->intake_major === $student->intake_semester_id) {
            return 'intake_course';
        }

        if ($student->intake_major !== null) {
            return 'intake_course';
        }

        if ($student->intake_gc !== null) {
            return 'intake_pre_uni_gc';
        }

        return $student->status ?? 'pending';
    }

    private function resolveRelevantSemester(?StudentActionLog $action): ?Semester
    {
        if (! $action) {
            return null;
        }

        return match ($this->actionTypeValue($action)) {
            StudentActionType::ACADEMIC_DEFER->value => $action->fromSemester,
            StudentActionType::ACADEMIC_RESUME->value => $action->returnSemester,
            StudentActionType::ADMISSION_DEFERRAL->value => $action->intendedIntakeSemester,
            StudentActionType::ACADEMIC_DROPOUT->value => $action->dropoutSemester,
            default => $action->effectiveSemester,
        };
    }

    private function actionTypeValue(?StudentActionLog $action): ?string
    {
        if (! $action) {
            return null;
        }

        return $action->action_type instanceof StudentActionType
            ? $action->action_type->value
            : (string) $action->action_type;
    }

    private function actionAppliesOnOrBeforeSemester(StudentActionLog $action, Carbon $selectedStart): bool
    {
        $relevantSemester = $this->resolveRelevantSemester($action);
        if (! $relevantSemester?->start_date) {
            return false;
        }

        return Carbon::parse($relevantSemester->start_date)->startOfDay()->lte($selectedStart);
    }
}
