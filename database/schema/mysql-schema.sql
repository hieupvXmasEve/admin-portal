/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `academic_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_holds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `hold_type` enum('financial','academic','disciplinary','administrative','health','library') COLLATE utf8mb4_unicode_ci NOT NULL,
  `hold_category` enum('registration','graduation','transcript','all') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registration',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `amount` decimal(10,2) DEFAULT NULL,
  `priority` enum('high','medium','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` enum('active','resolved','waived','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `placed_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `placed_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_holds_placed_by_user_id_foreign` (`placed_by_user_id`),
  KEY `academic_holds_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `academic_holds_student_id_index` (`student_id`),
  KEY `academic_holds_hold_type_status_index` (`hold_type`,`status`),
  KEY `academic_holds_hold_category_index` (`hold_category`),
  CONSTRAINT `academic_holds_placed_by_user_id_foreign` FOREIGN KEY (`placed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_holds_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_holds_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `academic_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `course_offering_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  `campus_id` bigint unsigned NOT NULL,
  `final_percentage` decimal(5,2) DEFAULT NULL,
  `final_letter_grade` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade_points` decimal(3,2) DEFAULT NULL,
  `quality_points` decimal(6,2) DEFAULT NULL,
  `credit_hours` decimal(4,2) NOT NULL,
  `credit_hours_earned` decimal(4,2) NOT NULL DEFAULT '0.00',
  `grade_status` enum('in_progress','provisional','final','incomplete','withdrawn','failed','pass_no_credit','audit','transfer_credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `completion_status` enum('enrolled','completed','withdrawn','failed','incomplete','in_progress') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enrolled',
  `enrollment_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `grade_submission_date` date DEFAULT NULL,
  `grade_finalized_date` date DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `total_absences` int NOT NULL DEFAULT '0',
  `total_class_sessions` int DEFAULT NULL,
  `meets_attendance_requirement` tinyint(1) NOT NULL DEFAULT '1',
  `is_repeat_course` tinyint(1) NOT NULL DEFAULT '0',
  `attempt_number` int NOT NULL DEFAULT '1',
  `original_record_id` bigint unsigned DEFAULT NULL,
  `is_transfer_credit` tinyint(1) NOT NULL DEFAULT '0',
  `transfer_institution` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_course_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_course_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_advanced_placement` tinyint(1) NOT NULL DEFAULT '0',
  `is_challenge_exam` tinyint(1) NOT NULL DEFAULT '0',
  `is_credit_by_exam` tinyint(1) NOT NULL DEFAULT '0',
  `grade_breakdown` json DEFAULT NULL,
  `raw_percentage` decimal(5,2) DEFAULT NULL,
  `curve_adjustment` decimal(5,2) NOT NULL DEFAULT '0.00',
  `grade_adjustment_reason` text COLLATE utf8mb4_unicode_ci,
  `excluded_from_gpa` tinyint(1) NOT NULL DEFAULT '0',
  `gpa_exclusion_reason` text COLLATE utf8mb4_unicode_ci,
  `instructor_comments` text COLLATE utf8mb4_unicode_ci,
  `administrative_notes` text COLLATE utf8mb4_unicode_ci,
  `instructor_id` bigint unsigned DEFAULT NULL,
  `grade_submitted_by_lecture_id` bigint unsigned DEFAULT NULL,
  `grade_approved_by_lecture_id` bigint unsigned DEFAULT NULL,
  `affects_academic_standing` tinyint(1) NOT NULL DEFAULT '1',
  `affects_graduation_requirement` tinyint(1) NOT NULL DEFAULT '1',
  `satisfies_prerequisite` tinyint(1) NOT NULL DEFAULT '1',
  `grade_history` json DEFAULT NULL,
  `last_grade_change_at` timestamp NULL DEFAULT NULL,
  `last_changed_by_lecture_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_course_offering` (`student_id`,`course_offering_id`),
  KEY `academic_records_course_offering_id_foreign` (`course_offering_id`),
  KEY `academic_records_original_record_id_foreign` (`original_record_id`),
  KEY `academic_records_grade_submitted_by_lecture_id_foreign` (`grade_submitted_by_lecture_id`),
  KEY `academic_records_grade_approved_by_lecture_id_foreign` (`grade_approved_by_lecture_id`),
  KEY `academic_records_last_changed_by_lecture_id_foreign` (`last_changed_by_lecture_id`),
  KEY `student_semester_idx` (`student_id`,`semester_id`),
  KEY `student_grade_completion_idx` (`student_id`,`grade_status`,`completion_status`),
  KEY `student_gpa_calc_idx` (`student_id`,`excluded_from_gpa`,`grade_points`),
  KEY `unit_semester_grade_idx` (`unit_id`,`semester_id`,`grade_status`),
  KEY `program_semester_completion_idx` (`program_id`,`semester_id`,`completion_status`),
  KEY `campus_semester_grade_idx` (`campus_id`,`semester_id`,`grade_status`),
  KEY `instructor_semester_grade_idx` (`instructor_id`,`semester_id`,`grade_status`),
  KEY `repeat_attempt_idx` (`is_repeat_course`,`attempt_number`),
  KEY `transfer_grade_idx` (`is_transfer_credit`,`grade_status`),
  KEY `finalized_date_idx` (`grade_finalized_date`,`grade_status`),
  KEY `enrollment_completion_dates_idx` (`enrollment_date`,`completion_date`),
  KEY `transcript_calc_idx` (`student_id`,`completion_status`,`grade_finalized_date`,`credit_hours`),
  KEY `gpa_calc_idx` (`student_id`,`excluded_from_gpa`,`quality_points`,`credit_hours`),
  KEY `student_final_grades_idx` (`student_id`,`grade_finalized_date`,`final_letter_grade`),
  KEY `semester_unit_completion_idx` (`semester_id`,`unit_id`,`completion_status`),
  CONSTRAINT `academic_records_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `academic_records_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_records_grade_approved_by_lecture_id_foreign` FOREIGN KEY (`grade_approved_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_grade_submitted_by_lecture_id_foreign` FOREIGN KEY (`grade_submitted_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_instructor_id_foreign` FOREIGN KEY (`instructor_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_last_changed_by_lecture_id_foreign` FOREIGN KEY (`last_changed_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_original_record_id_foreign` FOREIGN KEY (`original_record_id`) REFERENCES `academic_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `academic_records_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `academic_records_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_records_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `check_records_attempt_number_positive` CHECK ((`attempt_number` >= 1)),
  CONSTRAINT `check_records_attendance_percentage` CHECK (((`attendance_percentage` is null) or ((`attendance_percentage` >= 0) and (`attendance_percentage` <= 100)))),
  CONSTRAINT `check_records_credit_hours_earned` CHECK (((`credit_hours_earned` >= 0) and (`credit_hours_earned` <= `credit_hours`))),
  CONSTRAINT `check_records_credit_hours_positive` CHECK ((`credit_hours` > 0)),
  CONSTRAINT `check_records_curve_adjustment` CHECK (((`curve_adjustment` >= -(100)) and (`curve_adjustment` <= 100))),
  CONSTRAINT `check_records_final_percentage` CHECK (((`final_percentage` is null) or ((`final_percentage` >= 0) and (`final_percentage` <= 100)))),
  CONSTRAINT `check_records_grade_points` CHECK (((`grade_points` is null) or ((`grade_points` >= 0) and (`grade_points` <= 4)))),
  CONSTRAINT `check_records_quality_points` CHECK (((`quality_points` is null) or (`quality_points` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_component_detail_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_component_detail_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_component_detail_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `course_offering_id` bigint unsigned NOT NULL,
  `graded_by_lecture_id` bigint unsigned DEFAULT NULL,
  `points_earned` decimal(8,2) DEFAULT NULL,
  `percentage_score` decimal(5,2) DEFAULT NULL,
  `letter_grade` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gpa_points` decimal(3,2) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `submission_attempt` int NOT NULL DEFAULT '1',
  `submission_files` json DEFAULT NULL,
  `submission_text` longtext COLLATE utf8mb4_unicode_ci,
  `submission_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `minutes_late` int NOT NULL DEFAULT '0',
  `late_penalty_applied` decimal(5,2) NOT NULL DEFAULT '0.00',
  `late_excuse` text COLLATE utf8mb4_unicode_ci,
  `late_excuse_approved` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('not_submitted','submitted','grading','graded','returned','resubmit_required','excused','incomplete','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_submitted',
  `score_status` enum('draft','provisional','final','disputed','under_review') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `instructor_feedback` longtext COLLATE utf8mb4_unicode_ci,
  `private_notes` longtext COLLATE utf8mb4_unicode_ci,
  `rubric_scores` json DEFAULT NULL,
  `bonus_points` decimal(8,2) NOT NULL DEFAULT '0.00',
  `bonus_reason` text COLLATE utf8mb4_unicode_ci,
  `plagiarism_suspected` tinyint(1) NOT NULL DEFAULT '0',
  `plagiarism_score` decimal(5,2) DEFAULT NULL,
  `plagiarism_notes` text COLLATE utf8mb4_unicode_ci,
  `integrity_status` enum('clear','under_investigation','violation_confirmed','violation_minor','violation_major') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'clear',
  `score_history` json DEFAULT NULL,
  `last_modified_at` timestamp NULL DEFAULT NULL,
  `last_modified_by_lecture_id` bigint unsigned DEFAULT NULL,
  `is_extra_credit` tinyint(1) NOT NULL DEFAULT '0',
  `is_makeup` tinyint(1) NOT NULL DEFAULT '0',
  `special_circumstances` text COLLATE utf8mb4_unicode_ci,
  `score_excluded` tinyint(1) NOT NULL DEFAULT '0',
  `exclusion_reason` text COLLATE utf8mb4_unicode_ci,
  `student_comments` text COLLATE utf8mb4_unicode_ci,
  `appeal_requested` tinyint(1) NOT NULL DEFAULT '0',
  `appeal_requested_at` timestamp NULL DEFAULT NULL,
  `appeal_reason` text COLLATE utf8mb4_unicode_ci,
  `appeal_status` enum('none','pending','under_review','approved','denied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_detail_student_course_attempt` (`assessment_component_detail_id`,`student_id`,`course_offering_id`,`submission_attempt`),
  KEY `detail_scores_modified_by_fk` (`last_modified_by_lecture_id`),
  KEY `detail_student_idx` (`assessment_component_detail_id`,`student_id`),
  KEY `student_status_idx` (`student_id`,`status`),
  KEY `student_score_status_idx` (`student_id`,`score_status`),
  KEY `course_status_idx` (`course_offering_id`,`status`),
  KEY `graded_by_date_idx` (`graded_by_lecture_id`,`graded_at`),
  KEY `submitted_status_idx` (`submitted_at`,`status`),
  KEY `late_penalty_idx` (`is_late`,`late_penalty_applied`),
  KEY `plagiarism_integrity_idx` (`plagiarism_suspected`,`integrity_status`),
  KEY `appeal_status_idx` (`appeal_requested`,`appeal_status`),
  KEY `excluded_extra_idx` (`score_excluded`,`is_extra_credit`),
  KEY `student_gpa_calc_idx` (`student_id`,`score_status`,`score_excluded`,`gpa_points`),
  KEY `student_transcript_idx` (`student_id`,`graded_at`,`score_status`),
  KEY `student_score_timeline_idx` (`student_id`,`graded_at`,`score_status`,`points_earned`),
  KEY `graded_by_lecture_idx` (`graded_by_lecture_id`,`graded_at`),
  CONSTRAINT `assessment_component_detail_scores_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_component_detail_scores_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_scores_detail_id_fk` FOREIGN KEY (`assessment_component_detail_id`) REFERENCES `assessment_component_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_scores_graded_by_fk` FOREIGN KEY (`graded_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_scores_modified_by_fk` FOREIGN KEY (`last_modified_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_scores_gpa_points` CHECK (((`gpa_points` is null) or ((`gpa_points` >= 0) and (`gpa_points` <= 4)))),
  CONSTRAINT `check_scores_late_penalty_applied` CHECK (((`late_penalty_applied` >= 0) and (`late_penalty_applied` <= 100))),
  CONSTRAINT `check_scores_minutes_late_positive` CHECK ((`minutes_late` >= 0)),
  CONSTRAINT `check_scores_percentage_score` CHECK (((`percentage_score` is null) or ((`percentage_score` >= 0) and (`percentage_score` <= 100)))),
  CONSTRAINT `check_scores_plagiarism_score` CHECK (((`plagiarism_score` is null) or ((`plagiarism_score` >= 0) and (`plagiarism_score` <= 100)))),
  CONSTRAINT `check_scores_submission_attempt_positive` CHECK ((`submission_attempt` >= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_component_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_component_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `component_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assessment_component_details_component_id_foreign` (`component_id`),
  CONSTRAINT `assessment_component_details_component_id_foreign` FOREIGN KEY (`component_id`) REFERENCES `assessment_components` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_components` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `syllabus_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `weight` decimal(5,2) DEFAULT NULL,
  `type` enum('quiz','assignment','project','exam','online_activity','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required_to_sit_final_exam` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `available_from` datetime DEFAULT NULL,
  `late_submission_deadline` datetime DEFAULT NULL,
  `late_penalty_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `late_penalty_type` enum('per_day','per_hour','fixed','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `submission_type` enum('online','in_person','both','no_submission') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `allowed_file_types` json DEFAULT NULL,
  `max_file_size_mb` int DEFAULT NULL,
  `max_submissions` int NOT NULL DEFAULT '1',
  `allow_resubmission` tinyint(1) NOT NULL DEFAULT '0',
  `is_group_work` tinyint(1) NOT NULL DEFAULT '0',
  `min_group_size` int DEFAULT NULL,
  `max_group_size` int DEFAULT NULL,
  `students_form_groups` tinyint(1) NOT NULL DEFAULT '1',
  `assessment_criteria` json DEFAULT NULL,
  `grading_instructions` text COLLATE utf8mb4_unicode_ci,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `scores_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_extra_credit` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('draft','published','in_progress','grading','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `sort_order` int NOT NULL DEFAULT '0',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_syllabus_component_code` (`syllabus_id`,`code`),
  KEY `syllabus_type_idx` (`syllabus_id`,`type`),
  KEY `syllabus_sort_order_idx` (`syllabus_id`,`sort_order`),
  KEY `assessment_components_due_date_status_index` (`due_date`,`status`),
  KEY `assessment_components_is_published_status_index` (`is_published`,`status`),
  KEY `assessment_components_is_group_work_type_index` (`is_group_work`,`type`),
  KEY `assessment_components_category_sort_order_index` (`category`,`sort_order`),
  FULLTEXT KEY `name` (`name`,`description`,`grading_instructions`),
  CONSTRAINT `assessment_components_syllabus_id_foreign` FOREIGN KEY (`syllabus_id`) REFERENCES `syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `check_file_size_positive` CHECK (((`max_file_size_mb` is null) or (`max_file_size_mb` > 0))),
  CONSTRAINT `check_group_size_valid` CHECK ((((`min_group_size` is null) or (`min_group_size` >= 1)) and ((`max_group_size` is null) or (`max_group_size` >= 1)) and ((`min_group_size` is null) or (`max_group_size` is null) or (`min_group_size` <= `max_group_size`)))),
  CONSTRAINT `check_late_penalty_percentage` CHECK (((`late_penalty_percentage` >= 0) and (`late_penalty_percentage` <= 100))),
  CONSTRAINT `check_max_submissions_positive` CHECK ((`max_submissions` >= 1)),
  CONSTRAINT `check_weight_percentage` CHECK (((`weight` >= 0) and (`weight` <= 100)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `class_session_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `recorded_by_lecture_id` bigint unsigned DEFAULT NULL,
  `status` enum('present','absent','late','excused','partial','medical_leave','official_leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'absent',
  `check_in_time` timestamp NULL DEFAULT NULL,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `minutes_late` int NOT NULL DEFAULT '0',
  `minutes_present` int DEFAULT NULL,
  `recording_method` enum('manual','qr_code','rfid','biometric','mobile_app','online_participation','auto_system') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `excuse_reason` text COLLATE utf8mb4_unicode_ci,
  `excuse_document_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `participation_level` enum('excellent','good','average','poor','none') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `participation_score` decimal(3,1) DEFAULT NULL,
  `participation_notes` text COLLATE utf8mb4_unicode_ci,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `affects_grade` tinyint(1) NOT NULL DEFAULT '1',
  `is_makeup_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_lecture_id` bigint unsigned DEFAULT NULL,
  `batch_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_info` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session_student_attendance` (`class_session_id`,`student_id`),
  KEY `attendances_verified_by_lecture_id_foreign` (`verified_by_lecture_id`),
  KEY `session_attendance_status_idx` (`class_session_id`,`status`),
  KEY `student_attendance_status_idx` (`student_id`,`status`),
  KEY `session_student_idx` (`class_session_id`,`student_id`),
  KEY `attendances_recorded_by_lecture_id_created_at_index` (`recorded_by_lecture_id`,`created_at`),
  KEY `attendances_recording_method_created_at_index` (`recording_method`,`created_at`),
  KEY `attendances_status_affects_grade_index` (`status`,`affects_grade`),
  KEY `attendances_is_verified_verified_at_index` (`is_verified`,`verified_at`),
  KEY `attendances_batch_id_index` (`batch_id`),
  KEY `attendances_check_in_time_check_out_time_index` (`check_in_time`,`check_out_time`),
  KEY `student_grade_attendance_idx` (`student_id`,`status`,`affects_grade`,`created_at`),
  KEY `student_attendance_timeline_idx` (`student_id`,`check_in_time`,`status`),
  KEY `recorded_by_lecture_idx` (`recorded_by_lecture_id`,`created_at`),
  CONSTRAINT `attendances_class_session_id_foreign` FOREIGN KEY (`class_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_recorded_by_lecture_id_foreign` FOREIGN KEY (`recorded_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_verified_by_lecture_id_foreign` FOREIGN KEY (`verified_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_attendances_minutes_late_positive` CHECK ((`minutes_late` >= 0)),
  CONSTRAINT `check_attendances_minutes_present_positive` CHECK (((`minutes_present` is null) or (`minutes_present` >= 0))),
  CONSTRAINT `check_attendances_participation_score` CHECK (((`participation_score` is null) or ((`participation_score` >= 0) and (`participation_score` <= 10))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buildings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buildings_code_unique` (`code`),
  KEY `buildings_campus_id_foreign` (`campus_id`),
  CONSTRAINT `buildings_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campus_user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campus_user_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `campus_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campus_user_roles_user_id_foreign` (`user_id`),
  KEY `campus_user_roles_campus_id_foreign` (`campus_id`),
  KEY `campus_user_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `campus_user_roles_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campus_user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campus_user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campuses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_offering_id` bigint unsigned NOT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `room_booking_id` bigint unsigned DEFAULT NULL,
  `instructor_id` bigint unsigned DEFAULT NULL,
  `session_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_description` text COLLATE utf8mb4_unicode_ci,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_minutes` int DEFAULT NULL,
  `session_type` enum('lecture','tutorial','practical','laboratory','seminar','workshop','exam','assessment','field_trip','guest_lecture','review','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lecture',
  `delivery_mode` enum('in_person','online','hybrid','blended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_person',
  `status` enum('scheduled','in_progress','completed','cancelled','postponed','moved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `online_meeting_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meeting_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meeting_password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `learning_objectives` json DEFAULT NULL,
  `required_materials` json DEFAULT NULL,
  `topics_covered` json DEFAULT NULL,
  `attendance_required` tinyint(1) NOT NULL DEFAULT '1',
  `attendance_tracking_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `expected_attendees` int DEFAULT NULL,
  `actual_attendees` int DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `is_assessment` tinyint(1) NOT NULL DEFAULT '0',
  `assessment_weight` decimal(5,2) DEFAULT NULL,
  `assessment_duration_minutes` int DEFAULT NULL,
  `assessment_materials_allowed` json DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0',
  `parent_session_id` bigint unsigned DEFAULT NULL,
  `sequence_number` int DEFAULT NULL,
  `instructor_notes` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `student_instructions` text COLLATE utf8mb4_unicode_ci,
  `cancellation_reason` text COLLATE utf8mb4_unicode_ci,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_co_sequence` (`course_offering_id`,`sequence_number`),
  KEY `class_sessions_room_booking_id_foreign` (`room_booking_id`),
  KEY `class_sessions_parent_session_id_foreign` (`parent_session_id`),
  KEY `co_session_schedule_idx` (`course_offering_id`,`session_date`,`start_time`),
  KEY `room_session_conflict_idx` (`room_id`,`session_date`,`start_time`,`end_time`),
  KEY `instructor_schedule_idx` (`instructor_id`,`session_date`,`start_time`),
  KEY `class_sessions_session_type_status_index` (`session_type`,`status`),
  KEY `class_sessions_delivery_mode_session_date_index` (`delivery_mode`,`session_date`),
  KEY `class_sessions_is_assessment_session_date_index` (`is_assessment`,`session_date`),
  KEY `attendance_config_idx` (`attendance_required`,`attendance_tracking_enabled`),
  KEY `class_sessions_is_recurring_parent_session_id_index` (`is_recurring`,`parent_session_id`),
  KEY `class_sessions_sequence_number_index` (`sequence_number`),
  KEY `daily_room_schedule_idx` (`session_date`,`room_id`,`start_time`,`end_time`),
  KEY `instructor_daily_schedule_idx` (`instructor_id`,`session_date`,`session_type`),
  FULLTEXT KEY `session_title` (`session_title`,`session_description`,`student_instructions`),
  CONSTRAINT `class_sessions_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_sessions_instructor_id_foreign` FOREIGN KEY (`instructor_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_sessions_parent_session_id_foreign` FOREIGN KEY (`parent_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_sessions_room_booking_id_foreign` FOREIGN KEY (`room_booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_sessions_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_sessions_actual_attendees_valid` CHECK (((`actual_attendees` is null) or (`actual_attendees` >= 0))),
  CONSTRAINT `check_sessions_assessment_weight` CHECK (((`assessment_weight` is null) or ((`assessment_weight` >= 0) and (`assessment_weight` <= 100)))),
  CONSTRAINT `check_sessions_attendance_percentage` CHECK (((`attendance_percentage` is null) or ((`attendance_percentage` >= 0) and (`attendance_percentage` <= 100)))),
  CONSTRAINT `check_sessions_expected_attendees_positive` CHECK (((`expected_attendees` is null) or (`expected_attendees` > 0))),
  CONSTRAINT `check_sessions_times` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_offerings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_offerings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `semester_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `lecture_id` bigint unsigned DEFAULT NULL,
  `section_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_capacity` int NOT NULL DEFAULT '1000',
  `current_enrollment` int NOT NULL DEFAULT '0',
  `waitlist_capacity` int NOT NULL DEFAULT '10',
  `current_waitlist` int NOT NULL DEFAULT '0',
  `delivery_mode` enum('in_person','online','hybrid','blended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_person',
  `schedule_days` json DEFAULT NULL,
  `schedule_time_start` time DEFAULT NULL,
  `schedule_time_end` time DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `enrollment_status` enum('open','closed','waitlist_only','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `registration_start_date` date DEFAULT NULL,
  `registration_end_date` date DEFAULT NULL,
  `special_requirements` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_semester_unit_section` (`semester_id`,`unit_id`,`section_code`),
  KEY `course_offerings_unit_id_foreign` (`unit_id`),
  KEY `course_offerings_semester_id_unit_id_index` (`semester_id`,`unit_id`),
  KEY `course_offerings_lecture_id_semester_id_index` (`lecture_id`,`semester_id`),
  KEY `course_offerings_is_active_enrollment_status_index` (`is_active`,`enrollment_status`),
  KEY `course_offerings_delivery_mode_index` (`delivery_mode`),
  KEY `course_offerings_current_enrollment_max_capacity_index` (`current_enrollment`,`max_capacity`),
  KEY `co_registration_dates_idx` (`registration_start_date`,`registration_end_date`),
  CONSTRAINT `course_offerings_lecture_id_foreign` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_offerings_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_offerings_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `course_offering_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `registration_status` enum('registered','confirmed','dropped','withdrawn','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registered',
  `registration_date` timestamp NOT NULL,
  `registration_method` enum('online','advisor','admin_override') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `credit_hours` decimal(4,2) NOT NULL,
  `final_grade` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grade_points` decimal(3,2) DEFAULT NULL,
  `attempt_number` int NOT NULL DEFAULT '1',
  `is_retake` tinyint(1) NOT NULL DEFAULT '0',
  `drop_date` timestamp NULL DEFAULT NULL,
  `withdrawal_date` timestamp NULL DEFAULT NULL,
  `completion_date` timestamp NULL DEFAULT NULL,
  `retake_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_retake_paid` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_course_semester` (`student_id`,`course_offering_id`,`semester_id`),
  KEY `course_registrations_student_id_index` (`student_id`),
  KEY `course_registrations_course_offering_id_index` (`course_offering_id`),
  KEY `course_registrations_semester_id_index` (`semester_id`),
  KEY `course_registrations_registration_status_index` (`registration_status`),
  CONSTRAINT `course_registrations_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_registrations_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_registrations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `curriculum_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `curriculum_version_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned DEFAULT NULL,
  `type` enum('core','major','elective') COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_scope` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'program' COMMENT 'Scope of the unit: program, common, specialization_specific, cross_program',
  `year_level` tinyint unsigned DEFAULT NULL COMMENT 'Academic year level (1-4)',
  `semester_number` tinyint unsigned DEFAULT NULL COMMENT 'Suggested semester number within the course (1-12)',
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `minimum_grade` decimal(4,2) DEFAULT NULL COMMENT 'Minimum grade required for completion (optional)',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `curriculum_unit_unique` (`curriculum_version_id`,`unit_id`),
  KEY `curriculum_units_unit_id_foreign` (`unit_id`),
  KEY `curriculum_units_semester_id_foreign` (`semester_id`),
  KEY `curriculum_units_curriculum_version_id_type_index` (`curriculum_version_id`,`type`),
  KEY `curriculum_units_year_level_semester_number_index` (`year_level`,`semester_number`),
  CONSTRAINT `curriculum_units_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `curriculum_units_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `curriculum_units_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `curriculum_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint unsigned NOT NULL,
  `specialization_id` bigint unsigned DEFAULT NULL,
  `version_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_versions_specialization_id_foreign` (`specialization_id`),
  KEY `curriculum_versions_semester_id_foreign` (`semester_id`),
  KEY `curriculum_versions_program_id_specialization_id_index` (`program_id`,`specialization_id`),
  CONSTRAINT `curriculum_versions_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `curriculum_versions_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`),
  CONSTRAINT `curriculum_versions_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned NOT NULL,
  `curriculum_version_id` bigint unsigned NOT NULL,
  `semester_number` tinyint unsigned NOT NULL,
  `status` enum('in_progress','completed','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_semester_enrollment` (`student_id`,`semester_id`),
  KEY `enrollments_student_id_semester_id_index` (`student_id`,`semester_id`),
  KEY `enrollments_semester_id_status_index` (`semester_id`,`status`),
  KEY `enrollments_curriculum_version_id_semester_number_index` (`curriculum_version_id`,`semester_number`),
  CONSTRAINT `enrollments_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equivalent_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equivalent_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint unsigned NOT NULL,
  `equivalent_unit_id` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equivalent_units_unit_id_equivalent_unit_id_unique` (`unit_id`,`equivalent_unit_id`),
  KEY `equivalent_units_equivalent_unit_id_foreign` (`equivalent_unit_id`),
  CONSTRAINT `equivalent_units_equivalent_unit_id_foreign` FOREIGN KEY (`equivalent_unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `equivalent_units_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gpa_calculations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gpa_calculations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned NOT NULL,
  `semester_id` bigint unsigned DEFAULT NULL,
  `program_id` bigint unsigned DEFAULT NULL,
  `calculation_type` enum('semester','cumulative','major','program','year','transfer','institutional') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'semester',
  `gpa` decimal(4,3) DEFAULT NULL,
  `quality_points` decimal(8,3) NOT NULL DEFAULT '0.000',
  `credit_hours_attempted` decimal(6,2) NOT NULL DEFAULT '0.00',
  `credit_hours_earned` decimal(6,2) NOT NULL DEFAULT '0.00',
  `credit_hours_gpa` decimal(6,2) NOT NULL DEFAULT '0.00',
  `total_courses` int NOT NULL DEFAULT '0',
  `completed_courses` int NOT NULL DEFAULT '0',
  `failed_courses` int NOT NULL DEFAULT '0',
  `withdrawn_courses` int NOT NULL DEFAULT '0',
  `incomplete_courses` int NOT NULL DEFAULT '0',
  `a_grades` int NOT NULL DEFAULT '0',
  `b_grades` int NOT NULL DEFAULT '0',
  `c_grades` int NOT NULL DEFAULT '0',
  `d_grades` int NOT NULL DEFAULT '0',
  `f_grades` int NOT NULL DEFAULT '0',
  `academic_standing` enum('excellent','good','satisfactory','probation','suspension','dismissal','warning') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required_gpa` decimal(3,2) DEFAULT NULL,
  `meets_gpa_requirement` tinyint(1) NOT NULL DEFAULT '1',
  `gpa_deficit` decimal(4,3) DEFAULT NULL,
  `academic_year` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_level` int DEFAULT NULL,
  `semester_type` enum('fall','spring','summer','winter') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_rank` int DEFAULT NULL,
  `class_size` int DEFAULT NULL,
  `percentile` decimal(5,2) DEFAULT NULL,
  `program_rank` int DEFAULT NULL,
  `program_class_size` int DEFAULT NULL,
  `credits_needed_to_graduate` decimal(6,2) DEFAULT NULL,
  `completion_percentage` decimal(5,2) DEFAULT NULL,
  `projected_graduation_date` date DEFAULT NULL,
  `on_track_to_graduate` tinyint(1) NOT NULL DEFAULT '1',
  `includes_transfer_credits` tinyint(1) NOT NULL DEFAULT '0',
  `includes_repeated_courses` tinyint(1) NOT NULL DEFAULT '0',
  `dean_list_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `honors_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `graduation_honors_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `calculated_at` timestamp NOT NULL,
  `calculated_by_lecture_id` bigint unsigned DEFAULT NULL,
  `calculation_parameters` json DEFAULT NULL,
  `calculation_notes` text COLLATE utf8mb4_unicode_ci,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_lecture_id` bigint unsigned DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `previous_gpa` decimal(4,3) DEFAULT NULL,
  `gpa_change` decimal(4,3) DEFAULT NULL,
  `gpa_trend` enum('improving','declining','stable') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_semester_calc_type` (`student_id`,`semester_id`,`calculation_type`),
  KEY `gpa_calculations_semester_id_foreign` (`semester_id`),
  KEY `gpa_calculations_calculated_by_lecture_id_foreign` (`calculated_by_lecture_id`),
  KEY `gpa_calculations_verified_by_lecture_id_foreign` (`verified_by_lecture_id`),
  KEY `student_calc_type_current_idx` (`student_id`,`calculation_type`,`is_current`),
  KEY `student_semester_calc_idx` (`student_id`,`semester_id`,`calculation_type`),
  KEY `student_year_calc_idx` (`student_id`,`academic_year`,`calculation_type`),
  KEY `program_semester_gpa_idx` (`program_id`,`semester_id`,`gpa`),
  KEY `standing_gpa_idx` (`academic_standing`,`gpa`),
  KEY `dean_list_gpa_idx` (`dean_list_eligible`,`gpa`),
  KEY `class_ranking_idx` (`class_rank`,`class_size`),
  KEY `program_ranking_idx` (`program_rank`,`program_class_size`),
  KEY `calc_date_current_idx` (`calculated_at`,`is_current`),
  KEY `verification_idx` (`is_verified`,`verified_at`),
  KEY `completion_track_idx` (`completion_percentage`,`on_track_to_graduate`),
  KEY `student_calc_semester_current_idx` (`student_id`,`calculation_type`,`semester_id`,`is_current`),
  KEY `student_progress_idx` (`student_id`,`gpa`,`credit_hours_earned`,`is_current`),
  CONSTRAINT `gpa_calculations_calculated_by_lecture_id_foreign` FOREIGN KEY (`calculated_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gpa_calculations_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gpa_calculations_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gpa_calculations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gpa_calculations_verified_by_lecture_id_foreign` FOREIGN KEY (`verified_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_completion_percentage` CHECK (((`completion_percentage` is null) or ((`completion_percentage` >= 0) and (`completion_percentage` <= 100)))),
  CONSTRAINT `check_course_counts` CHECK (((`total_courses` >= 0) and (`completed_courses` >= 0) and (`failed_courses` >= 0) and (`withdrawn_courses` >= 0) and (`incomplete_courses` >= 0) and ((((`completed_courses` + `failed_courses`) + `withdrawn_courses`) + `incomplete_courses`) <= `total_courses`))),
  CONSTRAINT `check_credit_hours_positive` CHECK (((`credit_hours_attempted` >= 0) and (`credit_hours_earned` >= 0) and (`credit_hours_gpa` >= 0) and (`credit_hours_earned` <= `credit_hours_attempted`))),
  CONSTRAINT `check_gpa_valid` CHECK (((`gpa` is null) or ((`gpa` >= 0) and (`gpa` <= 4)))),
  CONSTRAINT `check_grade_counts` CHECK (((`a_grades` >= 0) and (`b_grades` >= 0) and (`c_grades` >= 0) and (`d_grades` >= 0) and (`f_grades` >= 0))),
  CONSTRAINT `check_percentile` CHECK (((`percentile` is null) or ((`percentile` >= 0) and (`percentile` <= 100)))),
  CONSTRAINT `check_quality_points_positive` CHECK ((`quality_points` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `graduation_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `graduation_requirements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint unsigned NOT NULL,
  `specialization_id` bigint unsigned DEFAULT NULL,
  `total_credits_required` decimal(5,2) NOT NULL,
  `core_credits_required` decimal(5,2) NOT NULL DEFAULT '0.00',
  `major_credits_required` decimal(5,2) NOT NULL DEFAULT '0.00',
  `elective_credits_required` decimal(5,2) NOT NULL DEFAULT '0.00',
  `minimum_gpa` decimal(3,2) NOT NULL DEFAULT '2.00',
  `minimum_major_gpa` decimal(3,2) NOT NULL DEFAULT '2.00',
  `maximum_study_years` int NOT NULL DEFAULT '6',
  `required_internship` tinyint(1) NOT NULL DEFAULT '0',
  `required_thesis` tinyint(1) NOT NULL DEFAULT '0',
  `required_english_certification` tinyint(1) NOT NULL DEFAULT '0',
  `special_requirements` json DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `graduation_requirements_specialization_id_foreign` (`specialization_id`),
  KEY `graduation_requirements_program_id_specialization_id_index` (`program_id`,`specialization_id`),
  KEY `grad_req_active_dates_idx` (`is_active`,`effective_from`,`effective_to`),
  CONSTRAINT `graduation_requirements_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `graduation_requirements_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lectures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lectures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campus_id` bigint unsigned NOT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faculty` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expertise_areas` json DEFAULT NULL,
  `academic_rank` enum('lecturer','senior_lecturer','associate_professor','professor','emeritus_professor','visiting_lecturer','adjunct_professor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lecturer',
  `highest_degree` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `degree_field` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alma_mater` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graduation_year` year DEFAULT NULL,
  `hire_date` date NOT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contract','visiting','emeritus') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_time',
  `employment_status` enum('active','on_leave','sabbatical','retired','terminated','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `preferred_teaching_days` json DEFAULT NULL,
  `preferred_start_time` time DEFAULT NULL,
  `preferred_end_time` time DEFAULT NULL,
  `max_teaching_hours_per_week` int NOT NULL DEFAULT '40',
  `teaching_modalities` json DEFAULT NULL,
  `office_address` text COLLATE utf8mb4_unicode_ci,
  `office_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` text COLLATE utf8mb4_unicode_ci,
  `emergency_contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `certifications` json DEFAULT NULL,
  `languages` json DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `can_teach_online` tinyint(1) NOT NULL DEFAULT '1',
  `is_available_for_assignment` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lectures_employee_id_unique` (`employee_id`),
  UNIQUE KEY `lectures_email_unique` (`email`),
  KEY `lectures_campus_id_is_active_index` (`campus_id`,`is_active`),
  KEY `lectures_employment_status_is_active_index` (`employment_status`,`is_active`),
  KEY `lectures_academic_rank_specialization_index` (`academic_rank`,`specialization`),
  KEY `lectures_hire_date_index` (`hire_date`),
  KEY `lectures_email_index` (`email`),
  KEY `lectures_employee_id_index` (`employee_id`),
  KEY `lectures_last_name_first_name_index` (`last_name`,`first_name`),
  KEY `lectures_department_faculty_index` (`department`,`faculty`),
  KEY `lectures_is_available_for_assignment_employment_status_index` (`is_available_for_assignment`,`employment_status`),
  CONSTRAINT `lectures_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  UNIQUE KEY `permissions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_permissions_role_id_foreign` (`role_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bitwise_permissions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`),
  UNIQUE KEY `roles_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `room_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `booked_by_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booked_by_id` bigint unsigned NOT NULL,
  `approved_by_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by_id` bigint unsigned DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `booking_type` enum('class','exam','meeting','event','maintenance','personal_study','workshop','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'meeting',
  `status` enum('pending','approved','rejected','cancelled','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0',
  `recurrence_type` enum('daily','weekly','biweekly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `recurrence_days` json DEFAULT NULL,
  `parent_booking_id` bigint unsigned DEFAULT NULL,
  `required_equipment` json DEFAULT NULL,
  `setup_requirements` json DEFAULT NULL,
  `special_requirements` text COLLATE utf8mb4_unicode_ci,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `send_reminders` tinyint(1) NOT NULL DEFAULT '1',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_bookings_parent_booking_id_foreign` (`parent_booking_id`),
  KEY `room_time_conflict_idx` (`room_id`,`booking_date`,`start_time`,`end_time`),
  KEY `room_bookings_booked_by_idx` (`booked_by_type`,`booked_by_id`),
  KEY `room_bookings_approved_by_idx` (`approved_by_type`,`approved_by_id`),
  KEY `room_bookings_status_booking_date_index` (`status`,`booking_date`),
  KEY `room_bookings_booking_type_status_index` (`booking_type`,`status`),
  KEY `room_bookings_is_recurring_parent_booking_id_index` (`is_recurring`,`parent_booking_id`),
  KEY `room_bookings_priority_status_index` (`priority`,`status`),
  FULLTEXT KEY `title` (`title`,`description`,`special_requirements`),
  CONSTRAINT `room_bookings_parent_booking_id_foreign` FOREIGN KEY (`parent_booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `room_bookings_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `check_booking_times` CHECK ((`start_time` < `end_time`)),
  CONSTRAINT `check_recurrence_end_date` CHECK (((`recurrence_end_date` is null) or (`recurrence_end_date` >= `booking_date`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `building` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('classroom','laboratory','computer_lab','auditorium','meeting_room','library','study_room','workshop','office','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'classroom',
  `capacity` int NOT NULL DEFAULT '1',
  `status` enum('available','occupied','maintenance','out_of_service','reserved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `is_bookable` tinyint(1) NOT NULL DEFAULT '1',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `available_from` time NOT NULL DEFAULT '07:00:00',
  `available_until` time NOT NULL DEFAULT '18:00:00',
  `blocked_days` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `usage_guidelines` text COLLATE utf8mb4_unicode_ci,
  `booking_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campus_room_code` (`campus_id`,`code`),
  UNIQUE KEY `rooms_code_unique` (`code`),
  KEY `rooms_campus_id_type_index` (`campus_id`,`type`),
  KEY `rooms_status_is_bookable_index` (`status`,`is_bookable`),
  KEY `rooms_capacity_index` (`capacity`),
  KEY `rooms_building_floor_index` (`building`,`floor`),
  KEY `rooms_available_from_available_until_index` (`available_from`,`available_until`),
  FULLTEXT KEY `description` (`description`,`usage_guidelines`,`booking_notes`),
  CONSTRAINT `rooms_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `check_available_times` CHECK ((`available_from` < `available_until`)),
  CONSTRAINT `check_capacity_positive` CHECK ((`capacity` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `semesters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `semesters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `enrollment_start_date` datetime DEFAULT NULL,
  `enrollment_end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `semesters_code_index` (`code`),
  KEY `semesters_is_active_is_archived_index` (`is_active`,`is_archived`),
  KEY `semesters_enrollment_start_date_enrollment_end_date_index` (`enrollment_start_date`,`enrollment_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `specializations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `specializations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `specializations_program_id_name_unique` (`program_id`,`name`),
  UNIQUE KEY `specializations_code_unique` (`code`),
  KEY `specializations_program_id_is_active_index` (`program_id`,`is_active`),
  CONSTRAINT `specializations_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oauth_provider` enum('google','microsoft','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google',
  `oauth_provider_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Vietnamese',
  `national_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `campus_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  `specialization_id` bigint unsigned DEFAULT NULL,
  `curriculum_version_id` bigint unsigned NOT NULL,
  `admission_date` date NOT NULL,
  `expected_graduation_date` date DEFAULT NULL,
  `emergency_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `high_school_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `high_school_graduation_year` year DEFAULT NULL,
  `entrance_exam_score` decimal(5,2) DEFAULT NULL,
  `admission_notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','suspended','graduated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_student_id_unique` (`student_id`),
  UNIQUE KEY `students_email_unique` (`email`),
  UNIQUE KEY `students_national_id_unique` (`national_id`),
  KEY `students_specialization_id_foreign` (`specialization_id`),
  KEY `students_curriculum_version_id_foreign` (`curriculum_version_id`),
  KEY `students_student_id_index` (`student_id`),
  KEY `students_email_index` (`email`),
  KEY `students_oauth_provider_oauth_provider_id_index` (`oauth_provider`,`oauth_provider_id`),
  KEY `students_campus_id_index` (`campus_id`),
  KEY `students_program_id_specialization_id_index` (`program_id`,`specialization_id`),
  KEY `students_status_index` (`status`),
  CONSTRAINT `students_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `students_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `students_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `students_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `syllabus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `syllabus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `total_hours` int DEFAULT NULL,
  `hours_per_session` int DEFAULT NULL,
  `semester_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unit_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `syllabus_unit_id_semester_id_is_active_unique` (`unit_id`,`semester_id`,`is_active`),
  KEY `syllabus_semester_id_foreign` (`semester_id`),
  CONSTRAINT `syllabus_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `syllabus_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unit_prerequisite_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_prerequisite_conditions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned NOT NULL,
  `type` enum('prerequisite','co_requisite','concurrent','anti_requisite','assumed_knowledge','credit_requirement','textual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prerequisite',
  `required_unit_id` bigint unsigned DEFAULT NULL,
  `required_credits` int DEFAULT NULL,
  `free_text` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_prerequisite_conditions_group_id_foreign` (`group_id`),
  KEY `unit_prerequisite_conditions_required_unit_id_foreign` (`required_unit_id`),
  CONSTRAINT `unit_prerequisite_conditions_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `unit_prerequisite_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unit_prerequisite_conditions_required_unit_id_foreign` FOREIGN KEY (`required_unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unit_prerequisite_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_prerequisite_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint unsigned NOT NULL,
  `logic_operator` enum('AND','OR') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AND',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_prerequisite_groups_unit_id_index` (`unit_id`),
  CONSTRAINT `unit_prerequisite_groups_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credit_points` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_05_20_093807_create_campuses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_05_20_102140_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_05_20_102647_create_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_05_21_193823_create_role_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_21_194034_create_campus_user_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_27_101351_create_semesters_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_05_27_154915_create_programs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_05_28_101508_create_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_05_28_101526_create_equivalent_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_05_28_110044_create_specializations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_05_28_110050_create_curriculum_versions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_05_28_110051_create_graduation_requirements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_05_28_110055_create_curriculum_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_05_28_120000_create_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_05_28_120001_create_lectures_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_05_28_120002_create_course_offerings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_05_28_120005_create_course_registrations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_05_28_120006_create_academic_holds_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_05_29_152151_create_syllabus_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_05_29_152159_create_assessment_components_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_05_29_152205_create_assessment_component_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_05_30_000000_create_unit_prerequisite_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_05_30_000001_create_unit_prerequisite_conditions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_06_07_234211_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_06_12_054616_create_enrollments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_06_15_100000_create_rooms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_06_15_101000_create_room_bookings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_06_15_102000_create_class_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_06_15_103000_create_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_06_15_106000_create_academic_records_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_06_15_107000_create_gpa_calculations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_06_15_108500_update_assessment_components_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_06_15_109000_create_assessment_component_detail_scores_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_06_15_110000_add_academic_system_constraints',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_06_29_212609_create_buildings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_07_01_100000_update_permissions_table_add_module_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_07_01_110000_make_code_nullable_in_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_07_01_120000_add_bitwise_permissions_to_roles_table',1);
