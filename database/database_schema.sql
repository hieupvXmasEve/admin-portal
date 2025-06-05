-- SWINX Database Schema
-- Generated from Laravel migrations
-- This SQL file creates all tables, indexes, and constraints for the SWINX educational management system

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- CORE SYSTEM TABLES
-- =============================================

-- Users table (authentication and base user data)
CREATE TABLE `users` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL UNIQUE,
    `phone` varchar(20) NULL,
    `address` varchar(500) NULL,
    `email_verified_at` timestamp NULL DEFAULT NULL,
    `password` varchar(255) NOT NULL,
    `remember_token` varchar(100) NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
);

-- Password reset tokens
CREATE TABLE `password_reset_tokens` (
    `email` varchar(255) NOT NULL,
    `token` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
);

-- Sessions table
CREATE TABLE `sessions` (
    `id` varchar(255) NOT NULL,
    `user_id` bigint(20) UNSIGNED NULL,
    `ip_address` varchar(45) NULL,
    `user_agent` text NULL,
    `payload` longtext NOT NULL,
    `last_activity` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
);

-- =============================================
-- AUTHORIZATION SYSTEM
-- =============================================

-- Campuses
CREATE TABLE `campuses` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(255) NOT NULL UNIQUE,
    `address` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `campuses_code_unique` (`code`)
);

-- Roles
CREATE TABLE `roles` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL UNIQUE,
    `code` varchar(255) NOT NULL UNIQUE,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roles_name_unique` (`name`),
    UNIQUE KEY `roles_code_unique` (`code`)
);

-- Permissions
CREATE TABLE `permissions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL UNIQUE,
    `code` varchar(255) NOT NULL UNIQUE,
    `description` varchar(255) NULL,
    `parent_id` int(11) NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `permissions_name_unique` (`name`),
    UNIQUE KEY `permissions_code_unique` (`code`)
);

-- Role permissions (many-to-many)
CREATE TABLE `role_permissions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` bigint(20) UNSIGNED NOT NULL,
    `permission_id` bigint(20) UNSIGNED NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `role_permissions_role_id_foreign` (`role_id`),
    KEY `role_permissions_permission_id_foreign` (`permission_id`),
    CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
);

-- Campus user roles (assigns roles to users within specific campuses)
CREATE TABLE `campus_user_roles` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `campus_id` bigint(20) UNSIGNED NOT NULL,
    `role_id` bigint(20) UNSIGNED NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `campus_user_roles_user_id_foreign` (`user_id`),
    KEY `campus_user_roles_campus_id_foreign` (`campus_id`),
    KEY `campus_user_roles_role_id_foreign` (`role_id`),
    CONSTRAINT `campus_user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `campus_user_roles_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `campus_user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
);

-- =============================================
-- ACADEMIC STRUCTURE
-- =============================================

-- Semesters (academic terms)
CREATE TABLE `semesters` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `start_date` datetime NULL,
    `end_date` datetime NULL,
    `enrollment_start_date` datetime NULL,
    `enrollment_end_date` datetime NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 0,
    `is_archived` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `semesters_code_index` (`code`),
    KEY `semesters_is_active_is_archived_index` (`is_active`, `is_archived`),
    KEY `semesters_enrollment_start_date_enrollment_end_date_index` (`enrollment_start_date`, `enrollment_end_date`)
);

-- Programs (degree programs)
CREATE TABLE `programs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(255) NOT NULL UNIQUE,
    `description` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `programs_code_unique` (`code`)
);

-- Specializations (specializations within programs)
CREATE TABLE `specializations` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` bigint(20) UNSIGNED NOT NULL,
    `name` varchar(255) NOT NULL,
    `code` varchar(255) NOT NULL UNIQUE,
    `description` text NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `specializations_code_unique` (`code`),
    UNIQUE KEY `specializations_program_id_name_unique` (`program_id`, `name`),
    KEY `specializations_program_id_is_active_index` (`program_id`, `is_active`),
    CONSTRAINT `specializations_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
);

-- Units (individual courses/subjects)
CREATE TABLE `units` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` varchar(255) NOT NULL UNIQUE,
    `name` varchar(255) NOT NULL,
    `credit_points` decimal(8, 2) NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `units_code_unique` (`code`)
);

-- Curriculum unit types (core, elective, major)
CREATE TABLE `curriculum_unit_types` (
    `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` enum('core', 'elective', 'major') NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `curriculum_unit_types_name_unique` (`name`)
);

-- Curriculum versions (specific versions of program curricula)
CREATE TABLE `curriculum_versions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id` bigint(20) UNSIGNED NOT NULL,
    `specialization_id` bigint(20) UNSIGNED NULL,
    `version_code` varchar(20) NULL,
    `semester_id` bigint(20) UNSIGNED NOT NULL,
    `notes` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `curriculum_versions_program_id_specialization_id_index` (`program_id`, `specialization_id`),
    CONSTRAINT `curriculum_versions_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
    CONSTRAINT `curriculum_versions_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `curriculum_versions_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`)
);

-- Curriculum units (units within curriculum versions)
CREATE TABLE `curriculum_units` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `curriculum_version_id` bigint(20) UNSIGNED NOT NULL,
    `unit_id` bigint(20) UNSIGNED NOT NULL,
    `semester_id` bigint(20) UNSIGNED NOT NULL,
    `unit_type_id` tinyint(3) UNSIGNED NOT NULL,
    `semester_order` tinyint(3) UNSIGNED NULL COMMENT 'Suggested semester (1-12)',
    `is_compulsory` tinyint(1) NOT NULL DEFAULT 1,
    `note` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `curriculum_unit_unique` (`curriculum_version_id`, `unit_id`),
    KEY `curriculum_units_curriculum_version_id_unit_type_id_index` (`curriculum_version_id`, `unit_type_id`),
    KEY `curriculum_units_semester_order_is_compulsory_index` (`semester_order`, `is_compulsory`),
    CONSTRAINT `curriculum_units_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `curriculum_units_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `curriculum_units_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
    CONSTRAINT `curriculum_units_unit_type_id_foreign` FOREIGN KEY (`unit_type_id`) REFERENCES `curriculum_unit_types` (`id`) ON DELETE CASCADE
);

-- =============================================
-- UNIT RELATIONSHIPS AND PREREQUISITES
-- =============================================

-- Equivalent units (units that can substitute for each other)
CREATE TABLE `equivalent_units` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `unit_id` bigint(20) UNSIGNED NOT NULL,
    `equivalent_unit_id` bigint(20) UNSIGNED NOT NULL,
    `reason` varchar(255) NULL,
    `valid_from_semester_id` bigint(20) UNSIGNED NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `equivalent_units_unit_id_equivalent_unit_id_unique` (`unit_id`, `equivalent_unit_id`),
    CONSTRAINT `equivalent_units_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `equivalent_units_equivalent_unit_id_foreign` FOREIGN KEY (`equivalent_unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `equivalent_units_valid_from_semester_id_foreign` FOREIGN KEY (`valid_from_semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL
);

-- Unit prerequisite groups (groups of prerequisites with logical operators)
CREATE TABLE `unit_prerequisite_groups` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `unit_id` bigint(20) UNSIGNED NOT NULL,
    `logic_operator` enum('AND', 'OR') NOT NULL DEFAULT 'AND',
    `description` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `unit_prerequisite_groups_unit_id_index` (`unit_id`),
    CONSTRAINT `unit_prerequisite_groups_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
);

-- Unit prerequisite conditions (individual prerequisite conditions)
CREATE TABLE `unit_prerequisite_conditions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` bigint(20) UNSIGNED NOT NULL,
    `type` enum('prerequisite', 'co_requisite', 'concurrent', 'anti_requisite', 'assumed_knowledge', 'credit_requirement', 'textual') NOT NULL DEFAULT 'prerequisite',
    `required_unit_id` bigint(20) UNSIGNED NULL,
    `required_credits` int(11) NULL,
    `free_text` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `unit_prerequisite_conditions_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `unit_prerequisite_groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `unit_prerequisite_conditions_required_unit_id_foreign` FOREIGN KEY (`required_unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
);

-- =============================================
-- SYLLABUS AND ASSESSMENT
-- =============================================

-- Syllabus (detailed unit content and structure)
CREATE TABLE `syllabus` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `version` varchar(30) NULL,
    `description` text NULL,
    `total_hours` int(11) NULL,
    `hours_per_session` int(11) NULL,
    `semester_id` bigint(20) UNSIGNED NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    `unit_id` bigint(20) UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `syllabus_unit_id_semester_id_is_active_unique` (`unit_id`, `semester_id`, `is_active`),
    CONSTRAINT `syllabus_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
    CONSTRAINT `syllabus_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
);

-- Assessment components (types of assessments for units)
CREATE TABLE `assessment_components` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `syllabus_id` bigint(20) UNSIGNED NOT NULL,
    `name` varchar(100) NULL,
    `weight` decimal(5, 2) NULL,
    `type` enum('quiz', 'assignment', 'project', 'exam', 'online_activity', 'other') NOT NULL,
    `is_required_to_sit_final_exam` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `assessment_components_syllabus_id_foreign` FOREIGN KEY (`syllabus_id`) REFERENCES `syllabus` (`id`) ON DELETE CASCADE
);

-- Assessment component details (individual assessment items)
CREATE TABLE `assessment_component_details` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `component_id` bigint(20) UNSIGNED NOT NULL,
    `name` varchar(100) NOT NULL,
    `weight` decimal(5, 2) NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `assessment_component_details_component_id_foreign` FOREIGN KEY (`component_id`) REFERENCES `assessment_components` (`id`) ON DELETE CASCADE
);

-- =============================================
-- STUDENT ENROLLMENT AND ACADEMIC RECORDS
-- =============================================

-- Student enrollments (student enrollment in programs per semester)
CREATE TABLE `student_enrollments` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `semester_id` bigint(20) UNSIGNED NOT NULL,
    `program_id` bigint(20) UNSIGNED NOT NULL,
    `specialization_id` bigint(20) UNSIGNED NULL,
    `enrollment_status` enum('enrolled', 'active', 'withdrawn', 'completed', 'suspended', 'deferred') NOT NULL DEFAULT 'enrolled',
    `enrollment_date` date NOT NULL,
    `total_credit_hours` decimal(5, 2) NOT NULL DEFAULT 0.00,
    `gpa_semester` decimal(3, 2) NULL,
    `gpa_cumulative` decimal(3, 2) NULL,
    `academic_standing` enum('good_standing', 'probation', 'suspension', 'dismissal', 'dean_list', 'honor_roll') NOT NULL DEFAULT 'good_standing',
    `is_full_time` tinyint(1) NOT NULL DEFAULT 1,
    `is_probation` tinyint(1) NOT NULL DEFAULT 0,
    `is_dean_list` tinyint(1) NOT NULL DEFAULT 0,
    `notes` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_student_semester_enrollment` (`user_id`, `semester_id`),
    KEY `student_enrollments_user_id_semester_id_index` (`user_id`, `semester_id`),
    KEY `student_enrollments_semester_id_enrollment_status_index` (`semester_id`, `enrollment_status`),
    KEY `student_enrollments_program_id_specialization_id_index` (`program_id`, `specialization_id`),
    KEY `student_enrollments_academic_standing_is_probation_index` (`academic_standing`, `is_probation`),
    CONSTRAINT `student_enrollments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `student_enrollments_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
    CONSTRAINT `student_enrollments_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
    CONSTRAINT `student_enrollments_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE SET NULL
);

-- Semester unit offerings (specific offerings of units in semesters)
CREATE TABLE `semester_unit_offerings` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `semester_id` bigint(20) UNSIGNED NOT NULL,
    `unit_id` bigint(20) UNSIGNED NOT NULL,
    `instructor_id` bigint(20) UNSIGNED NULL,
    `section_code` varchar(10) NULL,
    `max_capacity` int(11) NOT NULL DEFAULT 30,
    `current_enrollment` int(11) NOT NULL DEFAULT 0,
    `waitlist_capacity` int(11) NOT NULL DEFAULT 10,
    `current_waitlist` int(11) NOT NULL DEFAULT 0,
    `delivery_mode` enum('in_person', 'online', 'hybrid', 'blended') NOT NULL DEFAULT 'in_person',
    `schedule_days` json NULL,
    `schedule_time_start` time NULL,
    `schedule_time_end` time NULL,
    `location` varchar(255) NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `enrollment_status` enum('open', 'closed', 'waitlist_only', 'cancelled') NOT NULL DEFAULT 'open',
    `special_requirements` text NULL,
    `notes` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_semester_unit_section` (`semester_id`, `unit_id`, `section_code`),
    KEY `semester_unit_offerings_semester_id_unit_id_index` (`semester_id`, `unit_id`),
    KEY `semester_unit_offerings_instructor_id_semester_id_index` (`instructor_id`, `semester_id`),
    KEY `semester_unit_offerings_is_active_enrollment_status_index` (`is_active`, `enrollment_status`),
    KEY `semester_unit_offerings_delivery_mode_index` (`delivery_mode`),
    KEY `semester_unit_offerings_current_enrollment_max_capacity_index` (`current_enrollment`, `max_capacity`),
    CONSTRAINT `semester_unit_offerings_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
    CONSTRAINT `semester_unit_offerings_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
    CONSTRAINT `semester_unit_offerings_instructor_id_foreign` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
);

-- Student unit enrollments (student enrollment in specific unit offerings)
CREATE TABLE `student_unit_enrollments` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_enrollment_id` bigint(20) UNSIGNED NOT NULL,
    `semester_unit_offering_id` bigint(20) UNSIGNED NOT NULL,
    `enrollment_status` enum('enrolled', 'active', 'dropped', 'withdrawn', 'completed', 'waitlisted') NOT NULL DEFAULT 'enrolled',
    `enrollment_date` date NOT NULL,
    `drop_date` date NULL,
    `withdrawal_date` date NULL,
    `midterm_grade` varchar(3) NULL,
    `final_grade` varchar(3) NULL,
    `grade_status` enum('in_progress', 'midterm', 'final', 'incomplete', 'audit') NOT NULL DEFAULT 'in_progress',
    `attendance_percentage` decimal(5, 2) NULL,
    `is_audit` tinyint(1) NOT NULL DEFAULT 0,
    `payment_status` enum('paid', 'pending', 'overdue', 'waived', 'scholarship') NOT NULL DEFAULT 'pending',
    `notes` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_student_unit_enrollment` (`student_enrollment_id`, `semester_unit_offering_id`),
    KEY `sue_student_enrollment_status_idx` (`student_enrollment_id`, `enrollment_status`),
    KEY `sue_offering_status_idx` (`semester_unit_offering_id`, `enrollment_status`),
    KEY `sue_grade_status_idx` (`final_grade`, `grade_status`),
    KEY `sue_payment_status_idx` (`payment_status`),
    KEY `sue_audit_idx` (`is_audit`),
    CONSTRAINT `student_unit_enrollments_student_enrollment_id_foreign` FOREIGN KEY (`student_enrollment_id`) REFERENCES `student_enrollments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `student_unit_enrollments_semester_unit_offering_id_foreign` FOREIGN KEY (`semester_unit_offering_id`) REFERENCES `semester_unit_offerings` (`id`) ON DELETE CASCADE
);

-- =============================================
-- SYSTEM INFRASTRUCTURE TABLES
-- =============================================

-- Cache table
CREATE TABLE `cache` (
    `key` varchar(255) NOT NULL,
    `value` mediumtext NOT NULL,
    `expiration` int(11) NOT NULL,
    PRIMARY KEY (`key`)
);

-- Cache locks
CREATE TABLE `cache_locks` (
    `key` varchar(255) NOT NULL,
    `owner` varchar(255) NOT NULL,
    `expiration` int(11) NOT NULL,
    PRIMARY KEY (`key`)
);

-- Jobs queue
CREATE TABLE `jobs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` varchar(255) NOT NULL,
    `payload` longtext NOT NULL,
    `attempts` tinyint(3) UNSIGNED NOT NULL,
    `reserved_at` int(10) UNSIGNED NULL,
    `available_at` int(10) UNSIGNED NOT NULL,
    `created_at` int(10) UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
);

-- Job batches
CREATE TABLE `job_batches` (
    `id` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `total_jobs` int(11) NOT NULL,
    `pending_jobs` int(11) NOT NULL,
    `failed_jobs` int(11) NOT NULL,
    `failed_job_ids` longtext NOT NULL,
    `options` mediumtext NULL,
    `cancelled_at` int(11) NULL,
    `created_at` int(11) NOT NULL,
    `finished_at` int(11) NULL,
    PRIMARY KEY (`id`)
);

-- Failed jobs
CREATE TABLE `failed_jobs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` varchar(255) NOT NULL UNIQUE,
    `connection` text NOT NULL,
    `queue` text NOT NULL,
    `payload` longtext NOT NULL,
    `exception` longtext NOT NULL,
    `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- INITIAL DATA POPULATION (OPTIONAL)
-- =============================================

-- Insert basic curriculum unit types
INSERT IGNORE INTO `curriculum_unit_types` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'core', NOW(), NOW()),
(2, 'elective', NOW(), NOW()),
(3, 'major', NOW(), NOW());

-- Example campus (uncomment if needed)
-- INSERT INTO `campuses` (`name`, `code`, `address`, `created_at`, `updated_at`) VALUES
-- ('Main Campus', 'MAIN', '123 University Street, Education City', NOW(), NOW());

-- =============================================
-- INDEXES AND PERFORMANCE OPTIMIZATIONS
-- =============================================

-- Additional indexes for common queries
CREATE INDEX `idx_users_email_verified` ON `users` (`email_verified_at`);
CREATE INDEX `idx_semesters_active_period` ON `semesters` (`is_active`, `start_date`, `end_date`);
CREATE INDEX `idx_specializations_active` ON `specializations` (`is_active`);
CREATE INDEX `idx_units_credit_points` ON `units` (`credit_points`);
CREATE INDEX `idx_student_enrollments_status_date` ON `student_enrollments` (`enrollment_status`, `enrollment_date`);
CREATE INDEX `idx_student_unit_enrollments_grades` ON `student_unit_enrollments` (`final_grade`, `grade_status`);

-- =============================================
-- VIEWS FOR COMMON QUERIES (OPTIONAL)
-- =============================================

-- View for active student enrollments with program details
CREATE OR REPLACE VIEW `active_student_enrollments` AS
SELECT
    se.id,
    se.user_id,
    u.name as student_name,
    u.email as student_email,
    s.name as semester_name,
    s.code as semester_code,
    p.name as program_name,
    p.code as program_code,
    sp.name as specialization_name,
    sp.code as specialization_code,
    se.enrollment_status,
    se.gpa_cumulative,
    se.academic_standing
FROM student_enrollments se
JOIN users u ON se.user_id = u.id
JOIN semesters s ON se.semester_id = s.id
JOIN programs p ON se.program_id = p.id
LEFT JOIN specializations sp ON se.specialization_id = sp.id
WHERE se.deleted_at IS NULL
AND se.enrollment_status IN ('enrolled', 'active');

-- View for current semester unit offerings with enrollment stats
CREATE OR REPLACE VIEW `current_unit_offerings` AS
SELECT
    suo.id,
    u.code as unit_code,
    u.name as unit_name,
    u.credit_points,
    s.name as semester_name,
    suo.section_code,
    suo.current_enrollment,
    suo.max_capacity,
    suo.enrollment_status,
    suo.delivery_mode,
    instructor.name as instructor_name
FROM semester_unit_offerings suo
JOIN units u ON suo.unit_id = u.id
JOIN semesters s ON suo.semester_id = s.id
LEFT JOIN users instructor ON suo.instructor_id = instructor.id
WHERE suo.deleted_at IS NULL
AND s.is_active = 1;

-- =============================================
-- COMMENTS AND DOCUMENTATION
-- =============================================

-- Table descriptions for documentation
ALTER TABLE `users` COMMENT = 'Core user authentication and profile data';
ALTER TABLE `campuses` COMMENT = 'Physical campus locations';
ALTER TABLE `programs` COMMENT = 'Academic degree programs (e.g., Bachelor of IT, Master of Business)';
ALTER TABLE `specializations` COMMENT = 'Specializations within programs (e.g., Software Development, Cybersecurity)';
ALTER TABLE `units` COMMENT = 'Individual courses/subjects with credit points';
ALTER TABLE `curriculum_versions` COMMENT = 'Specific versions of program curricula';
ALTER TABLE `curriculum_units` COMMENT = 'Units that belong to specific curriculum versions';
ALTER TABLE `student_enrollments` COMMENT = 'Student enrollment in programs per semester';
ALTER TABLE `semester_unit_offerings` COMMENT = 'Specific offerings of units in semesters with instructor and schedule';
ALTER TABLE `student_unit_enrollments` COMMENT = 'Student enrollment in specific unit offerings with grades';
ALTER TABLE `syllabus` COMMENT = 'Detailed unit content, structure and assessment information';
