-- SwinX Academic Management System - Campus and Infrastructure Tables
-- Generated from Laravel migrations

-- Enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- Campuses
CREATE TABLE IF NOT EXISTS campuses (
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
CREATE TABLE IF NOT EXISTS buildings (
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
CREATE TABLE IF NOT EXISTS rooms (
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
CREATE TABLE IF NOT EXISTS room_bookings (
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

-- Update foreign key for campus_user_roles from the Auth tables
ALTER TABLE campus_user_roles ADD CONSTRAINT fk_campus_user_roles_campus_id FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE;
