-- SwinX Academic Management System Database Schema
-- Generated from Laravel migrations
-- Created: 2025-01-03

-- Enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS swinx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE swinx;

-- ============================================================================
-- Core System Tables
-- ============================================================================

-- Users table (Base authentication)
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    address VARCHAR(500) NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table
CREATE TABLE sessions (
    id VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_sessions_user_id (user_id),
    INDEX idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache tables
CREATE TABLE cache (
    `key` VARCHAR(255) NOT NULL,
    value MEDIUMTEXT NOT NULL,
    expiration INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache_locks (
    `key` VARCHAR(255) NOT NULL,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job queue tables
CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_jobs_queue (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_batches (
    id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT NULL,
    cancelled_at INT NULL,
    created_at INT NOT NULL,
    finished_at INT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_failed_jobs_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personal access tokens (API authentication)
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_personal_access_tokens_tokenable (tokenable_type, tokenable_id),
    UNIQUE KEY idx_personal_access_tokens_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Campus and Infrastructure Tables
-- ============================================================================

-- Campuses
CREATE TABLE campuses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    address VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_campuses_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Buildings
CREATE TABLE buildings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campus_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT NULL,
    address TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_buildings_code (code),
    INDEX idx_buildings_campus_id (campus_id),
    CONSTRAINT fk_buildings_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms
CREATE TABLE rooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campus_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    building VARCHAR(50) NULL,
    floor VARCHAR(10) NULL,
    `type` ENUM('classroom', 'laboratory', 'computer_lab', 'auditorium', 'meeting_room', 'library', 'study_room', 'workshop', 'office', 'other') NOT NULL DEFAULT 'classroom',
    capacity INT NOT NULL DEFAULT 1,
    `status` ENUM('available', 'occupied', 'maintenance', 'out_of_service', 'reserved') NOT NULL DEFAULT 'available',
    is_bookable BOOLEAN NOT NULL DEFAULT TRUE,
    requires_approval BOOLEAN NOT NULL DEFAULT FALSE,
    available_from TIME NOT NULL DEFAULT '07:00:00',
    available_until TIME NOT NULL DEFAULT '18:00:00',
    blocked_days JSON NULL,
    description TEXT NULL,
    usage_guidelines TEXT NULL,
    booking_notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_rooms_campus_type (campus_id, `type`),
    INDEX idx_rooms_status_bookable (`status`, is_bookable),
    INDEX idx_rooms_capacity (capacity),
    INDEX idx_rooms_building_floor (building, floor),
    INDEX idx_rooms_available_times (available_from, available_until),
    UNIQUE KEY idx_rooms_campus_code (campus_id, code),
    CONSTRAINT fk_rooms_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    CONSTRAINT check_capacity_positive CHECK (capacity > 0),
    CONSTRAINT check_available_times CHECK (available_from < available_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add full-text search for rooms
ALTER TABLE rooms ADD FULLTEXT(description, usage_guidelines, booking_notes);

-- Room bookings
CREATE TABLE room_bookings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id BIGINT UNSIGNED NOT NULL,
    booked_by_type VARCHAR(255) NOT NULL,
    booked_by_id BIGINT UNSIGNED NOT NULL,
    approved_by_type VARCHAR(255) NULL,
    approved_by_id BIGINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    booking_type ENUM('class', 'exam', 'meeting', 'event', 'maintenance', 'personal_study', 'workshop', 'other') NOT NULL DEFAULT 'meeting',
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    is_recurring BOOLEAN NOT NULL DEFAULT FALSE,
    recurrence_type ENUM('daily', 'weekly', 'biweekly', 'monthly') NULL,
    recurrence_end_date DATE NULL,
    recurrence_days JSON NULL,
    parent_booking_id BIGINT UNSIGNED NULL,
    required_equipment JSON NULL,
    setup_requirements JSON NULL,
    special_requirements TEXT NULL,
    contact_person VARCHAR(100) NULL,
    contact_phone VARCHAR(20) NULL,
    contact_email VARCHAR(255) NULL,
    send_reminders BOOLEAN NOT NULL DEFAULT TRUE,
    rejection_reason TEXT NULL,
    admin_notes TEXT NULL,
    approved_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_room_bookings_room_time (room_id, booking_date, start_time, end_time),
    INDEX idx_room_bookings_booked_by (booked_by_type, booked_by_id),
    INDEX idx_room_bookings_approved_by (approved_by_type, approved_by_id),
    INDEX idx_room_bookings_status_date (`status`, booking_date),
    INDEX idx_room_bookings_type_status (booking_type, `status`),
    INDEX idx_room_bookings_recurring (is_recurring, parent_booking_id),
    INDEX idx_room_bookings_priority (`priority`, `status`),
    CONSTRAINT fk_room_bookings_room_id FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_room_bookings_parent FOREIGN KEY (parent_booking_id) REFERENCES room_bookings(id) ON DELETE SET NULL,
    CONSTRAINT check_booking_times CHECK (start_time < end_time),
    CONSTRAINT check_recurrence_end_date CHECK (recurrence_end_date IS NULL OR recurrence_end_date >= booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add full-text search for room bookings
ALTER TABLE room_bookings ADD FULLTEXT(title, description, special_requirements);

-- ============================================================================
-- Academic Structure Tables
-- ============================================================================

-- Semesters/Terms
CREATE TABLE semesters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    enrollment_start_date DATETIME NULL,
    enrollment_end_date DATETIME NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_semesters_code (code),
    INDEX idx_semesters_active_archived (is_active, is_archived),
    INDEX idx_semesters_enrollment_dates (enrollment_start_date, enrollment_end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Programs
CREATE TABLE programs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_programs_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Units (Courses/Subjects)
CREATE TABLE units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    credit_points DECIMAL(8,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_units_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equivalent units
CREATE TABLE equivalent_units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id BIGINT UNSIGNED NOT NULL,
    equivalent_unit_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_equivalent_units_unique (unit_id, equivalent_unit_id),
    CONSTRAINT fk_equivalent_units_unit_id FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_equivalent_units_equivalent_unit_id FOREIGN KEY (equivalent_unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Specializations
CREATE TABLE specializations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_specializations_code (code),
    UNIQUE KEY idx_specializations_program_name (program_id, name),
    INDEX idx_specializations_program_active (program_id, is_active),
    CONSTRAINT fk_specializations_program_id FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Curriculum versions
CREATE TABLE curriculum_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED NULL,
    version_code VARCHAR(20) NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_curriculum_versions_program_specialization (program_id, specialization_id),
    CONSTRAINT fk_curriculum_versions_program_id FOREIGN KEY (program_id) REFERENCES programs(id),
    CONSTRAINT fk_curriculum_versions_specialization_id FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE,
    CONSTRAINT fk_curriculum_versions_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Graduation requirements
CREATE TABLE graduation_requirements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED NULL,
    total_credits_required DECIMAL(5,2) NOT NULL,
    core_credits_required DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    major_credits_required DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    elective_credits_required DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    minimum_gpa DECIMAL(3,2) NOT NULL DEFAULT 2.00,
    minimum_major_gpa DECIMAL(3,2) NOT NULL DEFAULT 2.00,
    maximum_study_years INT NOT NULL DEFAULT 6,
    required_internship BOOLEAN NOT NULL DEFAULT FALSE,
    required_thesis BOOLEAN NOT NULL DEFAULT FALSE,
    required_english_certification BOOLEAN NOT NULL DEFAULT FALSE,
    special_requirements JSON NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_graduation_requirements_program_specialization (program_id, specialization_id),
    INDEX idx_graduation_requirements_active_dates (is_active, effective_from, effective_to),
    CONSTRAINT fk_graduation_requirements_program_id FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    CONSTRAINT fk_graduation_requirements_specialization_id FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Curriculum units
CREATE TABLE curriculum_units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curriculum_version_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NULL,
    `type` ENUM('core', 'major', 'elective') NOT NULL,
    unit_scope VARCHAR(255) NOT NULL DEFAULT 'program' COMMENT 'Scope of the unit: program, common, specialization_specific, cross_program',
    year_level TINYINT UNSIGNED NULL COMMENT 'Academic year level (1-4)',
    semester_number TINYINT UNSIGNED NULL COMMENT 'Suggested semester number within the course (1-12)',
    is_required BOOLEAN NOT NULL DEFAULT TRUE,
    minimum_grade DECIMAL(4,2) NULL COMMENT 'Minimum grade required for completion (optional)',
    note TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_curriculum_units_unique (curriculum_version_id, unit_id),
    INDEX idx_curriculum_units_curriculum_type (curriculum_version_id, `type`),
    INDEX idx_curriculum_units_year_semester (year_level, semester_number),
    CONSTRAINT fk_curriculum_units_curriculum_version_id FOREIGN KEY (curriculum_version_id) REFERENCES curriculum_versions(id) ON DELETE CASCADE,
    CONSTRAINT fk_curriculum_units_unit_id FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_curriculum_units_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unit prerequisites
CREATE TABLE unit_prerequisite_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id BIGINT UNSIGNED NOT NULL,
    logic_operator ENUM('AND', 'OR') NOT NULL DEFAULT 'AND',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_unit_prerequisite_groups_unit_id (unit_id),
    CONSTRAINT fk_unit_prerequisite_groups_unit_id FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE unit_prerequisite_conditions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    `type` ENUM('prerequisite', 'co_requisite', 'concurrent', 'anti_requisite', 'assumed_knowledge', 'credit_requirement', 'textual') NOT NULL DEFAULT 'prerequisite',
    required_unit_id BIGINT UNSIGNED NULL,
    required_credits INT NULL,
    free_text TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_unit_prerequisite_conditions_group_id FOREIGN KEY (group_id) REFERENCES unit_prerequisite_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_unit_prerequisite_conditions_required_unit_id FOREIGN KEY (required_unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- People and Roles Tables
-- ============================================================================

-- Roles
CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    code VARCHAR(255) NOT NULL UNIQUE,
    bitwise_permissions JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_roles_name (name),
    UNIQUE KEY idx_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255) NULL,
    code VARCHAR(255) NULL,
    description VARCHAR(255) NULL,
    module VARCHAR(255) NULL,
    parent_id INT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions
CREATE TABLE role_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_role_permissions_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission_id FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campus user roles
CREATE TABLE campus_user_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_campus_user_roles_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_campus_user_roles_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
    CONSTRAINT fk_campus_user_roles_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students
CREATE TABLE students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    oauth_provider ENUM('google', 'microsoft', 'manual') NOT NULL DEFAULT 'google',
    oauth_provider_id VARCHAR(255) NULL,
    avatar_url VARCHAR(500) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    nationality VARCHAR(100) NOT NULL DEFAULT 'Vietnamese',
    national_id VARCHAR(20) NULL UNIQUE,
    address TEXT NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    specialization_id BIGINT UNSIGNED NULL,
    curriculum_version_id BIGINT UNSIGNED NOT NULL,
    admission_date DATE NOT NULL,
    expected_graduation_date DATE NULL,
    emergency_contact_name VARCHAR(255) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    emergency_contact_relationship VARCHAR(100) NULL,
    high_school_name VARCHAR(255) NULL,
    high_school_graduation_year YEAR NULL,
    entrance_exam_score DECIMAL(5,2) NULL,
    admission_notes TEXT NULL,
    `status` ENUM('active', 'inactive', 'suspended', 'graduated') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_students_student_id (student_id),
    INDEX idx_students_email (email),
    INDEX idx_students_oauth (oauth_provider, oauth_provider_id),
    INDEX idx_students_campus_id (campus_id),
    INDEX idx_students_program_specialization (program_id, specialization_id),
    INDEX idx_students_status (`status`),
    CONSTRAINT fk_students_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_students_program_id FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    CONSTRAINT fk_students_specialization_id FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE SET NULL,
    CONSTRAINT fk_students_curriculum_version_id FOREIGN KEY (curriculum_version_id) REFERENCES curriculum_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lectures/Faculty
CREATE TABLE lectures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(10) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    mobile_phone VARCHAR(20) NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    department VARCHAR(100) NULL,
    faculty VARCHAR(100) NULL,
    specialization VARCHAR(255) NULL,
    expertise_areas JSON NULL,
    academic_rank ENUM('lecturer', 'senior_lecturer', 'associate_professor', 'professor', 'emeritus_professor', 'visiting_lecturer', 'adjunct_professor') NOT NULL DEFAULT 'lecturer',
    highest_degree VARCHAR(50) NULL,
    degree_field VARCHAR(255) NULL,
    alma_mater VARCHAR(255) NULL,
    graduation_year YEAR NULL,
    hire_date DATE NOT NULL,
    contract_start_date DATE NULL,
    contract_end_date DATE NULL,
    employment_type ENUM('full_time', 'part_time', 'contract', 'visiting', 'emeritus') NOT NULL DEFAULT 'full_time',
    employment_status ENUM('active', 'on_leave', 'sabbatical', 'retired', 'terminated', 'suspended') NOT NULL DEFAULT 'active',
    preferred_teaching_days JSON NULL,
    preferred_start_time TIME NULL,
    preferred_end_time TIME NULL,
    max_teaching_hours_per_week INT NOT NULL DEFAULT 40,
    teaching_modalities JSON NULL,
    office_address TEXT NULL,
    office_phone VARCHAR(20) NULL,
    emergency_contact_name TEXT NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    emergency_contact_relationship VARCHAR(50) NULL,
    biography TEXT NULL,
    certifications JSON NULL,
    languages JSON NULL,
    hourly_rate DECIMAL(10,2) NULL,
    salary DECIMAL(12,2) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    can_teach_online BOOLEAN NOT NULL DEFAULT TRUE,
    is_available_for_assignment BOOLEAN NOT NULL DEFAULT TRUE,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_lectures_campus_active (campus_id, is_active),
    INDEX idx_lectures_employment_active (employment_status, is_active),
    INDEX idx_lectures_rank_specialization (academic_rank, specialization),
    INDEX idx_lectures_hire_date (hire_date),
    INDEX idx_lectures_email (email),
    INDEX idx_lectures_employee_id (employee_id),
    INDEX idx_lectures_name (last_name, first_name),
    INDEX idx_lectures_department (department, faculty),
    INDEX idx_lectures_available (is_available_for_assignment, employment_status),
    CONSTRAINT fk_lectures_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrollments
CREATE TABLE enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    curriculum_version_id BIGINT UNSIGNED NOT NULL,
    semester_number TINYINT UNSIGNED NOT NULL,
    `status` ENUM('in_progress', 'completed', 'withdrawn') NOT NULL DEFAULT 'in_progress',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_enrollments_student_semester (student_id, semester_id),
    INDEX idx_enrollments_semester_status (semester_id, `status`),
    INDEX idx_enrollments_curriculum_semester (curriculum_version_id, semester_number),
    UNIQUE KEY idx_enrollments_unique (student_id, semester_id),
    CONSTRAINT fk_enrollments_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_curriculum_version_id FOREIGN KEY (curriculum_version_id) REFERENCES curriculum_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Course Delivery Tables
-- ============================================================================

-- Course offerings
CREATE TABLE course_offerings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    semester_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    lecture_id BIGINT UNSIGNED NULL,
    section_code VARCHAR(10) NULL,
    max_capacity INT NOT NULL DEFAULT 1000,
    current_enrollment INT NOT NULL DEFAULT 0,
    waitlist_capacity INT NOT NULL DEFAULT 10,
    current_waitlist INT NOT NULL DEFAULT 0,
    delivery_mode ENUM('in_person', 'online', 'hybrid', 'blended') NOT NULL DEFAULT 'in_person',
    schedule_days JSON NULL,
    schedule_time_start TIME NULL,
    schedule_time_end TIME NULL,
    location VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    enrollment_status ENUM('open', 'closed', 'waitlist_only', 'cancelled') NOT NULL DEFAULT 'open',
    registration_start_date DATE NULL,
    registration_end_date DATE NULL,
    special_requirements TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_course_offerings_semester_unit (semester_id, unit_id),
    INDEX idx_course_offerings_lecture_semester (lecture_id, semester_id),
    INDEX idx_course_offerings_active_status (is_active, enrollment_status),
    INDEX idx_course_offerings_delivery (delivery_mode),
    INDEX idx_course_offerings_enrollment (current_enrollment, max_capacity),
    INDEX idx_course_offerings_registration (registration_start_date, registration_end_date),
    UNIQUE KEY idx_course_offerings_unique (semester_id, unit_id, section_code),
    CONSTRAINT fk_course_offerings_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    CONSTRAINT fk_course_offerings_unit_id FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_course_offerings_lecture_id FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course registrations
CREATE TABLE course_registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    registration_status ENUM('registered', 'confirmed', 'dropped', 'withdrawn', 'completed') NOT NULL DEFAULT 'registered',
    registration_date TIMESTAMP NOT NULL,
    registration_method ENUM('online', 'advisor', 'admin_override') NOT NULL DEFAULT 'online',
    credit_hours DECIMAL(4,2) NOT NULL,
    final_grade VARCHAR(3) NULL,
    grade_points DECIMAL(3,2) NULL,
    attempt_number INT NOT NULL DEFAULT 1,
    is_retake BOOLEAN NOT NULL DEFAULT FALSE,
    drop_date TIMESTAMP NULL,
    withdrawal_date TIMESTAMP NULL,
    completion_date TIMESTAMP NULL,
    retake_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_retake_paid ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_course_registrations_unique (student_id, course_offering_id, semester_id),
    INDEX idx_course_registrations_student (student_id),
    INDEX idx_course_registrations_offering (course_offering_id),
    INDEX idx_course_registrations_semester (semester_id),
    INDEX idx_course_registrations_status (registration_status),
    CONSTRAINT fk_course_registrations_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_course_registrations_course_offering_id FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    CONSTRAINT fk_course_registrations_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Academic holds
CREATE TABLE academic_holds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    hold_type ENUM('financial', 'academic', 'disciplinary', 'administrative', 'health', 'library') NOT NULL,
    hold_category ENUM('registration', 'graduation', 'transcript', 'all') NOT NULL DEFAULT 'registration',
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(10,2) NULL,
    priority ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    status ENUM('active', 'resolved', 'waived', 'expired') NOT NULL DEFAULT 'active',
    placed_date DATE NOT NULL,
    due_date DATE NULL,
    resolved_date DATE NULL,
    placed_by_user_id BIGINT UNSIGNED NULL,
    resolved_by_user_id BIGINT UNSIGNED NULL,
    resolution_notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_academic_holds_student (student_id),
    INDEX idx_academic_holds_type_status (hold_type, status),
    INDEX idx_academic_holds_category (hold_category),
    CONSTRAINT fk_academic_holds_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_academic_holds_placed_by_user_id FOREIGN KEY (placed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_academic_holds_resolved_by_user_id FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class sessions
CREATE TABLE class_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NULL,
    room_booking_id BIGINT UNSIGNED NULL,
    instructor_id BIGINT UNSIGNED NULL,
    session_title VARCHAR(200) NULL,
    session_description TEXT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes INT NULL,
    session_type ENUM('lecture', 'tutorial', 'practical', 'laboratory', 'seminar', 'workshop', 'exam', 'assessment', 'field_trip', 'guest_lecture', 'review', 'other') NOT NULL DEFAULT 'lecture',
    delivery_mode ENUM('in_person', 'online', 'hybrid', 'blended') NOT NULL DEFAULT 'in_person',
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'postponed', 'moved') NOT NULL DEFAULT 'scheduled',
    online_meeting_url VARCHAR(500) NULL,
    meeting_id VARCHAR(100) NULL,
    meeting_password VARCHAR(100) NULL,
    learning_objectives JSON NULL,
    required_materials JSON NULL,
    topics_covered JSON NULL,
    attendance_required BOOLEAN NOT NULL DEFAULT TRUE,
    attendance_tracking_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    expected_attendees INT NULL,
    actual_attendees INT NULL,
    attendance_percentage DECIMAL(5,2) NULL,
    is_assessment BOOLEAN NOT NULL DEFAULT FALSE,
    assessment_weight DECIMAL(5,2) NULL,
    assessment_duration_minutes INT NULL,
    assessment_materials_allowed JSON NULL,
    is_recurring BOOLEAN NOT NULL DEFAULT FALSE,
    parent_session_id BIGINT UNSIGNED NULL,
    sequence_number INT NULL,
    instructor_notes TEXT NULL,
    admin_notes TEXT NULL,
    student_instructions TEXT NULL,
    cancellation_reason TEXT NULL,
    scheduled_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_class_sessions_co_schedule (course_offering_id, session_date, start_time),
    INDEX idx_class_sessions_room_conflict (room_id, session_date, start_time, end_time),
    INDEX idx_class_sessions_instructor_schedule (instructor_id, session_date, start_time),
    INDEX idx_class_sessions_type_status (session_type, status),
    INDEX idx_class_sessions_delivery_date (delivery_mode, session_date),
    INDEX idx_class_sessions_assessment (is_assessment, session_date),
    INDEX idx_class_sessions_attendance_config (attendance_required, attendance_tracking_enabled),
    INDEX idx_class_sessions_recurring (is_recurring, parent_session_id),
    INDEX idx_class_sessions_sequence (sequence_number),
    INDEX idx_class_sessions_daily_room_schedule (session_date, room_id, start_time, end_time),
    INDEX idx_class_sessions_instructor_daily_schedule (instructor_id, session_date, session_type),
    UNIQUE KEY idx_class_sessions_co_sequence (course_offering_id, sequence_number),
    CONSTRAINT fk_class_sessions_course_offering_id FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_sessions_room_id FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    CONSTRAINT fk_class_sessions_room_booking_id FOREIGN KEY (room_booking_id) REFERENCES room_bookings(id) ON DELETE SET NULL,
    CONSTRAINT fk_class_sessions_instructor_id FOREIGN KEY (instructor_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_class_sessions_parent_session_id FOREIGN KEY (parent_session_id) REFERENCES class_sessions(id) ON DELETE SET NULL,
    CONSTRAINT check_sessions_times CHECK (start_time < end_time),
    CONSTRAINT check_sessions_expected_attendees_positive CHECK (expected_attendees IS NULL OR expected_attendees > 0),
    CONSTRAINT check_sessions_actual_attendees_valid CHECK (actual_attendees IS NULL OR actual_attendees >= 0),
    CONSTRAINT check_sessions_attendance_percentage CHECK (attendance_percentage IS NULL OR (attendance_percentage >= 0 AND attendance_percentage <= 100)),
    CONSTRAINT check_sessions_assessment_weight CHECK (assessment_weight IS NULL OR (assessment_weight >= 0 AND assessment_weight <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add full-text search for class sessions
ALTER TABLE class_sessions ADD FULLTEXT(session_title, session_description, student_instructions);

-- Attendances
CREATE TABLE attendances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_session_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    recorded_by_lecture_id BIGINT UNSIGNED NULL,
    status ENUM('present', 'absent', 'late', 'excused', 'partial', 'medical_leave', 'official_leave') NOT NULL DEFAULT 'absent',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    minutes_late INT NOT NULL DEFAULT 0,
    minutes_present INT NULL,
    recording_method ENUM('manual', 'qr_code', 'rfid', 'biometric', 'mobile_app', 'online_participation', 'auto_system') NOT NULL DEFAULT 'manual',
    notes TEXT NULL,
    excuse_reason TEXT NULL,
    excuse_document_path VARCHAR(500) NULL,
    participation_level ENUM('excellent', 'good', 'average', 'poor', 'none') NULL,
    participation_score DECIMAL(3,1) NULL,
    participation_notes TEXT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    affects_grade BOOLEAN NOT NULL DEFAULT TRUE,
    is_makeup_allowed BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    verified_by_lecture_id BIGINT UNSIGNED NULL,
    batch_id VARCHAR(50) NULL,
    device_info JSON NULL,
    ip_address VARCHAR(45) NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_attendances_session_status (class_session_id, status),
    INDEX idx_attendances_student_status (student_id, status),
    INDEX idx_attendances_session_student (class_session_id, student_id),
    INDEX idx_attendances_recorded_by (recorded_by_lecture_id, created_at),
    INDEX idx_attendances_method (recording_method, created_at),
    INDEX idx_attendances_grade (status, affects_grade),
    INDEX idx_attendances_verification (is_verified, verified_at),
    INDEX idx_attendances_batch (batch_id),
    INDEX idx_attendances_times (check_in_time, check_out_time),
    INDEX idx_attendances_student_grade (student_id, status, affects_grade, created_at),
    INDEX idx_attendances_student_timeline (student_id, check_in_time, status),
    INDEX idx_attendances_recorded_by_lecture (recorded_by_lecture_id, created_at),
    UNIQUE KEY idx_attendances_unique (class_session_id, student_id),
    CONSTRAINT fk_attendances_class_session_id FOREIGN KEY (class_session_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendances_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendances_recorded_by_lecture_id FOREIGN KEY (recorded_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_attendances_verified_by_lecture_id FOREIGN KEY (verified_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT check_attendances_minutes_late_positive CHECK (minutes_late >= 0),
    CONSTRAINT check_attendances_minutes_present_positive CHECK (minutes_present IS NULL OR minutes_present >= 0),
    CONSTRAINT check_attendances_participation_score CHECK (participation_score IS NULL OR (participation_score >= 0 AND participation_score <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Academic Records and Assessment Tables
-- ============================================================================

-- Syllabus
CREATE TABLE syllabus (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(30) NULL,
    description TEXT NULL,
    total_hours INT NULL,
    hours_per_session INT NULL,
    semester_id BIGINT UNSIGNED NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_syllabus_unit_semester_active (unit_id, semester_id, is_active),
    CONSTRAINT fk_syllabus_unit_id FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_syllabus_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assessment components
CREATE TABLE assessment_components (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    syllabus_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NULL,
    code VARCHAR(20) NULL,
    description TEXT NULL,
    weight DECIMAL(5,2) NULL,
    type ENUM('quiz', 'assignment', 'project', 'exam', 'online_activity', 'other') NOT NULL,
    is_required_to_sit_final_exam BOOLEAN NOT NULL DEFAULT TRUE,
    due_date DATETIME NULL,
    available_from DATETIME NULL,
    late_submission_deadline DATETIME NULL,
    late_penalty_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    late_penalty_type ENUM('per_day', 'per_hour', 'fixed', 'none') NOT NULL DEFAULT 'none',
    submission_type ENUM('online', 'in_person', 'both', 'no_submission') NOT NULL DEFAULT 'online',
    allowed_file_types JSON NULL,
    max_file_size_mb INT NULL,
    max_submissions INT NOT NULL DEFAULT 1,
    allow_resubmission BOOLEAN NOT NULL DEFAULT FALSE,
    is_group_work BOOLEAN NOT NULL DEFAULT FALSE,
    min_group_size INT NULL,
    max_group_size INT NULL,
    students_form_groups BOOLEAN NOT NULL DEFAULT TRUE,
    assessment_criteria JSON NULL,
    grading_instructions TEXT NULL,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    scores_published BOOLEAN NOT NULL DEFAULT FALSE,
    is_extra_credit BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('draft', 'published', 'in_progress', 'grading', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    category VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_assessment_components_syllabus_code (syllabus_id, code),
    INDEX idx_assessment_components_syllabus_type (syllabus_id, type),
    INDEX idx_assessment_components_syllabus_order (syllabus_id, sort_order),
    INDEX idx_assessment_components_due_status (due_date, status),
    INDEX idx_assessment_components_published_status (is_published, status),
    INDEX idx_assessment_components_group_type (is_group_work, type),
    INDEX idx_assessment_components_category_order (category, sort_order),
    CONSTRAINT fk_assessment_components_syllabus_id FOREIGN KEY (syllabus_id) REFERENCES syllabus(id) ON DELETE CASCADE,
    CONSTRAINT check_weight_percentage CHECK (weight >= 0 AND weight <= 100),
    CONSTRAINT check_late_penalty_percentage CHECK (late_penalty_percentage >= 0 AND late_penalty_percentage <= 100),
    CONSTRAINT check_file_size_positive CHECK (max_file_size_mb IS NULL OR max_file_size_mb > 0),
    CONSTRAINT check_max_submissions_positive CHECK (max_submissions >= 1),
    CONSTRAINT check_group_size_valid CHECK (
        (min_group_size IS NULL OR min_group_size >= 1) AND
        (max_group_size IS NULL OR max_group_size >= 1) AND
        (min_group_size IS NULL OR max_group_size IS NULL OR min_group_size <= max_group_size)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add full-text search for assessment components
ALTER TABLE assessment_components ADD FULLTEXT(name, description, grading_instructions);

-- Assessment component details
CREATE TABLE assessment_component_details (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    component_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    weight DECIMAL(5,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_assessment_component_details_component_id FOREIGN KEY (component_id) REFERENCES assessment_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student groups (for group assignments)
CREATE TABLE student_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    assessment_component_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    created_by_student_id BIGINT UNSIGNED NULL,
    approved_by_lecture_id BIGINT UNSIGNED NULL,
    min_members INT NOT NULL DEFAULT 1,
    max_members INT NOT NULL DEFAULT 5,
    current_members INT NOT NULL DEFAULT 0,
    status ENUM('forming', 'complete', 'approved', 'rejected') NOT NULL DEFAULT 'forming',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_student_groups_component (assessment_component_id),
    INDEX idx_student_groups_offering (course_offering_id),
    INDEX idx_student_groups_status (status),
    CONSTRAINT fk_student_groups_assessment_component_id FOREIGN KEY (assessment_component_id) REFERENCES assessment_components(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_groups_course_offering_id FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_groups_created_by_student_id FOREIGN KEY (created_by_student_id) REFERENCES students(id) ON DELETE SET NULL,
    CONSTRAINT fk_student_groups_approved_by_lecture_id FOREIGN KEY (approved_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT check_group_member_counts CHECK (
        min_members >= 1 AND
        max_members >= 1 AND
        min_members <= max_members AND
        current_members >= 0 AND
        current_members <= max_members
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assessment component detail scores
CREATE TABLE assessment_component_detail_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assessment_component_detail_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    graded_by_lecture_id BIGINT UNSIGNED NULL,
    points_earned DECIMAL(8,2) NULL,
    percentage_score DECIMAL(5,2) NULL,
    letter_grade VARCHAR(5) NULL,
    gpa_points DECIMAL(3,2) NULL,
    submitted_at TIMESTAMP NULL,
    graded_at TIMESTAMP NULL,
    submission_attempt INT NOT NULL DEFAULT 1,
    submission_files JSON NULL,
    submission_text LONGTEXT NULL,
    submission_url VARCHAR(500) NULL,
    is_late BOOLEAN NOT NULL DEFAULT FALSE,
    minutes_late INT NOT NULL DEFAULT 0,
    late_penalty_applied DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    late_excuse TEXT NULL,
    late_excuse_approved BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('not_submitted', 'submitted', 'grading', 'graded', 'returned', 'resubmit_required', 'excused', 'incomplete', 'cancelled') NOT NULL DEFAULT 'not_submitted',
    score_status ENUM('draft', 'provisional', 'final', 'disputed', 'under_review') NOT NULL DEFAULT 'draft',
    instructor_feedback LONGTEXT NULL,
    private_notes LONGTEXT NULL,
    rubric_scores JSON NULL,
    bonus_points DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    bonus_reason TEXT NULL,
    student_group_id BIGINT UNSIGNED NULL,
    individual_score_override BOOLEAN NOT NULL DEFAULT FALSE,
    individual_override_reason TEXT NULL,
    plagiarism_suspected BOOLEAN NOT NULL DEFAULT FALSE,
    plagiarism_score DECIMAL(5,2) NULL,
    plagiarism_notes TEXT NULL,
    integrity_status ENUM('clear', 'under_investigation', 'violation_confirmed', 'violation_minor', 'violation_major') NOT NULL DEFAULT 'clear',
    score_history JSON NULL,
    last_modified_at TIMESTAMP NULL,
    last_modified_by_lecture_id BIGINT UNSIGNED NULL,
    is_extra_credit BOOLEAN NOT NULL DEFAULT FALSE,
    is_makeup BOOLEAN NOT NULL DEFAULT FALSE,
    special_circumstances TEXT NULL,
    score_excluded BOOLEAN NOT NULL DEFAULT FALSE,
    exclusion_reason TEXT NULL,
    student_comments TEXT NULL,
    appeal_requested BOOLEAN NOT NULL DEFAULT FALSE,
    appeal_requested_at TIMESTAMP NULL,
    appeal_reason TEXT NULL,
    appeal_status ENUM('none', 'pending', 'under_review', 'approved', 'denied') NOT NULL DEFAULT 'none',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_detail_scores_detail_student (assessment_component_detail_id, student_id),
    INDEX idx_detail_scores_student_status (student_id, status),
    INDEX idx_detail_scores_student_score_status (student_id, score_status),
    INDEX idx_detail_scores_course_status (course_offering_id, status),
    INDEX idx_detail_scores_graded_by_date (graded_by_lecture_id, graded_at),
    INDEX idx_detail_scores_submitted_status (submitted_at, status),
    INDEX idx_detail_scores_late_penalty (is_late, late_penalty_applied),
    INDEX idx_detail_scores_plagiarism_integrity (plagiarism_suspected, integrity_status),
    INDEX idx_detail_scores_appeal_status (appeal_requested, appeal_status),
    INDEX idx_detail_scores_group_override (student_group_id, individual_score_override),
    INDEX idx_detail_scores_excluded_extra (score_excluded, is_extra_credit),
    INDEX idx_detail_scores_student_gpa_calc (student_id, score_status, score_excluded, gpa_points),
    INDEX idx_detail_scores_student_transcript (student_id, graded_at, score_status),
    INDEX idx_detail_scores_student_score_timeline (student_id, graded_at, score_status, points_earned),
    INDEX idx_detail_scores_graded_by_lecture (graded_by_lecture_id, graded_at),
    UNIQUE KEY idx_detail_scores_unique (assessment_component_detail_id, student_id, course_offering_id, submission_attempt),
    CONSTRAINT fk_detail_scores_detail_id FOREIGN KEY (assessment_component_detail_id) REFERENCES assessment_component_details(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_scores_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_scores_course_offering_id FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_scores_graded_by FOREIGN KEY (graded_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_detail_scores_modified_by FOREIGN KEY (last_modified_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_detail_scores_student_group_id FOREIGN KEY (student_group_id) REFERENCES student_groups(id) ON DELETE SET NULL,
    CONSTRAINT check_scores_percentage_score CHECK (percentage_score IS NULL OR (percentage_score >= 0 AND percentage_score <= 100)),
    CONSTRAINT check_scores_gpa_points CHECK (gpa_points IS NULL OR (gpa_points >= 0 AND gpa_points <= 4)),
    CONSTRAINT check_scores_submission_attempt_positive CHECK (submission_attempt >= 1),
    CONSTRAINT check_scores_minutes_late_positive CHECK (minutes_late >= 0),
    CONSTRAINT check_scores_late_penalty_applied CHECK (late_penalty_applied >= 0 AND late_penalty_applied <= 100),
    CONSTRAINT check_scores_plagiarism_score CHECK (plagiarism_score IS NULL OR (plagiarism_score >= 0 AND plagiarism_score <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Academic records
CREATE TABLE academic_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    course_offering_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    campus_id BIGINT UNSIGNED NOT NULL,
    final_percentage DECIMAL(5,2) NULL,
    final_letter_grade VARCHAR(5) NULL,
    grade_points DECIMAL(3,2) NULL,
    quality_points DECIMAL(6,2) NULL,
    credit_hours DECIMAL(4,2) NOT NULL,
    credit_hours_earned DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    grade_status ENUM('in_progress', 'provisional', 'final', 'incomplete', 'withdrawn', 'failed', 'pass_no_credit', 'audit', 'transfer_credit') NOT NULL DEFAULT 'in_progress',
    completion_status ENUM('enrolled', 'completed', 'withdrawn', 'failed', 'incomplete', 'in_progress') NOT NULL DEFAULT 'enrolled',
    enrollment_date DATE NOT NULL,
    completion_date DATE NULL,
    grade_submission_date DATE NULL,
    grade_finalized_date DATE NULL,
    attendance_percentage DECIMAL(5,2) NULL,
    total_absences INT NOT NULL DEFAULT 0,
    total_class_sessions INT NULL,
    meets_attendance_requirement BOOLEAN NOT NULL DEFAULT TRUE,
    is_repeat_course BOOLEAN NOT NULL DEFAULT FALSE,
    attempt_number INT NOT NULL DEFAULT 1,
    original_record_id BIGINT UNSIGNED NULL,
    is_transfer_credit BOOLEAN NOT NULL DEFAULT FALSE,
    transfer_institution VARCHAR(200) NULL,
    transfer_course_code VARCHAR(50) NULL,
    transfer_course_title VARCHAR(200) NULL,
    is_advanced_placement BOOLEAN NOT NULL DEFAULT FALSE,
    is_challenge_exam BOOLEAN NOT NULL DEFAULT FALSE,
    is_credit_by_exam BOOLEAN NOT NULL DEFAULT FALSE,
    grade_breakdown JSON NULL,
    raw_percentage DECIMAL(5,2) NULL,
    curve_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    grade_adjustment_reason TEXT NULL,
    excluded_from_gpa BOOLEAN NOT NULL DEFAULT FALSE,
    gpa_exclusion_reason TEXT NULL,
    instructor_comments TEXT NULL,
    administrative_notes TEXT NULL,
    instructor_id BIGINT UNSIGNED NULL,
    grade_submitted_by_lecture_id BIGINT UNSIGNED NULL,
    grade_approved_by_lecture_id BIGINT UNSIGNED NULL,
    affects_academic_standing BOOLEAN NOT NULL DEFAULT TRUE,
    affects_graduation_requirement BOOLEAN NOT NULL DEFAULT TRUE,
    satisfies_prerequisite BOOLEAN NOT NULL DEFAULT TRUE,
    grade_history JSON NULL,
    last_grade_change_at TIMESTAMP NULL,
    last_changed_by_lecture_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_academic_records_unique (student_id, course_offering_id),
    INDEX idx_academic_records_student_semester (student_id, semester_id),
    INDEX idx_academic_records_student_grade_completion (student_id, grade_status, completion_status),
    INDEX idx_academic_records_student_gpa (student_id, excluded_from_gpa, grade_points),
    INDEX idx_academic_records_unit_semester_grade (unit_id, semester_id, grade_status),
    INDEX idx_academic_records_program_semester_completion (program_id, semester_id, completion_status),
    INDEX idx_academic_records_campus_semester_grade (campus_id, semester_id, grade_status),
    INDEX idx_academic_records_instructor_semester_grade (instructor_id, semester_id, grade_status),
    INDEX idx_academic_records_repeat_attempt (is_repeat_course, attempt_number),
    INDEX idx_academic_records_transfer_grade (is_transfer_credit, grade_status),
    INDEX idx_academic_records_finalized_date (grade_finalized_date, grade_status),
    INDEX idx_academic_records_enrollment_completion (enrollment_date, completion_date),
    INDEX idx_academic_records_transcript (student_id, completion_status, grade_finalized_date, credit_hours),
    INDEX idx_academic_records_gpa_calc (student_id, excluded_from_gpa, quality_points, credit_hours),
    INDEX idx_academic_records_student_final_grades (student_id, grade_finalized_date, final_letter_grade),
    INDEX idx_academic_records_semester_unit_completion (semester_id, unit_id, completion_status),
    CONSTRAINT fk_academic_records_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_academic_records_course_offering_id FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE,
    CONSTRAINT fk_academic_records_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE RESTRICT,
    CONSTRAINT fk_academic_records_unit_id FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT,
    CONSTRAINT fk_academic_records_program_id FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    CONSTRAINT fk_academic_records_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE RESTRICT,
    CONSTRAINT fk_academic_records_original_record_id FOREIGN KEY (original_record_id) REFERENCES academic_records(id) ON DELETE SET NULL,
    CONSTRAINT fk_academic_records_instructor_id FOREIGN KEY (instructor_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_academic_records_grade_submitted_by FOREIGN KEY (grade_submitted_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_academic_records_grade_approved_by FOREIGN KEY (grade_approved_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_academic_records_last_changed_by FOREIGN KEY (last_changed_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT check_records_final_percentage CHECK (final_percentage IS NULL OR (final_percentage >= 0 AND final_percentage <= 100)),
    CONSTRAINT check_records_grade_points CHECK (grade_points IS NULL OR (grade_points >= 0 AND grade_points <= 4)),
    CONSTRAINT check_records_credit_hours_positive CHECK (credit_hours > 0),
    CONSTRAINT check_records_credit_hours_earned CHECK (credit_hours_earned >= 0 AND credit_hours_earned <= credit_hours),
    CONSTRAINT check_records_quality_points CHECK (quality_points IS NULL OR quality_points >= 0),
    CONSTRAINT check_records_attendance_percentage CHECK (attendance_percentage IS NULL OR (attendance_percentage >= 0 AND attendance_percentage <= 100)),
    CONSTRAINT check_records_attempt_number_positive CHECK (attempt_number >= 1),
    CONSTRAINT check_records_curve_adjustment CHECK (curve_adjustment >= -100 AND curve_adjustment <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GPA calculations
CREATE TABLE gpa_calculations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NULL,
    program_id BIGINT UNSIGNED NULL,
    calculation_type ENUM('semester', 'cumulative', 'major', 'program', 'year', 'transfer', 'institutional') NOT NULL DEFAULT 'semester',
    gpa DECIMAL(4,3) NULL,
    quality_points DECIMAL(8,3) NOT NULL DEFAULT 0.000,
    credit_hours_attempted DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    credit_hours_earned DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    credit_hours_gpa DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    total_courses INT NOT NULL DEFAULT 0,
    completed_courses INT NOT NULL DEFAULT 0,
    failed_courses INT NOT NULL DEFAULT 0,
    withdrawn_courses INT NOT NULL DEFAULT 0,
    incomplete_courses INT NOT NULL DEFAULT 0,
    a_grades INT NOT NULL DEFAULT 0,
    b_grades INT NOT NULL DEFAULT 0,
    c_grades INT NOT NULL DEFAULT 0,
    d_grades INT NOT NULL DEFAULT 0,
    f_grades INT NOT NULL DEFAULT 0,
    academic_standing ENUM('excellent', 'good', 'satisfactory', 'probation', 'suspension', 'dismissal', 'warning') NULL,
    required_gpa DECIMAL(3,2) NULL,
    meets_gpa_requirement BOOLEAN NOT NULL DEFAULT TRUE,
    gpa_deficit DECIMAL(4,3) NULL,
    academic_year VARCHAR(10) NULL,
    year_level INT NULL,
    semester_type ENUM('fall', 'spring', 'summer', 'winter') NULL,
    class_rank INT NULL,
    class_size INT NULL,
    percentile DECIMAL(5,2) NULL,
    program_rank INT NULL,
    program_class_size INT NULL,
    credits_needed_to_graduate DECIMAL(6,2) NULL,
    completion_percentage DECIMAL(5,2) NULL,
    projected_graduation_date DATE NULL,
    on_track_to_graduate BOOLEAN NOT NULL DEFAULT TRUE,
    includes_transfer_credits BOOLEAN NOT NULL DEFAULT FALSE,
    includes_repeated_courses BOOLEAN NOT NULL DEFAULT FALSE,
    dean_list_eligible BOOLEAN NOT NULL DEFAULT FALSE,
    honors_eligible BOOLEAN NOT NULL DEFAULT FALSE,
    graduation_honors_eligible BOOLEAN NOT NULL DEFAULT FALSE,
    calculated_at TIMESTAMP NOT NULL,
    calculated_by_lecture_id BIGINT UNSIGNED NULL,
    calculation_parameters JSON NULL,
    calculation_notes TEXT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    verified_by_lecture_id BIGINT UNSIGNED NULL,
    is_current BOOLEAN NOT NULL DEFAULT TRUE,
    previous_gpa DECIMAL(4,3) NULL,
    gpa_change DECIMAL(4,3) NULL,
    gpa_trend ENUM('improving', 'declining', 'stable') NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_gpa_calculations_unique (student_id, semester_id, calculation_type),
    INDEX idx_gpa_calculations_student_type_current (student_id, calculation_type, is_current),
    INDEX idx_gpa_calculations_student_semester_type (student_id, semester_id, calculation_type),
    INDEX idx_gpa_calculations_student_year_type (student_id, academic_year, calculation_type),
    INDEX idx_gpa_calculations_program_semester_gpa (program_id, semester_id, gpa),
    INDEX idx_gpa_calculations_standing_gpa (academic_standing, gpa),
    INDEX idx_gpa_calculations_dean_list_gpa (dean_list_eligible, gpa),
    INDEX idx_gpa_calculations_class_ranking (class_rank, class_size),
    INDEX idx_gpa_calculations_program_ranking (program_rank, program_class_size),
    INDEX idx_gpa_calculations_calc_date_current (calculated_at, is_current),
    INDEX idx_gpa_calculations_verification (is_verified, verified_at),
    INDEX idx_gpa_calculations_completion_track (completion_percentage, on_track_to_graduate),
    INDEX idx_gpa_calculations_student_type_semester_current (student_id, calculation_type, semester_id, is_current),
    INDEX idx_gpa_calculations_student_progress (student_id, gpa, credit_hours_earned, is_current),
    CONSTRAINT fk_gpa_calculations_student_id FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_gpa_calculations_semester_id FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL,
    CONSTRAINT fk_gpa_calculations_program_id FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL,
    CONSTRAINT fk_gpa_calculations_calculated_by FOREIGN KEY (calculated_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT fk_gpa_calculations_verified_by FOREIGN KEY (verified_by_lecture_id) REFERENCES lectures(id) ON DELETE SET NULL,
    CONSTRAINT check_gpa_valid CHECK (gpa IS NULL OR (gpa >= 0 AND gpa <= 4)),
    CONSTRAINT check_quality_points_positive CHECK (quality_points >= 0),
    CONSTRAINT check_credit_hours_positive CHECK (
        credit_hours_attempted >= 0 AND
        credit_hours_earned >= 0 AND
        credit_hours_gpa >= 0 AND
        credit_hours_earned <= credit_hours_attempted
    ),
    CONSTRAINT check_course_counts CHECK (
        total_courses >= 0 AND
        completed_courses >= 0 AND
        failed_courses >= 0 AND
        withdrawn_courses >= 0 AND
        incomplete_courses >= 0 AND
        (completed_courses + failed_courses + withdrawn_courses + incomplete_courses) <= total_courses
    ),
    CONSTRAINT check_grade_counts CHECK (
        a_grades >= 0 AND b_grades >= 0 AND c_grades >= 0 AND d_grades >= 0 AND f_grades >= 0
    ),
    CONSTRAINT check_percentile CHECK (percentile IS NULL OR (percentile >= 0 AND percentile <= 100)),
    CONSTRAINT check_completion_percentage CHECK (completion_percentage IS NULL OR (completion_percentage >= 0 AND completion_percentage <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Final housekeeping
-- ============================================================================

-- Make sure all tables are in utf8mb4 character set
ALTER DATABASE swinx DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create a statement to verify all tables have been created
SELECT CONCAT('Tables created successfully: ', COUNT(*)) AS status
FROM information_schema.tables
WHERE table_schema = 'swinx';
