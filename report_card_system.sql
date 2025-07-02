--
-- Database: `arturo_school_system`
--

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--
DROP TABLE IF EXISTS `schools`;
CREATE TABLE `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `school_type` enum('primary','secondary','other') NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `motto` varchar(255) DEFAULT NULL,
  `admin_user_id` int(11) DEFAULT NULL COMMENT 'Primary admin for the school, FK to users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL COMMENT 'FK to schools.id, NULL for superadmins',
  `full_name` varchar(150) DEFAULT NULL,
  `email` varchar(191) NOT NULL COMMENT 'Primary login identifier, must be unique',
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','school_admin','teacher') NOT NULL DEFAULT 'teacher',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_dismissed_admin_activity_ts` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_users_school_id` (`school_id`),
  CONSTRAINT `fk_users_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Add the foreign key constraint from schools to users after users table is defined
--
ALTER TABLE `schools`
ADD CONSTRAINT `fk_schools_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--
DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'Indicates if this is the current active year for the school',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_year_for_school` (`school_id`,`year_name`),
  CONSTRAINT `fk_academic_years_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL COMMENT 'e.g., P1, S1 Blue, Year 10A',
  `class_level_group` varchar(50) DEFAULT NULL COMMENT 'e.g., primary_lower, primary_upper, secondary_olevel, secondary_alevel, other. Helps in filtering subjects, grading.',
  `teacher_id` int(11) DEFAULT NULL COMMENT 'Optional: Class teacher/homeroom teacher, FK to users.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_for_school` (`school_id`,`class_name`),
  KEY `idx_classes_teacher_id` (`teacher_id`),
  CONSTRAINT `fk_classes_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terms`
--
DROP TABLE IF EXISTS `terms`;
CREATE TABLE `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `term_name` varchar(50) NOT NULL COMMENT 'e.g., "Term I", "Semester 1"',
  `order_index` tinyint(4) DEFAULT NULL COMMENT 'Optional: For custom sorting of terms',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_term_for_school` (`school_id`,`term_name`),
  CONSTRAINT `fk_terms_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL COMMENT 'School-defined code, e.g., MTC, ENG-LIT',
  `subject_name_full` varchar(100) NOT NULL,
  `applicable_school_type` enum('primary','secondary','any') NOT NULL DEFAULT 'any' COMMENT 'Helps filter subjects based on school type',
  `department` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subject_for_school` (`school_id`,`subject_code`),
  CONSTRAINT `fk_subjects_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_identifier` varchar(50) DEFAULT NULL COMMENT 'School-specific student ID/Adm No.',
  `lin_no` varchar(50) DEFAULT NULL COMMENT 'Learner Identification Number, optional',
  `current_class_id` int(11) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `parent_contact` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 for active, 0 for inactive/archived',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_identifier_for_school` (`school_id`,`student_identifier`),
  KEY `idx_students_school_id` (`school_id`),
  KEY `idx_students_lin_no` (`lin_no`),
  KEY `idx_students_current_class_id` (`current_class_id`),
  CONSTRAINT `fk_students_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_students_class` FOREIGN KEY (`current_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grading_policies`
--
DROP TABLE IF EXISTS `grading_policies`;
CREATE TABLE `grading_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'e.g., Primary Standard, Secondary O-Level',
  `school_type_applicability` enum('primary','secondary','other','any') NOT NULL DEFAULT 'any' COMMENT 'Indicates if policy is for specific school type',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is this the default policy for new setups of this school type at this school?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grading_policies_school_id` (`school_id`),
  CONSTRAINT `fk_grading_policies_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grading_policy_levels`
--
DROP TABLE IF EXISTS `grading_policy_levels`;
CREATE TABLE `grading_policy_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grading_policy_id` int(11) NOT NULL,
  `grade_label` varchar(20) NOT NULL COMMENT 'e.g., D1, A, P7, F9',
  `min_score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `comment` varchar(255) DEFAULT NULL COMMENT 'e.g., Excellent, Very Good',
  `points` decimal(5,2) DEFAULT NULL COMMENT 'e.g., For O-Level points, or primary aggregates',
  `order_index` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'For sorting grades',
  PRIMARY KEY (`id`),
  KEY `idx_gpl_policy_id` (`grading_policy_id`),
  CONSTRAINT `fk_gpl_policy` FOREIGN KEY (`grading_policy_id`) REFERENCES `grading_policies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_batch_settings`
--
DROP TABLE IF EXISTS `report_batch_settings`;
CREATE TABLE `report_batch_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL COMMENT 'Direct link to school for easier querying',
  `academic_year_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `grading_policy_id` int(11) DEFAULT NULL,
  `term_end_date` date DEFAULT NULL,
  `next_term_begin_date` date DEFAULT NULL,
  `import_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_batch_for_school` (`school_id`,`academic_year_id`,`term_id`,`class_id`),
  KEY `idx_rbs_academic_year_id` (`academic_year_id`),
  KEY `idx_rbs_term_id` (`term_id`),
  KEY `idx_rbs_class_id` (`class_id`),
  KEY `idx_rbs_grading_policy_id` (`grading_policy_id`),
  CONSTRAINT `fk_rbs_school_id` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rbs_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rbs_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rbs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rbs_grading_policy` FOREIGN KEY (`grading_policy_id`) REFERENCES `grading_policies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--
DROP TABLE IF EXISTS `scores`;
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_batch_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `bot_score` decimal(5,1) DEFAULT NULL,
  `mot_score` decimal(5,1) DEFAULT NULL,
  `eot_score` decimal(5,1) DEFAULT NULL,
  `eot_remark` varchar(255) DEFAULT NULL,
  `eot_grade_on_report` varchar(20) DEFAULT NULL,
  `eot_points_on_report` decimal(5,2) DEFAULT NULL,
  `teacher_initials_on_report` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_score_entry` (`report_batch_id`,`student_id`,`subject_id`),
  KEY `idx_scores_student_id` (`student_id`),
  KEY `idx_scores_subject_id` (`subject_id`),
  CONSTRAINT `fk_scores_report_batch` FOREIGN KEY (`report_batch_id`) REFERENCES `report_batch_settings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scores_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scores_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_report_summary`
--
DROP TABLE IF EXISTS `student_report_summary`;
CREATE TABLE `student_report_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `report_batch_id` int(11) NOT NULL,
  `p4p7_aggregate_points` int(11) DEFAULT NULL,
  `p4p7_division` varchar(10) DEFAULT NULL,
  `p1p3_total_eot_score` decimal(6,1) DEFAULT NULL,
  `p1p3_average_eot_score` decimal(5,2) DEFAULT NULL,
  `p1p3_position_in_class` int(11) DEFAULT NULL,
  `p1p3_total_students_in_class` int(11) DEFAULT NULL,
  `auto_classteachers_remark_text` text DEFAULT NULL,
  `auto_headteachers_remark_text` text DEFAULT NULL,
  `manual_classteachers_remark_text` text DEFAULT NULL,
  `manual_headteachers_remark_text` text DEFAULT NULL,
  `p1p3_total_bot_score` decimal(6,1) DEFAULT NULL,
  `p1p3_position_total_bot` int(11) DEFAULT NULL,
  `p1p3_total_mot_score` decimal(6,1) DEFAULT NULL,
  `p1p3_position_total_mot` int(11) DEFAULT NULL,
  `p1p3_position_total_eot` int(11) DEFAULT NULL,
  `p1p3_average_bot_score` decimal(5,2) DEFAULT NULL,
  `p1p3_average_mot_score` decimal(5,2) DEFAULT NULL,
  `p4p7_aggregate_bot_score` int(11) DEFAULT NULL,
  `p4p7_division_bot` varchar(10) DEFAULT NULL,
  `p4p7_aggregate_mot_score` int(11) DEFAULT NULL,
  `p4p7_division_mot` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_summary_entry` (`student_id`,`report_batch_id`),
  KEY `idx_srs_report_batch_id` (`report_batch_id`),
  CONSTRAINT `fk_srs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_srs_report_batch` FOREIGN KEY (`report_batch_id`) REFERENCES `report_batch_settings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) DEFAULT NULL COMMENT 'FK to schools.id, if activity is school-specific',
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(191) NOT NULL COMMENT 'Denormalized for easy display, or use user.email',
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_al_school_id` (`school_id`),
  KEY `idx_al_user_id` (`user_id`),
  KEY `idx_al_action_type` (`action_type`),
  KEY `idx_al_timestamp` (`timestamp`),
  CONSTRAINT `fk_al_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `score_edit_log`
--
DROP TABLE IF EXISTS `score_edit_log`;
CREATE TABLE `score_edit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `score_id` int(11) NOT NULL,
  `changed_by_user_id` int(11) NOT NULL,
  `original_bot_score` decimal(5,1) DEFAULT NULL,
  `new_bot_score` decimal(5,1) DEFAULT NULL,
  `original_mot_score` decimal(5,1) DEFAULT NULL,
  `new_mot_score` decimal(5,1) DEFAULT NULL,
  `original_eot_score` decimal(5,1) DEFAULT NULL,
  `new_eot_score` decimal(5,1) DEFAULT NULL,
  `original_eot_remark` varchar(255) DEFAULT NULL,
  `new_eot_remark` varchar(255) DEFAULT NULL,
  `reason_for_change` text DEFAULT NULL,
  `change_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sel_score_id` (`score_id`),
  KEY `idx_sel_user_id` (`changed_by_user_id`),
  CONSTRAINT `fk_sel_score` FOREIGN KEY (`score_id`) REFERENCES `scores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sel_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--
DROP TABLE IF EXISTS `class_enrollments`;
CREATE TABLE `class_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `enrollment_date` DATE DEFAULT (CURRENT_DATE),
  `status` ENUM('enrolled', 'transferred_out', 'graduated') DEFAULT 'enrolled',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollment_per_term` (`school_id`, `student_id`,`class_id`,`academic_year_id`,`term_id`),
  KEY `idx_ce_student_id` (`student_id`),
  KEY `idx_ce_class_id` (`class_id`),
  KEY `idx_ce_academic_year_id` (`academic_year_id`),
  KEY `idx_ce_term_id` (`term_id`),
  CONSTRAINT `fk_ce_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


SET FOREIGN_KEY_CHECKS=1;
COMMIT;
