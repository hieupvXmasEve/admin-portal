-- ============================================================================
-- SWINBURNE EDUCATION MANAGEMENT SYSTEM - ACADEMIC DATABASE SCHEMA
-- ============================================================================
-- This file contains the complete database schema for the academic management
-- system including room management, class scheduling, attendance tracking,
-- grade management, and academic records.
-- ============================================================================

-- 1. ROOMS TABLE - Room management system
-- ============================================================================
CREATE TABLE `rooms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `campus_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'e.g., "A101", "Computer Lab 1", "Main Auditorium"',
    `code` VARCHAR(20) UNIQUE NOT NULL COMMENT 'e.g., "A101", "CL1", "AUD1"',
    `building` VARCHAR(50) NULL COMMENT 'Building name or code',
    `floor` VARCHAR(10) NULL COMMENT 'Floor number/name',
    `type` ENUM('classroom', 'laboratory', 'computer_lab', 'auditorium', 'meeting_room', 'library', 'study_room', 'workshop', 'office', 'other') DEFAULT 'classroom',
    `capacity` INT NOT NULL DEFAULT 1 COMMENT 'Maximum occupancy',
    `status` ENUM('available', 'occupied', 'maintenance', 'out_of_service', 'reserved') DEFAULT 'available',
    `is_bookable` BOOLEAN DEFAULT TRUE COMMENT 'Can this room be booked?',
    `requires_approval` BOOLEAN DEFAULT FALSE COMMENT 'Requires admin approval for booking',
    `available_from` TIME DEFAULT '07:00:00' COMMENT 'Daily availability start',
    `available_until` TIME DEFAULT '18:00:00' COMMENT 'Daily availability end',
    `blocked_days` JSON NULL COMMENT 'JSON: ["Saturday", "Sunday"] or specific dates',
    `description` TEXT NULL,
    `usage_guidelines` TEXT NULL,
    `booking_notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `rooms_campus_id_type_index` (`campus_id`, `type`),
    INDEX `rooms_status_is_bookable_index` (`status`, `is_bookable`),
    INDEX `rooms_capacity_index` (`capacity`),
    INDEX `rooms_building_floor_index` (`building`, `floor`),
    INDEX `rooms_available_from_available_until_index` (`available_from`, `available_until`),

    -- Constraints
    UNIQUE KEY `unique_campus_room_code` (`campus_id`, `code`),
    FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ROOM BOOKINGS TABLE - Room reservation system
-- ============================================================================
CREATE TABLE `room_bookings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `room_id` BIGINT UNSIGNED NOT NULL,
    `booked_by_user_id` BIGINT UNSIGNED NOT NULL,
    `approved_by_user_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL COMMENT 'Booking title/purpose',
    `description` TEXT NULL,
    `booking_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `booking_type` ENUM('class', 'exam', 'meeting', 'event', 'maintenance', 'personal_study', 'workshop', 'other') DEFAULT 'meeting',
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') DEFAULT 'pending',
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

    -- Recurring booking support
    `is_recurring` BOOLEAN DEFAULT FALSE,
    `recurrence_type` ENUM('daily', 'weekly', 'biweekly', 'monthly') NULL,
    `recurrence_end_date` DATE NULL,
    `recurrence_days` JSON NULL COMMENT 'JSON: ["Monday", "Wednesday", "Friday"]',
    `parent_booking_id` BIGINT UNSIGNED NULL,

    -- Equipment and setup requirements
    `required_equipment` JSON NULL COMMENT 'JSON: ["projector", "microphone"]',
    `setup_requirements` JSON NULL COMMENT 'JSON: ["theater_style", "u_shape", "conference"]',
    `special_requirements` TEXT NULL,

    -- Contact and notification details
    `contact_person` VARCHAR(100) NULL,
    `contact_phone` VARCHAR(20) NULL,
    `contact_email` VARCHAR(255) NULL,
    `send_reminders` BOOLEAN DEFAULT TRUE,

    -- Administrative fields
    `rejection_reason` TEXT NULL,
    `admin_notes` TEXT NULL,
    `approved_at` TIMESTAMP NULL,
    `cancelled_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `room_time_conflict_idx` (`room_id`, `booking_date`, `start_time`, `end_time`),
    INDEX `room_bookings_booked_by_user_id_booking_date_index` (`booked_by_user_id`, `booking_date`),
    INDEX `room_bookings_status_booking_date_index` (`status`, `booking_date`),
    INDEX `room_bookings_booking_type_status_index` (`booking_type`, `status`),
    INDEX `room_bookings_is_recurring_parent_booking_id_index` (`is_recurring`, `parent_booking_id`),
    INDEX `room_bookings_priority_status_index` (`priority`, `status`),

    -- Constraints
    FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`booked_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`parent_booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CLASS SESSIONS TABLE - Detailed class scheduling
-- ============================================================================
CREATE TABLE `class_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `room_id` BIGINT UNSIGNED NULL,
    `instructor_id` BIGINT UNSIGNED NULL,
    `room_booking_id` BIGINT UNSIGNED NULL,
    `session_title` VARCHAR(200) NULL COMMENT 'e.g., "Introduction to Programming", "Midterm Exam"',
    `session_description` TEXT NULL,

    -- Session timing
    `session_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `duration_minutes` INT GENERATED ALWAYS AS (TIMESTAMPDIFF(MINUTE, start_time, end_time)) STORED,

    `session_type` ENUM('lecture', 'tutorial', 'practical', 'laboratory', 'seminar', 'workshop', 'exam', 'assessment', 'field_trip', 'guest_lecture', 'review', 'other') DEFAULT 'lecture',
    `delivery_mode` ENUM('in_person', 'online', 'hybrid', 'blended') DEFAULT 'in_person',
    `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'postponed', 'moved') DEFAULT 'scheduled',

    -- Online session details
    `online_meeting_url` VARCHAR(500) NULL,
    `meeting_id` VARCHAR(100) NULL,
    `meeting_password` VARCHAR(100) NULL,

    -- Session content and materials
    `learning_objectives` JSON NULL COMMENT 'JSON: ["Understand basic concepts", "Apply theory"]',
    `required_materials` JSON NULL COMMENT 'JSON: ["textbook_chapter_5", "calculator", "laptop"]',
    `topics_covered` JSON NULL COMMENT 'JSON: ["Variables", "Functions", "Loops"]',

    -- Attendance and participation
    `attendance_required` BOOLEAN DEFAULT TRUE,
    `attendance_tracking_enabled` BOOLEAN DEFAULT TRUE,
    `expected_attendees` INT NULL,
    `actual_attendees` INT NULL,
    `attendance_percentage` DECIMAL(5,2) NULL,

    -- Assessment details (if applicable)
    `is_assessment` BOOLEAN DEFAULT FALSE,
    `assessment_weight` DECIMAL(5,2) NULL COMMENT 'Percentage of total grade',
    `assessment_duration_minutes` INT NULL,
    `assessment_materials_allowed` JSON NULL COMMENT 'JSON: ["calculator", "notes", "open_book"]',

    -- Recurring session support
    `is_recurring` BOOLEAN DEFAULT FALSE,
    `parent_session_id` BIGINT UNSIGNED NULL,
    `sequence_number` INT NULL COMMENT 'For ordering recurring sessions',

    -- Administrative fields
    `instructor_notes` TEXT NULL,
    `admin_notes` TEXT NULL,
    `student_instructions` TEXT NULL,
    `cancellation_reason` TEXT NULL,

    -- Timestamps for tracking changes
    `scheduled_at` TIMESTAMP NULL,
    `started_at` TIMESTAMP NULL,
    `ended_at` TIMESTAMP NULL,
    `cancelled_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `co_session_schedule_idx` (`course_offering_id`, `session_date`, `start_time`),
    INDEX `room_session_conflict_idx` (`room_id`, `session_date`, `start_time`, `end_time`),
    INDEX `instructor_schedule_idx` (`instructor_id`, `session_date`, `start_time`),
    INDEX `class_sessions_session_type_status_index` (`session_type`, `status`),
    INDEX `class_sessions_delivery_mode_session_date_index` (`delivery_mode`, `session_date`),
    INDEX `class_sessions_is_assessment_session_date_index` (`is_assessment`, `session_date`),
    INDEX `class_sessions_attendance_required_attendance_tracking_enabled_index` (`attendance_required`, `attendance_tracking_enabled`),
    INDEX `class_sessions_is_recurring_parent_session_id_index` (`is_recurring`, `parent_session_id`),
    INDEX `class_sessions_sequence_number_index` (`sequence_number`),
    INDEX `daily_room_schedule_idx` (`session_date`, `room_id`, `start_time`, `end_time`),
    INDEX `instructor_daily_schedule_idx` (`instructor_id`, `session_date`, `session_type`),

    -- Constraints
    UNIQUE KEY `unique_co_sequence` (`course_offering_id`, `sequence_number`),
    FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`room_booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`parent_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ATTENDANCES TABLE - Attendance tracking system
-- ============================================================================
CREATE TABLE `attendances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `class_session_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `recorded_by_user_id` BIGINT UNSIGNED NULL,
    `status` ENUM('present', 'absent', 'late', 'excused', 'partial', 'medical_leave', 'official_leave') DEFAULT 'absent',

    -- Timing details
    `check_in_time` TIMESTAMP NULL,
    `check_out_time` TIMESTAMP NULL,
    `minutes_late` INT DEFAULT 0,
    `minutes_present` INT NULL COMMENT 'How long the student was actually present',

    -- Method of attendance recording
    `recording_method` ENUM('manual', 'qr_code', 'rfid', 'biometric', 'mobile_app', 'online_participation', 'auto_system') DEFAULT 'manual',

    -- Additional details
    `notes` TEXT NULL COMMENT 'Reason for absence, late arrival explanation, etc.',
    `excuse_reason` TEXT NULL COMMENT 'Medical certificate, family emergency, etc.',
    `excuse_document_path` VARCHAR(500) NULL COMMENT 'Path to uploaded excuse document',

    -- Participation and engagement (optional)
    `participation_level` ENUM('excellent', 'good', 'average', 'poor', 'none') NULL,
    `participation_score` DECIMAL(3,1) NULL COMMENT 'Out of 10',
    `participation_notes` TEXT NULL,

    -- Administrative fields
    `is_verified` BOOLEAN DEFAULT FALSE COMMENT 'Has attendance been verified by instructor',
    `affects_grade` BOOLEAN DEFAULT TRUE COMMENT 'Does this attendance count towards grade',
    `is_makeup_allowed` BOOLEAN DEFAULT FALSE COMMENT 'Can student make up for this absence',

    `verified_at` TIMESTAMP NULL,
    `verified_by_user_id` BIGINT UNSIGNED NULL,

    -- For bulk operations and auditing
    `batch_id` VARCHAR(50) NULL COMMENT 'For grouping bulk attendance updates',
    `device_info` JSON NULL COMMENT 'Information about device used for check-in',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address for digital check-ins',
    `location` POINT NULL COMMENT 'GPS coordinates for mobile check-ins',

    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `session_attendance_status_idx` (`class_session_id`, `status`),
    INDEX `student_attendance_status_idx` (`student_id`, `status`),
    INDEX `session_student_idx` (`class_session_id`, `student_id`),
    INDEX `attendances_recorded_by_user_id_created_at_index` (`recorded_by_user_id`, `created_at`),
    INDEX `attendances_recording_method_created_at_index` (`recording_method`, `created_at`),
    INDEX `attendances_status_affects_grade_index` (`status`, `affects_grade`),
    INDEX `attendances_is_verified_verified_at_index` (`is_verified`, `verified_at`),
    INDEX `attendances_batch_id_index` (`batch_id`),
    INDEX `attendances_check_in_time_check_out_time_index` (`check_in_time`, `check_out_time`),
    INDEX `student_grade_attendance_idx` (`student_id`, `status`, `affects_grade`, `created_at`),
    INDEX `student_attendance_timeline_idx` (`student_id`, `check_in_time`, `status`),

    -- Constraints
    UNIQUE KEY `unique_session_student_attendance` (`class_session_id`, `student_id`),
    FOREIGN KEY (`class_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. GRADE COMPONENTS TABLE - Assessment types and grading components
-- ============================================================================
CREATE TABLE `grade_components` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `created_by_user_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'e.g., "Assignment 1", "Midterm Exam", "Final Project"',
    `code` VARCHAR(20) NULL COMMENT 'e.g., "A1", "MID", "PROJ"',
    `description` TEXT NULL,
    `component_type` ENUM('assignment', 'quiz', 'exam', 'midterm', 'final_exam', 'project', 'presentation', 'participation', 'attendance', 'lab_work', 'homework', 'essay', 'research_paper', 'group_work', 'portfolio', 'practical', 'fieldwork', 'internship', 'other') DEFAULT 'assignment',

    -- Grading configuration
    `weight_percentage` DECIMAL(5,2) NOT NULL COMMENT 'Percentage of total course grade (e.g., 25.00)',
    `max_points` DECIMAL(8,2) DEFAULT 100.00 COMMENT 'Maximum possible points',
    `passing_points` DECIMAL(8,2) NULL COMMENT 'Minimum points to pass this component',
    `grading_scale` ENUM('percentage', 'points', 'letter', 'gpa', 'pass_fail', 'complete_incomplete', 'numeric_1_10', 'custom') DEFAULT 'percentage',

    -- Due dates and timing
    `due_date` DATETIME NULL,
    `available_from` DATETIME NULL COMMENT 'When students can start working on it',
    `late_submission_deadline` DATETIME NULL,
    `late_penalty_percentage` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Penalty per day/hour late',
    `late_penalty_type` ENUM('per_day', 'per_hour', 'fixed', 'none') DEFAULT 'none',

    -- Submission and assessment details
    `submission_type` ENUM('online', 'in_person', 'both', 'no_submission') DEFAULT 'online',
    `allowed_file_types` JSON NULL COMMENT 'JSON: ["pdf", "docx", "pptx"]',
    `max_file_size_mb` INT NULL,
    `max_submissions` INT DEFAULT 1 COMMENT 'How many times can student submit',
    `allow_resubmission` BOOLEAN DEFAULT FALSE,

    -- Group work settings
    `is_group_work` BOOLEAN DEFAULT FALSE,
    `min_group_size` INT NULL,
    `max_group_size` INT NULL,
    `students_form_groups` BOOLEAN DEFAULT TRUE COMMENT 'If false, instructor assigns groups',

    -- Assessment criteria and rubrics
    `assessment_criteria` JSON NULL COMMENT 'Detailed rubric or criteria',
    `grade_breakdown` JSON NULL COMMENT 'For complex grading schemes',
    `grading_instructions` TEXT NULL COMMENT 'Instructions for graders',

    -- Administrative settings
    `is_published` BOOLEAN DEFAULT FALSE COMMENT 'Is visible to students',
    `grades_published` BOOLEAN DEFAULT FALSE COMMENT 'Are grades visible to students',
    `is_extra_credit` BOOLEAN DEFAULT FALSE,
    `drop_lowest` BOOLEAN DEFAULT FALSE COMMENT 'For components like "drop lowest quiz"',
    `status` ENUM('draft', 'published', 'in_progress', 'grading', 'completed', 'cancelled') DEFAULT 'draft',

    -- Ordering and organization
    `sort_order` INT DEFAULT 0,
    `category` VARCHAR(50) NULL COMMENT 'Group related components',

    -- Analytics and statistics
    `average_score` DECIMAL(5,2) NULL,
    `median_score` DECIMAL(5,2) NULL,
    `highest_score` DECIMAL(8,2) NULL,
    `lowest_score` DECIMAL(8,2) NULL,
    `total_submissions` INT DEFAULT 0,
    `graded_submissions` INT DEFAULT 0,

    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `co_component_type_idx` (`course_offering_id`, `component_type`),
    INDEX `co_sort_order_idx` (`course_offering_id`, `sort_order`),
    INDEX `grade_components_due_date_status_index` (`due_date`, `status`),
    INDEX `grade_components_is_published_status_index` (`is_published`, `status`),
    INDEX `grade_components_weight_percentage_component_type_index` (`weight_percentage`, `component_type`),
    INDEX `grade_components_is_group_work_component_type_index` (`is_group_work`, `component_type`),
    INDEX `grade_components_created_by_user_id_created_at_index` (`created_by_user_id`, `created_at`),
    INDEX `grade_components_category_sort_order_index` (`category`, `sort_order`),

    -- Constraints
    UNIQUE KEY `unique_co_component_code` (`course_offering_id`, `code`),
    FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. STUDENT GROUPS TABLE - Group work management
-- ============================================================================
CREATE TABLE `student_groups` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `grade_component_id` BIGINT UNSIGNED NOT NULL,
    `created_by_user_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'e.g., "Group A", "Team Alpha", "Project Group 1"',
    `code` VARCHAR(20) NULL COMMENT 'e.g., "GRP1", "ALPHA"',
    `description` TEXT NULL,
    `min_members` INT DEFAULT 1,
    `max_members` INT DEFAULT 10,
    `current_members` INT DEFAULT 0,
    `status` ENUM('forming', 'active', 'completed', 'disbanded', 'inactive') DEFAULT 'forming',
    `is_locked` BOOLEAN DEFAULT FALSE COMMENT 'Prevent changes to membership',
    `locked_at` DATETIME NULL,
    `group_notes` TEXT NULL,
    `member_roles` JSON NULL COMMENT 'JSON: {"leader": "student_id", "secretary": "student_id"}',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `student_groups_grade_component_id_status_index` (`grade_component_id`, `status`),
    INDEX `student_groups_created_by_user_id_created_at_index` (`created_by_user_id`, `created_at`),
    INDEX `student_groups_is_locked_status_index` (`is_locked`, `status`),

    -- Constraints
    UNIQUE KEY `unique_component_group_code` (`grade_component_id`, `code`),
    FOREIGN KEY (`grade_component_id`) REFERENCES `grade_components` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. STUDENT GROUP MEMBERS TABLE - Group membership tracking
-- ============================================================================
CREATE TABLE `student_group_members` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_group_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `added_by_user_id` BIGINT UNSIGNED NULL,
    `role` ENUM('member', 'leader', 'co_leader', 'secretary', 'treasurer', 'coordinator') DEFAULT 'member',
    `status` ENUM('active', 'inactive', 'removed', 'pending_approval') DEFAULT 'active',
    `joined_at` TIMESTAMP NULL,
    `left_at` TIMESTAMP NULL,
    `removal_reason` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `student_group_members_student_group_id_status_index` (`student_group_id`, `status`),
    INDEX `student_group_members_student_id_status_index` (`student_id`, `status`),
    INDEX `student_group_members_role_status_index` (`role`, `status`),

    -- Constraints
    UNIQUE KEY `unique_group_student_membership` (`student_group_id`, `student_id`),
    FOREIGN KEY (`student_group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`added_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. STUDENT GRADES TABLE - Individual student grades
-- ============================================================================
CREATE TABLE `student_grades` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `grade_component_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `graded_by_user_id` BIGINT UNSIGNED NULL,

    -- Grade values
    `points_earned` DECIMAL(8,2) NULL COMMENT 'Actual points earned',
    `percentage_score` DECIMAL(5,2) NULL COMMENT 'Percentage score (0-100)',
    `letter_grade` VARCHAR(5) NULL COMMENT 'A+, A, A-, B+, etc.',
    `gpa_points` DECIMAL(3,2) NULL COMMENT '4.0 scale equivalent',

    -- Submission details
    `submitted_at` TIMESTAMP NULL,
    `graded_at` TIMESTAMP NULL,
    `submission_attempt` INT DEFAULT 1 COMMENT 'Which submission attempt this is',
    `submission_files` JSON NULL COMMENT 'Paths to submitted files',
    `submission_text` LONGTEXT NULL COMMENT 'Text-based submissions',
    `submission_url` VARCHAR(500) NULL COMMENT 'External submission URLs',

    -- Late submission tracking
    `is_late` BOOLEAN DEFAULT FALSE,
    `minutes_late` INT DEFAULT 0,
    `late_penalty_applied` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage penalty applied',
    `late_excuse` TEXT NULL,
    `late_excuse_approved` BOOLEAN DEFAULT FALSE,

    -- Grade status and workflow
    `status` ENUM('not_submitted', 'submitted', 'grading', 'graded', 'returned', 'resubmit_required', 'excused', 'incomplete', 'cancelled') DEFAULT 'not_submitted',
    `grade_status` ENUM('draft', 'provisional', 'final', 'disputed', 'under_review') DEFAULT 'draft',

    -- Feedback and comments
    `instructor_feedback` LONGTEXT NULL,
    `private_notes` LONGTEXT NULL COMMENT 'Internal notes, not visible to student',
    `rubric_scores` JSON NULL COMMENT 'Detailed rubric scoring',
    `bonus_points` DECIMAL(8,2) DEFAULT 0.00,
    `bonus_reason` TEXT NULL,

    -- Group work tracking
    `group_id` BIGINT UNSIGNED NULL,
    `individual_grade_override` BOOLEAN DEFAULT FALSE COMMENT 'Override group grade for this student',
    `individual_override_reason` TEXT NULL,

    -- Academic integrity and plagiarism
    `plagiarism_suspected` BOOLEAN DEFAULT FALSE,
    `plagiarism_score` DECIMAL(5,2) NULL COMMENT 'Similarity percentage from plagiarism checker',
    `plagiarism_notes` TEXT NULL,
    `integrity_status` ENUM('clear', 'under_investigation', 'violation_confirmed', 'violation_minor', 'violation_major') DEFAULT 'clear',

    -- Grade history and auditing
    `grade_history` JSON NULL COMMENT 'Track all grade changes',
    `last_modified_at` TIMESTAMP NULL,
    `last_modified_by_user_id` BIGINT UNSIGNED NULL,

    -- Special circumstances
    `is_extra_credit` BOOLEAN DEFAULT FALSE,
    `is_makeup` BOOLEAN DEFAULT FALSE COMMENT 'Makeup assignment/exam',
    `special_circumstances` TEXT NULL,
    `grade_excluded` BOOLEAN DEFAULT FALSE COMMENT 'Exclude from final grade calculation',
    `exclusion_reason` TEXT NULL,

    -- Student feedback and appeals
    `student_comments` TEXT NULL,
    `appeal_requested` BOOLEAN DEFAULT FALSE,
    `appeal_requested_at` TIMESTAMP NULL,
    `appeal_reason` TEXT NULL,
    `appeal_status` ENUM('none', 'pending', 'under_review', 'approved', 'denied') DEFAULT 'none',

    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `component_student_idx` (`grade_component_id`, `student_id`),
    INDEX `student_status_idx` (`student_id`, `status`),
    INDEX `student_grade_status_idx` (`student_id`, `grade_status`),
    INDEX `student_grades_graded_by_user_id_graded_at_index` (`graded_by_user_id`, `graded_at`),
    INDEX `student_grades_submitted_at_status_index` (`submitted_at`, `status`),
    INDEX `student_grades_is_late_late_penalty_applied_index` (`is_late`, `late_penalty_applied`),
    INDEX `student_grades_plagiarism_suspected_integrity_status_index` (`plagiarism_suspected`, `integrity_status`),
    INDEX `student_grades_appeal_requested_appeal_status_index` (`appeal_requested`, `appeal_status`),
    INDEX `student_grades_group_id_individual_grade_override_index` (`group_id`, `individual_grade_override`),
    INDEX `student_grades_grade_excluded_is_extra_credit_index` (`grade_excluded`, `is_extra_credit`),
    INDEX `student_gpa_calc_idx` (`student_id`, `grade_status`, `grade_excluded`, `gpa_points`),
    INDEX `student_transcript_idx` (`student_id`, `graded_at`, `grade_status`),
    INDEX `student_grade_timeline_idx` (`student_id`, `graded_at`, `grade_status`, `points_earned`),

    -- Constraints
    UNIQUE KEY `unique_component_student_attempt` (`grade_component_id`, `student_id`, `submission_attempt`),
    FOREIGN KEY (`grade_component_id`) REFERENCES `grade_components` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`graded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`last_modified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ACADEMIC RECORDS TABLE - Final grades and transcript data
-- ============================================================================
CREATE TABLE `academic_records` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `course_offering_id` BIGINT UNSIGNED NOT NULL,
    `semester_id` BIGINT UNSIGNED NOT NULL,
    `unit_id` BIGINT UNSIGNED NOT NULL,
    `program_id` BIGINT UNSIGNED NOT NULL,
    `campus_id` BIGINT UNSIGNED NOT NULL,

    -- Final grade information
    `final_percentage` DECIMAL(5,2) NULL COMMENT 'Final percentage score (0-100)',
    `final_letter_grade` VARCHAR(5) NULL COMMENT 'A+, A, A-, B+, etc.',
    `grade_points` DECIMAL(3,2) NULL COMMENT '4.0 scale equivalent',
    `quality_points` DECIMAL(6,2) NULL COMMENT 'grade_points * credit_hours',
    `credit_hours` DECIMAL(4,2) NOT NULL COMMENT 'Credit hours for this course',
    `credit_hours_earned` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Credit hours earned (0 if failed)',

    -- Grade status and verification
    `grade_status` ENUM('in_progress', 'provisional', 'final', 'incomplete', 'withdrawn', 'failed', 'pass_no_credit', 'audit', 'transfer_credit') DEFAULT 'in_progress',
    `completion_status` ENUM('enrolled', 'completed', 'withdrawn', 'failed', 'incomplete', 'in_progress') DEFAULT 'enrolled',

    -- Important dates
    `enrollment_date` DATE NOT NULL,
    `completion_date` DATE NULL,
    `grade_submission_date` DATE NULL,
    `grade_finalized_date` DATE NULL,

    -- Academic performance indicators
    `attendance_percentage` DECIMAL(5,2) NULL,
    `total_absences` INT DEFAULT 0,
    `total_class_sessions` INT NULL,
    `meets_attendance_requirement` BOOLEAN DEFAULT TRUE,

    -- Special circumstances and notes
    `is_repeat_course` BOOLEAN DEFAULT FALSE COMMENT 'Student repeating this course',
    `attempt_number` INT DEFAULT 1 COMMENT 'Which attempt this is (1st, 2nd, etc.)',
    `original_record_id` BIGINT UNSIGNED NULL,

    `is_transfer_credit` BOOLEAN DEFAULT FALSE,
    `transfer_institution` VARCHAR(200) NULL,
    `transfer_course_code` VARCHAR(50) NULL,
    `transfer_course_title` VARCHAR(200) NULL,

    `is_advanced_placement` BOOLEAN DEFAULT FALSE,
    `is_challenge_exam` BOOLEAN DEFAULT FALSE,
    `is_credit_by_exam` BOOLEAN DEFAULT FALSE,

    -- Grading and calculation details
    `grade_breakdown` JSON NULL COMMENT 'Detailed breakdown of component grades',
    `raw_percentage` DECIMAL(5,2) NULL COMMENT 'Before any adjustments',
    `curve_adjustment` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Grade curve applied',
    `grade_adjustment_reason` TEXT NULL,

    `excluded_from_gpa` BOOLEAN DEFAULT FALSE,
    `gpa_exclusion_reason` TEXT NULL,

    -- Administrative fields
    `instructor_id` BIGINT UNSIGNED NULL,
    `grade_submitted_by_user_id` BIGINT UNSIGNED NULL,
    `grade_approved_by_user_id` BIGINT UNSIGNED NULL,

    `instructor_comments` TEXT NULL,
    `administrative_notes` TEXT NULL,

    -- Academic standing impact
    `affects_academic_standing` BOOLEAN DEFAULT TRUE,
    `affects_graduation_requirement` BOOLEAN DEFAULT TRUE,
    `satisfies_prerequisite` BOOLEAN DEFAULT TRUE,

    -- Audit trail
    `grade_history` JSON NULL COMMENT 'Track all grade changes',
    `last_grade_change_at` TIMESTAMP NULL,
    `last_changed_by_user_id` BIGINT UNSIGNED NULL,

    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `student_semester_idx` (`student_id`, `semester_id`),
    INDEX `student_grade_completion_idx` (`student_id`, `grade_status`, `completion_status`),
    INDEX `student_gpa_calc_idx` (`student_id`, `excluded_from_gpa`, `grade_points`),
    INDEX `unit_semester_grade_idx` (`unit_id`, `semester_id`, `grade_status`),
    INDEX `program_semester_completion_idx` (`program_id`, `semester_id`, `completion_status`),
    INDEX `campus_semester_grade_idx` (`campus_id`, `semester_id`, `grade_status`),
    INDEX `instructor_semester_grade_idx` (`instructor_id`, `semester_id`, `grade_status`),
    INDEX `repeat_attempt_idx` (`is_repeat_course`, `attempt_number`),
    INDEX `transfer_grade_idx` (`is_transfer_credit`, `grade_status`),
    INDEX `finalized_date_idx` (`grade_finalized_date`, `grade_status`),
    INDEX `enrollment_completion_dates_idx` (`enrollment_date`, `completion_date`),
    INDEX `transcript_calc_idx` (`student_id`, `completion_status`, `grade_finalized_date`, `credit_hours`),
    INDEX `gpa_calc_idx` (`student_id`, `excluded_from_gpa`, `quality_points`, `credit_hours`),
    INDEX `student_final_grades_idx` (`student_id`, `grade_finalized_date`, `final_letter_grade`),
    INDEX `semester_unit_completion_idx` (`semester_id`, `unit_id`, `completion_status`),

    -- Constraints
    UNIQUE KEY `unique_student_course_offering` (`student_id`, `course_offering_id`),
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`original_record_id`) REFERENCES `academic_records` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`grade_submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`grade_approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`last_changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. GPA CALCULATIONS TABLE - Precomputed GPA values for performance
-- ============================================================================
CREATE TABLE `gpa_calculations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `semester_id` BIGINT UNSIGNED NULL,
    `program_id` BIGINT UNSIGNED NULL,

    -- GPA calculation type
    `calculation_type` ENUM('semester', 'cumulative', 'major', 'program', 'year', 'transfer', 'institutional') DEFAULT 'semester',

    -- GPA values
    `gpa` DECIMAL(4,3) NULL COMMENT 'GPA on 4.0 scale (e.g., 3.750)',
    `quality_points` DECIMAL(8,3) DEFAULT 0.000 COMMENT 'Total quality points earned',
    `credit_hours_attempted` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'Total credit hours attempted',
    `credit_hours_earned` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'Total credit hours successfully completed',
    `credit_hours_gpa` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'Credit hours used in GPA calculation',

    -- Course counts and statistics
    `total_courses` INT DEFAULT 0 COMMENT 'Total number of courses',
    `completed_courses` INT DEFAULT 0 COMMENT 'Successfully completed courses',
    `failed_courses` INT DEFAULT 0 COMMENT 'Failed courses',
    `withdrawn_courses` INT DEFAULT 0 COMMENT 'Withdrawn courses',
    `incomplete_courses` INT DEFAULT 0 COMMENT 'Incomplete courses',

    -- Grade distribution
    `a_grades` INT DEFAULT 0 COMMENT 'A+ and A grades',
    `b_grades` INT DEFAULT 0 COMMENT 'B+ and B grades',
    `c_grades` INT DEFAULT 0 COMMENT 'C+ and C grades',
    `d_grades` INT DEFAULT 0 COMMENT 'D+ and D grades',
    `f_grades` INT DEFAULT 0 COMMENT 'F grades',

    -- Academic standing indicators
    `academic_standing` ENUM('excellent', 'good', 'satisfactory', 'probation', 'suspension', 'dismissal', 'warning') NULL,
    `required_gpa` DECIMAL(3,2) NULL COMMENT 'Required GPA for program/level',
    `meets_gpa_requirement` BOOLEAN DEFAULT TRUE,
    `gpa_deficit` DECIMAL(4,3) NULL COMMENT 'How far below required GPA',

    -- Semester/Year specific data
    `academic_year` VARCHAR(10) NULL COMMENT 'e.g., "2024-2025"',
    `year_level` INT NULL COMMENT '1, 2, 3, 4 for undergrad',
    `semester_type` ENUM('fall', 'spring', 'summer', 'winter') NULL,

    -- Ranking and percentile information
    `class_rank` INT NULL COMMENT 'Rank within graduating class',
    `class_size` INT NULL COMMENT 'Total students in class',
    `percentile` DECIMAL(5,2) NULL COMMENT 'Percentile ranking',
    `program_rank` INT NULL COMMENT 'Rank within program',
    `program_class_size` INT NULL COMMENT 'Total students in program',

    -- Graduation and progression tracking
    `credits_needed_to_graduate` DECIMAL(6,2) NULL,
    `completion_percentage` DECIMAL(5,2) NULL COMMENT '% of degree completed',
    `projected_graduation_date` DATE NULL,
    `on_track_to_graduate` BOOLEAN DEFAULT TRUE,

    -- Special circumstances
    `includes_transfer_credits` BOOLEAN DEFAULT FALSE,
    `includes_repeated_courses` BOOLEAN DEFAULT FALSE,
    `dean_list_eligible` BOOLEAN DEFAULT FALSE,
    `honors_eligible` BOOLEAN DEFAULT FALSE,
    `graduation_honors_eligible` BOOLEAN DEFAULT FALSE,

    -- Calculation metadata
    `calculated_at` TIMESTAMP NOT NULL,
    `calculated_by_user_id` BIGINT UNSIGNED NULL,
    `calculation_parameters` JSON NULL COMMENT 'Parameters used in calculation',
    `calculation_notes` TEXT NULL,

    -- Verification and approval
    `is_verified` BOOLEAN DEFAULT FALSE,
    `verified_at` TIMESTAMP NULL,
    `verified_by_user_id` BIGINT UNSIGNED NULL,

    -- Historical tracking
    `is_current` BOOLEAN DEFAULT TRUE COMMENT 'Is this the current calculation',
    `previous_gpa` DECIMAL(4,3) NULL COMMENT 'Previous GPA for comparison',
    `gpa_change` DECIMAL(4,3) NULL COMMENT 'Change from previous calculation',
    `gpa_trend` ENUM('improving', 'declining', 'stable') NULL,

    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes
    INDEX `student_calc_type_current_idx` (`student_id`, `calculation_type`, `is_current`),
    INDEX `student_semester_calc_idx` (`student_id`, `semester_id`, `calculation_type`),
    INDEX `student_year_calc_idx` (`student_id`, `academic_year`, `calculation_type`),
    INDEX `program_semester_gpa_idx` (`program_id`, `semester_id`, `gpa`),
    INDEX `standing_gpa_idx` (`academic_standing`, `gpa`),
    INDEX `dean_list_gpa_idx` (`dean_list_eligible`, `gpa`),
    INDEX `class_ranking_idx` (`class_rank`, `class_size`),
    INDEX `program_ranking_idx` (`program_rank`, `program_class_size`),
    INDEX `calc_date_current_idx` (`calculated_at`, `is_current`),
    INDEX `verification_idx` (`is_verified`, `verified_at`),
    INDEX `completion_track_idx` (`completion_percentage`, `on_track_to_graduate`),
    INDEX `student_calc_semester_current_idx` (`student_id`, `calculation_type`, `semester_id`, `is_current`),
    INDEX `student_progress_idx` (`student_id`, `gpa`, `credit_hours_earned`, `is_current`),

    -- Constraints
    UNIQUE KEY `unique_student_semester_calc_type` (`student_id`, `semester_id`, `calculation_type`),
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`calculated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATABASE CONSTRAINTS AND OPTIMIZATIONS
-- ============================================================================

-- Data integrity constraints
ALTER TABLE `rooms` ADD CONSTRAINT `check_capacity_positive` CHECK (`capacity` > 0);
ALTER TABLE `rooms` ADD CONSTRAINT `check_available_times` CHECK (`available_from` < `available_until`);

ALTER TABLE `room_bookings` ADD CONSTRAINT `check_booking_times` CHECK (`start_time` < `end_time`);
ALTER TABLE `room_bookings` ADD CONSTRAINT `check_recurrence_end_date` CHECK (`recurrence_end_date` IS NULL OR `recurrence_end_date` >= `booking_date`);

ALTER TABLE `class_sessions` ADD CONSTRAINT `check_session_times` CHECK (`start_time` < `end_time`);
ALTER TABLE `class_sessions` ADD CONSTRAINT `check_expected_attendees_positive` CHECK (`expected_attendees` IS NULL OR `expected_attendees` > 0);
ALTER TABLE `class_sessions` ADD CONSTRAINT `check_actual_attendees_valid` CHECK (`actual_attendees` IS NULL OR `actual_attendees` >= 0);
ALTER TABLE `class_sessions` ADD CONSTRAINT `check_attendance_percentage` CHECK (`attendance_percentage` IS NULL OR (`attendance_percentage` >= 0 AND `attendance_percentage` <= 100));
ALTER TABLE `class_sessions` ADD CONSTRAINT `check_assessment_weight` CHECK (`assessment_weight` IS NULL OR (`assessment_weight` >= 0 AND `assessment_weight` <= 100));

ALTER TABLE `attendances` ADD CONSTRAINT `check_minutes_late_positive` CHECK (`minutes_late` >= 0);
ALTER TABLE `attendances` ADD CONSTRAINT `check_minutes_present_positive` CHECK (`minutes_present` IS NULL OR `minutes_present` >= 0);
ALTER TABLE `attendances` ADD CONSTRAINT `check_participation_score` CHECK (`participation_score` IS NULL OR (`participation_score` >= 0 AND `participation_score` <= 10));

ALTER TABLE `grade_components` ADD CONSTRAINT `check_weight_percentage` CHECK (`weight_percentage` >= 0 AND `weight_percentage` <= 100);
ALTER TABLE `grade_components` ADD CONSTRAINT `check_max_points_positive` CHECK (`max_points` > 0);
ALTER TABLE `grade_components` ADD CONSTRAINT `check_passing_points_valid` CHECK (`passing_points` IS NULL OR (`passing_points` >= 0 AND `passing_points` <= `max_points`));
ALTER TABLE `grade_components` ADD CONSTRAINT `check_late_penalty_percentage` CHECK (`late_penalty_percentage` >= 0 AND `late_penalty_percentage` <= 100);
ALTER TABLE `grade_components` ADD CONSTRAINT `check_group_size_valid` CHECK (
    (`min_group_size` IS NULL OR `min_group_size` >= 1) AND
    (`max_group_size` IS NULL OR `max_group_size` >= 1) AND
    (`min_group_size` IS NULL OR `max_group_size` IS NULL OR `min_group_size` <= `max_group_size`)
);

ALTER TABLE `student_grades` ADD CONSTRAINT `check_percentage_score` CHECK (`percentage_score` IS NULL OR (`percentage_score` >= 0 AND `percentage_score` <= 100));
ALTER TABLE `student_grades` ADD CONSTRAINT `check_gpa_points` CHECK (`gpa_points` IS NULL OR (`gpa_points` >= 0 AND `gpa_points` <= 4));
ALTER TABLE `student_grades` ADD CONSTRAINT `check_submission_attempt_positive` CHECK (`submission_attempt` >= 1);
ALTER TABLE `student_grades` ADD CONSTRAINT `check_late_penalty_applied` CHECK (`late_penalty_applied` >= 0 AND `late_penalty_applied` <= 100);
ALTER TABLE `student_grades` ADD CONSTRAINT `check_plagiarism_score` CHECK (`plagiarism_score` IS NULL OR (`plagiarism_score` >= 0 AND `plagiarism_score` <= 100));

ALTER TABLE `student_groups` ADD CONSTRAINT `check_group_member_counts` CHECK (
    `min_members` >= 1 AND
    `max_members` >= 1 AND
    `min_members` <= `max_members` AND
    `current_members` >= 0 AND
    `current_members` <= `max_members`
);

ALTER TABLE `academic_records` ADD CONSTRAINT `check_final_percentage` CHECK (`final_percentage` IS NULL OR (`final_percentage` >= 0 AND `final_percentage` <= 100));
ALTER TABLE `academic_records` ADD CONSTRAINT `check_grade_points` CHECK (`grade_points` IS NULL OR (`grade_points` >= 0 AND `grade_points` <= 4));
ALTER TABLE `academic_records` ADD CONSTRAINT `check_credit_hours_positive` CHECK (`credit_hours` > 0);
ALTER TABLE `academic_records` ADD CONSTRAINT `check_credit_hours_earned` CHECK (`credit_hours_earned` >= 0 AND `credit_hours_earned` <= `credit_hours`);
ALTER TABLE `academic_records` ADD CONSTRAINT `check_quality_points` CHECK (`quality_points` IS NULL OR `quality_points` >= 0);
ALTER TABLE `academic_records` ADD CONSTRAINT `check_attendance_percentage` CHECK (`attendance_percentage` IS NULL OR (`attendance_percentage` >= 0 AND `attendance_percentage` <= 100));
ALTER TABLE `academic_records` ADD CONSTRAINT `check_attempt_number_positive` CHECK (`attempt_number` >= 1);
ALTER TABLE `academic_records` ADD CONSTRAINT `check_curve_adjustment` CHECK (`curve_adjustment` >= -100 AND `curve_adjustment` <= 100);

ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_gpa_valid` CHECK (`gpa` IS NULL OR (`gpa` >= 0 AND `gpa` <= 4));
ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_quality_points_positive` CHECK (`quality_points` >= 0);
ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_credit_hours_positive` CHECK (
    `credit_hours_attempted` >= 0 AND
    `credit_hours_earned` >= 0 AND
    `credit_hours_gpa` >= 0 AND
    `credit_hours_earned` <= `credit_hours_attempted`
);
ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_course_counts` CHECK (
    `total_courses` >= 0 AND
    `completed_courses` >= 0 AND
    `failed_courses` >= 0 AND
    `withdrawn_courses` >= 0 AND
    `incomplete_courses` >= 0 AND
    (`completed_courses` + `failed_courses` + `withdrawn_courses` + `incomplete_courses`) <= `total_courses`
);
ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_grade_counts` CHECK (
    `a_grades` >= 0 AND `b_grades` >= 0 AND `c_grades` >= 0 AND `d_grades` >= 0 AND `f_grades` >= 0
);
ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_percentile` CHECK (`percentile` IS NULL OR (`percentile` >= 0 AND `percentile` <= 100));
ALTER TABLE `gpa_calculations` ADD CONSTRAINT `check_completion_percentage` CHECK (`completion_percentage` IS NULL OR (`completion_percentage` >= 0 AND `completion_percentage` <= 100));

-- Full-text search indexes
ALTER TABLE `rooms` ADD FULLTEXT(`description`, `usage_guidelines`, `booking_notes`);
ALTER TABLE `room_bookings` ADD FULLTEXT(`title`, `description`, `special_requirements`);
ALTER TABLE `class_sessions` ADD FULLTEXT(`session_title`, `session_description`, `student_instructions`);
ALTER TABLE `grade_components` ADD FULLTEXT(`name`, `description`, `grading_instructions`);

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
