<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\GpaCalculation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\Unit;
use App\Models\User;
use App\Modules\AI\Agents\LiveStaffCopilotFinalAnswerAgent;
use App\Modules\AI\Agents\LiveStaffCopilotPlannerAgent;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Modules\AI\Support\Tools\ToolDispatcher;
use App\Modules\AI\Support\Tools\ToolRegistry;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->fullAccessUser = User::factory()->create();
    $this->academicOnlyUser = User::factory()->create();
    $this->aiOnlyUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HN']);
    $this->program = Program::factory()->create([
        'code' => 'BIT',
        'name' => 'Business Information Technology',
    ]);
    $this->specialization = Specialization::factory()->create([
        'program_id' => $this->program->id,
        'code' => 'SE',
        'name' => 'Software Engineering',
    ]);
    $this->semester = Semester::factory()->active()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);
    $this->curriculumVersion = CurriculumVersion::factory()->create([
        'program_id' => $this->program->id,
        'specialization_id' => $this->specialization->id,
        'version_code' => 'BIT-2024',
        'semester_id' => $this->semester->id,
    ]);
    $this->unit = Unit::factory()->create([
        'code' => 'ICT101',
        'name' => 'Information Systems Foundations',
        'credit_points' => 3,
    ]);
    $this->student = Student::factory()->create([
        'student_id' => 'AUS24001',
        'full_name' => 'Nguyen Van A',
        'email' => 'secret.student@example.test',
        'phone' => '0900000001',
        'national_id' => '0123456789',
        'address' => 'Hidden address',
        'current_address_line' => 'Hidden current address',
        'admission_notes' => 'Hidden admissions note',
        'emergency_contact_name' => 'Hidden emergency contact',
        'emergency_contact_phone' => '0900999999',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'specialization_id' => $this->specialization->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 2024,
        'admission_date' => '2024-01-10',
        'expected_graduation_date' => '2027-12-31',
        'status' => 'active',
        'academic_status' => 'active',
        'gc_starting_level' => 1,
        'gc_current_level' => 2,
        'gc_total_levels' => 4,
    ]);
    $this->otherCampusStudent = Student::factory()->create([
        'student_id' => 'AUS99999',
        'full_name' => 'Remote Campus Match',
        'campus_id' => $this->otherCampus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 2024,
    ]);
    $this->courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'section_code' => 'BIT-A1',
        'course_status' => 'in_progress',
    ]);

    CourseRegistration::query()->create([
        'student_id' => $this->student->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now()->subDays(10),
        'registration_method' => 'advisor',
        'credit_points' => 3,
        'credit_hours' => 45,
        'final_grade' => 'A',
        'grade_points' => 4,
        'attempt_number' => 1,
        'is_retake' => false,
        'notes' => 'Hidden registration note',
    ]);

    AcademicRecord::query()->create([
        'student_id' => $this->student->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'program_id' => $this->program->id,
        'campus_id' => $this->campus->id,
        'final_percentage' => 88.5,
        'final_letter_grade' => 'A',
        'grade_points' => 4,
        'quality_points' => 12,
        'credit_hours' => 45,
        'credit_hours_earned' => 45,
        'credit_points' => 3,
        'credit_points_earned' => 3,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'enrollment_date' => now()->subMonths(4)->toDateString(),
        'completion_date' => now()->subMonth()->toDateString(),
        'attendance_percentage' => 90,
        'total_absences' => 1,
        'total_present' => 9,
        'total_late' => 1,
        'total_class_sessions' => 11,
        'meets_attendance_requirement' => true,
        'attempt_number' => 1,
        'instructor_comments' => 'Instructor says private',
        'administrative_notes' => 'Administrative raw note',
    ]);

    GpaCalculation::query()->create([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'program_id' => $this->program->id,
        'semester_gpa' => 3.25,
        'cumulative_gpa' => 3.5,
        'semester_quality_points' => 9.75,
        'cumulative_quality_points' => 42,
        'semester_credit_points' => 3,
        'cumulative_credit_points' => 12,
        'semester_credit_points_earned' => 3,
        'cumulative_credit_points_earned' => 12,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    $presentSession = ClassSession::factory()->create([
        'course_offering_id' => $this->courseOffering->id,
        'room_id' => null,
        'sequence_number' => 1,
        'session_date' => now()->subDays(2)->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'status' => 'completed',
    ]);
    $absentSession = ClassSession::factory()->create([
        'course_offering_id' => $this->courseOffering->id,
        'room_id' => null,
        'sequence_number' => 2,
        'session_date' => now()->subDay()->toDateString(),
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'status' => 'completed',
    ]);

    Attendance::query()->create([
        'class_session_id' => $presentSession->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'recording_method' => 'manual',
        'notes' => 'Raw attendance note',
    ]);
    Attendance::query()->create([
        'class_session_id' => $absentSession->id,
        'student_id' => $this->student->id,
        'status' => 'absent',
        'recording_method' => 'manual',
        'excuse_reason' => 'Raw excuse detail',
    ]);

    StudentActionLog::query()->create([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Hidden lifecycle reason',
        'notes' => 'Hidden lifecycle notes',
        'signed_at' => now()->subDays(5)->toDateString(),
        'decision_number' => 'QD-2026-001',
        'changed_by_user_id' => $this->fullAccessUser->id,
        'from_semester_id' => $this->semester->id,
        'return_semester_id' => $this->semester->id,
        'previous_status' => 'active',
        'new_status' => 'deferred',
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ]);

    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $this->student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'ai_student_profile',
        'source_ref' => "student:{$this->student->id}:tuition-term",
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 1200,
        'currency' => 'VND',
        'pricing_rule_version' => 'ai-student-profile:test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1200,
        'description' => 'Tuition term charge',
        'effective_at' => now(),
        'created_by_user_id' => $this->fullAccessUser->id,
    ]);
    FinanceChargeInstallment::factory()->paid()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 600,
    ]);
    FinanceChargeInstallment::factory()->pushFailed('Push failed detail')->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 2,
        'amount' => 600,
    ]);
    DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'campus_code' => 'HCM',
        'student_code' => 'AUS24001',
        'fee_type' => 'TUITION',
        'item_id' => 'DNG-AUS24001-001',
        'amount' => 1200,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'push_payload' => ['secret' => 'Gateway push payload'],
        'push_response' => ['secret' => 'Gateway response payload'],
        'qr_payload' => ['secret' => 'Gateway QR payload'],
        'last_callback_payload' => ['secret' => 'Gateway callback payload'],
        'error_message' => 'Gateway error detail',
    ]);

    session([
        '_token' => 'ai-student-profile-test-token',
        'current_campus_id' => $this->campus->id,
    ]);

    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(function (int $userId, ?int $campusId = null): array {
            if ($userId === $this->fullAccessUser->id && $campusId === $this->campus->id) {
                return [
                    'view_ai_metrics',
                    'view_student',
                    'view_student_summary',
                    'view_finance_student_overview',
                    'view_student_action',
                    'view_program',
                    'view_semester',
                    'view_course_offering',
                ];
            }

            if ($userId === $this->academicOnlyUser->id && $campusId === $this->campus->id) {
                return [
                    'view_ai_metrics',
                    'view_student',
                    'view_student_summary',
                    'view_student_action',
                ];
            }

            if ($userId === $this->aiOnlyUser->id && $campusId === $this->campus->id) {
                return ['view_ai_metrics'];
            }

            return [];
        });

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->entityRefFor = function (?Student $student = null, ?Campus $campus = null): string {
        $student ??= $this->student;
        $campus ??= $this->campus;

        if ($student->id === $this->student->id && $campus->id === $this->campus->id) {
            $search = app(ToolDispatcher::class)->dispatch(
                toolName: 'search_entities',
                arguments: [
                    'query' => (string) $student->student_id,
                    'entity_types' => ['student'],
                    'options' => ['limit' => 5],
                ],
                actor: $this->fullAccessUser,
                campus: $this->campus,
            );

            return (string) $search->toArray()['results'][0]['entity_ref'];
        }

        return Crypt::encryptString(json_encode([
            'entity_type' => 'student',
            'source_id' => $student->id,
            'campus_id' => $campus->id,
            'catalog_version' => EntityCatalog::VERSION,
            'scope_rule' => 'current_campus_only',
            'issued_at' => now()->toISOString(),
        ], JSON_THROW_ON_ERROR));
    };
});

it('registers the student profile section catalog and exposes get entity profile capabilities', function () {
    $catalog = app(StudentProfileSectionCatalog::class);
    $registry = app(ToolRegistry::class);

    expect($catalog->version())->toBe('student-profile-sections:v1')
        ->and($catalog->toolSchemaVersion())->toBe('get_entity_profile:v1')
        ->and($catalog->sectionKeys())->toBe([
            'identity',
            'academic_summary',
            'enrollments',
            'attendance_summary',
            'finance_summary',
            'lifecycle_actions',
        ])
        ->and($registry->toolNames())->toBe(['query_metrics', 'search_entities', 'get_entity_profile'])
        ->and($registry->has('get_entity_profile'))->toBeTrue()
        ->and($registry->definition('get_entity_profile')?->toArray())->toMatchArray([
            'name' => 'get_entity_profile',
            'schema_version' => 'get_entity_profile:v1',
            'permission' => 'view_ai_metrics',
        ]);

    $this->actingAs($this->fullAccessUser)
        ->get(route('ai.copilot.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('capabilities.profile_catalog_version', 'student-profile-sections:v1')
            ->where('capabilities.tool_schema_versions.get_entity_profile', 'get_entity_profile:v1'));
});

it('returns allowlisted student profile sections from an opaque entity reference with audit evidence', function () {
    $audit = app(AiAuditRecorder::class);
    $conversation = $audit->startConversation($this->fullAccessUser, $this->campus);
    $trace = $audit->startTrace($conversation, null, [
        'catalog_version' => 'student-profile-sections:v1',
        'tool_schema_version' => 'get_entity_profile:v1',
    ]);

    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => ($this->entityRefFor)(),
            'sections' => [
                'identity',
                'academic_summary',
                'enrollments',
                'attendance_summary',
                'finance_summary',
                'lifecycle_actions',
            ],
            'options' => ['limit' => 5],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
        trace: $trace,
    );

    $payload = $result->toArray();
    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($payload)->toMatchArray([
        'allowed' => true,
        'tool' => 'get_entity_profile',
        'tool_schema_version' => 'get_entity_profile:v1',
        'catalog_version' => 'student-profile-sections:v1',
        'entity_catalog_version' => 'entity-catalog:v1',
        'entity_type' => 'student',
        'requested_sections' => [
            'identity',
            'academic_summary',
            'enrollments',
            'attendance_summary',
            'finance_summary',
            'lifecycle_actions',
        ],
        'returned_sections' => [
            'identity',
            'academic_summary',
            'enrollments',
            'attendance_summary',
            'finance_summary',
            'lifecycle_actions',
        ],
        'hidden_sections' => [],
        'safe_error_code' => null,
        'status' => 'completed',
    ])
        ->and($payload['sections']['identity'])->toMatchArray([
            'student_code' => 'AUS24001',
            'display_name' => 'NGUYEN VAN A',
            'campus_code' => 'HCM',
            'program_code' => 'BIT',
            'specialization_code' => 'SE',
            'curriculum_version_code' => 'BIT-2024',
            'status' => 'active',
            'academic_status' => 'active',
        ])
        ->and($payload['sections']['academic_summary'])->toMatchArray([
            'current_semester_code' => '2026-T1',
            'semester_gpa' => 3.25,
            'cumulative_gpa' => 3.5,
            'academic_standing' => 'normal',
            'completed_courses' => 1,
            'active_registrations' => 1,
            'earned_credit_points' => 3.0,
        ])
        ->and($payload['sections']['enrollments']['items'][0])->toMatchArray([
            'semester_code' => '2026-T1',
            'unit_code' => 'ICT101',
            'section_code' => 'BIT-A1',
            'registration_status' => 'confirmed',
            'attempt_number' => 1,
            'is_retake' => false,
        ])
        ->and($payload['sections']['attendance_summary']['summary'])->toMatchArray([
            'total_sessions' => 2,
            'total_attended' => 1,
            'total_absent' => 1,
            'overall_percentage' => 50.0,
        ])
        ->and($payload['sections']['attendance_summary']['courses'][0])->toMatchArray([
            'unit_code' => 'ICT101',
            'section_code' => 'BIT-A1',
            'total_sessions' => 2,
            'attended_count' => 1,
            'absent_count' => 1,
        ])
        ->and($payload['sections']['finance_summary']['balance'])->toMatchArray([
            'status' => 'outstanding',
            'balance' => 1200.0,
        ])
        ->and($payload['sections']['finance_summary']['dng'])->toMatchArray([
            'has_active' => true,
            'request' => [
                'status' => DngPaymentRequest::STATUS_PENDING,
                'item_id' => 'DNG-AUS24001-001',
                'amount' => 1200.0,
            ],
        ])
        ->and($payload['sections']['finance_summary']['installments'])->toMatchArray([
            'total' => 2,
            'paid' => 1,
        ])
        ->and($payload['sections']['lifecycle_actions']['items'][0])->toMatchArray([
            'action_type' => StudentActionType::ACADEMIC_DEFER->value,
            'from_semester_code' => '2026-T1',
            'return_semester_code' => '2026-T1',
            'previous_status' => 'active',
            'new_status' => 'deferred',
            'decision_number' => 'QD-2026-001',
        ])
        ->and($payload['source_references'])->sequence(
            fn ($source) => $source->source_report->toBe('academic.student-profile.identity'),
            fn ($source) => $source->source_report->toBe('academic.student-profile.summary'),
            fn ($source) => $source->source_report->toBe('academic.student-profile.enrollments'),
            fn ($source) => $source->source_report->toBe('academic.student-profile.attendance-summary'),
            fn ($source) => $source->source_report->toBe('finance.student-profile.summary'),
            fn ($source) => $source->source_report->toBe('academic.student-profile.lifecycle-actions'),
        )
        ->and($encodedPayload)->not->toContain('secret.student@example.test')
        ->and($encodedPayload)->not->toContain('0900000001')
        ->and($encodedPayload)->not->toContain('0123456789')
        ->and($encodedPayload)->not->toContain('Hidden address')
        ->and($encodedPayload)->not->toContain('Hidden current address')
        ->and($encodedPayload)->not->toContain('Hidden admissions note')
        ->and($encodedPayload)->not->toContain('Hidden emergency contact')
        ->and($encodedPayload)->not->toContain('Hidden registration note')
        ->and($encodedPayload)->not->toContain('Instructor says private')
        ->and($encodedPayload)->not->toContain('Administrative raw note')
        ->and($encodedPayload)->not->toContain('Raw attendance note')
        ->and($encodedPayload)->not->toContain('Raw excuse detail')
        ->and($encodedPayload)->not->toContain('Hidden lifecycle reason')
        ->and($encodedPayload)->not->toContain('Hidden lifecycle notes')
        ->and($encodedPayload)->not->toContain('Gateway push payload')
        ->and($encodedPayload)->not->toContain('Gateway error detail')
        ->and($encodedPayload)->not->toContain('Push failed detail');

    $toolCall = AiToolCall::query()->where('tool_name', 'get_entity_profile')->firstOrFail();

    expect($toolCall->status)->toBe('completed')
        ->and($toolCall->permission_result)->toBe('allowed')
        ->and($toolCall->record_count)->toBe(6)
        ->and($toolCall->safe_error_code)->toBeNull()
        ->and($toolCall->source_references[0]['source_report'])->toBe('academic.student-profile.identity')
        ->and($toolCall->redacted_result_summary['sections']['identity']['student_code'])->toBe('AUS24001');
});

it('reports hidden profile sections when the actor lacks section permission', function () {
    $result = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => ($this->entityRefFor)(),
            'sections' => ['identity', 'academic_summary', 'finance_summary'],
        ],
        actor: $this->academicOnlyUser,
        campus: $this->campus,
    );

    $payload = $result->toArray();

    expect($payload)->toMatchArray([
        'allowed' => true,
        'status' => 'partial',
        'requested_sections' => ['identity', 'academic_summary', 'finance_summary'],
        'returned_sections' => ['identity', 'academic_summary'],
        'hidden_sections' => ['student.finance_summary'],
        'safe_error_code' => null,
    ])
        ->and(array_key_exists('finance_summary', $payload['sections']))->toBeFalse();

    $denied = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => ($this->entityRefFor)(),
            'sections' => ['identity'],
        ],
        actor: $this->aiOnlyUser,
        campus: $this->campus,
    );

    expect($denied->toArray())->toMatchArray([
        'allowed' => false,
        'status' => 'denied',
        'sections' => [],
        'returned_sections' => [],
        'hidden_sections' => ['student.identity'],
        'safe_error_code' => 'forbidden_by_permission',
    ]);
});

it('rejects raw identifiers, unsafe include requests, invalid references, and cross-campus references', function () {
    $validRef = ($this->entityRefFor)();

    $rawIdentifier = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => $validRef,
            'student_id' => $this->student->id,
            'sections' => ['identity'],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    $unsafeInclude = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => $validRef,
            'sections' => ['identity'],
            'options' => ['include_hidden_fields' => true],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    $invalidRef = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => 'not-an-opaque-ref',
            'sections' => ['identity'],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    $crossCampus = app(ToolDispatcher::class)->dispatch(
        toolName: 'get_entity_profile',
        arguments: [
            'entity_type' => 'student',
            'entity_ref' => ($this->entityRefFor)($this->otherCampusStudent, $this->otherCampus),
            'sections' => ['identity'],
        ],
        actor: $this->fullAccessUser,
        campus: $this->campus,
    );

    expect($rawIdentifier->toArray())->toMatchArray([
        'allowed' => false,
        'status' => 'failed',
        'safe_error_code' => 'invalid_profile_request_schema',
        'sections' => [],
    ])
        ->and($unsafeInclude->toArray())->toMatchArray([
            'allowed' => false,
            'status' => 'failed',
            'safe_error_code' => 'invalid_profile_request_schema',
            'sections' => [],
        ])
        ->and($invalidRef->toArray())->toMatchArray([
            'allowed' => false,
            'status' => 'failed',
            'safe_error_code' => 'invalid_entity_reference',
            'sections' => [],
        ])
        ->and($crossCampus->toArray())->toMatchArray([
            'allowed' => false,
            'status' => 'denied',
            'safe_error_code' => 'forbidden_by_campus_scope',
            'sections' => [],
            'hidden_sections' => ['student.profile'],
        ]);
});

it('allows the live staff copilot planner to execute get entity profile without leaking protected fields', function () {
    $entityRef = ($this->entityRefFor)();

    AiProviderSetting::query()->create([
        'user_id' => $this->fullAccessUser->id,
        'provider' => 'openai',
        'default_model' => 'gpt-4o-mini',
        'encrypted_api_key' => 'fake-profile-key',
        'enabled' => true,
        'tested_at' => now(),
        'last_test_status' => 'success',
        'created_by_user_id' => $this->fullAccessUser->id,
        'updated_by_user_id' => $this->fullAccessUser->id,
    ]);

    LiveStaffCopilotPlannerAgent::fake([
        [
            'action' => 'tool_calls',
            'tool_calls' => [
                [
                    'tool_name' => 'get_entity_profile',
                    'arguments' => [
                        'entity_type' => 'student',
                        'entity_ref' => $entityRef,
                        'sections' => ['identity', 'academic_summary', 'finance_summary'],
                    ],
                    'reason' => 'The staff question asks for a student profile from an entity reference.',
                ],
            ],
            'answer_intent' => 'student_profile_sections',
            'question' => null,
            'safe_error_code' => null,
            'reason' => null,
        ],
    ])->preventStrayPrompts();

    LiveStaffCopilotFinalAnswerAgent::fake([
        [
            'status' => 'completed',
            'answer' => 'AUS24001 profile sections were retrieved from the allowlisted student profile reports.',
            'referenced_tool_call_ids' => ['tool-call-1'],
            'source_references' => [
                [
                    'source_report' => 'academic.student-profile.identity',
                    'source_reference_policy' => 'profile_section_summary',
                ],
                [
                    'source_report' => 'finance.student-profile.summary',
                    'source_reference_policy' => 'profile_section_summary',
                ],
            ],
            'confidence' => [
                'level' => 'high',
                'basis' => 'tool_result_exact_match',
            ],
            'limitations' => [],
            'clarification_question' => null,
            'safe_error_code' => null,
        ],
    ])->preventStrayPrompts();

    $this->actingAs($this->fullAccessUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-student-profile-test-token')
        ->from(route('ai.copilot.index'))
        ->post(route('ai.copilot.messages.store'), [
            'question' => 'Show the safe profile for the selected student entity reference.',
        ])
        ->assertRedirect(route('ai.copilot.index'))
        ->assertInertiaFlash('success', 'AI copilot run queued.');

    $run = AiChatRun::query()->firstOrFail();

    $response = $this->actingAs($this->fullAccessUser)
        ->get(route('ai.copilot.runs.events', $run));

    $response->assertStreamed();

    expect($response->streamedContent())->toContain('event: run.completed');

    LiveStaffCopilotPlannerAgent::assertPrompted(fn ($prompt): bool => str_contains((string) $prompt->agent->instructions(), 'get_entity_profile')
        && str_contains((string) $prompt->agent->instructions(), 'student-profile-sections:v1')
        && ! str_contains($prompt->prompt, 'fake-profile-key'));

    LiveStaffCopilotFinalAnswerAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'academic.student-profile.identity')
        && str_contains($prompt->prompt, 'finance.student-profile.summary')
        && str_contains($prompt->prompt, 'student_code')
        && ! str_contains($prompt->prompt, 'secret.student@example.test')
        && ! str_contains($prompt->prompt, 'Gateway push payload'));

    $toolCall = AiToolCall::query()->where('tool_name', 'get_entity_profile')->firstOrFail();

    expect($toolCall->status)->toBe('completed')
        ->and($toolCall->permission_result)->toBe('allowed')
        ->and($toolCall->redacted_result_summary['returned_sections'])->toBe(['identity', 'academic_summary', 'finance_summary'])
        ->and($toolCall->redacted_result_summary['sections']['identity']['student_code'])->toBe('AUS24001');
});
