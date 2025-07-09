-- SwinX Academic Management System - SQL Scripts Index
-- Generated from Laravel migrations

-- This file lists all the SQL script files available in this directory
-- and provides instructions for creating the database schema.

/*
 * Files Overview:
 *
 * 1. 001_create_all_tables.sql: Complete database schema with all tables in a single file
 * 2. 002_users_and_auth_tables.sql: Authentication, users, roles, and permissions tables
 * 3. 003_system_and_jobs_tables.sql: System tables for caching, jobs, and queue management
 * 4. 004_campus_and_infrastructure_tables.sql: Campus, buildings, rooms, and room booking tables
 * 5. 005_academic_structure_tables.sql: Academic program structure, semesters, units, and curriculum tables
 * 6. 006_people_tables.sql: Student and faculty/lecture tables
 * 7. 007_course_delivery_tables.sql: Course offerings, registrations, class sessions, and attendance tables
 * 8. 008_assessment_and_academic_records_tables.sql: Assessment, grades, and academic records tables
 */

-- ----------------------------------------------------------------------------
-- Option 1: Create database and all tables at once
-- ----------------------------------------------------------------------------

-- To create the complete database schema with all tables:
-- mysql -u username -p < 001_create_all_tables.sql

-- ----------------------------------------------------------------------------
-- Option 2: Create database and tables modularly (by component)
-- ----------------------------------------------------------------------------

-- Create database
CREATE DATABASE IF NOT EXISTS swinx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE swinx;

-- The following scripts should be executed in the correct order to maintain referential integrity:
-- 1. mysql -u username -p swinx < 002_users_and_auth_tables.sql
-- 2. mysql -u username -p swinx < 003_system_and_jobs_tables.sql
-- 3. mysql -u username -p swinx < 004_campus_and_infrastructure_tables.sql
-- 4. mysql -u username -p swinx < 005_academic_structure_tables.sql
-- 5. mysql -u username -p swinx < 006_people_tables.sql
-- 6. mysql -u username -p swinx < 007_course_delivery_tables.sql
-- 7. mysql -u username -p swinx < 008_assessment_and_academic_records_tables.sql

-- ----------------------------------------------------------------------------
-- Verification query
-- ----------------------------------------------------------------------------

-- After running the scripts, you can verify the tables were created correctly:
SELECT
    table_schema AS 'Database',
    table_name AS 'Table',
    table_rows AS 'Rows',
    create_time AS 'Created'
FROM
    information_schema.tables
WHERE
    table_schema = 'swinx'
ORDER BY
    table_name;
