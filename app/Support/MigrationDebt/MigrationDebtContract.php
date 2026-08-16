<?php

declare(strict_types=1);

namespace App\Support\MigrationDebt;

final class MigrationDebtContract
{
    /** @var list<string> */
    public const RULES = [
        'frozen_services',
        'frozen_controllers',
        'frozen_routes',
        'shared_model_imports',
        'cross_context_concrete_imports',
        'direct_json_responses',
        'inline_request_validation',
        'missing_strict_types',
        'missing_route_strict_types',
        'legacy_filter_stacks',
        'literal_frontend_urls',
        'legacy_page_directories',
        'removed_inertia_apis',
        'migration_commands',
    ];

    /** @var list<string> */
    public const SOURCE_ROOTS = [
        'app',
        'routes',
        'resources/js',
        'config/mcp.php',
    ];

    /** @var list<string> */
    public const RUNTIME_SURFACES = [
        'http',
        'api',
        'queue',
        'scheduler',
        'commands',
        'imports_exports',
        'reports',
        'webhooks',
        'broadcasts',
        'mcp',
        'portal',
        'frontend',
        'other',
    ];

    /** @var list<string> */
    public const REQUIRED_RUNTIME_SURFACES = [
        'http',
        'api',
        'queue',
        'scheduler',
        'commands',
        'imports_exports',
        'reports',
        'webhooks',
        'broadcasts',
        'mcp',
        'portal',
    ];

    /** @var list<string> */
    public const PATH_SNAPSHOT_RULES = [
        'frozen_services',
        'frozen_controllers',
        'frozen_routes',
    ];

    /** @var array<string, int> */
    public const BASELINE_CEILINGS = [
        'frozen_services' => 55,
        'frozen_controllers' => 30,
        'frozen_routes' => 12,
        'shared_model_imports' => 376,
        'cross_context_concrete_imports' => 0,
        'direct_json_responses' => 20,
        'inline_request_validation' => 38,
        'missing_strict_types' => 133,
        'missing_route_strict_types' => 11,
        'legacy_filter_stacks' => 24,
        'literal_frontend_urls' => 40,
        'legacy_page_directories' => 0,
        'removed_inertia_apis' => 0,
        'migration_commands' => 7,
    ];

    /** @var array<string, array<string, list<string>>|list<string>> */
    public const OWNED_SHARED_MODELS = [
        'Identity' => [
            'User',
            'Role',
            'CampusUserRole',
            'ParentProfile',
        ],
        'Institution' => [
            'Campus',
            'Department',
            'DepartmentMembership',
        ],
        'StudentRegistry' => [
            'Student',
        ],
        'Academic' => [
            'Catalog' => [
                'Program',
                'Semester',
                'Unit',
                'EquivalentUnit',
                'UnitPrerequisiteCondition',
                'UnitPrerequisiteGroup',
                'CurriculumUnit',
                'CurriculumModule',
                'CurriculumVersion',
                'Module',
                'Specialization',
                'SyllabusTemplate',
            ],
            'Delivery' => [
                'Attendance',
                'AssessmentComponent',
                'AssessmentComponentDetail',
                'AcademicRecord',
                'AssessmentComponentDetailScore',
                'CanvasCourseMapping',
                'CanvasIntegration',
                'ClassSession',
                'CourseOffering',
                'CourseRegistration',
                'CourseRetakeRegistration',
                'ExamResitAttempt',
                'ExamResitSession',
                'ExamRoomSlot',
                'ExamRoomSlotInvigilator',
            ],
            'FacultyWorkforce' => [
                'Lecture',
            ],
            'Progression' => [
                'AcademicHold',
                'AcademicProgressionEvent',
                'AcademicWarningSetting',
                'StudentWarningLog',
                'GpaCalculation',
                'Enrollment',
                'IeltsCertificate',
                'StudentActionLog',
                'StudentDecision',
            ],
        ],
        'Admissions' => [
            'ApplicationDocument',
            'ApplicationDocumentType',
            'ApplicationGuardian',
            'StudentApplication',
        ],
        'Facilities' => [
            'Building',
            'Room',
            'RoomBooking',
            'RoomBookingAction',
        ],
        'Engagement' => [
            'Answer',
            'AnswerOption',
            'Club',
            'ClubMember',
            'ClubMemberRoleHistory',
            'Event',
            'EventParticipant',
            'Form',
            'FormResponse',
            'FormResultVisibility',
            'FormSection',
            'FormSurvey',
            'FormTarget',
            'FormVersion',
            'QueryAssignment',
            'QueryReply',
            'QueryTicket',
            'QueryTopic',
            'StudentFormAssignment',
            'StudentFormSurvey',
        ],
    ];
}
