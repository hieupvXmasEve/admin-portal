/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `academic_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_holds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `hold_type` enum('financial','academic','disciplinary','administrative','health','library') NOT NULL,
  `hold_category` enum('registration','graduation','transcript','all') NOT NULL DEFAULT 'registration',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `priority` enum('high','medium','low') NOT NULL DEFAULT 'medium',
  `status` enum('active','resolved','waived','expired') NOT NULL DEFAULT 'active',
  `placed_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `placed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_holds_placed_by_user_id_foreign` (`placed_by_user_id`),
  KEY `academic_holds_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `academic_holds_student_id_index` (`student_id`),
  KEY `academic_holds_hold_type_status_index` (`hold_type`,`status`),
  KEY `academic_holds_hold_category_index` (`hold_category`),
  KEY `idx_academic_holds_student_active` (`student_id`,`status`),
  KEY `idx_academic_holds_type_active` (`hold_type`,`status`),
  KEY `idx_academic_holds_effective_date` (`placed_date`),
  KEY `idx_academic_holds_resolved_date` (`resolved_date`),
  CONSTRAINT `academic_holds_placed_by_user_id_foreign` FOREIGN KEY (`placed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_holds_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_holds_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `academic_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_offering_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `program_id` bigint(20) unsigned NOT NULL,
  `campus_id` bigint(20) unsigned NOT NULL,
  `final_percentage` decimal(5,2) DEFAULT NULL,
  `final_letter_grade` varchar(5) DEFAULT NULL,
  `grade_points` decimal(3,2) DEFAULT NULL,
  `quality_points` decimal(6,2) DEFAULT NULL,
  `credit_hours` decimal(4,2) NOT NULL,
  `credit_hours_earned` decimal(4,2) NOT NULL DEFAULT 0.00,
  `grade_status` enum('in_progress','provisional','final','incomplete','withdrawn','failed','pass_no_credit','audit','transfer_credit') NOT NULL DEFAULT 'in_progress',
  `completion_status` enum('enrolled','completed','withdrawn','failed','incomplete','in_progress') NOT NULL DEFAULT 'enrolled',
  `enrollment_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `grade_submission_date` date DEFAULT NULL,
  `grade_finalized_date` date DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `total_absences` int(11) NOT NULL DEFAULT 0,
  `total_class_sessions` int(11) DEFAULT NULL,
  `meets_attendance_requirement` tinyint(1) NOT NULL DEFAULT 1,
  `is_repeat_course` tinyint(1) NOT NULL DEFAULT 0,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `original_record_id` bigint(20) unsigned DEFAULT NULL,
  `is_transfer_credit` tinyint(1) NOT NULL DEFAULT 0,
  `transfer_institution` varchar(200) DEFAULT NULL,
  `transfer_course_code` varchar(50) DEFAULT NULL,
  `transfer_course_title` varchar(200) DEFAULT NULL,
  `is_advanced_placement` tinyint(1) NOT NULL DEFAULT 0,
  `is_challenge_exam` tinyint(1) NOT NULL DEFAULT 0,
  `is_credit_by_exam` tinyint(1) NOT NULL DEFAULT 0,
  `grade_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`grade_breakdown`)),
  `raw_percentage` decimal(5,2) DEFAULT NULL,
  `curve_adjustment` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade_adjustment_reason` text DEFAULT NULL,
  `excluded_from_gpa` tinyint(1) NOT NULL DEFAULT 0,
  `gpa_exclusion_reason` text DEFAULT NULL,
  `instructor_comments` text DEFAULT NULL,
  `administrative_notes` text DEFAULT NULL,
  `instructor_id` bigint(20) unsigned DEFAULT NULL,
  `grade_submitted_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `grade_approved_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `affects_academic_standing` tinyint(1) NOT NULL DEFAULT 1,
  `affects_graduation_requirement` tinyint(1) NOT NULL DEFAULT 1,
  `satisfies_prerequisite` tinyint(1) NOT NULL DEFAULT 1,
  `grade_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`grade_history`)),
  `last_grade_change_at` timestamp NULL DEFAULT NULL,
  `last_changed_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
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
  KEY `idx_academic_rec_student_semester` (`student_id`,`semester_id`),
  KEY `idx_academic_rec_unit_grade_status` (`unit_id`,`grade_status`),
  KEY `idx_academic_rec_semester_grade_status` (`semester_id`,`grade_status`),
  KEY `idx_academic_rec_letter_grade` (`final_letter_grade`),
  KEY `idx_academic_rec_excluded_gpa` (`excluded_from_gpa`),
  CONSTRAINT `academic_records_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`),
  CONSTRAINT `academic_records_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_records_grade_approved_by_lecture_id_foreign` FOREIGN KEY (`grade_approved_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_grade_submitted_by_lecture_id_foreign` FOREIGN KEY (`grade_submitted_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_instructor_id_foreign` FOREIGN KEY (`instructor_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_last_changed_by_lecture_id_foreign` FOREIGN KEY (`last_changed_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_original_record_id_foreign` FOREIGN KEY (`original_record_id`) REFERENCES `academic_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_records_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `academic_records_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`),
  CONSTRAINT `academic_records_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_records_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`),
  CONSTRAINT `check_records_final_percentage` CHECK (`final_percentage` is null or `final_percentage` >= 0 and `final_percentage` <= 100),
  CONSTRAINT `check_records_grade_points` CHECK (`grade_points` is null or `grade_points` >= 0 and `grade_points` <= 4),
  CONSTRAINT `check_records_credit_hours_positive` CHECK (`credit_hours` > 0),
  CONSTRAINT `check_records_credit_hours_earned` CHECK (`credit_hours_earned` >= 0 and `credit_hours_earned` <= `credit_hours`),
  CONSTRAINT `check_records_quality_points` CHECK (`quality_points` is null or `quality_points` >= 0),
  CONSTRAINT `check_records_attendance_percentage` CHECK (`attendance_percentage` is null or `attendance_percentage` >= 0 and `attendance_percentage` <= 100),
  CONSTRAINT `check_records_attempt_number_positive` CHECK (`attempt_number` >= 1),
  CONSTRAINT `check_records_curve_adjustment` CHECK (`curve_adjustment` >= -100 and `curve_adjustment` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `academic_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_standings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `standing` enum('good','probation','suspension','honors') NOT NULL DEFAULT 'good',
  `gpa` decimal(4,2) NOT NULL,
  `cumulative_gpa` decimal(4,2) DEFAULT NULL,
  `total_credits_completed` int(11) NOT NULL DEFAULT 0,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_date` timestamp NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_standings_student_id_semester_id_unique` (`student_id`,`semester_id`),
  KEY `academic_standings_semester_id_foreign` (`semester_id`),
  KEY `academic_standings_created_by_foreign` (`created_by`),
  KEY `academic_standings_student_id_semester_id_index` (`student_id`,`semester_id`),
  KEY `academic_standings_student_id_is_active_index` (`student_id`,`is_active`),
  KEY `academic_standings_standing_index` (`standing`),
  CONSTRAINT `academic_standings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_standings_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_standings_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `causer_type` varchar(255) DEFAULT NULL,
  `causer_id` bigint(20) unsigned DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `batch_uuid` uuid DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `answer_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `answer_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `answer_id` bigint(20) unsigned NOT NULL,
  `option_id` bigint(20) unsigned NOT NULL,
  `free_text` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `answer_options_answer_id_option_id_unique` (`answer_id`,`option_id`),
  KEY `answer_options_option_id_foreign` (`option_id`),
  CONSTRAINT `answer_options_answer_id_foreign` FOREIGN KEY (`answer_id`) REFERENCES `answers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answer_options_option_id_foreign` FOREIGN KEY (`option_id`) REFERENCES `options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `response_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `answer_text` longtext DEFAULT NULL,
  `answer_number` decimal(12,4) DEFAULT NULL,
  `answer_date` date DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `answers_response_id_index` (`response_id`),
  KEY `answers_question_id_index` (`question_id`),
  CONSTRAINT `answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answers_response_id_foreign` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_component_detail_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_component_detail_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_component_detail_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_offering_id` bigint(20) unsigned NOT NULL,
  `graded_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `points_earned` decimal(8,2) DEFAULT NULL,
  `percentage_score` decimal(5,2) DEFAULT NULL,
  `letter_grade` varchar(5) DEFAULT NULL,
  `gpa_points` decimal(3,2) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `submission_attempt` int(11) NOT NULL DEFAULT 1,
  `submission_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`submission_files`)),
  `submission_text` longtext DEFAULT NULL,
  `submission_url` varchar(500) DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `minutes_late` int(11) NOT NULL DEFAULT 0,
  `late_penalty_applied` decimal(5,2) NOT NULL DEFAULT 0.00,
  `late_excuse` text DEFAULT NULL,
  `late_excuse_approved` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('not_submitted','submitted','grading','graded','returned','resubmit_required','excused','incomplete','cancelled') NOT NULL DEFAULT 'not_submitted',
  `score_status` enum('draft','provisional','final','disputed','under_review') NOT NULL DEFAULT 'draft',
  `instructor_feedback` longtext DEFAULT NULL,
  `private_notes` longtext DEFAULT NULL,
  `rubric_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rubric_scores`)),
  `bonus_points` decimal(8,2) NOT NULL DEFAULT 0.00,
  `bonus_reason` text DEFAULT NULL,
  `plagiarism_suspected` tinyint(1) NOT NULL DEFAULT 0,
  `plagiarism_score` decimal(5,2) DEFAULT NULL,
  `plagiarism_notes` text DEFAULT NULL,
  `integrity_status` enum('clear','under_investigation','violation_confirmed','violation_minor','violation_major') NOT NULL DEFAULT 'clear',
  `score_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`score_history`)),
  `last_modified_at` timestamp NULL DEFAULT NULL,
  `last_modified_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `is_extra_credit` tinyint(1) NOT NULL DEFAULT 0,
  `is_makeup` tinyint(1) NOT NULL DEFAULT 0,
  `special_circumstances` text DEFAULT NULL,
  `score_excluded` tinyint(1) NOT NULL DEFAULT 0,
  `exclusion_reason` text DEFAULT NULL,
  `student_comments` text DEFAULT NULL,
  `appeal_requested` tinyint(1) NOT NULL DEFAULT 0,
  `appeal_requested_at` timestamp NULL DEFAULT NULL,
  `appeal_reason` text DEFAULT NULL,
  `appeal_status` enum('none','pending','under_review','approved','denied') NOT NULL DEFAULT 'none',
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
  KEY `student_score_gpa_calc_idx` (`student_id`,`score_status`,`score_excluded`,`gpa_points`),
  KEY `student_transcript_idx` (`student_id`,`graded_at`,`score_status`),
  KEY `student_score_timeline_idx` (`student_id`,`graded_at`,`score_status`,`points_earned`),
  KEY `graded_by_lecture_idx` (`graded_by_lecture_id`,`graded_at`),
  KEY `idx_scores_student_component` (`student_id`,`assessment_component_detail_id`),
  KEY `idx_scores_component_detail` (`assessment_component_detail_id`),
  KEY `idx_scores_submitted_at` (`submitted_at`),
  CONSTRAINT `assessment_component_detail_scores_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_component_detail_scores_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_scores_detail_id_fk` FOREIGN KEY (`assessment_component_detail_id`) REFERENCES `assessment_component_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_scores_graded_by_fk` FOREIGN KEY (`graded_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `detail_scores_modified_by_fk` FOREIGN KEY (`last_modified_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_scores_percentage_score` CHECK (`percentage_score` is null or `percentage_score` >= 0 and `percentage_score` <= 100),
  CONSTRAINT `check_scores_gpa_points` CHECK (`gpa_points` is null or `gpa_points` >= 0 and `gpa_points` <= 4),
  CONSTRAINT `check_scores_submission_attempt_positive` CHECK (`submission_attempt` >= 1),
  CONSTRAINT `check_scores_minutes_late_positive` CHECK (`minutes_late` >= 0),
  CONSTRAINT `check_scores_late_penalty_applied` CHECK (`late_penalty_applied` >= 0 and `late_penalty_applied` <= 100),
  CONSTRAINT `check_scores_plagiarism_score` CHECK (`plagiarism_score` is null or `plagiarism_score` >= 0 and `plagiarism_score` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_component_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_component_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_component_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `max_points` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assessment_component_details_assessment_component_id_foreign` (`assessment_component_id`),
  CONSTRAINT `assessment_component_details_assessment_component_id_foreign` FOREIGN KEY (`assessment_component_id`) REFERENCES `assessment_components` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_components`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `syllabus_template_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `type` enum('quiz','assignment','project','exam','online_activity','other') NOT NULL,
  `is_required_to_sit_final_exam` tinyint(1) NOT NULL DEFAULT 1,
  `due_date` datetime DEFAULT NULL,
  `available_from` datetime DEFAULT NULL,
  `late_submission_deadline` datetime DEFAULT NULL,
  `late_penalty_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `late_penalty_type` enum('per_day','per_hour','fixed','none') NOT NULL DEFAULT 'none',
  `submission_type` enum('online','in_person','both','no_submission') NOT NULL DEFAULT 'online',
  `allowed_file_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_file_types`)),
  `max_file_size_mb` int(11) DEFAULT NULL,
  `max_submissions` int(11) NOT NULL DEFAULT 1,
  `allow_resubmission` tinyint(1) NOT NULL DEFAULT 0,
  `is_group_work` tinyint(1) NOT NULL DEFAULT 0,
  `min_group_size` int(11) DEFAULT NULL,
  `max_group_size` int(11) DEFAULT NULL,
  `students_form_groups` tinyint(1) NOT NULL DEFAULT 1,
  `assessment_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment_criteria`)),
  `grading_instructions` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `scores_published` tinyint(1) NOT NULL DEFAULT 0,
  `is_extra_credit` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','published','in_progress','grading','completed','cancelled') NOT NULL DEFAULT 'draft',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_syllabus_template_component_code` (`syllabus_template_id`,`code`),
  KEY `syllabus_template_type_idx` (`syllabus_template_id`,`type`),
  KEY `syllabus_template_sort_order_idx` (`syllabus_template_id`,`sort_order`),
  KEY `assessment_components_due_date_status_index` (`due_date`,`status`),
  KEY `assessment_components_is_published_status_index` (`is_published`,`status`),
  KEY `assessment_components_is_group_work_type_index` (`is_group_work`,`type`),
  KEY `assessment_components_category_sort_order_index` (`category`,`sort_order`),
  FULLTEXT KEY `name` (`name`,`description`,`grading_instructions`),
  CONSTRAINT `assessment_components_syllabus_template_id_foreign` FOREIGN KEY (`syllabus_template_id`) REFERENCES `syllabus_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `check_weight_percentage` CHECK (`weight` >= 0 and `weight` <= 100),
  CONSTRAINT `check_late_penalty_percentage` CHECK (`late_penalty_percentage` >= 0 and `late_penalty_percentage` <= 100),
  CONSTRAINT `check_file_size_positive` CHECK (`max_file_size_mb` is null or `max_file_size_mb` > 0),
  CONSTRAINT `check_max_submissions_positive` CHECK (`max_submissions` >= 1),
  CONSTRAINT `check_group_size_valid` CHECK ((`min_group_size` is null or `min_group_size` >= 1) and (`max_group_size` is null or `max_group_size` >= 1) and (`min_group_size` is null or `max_group_size` is null or `min_group_size` <= `max_group_size`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `class_session_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `recorded_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('present','absent','late','excused','partial','medical_leave','official_leave') NOT NULL DEFAULT 'absent',
  `check_in_time` timestamp NULL DEFAULT NULL,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `minutes_late` int(11) NOT NULL DEFAULT 0,
  `minutes_present` int(11) DEFAULT NULL,
  `recording_method` enum('manual','qr_code','rfid','biometric','mobile_app','online_participation','auto_system') NOT NULL DEFAULT 'manual',
  `notes` text DEFAULT NULL,
  `excuse_reason` text DEFAULT NULL,
  `excuse_document_path` varchar(500) DEFAULT NULL,
  `participation_level` enum('excellent','good','average','poor','none') DEFAULT NULL,
  `participation_score` decimal(3,1) DEFAULT NULL,
  `participation_notes` text DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `affects_grade` tinyint(1) NOT NULL DEFAULT 1,
  `is_makeup_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `batch_id` varchar(50) DEFAULT NULL,
  `device_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_info`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session_student_attendance` (`class_session_id`,`student_id`),
  UNIQUE KEY `unique_attendance_per_session` (`class_session_id`,`student_id`),
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
  KEY `idx_attendances_student_session` (`student_id`,`class_session_id`),
  KEY `idx_attendances_session_status` (`class_session_id`,`status`),
  KEY `idx_attendances_status` (`status`),
  CONSTRAINT `attendances_class_session_id_foreign` FOREIGN KEY (`class_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_recorded_by_lecture_id_foreign` FOREIGN KEY (`recorded_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_verified_by_lecture_id_foreign` FOREIGN KEY (`verified_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_attendances_minutes_late_positive` CHECK (`minutes_late` >= 0),
  CONSTRAINT `check_attendances_minutes_present_positive` CHECK (`minutes_present` is null or `minutes_present` >= 0),
  CONSTRAINT `check_attendances_participation_score` CHECK (`participation_score` is null or `participation_score` >= 0 and `participation_score` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `buildings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buildings_code_unique` (`code`),
  KEY `buildings_campus_id_foreign` (`campus_id`),
  CONSTRAINT `buildings_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campus_user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campus_user_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `campus_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campus_user_roles_user_id_foreign` (`user_id`),
  KEY `campus_user_roles_campus_id_foreign` (`campus_id`),
  KEY `campus_user_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `campus_user_roles_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campus_user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `campus_user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campuses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canvas_course_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `canvas_course_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `canvas_integration_id` bigint(20) unsigned NOT NULL,
  `canvas_course_id` varchar(255) NOT NULL,
  `canvas_course_code` varchar(255) DEFAULT NULL,
  `canvas_course_name` varchar(255) DEFAULT NULL,
  `course_offering_id` bigint(20) unsigned DEFAULT NULL,
  `canvas_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`canvas_data`)),
  `sync_status` enum('pending','mapped','ignored') NOT NULL DEFAULT 'pending',
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_integration_canvas_course` (`canvas_integration_id`,`canvas_course_id`),
  KEY `canvas_course_mappings_canvas_integration_id_sync_status_index` (`canvas_integration_id`,`sync_status`),
  KEY `canvas_course_mappings_course_offering_id_index` (`course_offering_id`),
  KEY `canvas_course_mappings_canvas_course_id_index` (`canvas_course_id`),
  CONSTRAINT `canvas_course_mappings_canvas_integration_id_foreign` FOREIGN KEY (`canvas_integration_id`) REFERENCES `canvas_integrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `canvas_course_mappings_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canvas_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `canvas_integrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `canvas_url` varchar(255) NOT NULL,
  `client_id` varchar(255) NOT NULL,
  `client_secret` text NOT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `sync_status` enum('idle','syncing','completed','failed') NOT NULL DEFAULT 'idle',
  `sync_error` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `canvas_integrations_created_by_foreign` (`created_by`),
  KEY `canvas_integrations_updated_by_foreign` (`updated_by`),
  KEY `canvas_integrations_is_active_index` (`is_active`),
  KEY `canvas_integrations_sync_status_index` (`sync_status`),
  CONSTRAINT `canvas_integrations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `canvas_integrations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `class_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_offering_id` bigint(20) unsigned NOT NULL,
  `room_id` bigint(20) unsigned DEFAULT NULL,
  `room_booking_id` bigint(20) unsigned DEFAULT NULL,
  `lecture_id` bigint(20) unsigned DEFAULT NULL,
  `session_title` varchar(200) DEFAULT NULL,
  `session_description` text DEFAULT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `session_type` enum('lecture','tutorial','practical','laboratory','seminar','workshop','exam','assessment','field_trip','guest_lecture','review','other') NOT NULL DEFAULT 'lecture',
  `delivery_mode` enum('in_person','online','hybrid','blended') NOT NULL DEFAULT 'in_person',
  `status` enum('scheduled','in_progress','completed','cancelled','postponed','moved') NOT NULL DEFAULT 'scheduled',
  `online_meeting_url` varchar(500) DEFAULT NULL,
  `meeting_id` varchar(100) DEFAULT NULL,
  `meeting_password` varchar(100) DEFAULT NULL,
  `learning_objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`learning_objectives`)),
  `required_materials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_materials`)),
  `topics_covered` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`topics_covered`)),
  `attendance_required` tinyint(1) NOT NULL DEFAULT 1,
  `attendance_tracking_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `expected_attendees` int(11) DEFAULT NULL,
  `actual_attendees` int(11) DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `is_assessment` tinyint(1) NOT NULL DEFAULT 0,
  `assessment_weight` decimal(5,2) DEFAULT NULL,
  `assessment_duration_minutes` int(11) DEFAULT NULL,
  `assessment_materials_allowed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment_materials_allowed`)),
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `parent_session_id` bigint(20) unsigned DEFAULT NULL,
  `sequence_number` int(11) DEFAULT NULL,
  `instructor_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `student_instructions` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
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
  KEY `instructor_schedule_idx` (`lecture_id`,`session_date`,`start_time`),
  KEY `class_sessions_session_type_status_index` (`session_type`,`status`),
  KEY `class_sessions_delivery_mode_session_date_index` (`delivery_mode`,`session_date`),
  KEY `class_sessions_is_assessment_session_date_index` (`is_assessment`,`session_date`),
  KEY `attendance_config_idx` (`attendance_required`,`attendance_tracking_enabled`),
  KEY `class_sessions_is_recurring_parent_session_id_index` (`is_recurring`,`parent_session_id`),
  KEY `class_sessions_sequence_number_index` (`sequence_number`),
  KEY `idx_class_sessions_offering_date` (`course_offering_id`,`session_date`),
  KEY `idx_class_sessions_datetime` (`session_date`,`start_time`),
  KEY `idx_class_sessions_room` (`room_id`),
  FULLTEXT KEY `session_title` (`session_title`,`session_description`,`student_instructions`),
  CONSTRAINT `class_sessions_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_sessions_lecture_id_foreign` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_sessions_parent_session_id_foreign` FOREIGN KEY (`parent_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_sessions_room_booking_id_foreign` FOREIGN KEY (`room_booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_sessions_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_sessions_times` CHECK (`start_time` < `end_time`),
  CONSTRAINT `check_sessions_expected_attendees_positive` CHECK (`expected_attendees` is null or `expected_attendees` > 0),
  CONSTRAINT `check_sessions_actual_attendees_valid` CHECK (`actual_attendees` is null or `actual_attendees` >= 0),
  CONSTRAINT `check_sessions_attendance_percentage` CHECK (`attendance_percentage` is null or `attendance_percentage` >= 0 and `attendance_percentage` <= 100),
  CONSTRAINT `check_sessions_assessment_weight` CHECK (`assessment_weight` is null or `assessment_weight` >= 0 and `assessment_weight` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_offerings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_offerings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `semester_id` bigint(20) unsigned NOT NULL,
  `curriculum_unit_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `syllabus_template_id` bigint(20) unsigned DEFAULT NULL,
  `lecture_id` bigint(20) unsigned DEFAULT NULL,
  `campus_id` bigint(20) unsigned DEFAULT NULL,
  `section_code` varchar(10) DEFAULT NULL,
  `max_capacity` int(11) NOT NULL DEFAULT 1000,
  `current_enrollment` int(11) NOT NULL DEFAULT 0,
  `waitlist_capacity` int(11) NOT NULL DEFAULT 10,
  `current_waitlist` int(11) NOT NULL DEFAULT 0,
  `delivery_mode` enum('in_person','online','hybrid','blended') NOT NULL DEFAULT 'in_person',
  `schedule_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_days`)),
  `schedule_time_start` time DEFAULT NULL,
  `schedule_time_end` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `enrollment_status` enum('open','closed','waitlist_only','cancelled') NOT NULL DEFAULT 'open',
  `registration_start_date` date DEFAULT NULL,
  `registration_end_date` date DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_semester_unit_section` (`semester_id`,`curriculum_unit_id`,`section_code`),
  KEY `course_offerings_syllabus_template_id_foreign` (`syllabus_template_id`),
  KEY `course_offerings_campus_id_foreign` (`campus_id`),
  KEY `course_offerings_semester_id_curriculum_unit_id_index` (`semester_id`,`curriculum_unit_id`),
  KEY `course_offerings_lecture_id_semester_id_index` (`lecture_id`,`semester_id`),
  KEY `course_offerings_is_active_enrollment_status_index` (`is_active`,`enrollment_status`),
  KEY `course_offerings_delivery_mode_index` (`delivery_mode`),
  KEY `course_offerings_current_enrollment_max_capacity_index` (`current_enrollment`,`max_capacity`),
  KEY `co_registration_dates_idx` (`registration_start_date`,`registration_end_date`),
  KEY `idx_course_off_semester_unit` (`semester_id`,`curriculum_unit_id`),
  KEY `idx_course_off_unit_active` (`curriculum_unit_id`,`is_active`),
  KEY `idx_course_off_lecture` (`lecture_id`),
  KEY `idx_course_off_capacity` (`max_capacity`,`current_enrollment`),
  KEY `course_offerings_unit_id_index` (`unit_id`),
  CONSTRAINT `course_offerings_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_offerings_curriculum_unit_id_foreign` FOREIGN KEY (`curriculum_unit_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_offerings_lecture_id_foreign` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_offerings_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_offerings_syllabus_template_id_foreign` FOREIGN KEY (`syllabus_template_id`) REFERENCES `syllabus_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_offerings_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `course_offering_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `registration_status` enum('registered','confirmed','dropped','withdrawn','completed') NOT NULL DEFAULT 'registered',
  `registration_date` timestamp NOT NULL,
  `registration_method` enum('online','advisor','admin_override') NOT NULL DEFAULT 'online',
  `credit_hours` decimal(4,2) NOT NULL,
  `final_grade` varchar(3) DEFAULT NULL,
  `grade_points` decimal(3,2) DEFAULT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `is_retake` tinyint(1) NOT NULL DEFAULT 0,
  `drop_date` timestamp NULL DEFAULT NULL,
  `withdrawal_date` timestamp NULL DEFAULT NULL,
  `completion_date` timestamp NULL DEFAULT NULL,
  `retake_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_retake_paid` enum('yes','no') NOT NULL DEFAULT 'no',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `original_registration_id` bigint(20) unsigned DEFAULT NULL,
  `retake_reason` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_course_semester` (`student_id`,`course_offering_id`,`semester_id`),
  KEY `course_registrations_student_id_index` (`student_id`),
  KEY `course_registrations_course_offering_id_index` (`course_offering_id`),
  KEY `course_registrations_semester_id_index` (`semester_id`),
  KEY `course_registrations_registration_status_index` (`registration_status`),
  KEY `idx_course_reg_student_semester` (`student_id`,`semester_id`),
  KEY `idx_course_reg_offering_status` (`course_offering_id`,`registration_status`),
  KEY `idx_course_reg_semester_status` (`semester_id`,`registration_status`),
  KEY `idx_course_reg_date` (`registration_date`),
  KEY `course_registrations_original_registration_id_foreign` (`original_registration_id`),
  CONSTRAINT `course_registrations_course_offering_id_foreign` FOREIGN KEY (`course_offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_registrations_original_registration_id_foreign` FOREIGN KEY (`original_registration_id`) REFERENCES `course_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `course_registrations_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_registrations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `curriculum_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `curriculum_version_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('core','major','elective') DEFAULT NULL,
  `unit_scope` enum('program','common','specialization_specific','cross_program') DEFAULT NULL,
  `year_level` tinyint(3) unsigned DEFAULT NULL COMMENT 'Academic year level (1-3)',
  `semester_number` tinyint(3) unsigned DEFAULT NULL COMMENT 'Suggested semester number within the course (1-9)',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `minimum_grade` decimal(4,2) DEFAULT NULL COMMENT 'Minimum grade required for completion (optional)',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `curriculum_unit_unique` (`curriculum_version_id`,`unit_id`),
  KEY `curriculum_units_semester_id_foreign` (`semester_id`),
  KEY `curriculum_units_curriculum_version_id_type_index` (`curriculum_version_id`,`type`),
  KEY `curriculum_units_year_level_semester_number_index` (`year_level`,`semester_number`),
  KEY `idx_curriculum_units_version_semester` (`curriculum_version_id`,`semester_number`),
  KEY `idx_curriculum_units_unit` (`unit_id`),
  KEY `idx_curriculum_units_required` (`is_required`),
  CONSTRAINT `curriculum_units_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `curriculum_units_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `curriculum_units_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `curriculum_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `specialization_id` bigint(20) unsigned DEFAULT NULL,
  `version_code` varchar(20) DEFAULT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_versions_semester_id_foreign` (`semester_id`),
  KEY `curriculum_versions_program_id_specialization_id_index` (`program_id`,`specialization_id`),
  KEY `idx_curriculum_versions_spec_active` (`specialization_id`,`semester_id`),
  KEY `idx_curriculum_versions_effective_date` (`created_at`),
  CONSTRAINT `curriculum_versions_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `curriculum_versions_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`),
  CONSTRAINT `curriculum_versions_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_configurations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Configuration name for identification',
  `host` varchar(255) NOT NULL COMMENT 'SMTP server hostname',
  `port` int(11) NOT NULL DEFAULT 587 COMMENT 'SMTP server port',
  `username` varchar(255) DEFAULT NULL COMMENT 'SMTP authentication username',
  `password` text DEFAULT NULL COMMENT 'Encrypted SMTP password',
  `encryption` enum('tls','ssl','none') NOT NULL DEFAULT 'tls' COMMENT 'Encryption method',
  `from_address` varchar(255) NOT NULL COMMENT 'Default from email address',
  `from_name` varchar(255) NOT NULL COMMENT 'Default from name',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this configuration is active',
  `daily_limit` int(11) NOT NULL DEFAULT 1000 COMMENT 'Daily email sending limit',
  `rate_limit` int(11) NOT NULL DEFAULT 60 COMMENT 'Emails per minute limit',
  `last_tested_at` timestamp NULL DEFAULT NULL COMMENT 'Last connection test timestamp',
  `test_result` text DEFAULT NULL COMMENT 'Last connection test result',
  `password_salt` varchar(64) DEFAULT NULL,
  `password_verification_hash` varchar(255) DEFAULT NULL,
  `password_encrypted_at` timestamp NULL DEFAULT NULL,
  `credential_backup` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_configurations_is_active_index` (`is_active`),
  KEY `email_configurations_host_index` (`host`),
  KEY `email_configurations_password_encrypted_at_index` (`password_encrypted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL COMMENT 'Email recipient address',
  `sender` varchar(255) DEFAULT NULL COMMENT 'Email sender address',
  `subject` varchar(255) NOT NULL COMMENT 'Email subject',
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','queued','sending','sent','delivered','failed','bounced','rejected') NOT NULL DEFAULT 'pending' COMMENT 'Email delivery status',
  `queued_at` timestamp NULL DEFAULT NULL COMMENT 'When email was queued',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When email was sent',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'When email was delivered',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'When email failed',
  `error_message` text DEFAULT NULL COMMENT 'Error message if failed',
  `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional email metadata' CHECK (json_valid(`metadata`)),
  `message_id` varchar(255) DEFAULT NULL COMMENT 'Email message ID from provider',
  `batch_id` varchar(255) DEFAULT NULL COMMENT 'Batch ID for bulk emails',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_logs_recipient_status_index` (`recipient`,`status`),
  KEY `email_logs_status_created_at_index` (`status`,`created_at`),
  KEY `email_logs_template_id_status_index` (`template_id`,`status`),
  KEY `email_logs_batch_id_status_index` (`batch_id`,`status`),
  KEY `email_logs_user_id_status_index` (`user_id`,`status`),
  KEY `email_logs_sent_at_index` (`sent_at`),
  KEY `email_logs_failed_at_index` (`failed_at`),
  KEY `email_logs_message_id_index` (`message_id`),
  CONSTRAINT `email_logs_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_operation_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `operation` varchar(100) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_operation_logs_created_at_index` (`created_at`),
  KEY `email_operation_logs_operation_created_at_index` (`operation`,`created_at`),
  KEY `email_operation_logs_operation_index` (`operation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Template name for identification',
  `type` enum('welcome','grade_notification','course_registration','academic_hold','enrollment_confirmation','assessment_deadline','system_announcement','reminder','custom') NOT NULL COMMENT 'Template type for categorization',
  `subject` varchar(255) NOT NULL COMMENT 'Email subject line template',
  `html_content` longtext NOT NULL COMMENT 'HTML email content with variables',
  `text_content` longtext DEFAULT NULL COMMENT 'Plain text email content',
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Available template variables' CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether template is active',
  `version` int(11) NOT NULL DEFAULT 1 COMMENT 'Template version number',
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'Template description',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_templates_name_version_unique` (`name`,`version`),
  KEY `email_templates_parent_id_foreign` (`parent_id`),
  KEY `email_templates_type_is_active_index` (`type`,`is_active`),
  KEY `email_templates_is_active_index` (`is_active`),
  KEY `email_templates_version_index` (`version`),
  CONSTRAINT `email_templates_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned NOT NULL,
  `curriculum_version_id` bigint(20) unsigned NOT NULL,
  `semester_number` tinyint(3) unsigned NOT NULL,
  `status` enum('in_progress','completed','withdrawn') NOT NULL DEFAULT 'in_progress',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_semester_enrollment` (`student_id`,`semester_id`),
  KEY `enrollments_student_id_semester_id_index` (`student_id`,`semester_id`),
  KEY `enrollments_semester_id_status_index` (`semester_id`,`status`),
  KEY `enrollments_curriculum_version_id_semester_number_index` (`curriculum_version_id`,`semester_number`),
  KEY `idx_enrollments_student_semester` (`student_id`,`semester_id`),
  KEY `idx_enrollments_semester_status` (`semester_id`,`status`),
  KEY `idx_enrollments_curriculum_version` (`curriculum_version_id`),
  KEY `idx_enrollments_semester_number` (`semester_number`),
  CONSTRAINT `enrollments_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `equivalent_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equivalent_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `equivalent_unit_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `form_result_visibility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_result_visibility` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `visibility_level` enum('own_submission','aggregated','full_detail') NOT NULL,
  `min_aggregation_threshold` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_result_visibility_form_id_role_id_unique` (`form_id`,`role_id`),
  KEY `form_result_visibility_role_id_foreign` (`role_id`),
  CONSTRAINT `form_result_visibility_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_result_visibility_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `form_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_version_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `form_sections_form_version_id_order_index_index` (`form_version_id`,`order_index`),
  CONSTRAINT `form_sections_form_version_id_foreign` FOREIGN KEY (`form_version_id`) REFERENCES `form_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `form_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `form_version_id` bigint(20) unsigned DEFAULT NULL,
  `campus_id` bigint(20) unsigned DEFAULT NULL,
  `scope_type` enum('section','class_session','course','global') NOT NULL,
  `scope_id` bigint(20) unsigned DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `submission_limit_per_user` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `form_targets_form_version_id_foreign` (`form_version_id`),
  KEY `form_targets_campus_id_foreign` (`campus_id`),
  KEY `idx_form_targets_lookup` (`form_id`,`campus_id`,`scope_type`,`scope_id`,`start_at`),
  CONSTRAINT `form_targets_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_targets_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_targets_form_version_id_foreign` FOREIGN KEY (`form_version_id`) REFERENCES `form_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `form_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `version_no` int(11) NOT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `effective_from` datetime DEFAULT NULL,
  `effective_to` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_versions_form_id_version_no_unique` (`form_id`,`version_no`),
  KEY `form_versions_form_id_is_published_index` (`form_id`,`is_published`),
  CONSTRAINT `form_versions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `form_visibility_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_visibility_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_visibility_roles_form_id_role_id_unique` (`form_id`,`role_id`),
  KEY `form_visibility_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `form_visibility_roles_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_visibility_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `type` enum('feedback','survey','query') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forms_code_unique` (`code`),
  KEY `forms_created_by_foreign` (`created_by`),
  KEY `forms_type_status_index` (`type`,`status`),
  CONSTRAINT `forms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gpa_calculations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gpa_calculations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned DEFAULT NULL,
  `program_id` bigint(20) unsigned DEFAULT NULL,
  `calculation_type` enum('semester','cumulative','major','program','year','transfer','institutional') NOT NULL DEFAULT 'semester',
  `gpa` decimal(4,3) DEFAULT NULL,
  `quality_points` decimal(8,3) NOT NULL DEFAULT 0.000,
  `credit_hours_attempted` decimal(6,2) NOT NULL DEFAULT 0.00,
  `credit_hours_earned` decimal(6,2) NOT NULL DEFAULT 0.00,
  `credit_hours_gpa` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_courses` int(11) NOT NULL DEFAULT 0,
  `completed_courses` int(11) NOT NULL DEFAULT 0,
  `failed_courses` int(11) NOT NULL DEFAULT 0,
  `withdrawn_courses` int(11) NOT NULL DEFAULT 0,
  `incomplete_courses` int(11) NOT NULL DEFAULT 0,
  `a_grades` int(11) NOT NULL DEFAULT 0,
  `b_grades` int(11) NOT NULL DEFAULT 0,
  `c_grades` int(11) NOT NULL DEFAULT 0,
  `d_grades` int(11) NOT NULL DEFAULT 0,
  `f_grades` int(11) NOT NULL DEFAULT 0,
  `academic_standing` enum('excellent','good','satisfactory','probation','suspension','dismissal','warning') DEFAULT NULL,
  `required_gpa` decimal(3,2) DEFAULT NULL,
  `meets_gpa_requirement` tinyint(1) NOT NULL DEFAULT 1,
  `gpa_deficit` decimal(4,3) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `semester_type` enum('fall','spring','summer','winter') DEFAULT NULL,
  `class_rank` int(11) DEFAULT NULL,
  `class_size` int(11) DEFAULT NULL,
  `percentile` decimal(5,2) DEFAULT NULL,
  `program_rank` int(11) DEFAULT NULL,
  `program_class_size` int(11) DEFAULT NULL,
  `credits_needed_to_graduate` decimal(6,2) DEFAULT NULL,
  `completion_percentage` decimal(5,2) DEFAULT NULL,
  `projected_graduation_date` date DEFAULT NULL,
  `on_track_to_graduate` tinyint(1) NOT NULL DEFAULT 1,
  `includes_transfer_credits` tinyint(1) NOT NULL DEFAULT 0,
  `includes_repeated_courses` tinyint(1) NOT NULL DEFAULT 0,
  `dean_list_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `honors_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `graduation_honors_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `calculated_at` timestamp NOT NULL,
  `calculated_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `calculation_parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_parameters`)),
  `calculation_notes` text DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by_lecture_id` bigint(20) unsigned DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `previous_gpa` decimal(4,3) DEFAULT NULL,
  `gpa_change` decimal(4,3) DEFAULT NULL,
  `gpa_trend` enum('improving','declining','stable') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_semester_calc_type` (`student_id`,`semester_id`,`calculation_type`),
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
  KEY `idx_gpa_calc_student_type` (`student_id`,`calculation_type`),
  KEY `idx_gpa_calc_semester_type` (`semester_id`,`calculation_type`),
  KEY `idx_gpa_calc_program` (`program_id`),
  KEY `idx_gpa_calc_academic_standing` (`academic_standing`),
  CONSTRAINT `gpa_calculations_calculated_by_lecture_id_foreign` FOREIGN KEY (`calculated_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gpa_calculations_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gpa_calculations_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gpa_calculations_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gpa_calculations_verified_by_lecture_id_foreign` FOREIGN KEY (`verified_by_lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `check_gpa_valid` CHECK (`gpa` is null or `gpa` >= 0 and `gpa` <= 4),
  CONSTRAINT `check_quality_points_positive` CHECK (`quality_points` >= 0),
  CONSTRAINT `check_credit_hours_positive` CHECK (`credit_hours_attempted` >= 0 and `credit_hours_earned` >= 0 and `credit_hours_gpa` >= 0 and `credit_hours_earned` <= `credit_hours_attempted`),
  CONSTRAINT `check_course_counts` CHECK (`total_courses` >= 0 and `completed_courses` >= 0 and `failed_courses` >= 0 and `withdrawn_courses` >= 0 and `incomplete_courses` >= 0 and `completed_courses` + `failed_courses` + `withdrawn_courses` + `incomplete_courses` <= `total_courses`),
  CONSTRAINT `check_grade_counts` CHECK (`a_grades` >= 0 and `b_grades` >= 0 and `c_grades` >= 0 and `d_grades` >= 0 and `f_grades` >= 0),
  CONSTRAINT `check_percentile` CHECK (`percentile` is null or `percentile` >= 0 and `percentile` <= 100),
  CONSTRAINT `check_completion_percentage` CHECK (`completion_percentage` is null or `completion_percentage` >= 0 and `completion_percentage` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `graduation_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `graduation_requirements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `specialization_id` bigint(20) unsigned DEFAULT NULL,
  `total_credits_required` decimal(5,2) NOT NULL,
  `core_credits_required` decimal(5,2) NOT NULL DEFAULT 0.00,
  `major_credits_required` decimal(5,2) NOT NULL DEFAULT 0.00,
  `elective_credits_required` decimal(5,2) NOT NULL DEFAULT 0.00,
  `minimum_gpa` decimal(3,2) NOT NULL DEFAULT 2.00,
  `minimum_major_gpa` decimal(3,2) NOT NULL DEFAULT 2.00,
  `maximum_study_years` int(11) NOT NULL DEFAULT 6,
  `required_internship` tinyint(1) NOT NULL DEFAULT 0,
  `required_thesis` tinyint(1) NOT NULL DEFAULT 0,
  `required_english_certification` tinyint(1) NOT NULL DEFAULT 0,
  `special_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`special_requirements`)),
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lectures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lectures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `title` varchar(10) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile_phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `oauth_provider` varchar(255) DEFAULT NULL,
  `oauth_provider_id` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `campus_id` bigint(20) unsigned NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `expertise_areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`expertise_areas`)),
  `academic_rank` enum('lecturer','senior_lecturer','associate_professor','professor','emeritus_professor','visiting_lecturer','adjunct_professor') NOT NULL DEFAULT 'lecturer',
  `highest_degree` varchar(50) DEFAULT NULL,
  `degree_field` varchar(255) DEFAULT NULL,
  `alma_mater` varchar(255) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contract','visiting','emeritus') NOT NULL DEFAULT 'full_time',
  `employment_status` enum('active','on_leave','sabbatical','retired','terminated','suspended') NOT NULL DEFAULT 'active',
  `preferred_teaching_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferred_teaching_days`)),
  `preferred_start_time` time DEFAULT NULL,
  `preferred_end_time` time DEFAULT NULL,
  `max_teaching_hours_per_week` int(11) NOT NULL DEFAULT 40,
  `teaching_modalities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`teaching_modalities`)),
  `office_address` text DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_name` text DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `certifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`certifications`)),
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`languages`)),
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `can_teach_online` tinyint(1) NOT NULL DEFAULT 1,
  `is_available_for_assignment` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
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
  KEY `lectures_oauth_provider_oauth_provider_id_index` (`oauth_provider`,`oauth_provider_id`),
  KEY `lectures_last_login_at_index` (`last_login_at`),
  CONSTRAINT `lectures_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `value` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `allows_free_text` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `options_question_id_value_unique` (`question_id`,`value`),
  KEY `options_question_id_order_index_index` (`question_id`,`order_index`),
  CONSTRAINT `options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  UNIQUE KEY `permissions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `program_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_change_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `from_program_id` bigint(20) unsigned NOT NULL,
  `to_program_id` bigint(20) unsigned NOT NULL,
  `from_specialization_id` bigint(20) unsigned DEFAULT NULL,
  `to_specialization_id` bigint(20) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `affected_credits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`affected_credits`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `program_change_requests_from_program_id_foreign` (`from_program_id`),
  KEY `program_change_requests_to_program_id_foreign` (`to_program_id`),
  KEY `program_change_requests_from_specialization_id_foreign` (`from_specialization_id`),
  KEY `program_change_requests_to_specialization_id_foreign` (`to_specialization_id`),
  KEY `program_change_requests_approved_by_foreign` (`approved_by`),
  KEY `program_change_requests_student_id_status_index` (`student_id`,`status`),
  KEY `program_change_requests_status_index` (`status`),
  CONSTRAINT `program_change_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `program_change_requests_from_program_id_foreign` FOREIGN KEY (`from_program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `program_change_requests_from_specialization_id_foreign` FOREIGN KEY (`from_specialization_id`) REFERENCES `specializations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `program_change_requests_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `program_change_requests_to_program_id_foreign` FOREIGN KEY (`to_program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `program_change_requests_to_specialization_id_foreign` FOREIGN KEY (`to_specialization_id`) REFERENCES `specializations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programs_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `queries_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `queries_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `response_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned DEFAULT NULL,
  `custom_topic_text` varchar(255) DEFAULT NULL,
  `status` enum('open','pending','answered','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `assigned_to_user_id` bigint(20) unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queries_tickets_response_id_foreign` (`response_id`),
  KEY `queries_tickets_topic_id_foreign` (`topic_id`),
  KEY `queries_tickets_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  KEY `idx_queries_tickets_status` (`status`,`assigned_to_user_id`,`created_at`),
  CONSTRAINT `queries_tickets_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `queries_tickets_response_id_foreign` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queries_tickets_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `query_topics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `query_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `query_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `author_user_id` bigint(20) unsigned DEFAULT NULL,
  `author_student_id` bigint(20) unsigned DEFAULT NULL,
  `message` longtext NOT NULL,
  `is_official_answer` tinyint(1) NOT NULL DEFAULT 0,
  `upload_record_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `query_replies_author_student_id_foreign` (`author_student_id`),
  KEY `query_replies_upload_record_id_foreign` (`upload_record_id`),
  KEY `query_replies_ticket_id_created_at_index` (`ticket_id`,`created_at`),
  KEY `query_replies_author_user_id_foreign` (`author_user_id`),
  CONSTRAINT `query_replies_author_student_id_foreign` FOREIGN KEY (`author_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `query_replies_author_user_id_foreign` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `query_replies_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `queries_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `query_replies_upload_record_id_foreign` FOREIGN KEY (`upload_record_id`) REFERENCES `upload_records` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `query_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `query_topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_topics_title_unique` (`title`),
  KEY `query_topics_is_active_order_index_index` (`is_active`,`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_version_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(100) NOT NULL,
  `text` text NOT NULL,
  `type` enum('short_text','long_text','single_choice','multi_choice','likert','rating','date','number','file','matrix','yes_no') NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `help_text` text DEFAULT NULL,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `validation_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_json`)),
  `visibility_condition_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visibility_condition_json`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `questions_form_version_id_code_unique` (`form_version_id`,`code`),
  KEY `questions_section_id_foreign` (`section_id`),
  KEY `questions_form_version_id_order_index_index` (`form_version_id`,`order_index`),
  CONSTRAINT `questions_form_version_id_foreign` FOREIGN KEY (`form_version_id`) REFERENCES `form_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `questions_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `form_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `form_version_id` bigint(20) unsigned NOT NULL,
  `campus_id` bigint(20) unsigned DEFAULT NULL,
  `target_scope_type` enum('section','class_session','course','global') NOT NULL,
  `target_scope_id` bigint(20) unsigned DEFAULT NULL,
  `submitted_by_student_id` bigint(20) unsigned DEFAULT NULL,
  `anonymized` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
  `reviewed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `origin` enum('web','mobile','api') NOT NULL DEFAULT 'web',
  `submitted_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `responses_form_version_id_foreign` (`form_version_id`),
  KEY `responses_campus_id_foreign` (`campus_id`),
  KEY `responses_submitted_by_student_id_foreign` (`submitted_by_student_id`),
  KEY `responses_reviewed_by_user_id_foreign` (`reviewed_by_user_id`),
  KEY `idx_responses_lookup` (`form_id`,`campus_id`,`submitted_at`),
  KEY `responses_status_reviewed_by_user_id_index` (`status`,`reviewed_by_user_id`),
  CONSTRAINT `responses_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `responses_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `responses_form_version_id_foreign` FOREIGN KEY (`form_version_id`) REFERENCES `form_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `responses_reviewed_by_user_id_foreign` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `responses_submitted_by_student_id_foreign` FOREIGN KEY (`submitted_by_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`),
  UNIQUE KEY `roles_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `room_booking_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_booking_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_booking_id` bigint(20) unsigned NOT NULL,
  `action_type` enum('created','updated','approved','rejected','cancelled','completed') NOT NULL,
  `action_by_type` varchar(255) NOT NULL,
  `action_by_id` bigint(20) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_booking_actions_room_booking_id_action_type_index` (`room_booking_id`,`action_type`),
  KEY `room_booking_actions_action_by_idx` (`action_by_type`,`action_by_id`),
  KEY `room_booking_actions_created_at_index` (`created_at`),
  CONSTRAINT `room_booking_actions_room_booking_id_foreign` FOREIGN KEY (`room_booking_id`) REFERENCES `room_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `room_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `booked_by_type` varchar(255) NOT NULL,
  `booked_by_id` bigint(20) unsigned NOT NULL,
  `approved_by_type` varchar(255) DEFAULT NULL,
  `approved_by_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `booking_type` enum('class','exam','meeting','event','maintenance','personal_study','workshop','other') NOT NULL DEFAULT 'meeting',
  `status` enum('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `recurrence_type` enum('daily','weekly','biweekly','monthly') DEFAULT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `recurrence_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recurrence_days`)),
  `parent_booking_id` bigint(20) unsigned DEFAULT NULL,
  `required_equipment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_equipment`)),
  `setup_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`setup_requirements`)),
  `special_requirements` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `send_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `rejection_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
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
  CONSTRAINT `check_booking_times` CHECK (`start_time` < `end_time`),
  CONSTRAINT `check_recurrence_end_date` CHECK (`recurrence_end_date` is null or `recurrence_end_date` >= `booking_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` bigint(20) unsigned NOT NULL,
  `building_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `type` enum('classroom','laboratory','computer_lab','auditorium','meeting_room','library','study_room','workshop','office','other') NOT NULL DEFAULT 'classroom',
  `capacity` int(11) NOT NULL DEFAULT 1,
  `status` enum('available','occupied','maintenance','out_of_service','reserved') NOT NULL DEFAULT 'available',
  `is_bookable` tinyint(1) NOT NULL DEFAULT 1,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `available_from` time NOT NULL DEFAULT '07:00:00',
  `available_until` time NOT NULL DEFAULT '18:00:00',
  `blocked_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`blocked_days`)),
  `description` text DEFAULT NULL,
  `usage_guidelines` text DEFAULT NULL,
  `booking_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campus_room_code` (`campus_id`,`code`),
  UNIQUE KEY `rooms_code_unique` (`code`),
  KEY `rooms_campus_id_type_index` (`campus_id`,`type`),
  KEY `rooms_status_is_bookable_index` (`status`,`is_bookable`),
  KEY `rooms_capacity_index` (`capacity`),
  KEY `rooms_available_from_available_until_index` (`available_from`,`available_until`),
  KEY `rooms_building_id_floor_index` (`building_id`,`floor`),
  FULLTEXT KEY `description` (`description`,`usage_guidelines`,`booking_notes`),
  CONSTRAINT `rooms_building_id_foreign` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`),
  CONSTRAINT `rooms_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`),
  CONSTRAINT `check_capacity_positive` CHECK (`capacity` > 0),
  CONSTRAINT `check_available_times` CHECK (`available_from` < `available_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `semesters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `semesters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `enrollment_start_date` datetime DEFAULT NULL,
  `enrollment_end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `semesters_code_index` (`code`),
  KEY `semesters_is_active_is_archived_index` (`is_active`,`is_archived`),
  KEY `semesters_enrollment_start_date_enrollment_end_date_index` (`enrollment_start_date`,`enrollment_end_date`),
  KEY `idx_semesters_active` (`is_active`),
  KEY `idx_semesters_date_range` (`start_date`,`end_date`),
  KEY `idx_semesters_enrollment_period` (`enrollment_start_date`,`enrollment_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `specializations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `specializations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `specializations_program_id_name_unique` (`program_id`,`name`),
  UNIQUE KEY `specializations_code_unique` (`code`),
  KEY `specializations_program_id_is_active_index` (`program_id`,`is_active`),
  CONSTRAINT `specializations_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `ethnicity` varchar(100) DEFAULT NULL,
  `birth_day` tinyint(3) unsigned DEFAULT NULL,
  `birth_month` tinyint(3) unsigned DEFAULT NULL,
  `birth_year` year(4) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `health_information` text DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(255) DEFAULT NULL,
  `campus_code` varchar(20) NOT NULL,
  `intended_program` varchar(100) DEFAULT NULL,
  `intended_specialization` varchar(100) DEFAULT NULL,
  `intake` varchar(50) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `english_test_type` varchar(50) DEFAULT NULL,
  `listening` decimal(5,2) DEFAULT NULL,
  `reading` decimal(5,2) DEFAULT NULL,
  `writing` decimal(5,2) DEFAULT NULL,
  `speaking` decimal(5,2) DEFAULT NULL,
  `overall` decimal(5,2) DEFAULT NULL,
  `submitted_photo` varchar(255) DEFAULT NULL,
  `submitted_cccd` varchar(255) DEFAULT NULL,
  `submitted_ccta` varchar(255) DEFAULT NULL,
  `submitted_tn_translate` varchar(255) DEFAULT NULL,
  `submitted_hb_translate` varchar(255) DEFAULT NULL,
  `submitted_other` varchar(255) DEFAULT NULL,
  `submitted_insurance_card` varchar(255) DEFAULT NULL,
  `submitted_exemption_gc` varchar(255) DEFAULT NULL,
  `study_link_status` varchar(50) DEFAULT NULL,
  `english_qualifications` varchar(100) DEFAULT NULL,
  `sut_id` varchar(50) DEFAULT NULL,
  `is_international_applicant` tinyint(1) NOT NULL DEFAULT 0,
  `exception_units` text DEFAULT NULL,
  `status` enum('pending','reviewed','approved','rejected') NOT NULL DEFAULT 'approved',
  `student_code` varchar(50) NOT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_applications_email_unique` (`email`),
  UNIQUE KEY `student_applications_student_code_unique` (`student_code`),
  UNIQUE KEY `student_applications_national_id_unique` (`national_id`),
  KEY `student_applications_student_id_index` (`student_id`),
  CONSTRAINT `student_applications_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `oauth_provider` enum('google','microsoft','manual') NOT NULL DEFAULT 'google',
  `oauth_provider_id` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `nationality` varchar(100) NOT NULL DEFAULT 'Vietnamese',
  `national_id` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cccd_address` text DEFAULT NULL,
  `campus_id` bigint(20) unsigned NOT NULL,
  `program_id` bigint(20) unsigned NOT NULL,
  `specialization_id` bigint(20) unsigned DEFAULT NULL,
  `curriculum_version_id` bigint(20) unsigned NOT NULL,
  `admission_date` date NOT NULL,
  `expected_graduation_date` date DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `high_school_name` varchar(255) DEFAULT NULL,
  `high_school_graduation_year` year(4) DEFAULT NULL,
  `entrance_exam_score` decimal(5,2) DEFAULT NULL,
  `admission_notes` text DEFAULT NULL,
  `status` enum('active','inactive','suspended','graduated','intake_pre_uni_gc','intake_course','deferred','dropout','dropout_transfer','pending') DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `parent_user_id` bigint(20) unsigned DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `academic_status` enum('active','inactive','graduated','suspended','withdrawn') NOT NULL DEFAULT 'active',
  `status_change_date` date DEFAULT NULL,
  `status_reason` text DEFAULT NULL,
  `status_changed_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_student_id_unique` (`student_id`),
  UNIQUE KEY `students_email_unique` (`email`),
  UNIQUE KEY `students_national_id_unique` (`national_id`),
  KEY `students_specialization_id_foreign` (`specialization_id`),
  KEY `students_student_id_index` (`student_id`),
  KEY `students_email_index` (`email`),
  KEY `students_oauth_provider_oauth_provider_id_index` (`oauth_provider`,`oauth_provider_id`),
  KEY `students_campus_id_index` (`campus_id`),
  KEY `students_program_id_specialization_id_index` (`program_id`,`specialization_id`),
  KEY `students_status_index` (`status`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_campus_status` (`campus_id`,`status`),
  KEY `idx_students_program_status` (`program_id`,`status`),
  KEY `idx_students_curriculum_version` (`curriculum_version_id`),
  KEY `idx_students_admission_date` (`admission_date`),
  KEY `students_status_changed_by_foreign` (`status_changed_by`),
  KEY `students_parent_user_id_foreign` (`parent_user_id`),
  CONSTRAINT `students_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`),
  CONSTRAINT `students_curriculum_version_id_foreign` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`),
  CONSTRAINT `students_parent_user_id_foreign` FOREIGN KEY (`parent_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `students_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_status_changed_by_foreign` FOREIGN KEY (`status_changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `syllabus_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `syllabus_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `version` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_hours` int(11) DEFAULT NULL,
  `total_sessions` int(11) DEFAULT NULL,
  `learning_outcomes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`learning_outcomes`)),
  `grading_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`grading_criteria`)),
  `required_materials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_materials`)),
  `assessment_policy` text DEFAULT NULL,
  `applicable_program_id` bigint(20) unsigned DEFAULT NULL,
  `applicable_campus_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_mode` enum('in_person','online','hybrid','blended') DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `source_template_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_syllabus_templates_unit` (`unit_id`),
  KEY `fk_syllabus_templates_program` (`applicable_program_id`),
  KEY `fk_syllabus_templates_campus` (`applicable_campus_id`),
  KEY `fk_syllabus_templates_creator` (`created_by`),
  KEY `fk_syllabus_templates_source` (`source_template_id`),
  CONSTRAINT `fk_syllabus_templates_campus` FOREIGN KEY (`applicable_campus_id`) REFERENCES `campuses` (`id`),
  CONSTRAINT `fk_syllabus_templates_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_syllabus_templates_program` FOREIGN KEY (`applicable_program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `fk_syllabus_templates_source` FOREIGN KEY (`source_template_id`) REFERENCES `syllabus_templates` (`id`),
  CONSTRAINT `fk_syllabus_templates_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` uuid NOT NULL,
  `batch_id` uuid NOT NULL,
  `family_hash` varchar(255) DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_family_hash_index` (`family_hash`),
  KEY `telescope_entries_created_at_index` (`created_at`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` uuid NOT NULL,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unit_prerequisite_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_prerequisite_conditions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `type` enum('prerequisite','co_requisite','concurrent_prerequisite','anti_requisite','assumed_knowledge','credit_requirement','textual') NOT NULL DEFAULT 'prerequisite',
  `required_unit_id` bigint(20) unsigned DEFAULT NULL,
  `required_credits` int(11) DEFAULT NULL,
  `free_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prereq_conditions_group` (`group_id`),
  KEY `idx_prereq_conditions_required_unit` (`required_unit_id`),
  KEY `idx_prereq_conditions_type` (`type`),
  CONSTRAINT `unit_prerequisite_conditions_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `unit_prerequisite_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unit_prerequisite_conditions_required_unit_id_foreign` FOREIGN KEY (`required_unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unit_prerequisite_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_prerequisite_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint(20) unsigned NOT NULL,
  `logic_operator` enum('AND','OR') NOT NULL DEFAULT 'AND',
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_prerequisite_groups_unit_id_index` (`unit_id`),
  KEY `idx_prereq_groups_unit` (`unit_id`),
  CONSTRAINT `unit_prerequisite_groups_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `credit_points` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `upload_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `upload_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `context` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `disk` varchar(255) NOT NULL,
  `url` text DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `response_id` bigint(20) unsigned DEFAULT NULL,
  `answer_id` bigint(20) unsigned DEFAULT NULL,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `reply_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `upload_records_user_id_foreign` (`user_id`),
  KEY `upload_records_context_user_id_index` (`context`,`user_id`),
  KEY `upload_records_context_student_id_index` (`context`,`student_id`),
  KEY `upload_records_response_id_index` (`response_id`),
  KEY `upload_records_answer_id_index` (`answer_id`),
  KEY `upload_records_ticket_id_index` (`ticket_id`),
  KEY `upload_records_reply_id_index` (`reply_id`),
  KEY `upload_records_created_at_context_index` (`created_at`,`context`),
  KEY `upload_records_expires_at_index` (`expires_at`),
  KEY `upload_records_filename_index` (`filename`),
  KEY `upload_records_context_index` (`context`),
  KEY `upload_records_hash_index` (`hash`),
  CONSTRAINT `upload_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_email_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_email_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `notification_type` enum('welcome','grade_notification','course_registration','academic_hold','enrollment_confirmation','assessment_deadline','system_announcement','reminder','event_publication','all') NOT NULL COMMENT 'Type of notification',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this notification type is enabled',
  `frequency` enum('immediate','daily','weekly','never') NOT NULL DEFAULT 'immediate' COMMENT 'Notification frequency',
  `last_sent_at` timestamp NULL DEFAULT NULL COMMENT 'Last time this type of notification was sent',
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional notification settings' CHECK (json_valid(`settings`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_email_preferences_user_id_notification_type_unique` (`user_id`,`notification_type`),
  KEY `user_email_preferences_user_id_is_enabled_index` (`user_id`,`is_enabled`),
  KEY `user_email_preferences_notification_type_is_enabled_index` (`notification_type`,`is_enabled`),
  CONSTRAINT `user_email_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','pending','suspended','banned','locked','verified','unverified') NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
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
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_01_26_100000_create_canvas_integrations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_01_26_100001_create_canvas_course_mappings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_05_20_093807_create_campuses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_05_20_102140_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_05_20_102647_create_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_05_21_193823_create_role_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_05_21_194034_create_campus_user_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_05_27_101351_create_semesters_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_05_27_154915_create_programs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_05_28_101508_create_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_05_28_101526_create_equivalent_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_05_28_110044_create_specializations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_05_28_110050_create_curriculum_versions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_05_28_110051_create_graduation_requirements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_05_28_110055_create_curriculum_units_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_05_28_120000_create_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_05_28_120001_create_lectures_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_05_28_120001_create_syllabus_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_05_28_120002_create_course_offerings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_05_28_120003_add_foreign_key_to_canvas_course_mappings',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_05_28_120005_create_course_registrations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_05_28_120006_create_academic_holds_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_05_29_152159_create_assessment_components_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_05_29_152205_create_assessment_component_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_05_30_000000_create_unit_prerequisite_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_05_30_000001_create_unit_prerequisite_conditions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_06_07_234211_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_06_12_054616_create_enrollments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_06_15_100000_create_rooms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_06_15_101000_create_room_bookings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2025_06_15_102000_create_class_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_06_15_102000_create_room_booking_actions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_06_15_103000_create_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_06_15_106000_create_academic_records_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_06_15_107000_create_gpa_calculations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_06_15_109000_create_assessment_component_detail_scores_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_06_15_110000_add_academic_system_constraints',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_06_15_120000_add_performance_indexes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_06_29_212609_create_buildings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_07_01_100000_update_permissions_table_add_module_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_07_01_110000_make_code_nullable_in_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_07_14_100022_create_program_change_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_07_14_100023_add_student_management_fields_to_existing_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_07_14_100023_create_academic_standings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_07_18_023941_add_soft_deletes_to_academic_standings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_08_04_195804_create_student_applications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_08_07_210147_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_08_07_210148_add_event_column_to_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_08_13_192417_create_email_configurations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_08_13_192537_create_email_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_08_13_192654_create_email_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_08_13_192803_create_user_email_preferences_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_08_13_234300_create_email_operation_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_08_15_141912_add_building_id_to_rooms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_08_21_051543_add_unit_id_to_course_offerings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_08_23_032156_update_student_applications_comprehensive',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_08_24_024557_create_upload_records_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_08_29_135707_add_soft_deletes_to_enrollments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_08_29_135712_add_soft_deletes_to_course_registrations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_08_29_140208_add_soft_deletes_to_program_change_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_08_29_163940_create_telescope_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_09_07_211552_add_cccd_address_to_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_09_07_233441_add_unique_constraint_to_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_09_09_000001_add_parent_user_id_to_students_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_09_11_161545_update_students_status_column',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_09_15_024001_create_forms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_09_15_024100_create_form_versions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_09_15_024129_create_form_sections_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_09_15_024152_create_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_09_15_024216_create_options_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_09_15_024239_create_form_targets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_09_15_024312_create_form_visibility_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_09_15_024339_create_form_result_visibility_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_09_15_024358_create_responses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_09_15_024425_create_answers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_09_15_024443_create_answer_options_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_09_15_024520_create_query_topics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_09_15_024536_create_queries_tickets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_09_15_024556_create_query_replies_table',1);
