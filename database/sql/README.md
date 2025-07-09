# SwinX Academic Management System - SQL Scripts

These SQL scripts are generated from Laravel migrations and can be used to create the database schema for the SwinX Academic Management System.

## Files Overview

1. **001_create_all_tables.sql**: Complete database schema with all tables in a single file
2. **002_users_and_auth_tables.sql**: Authentication, users, roles, and permissions tables
3. **003_system_and_jobs_tables.sql**: System tables for caching, jobs, and queue management
4. **004_campus_and_infrastructure_tables.sql**: Campus, buildings, rooms, and room booking tables
5. **005_academic_structure_tables.sql**: Academic program structure, semesters, units, and curriculum tables
6. **006_people_tables.sql**: Student and faculty/lecture tables
7. **007_course_delivery_tables.sql**: Course offerings, registrations, class sessions, and attendance tables
8. **008_assessment_and_academic_records_tables.sql**: Assessment, grades, and academic records tables

## Usage

### Option 1: Create complete schema (all tables)

```bash
mysql -u username -p swinx < 001_create_all_tables.sql
```

### Option 2: Create schema modularly (by component)

```bash
# Create tables in the correct order
mysql -u username -p swinx < 002_users_and_auth_tables.sql
mysql -u username -p swinx < 003_system_and_jobs_tables.sql
mysql -u username -p swinx < 004_campus_and_infrastructure_tables.sql
mysql -u username -p swinx < 005_academic_structure_tables.sql
mysql -u username -p swinx < 006_people_tables.sql
mysql -u username -p swinx < 007_course_delivery_tables.sql
mysql -u username -p swinx < 008_assessment_and_academic_records_tables.sql
```

## Schema Features

- All tables are designed with appropriate foreign key constraints and indexes
- Full-text search indexes for relevant content tables
- Check constraints to ensure data integrity
- Optimized index structure for common query patterns
- Soft delete functionality (deleted_at columns) for relevant tables
- Comprehensive relationship management across all components

## Database Design

The SwinX Academic Management System database is organized into the following major components:

1. **Authentication & Permissions**: User accounts, roles, and fine-grained permissions
2. **System Tables**: Laravel system tables for cache, jobs, and session management
3. **Campus Infrastructure**: Physical campus organization, buildings, rooms, and bookings
4. **Academic Structure**: Programs, specializations, curriculum versions, and graduation requirements
5. **People**: Students, faculty, and their academic profiles
6. **Course Delivery**: Course offerings, registrations, class sessions, and attendance tracking
7. **Academic Records**: Comprehensive grade management, GPAs, and academic performance tracking

Each component has its own SQL file for easier maintenance and understanding of the system's architecture.