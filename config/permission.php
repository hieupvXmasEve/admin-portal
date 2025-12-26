<?php

return [
    'access' => [
        // User Management
        'users' => [
            'view_user' => 'view_user',
            'create_user' => 'create_user',
            'edit_user' => 'edit_user',
            'delete_user' => 'delete_user',
            'import_user' => 'import_user',
            'export_user' => 'export_user',
            // Change gc level
            'change_gc_level' => 'change_gc_level',
            'change_student_status' => 'change_student_status',
        ],

        // Campus Management
        'campuses' => [
            'view_campus' => 'view_campus',
            'create_campus' => 'create_campus',
            'edit_campus' => 'edit_campus',
            'delete_campus' => 'delete_campus',
        ],
        // Building Management
        'buildings' => [
            'view_building' => 'view_building',
            'create_building' => 'create_building',
            'edit_building' => 'edit_building',
            'delete_building' => 'delete_building',
        ],
        // Department Management
        'departments' => [
            'manage_departments' => 'manage_departments',
            // 'create_department' => 'create_department',
            // 'edit_department' => 'edit_department',
            // 'delete_department' => 'delete_department',
        ],

        // Role & Permission Management
        'roles' => [
            'view_role' => 'view_role',
            'create_role' => 'create_role',
            'edit_role' => 'edit_role',
            'delete_role' => 'delete_role',
        ],
        'permissions' => [
            'view_permission' => 'view_permission',
            'create_permission' => 'create_permission',
            'edit_permission' => 'edit_permission',
            'delete_permission' => 'delete_permission',
        ],

        // Academic Management
        'programs' => [
            'view_program' => 'view_program',
            'create_program' => 'create_program',
            'edit_program' => 'edit_program',
            'delete_program' => 'delete_program',
        ],
        'specializations' => [
            'view_specialization' => 'view_specialization',
            'create_specialization' => 'create_specialization',
            'edit_specialization' => 'edit_specialization',
            'delete_specialization' => 'delete_specialization',
        ],
        'units' => [
            'view_unit' => 'view_unit',
            'create_unit' => 'create_unit',
            'edit_unit' => 'edit_unit',
            'delete_unit' => 'delete_unit',
        ],
        'curriculum_versions' => [
            'view_curriculum_version' => 'view_curriculum_version',
            'create_curriculum_version' => 'create_curriculum_version',
            'edit_curriculum_version' => 'edit_curriculum_version',
            'delete_curriculum_version' => 'delete_curriculum_version',
        ],
        'curriculum_units' => [
            'view_curriculum_unit' => 'view_curriculum_unit',
            'create_curriculum_unit' => 'create_curriculum_unit',
            'edit_curriculum_unit' => 'edit_curriculum_unit',
            'delete_curriculum_unit' => 'delete_curriculum_unit',
        ],

        // Scheduling & Resources
        'semesters' => [
            'view_semester' => 'view_semester',
            'create_semester' => 'create_semester',
            'edit_semester' => 'edit_semester',
            'delete_semester' => 'delete_semester',
        ],
        'rooms' => [
            'view_room' => 'view_room',
            'create_room' => 'create_room',
            'edit_room' => 'edit_room',
            'delete_room' => 'delete_room',
        ],
        'room_bookings' => [
            'view_room_booking' => 'view_room_booking',
            'create_room_booking' => 'create_room_booking',
            'edit_room_booking' => 'edit_room_booking',
            'delete_room_booking' => 'delete_room_booking',
            'approve_room_booking' => 'approve_room_booking',
            'manage_room_bookings' => 'manage_room_bookings',
        ],
        'courses' => [
            'view_course' => 'view_course',
            'create_course' => 'create_course',
            'edit_course' => 'edit_course',
            'delete_course' => 'delete_course',
        ],
        'course_offerings' => [
            'view_course_offering' => 'view_course_offering',
            'create_course_offering' => 'create_course_offering',
            'edit_course_offering' => 'edit_course_offering',
            'delete_course_offering' => 'delete_course_offering',
            'add_student_registration' => 'add_student_registration',
            'delete_student_registration' => 'delete_student_registration',

        ],
        'course_registrations' => [
            'view_course_registration' => 'view_course_registration',
            'create_course_registration' => 'create_course_registration',
            'edit_course_registration' => 'edit_course_registration',
            'delete_course_registration' => 'delete_course_registration',
        ],

        // Student Management
        'students' => [
            'view_student' => 'view_student',
            'create_student' => 'create_student',
            'edit_student' => 'edit_student',
            'delete_student' => 'delete_student',
            'view_student_summary' => 'view_student_summary',
        ],

        // Student Applications
        'student_applications' => [
            'view_student_application' => 'view_student_application',
            'create_student_application' => 'create_student_application',
            'edit_student_application' => 'edit_student_application',
            'delete_student_application' => 'delete_student_application',
            'import_student_application' => 'import_student_application',
            'export_student_application' => 'export_student_application'
        ],

        // Attendance Management
        'attendance' => [
            'view_attendance' => 'view_attendance',
            'create_attendance' => 'create_attendance',
            'edit_attendance' => 'edit_attendance',
            'delete_attendance' => 'delete_attendance',
        ],

        // Grading System
        'grades' => [
            'view_grade' => 'view_grade',
            'create_grade' => 'create_grade',
            'edit_grade' => 'edit_grade',
            'delete_grade' => 'delete_grade',
        ],

        // Course Syllabus
        'syllabus' => [
            'view_syllabus' => 'view_syllabus',
            'create_syllabus' => 'create_syllabus',
            'edit_syllabus' => 'edit_syllabus',
            'delete_syllabus' => 'delete_syllabus',
        ],

        // Reports
        'reports' => [
            'view_report' => 'view_report',
            'create_report' => 'create_report',
            'export_report' => 'export_report',
        ],
        'events' => [
            'view_event' => 'view_event',
            'show_event' => 'show_event',
            'create_event' => 'create_event',
            'update_event' => 'update_event',
            'delete_event' => 'delete_event',
            'publish_event' => 'publish_event',
            'cancel_event' => 'cancel_event',
            'complete_event' => 'complete_event',
            'checkin_event' => 'checkin_event',
            'manage_participants' => 'manage_participants',
        ],
        'clubs' => [
            'view_clubs' => 'view_clubs',
            'create_clubs' => 'create_clubs',
            'edit_clubs' => 'edit_clubs',
            'delete_clubs' => 'delete_clubs',
        ],

        // Canvas LMS Integration
        'canvas' => [
            'view_canvas_integration' => 'view_canvas_integration',
            'create_canvas_integration' => 'create_canvas_integration',
            'edit_canvas_integration' => 'edit_canvas_integration',
            'delete_canvas_integration' => 'delete_canvas_integration',
            'sync_canvas_courses' => 'sync_canvas_courses',
            'map_canvas_courses' => 'map_canvas_courses',
        ],

        // Financial Management
        'fees' => [
            'view_fees' => 'view_fees',
            'create_fees' => 'create_fees',
            'import_fees' => 'import_fees',
        ],

        // Reporting & Analytics
        'queries' => [
            'view_queries' => 'view_queries',
            'detail_queries' => 'detail_queries',
            'view_all_queries' => 'view_all_queries',
        ],

        // Lecturer Management
        'lecturers' => [
            'view_lecturer' => 'view_lecturer',
            'create_lecturer' => 'create_lecturer',
            'edit_lecturer' => 'edit_lecturer',
            'delete_lecturer' => 'delete_lecturer',
            'import_lecturer' => 'import_lecturer',
            'export_lecturer' => 'export_lecturer',
        ],


        // Assessment Management
        'assessments' => [
            'view_assessment' => 'view_assessment',
            'create_assessment' => 'create_assessment',
            'edit_assessment' => 'edit_assessment',
            'delete_assessment' => 'delete_assessment',
            'grade_assessment' => 'grade_assessment',
            'export_assessment' => 'export_assessment',
        ],

        // System Management
        'system_logs' => [
            'view_system_log' => 'view_system_log',
        ],

        // Email Management
        'email_configurations' => [
            'view_email_configuration' => 'view_email_configuration',
            'create_email_configuration' => 'create_email_configuration',
            'edit_email_configuration' => 'edit_email_configuration',
            'delete_email_configuration' => 'delete_email_configuration',
            'test_email_configuration' => 'test_email_configuration',
        ],

        'email_templates' => [
            'view_email_template' => 'view_email_template',
            'create_email_template' => 'create_email_template',
            'edit_email_template' => 'edit_email_template',
            'delete_email_template' => 'delete_email_template',
            'preview_email_template' => 'preview_email_template',
        ],

        'email_sending' => [
            'send_single_email' => 'send_single_email',
            'send_bulk_email' => 'send_bulk_email',
            'view_email_queue' => 'view_email_queue',
            'manage_email_queue' => 'manage_email_queue',
        ],

        'email_logs' => [
            'view_email_log' => 'view_email_log',
            'export_email_log' => 'export_email_log',
            'delete_email_log' => 'delete_email_log',
        ],

        'email_preferences' => [
            'view_email_preference' => 'view_email_preference',
            'edit_email_preference' => 'edit_email_preference',
            'manage_user_email_preferences' => 'manage_user_email_preferences',
        ],
        'forms' => [
            'view_form' => 'view_form',
            'edit_form' => 'edit_form',
            'create_form' => 'create_form',
            'review_form' => 'review_form',
            'view_form_analytics' => 'view_form_analytics',
            'view_survey_results_aggregate' => 'view_survey_results_aggregate',
            'view_survey_results_raw' => 'view_survey_results_raw'
        ],

        // Student Wallet Management
        'student_wallets' => [
            'view_student_wallet' => 'view_student_wallet',
            'adjust_wallet_balance' => 'adjust_wallet_balance',
            'deposit_wallet_balance' => 'deposit_wallet_balance'
        ],
        'gold_transactions' => [
            'view_gold_transaction' => 'view_gold_transaction',
            'view_any_gold_transaction' => 'view_any_gold_transaction',
        ],
        'fee' => [
            'view_scholarship' => 'view_scholarship',
            'assign_scholarship' => 'assign_scholarship',
            'import_student_financial' => 'import_student_financial'
        ],
        'vouchers' => [
            'view_voucher' => 'view_voucher',
            'create_voucher' => 'create_voucher',
            'edit_voucher' => 'edit_voucher',
            'delete_voucher' => 'delete_voucher',
        ],
        'tuition_plans' => [
            'view_tuition_plan' => 'view_tuition_plan',
            'create_tuition_plan' => 'create_tuition_plan',
            'edit_tuition_plan' => 'edit_tuition_plan',
            'delete_tuition_plan' => 'delete_tuition_plan',
        ],
        'billing-cycles' => [
            'view_billing_cycle' => 'view_billing_cycle',
            'create_billing_cycle' => 'create_billing_cycle',
            'edit_billing_cycle' => 'edit_billing_cycle',
            'delete_billing_cycle' => 'delete_billing_cycle',
            'activate_billing_cycle' => 'activate_billing_cycle',
            'close_billing_cycle' => 'close_billing_cycle',
        ],
        'invoices' => [
            'view_invoice' => 'view_invoice',
            'create_invoice' => 'create_invoice',
            'edit_invoice' => 'edit_invoice',
            'delete_invoice' => 'delete_invoice',
            'generate_invoice' => 'generate_invoice',
            'pay_invoice' => 'pay_invoice',
        ],
        'modules' => [
            'view_module' => 'view_module',
            'create_module' => 'create_module',
            'edit_module' => 'edit_module',
            'delete_module' => 'delete_module',
        ],
        'surveys' => [
            'view_survey' => 'view_survey',
            'create_survey' => 'create_survey',
            'edit_survey' => 'edit_survey',
            'delete_survey' => 'delete_survey',
            'view_survey_analytics' => 'view_survey_analytics',
        ],
        'notifications' => [
            'send_manual_notification' => 'send_manual_notification',
            'view_any_notification' => 'view_any_notification',
        ],
    ],

    'actions' => [
        'view',
        'create',
        'edit',
        'delete',
        'import',
        'export',
        'detail',
    ],
];
