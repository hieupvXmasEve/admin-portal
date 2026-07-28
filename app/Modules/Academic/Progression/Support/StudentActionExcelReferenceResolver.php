<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class StudentActionExcelReferenceResolver
{
    /** @var array<string, Student|null> */
    private array $studentCache = [];

    /** @var array<string, Semester|null> */
    private array $semesterCache = [];

    /** @var array<string, Campus|null> */
    private array $campusCache = [];

    public function studentByCode(string $studentCode): ?Student
    {
        if (! array_key_exists($studentCode, $this->studentCache)) {
            $this->studentCache[$studentCode] = Student::query()->where('student_id', $studentCode)->first();
        }

        return $this->studentCache[$studentCode];
    }

    public function semesterByCode(string $code): ?Semester
    {
        if (! array_key_exists($code, $this->semesterCache)) {
            $this->semesterCache[$code] = Semester::query()->where('code', $code)->first();
        }

        return $this->semesterCache[$code];
    }

    public function campusByCode(string $code): ?Campus
    {
        if (! array_key_exists($code, $this->campusCache)) {
            $this->campusCache[$code] = Campus::query()->where('code', $code)->first();
        }

        return $this->campusCache[$code];
    }

    public function semesterByDateTime(string $dateTime): ?Semester
    {
        if (! array_key_exists($dateTime, $this->semesterCache)) {
            $this->semesterCache[$dateTime] = Semester::query()
                ->whereDate('start_date', '<=', $dateTime)
                ->whereDate('end_date', '>=', $dateTime)
                ->first();
        }

        return $this->semesterCache[$dateTime];
    }

    public function normalizeDate(mixed $value): ?string
    {
        if (trim((string) ($value ?? '')) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        return Carbon::parse((string) $value)->format('Y-m-d');
    }

    public function normalizeDateTime(mixed $value): ?string
    {
        if (trim((string) ($value ?? '')) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d H:i:s');
        }

        return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }
}
