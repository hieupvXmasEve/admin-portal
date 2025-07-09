-- SwinX Academic Management System - Academic Structure Tables
-- Generated from Laravel migrations

-- Enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- Semesters/Terms
CREATE TABLE IF NOT EXISTS semesters (
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
CREATE TABLE IF NOT EXISTS programs (
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
CREATE TABLE IF NOT EXISTS units (
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
CREATE TABLE IF NOT EXISTS equivalent_units (
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
CREATE TABLE IF NOT EXISTS specializations (
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
CREATE TABLE IF NOT EXISTS curriculum_versions (
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
CREATE TABLE IF NOT EXISTS graduation_requirements (
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
CREATE TABLE IF NOT EXISTS curriculum_units (
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
CREATE TABLE IF NOT EXISTS unit_prerequisite_groups (
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

CREATE TABLE IF NOT EXISTS unit_prerequisite_conditions (
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

-- Enrollments
CREATE TABLE IF NOT EXISTS enrollments (
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
