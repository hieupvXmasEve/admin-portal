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
            'complete_course_offering' => 'complete_course_offering',
            'recalculate_course_offering' => 'recalculate_course_offering',
            // Cockpit Scores tab "Sync from Canvas" (preview + apply). Distinct from
            // 'sync_canvas_courses' (Canvas-integration admin bulk sync).
            'sync_course_grades' => 'sync_course_grades',
            'delete_course_offering' => 'delete_course_offering',
            'add_student_registration' => 'add_student_registration',
            'delete_student_registration' => 'delete_student_registration',

        ],
        'class_sessions' => [
            'view_class_session' => 'view_class_session',
            'create_class_session' => 'create_class_session',
            'edit_class_session' => 'edit_class_session',
            'delete_class_session' => 'delete_class_session',
            'generate_class_session' => 'generate_class_session',
            'generate_class_session_attendance' => 'generate_class_session_attendance',
            'export_class_session_attendance' => 'export_class_session_attendance',
        ],
        'course_registrations' => [
            'view_course_registration' => 'view_course_registration',
            'create_course_registration' => 'create_course_registration',
            'edit_course_registration' => 'edit_course_registration',
            'delete_course_registration' => 'delete_course_registration',
        ],
        'retake_course_registrations' => [
            'view_retake_course' => 'view_retake_course',
            'create_retake_course' => 'create_retake_course',
            'cancel_retake_course' => 'cancel_retake_course',
        ],
        'exam_resit_operations' => [
            'view_exam_resit' => 'view_exam_resit',
            'create_exam_resit' => 'create_exam_resit',
            'cancel_exam_resit' => 'cancel_exam_resit',
            'schedule_exam_resit' => 'schedule_exam_resit',
            'complete_exam_resit' => 'complete_exam_resit',
            'manage_exam_schedule' => 'manage_exam_schedule',
        ],
        'gpa_management' => [
            'view_gpa_finalization' => 'view_gpa_finalization',
            'create_gpa_finalization' => 'create_gpa_finalization',
            'view_performance_dashboard' => 'view_performance_dashboard',
            'view_academic_report' => 'view_academic_report',
        ],
        'gpa_history' => [
            'view_gpa_history' => 'view_gpa_history',
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
            'export_student_application' => 'export_student_application',
            // Campus-scoped lifecycle permissions (enforced via StudentApplicationPolicy).
            'approve_student_application' => 'approve_student_application',
            'reject_student_application' => 'reject_student_application',
            'revoke_student_application' => 'revoke_student_application',
            // Global (org-wide only): mapping rows write campus_code across every
            // campus's pending applications, so this is deliberately not a
            // campus-scoped permission — see phase-04-mapping-config-ui.md.
            'manage_crm_value_mapping' => 'manage_crm_value_mapping',
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

            // Student action audit page
            'view_student_action' => 'view_student_action',
            // Student decision management (link/unlink action logs to decisions)
            'unlink_student_from_decision' => 'unlink_student_from_decision',
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

        // AI Governance
        'ai_metrics' => [
            'view_ai_metrics' => 'view_ai_metrics',
            // All-campus AI scope (ADR-0010): a dedicated AI-wide permission that lets a
            // holder span every campus over the Controlled MCP server. Distinct from the
            // finance all-campus permission (wrong domain); per-domain data stays gated by
            // its own domain permission — this only governs campus span.
            'view_ai_all_campus' => 'view_ai_all_campus',
        ],
        'ai_provider_settings' => [
            'view_ai_provider_settings' => 'view_ai_provider_settings',
            'manage_ai_provider_settings' => 'manage_ai_provider_settings',
            'test_ai_provider_settings' => 'test_ai_provider_settings',
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
        'system_configuration' => [
            'view_system_config' => 'view_system_config',
            'manage_system_config' => 'manage_system_config',
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
        ],

        'gold_transactions' => [
            'view_gold_transaction' => 'view_gold_transaction',
            'view_any_gold_transaction' => 'view_any_gold_transaction',
            'adjust_gold_wallet' => 'adjust_gold_wallet',
        ],
        // Merchandise store — catalog + inventory (Phase 2).
        'merchandise' => [
            'view_merchandise' => 'view_merchandise',
            'create_merchandise' => 'create_merchandise',
            'edit_merchandise' => 'edit_merchandise',
            'archive_merchandise' => 'archive_merchandise',
            'manage_merchandise_image' => 'manage_merchandise_image',
            'manage_merchandise_variant' => 'manage_merchandise_variant',
            'adjust_merchandise_stock' => 'adjust_merchandise_stock',
            'view_merchandise_report' => 'view_merchandise_report',
            'export_merchandise_report' => 'export_merchandise_report',
            'view_merchandise_audit' => 'view_merchandise_audit',
        ],
        // Merchandise redemption orders (Phase 3) — campus-scoped via
        // RedemptionOrderPolicy, resolved from the order's snapshot campus_id.
        'redemption' => [
            'view_redemption_order' => 'view_redemption_order',
            'approve_redemption_order' => 'approve_redemption_order',
            'reject_redemption_order' => 'reject_redemption_order',
            'cancel_redemption_order' => 'cancel_redemption_order',
            'confirm_redemption_collection' => 'confirm_redemption_collection',
            'mark_redemption_shipped' => 'mark_redemption_shipped',
        ],
        'fee' => [
            'view_scholarship' => 'view_scholarship',
            'assign_scholarship' => 'assign_scholarship',
            'remove_scholarship' => 'remove_scholarship',
            'import_student_financial' => 'import_student_financial',
        ],
        'scholarship_adjustments' => [
            'view_scholarship_adjustment' => 'view_scholarship_adjustment',
            'manage_scholarship_adjustment_candidate' => 'manage_scholarship_adjustment_candidate',
            'manage_scholarship_interview' => 'manage_scholarship_interview',
            'decide_scholarship_adjustment' => 'decide_scholarship_adjustment',
            'approve_scholarship_adjustment' => 'approve_scholarship_adjustment',
            'apply_scholarship_adjustment_finance' => 'apply_scholarship_adjustment_finance',
            'restore_scholarship' => 'restore_scholarship',
            'confirm_scholarship_adjustment_on_behalf' => 'confirm_scholarship_adjustment_on_behalf',
        ],
        'vouchers' => [
            'view_voucher' => 'view_voucher',
            'create_voucher' => 'create_voucher',
            'edit_voucher' => 'edit_voucher',
        ],
        'tuition_plans' => [
            'view_tuition_plan' => 'view_tuition_plan',
            'create_tuition_plan' => 'create_tuition_plan',
            'edit_tuition_plan' => 'edit_tuition_plan',
            'delete_tuition_plan' => 'delete_tuition_plan',
        ],
        'finances' => [
            'view_finance_operations_dashboard' => 'view_finance_operations_dashboard',
            'view_finance_operations_generate_charges' => 'view_finance_operations_generate_charges',
            'view_finance_operations_exceptions' => 'view_finance_operations_exceptions',
            'view_finance_operations_due_calendar' => 'view_finance_operations_due_calendar',
            // EGC Operations
            'view_egc_finance_operations' => 'view_egc_finance_operations',
            'generate_egc_finance_charges' => 'generate_egc_finance_charges',
            'view_egc_block_results' => 'view_egc_block_results',
            'sync_egc_block_results' => 'sync_egc_block_results',
            'view_egc_retake_adjustments' => 'view_egc_retake_adjustments',
            'apply_egc_retake_adjustment' => 'apply_egc_retake_adjustment',
            // Invoices
            'view_finance_invoices' => 'view_finance_invoices',
            'view_finance_export_invoices' => 'view_finance_export_invoices',
            // Charges
            'view_finance_charges' => 'view_finance_charges',
            'create_finance_charges' => 'create_finance_charges',
            'void_finance_charges' => 'void_finance_charges',
            'split_installment_finance_charges' => 'split_installment_finance_charges',
            // Payments
            'view_finance_payments' => 'view_finance_payments',
            'allocate_finance_payment' => 'allocate_finance_payment',
            'refund_finance_payment' => 'refund_finance_payment',
            'forfeit_finance_payment_surplus' => 'forfeit_finance_payment_surplus',
            'view_finance_payment_details' => 'view_finance_payment_details',
            'create_finance_payments' => 'create_finance_payments',
            'view_finance_dng_payment_requests' => 'view_finance_dng_payment_requests',
            'view_finance_dng_webhook_events' => 'view_finance_dng_webhook_events',
            'view_finance_dng_receipt_exceptions' => 'view_finance_dng_receipt_exceptions',
            'resolve_finance_dng_receipt_exceptions' => 'resolve_finance_dng_receipt_exceptions',
            // Audit Workspace (export endpoint deferred; permission seeded now)
            'view_finance_audit_workspace' => 'view_finance_audit_workspace',
            'export_finance_audit_workspace' => 'export_finance_audit_workspace',
            // Retake Course Charges
            'view_retake_course_charge' => 'view_retake_course_charge',
            'create_retake_course_charge' => 'create_retake_course_charge',
            // Staff workspace foundation (S-010 milestone 1)
            'view_finance_student_overview' => 'view_finance_student_overview',
            'view_finance_all_campus' => 'view_finance_all_campus',
            // Batch Studio (S-010 milestone 4) — thin view gate; each job gated per-action
            'view_finance_batch_studio' => 'view_finance_batch_studio',
            // Cockpit "Hôm nay" (S-010 milestone 3) — thin view gate; widgets check source permissions
            'view_finance_cockpit' => 'view_finance_cockpit',
            // Finance Reporting (FIN-REV-016) — thin shell gate; lens stories own data permissions later
            'view_finance_reporting' => 'view_finance_reporting',
            // Revenue report — always school-wide (every campus), unlike the
            // campus-bound view_finance_reporting. Grant deliberately.
            'view_finance_revenue_report' => 'view_finance_revenue_report',
            // Pricing Operations (obligation v2 wave 1) — catalog rules only; types are code-owned
            'view_finance_pricing_operations' => 'view_finance_pricing_operations',
            'manage_finance_pricing_operations' => 'manage_finance_pricing_operations',
            // DNG provider configuration is Finance-owned and scoped to the selected campus.
            'view_finance_dng_campus_mappings' => 'view_finance_dng_campus_mappings',
            'manage_finance_dng_campus_mappings' => 'manage_finance_dng_campus_mappings',
            // Finance settings (credit offset toggle/threshold) — the SETTING is
            // global (applies to every campus), but the permission check itself
            // is campus-scoped like every other permission in this system
            // (CampusPermissionReader). A role granting manage_finance_settings
            // at ANY single campus can change behavior for ALL campuses. Only
            // grant this to HQ/super_admin-level roles, never a campus-local
            // finance admin role.
            'view_finance_settings' => 'view_finance_settings',
            'manage_finance_settings' => 'manage_finance_settings',
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
            'view_survey_results_aggregate' => 'view_survey_results_aggregate',
            'view_survey_results_raw' => 'view_survey_results_raw',
            'configure_survey_aggregate' => 'configure_survey_aggregate',
        ],
        'notifications' => [
            'send_manual_notification' => 'send_manual_notification',
            'view_any_notification' => 'view_any_notification',
            'view_email_system' => 'view_email_system',
            'manage_email_system' => 'manage_email_system',
            'view_notification_ops' => 'view_notification_ops',
        ],

        // Notification Templates — per-channel template management.
        // Currently covers email templates (NotificationEmailTemplate); future
        // channels (SMS, push, in-app) will add sibling buckets here, e.g.
        // notification_sms_templates, notification_push_templates.
        'notification_email_templates' => [
            'view_notification_email_template' => 'view_notification_email_template',
            'edit_notification_email_template' => 'edit_notification_email_template',
            'preview_notification_email_template' => 'preview_notification_email_template',
            'test_send_notification_email_template' => 'test_send_notification_email_template',
            // No create/delete: the canonical template set is provisioned from
            // NotificationTemplateTypeKey for each campus.
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
