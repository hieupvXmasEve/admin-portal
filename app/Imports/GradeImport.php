<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class GradeImport implements ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    protected AssessmentComponentDetail $assessmentComponentDetail;

    protected CourseOffering $courseOffering;

    protected array $options;

    protected array $importResults = [
        'total_rows' => 0,
        'successful_updates' => 0,
        'successful_creates' => 0,
        'errors' => [],
        'warnings' => [],
        'skipped' => 0,
    ];

    protected int $lecturerId;

    public function __construct(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering,
        array $options = [],
        int $lecturerId = 0
    ) {
        $this->assessmentComponentDetail = $assessmentComponentDetail;
        $this->courseOffering = $courseOffering;
        $this->options = array_merge([
            'update_mode' => 'update_and_create',
            'overwrite_existing' => false,
            'validate_only' => false,
            'skip_errors' => false,
            'default_status' => 'graded',
            'default_score_status' => 'provisional',
        ], $options);
        $this->lecturerId = $lecturerId;
    }

    /**
     * Process the imported collection
     */
    public function collection(Collection $rows): void
    {
        $this->importResults['total_rows'] = $rows->count();

        if ($this->options['validate_only']) {
            $this->validateRows($rows);

            return;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $this->processRow($row, $index + 2); // +2 because of header row and 0-based index
            }
        });
    }

    /**
     * Process a single row
     */
    protected function processRow(Collection $row, int $rowNumber): void
    {
        try {
            // Validate row data
            $validatedData = $this->validateRowData($row, $rowNumber);
            if (! $validatedData) {
                return; // Skip this row due to validation errors
            }

            // Find student
            $student = $this->findStudent($validatedData);
            if (! $student) {
                $this->addError($rowNumber, 'Student not found', $validatedData);

                return;
            }

            // Check if student is enrolled in the course
            if (! $this->isStudentEnrolled($student)) {
                $this->addError($rowNumber, 'Student is not enrolled in this course', $validatedData);

                return;
            }

            // Find or create score record
            $score = $this->findOrCreateScore($student, $validatedData, $rowNumber);
            if (! $score) {
                return; // Error already logged
            }

            // Update score with validated data
            $this->updateScore($score, $validatedData, $rowNumber);
        } catch (\Exception $e) {
            $this->addError($rowNumber, 'Unexpected error: ' . $e->getMessage(), $row->toArray());

            if (! $this->options['skip_errors']) {
                throw $e;
            }
        }
    }

    /**
     * Validate row data
     */
    protected function validateRowData(Collection $row, int $rowNumber): ?array
    {
        $data = [
            'student_id' => $row->get('student_id'),
            'points_earned' => $row->get('points_earned'),
            'percentage_score' => $row->get('percentage_score'),
            'letter_grade' => $row->get('letter_grade'),
            'status' => $row->get('status', $this->options['default_status']),
            'score_status' => $row->get('score_status', $this->options['default_score_status']),
            'instructor_feedback' => $row->get('instructor_feedback'),
            'bonus_points' => $row->get('bonus_points', 0),
            'bonus_reason' => $row->get('bonus_reason'),
            'late_penalty_applied' => $row->get('late_penalty_applied', 0),
            'private_notes' => $row->get('private_notes'),
        ];

        $rules = [
            'student_id' => 'required|string',
            'points_earned' => 'nullable|numeric|min:0',
            'percentage_score' => 'nullable|numeric|min:0|max:100',
            'letter_grade' => ['nullable', 'string', 'max:5', Rule::in(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F', 'I', 'W', 'P', 'NP'])],
            'status' => ['required', Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned'])],
            'score_status' => ['required', Rule::in(['draft', 'provisional', 'final'])],
            'instructor_feedback' => 'nullable|string|max:1000',
            'bonus_points' => 'nullable|numeric|min:0|max:100',
            'bonus_reason' => 'nullable|string|max:255',
            'late_penalty_applied' => 'nullable|numeric|min:0',
            'private_notes' => 'nullable|string|max:1000',
        ];

        // Add max points validation if available
        if ($this->assessmentComponentDetail->max_points) {
            $rules['points_earned'] .= '|max:' . $this->assessmentComponentDetail->max_points;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->addError($rowNumber, $error, $data);
            }

            return null;
        }

        return $validator->validated();
    }

    /**
     * Find student by ID or email
     */
    protected function findStudent(array $data): ?Student
    {
        // First try by student_id
        $student = Student::where('student_id', $data['student_id'])->first();

        if (! $student) {
            // Try by email as fallback
            $student = Student::where('email', $data['student_id'])->first();
        }

        return $student;
    }

    /**
     * Check if student is enrolled in the course
     */
    protected function isStudentEnrolled(Student $student): bool
    {
        return DB::table('course_registrations')
            ->where('student_id', $student->id)
            ->where('course_offering_id', $this->courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->exists();
    }

    /**
     * Find or create score record
     */
    protected function findOrCreateScore(Student $student, array $data, int $rowNumber): ?AssessmentComponentDetailScore
    {
        $score = AssessmentComponentDetailScore::where([
            'assessment_component_detail_id' => $this->assessmentComponentDetail->id,
            'student_id' => $student->id,
            'course_offering_id' => $this->courseOffering->id,
        ])->first();

        if ($score) {
            // Check if we should update existing scores
            if (! $this->options['overwrite_existing'] && $this->options['update_mode'] === 'create_missing') {
                $this->addWarning($rowNumber, 'Score already exists and overwrite is disabled', $data);
                $this->importResults['skipped']++;

                return null;
            }

            return $score;
        }

        // Create new score if allowed
        if (in_array($this->options['update_mode'], ['create_missing', 'update_and_create'])) {
            $score = new AssessmentComponentDetailScore([
                'assessment_component_detail_id' => $this->assessmentComponentDetail->id,
                'student_id' => $student->id,
                'course_offering_id' => $this->courseOffering->id,
                'graded_by_lecture_id' => $this->lecturerId,
                'graded_at' => now(),
                'last_modified_by_lecture_id' => $this->lecturerId,
                'last_modified_at' => now(),
            ]);

            return $score;
        }

        $this->addWarning($rowNumber, 'Score does not exist and create mode is disabled', $data);
        $this->importResults['skipped']++;

        return null;
    }

    /**
     * Update score with validated data
     */
    protected function updateScore(AssessmentComponentDetailScore $score, array $data, int $rowNumber): void
    {
        $isNewRecord = ! $score->exists;

        // Calculate percentage if points are provided but percentage is not
        if (isset($data['points_earned']) && ! isset($data['percentage_score']) && $this->assessmentComponentDetail->max_points) {
            $data['percentage_score'] = ($data['points_earned'] / $this->assessmentComponentDetail->max_points) * 100;
        }

        // Remove student_id from data as we already have the correct student
        $scoreData = array_filter($data, function ($value, $key) {
            return $value !== null && $value !== '' && $key !== 'student_id';
        }, ARRAY_FILTER_USE_BOTH);

        // Update score fields
        $score->fill($scoreData);

        // Set metadata
        $score->graded_by_lecture_id = $this->lecturerId;
        $score->graded_at = now();
        $score->last_modified_by_lecture_id = $this->lecturerId;
        $score->last_modified_at = now();

        $score->save();

        if ($isNewRecord) {
            $this->importResults['successful_creates']++;
        } else {
            $this->importResults['successful_updates']++;
        }
    }

    /**
     * Add error to results
     */
    protected function addError(int $row, string $message, array $data): void
    {
        $this->importResults['errors'][] = [
            'row' => $row,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Add warning to results
     */
    protected function addWarning(int $row, string $message, array $data): void
    {
        $this->importResults['warnings'][] = [
            'row' => $row,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Validate all rows without processing
     */
    protected function validateRows(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $this->validateRowData($row, $index + 2);
        }
    }

    /**
     * Get import results
     */
    public function getResults(): array
    {
        return $this->importResults;
    }

    /**
     * Validation rules for the import
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required',
        ];
    }

    /**
     * Batch size for processing
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Chunk size for reading
     */
    public function chunkSize(): int
    {
        return 100;
    }
}
