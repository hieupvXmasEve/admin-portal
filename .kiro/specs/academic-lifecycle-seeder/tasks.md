# Implementation Plan

- [ ]   1. Create core seeder infrastructure and configuration system
    - Implement base seeder orchestrator class with phase management
    - Create configuration schema and validation system
    - Set up progress tracking and reporting mechanisms
    - _Requirements: 7.1, 7.2, 7.3_

- [ ]   2. **CRITICAL FIX**: Implement Phase 1: Enrollment and Course Offering Generation
    - [ ] 2.1 Create EnrollmentProcessor class FIRST
        - Create semester enrollments for all active students based on their admission dates and curriculum progression
        - Calculate correct semester_number for each student using admission_date vs semester start dates  
        - Link enrollments to curriculum_version_id and semester_id
        - Set enrollment status as 'in_progress' for current semester
        - **MUST RUN BEFORE course offering creation - no enrollments = no course offerings**
        - _Requirements: Logical prerequisite for all other phases_

    - [ ] 2.2 Create CourseOfferingProcessor class (AFTER enrollments exist)
        - **ONLY** create course offerings for curriculum units that have enrolled students
        - Query enrollments table to find which curriculum units have demand before creating offerings
        - Implement logic to calculate student's current semester_number based on admission_date
        - Build processor to fetch curriculum units matching enrolled students' curriculum_version_id and semester_number
        - Create course offerings ONLY for curriculum units that have enrolled students in current semester
        - Add lecturer assignment logic with dummy data generation
        - **KEY CHANGE**: Course offering creation is driven by enrollment demand, not all curriculum units
        - _Requirements: 1.1, 1.2, 1.3, 1.4_

    - [ ] 2.3 Implement syllabus and assessment component generation
        - Create syllabus generation for course offerings without existing syllabus
        - Add check to skip syllabus creation if it already exists to prevent duplication
        - Generate assessment components with realistic weight distribution
        - Add check to skip assessment component creation if they already exist
        - Ensure total assessment weights equal 100% for each syllabus
        - _Requirements: 1.2_

    - [ ] 2.4 Add schedule and location assignment
        - Implement delivery mode assignment (in_person, online, hybrid)
        - Generate realistic schedule days and time slots
        - Assign location data for in-person courses
        - _Requirements: 1.2_

- [ ]   3. Implement Phase 2: Student Course Registration
    - [ ] 3.1 Create StudentRegistrationProcessor class
        - Build student-to-course matching based on existing enrollments and curriculum versions
        - **KEY FIX**: Only register students who have enrollments in the semester
        - Implement course registration creation for current semester
        - Add prerequisite validation logic
        - _Requirements: 2.1_

    - [ ] 3.2 Generate academic records for registrations
        - Create academic records with "in_progress" status for each registration
        - Link academic records to students, course offerings, and semesters
        - Set initial academic record fields (enrollment_date, etc.)
        - _Requirements: 2.2_

- [ ]   4. **CRITICAL FIX**: Implement Phase 3: Group Assignment for Large Courses
    - [ ] 4.1 Create unified GroupAssignmentProcessor class
        - Identify course offerings with more than 55 students
        - Implement course splitting logic with section codes (A, B, C)
        - Create duplicate course offerings for each section
        - **CRITICAL FIX**: Distribute students EVENLY across course sections using round-robin distribution instead of filling sections sequentially
        - Implement round-robin algorithm: assign student 1 to section A, student 2 to section B, student 3 to section C, student 4 to section A, etc.
        - Update course_registration.course_offering_id for reassigned students
        - Maintain enrollment balance across sections with maximum difference of 1 student between sections
        - _Requirements: 2.3_

- [ ]   5. Implement Phase 4: Class Sessions and Attendance
    - [ ] 5.1 Create ClassSessionProcessor class
        - Calculate number of sessions using syllabus total_hours / hours_per_session
        - Generate class sessions with appropriate session types (lecture, lab, tutorial)
        - Assign room_id, session_date, start_time, end_time for each session
        - _Requirements: 3.1, 3.2_

    - [ ] 5.2 Generate attendance records for class sessions
        - Create attendance records for all registered students per session
        - Implement randomized attendance status generation (present, late, excused, absent)
        - Apply realistic attendance patterns based on student profiles
        - _Requirements: 3.3_

- [ ]   6. Implement Phase 5: Assessment and Grading System
    - [ ] 6.1 Create AssessmentProcessor class
        - Generate assessment_component_details for each assessment component
        - Assign realistic due dates and weight distribution for assessment details
        - Ensure assessment detail weights sum to component weight
        - _Requirements: 4.1_

    - [ ] 6.2 Generate student assessment scores
        - Create assessment_component_detail_scores for each student and assessment
        - Simulate realistic performance with submission times, scores, lateness, bonus points
        - Apply grade distribution patterns (A: 15%, B: 35%, C: 35%, D: 10%, F: 5%)
        - _Requirements: 4.2_

    - [ ] 6.3 Calculate final grades and academic records
        - Calculate final_percentage from weighted assessment scores
        - Determine final_letter_grade and grade_points based on percentage
        - Update academic records with completion_status and final grades
        - Flag failed courses for potential retake processing
        - Optionally generate retake course_registrations directly for failed courses (auto-retake mode)
        - _Requirements: 4.3_

- [ ]   7. Implement Phase 6: GPA Calculations and Academic Standing
    - [ ] 7.1 Create GpaStandingProcessor class
        - Calculate semester GPA for each student based on completed courses
        - Calculate cumulative GPA including previous semester data
        - Generate gpa_calculations records with both semester and cumulative types
        - _Requirements: 5.1_

    - [ ] 7.2 Determine academic standings based on GPA thresholds
        - Implement GPA threshold logic (probation < 2.0, good >= 2.0, honors >= 3.5)
        - Create academic_standings records with appropriate status
        - Set effective_date, reason, and created_by fields
        - _Requirements: 5.2_

- [ ]   8. Implement additional academic lifecycle features
    - [ ] 8.1 Create retake course registrations
        - Identify failed courses from academic records
        - Generate retake course_registrations for next semester
        - Set is_retake = true and increment attempt_number
        - _Requirements: 6.1_

    - [ ] 8.2 Simulate program change requests
        - Create program_change_requests for a percentage of students
        - Generate approval workflow and reassign students to new curriculum versions
        - Update student curriculum_version_id after approval
        - _Requirements: 6.2_

    - [ ] 8.3 Generate academic holds and interventions
        - Create academic_holds for students with poor performance or other issues
        - Set hold types (financial, disciplinary, academic) with appropriate status
        - Link holds to student_id with due_date and placed_by_user_id
        - _Requirements: 6.3_

- [ ]   9. Implement graduation qualification checking
    - [ ] 9.1 Create graduation requirement validation
        - Check graduation_requirements against student academic records
        - Validate credit hour completion and GPA requirements
        - Identify students eligible for graduation
        - _Requirements: 6.3_

    - [ ] 9.2 Update student graduation status
        - Set students.status = 'graduated' for qualified students
        - Update academic_status = 'graduated' and set graduation date
        - Create graduation application records where appropriate
        - _Requirements: 6.3_

- [ ]   10. Create seeder command and configuration
    - [ ] 10.1 Build Artisan command interface
        - Create php artisan command for seeder execution
        - Add command-line options for configuration parameters
        - Support selective execution options like --phases=1,2,3 or --student-id=xxx
        - Implement progress display and error reporting
        - _Requirements: 7.1, 7.2_

    - [ ] 10.2 Add configuration file and validation
        - Create seeder configuration file with all parameters
        - Implement configuration validation and error handling
        - Add environment-specific configuration options
        - _Requirements: 7.1_

- [ ]   11. Implement data quality and validation
    - [ ] 11.1 Add data integrity checks
        - Validate referential integrity between generated records
        - Check for data consistency across related tables
        - **KEY VALIDATION**: Ensure course offerings only exist when enrollments exist
        - **KEY VALIDATION**: Ensure even distribution of students across sections (max difference of 1)
        - Implement rollback functionality for failed executions
        - Use database transactions per phase rather than single global transaction
        - _Requirements: 7.3_

    - [ ] 11.2 Create summary reporting
        - Generate execution summary with record counts by type
        - Include data quality metrics and validation results
        - Provide performance statistics and execution time breakdown
        - Report on enrollment-to-offering ratios and section balance
        - _Requirements: 7.3_

- [ ]   12. Add comprehensive testing suite
    - [ ] 12.1 Create unit tests for all processor classes
        - Test each phase processor independently with mock data
        - **PRIORITY**: Test enrollment creation logic and course offering demand calculation
        - **PRIORITY**: Test round-robin student distribution algorithm for section splitting
        - Validate data generation logic and edge cases
        - Test error handling and recovery mechanisms
        - Prioritize testing processors that involve more business logic such as GpaStandingProcessor, AssessmentProcessor, as they are more error-prone and complex
        - _Requirements: All requirements_

    - [ ] 12.2 Implement integration tests
        - Test end-to-end seeder execution with small datasets
        - **PRIORITY**: Test enrollment → course offering → registration → section splitting flow
        - Validate database integrity after seeder completion
        - Test rollback functionality and error recovery
        - _Requirements: All requirements_
