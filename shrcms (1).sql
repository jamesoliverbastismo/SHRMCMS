-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 19, 2026 at 02:59 AM
-- Server version: 8.4.3
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shrcms`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int NOT NULL,
  `admin_id_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `student_id_no` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `admin_id_no`, `username`, `email`, `password`, `created_at`, `student_id_no`) VALUES
(1, 'ADMIN-0001', 'admin', 'admin@shrcms.com', '$2y$12$O.JrdKbw8Az0lTIV0hCHMewOpJ5kZuFVUQw4PaTGMK4ahhPFwn8hK', '2026-10-13 05:56:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clinic_passes`
--

CREATE TABLE `clinic_passes` (
  `id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pass_date` datetime NOT NULL,
  `pass_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Issued',
  `authorization_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Authorized',
  `return_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `returned_at` datetime DEFAULT NULL,
  `issued_by_staff_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_referrals`
--

CREATE TABLE `clinic_referrals` (
  `id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `referral_date` date NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `destination` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `follow_up_status` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending Follow-Up',
  `action_required` tinyint(1) NOT NULL DEFAULT '1',
  `follow_up_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recorded_by_staff_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int NOT NULL,
  `student_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `consultation_date` date NOT NULL,
  `reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `staff_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `student_id`, `consultation_date`, `reason`, `remarks`, `staff_name`, `created_at`) VALUES
(1, 'scc-000-202344', '2026-10-01', 'Stomachache', 'okay', 'shani', '2026-10-17 05:29:00'),
(2, '1', '2026-10-17', 'fever', 'give biogesic', 'shani', '2026-10-17 06:42:50'),
(3, 'scc-00-2024-987634', '2026-10-17', 'General Check-up', '', 'shani', '2026-10-17 07:42:46'),
(4, 'scc-00-2024-987634', '2026-10-17', 'General Check-up', '', 'shani', '2026-10-17 07:44:29'),
(5, 'scc-00-2024-987634', '2026-10-17', 'General Check-up', '', 'shani', '2026-10-17 13:23:04'),
(6, '3', '2026-10-31', 'Fever / Headache', 'pabinisaya lang daii', 'dutctmilk', '2026-10-31 01:29:58'),
(7, '5', '2026-10-31', 'Fever / Headache', 'pabinisaya daii!!!!', 'dutctmilk', '2026-10-31 02:13:06');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_inventory`
--

CREATE TABLE `equipment_inventory` (
  `id` int NOT NULL,
  `equipment_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `equipment_condition` enum('Good','Needs Maintenance','Under Repair','Damaged') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Good',
  `date_acquired` date DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `location` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_inventory`
--

INSERT INTO `equipment_inventory` (`id`, `equipment_name`, `category`, `quantity`, `equipment_condition`, `date_acquired`, `last_maintenance`, `next_maintenance`, `location`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'digital thermometer', 'diagnostic equipment', 5, 'Good', '2026-10-01', '2026-10-13', '2027-06-17', 'school clinic', '', '2026-10-17 06:55:27', '2026-10-17 06:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `health_documentation`
--

CREATE TABLE `health_documentation` (
  `id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `examination_date` date NOT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `findings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recorded_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_documentation`
--

INSERT INTO `health_documentation` (`id`, `student_id_no`, `examination_date`, `height_cm`, `weight_kg`, `temperature`, `blood_pressure`, `findings`, `remarks`, `recorded_by`, `created_at`) VALUES
(2, 'SCC-22-00016716', '2026-10-31', 150.00, 55.00, 36.5, '80', 'fine', 'pagkulkog', 'dutctmilk', '2026-10-31 02:15:01'),
(3, 'SCC-22-00022156', '2026-11-17', 164.98, 55.00, 36.5, '120/80', 'NORMAL', '', 'olivia', '2026-11-17 08:30:16');

-- --------------------------------------------------------

--
-- Table structure for table `incident_records`
--

CREATE TABLE `incident_records` (
  `id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `incident_datetime` datetime NOT NULL,
  `location` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `circumstances` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_aid_action` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `referral_needed` tinyint(1) NOT NULL DEFAULT '0',
  `follow_up_status` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Open',
  `outcome_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recorded_by_staff_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `initial_password_changes`
--

CREATE TABLE `initial_password_changes` (
  `account_type` enum('student','staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `account_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_certificates`
--

CREATE TABLE `medical_certificates` (
  `id` int NOT NULL,
  `examination_id` int NOT NULL,
  `student_id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `certificate_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `medical_findings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `recommendations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `issued_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `issued_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_examinations`
--

CREATE TABLE `medical_examinations` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `examination_date` date NOT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allergies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `current_medications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `medical_history` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `menstrual_history` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alcohol_use` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tobacco_use` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_tuberculosis` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_hypertension` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_diabetes` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_heart_disease` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_other` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `physical_examination` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `assessment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recommendations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recorded_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicine_inventory`
--

CREATE TABLE `medicine_inventory` (
  `id` int NOT NULL,
  `medicine_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pcs',
  `reorder_level` int NOT NULL DEFAULT '10',
  `expiration_date` date DEFAULT NULL,
  `supplier` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `storage_location` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_inventory`
--

INSERT INTO `medicine_inventory` (`id`, `medicine_name`, `category`, `quantity`, `unit`, `reorder_level`, `expiration_date`, `supplier`, `storage_location`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'cetirizine', 'allergy aid', 100, 'pcs', 10, '2028-11-15', 'mercury drug', 'box', '', '2026-10-17 05:50:56', '2026-10-17 06:13:19'),
(2, 'biogesic', 'fever reducer', 5, 'box', 8, '2027-05-17', 'mercury drug', 'box2', '', '2026-10-17 05:54:53', '2026-10-17 06:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `med_cert_requests`
--

CREATE TABLE `med_cert_requests` (
  `id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('Pending','Approved','Completed','Rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `diagnosis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recommendation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `qr_code_hash` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approved_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `student_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `age` smallint UNSIGNED DEFAULT NULL,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allergies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `current_medications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `medical_history` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `menstrual_history` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alcohol_use` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tobacco_use` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_tuberculosis` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_hypertension` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_diabetes` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_heart_disease` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `family_history_other` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `physical_findings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `examination_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `med_cert_requests`
--

INSERT INTO `med_cert_requests` (`id`, `student_id`, `student_id_no`, `purpose`, `status`, `diagnosis`, `recommendation`, `qr_code_hash`, `approved_by`, `issued_date`, `created_at`, `student_name`, `course_year`, `age`, `gender`, `contact`, `email`, `allergies`, `current_medications`, `medical_history`, `menstrual_history`, `alcohol_use`, `tobacco_use`, `family_history_tuberculosis`, `family_history_hypertension`, `family_history_diabetes`, `family_history_heart_disease`, `family_history_other`, `height_cm`, `weight_kg`, `temperature`, `blood_pressure`, `physical_findings`, `examination_date`) VALUES
(1, 5, 'SCC-22-00016716', 'sakit ako bilat', 'Completed', 'pagkulkog', 'augh augh', 'MC-AF34EFB7', 'dutctmilk', '2026-10-31', '2026-10-31 03:03:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 10, 'SCC-22-00022156', 'women basketball', 'Approved', '', '', 'MC-9103FD49A9D534F61058F53BC47776E2', 'olivia', '2026-11-17', '2026-11-17 08:28:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 45.00, 56.00, 35.0, '120/80', 'illegible', '2026-11-17'),
(5, 10, 'SCC-22-00022156', 'SPORTS', 'Pending', NULL, NULL, NULL, NULL, NULL, '2026-11-17 09:00:35', 'Kisha Nicole Paran', 'BSIT 3RD YEAR', 21, 'Female', '09354629968', 'kishanicoleparan@gmail.com', 'NONE', 'NONE', 'NONE', 'Irregular', 'Yes', 'No', 'No', 'Yes', 'No', 'No', '', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int NOT NULL,
  `staff_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_id_no`, `password`, `fullname`, `role`, `email`, `id_document`, `created_at`) VALUES
(1, 'STAFF-0001', '$2y$12$sGBiq12NiW6qFwiO43ztPuY1HPasWZ6tqtLdDHrT/rPmyBUUdVwuy', 'olivia', 'Staff', 'jamesoliverbastismo35@gmail.com', NULL, '2026-10-13 05:19:31'),
(2, 'STAFF-0002', NULL, 'James oliver Bastismo', 'Staff', 'jamesoliverbastismo188@gmail.com', NULL, '2026-10-13 05:47:41'),
(4, 'STAFF-0004', NULL, 'dutctmilk', 'Staff', 'dutchmilk@gmail.com', NULL, '2026-10-31 01:24:02');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `age` int DEFAULT NULL,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allergies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `known_conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id_no`, `fullname`, `age`, `gender`, `course_year`, `contact`, `allergies`, `known_conditions`, `created_at`, `user_id`, `email`, `id_document`, `password`) VALUES
(10, 'SCC-22-00022156', 'Kisha Nicole Paran', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-17 08:27:10', 27, NULL, NULL, '111');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `system_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SHRCMS',
  `school_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'My School',
  `primary_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '#1454bd',
  `sidebar_color` char(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '#08285e',
  `records_per_page` int NOT NULL DEFAULT '10',
  `date_format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'M d, Y',
  `confirm_delete` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `system_name`, `school_name`, `primary_color`, `sidebar_color`, `records_per_page`, `date_format`, `confirm_delete`, `updated_at`) VALUES
(1, 'SHRCMS', 'My School', '#1462e1', '#08285e', 5, 'M d, Y', 1, '2026-11-17 07:14:08');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int NOT NULL,
  `teacher_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `id_document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `teacher_id_no`, `fullname`, `email`, `password`, `id_document`, `status`, `created_at`) VALUES
(1, 'TEACHER69', 'Alipuyo, Jinks, LPT.', 'jingk@gmail.com', '$2y$12$Sf2HxMEZPtyrAJ7nZjPaq.wTlgOCiSPnBJJ8u2nTxbrwGm6d15Z.m', NULL, 'Active', '2026-11-19 01:48:47');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_course_assignments`
--

CREATE TABLE `teacher_course_assignments` (
  `id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `course_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `can_view_passes` tinyint(1) NOT NULL DEFAULT '0',
  `can_view_authorization` tinyint(1) NOT NULL DEFAULT '0',
  `can_view_return_records` tinyint(1) NOT NULL DEFAULT '0',
  `can_review` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'User',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `student_id_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `password`, `status`, `created_at`, `student_id_no`) VALUES
(1, 'olivia', 'jamesoliverbastismo35@gmail.com', 'Staff', '$2y$10$YZvXPm3iwZ7MyIOx13rNq.hR5js9mWMKay0iyqbLNrkRP1IQ1wn4e', 'Active', '2026-10-13 05:19:31', NULL),
(8, 'James oliver Bastismo', 'jamesoliverbastismo188@gmail.com', 'Staff', '$2y$10$nAhhP82RNiw4uR9khq7/neJbr6MnaQ0COqzUwtFViKNtHVMwhmZ5.', 'Active', '2026-10-13 05:47:41', NULL),
(10, 'shani', 'kishanicoleparan@gmail.com', 'Staff', '$2y$10$KTDVvtIU7G4LbzrHf6p85e.kXZtK0ZLzVOGmchA53oL3Pix6hi4uG', 'Active', '2026-10-17 03:41:13', NULL),
(11, 'xian paran', 'xian@gmail.com', 'Student', '$2y$10$g1JoInHjHDlxj.4D.pOHMe41EVJtklcqWnOl73UqvW5zKj7uAVGKy', 'Active', '2026-10-17 08:54:42', NULL),
(27, 'Kisha Nicole Paran', NULL, 'Student', '$2y$12$bZ2B.kp0jecyKpcftx2Wqel40HNbJ5LOUPH0jrKMBSEw6ahnEnlju', 'Active', '2026-11-17 08:27:10', 'SCC-22-00022156'),
(28, 'Alipuyo, Jinks, LPT.', 'jingk@gmail.com', 'Teacher', '$2y$12$B2ExIqz2Fjq/bN23TpC2H.C.FoRQkL0rHegDeWtO1txNbPlTwA17C', 'Active', '2026-11-19 01:45:08', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_admin_id_no` (`admin_id_no`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `clinic_passes`
--
ALTER TABLE `clinic_passes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pass_student` (`student_id_no`),
  ADD KEY `idx_pass_date` (`pass_date`);

--
-- Indexes for table `clinic_referrals`
--
ALTER TABLE `clinic_referrals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ref_student` (`student_id_no`),
  ADD KEY `idx_ref_date` (`referral_date`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consultations_student_date` (`student_id`,`consultation_date`);

--
-- Indexes for table `equipment_inventory`
--
ALTER TABLE `equipment_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipment_name` (`equipment_name`),
  ADD KEY `idx_equipment_condition` (`equipment_condition`),
  ADD KEY `idx_next_maintenance` (`next_maintenance`);

--
-- Indexes for table `health_documentation`
--
ALTER TABLE `health_documentation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_health_student` (`student_id_no`),
  ADD KEY `idx_health_date` (`examination_date`);

--
-- Indexes for table `incident_records`
--
ALTER TABLE `incident_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inc_student` (`student_id_no`),
  ADD KEY `idx_inc_date` (`incident_datetime`);

--
-- Indexes for table `initial_password_changes`
--
ALTER TABLE `initial_password_changes`
  ADD PRIMARY KEY (`account_type`,`account_id`);

--
-- Indexes for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_medical_certificates_code` (`certificate_code`),
  ADD KEY `idx_medical_certificates_exam` (`examination_id`),
  ADD KEY `idx_medical_certificates_student_date` (`student_id_no`,`issued_date`);

--
-- Indexes for table `medical_examinations`
--
ALTER TABLE `medical_examinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medical_examinations_student_date` (`student_id_no`,`examination_date`);

--
-- Indexes for table `medicine_inventory`
--
ALTER TABLE `medicine_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medicine_name` (`medicine_name`),
  ADD KEY `idx_medicine_expiration` (`expiration_date`);

--
-- Indexes for table `med_cert_requests`
--
ALTER TABLE `med_cert_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code_hash` (`qr_code_hash`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_staff_id_no` (`staff_id_no`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id_no` (`student_id_no`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_teachers_id_no` (`teacher_id_no`),
  ADD UNIQUE KEY `uq_teachers_email` (`email`);

--
-- Indexes for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_teacher_course` (`teacher_id`,`course_year`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clinic_passes`
--
ALTER TABLE `clinic_passes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_referrals`
--
ALTER TABLE `clinic_referrals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `equipment_inventory`
--
ALTER TABLE `equipment_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `health_documentation`
--
ALTER TABLE `health_documentation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `incident_records`
--
ALTER TABLE `incident_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `medical_examinations`
--
ALTER TABLE `medical_examinations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `medicine_inventory`
--
ALTER TABLE `medicine_inventory`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `med_cert_requests`
--
ALTER TABLE `med_cert_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  ADD CONSTRAINT `fk_tca_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
