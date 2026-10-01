-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 05:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `emr`
--

-- --------------------------------------------------------

--
-- Table structure for table `admissions`
--

CREATE TABLE `admissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admission_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ward_id` bigint(20) UNSIGNED NOT NULL,
  `bed_id` bigint(20) UNSIGNED DEFAULT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `admitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'admitted',
  `bed_charged_until` date DEFAULT NULL,
  `discharged_at` timestamp NULL DEFAULT NULL,
  `discharged_by` bigint(20) UNSIGNED DEFAULT NULL,
  `discharge_type` varchar(20) DEFAULT NULL,
  `final_diagnosis` text DEFAULT NULL,
  `discharge_summary` text DEFAULT NULL,
  `discharge_medications` text DEFAULT NULL,
  `follow_up` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admission_notes`
--

CREATE TABLE `admission_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anc_visits`
--

CREATE TABLE `anc_visits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregnancy_id` bigint(20) UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `weight` decimal(5,1) DEFAULT NULL,
  `systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `fundal_height` tinyint(3) UNSIGNED DEFAULT NULL,
  `presentation` varchar(20) DEFAULT NULL,
  `fetal_heart_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `fetal_movement` tinyint(1) DEFAULT NULL,
  `urine_protein` varchar(10) DEFAULT NULL,
  `oedema` varchar(10) DEFAULT NULL,
  `haemoglobin` decimal(4,1) DEFAULT NULL,
  `interventions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`interventions`)),
  `complaints` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'new',
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `source` varchar(10) NOT NULL DEFAULT 'staff',
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `booked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event` varchar(50) NOT NULL,
  `auditable_type` varchar(255) DEFAULT NULL,
  `auditable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `babies`
--

CREATE TABLE `babies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `delivery_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `birth_weight_g` smallint(5) UNSIGNED DEFAULT NULL,
  `apgar_1` tinyint(3) UNSIGNED DEFAULT NULL,
  `apgar_5` tinyint(3) UNSIGNED DEFAULT NULL,
  `outcome` varchar(30) NOT NULL,
  `resuscitated` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ward_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bed_movements`
--

CREATE TABLE `bed_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED NOT NULL,
  `from_bed_id` bigint(20) UNSIGNED DEFAULT NULL,
  `to_bed_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bill_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `insurance_provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `claim_status` varchar(20) NOT NULL DEFAULT 'none',
  `claim_batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `authorization_code` varchar(50) DEFAULT NULL,
  `claim_amount` decimal(12,2) DEFAULT NULL,
  `claim_amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `claim_rejection_reason` varchar(255) DEFAULT NULL,
  `claim_transferred_at` timestamp NULL DEFAULT NULL,
  `claim_submitted_at` timestamp NULL DEFAULT NULL,
  `claim_paid_at` timestamp NULL DEFAULT NULL,
  `claim_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bill_id` bigint(20) UNSIGNED NOT NULL,
  `billable_type` varchar(255) DEFAULT NULL,
  `billable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `insurance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `patient_amount` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_reason` varchar(255) DEFAULT NULL,
  `discounted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claim_batches`
--

CREATE TABLE `claim_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `batch_number` varchar(30) NOT NULL,
  `insurance_provider_id` bigint(20) UNSIGNED NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `submission_status` varchar(20) DEFAULT NULL,
  `submission_reference` varchar(100) DEFAULT NULL,
  `submitted_electronically_at` timestamp NULL DEFAULT NULL,
  `amount_claimed` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_on` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requires_triage` tinyint(1) NOT NULL DEFAULT 1,
  `specialty` varchar(20) NOT NULL DEFAULT 'general',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `presenting_complaint` text DEFAULT NULL,
  `history` text DEFAULT NULL,
  `past_history` text DEFAULT NULL,
  `drug_history` text DEFAULT NULL,
  `family_social_history` text DEFAULT NULL,
  `systems_review` text DEFAULT NULL,
  `examination` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'draft',
  `signed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_addenda`
--

CREATE TABLE `consultation_addenda` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `consultation_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_imports`
--

CREATE TABLE `data_imports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(40) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) DEFAULT NULL,
  `mode` varchar(20) NOT NULL DEFAULT 'create',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `status` varchar(20) NOT NULL,
  `total_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `create_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `update_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skip_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `error_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skipped_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`errors`)),
  `warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`warnings`)),
  `message` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregnancy_id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivered_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mode` varchar(30) NOT NULL,
  `gestation_weeks` tinyint(3) UNSIGNED DEFAULT NULL,
  `blood_loss_ml` smallint(5) UNSIGNED DEFAULT NULL,
  `placenta_complete` tinyint(1) NOT NULL DEFAULT 1,
  `perineum` varchar(20) DEFAULT NULL,
  `complications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`complications`)),
  `maternal_outcome` varchar(20) NOT NULL DEFAULT 'alive',
  `notes` text DEFAULT NULL,
  `attended_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dental_findings`
--

CREATE TABLE `dental_findings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tooth` tinyint(3) UNSIGNED DEFAULT NULL,
  `surfaces` varchar(10) DEFAULT NULL,
  `condition` varchar(30) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'existing',
  `service_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone_extension` varchar(20) DEFAULT NULL,
  `head_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diagnoses`
--

CREATE TABLE `diagnoses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `consultation_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `icd10_code` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `certainty` varchar(20) NOT NULL DEFAULT 'provisional',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drugs`
--

CREATE TABLE `drugs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `strength` varchar(50) DEFAULT NULL,
  `form` varchar(30) NOT NULL,
  `route` varchar(30) DEFAULT NULL,
  `dispensing_unit` varchar(30) DEFAULT NULL,
  `reorder_level` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eye_exams`
--

CREATE TABLE `eye_exams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `va_right` varchar(10) DEFAULT NULL,
  `va_left` varchar(10) DEFAULT NULL,
  `va_right_corrected` varchar(10) DEFAULT NULL,
  `va_left_corrected` varchar(10) DEFAULT NULL,
  `iop_right` decimal(4,1) DEFAULT NULL,
  `iop_left` decimal(4,1) DEFAULT NULL,
  `sph_right` decimal(5,2) DEFAULT NULL,
  `cyl_right` decimal(5,2) DEFAULT NULL,
  `axis_right` smallint(5) UNSIGNED DEFAULT NULL,
  `add_right` decimal(4,2) DEFAULT NULL,
  `anterior_right` text DEFAULT NULL,
  `fundus_right` text DEFAULT NULL,
  `sph_left` decimal(5,2) DEFAULT NULL,
  `cyl_left` decimal(5,2) DEFAULT NULL,
  `axis_left` smallint(5) UNSIGNED DEFAULT NULL,
  `add_left` decimal(4,2) DEFAULT NULL,
  `anterior_left` text DEFAULT NULL,
  `fundus_left` text DEFAULT NULL,
  `pd` tinyint(3) UNSIGNED DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `spectacles_prescribed` tinyint(1) NOT NULL DEFAULT 0,
  `lens_notes` varchar(255) DEFAULT NULL,
  `examined_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `icd10_codes`
--

CREATE TABLE `icd10_codes` (
  `code` varchar(10) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `imaging_attachments`
--

CREATE TABLE `imaging_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `imaging_order_id` bigint(20) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `size` bigint(20) UNSIGNED NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `imaging_orders`
--

CREATE TABLE `imaging_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `consultation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ordered_by` bigint(20) UNSIGNED DEFAULT NULL,
  `imaging_test_id` bigint(20) UNSIGNED NOT NULL,
  `priority` varchar(10) NOT NULL DEFAULT 'routine',
  `clinical_notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `study_instance_uid` varchar(100) DEFAULT NULL,
  `pacs_linked_at` timestamp NULL DEFAULT NULL,
  `scheduled_for` datetime DEFAULT NULL,
  `performed_at` timestamp NULL DEFAULT NULL,
  `performed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `technique` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `impression` text DEFAULT NULL,
  `reported_by` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `imaging_tests`
--

CREATE TABLE `imaging_tests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `modality` varchar(30) NOT NULL,
  `report_template` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `immunizations`
--

CREATE TABLE `immunizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `vaccine_id` bigint(20) UNSIGNED NOT NULL,
  `given_on` date NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `site` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `given_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_providers`
--

CREATE TABLE `insurance_providers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'insurance',
  `coverage_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 100,
  `requires_authorization` tinyint(1) NOT NULL DEFAULT 0,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integration_messages`
--

CREATE TABLE `integration_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `channel` varchar(20) NOT NULL,
  `direction` varchar(3) NOT NULL,
  `status` varchar(10) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `summary` varchar(500) NOT NULL,
  `payload` text DEFAULT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

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
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_analyzers`
--

CREATE TABLE `lab_analyzers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `token_hint` varchar(8) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_analyzer_mappings`
--

CREATE TABLE `lab_analyzer_mappings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lab_analyzer_id` bigint(20) UNSIGNED NOT NULL,
  `analyzer_code` varchar(50) NOT NULL,
  `lab_test_id` bigint(20) UNSIGNED NOT NULL,
  `lab_test_parameter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `consultation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ordered_by` bigint(20) UNSIGNED DEFAULT NULL,
  `priority` varchar(10) NOT NULL DEFAULT 'routine',
  `clinical_notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `collected_at` timestamp NULL DEFAULT NULL,
  `collected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_order_items`
--

CREATE TABLE `lab_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lab_order_id` bigint(20) UNSIGNED NOT NULL,
  `lab_test_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `comment` text DEFAULT NULL,
  `entered_by` bigint(20) UNSIGNED DEFAULT NULL,
  `entered_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lab_order_item_id` bigint(20) UNSIGNED NOT NULL,
  `lab_test_parameter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `reference` varchar(60) DEFAULT NULL,
  `value` text NOT NULL,
  `numeric_value` decimal(12,3) DEFAULT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `sample_type` varchar(50) DEFAULT NULL,
  `turnaround_hours` smallint(5) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_test_parameters`
--

CREATE TABLE `lab_test_parameters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lab_test_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'numeric',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `ref_low` decimal(10,3) DEFAULT NULL,
  `ref_high` decimal(10,3) DEFAULT NULL,
  `ref_text` varchar(50) DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medication_administrations`
--

CREATE TABLE `medication_administrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED NOT NULL,
  `prescription_item_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(10) NOT NULL,
  `dose_given` varchar(50) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `administered_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_notes`
--

CREATE TABLE `nursing_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'general',
  `note` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `online_payments`
--

CREATE TABLE `online_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(40) NOT NULL,
  `gateway` varchar(20) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `bill_item_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`bill_item_ids`)),
  `status` varchar(12) NOT NULL DEFAULT 'pending',
  `channel` varchar(10) NOT NULL,
  `checkout_url` varchar(500) DEFAULT NULL,
  `gateway_reference` varchar(100) DEFAULT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partograph_entries`
--

CREATE TABLE `partograph_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregnancy_id` bigint(20) UNSIGNED NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cervical_dilation` decimal(3,1) DEFAULT NULL,
  `descent` tinyint(3) UNSIGNED DEFAULT NULL,
  `contractions` tinyint(3) UNSIGNED DEFAULT NULL,
  `contraction_strength` varchar(10) DEFAULT NULL,
  `fetal_heart_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `liquor` char(1) DEFAULT NULL,
  `moulding` varchar(4) DEFAULT NULL,
  `pulse` smallint(5) UNSIGNED DEFAULT NULL,
  `systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hospital_number` varchar(30) NOT NULL,
  `legacy_number` varchar(50) DEFAULT NULL,
  `title` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `dob_estimated` tinyint(1) NOT NULL DEFAULT 0,
  `marital_status` varchar(20) DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `nin_verified_at` timestamp NULL DEFAULT NULL,
  `nin_verification_ref` varchar(100) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `alt_phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `genotype` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `nok_name` varchar(150) DEFAULT NULL,
  `nok_relationship` varchar(50) DEFAULT NULL,
  `nok_phone` varchar(30) DEFAULT NULL,
  `nok_address` varchar(255) DEFAULT NULL,
  `payment_type` varchar(20) NOT NULL DEFAULT 'self_pay',
  `insurance_provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `insurance_number` varchar(50) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_deceased` tinyint(1) NOT NULL DEFAULT 0,
  `date_of_death` date DEFAULT NULL,
  `registered_by` bigint(20) UNSIGNED DEFAULT NULL,
  `mother_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_accounts`
--

CREATE TABLE `patient_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `activation_code` varchar(255) DEFAULT NULL,
  `activation_expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations`
--

CREATE TABLE `payment_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `bill_item_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `physio_episodes`
--

CREATE TABLE `physio_episodes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `region` varchar(100) NOT NULL,
  `complaint` text NOT NULL,
  `assessment` text DEFAULT NULL,
  `pain_initial` tinyint(3) UNSIGNED DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `sessions_planned` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `outcome` varchar(20) DEFAULT NULL,
  `discharge_notes` text DEFAULT NULL,
  `discharged_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `physio_sessions`
--

CREATE TABLE `physio_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `physio_episode_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_date` date NOT NULL,
  `treatments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`treatments`)),
  `pain_before` tinyint(3) UNSIGNED DEFAULT NULL,
  `pain_after` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `therapist_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `postnatal_visits`
--

CREATE TABLE `postnatal_visits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregnancy_id` bigint(20) UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `uterus` varchar(30) DEFAULT NULL,
  `lochia` varchar(30) DEFAULT NULL,
  `breastfeeding` varchar(30) DEFAULT NULL,
  `wound` varchar(30) DEFAULT NULL,
  `mood_concern` tinyint(1) NOT NULL DEFAULT 0,
  `family_planning` varchar(255) DEFAULT NULL,
  `baby_weight_g` smallint(5) UNSIGNED DEFAULT NULL,
  `cord` varchar(30) DEFAULT NULL,
  `jaundice` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `preauthorizations`
--

CREATE TABLE `preauthorizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `insurance_provider_id` bigint(20) UNSIGNED NOT NULL,
  `bill_id` bigint(20) UNSIGNED DEFAULT NULL,
  `services` text NOT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `amount_requested` decimal(12,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'requested',
  `code` varchar(50) DEFAULT NULL,
  `amount_approved` decimal(12,2) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pregnancies`
--

CREATE TABLE `pregnancies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `lmp` date DEFAULT NULL,
  `edd` date NOT NULL,
  `edd_by_scan` tinyint(1) NOT NULL DEFAULT 0,
  `gravida` tinyint(3) UNSIGNED NOT NULL,
  `parity` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `abortions` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `living_children` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `risk_factors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`risk_factors`)),
  `notes` text DEFAULT NULL,
  `labour_started_at` timestamp NULL DEFAULT NULL,
  `end_reason` varchar(255) DEFAULT NULL,
  `booked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `prescription_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `consultation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `prescribed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `dispensed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `prescription_id` bigint(20) UNSIGNED NOT NULL,
  `drug_id` bigint(20) UNSIGNED DEFAULT NULL,
  `drug_name` varchar(255) NOT NULL,
  `dose` varchar(50) NOT NULL,
  `route` varchar(30) NOT NULL,
  `frequency` varchar(20) NOT NULL,
  `duration` varchar(30) NOT NULL,
  `quantity` int(10) UNSIGNED DEFAULT NULL,
  `quantity_dispensed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `billed_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `not_dispensed_reason` varchar(255) DEFAULT NULL,
  `instructions` varchar(255) DEFAULT NULL,
  `allergy_override` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stopped_at` timestamp NULL DEFAULT NULL,
  `stopped_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prices`
--

CREATE TABLE `prices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `billable_type` varchar(255) NOT NULL,
  `billable_id` bigint(20) UNSIGNED NOT NULL,
  `insurance_provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `po_number` varchar(30) NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `item_type` varchar(255) NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity_received` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requisitions`
--

CREATE TABLE `requisitions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `requisition_number` varchar(30) NOT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'submitted',
  `needed_by` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `closed_reason` varchar(255) DEFAULT NULL,
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `issued_by` bigint(20) UNSIGNED DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `requisition_id` bigint(20) UNSIGNED NOT NULL,
  `store_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity_requested` int(10) UNSIGNED NOT NULL,
  `quantity_issued` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sequences`
--

CREATE TABLE `sequences` (
  `name` varchar(50) NOT NULL,
  `next_value` bigint(20) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_messages`
--

CREATE TABLE `sms_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone` varchar(30) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(30) NOT NULL,
  `status` varchar(10) NOT NULL,
  `provider` varchar(20) DEFAULT NULL,
  `provider_message_id` varchar(100) DEFAULT NULL,
  `error` varchar(500) DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `dedupe_key` varchar(100) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_batches`
--

CREATE TABLE `stock_batches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `drug_id` bigint(20) UNSIGNED NOT NULL,
  `stock_receipt_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_number` varchar(50) NOT NULL,
  `expiry_date` date NOT NULL,
  `quantity_received` int(10) UNSIGNED NOT NULL,
  `quantity_on_hand` int(10) UNSIGNED NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `drug_id` bigint(20) UNSIGNED NOT NULL,
  `stock_batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_receipts`
--

CREATE TABLE `stock_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_number` varchar(30) NOT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `received_on` date NOT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_items`
--

CREATE TABLE `store_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'piece',
  `reorder_level` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_on_hand` int(11) NOT NULL DEFAULT 0,
  `average_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_movements`
--

CREATE TABLE `store_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `store_item_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `balance_after` int(11) NOT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoices`
--

CREATE TABLE `supplier_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'unpaid',
  `paid_on` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surgeries`
--

CREATE TABLE `surgeries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `surgery_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pregnancy_id` bigint(20) UNSIGNED DEFAULT NULL,
  `surgical_procedure_id` bigint(20) UNSIGNED DEFAULT NULL,
  `procedure_name` varchar(255) NOT NULL,
  `theatre_id` bigint(20) UNSIGNED NOT NULL,
  `surgeon_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assistant` varchar(255) DEFAULT NULL,
  `anaesthetist_id` bigint(20) UNSIGNED DEFAULT NULL,
  `urgency` varchar(20) NOT NULL DEFAULT 'elective',
  `scheduled_at` datetime NOT NULL,
  `estimated_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `indication` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `booked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `consent_signed` tinyint(1) NOT NULL DEFAULT 0,
  `fasting_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `site_marked` tinyint(1) NOT NULL DEFAULT 0,
  `asa_grade` tinyint(3) UNSIGNED DEFAULT NULL,
  `blood_units_available` tinyint(3) UNSIGNED DEFAULT NULL,
  `preop_notes` text DEFAULT NULL,
  `assessed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assessed_at` timestamp NULL DEFAULT NULL,
  `checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist`)),
  `anaesthesia_type` varchar(30) DEFAULT NULL,
  `airway` varchar(50) DEFAULT NULL,
  `anaesthesia_drugs` text DEFAULT NULL,
  `fluids` text DEFAULT NULL,
  `anaesthesia_notes` text DEFAULT NULL,
  `in_theatre_at` timestamp NULL DEFAULT NULL,
  `incision_at` timestamp NULL DEFAULT NULL,
  `out_at` timestamp NULL DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `procedure_performed` text DEFAULT NULL,
  `blood_loss_ml` smallint(5) UNSIGNED DEFAULT NULL,
  `specimens` varchar(255) DEFAULT NULL,
  `implants` varchar(255) DEFAULT NULL,
  `drains` varchar(255) DEFAULT NULL,
  `closure` varchar(255) DEFAULT NULL,
  `complications` text DEFAULT NULL,
  `postop_orders` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surgery_observations`
--

CREATE TABLE `surgery_observations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `surgery_id` bigint(20) UNSIGNED NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pulse` smallint(5) UNSIGNED DEFAULT NULL,
  `systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `spo2` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surgical_procedures`
--

CREATE TABLE `surgical_procedures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `specialty` varchar(50) NOT NULL,
  `typical_minutes` smallint(5) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `theatres`
--

CREATE TABLE `theatres` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `failed_login_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `dose_label` varchar(30) DEFAULT NULL,
  `age_days` smallint(5) UNSIGNED NOT NULL,
  `route` varchar(20) DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `visit_number` varchar(30) NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `visit_type` varchar(20) NOT NULL DEFAULT 'outpatient',
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `status` varchar(30) NOT NULL,
  `queue_number` varchar(20) NOT NULL,
  `complaint` varchar(255) DEFAULT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `checked_in_by` bigint(20) UNSIGNED DEFAULT NULL,
  `triaged_at` timestamp NULL DEFAULT NULL,
  `consultation_started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `closing_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vital_signs`
--

CREATE TABLE `vital_signs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `visit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `temperature` decimal(4,1) DEFAULT NULL,
  `systolic` smallint(5) UNSIGNED DEFAULT NULL,
  `diastolic` smallint(5) UNSIGNED DEFAULT NULL,
  `pulse` smallint(5) UNSIGNED DEFAULT NULL,
  `respiratory_rate` smallint(5) UNSIGNED DEFAULT NULL,
  `spo2` tinyint(3) UNSIGNED DEFAULT NULL,
  `on_oxygen` tinyint(1) NOT NULL DEFAULT 0,
  `consciousness` char(1) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,1) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
  `pain_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `blood_glucose` decimal(4,1) DEFAULT NULL,
  `news2_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `type` varchar(30) NOT NULL,
  `gender` varchar(10) NOT NULL DEFAULT 'any',
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `service_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admissions`
--
ALTER TABLE `admissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admissions_admission_number_unique` (`admission_number`),
  ADD KEY `admissions_patient_id_foreign` (`patient_id`),
  ADD KEY `admissions_visit_id_foreign` (`visit_id`),
  ADD KEY `admissions_ward_id_foreign` (`ward_id`),
  ADD KEY `admissions_bed_id_foreign` (`bed_id`),
  ADD KEY `admissions_doctor_id_foreign` (`doctor_id`),
  ADD KEY `admissions_admitted_by_foreign` (`admitted_by`),
  ADD KEY `admissions_discharged_by_foreign` (`discharged_by`),
  ADD KEY `admissions_status_index` (`status`);

--
-- Indexes for table `admission_notes`
--
ALTER TABLE `admission_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admission_notes_admission_id_foreign` (`admission_id`),
  ADD KEY `admission_notes_user_id_foreign` (`user_id`);

--
-- Indexes for table `anc_visits`
--
ALTER TABLE `anc_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `anc_visits_pregnancy_id_foreign` (`pregnancy_id`),
  ADD KEY `anc_visits_recorded_by_foreign` (`recorded_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointments_patient_id_foreign` (`patient_id`),
  ADD KEY `appointments_clinic_id_foreign` (`clinic_id`),
  ADD KEY `appointments_doctor_id_foreign` (`doctor_id`),
  ADD KEY `appointments_visit_id_foreign` (`visit_id`),
  ADD KEY `appointments_booked_by_foreign` (`booked_by`),
  ADD KEY `appointments_scheduled_at_index` (`scheduled_at`),
  ADD KEY `appointments_status_index` (`status`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_user_id_foreign` (`user_id`),
  ADD KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  ADD KEY `audit_logs_event_index` (`event`),
  ADD KEY `audit_logs_created_at_index` (`created_at`);

--
-- Indexes for table `babies`
--
ALTER TABLE `babies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `babies_delivery_id_foreign` (`delivery_id`),
  ADD KEY `babies_patient_id_foreign` (`patient_id`);

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `beds_ward_id_label_unique` (`ward_id`,`label`);

--
-- Indexes for table `bed_movements`
--
ALTER TABLE `bed_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bed_movements_admission_id_foreign` (`admission_id`),
  ADD KEY `bed_movements_from_bed_id_foreign` (`from_bed_id`),
  ADD KEY `bed_movements_to_bed_id_foreign` (`to_bed_id`),
  ADD KEY `bed_movements_user_id_foreign` (`user_id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bills_bill_number_unique` (`bill_number`),
  ADD UNIQUE KEY `bills_visit_id_unique` (`visit_id`),
  ADD UNIQUE KEY `bills_admission_id_unique` (`admission_id`),
  ADD KEY `bills_patient_id_foreign` (`patient_id`),
  ADD KEY `bills_insurance_provider_id_foreign` (`insurance_provider_id`),
  ADD KEY `bills_claim_status_index` (`claim_status`),
  ADD KEY `bills_claim_batch_id_foreign` (`claim_batch_id`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_items_bill_id_foreign` (`bill_id`),
  ADD KEY `bill_items_billable_type_billable_id_index` (`billable_type`,`billable_id`),
  ADD KEY `bill_items_source_type_source_id_index` (`source_type`,`source_id`),
  ADD KEY `bill_items_discounted_by_foreign` (`discounted_by`),
  ADD KEY `bill_items_voided_by_foreign` (`voided_by`),
  ADD KEY `bill_items_created_by_foreign` (`created_by`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `claim_batches`
--
ALTER TABLE `claim_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `claim_batches_batch_number_unique` (`batch_number`),
  ADD KEY `claim_batches_insurance_provider_id_foreign` (`insurance_provider_id`),
  ADD KEY `claim_batches_submitted_by_foreign` (`submitted_by`),
  ADD KEY `claim_batches_created_by_foreign` (`created_by`),
  ADD KEY `claim_batches_status_index` (`status`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clinics_name_unique` (`name`),
  ADD UNIQUE KEY `clinics_code_unique` (`code`),
  ADD KEY `clinics_department_id_foreign` (`department_id`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `consultations_visit_id_unique` (`visit_id`),
  ADD KEY `consultations_patient_id_foreign` (`patient_id`),
  ADD KEY `consultations_doctor_id_foreign` (`doctor_id`);

--
-- Indexes for table `consultation_addenda`
--
ALTER TABLE `consultation_addenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_addenda_consultation_id_foreign` (`consultation_id`),
  ADD KEY `consultation_addenda_user_id_foreign` (`user_id`);

--
-- Indexes for table `data_imports`
--
ALTER TABLE `data_imports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `data_imports_user_id_foreign` (`user_id`),
  ADD KEY `data_imports_type_index` (`type`),
  ADD KEY `data_imports_status_index` (`status`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deliveries_pregnancy_id_foreign` (`pregnancy_id`),
  ADD KEY `deliveries_admission_id_foreign` (`admission_id`),
  ADD KEY `deliveries_attended_by_foreign` (`attended_by`);

--
-- Indexes for table `dental_findings`
--
ALTER TABLE `dental_findings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dental_findings_patient_id_foreign` (`patient_id`),
  ADD KEY `dental_findings_visit_id_foreign` (`visit_id`),
  ADD KEY `dental_findings_service_id_foreign` (`service_id`),
  ADD KEY `dental_findings_recorded_by_foreign` (`recorded_by`),
  ADD KEY `dental_findings_completed_by_foreign` (`completed_by`),
  ADD KEY `dental_findings_tooth_index` (`tooth`),
  ADD KEY `dental_findings_status_index` (`status`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_name_unique` (`name`),
  ADD UNIQUE KEY `departments_code_unique` (`code`),
  ADD KEY `departments_head_id_foreign` (`head_id`);

--
-- Indexes for table `diagnoses`
--
ALTER TABLE `diagnoses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `diagnoses_consultation_id_foreign` (`consultation_id`),
  ADD KEY `diagnoses_patient_id_foreign` (`patient_id`),
  ADD KEY `diagnoses_icd10_code_index` (`icd10_code`);

--
-- Indexes for table `drugs`
--
ALTER TABLE `drugs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `drugs_name_strength_form_unique` (`name`,`strength`,`form`);

--
-- Indexes for table `eye_exams`
--
ALTER TABLE `eye_exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eye_exams_patient_id_foreign` (`patient_id`),
  ADD KEY `eye_exams_visit_id_foreign` (`visit_id`),
  ADD KEY `eye_exams_examined_by_foreign` (`examined_by`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `icd10_codes`
--
ALTER TABLE `icd10_codes`
  ADD PRIMARY KEY (`code`),
  ADD KEY `icd10_codes_description_index` (`description`);

--
-- Indexes for table `imaging_attachments`
--
ALTER TABLE `imaging_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `imaging_attachments_imaging_order_id_foreign` (`imaging_order_id`),
  ADD KEY `imaging_attachments_uploaded_by_foreign` (`uploaded_by`);

--
-- Indexes for table `imaging_orders`
--
ALTER TABLE `imaging_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `imaging_orders_order_number_unique` (`order_number`),
  ADD KEY `imaging_orders_patient_id_foreign` (`patient_id`),
  ADD KEY `imaging_orders_visit_id_foreign` (`visit_id`),
  ADD KEY `imaging_orders_consultation_id_foreign` (`consultation_id`),
  ADD KEY `imaging_orders_ordered_by_foreign` (`ordered_by`),
  ADD KEY `imaging_orders_imaging_test_id_foreign` (`imaging_test_id`),
  ADD KEY `imaging_orders_status_index` (`status`),
  ADD KEY `imaging_orders_performed_by_foreign` (`performed_by`),
  ADD KEY `imaging_orders_reported_by_foreign` (`reported_by`),
  ADD KEY `imaging_orders_admission_id_foreign` (`admission_id`);

--
-- Indexes for table `imaging_tests`
--
ALTER TABLE `imaging_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `imaging_tests_code_unique` (`code`),
  ADD UNIQUE KEY `imaging_tests_name_unique` (`name`);

--
-- Indexes for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `immunizations_patient_id_vaccine_id_unique` (`patient_id`,`vaccine_id`),
  ADD KEY `immunizations_vaccine_id_foreign` (`vaccine_id`),
  ADD KEY `immunizations_given_by_foreign` (`given_by`);

--
-- Indexes for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `insurance_providers_name_unique` (`name`),
  ADD UNIQUE KEY `insurance_providers_code_unique` (`code`);

--
-- Indexes for table `integration_messages`
--
ALTER TABLE `integration_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `integration_messages_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  ADD KEY `integration_messages_user_id_foreign` (`user_id`),
  ADD KEY `integration_messages_channel_index` (`channel`),
  ADD KEY `integration_messages_status_index` (`status`),
  ADD KEY `integration_messages_reference_index` (`reference`),
  ADD KEY `integration_messages_created_at_index` (`created_at`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_analyzers`
--
ALTER TABLE `lab_analyzers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_analyzers_code_unique` (`code`),
  ADD UNIQUE KEY `lab_analyzers_token_hash_unique` (`token_hash`),
  ADD KEY `lab_analyzers_user_id_foreign` (`user_id`);

--
-- Indexes for table `lab_analyzer_mappings`
--
ALTER TABLE `lab_analyzer_mappings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_analyzer_mappings_lab_analyzer_id_analyzer_code_unique` (`lab_analyzer_id`,`analyzer_code`),
  ADD KEY `lab_analyzer_mappings_lab_test_id_foreign` (`lab_test_id`),
  ADD KEY `lab_analyzer_mappings_lab_test_parameter_id_foreign` (`lab_test_parameter_id`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_orders_order_number_unique` (`order_number`),
  ADD KEY `lab_orders_patient_id_foreign` (`patient_id`),
  ADD KEY `lab_orders_visit_id_foreign` (`visit_id`),
  ADD KEY `lab_orders_consultation_id_foreign` (`consultation_id`),
  ADD KEY `lab_orders_ordered_by_foreign` (`ordered_by`),
  ADD KEY `lab_orders_status_index` (`status`),
  ADD KEY `lab_orders_collected_by_foreign` (`collected_by`),
  ADD KEY `lab_orders_admission_id_foreign` (`admission_id`);

--
-- Indexes for table `lab_order_items`
--
ALTER TABLE `lab_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_order_items_lab_order_id_foreign` (`lab_order_id`),
  ADD KEY `lab_order_items_lab_test_id_foreign` (`lab_test_id`),
  ADD KEY `lab_order_items_entered_by_foreign` (`entered_by`),
  ADD KEY `lab_order_items_verified_by_foreign` (`verified_by`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_results_lab_order_item_id_foreign` (`lab_order_item_id`),
  ADD KEY `lab_results_lab_test_parameter_id_foreign` (`lab_test_parameter_id`);

--
-- Indexes for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_tests_code_unique` (`code`),
  ADD UNIQUE KEY `lab_tests_name_unique` (`name`);

--
-- Indexes for table `lab_test_parameters`
--
ALTER TABLE `lab_test_parameters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_test_parameters_lab_test_id_foreign` (`lab_test_id`);

--
-- Indexes for table `medication_administrations`
--
ALTER TABLE `medication_administrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medication_administrations_admission_id_foreign` (`admission_id`),
  ADD KEY `medication_administrations_prescription_item_id_foreign` (`prescription_item_id`),
  ADD KEY `medication_administrations_user_id_foreign` (`user_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `nursing_notes`
--
ALTER TABLE `nursing_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nursing_notes_visit_id_foreign` (`visit_id`),
  ADD KEY `nursing_notes_user_id_foreign` (`user_id`),
  ADD KEY `nursing_notes_patient_id_created_at_index` (`patient_id`,`created_at`);

--
-- Indexes for table `online_payments`
--
ALTER TABLE `online_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `online_payments_reference_unique` (`reference`),
  ADD KEY `online_payments_patient_id_foreign` (`patient_id`),
  ADD KEY `online_payments_payment_id_foreign` (`payment_id`),
  ADD KEY `online_payments_created_by_foreign` (`created_by`),
  ADD KEY `online_payments_status_index` (`status`);

--
-- Indexes for table `partograph_entries`
--
ALTER TABLE `partograph_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partograph_entries_pregnancy_id_foreign` (`pregnancy_id`),
  ADD KEY `partograph_entries_recorded_by_foreign` (`recorded_by`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patients_hospital_number_unique` (`hospital_number`),
  ADD KEY `patients_insurance_provider_id_foreign` (`insurance_provider_id`),
  ADD KEY `patients_registered_by_foreign` (`registered_by`),
  ADD KEY `patients_last_name_first_name_index` (`last_name`,`first_name`),
  ADD KEY `patients_legacy_number_index` (`legacy_number`),
  ADD KEY `patients_national_id_index` (`national_id`),
  ADD KEY `patients_phone_index` (`phone`),
  ADD KEY `patients_insurance_number_index` (`insurance_number`),
  ADD KEY `patients_mother_id_foreign` (`mother_id`);

--
-- Indexes for table `patient_accounts`
--
ALTER TABLE `patient_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_accounts_patient_id_unique` (`patient_id`),
  ADD KEY `patient_accounts_created_by_foreign` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_receipt_number_unique` (`receipt_number`),
  ADD KEY `payments_patient_id_foreign` (`patient_id`),
  ADD KEY `payments_received_by_foreign` (`received_by`),
  ADD KEY `payments_voided_by_foreign` (`voided_by`),
  ADD KEY `payments_created_at_index` (`created_at`);

--
-- Indexes for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_allocations_payment_id_foreign` (`payment_id`),
  ADD KEY `payment_allocations_bill_item_id_foreign` (`bill_item_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `physio_episodes`
--
ALTER TABLE `physio_episodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `physio_episodes_patient_id_foreign` (`patient_id`),
  ADD KEY `physio_episodes_visit_id_foreign` (`visit_id`),
  ADD KEY `physio_episodes_created_by_foreign` (`created_by`),
  ADD KEY `physio_episodes_status_index` (`status`);

--
-- Indexes for table `physio_sessions`
--
ALTER TABLE `physio_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `physio_sessions_physio_episode_id_foreign` (`physio_episode_id`),
  ADD KEY `physio_sessions_visit_id_foreign` (`visit_id`),
  ADD KEY `physio_sessions_therapist_id_foreign` (`therapist_id`);

--
-- Indexes for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `postnatal_visits_pregnancy_id_foreign` (`pregnancy_id`),
  ADD KEY `postnatal_visits_recorded_by_foreign` (`recorded_by`);

--
-- Indexes for table `preauthorizations`
--
ALTER TABLE `preauthorizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `preauthorizations_patient_id_foreign` (`patient_id`),
  ADD KEY `preauthorizations_insurance_provider_id_foreign` (`insurance_provider_id`),
  ADD KEY `preauthorizations_bill_id_foreign` (`bill_id`),
  ADD KEY `preauthorizations_requested_by_foreign` (`requested_by`),
  ADD KEY `preauthorizations_decided_by_foreign` (`decided_by`),
  ADD KEY `preauthorizations_status_index` (`status`),
  ADD KEY `preauthorizations_code_index` (`code`);

--
-- Indexes for table `pregnancies`
--
ALTER TABLE `pregnancies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pregnancies_patient_id_foreign` (`patient_id`),
  ADD KEY `pregnancies_booked_by_foreign` (`booked_by`),
  ADD KEY `pregnancies_status_index` (`status`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prescriptions_prescription_number_unique` (`prescription_number`),
  ADD KEY `prescriptions_patient_id_foreign` (`patient_id`),
  ADD KEY `prescriptions_visit_id_foreign` (`visit_id`),
  ADD KEY `prescriptions_consultation_id_foreign` (`consultation_id`),
  ADD KEY `prescriptions_prescribed_by_foreign` (`prescribed_by`),
  ADD KEY `prescriptions_status_index` (`status`),
  ADD KEY `prescriptions_dispensed_by_foreign` (`dispensed_by`),
  ADD KEY `prescriptions_admission_id_foreign` (`admission_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_items_prescription_id_foreign` (`prescription_id`),
  ADD KEY `prescription_items_drug_id_foreign` (`drug_id`),
  ADD KEY `prescription_items_stopped_by_foreign` (`stopped_by`);

--
-- Indexes for table `prices`
--
ALTER TABLE `prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `prices_unique` (`billable_type`,`billable_id`,`insurance_provider_id`),
  ADD KEY `prices_billable_type_billable_id_index` (`billable_type`,`billable_id`),
  ADD KEY `prices_insurance_provider_id_foreign` (`insurance_provider_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_orders_po_number_unique` (`po_number`),
  ADD KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  ADD KEY `purchase_orders_created_by_foreign` (`created_by`),
  ADD KEY `purchase_orders_approved_by_foreign` (`approved_by`),
  ADD KEY `purchase_orders_status_index` (`status`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `purchase_order_items_item_type_item_id_index` (`item_type`,`item_id`);

--
-- Indexes for table `requisitions`
--
ALTER TABLE `requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisitions_requisition_number_unique` (`requisition_number`),
  ADD KEY `requisitions_department_id_foreign` (`department_id`),
  ADD KEY `requisitions_requested_by_foreign` (`requested_by`),
  ADD KEY `requisitions_issued_by_foreign` (`issued_by`),
  ADD KEY `requisitions_status_index` (`status`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisition_items_requisition_id_foreign` (`requisition_id`),
  ADD KEY `requisition_items_store_item_id_foreign` (`store_item_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sequences`
--
ALTER TABLE `sequences`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `services_code_unique` (`code`),
  ADD KEY `services_clinic_id_foreign` (`clinic_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`);

--
-- Indexes for table `sms_messages`
--
ALTER TABLE `sms_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sms_messages_dedupe_key_unique` (`dedupe_key`),
  ADD KEY `sms_messages_patient_id_foreign` (`patient_id`),
  ADD KEY `sms_messages_created_by_foreign` (`created_by`),
  ADD KEY `sms_messages_type_index` (`type`),
  ADD KEY `sms_messages_status_index` (`status`);

--
-- Indexes for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_batches_stock_receipt_id_foreign` (`stock_receipt_id`),
  ADD KEY `stock_batches_drug_id_quantity_on_hand_expiry_date_index` (`drug_id`,`quantity_on_hand`,`expiry_date`),
  ADD KEY `stock_batches_expiry_date_index` (`expiry_date`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_movements_drug_id_foreign` (`drug_id`),
  ADD KEY `stock_movements_stock_batch_id_foreign` (`stock_batch_id`),
  ADD KEY `stock_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  ADD KEY `stock_movements_user_id_foreign` (`user_id`),
  ADD KEY `stock_movements_created_at_index` (`created_at`);

--
-- Indexes for table `stock_receipts`
--
ALTER TABLE `stock_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stock_receipts_receipt_number_unique` (`receipt_number`),
  ADD KEY `stock_receipts_supplier_id_foreign` (`supplier_id`),
  ADD KEY `stock_receipts_received_by_foreign` (`received_by`);

--
-- Indexes for table `store_items`
--
ALTER TABLE `store_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_items_code_unique` (`code`),
  ADD KEY `store_items_category_index` (`category`);

--
-- Indexes for table `store_movements`
--
ALTER TABLE `store_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_movements_store_item_id_foreign` (`store_item_id`),
  ADD KEY `store_movements_department_id_foreign` (`department_id`),
  ADD KEY `store_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  ADD KEY `store_movements_user_id_foreign` (`user_id`),
  ADD KEY `store_movements_type_index` (`type`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `suppliers_name_unique` (`name`);

--
-- Indexes for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_invoices_supplier_id_invoice_number_unique` (`supplier_id`,`invoice_number`),
  ADD KEY `supplier_invoices_purchase_order_id_foreign` (`purchase_order_id`),
  ADD KEY `supplier_invoices_recorded_by_foreign` (`recorded_by`),
  ADD KEY `supplier_invoices_paid_by_foreign` (`paid_by`),
  ADD KEY `supplier_invoices_due_date_index` (`due_date`),
  ADD KEY `supplier_invoices_status_index` (`status`);

--
-- Indexes for table `surgeries`
--
ALTER TABLE `surgeries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surgeries_surgery_number_unique` (`surgery_number`),
  ADD KEY `surgeries_patient_id_foreign` (`patient_id`),
  ADD KEY `surgeries_admission_id_foreign` (`admission_id`),
  ADD KEY `surgeries_pregnancy_id_foreign` (`pregnancy_id`),
  ADD KEY `surgeries_surgical_procedure_id_foreign` (`surgical_procedure_id`),
  ADD KEY `surgeries_theatre_id_foreign` (`theatre_id`),
  ADD KEY `surgeries_surgeon_id_foreign` (`surgeon_id`),
  ADD KEY `surgeries_anaesthetist_id_foreign` (`anaesthetist_id`),
  ADD KEY `surgeries_booked_by_foreign` (`booked_by`),
  ADD KEY `surgeries_assessed_by_foreign` (`assessed_by`),
  ADD KEY `surgeries_scheduled_at_index` (`scheduled_at`),
  ADD KEY `surgeries_status_index` (`status`);

--
-- Indexes for table `surgery_observations`
--
ALTER TABLE `surgery_observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surgery_observations_surgery_id_foreign` (`surgery_id`),
  ADD KEY `surgery_observations_recorded_by_foreign` (`recorded_by`);

--
-- Indexes for table `surgical_procedures`
--
ALTER TABLE `surgical_procedures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `surgical_procedures_code_unique` (`code`),
  ADD UNIQUE KEY `surgical_procedures_name_unique` (`name`);

--
-- Indexes for table `theatres`
--
ALTER TABLE `theatres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `theatres_code_unique` (`code`),
  ADD UNIQUE KEY `theatres_name_unique` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_staff_id_unique` (`staff_id`),
  ADD KEY `users_department_id_foreign` (`department_id`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vaccines_code_unique` (`code`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visits_visit_number_unique` (`visit_number`),
  ADD KEY `visits_patient_id_foreign` (`patient_id`),
  ADD KEY `visits_doctor_id_foreign` (`doctor_id`),
  ADD KEY `visits_checked_in_by_foreign` (`checked_in_by`),
  ADD KEY `visits_clinic_id_checked_in_at_index` (`clinic_id`,`checked_in_at`),
  ADD KEY `visits_status_index` (`status`);

--
-- Indexes for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vital_signs_visit_id_foreign` (`visit_id`),
  ADD KEY `vital_signs_recorded_by_foreign` (`recorded_by`),
  ADD KEY `vital_signs_voided_by_foreign` (`voided_by`),
  ADD KEY `vital_signs_patient_id_recorded_at_index` (`patient_id`,`recorded_at`),
  ADD KEY `vital_signs_recorded_at_index` (`recorded_at`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wards_name_unique` (`name`),
  ADD UNIQUE KEY `wards_code_unique` (`code`),
  ADD KEY `wards_department_id_foreign` (`department_id`),
  ADD KEY `wards_service_id_foreign` (`service_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admissions`
--
ALTER TABLE `admissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admission_notes`
--
ALTER TABLE `admission_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anc_visits`
--
ALTER TABLE `anc_visits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `babies`
--
ALTER TABLE `babies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bed_movements`
--
ALTER TABLE `bed_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `claim_batches`
--
ALTER TABLE `claim_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultation_addenda`
--
ALTER TABLE `consultation_addenda`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_imports`
--
ALTER TABLE `data_imports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dental_findings`
--
ALTER TABLE `dental_findings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diagnoses`
--
ALTER TABLE `diagnoses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drugs`
--
ALTER TABLE `drugs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eye_exams`
--
ALTER TABLE `eye_exams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `imaging_attachments`
--
ALTER TABLE `imaging_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `imaging_orders`
--
ALTER TABLE `imaging_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `imaging_tests`
--
ALTER TABLE `imaging_tests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_providers`
--
ALTER TABLE `insurance_providers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integration_messages`
--
ALTER TABLE `integration_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_analyzers`
--
ALTER TABLE `lab_analyzers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_analyzer_mappings`
--
ALTER TABLE `lab_analyzer_mappings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_order_items`
--
ALTER TABLE `lab_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_test_parameters`
--
ALTER TABLE `lab_test_parameters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medication_administrations`
--
ALTER TABLE `medication_administrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nursing_notes`
--
ALTER TABLE `nursing_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `online_payments`
--
ALTER TABLE `online_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `partograph_entries`
--
ALTER TABLE `partograph_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_accounts`
--
ALTER TABLE `patient_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `physio_episodes`
--
ALTER TABLE `physio_episodes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `physio_sessions`
--
ALTER TABLE `physio_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `preauthorizations`
--
ALTER TABLE `preauthorizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pregnancies`
--
ALTER TABLE `pregnancies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prices`
--
ALTER TABLE `prices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requisitions`
--
ALTER TABLE `requisitions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_messages`
--
ALTER TABLE `sms_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_batches`
--
ALTER TABLE `stock_batches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_receipts`
--
ALTER TABLE `stock_receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_items`
--
ALTER TABLE `store_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_movements`
--
ALTER TABLE `store_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surgeries`
--
ALTER TABLE `surgeries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surgery_observations`
--
ALTER TABLE `surgery_observations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surgical_procedures`
--
ALTER TABLE `surgical_procedures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `theatres`
--
ALTER TABLE `theatres`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vital_signs`
--
ALTER TABLE `vital_signs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admissions`
--
ALTER TABLE `admissions`
  ADD CONSTRAINT `admissions_admitted_by_foreign` FOREIGN KEY (`admitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admissions_bed_id_foreign` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admissions_discharged_by_foreign` FOREIGN KEY (`discharged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admissions_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admissions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admissions_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admissions_ward_id_foreign` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`);

--
-- Constraints for table `admission_notes`
--
ALTER TABLE `admission_notes`
  ADD CONSTRAINT `admission_notes_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admission_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `anc_visits`
--
ALTER TABLE `anc_visits`
  ADD CONSTRAINT `anc_visits_pregnancy_id_foreign` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `anc_visits_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_booked_by_foreign` FOREIGN KEY (`booked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `appointments_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `babies`
--
ALTER TABLE `babies`
  ADD CONSTRAINT `babies_delivery_id_foreign` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `babies_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ward_id_foreign` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bed_movements`
--
ALTER TABLE `bed_movements`
  ADD CONSTRAINT `bed_movements_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bed_movements_from_bed_id_foreign` FOREIGN KEY (`from_bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bed_movements_to_bed_id_foreign` FOREIGN KEY (`to_bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bed_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_claim_batch_id_foreign` FOREIGN KEY (`claim_batch_id`) REFERENCES `claim_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_insurance_provider_id_foreign` FOREIGN KEY (`insurance_provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bills_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bills_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bill_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bill_items_discounted_by_foreign` FOREIGN KEY (`discounted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bill_items_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `claim_batches`
--
ALTER TABLE `claim_batches`
  ADD CONSTRAINT `claim_batches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `claim_batches_insurance_provider_id_foreign` FOREIGN KEY (`insurance_provider_id`) REFERENCES `insurance_providers` (`id`),
  ADD CONSTRAINT `claim_batches_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinics`
--
ALTER TABLE `clinics`
  ADD CONSTRAINT `clinics_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `consultations_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultations_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_addenda`
--
ALTER TABLE `consultation_addenda`
  ADD CONSTRAINT `consultation_addenda_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultation_addenda_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `data_imports`
--
ALTER TABLE `data_imports`
  ADD CONSTRAINT `data_imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `deliveries_attended_by_foreign` FOREIGN KEY (`attended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `deliveries_pregnancy_id_foreign` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dental_findings`
--
ALTER TABLE `dental_findings`
  ADD CONSTRAINT `dental_findings_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dental_findings_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dental_findings_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dental_findings_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dental_findings_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_head_id_foreign` FOREIGN KEY (`head_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `diagnoses`
--
ALTER TABLE `diagnoses`
  ADD CONSTRAINT `diagnoses_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `diagnoses_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `eye_exams`
--
ALTER TABLE `eye_exams`
  ADD CONSTRAINT `eye_exams_examined_by_foreign` FOREIGN KEY (`examined_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `eye_exams_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eye_exams_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `imaging_attachments`
--
ALTER TABLE `imaging_attachments`
  ADD CONSTRAINT `imaging_attachments_imaging_order_id_foreign` FOREIGN KEY (`imaging_order_id`) REFERENCES `imaging_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `imaging_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `imaging_orders`
--
ALTER TABLE `imaging_orders`
  ADD CONSTRAINT `imaging_orders_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `imaging_orders_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `imaging_orders_imaging_test_id_foreign` FOREIGN KEY (`imaging_test_id`) REFERENCES `imaging_tests` (`id`),
  ADD CONSTRAINT `imaging_orders_ordered_by_foreign` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `imaging_orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `imaging_orders_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `imaging_orders_reported_by_foreign` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `imaging_orders_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD CONSTRAINT `immunizations_given_by_foreign` FOREIGN KEY (`given_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `immunizations_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `immunizations_vaccine_id_foreign` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`);

--
-- Constraints for table `integration_messages`
--
ALTER TABLE `integration_messages`
  ADD CONSTRAINT `integration_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_analyzers`
--
ALTER TABLE `lab_analyzers`
  ADD CONSTRAINT `lab_analyzers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lab_analyzer_mappings`
--
ALTER TABLE `lab_analyzer_mappings`
  ADD CONSTRAINT `lab_analyzer_mappings_lab_analyzer_id_foreign` FOREIGN KEY (`lab_analyzer_id`) REFERENCES `lab_analyzers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_analyzer_mappings_lab_test_id_foreign` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_analyzer_mappings_lab_test_parameter_id_foreign` FOREIGN KEY (`lab_test_parameter_id`) REFERENCES `lab_test_parameters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD CONSTRAINT `lab_orders_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_orders_collected_by_foreign` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_orders_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_orders_ordered_by_foreign` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_orders_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_order_items`
--
ALTER TABLE `lab_order_items`
  ADD CONSTRAINT `lab_order_items_entered_by_foreign` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lab_order_items_lab_order_id_foreign` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_order_items_lab_test_id_foreign` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`),
  ADD CONSTRAINT `lab_order_items_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `lab_results_lab_order_item_id_foreign` FOREIGN KEY (`lab_order_item_id`) REFERENCES `lab_order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_results_lab_test_parameter_id_foreign` FOREIGN KEY (`lab_test_parameter_id`) REFERENCES `lab_test_parameters` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_test_parameters`
--
ALTER TABLE `lab_test_parameters`
  ADD CONSTRAINT `lab_test_parameters_lab_test_id_foreign` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medication_administrations`
--
ALTER TABLE `medication_administrations`
  ADD CONSTRAINT `medication_administrations_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medication_administrations_prescription_item_id_foreign` FOREIGN KEY (`prescription_item_id`) REFERENCES `prescription_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medication_administrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nursing_notes`
--
ALTER TABLE `nursing_notes`
  ADD CONSTRAINT `nursing_notes_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nursing_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `nursing_notes_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `online_payments`
--
ALTER TABLE `online_payments`
  ADD CONSTRAINT `online_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `online_payments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `online_payments_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `partograph_entries`
--
ALTER TABLE `partograph_entries`
  ADD CONSTRAINT `partograph_entries_pregnancy_id_foreign` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `partograph_entries_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_insurance_provider_id_foreign` FOREIGN KEY (`insurance_provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_mother_id_foreign` FOREIGN KEY (`mother_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patients_registered_by_foreign` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_accounts`
--
ALTER TABLE `patient_accounts`
  ADD CONSTRAINT `patient_accounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_accounts_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD CONSTRAINT `payment_allocations_bill_item_id_foreign` FOREIGN KEY (`bill_item_id`) REFERENCES `bill_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_allocations_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `physio_episodes`
--
ALTER TABLE `physio_episodes`
  ADD CONSTRAINT `physio_episodes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `physio_episodes_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `physio_episodes_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `physio_sessions`
--
ALTER TABLE `physio_sessions`
  ADD CONSTRAINT `physio_sessions_physio_episode_id_foreign` FOREIGN KEY (`physio_episode_id`) REFERENCES `physio_episodes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `physio_sessions_therapist_id_foreign` FOREIGN KEY (`therapist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `physio_sessions_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `postnatal_visits`
--
ALTER TABLE `postnatal_visits`
  ADD CONSTRAINT `postnatal_visits_pregnancy_id_foreign` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `postnatal_visits_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `preauthorizations`
--
ALTER TABLE `preauthorizations`
  ADD CONSTRAINT `preauthorizations_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `preauthorizations_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `preauthorizations_insurance_provider_id_foreign` FOREIGN KEY (`insurance_provider_id`) REFERENCES `insurance_providers` (`id`),
  ADD CONSTRAINT `preauthorizations_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `preauthorizations_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pregnancies`
--
ALTER TABLE `pregnancies`
  ADD CONSTRAINT `pregnancies_booked_by_foreign` FOREIGN KEY (`booked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pregnancies_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_dispensed_by_foreign` FOREIGN KEY (`dispensed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_prescribed_by_foreign` FOREIGN KEY (`prescribed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_drug_id_foreign` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescription_items_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_items_stopped_by_foreign` FOREIGN KEY (`stopped_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prices`
--
ALTER TABLE `prices`
  ADD CONSTRAINT `prices_insurance_provider_id_foreign` FOREIGN KEY (`insurance_provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requisitions`
--
ALTER TABLE `requisitions`
  ADD CONSTRAINT `requisitions_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `requisitions_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requisitions_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD CONSTRAINT `requisition_items_requisition_id_foreign` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requisition_items_store_item_id_foreign` FOREIGN KEY (`store_item_id`) REFERENCES `store_items` (`id`);

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sms_messages`
--
ALTER TABLE `sms_messages`
  ADD CONSTRAINT `sms_messages_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sms_messages_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_batches`
--
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `stock_batches_drug_id_foreign` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `stock_batches_stock_receipt_id_foreign` FOREIGN KEY (`stock_receipt_id`) REFERENCES `stock_receipts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_drug_id_foreign` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`id`),
  ADD CONSTRAINT `stock_movements_stock_batch_id_foreign` FOREIGN KEY (`stock_batch_id`) REFERENCES `stock_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_receipts`
--
ALTER TABLE `stock_receipts`
  ADD CONSTRAINT `stock_receipts_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_receipts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `store_movements`
--
ALTER TABLE `store_movements`
  ADD CONSTRAINT `store_movements_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `store_movements_store_item_id_foreign` FOREIGN KEY (`store_item_id`) REFERENCES `store_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD CONSTRAINT `supplier_invoices_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_invoices_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_invoices_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_invoices_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `surgeries`
--
ALTER TABLE `surgeries`
  ADD CONSTRAINT `surgeries_admission_id_foreign` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_anaesthetist_id_foreign` FOREIGN KEY (`anaesthetist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_assessed_by_foreign` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_booked_by_foreign` FOREIGN KEY (`booked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `surgeries_pregnancy_id_foreign` FOREIGN KEY (`pregnancy_id`) REFERENCES `pregnancies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_surgeon_id_foreign` FOREIGN KEY (`surgeon_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_surgical_procedure_id_foreign` FOREIGN KEY (`surgical_procedure_id`) REFERENCES `surgical_procedures` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgeries_theatre_id_foreign` FOREIGN KEY (`theatre_id`) REFERENCES `theatres` (`id`);

--
-- Constraints for table `surgery_observations`
--
ALTER TABLE `surgery_observations`
  ADD CONSTRAINT `surgery_observations_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surgery_observations_surgery_id_foreign` FOREIGN KEY (`surgery_id`) REFERENCES `surgeries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_checked_in_by_foreign` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `visits_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `visits_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vital_signs`
--
ALTER TABLE `vital_signs`
  ADD CONSTRAINT `vital_signs_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vital_signs_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vital_signs_visit_id_foreign` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vital_signs_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wards`
--
ALTER TABLE `wards`
  ADD CONSTRAINT `wards_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `wards_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
