<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentAcademicRecords
{
    /**
     * @param  array{
     *     summary: array{latest_semester_gpa: float|string, cumulative_gpa: float|string, academic_standing: string, credits_earned: float|string},
     *     history: list<array{semester_name: ?string, semester_gpa: float|string, cumulative_gpa: float|string, academic_standing: ?string, credits_attempted: float|string, credits_earned: float|string, finalized_at: ?string}>
     * }  $payload
     */
    /** @param list<array{semester: ?string, semester_code: ?string, gpa: float, credit_hours: float, quality_points: float, academic_standing: ?string, created_at: ?string}> $gpaTrendHistory */
    public function __construct(
        private array $payload,
        private array $gpaTrendHistory = [],
    ) {}

    /**
     * @return array{
     *     summary: array{latest_semester_gpa: float|string, cumulative_gpa: float|string, academic_standing: string, credits_earned: float|string},
     *     history: list<array{semester_name: ?string, semester_gpa: float|string, cumulative_gpa: float|string, academic_standing: ?string, credits_attempted: float|string, credits_earned: float|string, finalized_at: ?string}>
     * }
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /** @return list<array{semester: ?string, semester_code: ?string, gpa: float, credit_hours: float, quality_points: float, academic_standing: ?string, created_at: ?string}> */
    public function gpaTrendHistory(): array
    {
        return $this->gpaTrendHistory;
    }
}
