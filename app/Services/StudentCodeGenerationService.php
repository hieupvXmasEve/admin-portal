<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;

class StudentCodeGenerationService
{
    /**
     * Generate a unique student code
     *
     * @param string $campusCode The campus code (e.g., 'SAI', 'HCM')
     * @param int|null $year The year for the student code (defaults to current year)
     * @return string The generated student code
     */
    public function generateStudentCode(string $campusCode, ?int $year = null): string
    {
        $year = $year ?? now()->year;
        
        // Format: CAMPUSCODE + YEAR + 3-digit sequence number
        // Example: SAI20250001, HCM20250002
        $prefix = $campusCode . $year;
        
        // Get the next sequence number for this campus and year
        $sequenceNumber = $this->getNextSequenceNumber($prefix);
        
        return $prefix . str_pad((string) $sequenceNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate student code using campus ID
     *
     * @param int $campusId The campus ID
     * @param int|null $year The year for the student code
     * @return string The generated student code
     * @throws \Exception If campus not found
     */
    public function generateStudentCodeByCampusId(int $campusId, ?int $year = null): string
    {
        $campus = Campus::find($campusId);
        
        if (!$campus) {
            throw new \Exception("Campus with ID {$campusId} not found");
        }
        
        return $this->generateStudentCode($campus->code, $year);
    }

    /**
     * Get the next sequence number for a given prefix
     *
     * @param string $prefix The prefix (campus code + year)
     * @return int The next sequence number
     */
    private function getNextSequenceNumber(string $prefix): int
    {
        // Use database transaction to ensure uniqueness
        return DB::transaction(function () use ($prefix) {
            // Get the highest existing sequence number for this prefix
            $lastStudent = Student::where('student_code', 'like', $prefix . '%')
                ->orderBy('student_code', 'desc')
                ->lockForUpdate() // Prevent race conditions
                ->first();

            if ($lastStudent) {
                // Extract the sequence number from the last student code
                $lastSequence = (int) substr($lastStudent->student_code, strlen($prefix));
                return $lastSequence + 1;
            }

            // First student for this prefix
            return 1;
        });
    }

    /**
     * Validate if a student code follows the correct format
     *
     * @param string $studentCode The student code to validate
     * @return bool True if valid format
     */
    public function isValidStudentCodeFormat(string $studentCode): bool
    {
        // Format: 3+ chars (campus) + 4 digits (year) + 4 digits (sequence)
        // Minimum length: 11 characters
        return preg_match('/^[A-Z]{2,5}\d{8}$/', $studentCode) === 1;
    }

    /**
     * Check if a student code already exists
     *
     * @param string $studentCode The student code to check
     * @return bool True if exists
     */
    public function studentCodeExists(string $studentCode): bool
    {
        return Student::where('student_code', $studentCode)->exists();
    }

    /**
     * Generate multiple unique student codes for batch creation
     *
     * @param string $campusCode The campus code
     * @param int $count Number of codes to generate
     * @param int|null $year The year for the student codes
     * @return array Array of unique student codes
     */
    public function generateMultipleStudentCodes(string $campusCode, int $count, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $prefix = $campusCode . $year;
        
        return DB::transaction(function () use ($prefix, $count) {
            $codes = [];
            $startingSequence = $this->getNextSequenceNumber($prefix);
            
            for ($i = 0; $i < $count; $i++) {
                $sequenceNumber = $startingSequence + $i;
                $codes[] = $prefix . str_pad((string) $sequenceNumber, 4, '0', STR_PAD_LEFT);
            }
            
            return $codes;
        });
    }
}