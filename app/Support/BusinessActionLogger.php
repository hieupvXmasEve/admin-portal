<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

/**
 * BusinessActionLogger - Centralized logging for complex business operations
 * 
 * This class provides a consistent interface for logging complex business actions
 * that involve multiple models, calculations, or operations that go beyond
 * simple CRUD operations on individual models.
 */
class BusinessActionLogger
{
    protected ?Model $subject = null;
    protected ?Model $causer = null;
    protected string $actionType;
    protected string $description;
    protected array $properties = [];
    protected ?string $logName = null;
    protected ?string $event = null;
    protected array $affectedModels = [];
    protected ?string $batchUuid = null;

    /**
     * Create a new business action logger instance
     */
    public function __construct(string $actionType, string $description)
    {
        $this->actionType = $actionType;
        $this->description = $description;
        $this->causer = auth()->user();
    }

    /**
     * Create a new logger instance for a specific business action
     */
    public static function for(string $actionType, string $description): self
    {
        return new static($actionType, $description);
    }

    /**
     * Set the primary subject of the action
     */
    public function on(Model $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set who caused the action
     */
    public function by(?Model $causer): self
    {
        $this->causer = $causer;
        return $this;
    }

    /**
     * Add properties to the log
     */
    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);
        return $this;
    }

    /**
     * Set the log name (defaults to campus-aware naming)
     */
    public function logName(string $logName): self
    {
        $this->logName = $logName;
        return $this;
    }

    /**
     * Set the event name
     */
    public function event(string $event): self
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Add models that were affected by this action
     */
    public function affecting(Model|array $models): self
    {
        $models = is_array($models) ? $models : [$models];
        $this->affectedModels = array_merge($this->affectedModels, $models);
        return $this;
    }

    /**
     * Set batch UUID for grouping related actions
     */
    public function batch(string $batchUuid): self
    {
        $this->batchUuid = $batchUuid;
        return $this;
    }

    /**
     * Log the business action
     */
    public function log(): Activity
    {
        $logName = $this->getLogName();
        $properties = $this->buildProperties();

        $activity = activity($logName);

        if ($this->subject) {
            $activity->performedOn($this->subject);
        }

        if ($this->causer) {
            $activity->causedBy($this->causer);
        }

        if ($this->event) {
            $activity->event($this->event);
        }

        if ($this->batchUuid) {
            $activity->tap(function (Activity $activity) {
                $activity->batch_uuid = $this->batchUuid;
            });
        }

        return $activity->withProperties($properties)->log($this->description);
    }

    /**
     * Execute a business operation within a logged context
     */
    public function execute(callable $operation, ?callable $onSuccess = null, ?callable $onFailure = null): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            DB::beginTransaction();

            $result = $operation();

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $this->withProperties([
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'memory_usage_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 2),
                'status' => 'success',
                'result_summary' => $this->summarizeResult($result),
            ]);

            $this->log();

            if ($onSuccess) {
                $onSuccess($result);
            }

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();

            $endTime = microtime(true);
            $this->withProperties([
                'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            $this->log();

            if ($onFailure) {
                $onFailure($e);
            }

            throw $e;
        }
    }

    /**
     * Build comprehensive properties for logging
     */
    protected function buildProperties(): array
    {
        $baseProperties = [
            'action_type' => $this->actionType,
            'timestamp' => now()->toISOString(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Add campus context if available
        $campusProperties = CampusLogContext::getCampusContext();

        // Add subject information
        $subjectProperties = $this->subject ? [
            'subject_type' => get_class($this->subject),
            'subject_id' => $this->subject->getKey(),
            'subject_identifier' => $this->getModelIdentifier($this->subject),
        ] : [];

        // Add causer information
        $causerProperties = $this->causer ? [
            'causer_type' => get_class($this->causer),
            'causer_id' => $this->causer->getKey(),
            'causer_identifier' => $this->getModelIdentifier($this->causer),
        ] : [];

        // Add affected models information
        $affectedModelsProperties = [];
        if (!empty($this->affectedModels)) {
            $affectedModelsProperties['affected_models'] = collect($this->affectedModels)
                ->map(fn($model) => [
                    'type' => get_class($model),
                    'id' => $model->getKey(),
                    'identifier' => $this->getModelIdentifier($model),
                ])
                ->toArray();
            $affectedModelsProperties['affected_models_count'] = count($this->affectedModels);
        }

        return array_merge(
            $baseProperties,
            $campusProperties,
            $subjectProperties,
            $causerProperties,
            $affectedModelsProperties,
            $this->properties
        );
    }

    /**
     * Get the log name for this action
     */
    protected function getLogName(): string
    {
        if ($this->logName) {
            return $this->logName;
        }

        $campusId = $this->subject?->campus_id ?? CampusLogContext::getCurrentCampusId();
        return CampusLogContext::getLogName("business_{$this->actionType}", $campusId);
    }

    /**
     * Get a meaningful identifier for a model
     */
    protected function getModelIdentifier(Model $model): string
    {
        // Try common identifier fields
        $identifierFields = ['name', 'title', 'full_name', 'email', 'student_code', 'code'];
        
        foreach ($identifierFields as $field) {
            if (isset($model->$field) && !empty($model->$field)) {
                return (string) $model->$field;
            }
        }

        return "ID {$model->getKey()}";
    }

    /**
     * Summarize the result for logging
     */
    protected function summarizeResult($result): array
    {
        if ($result instanceof Collection) {
            return [
                'type' => 'collection',
                'count' => $result->count(),
                'is_empty' => $result->isEmpty(),
            ];
        }

        if ($result instanceof Model) {
            return [
                'type' => 'model',
                'class' => get_class($result),
                'id' => $result->getKey(),
                'identifier' => $this->getModelIdentifier($result),
            ];
        }

        if (is_array($result)) {
            return [
                'type' => 'array',
                'count' => count($result),
                'keys' => array_keys($result),
            ];
        }

        if (is_bool($result)) {
            return [
                'type' => 'boolean',
                'value' => $result,
            ];
        }

        if (is_numeric($result)) {
            return [
                'type' => 'numeric',
                'value' => $result,
            ];
        }

        if (is_string($result)) {
            return [
                'type' => 'string',
                'length' => strlen($result),
                'preview' => substr($result, 0, 100),
            ];
        }

        return [
            'type' => gettype($result),
            'summary' => 'Complex result type',
        ];
    }

    // === Predefined Business Action Types ===

    /**
     * Log a graduation evaluation
     */
    public static function graduationEvaluation(Model $student): self
    {
        return self::for('graduation_evaluation', 'Performed graduation requirements evaluation')
            ->on($student)
            ->event('graduation_evaluation');
    }

    /**
     * Log a course withdrawal
     */
    public static function courseWithdrawal(Model $registration, string $reason = null): self
    {
        $description = 'Processed course withdrawal';
        if ($reason) {
            $description .= " (Reason: {$reason})";
        }

        return self::for('course_withdrawal', $description)
            ->on($registration)
            ->event('course_withdrawal')
            ->withProperties($reason ? ['withdrawal_reason' => $reason] : []);
    }

    /**
     * Log grade entry/update operations
     */
    public static function gradeEntry(Model $assessment, string $operation = 'update'): self
    {
        return self::for('grade_entry', "Grade {$operation} processed")
            ->on($assessment)
            ->event("grade_{$operation}");
    }

    /**
     * Log bulk grade operations
     */
    public static function bulkGradeEntry(array $assessments, string $operation = 'update'): self
    {
        $count = count($assessments);
        return self::for('bulk_grade_entry', "Bulk grade {$operation} processed ({$count} assessments)")
            ->affecting($assessments)
            ->event("bulk_grade_{$operation}")
            ->withProperties(['assessment_count' => $count]);
    }

    /**
     * Log GPA calculation
     */
    public static function gpaCalculation(Model $student, string $type = 'semester'): self
    {
        return self::for('gpa_calculation', "GPA calculation performed ({$type})")
            ->on($student)
            ->event('gpa_calculation')
            ->withProperties(['calculation_type' => $type]);
    }

    /**
     * Log academic standing evaluation
     */
    public static function academicStanding(Model $student, string $oldStatus, string $newStatus): self
    {
        return self::for('academic_standing', "Academic standing updated: {$oldStatus} → {$newStatus}")
            ->on($student)
            ->event('academic_standing_change')
            ->withProperties([
                'old_academic_status' => $oldStatus,
                'new_academic_status' => $newStatus,
            ]);
    }

    /**
     * Log enrollment operations
     */
    public static function enrollment(string $operation, Model $student, ?Model $semester = null): self
    {
        $description = "Student enrollment {$operation}";
        
        $logger = self::for('enrollment', $description)
            ->on($student)
            ->event("enrollment_{$operation}");

        if ($semester) {
            $logger->affecting($semester)
                ->withProperties(['semester_id' => $semester->getKey()]);
        }

        return $logger;
    }

    /**
     * Log program change
     */
    public static function programChange(Model $student, Model $oldProgram, Model $newProgram): self
    {
        return self::for('program_change', 'Student program change processed')
            ->on($student)
            ->affecting([$oldProgram, $newProgram])
            ->event('program_change')
            ->withProperties([
                'old_program_id' => $oldProgram->getKey(),
                'new_program_id' => $newProgram->getKey(),
                'old_program_name' => $oldProgram->name ?? 'Unknown',
                'new_program_name' => $newProgram->name ?? 'Unknown',
            ]);
    }

    /**
     * Log academic hold operations
     */
    public static function academicHold(string $operation, Model $hold, ?string $reason = null): self
    {
        $description = "Academic hold {$operation}";
        if ($reason) {
            $description .= " (Reason: {$reason})";
        }

        return self::for('academic_hold', $description)
            ->on($hold)
            ->event("hold_{$operation}")
            ->withProperties($reason ? ['operation_reason' => $reason] : []);
    }

    /**
     * Log financial operations
     */
    public static function financial(string $operation, Model $subject, float $amount, string $type = 'payment'): self
    {
        return self::for('financial_operation', "Financial {$operation}: {$type}")
            ->on($subject)
            ->event("financial_{$operation}")
            ->withProperties([
                'amount' => $amount,
                'financial_type' => $type,
                'currency' => config('app.currency', 'USD'),
            ]);
    }

    /**
     * Log data import/export operations
     */
    public static function dataOperation(string $operation, string $type, int $recordCount, array $summary = []): self
    {
        return self::for("data_{$operation}", "Data {$operation}: {$type}")
            ->event("data_{$operation}")
            ->withProperties(array_merge([
                'data_type' => $type,
                'record_count' => $recordCount,
                'operation_summary' => $summary,
            ], $summary));
    }

    /**
     * Log system maintenance operations
     */
    public static function systemMaintenance(string $operation, array $details = []): self
    {
        return self::for('system_maintenance', "System maintenance: {$operation}")
            ->event('system_maintenance')
            ->withProperties(array_merge([
                'maintenance_type' => $operation,
            ], $details));
    }

    // === Query helpers for retrieving logged actions ===

    /**
     * Get activities for a specific business action type
     */
    public static function getActivitiesForAction(string $actionType, ?Model $subject = null): Collection
    {
        $query = ActivityModel::whereJsonContains('properties->action_type', $actionType);

        if ($subject) {
            $query->where('subject_type', get_class($subject))
                ->where('subject_id', $subject->getKey());
        }

        return $query->latest()->get();
    }

    /**
     * Get recent business activities
     */
    public static function getRecentActivities(int $limit = 50, ?string $actionType = null): Collection
    {
        $query = ActivityModel::whereJsonContains('properties->action_type', $actionType ?? '%')
            ->orWhere('log_name', 'like', 'business_%');

        if ($actionType) {
            $query->whereJsonContains('properties->action_type', $actionType);
        }

        return $query->latest()->limit($limit)->get();
    }

    /**
     * Get activities by batch UUID
     */
    public static function getActivitiesByBatch(string $batchUuid): Collection
    {
        return ActivityModel::where('batch_uuid', $batchUuid)->latest()->get();
    }
}
