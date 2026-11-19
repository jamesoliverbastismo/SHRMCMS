-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 19, 2026 at 11:59 AM
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
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int NOT NULL,
  `actor_name` varchar(100) NOT NULL,
  `actor_role` varchar(50) NOT NULL,
  `actor_user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `record_type` varchar(100) DEFAULT NULL,
  `record_id` varchar(100) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `actor_name`, `actor_role`, `actor_user_id`, `action`, `module`, `record_type`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 'admin', 'Administrator', 0, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 08:54:33'),
(2, 'admin', 'Administrator', 0, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 08:55:09'),
(3, 'admin', 'Administrator', 0, 'create', 'User Management', 'User', '29', 'Teacher account created', '::1', '2026-11-19 09:07:36'),
(4, 'admin', 'Administrator', 0, 'logout', 'Authentication', 'Session', 'c1c138540583c9b70db84bafd1b2929b', 'User logged out', '::1', '2026-11-19 09:07:48'),
(5, 'mayshane paran', 'Teacher', 2, 'login', 'Authentication', 'Session', 'fad48aa82bf1d770104b6f757b38b607', 'Teacher logged in', '::1', '2026-11-19 09:08:12'),
(6, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '686320db3b6d727b6dab5e6d5c614bc8', 'Administrator logged in', '::1', '2026-11-19 10:03:18'),
(7, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:03:18'),
(8, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:03:35'),
(9, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '686320db3b6d727b6dab5e6d5c614bc8', 'User logged out', '::1', '2026-11-19 10:03:40'),
(10, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', '6ca213418859a397a253e53dd0d77f4d', 'Staff or Teacher logged in', '::1', '2026-11-19 10:03:49'),
(11, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 10:03:49'),
(12, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 10:04:21'),
(13, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 10:04:28'),
(14, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', '6ca213418859a397a253e53dd0d77f4d', 'User logged out', '::1', '2026-11-19 10:04:42'),
(15, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', 'b60f2052839af2aacaa7c6fd6e0e6186', 'Administrator logged in', '::1', '2026-11-19 10:05:57'),
(16, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:05:57'),
(17, 'admin', 'Administrator', 1, 'create', 'User Management', 'User', '30', 'Teacher account created', '::1', '2026-11-19 10:07:19'),
(18, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:07:24'),
(19, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:07:27'),
(20, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:07:37'),
(21, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', 'b60f2052839af2aacaa7c6fd6e0e6186', 'User logged out', '::1', '2026-11-19 10:09:48'),
(22, 'brigildo', 'Teacher', 15, 'login', 'Authentication', 'Session', 'e4a7b7aecf2840b9a5d28ef5a67b5dce', 'Teacher logged in', '::1', '2026-11-19 10:10:44'),
(23, 'brigildo', 'Teacher', 15, 'logout', 'Authentication', 'Session', 'e4a7b7aecf2840b9a5d28ef5a67b5dce', 'User logged out', '::1', '2026-11-19 10:12:15'),
(24, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '9e01f12f5d69e006ea487dd809b5ac1f', 'Administrator logged in', '::1', '2026-11-19 10:16:38'),
(25, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:16:38'),
(26, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:17:35'),
(27, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:17:43'),
(28, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:18:06'),
(29, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:18:10'),
(30, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:18:18'),
(31, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:18:22'),
(32, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:18:36'),
(33, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:18:42'),
(34, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:18:58'),
(35, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:19:03'),
(36, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:19:08'),
(37, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:19:14'),
(38, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:19:19'),
(39, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:19:21'),
(40, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:19:30'),
(41, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 10:21:03'),
(42, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 10:21:10'),
(43, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '9e01f12f5d69e006ea487dd809b5ac1f', 'User logged out', '::1', '2026-11-19 10:21:17'),
(44, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', '44188d4c3893ab205a9f8bdc368ef529', 'Staff or Teacher logged in', '::1', '2026-11-19 10:21:37'),
(45, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 10:21:37'),
(46, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', '98483467dcee9b0d3a276002d98073b5', 'User logged out', '::1', '2026-11-19 11:16:22'),
(47, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '9b33fc5dcb541ff0654041d0b926cbf7', 'Administrator logged in', '::1', '2026-11-19 11:16:32'),
(48, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:16:32'),
(49, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:17:17'),
(50, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:22:39'),
(51, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '9b33fc5dcb541ff0654041d0b926cbf7', 'User logged out', '::1', '2026-11-19 11:23:11'),
(52, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', 'c3a4430fde7bd2caee40d9cd78fd2521', 'Administrator logged in', '::1', '2026-11-19 11:24:02'),
(53, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:24:03'),
(54, 'admin', 'Administrator', 1, 'create', 'User Management', 'User', '31', 'Student account created', '::1', '2026-11-19 11:24:42'),
(55, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', 'c3a4430fde7bd2caee40d9cd78fd2521', 'User logged out', '::1', '2026-11-19 11:25:08'),
(56, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', '4c2ff5bb7424054e020d72031308bac4', 'Staff or Teacher logged in', '::1', '2026-11-19 11:27:43'),
(57, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:27:43'),
(58, 'olivia', 'Staff', 1, 'created', 'Student Health Profiles', 'Student', '12', 'Student profile created: nn', '::1', '2026-11-19 11:29:28'),
(59, 'olivia', 'Staff', 1, 'create', 'Student Profiles', 'Student', '12', 'Student profile record saved', '::1', '2026-11-19 11:29:28'),
(60, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', '4c2ff5bb7424054e020d72031308bac4', 'User logged out', '::1', '2026-11-19 11:30:03'),
(61, 'Bastismo', 'Student', 0, 'login', 'Authentication', 'Session', 'a533046deccceb5cad0d4e8410c4356c', 'Student logged in', '::1', '2026-11-19 11:30:49'),
(62, 'Bastismo', 'Student', 0, 'logout', 'Authentication', 'Session', 'a533046deccceb5cad0d4e8410c4356c', 'User logged out', '::1', '2026-11-19 11:31:11'),
(63, 'brigildo', 'Teacher', 15, 'login', 'Authentication', 'Session', '064dc69d462e824e9c795dfd34682343', 'Teacher logged in', '::1', '2026-11-19 11:31:23'),
(64, 'brigildo', 'Teacher', 15, 'logout', 'Authentication', 'Session', '064dc69d462e824e9c795dfd34682343', 'User logged out', '::1', '2026-11-19 11:31:28'),
(65, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '6ba979ae2da68496d294b558a2bc522b', 'Administrator logged in', '::1', '2026-11-19 11:31:37'),
(66, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:31:37'),
(67, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:32:24'),
(68, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:32:39'),
(69, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '6ba979ae2da68496d294b558a2bc522b', 'User logged out', '::1', '2026-11-19 11:32:47'),
(70, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', 'ee9bd886097c22d3b6df89eed55c4ee9', 'Staff or Teacher logged in', '::1', '2026-11-19 11:32:56'),
(71, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:32:56'),
(72, 'olivia', 'Staff', 1, 'create', 'Clinic Operations', 'Clinic Pass', '1', 'Clinic pass issued', '::1', '2026-11-19 11:33:43'),
(73, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', 'ee9bd886097c22d3b6df89eed55c4ee9', 'User logged out', '::1', '2026-11-19 11:34:04'),
(74, 'brigildo', 'Teacher', 15, 'login', 'Authentication', 'Session', 'cde60f73b8e954c09162292fb651c4de', 'Teacher logged in', '::1', '2026-11-19 11:34:18'),
(75, 'brigildo', 'Teacher', 15, 'confirm return', 'Teacher Dashboard', 'Clinic Pass', '1', 'Teacher confirmed that the student returned to class', '::1', '2026-11-19 11:34:30'),
(76, 'brigildo', 'Teacher', 15, 'review', 'Teacher Dashboard', 'Clinic Pass', '1', 'Clinic pass marked as reviewed', '::1', '2026-11-19 11:34:33'),
(77, 'brigildo', 'Teacher', 15, 'logout', 'Authentication', 'Session', 'cde60f73b8e954c09162292fb651c4de', 'User logged out', '::1', '2026-11-19 11:34:41'),
(78, 'Bastismo', 'Student', 0, 'login', 'Authentication', 'Session', 'a5e192dd46923afe20182c974686aa34', 'Student logged in', '::1', '2026-11-19 11:34:54'),
(79, 'Bastismo', 'Student', 0, 'logout', 'Authentication', 'Session', 'a5e192dd46923afe20182c974686aa34', 'User logged out', '::1', '2026-11-19 11:35:14'),
(80, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', 'b60527b889b4b28ebc94158cb80236c8', 'Staff or Teacher logged in', '::1', '2026-11-19 11:35:24'),
(81, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:35:24'),
(82, 'olivia', 'Staff', 1, 'update', 'Clinic Operations', 'Clinic Pass', '1', 'Return-to-class status recorded', '::1', '2026-11-19 11:35:33'),
(83, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', 'b60527b889b4b28ebc94158cb80236c8', 'User logged out', '::1', '2026-11-19 11:36:14'),
(84, 'brigildo', 'Teacher', 15, 'login', 'Authentication', 'Session', 'e923e23b306bf4e6d39c947ca6fb8234', 'Teacher logged in', '::1', '2026-11-19 11:36:31'),
(85, 'brigildo', 'Teacher', 15, 'logout', 'Authentication', 'Session', 'e923e23b306bf4e6d39c947ca6fb8234', 'User logged out', '::1', '2026-11-19 11:36:51'),
(86, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '3f0105b718b0e56552f78a69ea373b21', 'Administrator logged in', '::1', '2026-11-19 11:37:00'),
(87, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:37:00'),
(88, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 11:37:08'),
(89, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:37:27'),
(90, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '3f0105b718b0e56552f78a69ea373b21', 'User logged out', '::1', '2026-11-19 11:37:52'),
(91, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', '475f854ebe33310b009ddb00e15008a6', 'Staff or Teacher logged in', '::1', '2026-11-19 11:37:59'),
(92, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:37:59'),
(93, 'olivia', 'Staff', 1, 'create', 'Clinic Operations', 'Clinic Pass', '2', 'Clinic pass issued', '::1', '2026-11-19 11:39:16'),
(94, 'olivia', 'Staff', 1, 'stock adjustment', 'Medicine Inventory', 'Inventory Item', '2', 'Stock in: 1 unit(s); quantity 5 -> 6', '::1', '2026-11-19 11:39:35'),
(95, 'olivia', 'Staff', 1, 'stock adjustment', 'Medicine Inventory', 'Inventory Item', '1', 'Stock in: 1 unit(s); quantity 100 -> 101', '::1', '2026-11-19 11:39:39'),
(96, 'olivia', 'Staff', 1, 'stock adjustment', 'Medicine Inventory', 'Inventory Item', '2', 'Stock in: 1 unit(s); quantity 6 -> 7', '::1', '2026-11-19 11:39:41'),
(97, 'olivia', 'Staff', 1, 'stock adjustment', 'Medicine Inventory', 'Inventory Item', '2', 'Stock in: 1 unit(s); quantity 7 -> 8', '::1', '2026-11-19 11:39:45'),
(98, 'olivia', 'Staff', 1, 'stock adjustment', 'Medicine Inventory', 'Inventory Item', '2', 'Stock in: 1 unit(s); quantity 8 -> 9', '::1', '2026-11-19 11:39:48'),
(99, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', '475f854ebe33310b009ddb00e15008a6', 'User logged out', '::1', '2026-11-19 11:39:56'),
(100, 'brigildo', 'Teacher', 15, 'login', 'Authentication', 'Session', 'a349de4cf4c85f2c1d45b0d9ecd591b1', 'Teacher logged in', '::1', '2026-11-19 11:40:12'),
(101, 'brigildo', 'Teacher', 15, 'logout', 'Authentication', 'Session', 'a349de4cf4c85f2c1d45b0d9ecd591b1', 'User logged out', '::1', '2026-11-19 11:40:32'),
(102, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '95cdf574c4fcaf0519334b7a9e01ca1a', 'Administrator logged in', '::1', '2026-11-19 11:40:39'),
(103, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:40:39'),
(104, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:41:05'),
(105, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '95cdf574c4fcaf0519334b7a9e01ca1a', 'User logged out', '::1', '2026-11-19 11:41:19'),
(106, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', 'ffbaeb46d32a17872ad441d42ab499b2', 'Staff or Teacher logged in', '::1', '2026-11-19 11:41:29'),
(107, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:41:29'),
(108, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', 'ffbaeb46d32a17872ad441d42ab499b2', 'User logged out', '::1', '2026-11-19 11:41:52'),
(109, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'login', 'Authentication', 'Session', 'd660643df8116281463e158ab368f86f', 'Teacher logged in', '::1', '2026-11-19 11:42:31'),
(110, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'review', 'Teacher Dashboard', 'Clinic Pass', '1', 'Clinic pass marked as reviewed', '::1', '2026-11-19 11:42:41'),
(111, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'logout', 'Authentication', 'Session', 'd660643df8116281463e158ab368f86f', 'User logged out', '::1', '2026-11-19 11:43:00'),
(112, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', 'd2be1c9ede60064738e3aa89a7eaedaa', 'Staff or Teacher logged in', '::1', '2026-11-19 11:43:52'),
(113, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:43:52'),
(114, 'olivia', 'Staff', 1, 'created', 'Student Health Profiles', 'Student', '13', 'Student profile created', '::1', '2026-11-19 11:45:07'),
(115, 'olivia', 'Staff', 1, 'create', 'Student Profiles', 'Student', '13', 'Student profile record saved', '::1', '2026-11-19 11:45:07'),
(116, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', 'd2be1c9ede60064738e3aa89a7eaedaa', 'User logged out', '::1', '2026-11-19 11:45:34'),
(117, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', 'a505aec16080e54c2f6ab53d8e2425e6', 'Administrator logged in', '::1', '2026-11-19 11:45:44'),
(118, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:45:44'),
(119, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', 'a505aec16080e54c2f6ab53d8e2425e6', 'User logged out', '::1', '2026-11-19 11:46:03'),
(120, 'riji', 'Student', 0, 'login', 'Authentication', 'Session', '370f8481db1638a569121a2da576273c', 'Student logged in', '::1', '2026-11-19 11:46:34'),
(121, 'riji', 'Student', 0, 'logout', 'Authentication', 'Session', '370f8481db1638a569121a2da576273c', 'User logged out', '::1', '2026-11-19 11:46:48'),
(122, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'login', 'Authentication', 'Session', '9f13262becaf67e6eddcf245a6bb3cdf', 'Teacher logged in', '::1', '2026-11-19 11:47:04'),
(123, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'logout', 'Authentication', 'Session', '9f13262becaf67e6eddcf245a6bb3cdf', 'User logged out', '::1', '2026-11-19 11:47:16'),
(124, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', 'c5a745972fd658ba15d5173b75af9cb6', 'Staff or Teacher logged in', '::1', '2026-11-19 11:47:23'),
(125, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:47:23'),
(126, 'olivia', 'Staff', 1, 'update', 'Clinic Operations', 'Clinic Pass', '2', 'Return-to-class status recorded', '::1', '2026-11-19 11:47:32'),
(127, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', 'c5a745972fd658ba15d5173b75af9cb6', 'User logged out', '::1', '2026-11-19 11:47:53'),
(128, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'login', 'Authentication', 'Session', '3397f3a3c9400d0422f0c719fba3578b', 'Teacher logged in', '::1', '2026-11-19 11:48:02'),
(129, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'logout', 'Authentication', 'Session', '3397f3a3c9400d0422f0c719fba3578b', 'User logged out', '::1', '2026-11-19 11:48:20'),
(130, 'brigildo', 'Teacher', 15, 'login', 'Authentication', 'Session', 'd6bd8a8502c66a5e2903587a5fa5a343', 'Teacher logged in', '::1', '2026-11-19 11:48:33'),
(131, 'brigildo', 'Teacher', 15, 'logout', 'Authentication', 'Session', 'd6bd8a8502c66a5e2903587a5fa5a343', 'User logged out', '::1', '2026-11-19 11:48:40'),
(132, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', 'd78f31f1de042a84d59b37e3b78d2ee9', 'Staff or Teacher logged in', '::1', '2026-11-19 11:48:52'),
(133, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:48:52'),
(134, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:49:25'),
(135, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', 'd78f31f1de042a84d59b37e3b78d2ee9', 'User logged out', '::1', '2026-11-19 11:50:25'),
(136, 'admin', 'Administrator', 1, 'login', 'Authentication', 'Session', '898dcce834da48421e41041fe9fd9252', 'Administrator logged in', '::1', '2026-11-19 11:50:36'),
(137, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:50:36'),
(138, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:50:45'),
(139, 'admin', 'Administrator', 1, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed', '::1', '2026-11-19 11:51:28'),
(140, 'admin', 'Administrator', 1, 'view', 'System Reports', 'Report', 'overview', 'Administrator accessed a system report', '::1', '2026-11-19 11:51:42'),
(141, 'admin', 'Administrator', 1, 'logout', 'Authentication', 'Session', '898dcce834da48421e41041fe9fd9252', 'User logged out', '::1', '2026-11-19 11:51:47'),
(142, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', '592a23ad0328f16334e82856b13f2df6', 'Staff or Teacher logged in', '::1', '2026-11-19 11:52:06'),
(143, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:52:07'),
(144, 'olivia', 'Staff', 1, 'create', 'Clinic Operations', 'Clinic Pass', '3', 'Clinic pass issued', '::1', '2026-11-19 11:52:30'),
(145, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', '592a23ad0328f16334e82856b13f2df6', 'User logged out', '::1', '2026-11-19 11:52:37'),
(146, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'login', 'Authentication', 'Session', 'f84dbde0b6dc2602b5d63017d14912af', 'Teacher logged in', '::1', '2026-11-19 11:52:46'),
(147, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'confirm return', 'Teacher Dashboard', 'Clinic Pass', '3', 'Teacher confirmed that the student returned to class', '::1', '2026-11-19 11:52:53'),
(148, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'review', 'Teacher Dashboard', 'Clinic Pass', '3', 'Clinic pass marked as reviewed', '::1', '2026-11-19 11:52:57'),
(149, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'logout', 'Authentication', 'Session', 'f84dbde0b6dc2602b5d63017d14912af', 'User logged out', '::1', '2026-11-19 11:53:01'),
(150, 'olivia', 'Staff', 1, 'login', 'Authentication', 'Session', '616d82408ede0641346368b5f3815352', 'Staff or Teacher logged in', '::1', '2026-11-19 11:53:08'),
(151, 'olivia', 'Staff', 1, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed', '::1', '2026-11-19 11:53:08'),
(152, 'olivia', 'Staff', 1, 'update', 'Clinic Operations', 'Clinic Pass', '3', 'Return-to-class status recorded', '::1', '2026-11-19 11:53:15'),
(153, 'olivia', 'Staff', 1, 'logout', 'Authentication', 'Session', '616d82408ede0641346368b5f3815352', 'User logged out', '::1', '2026-11-19 11:56:54'),
(154, 'Alipuyo, Jinks, LPT.', 'Teacher', 16, 'login', 'Authentication', 'Session', 'a51516d9e060c1dd2d18915992c84c11', 'Teacher logged in', '::1', '2026-11-19 11:57:03');

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
  `teacher_confirmed_return_at` datetime DEFAULT NULL,
  `return_confirmed_by_teacher_id` int DEFAULT NULL,
  `issued_by_staff_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_passes`
--

INSERT INTO `clinic_passes` (`id`, `student_id_no`, `pass_date`, `pass_status`, `authorization_status`, `return_status`, `returned_at`, `teacher_confirmed_return_at`, `return_confirmed_by_teacher_id`, `issued_by_staff_id`, `created_at`) VALUES
(1, 'SCC-22-00016716', '2026-11-19 11:33:00', 'Completed', 'Pending', 'Returned', '2026-11-19 19:35:33', '2026-11-19 19:34:30', 15, 1, '2026-11-19 11:33:43'),
(2, 'SCC-22-00022156', '2026-11-19 11:38:00', 'Completed', 'Authorized', 'Returned', '2026-11-19 19:47:32', NULL, NULL, 1, '2026-11-19 11:39:16'),
(3, 'scc-000', '2026-11-19 11:52:00', 'Completed', 'Pending', 'Returned', '2026-11-19 19:53:15', '2026-11-19 19:52:53', 16, 1, '2026-11-19 11:52:30');

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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `follow_up_due_date` date DEFAULT NULL,
  `follow_up_outcome` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `follow_up_completed_at` datetime DEFAULT NULL,
  `last_follow_up_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_referral_followups`
--

CREATE TABLE `clinic_referral_followups` (
  `id` int NOT NULL,
  `referral_id` int NOT NULL,
  `status` varchar(60) NOT NULL,
  `remarks` text,
  `outcome` text,
  `followed_up_by` varchar(100) DEFAULT NULL,
  `followed_up_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `academic_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
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
(1, 'cetirizine', 'allergy aid', 101, 'pcs', 10, '2028-11-15', 'mercury drug', 'box', '[Stock In +1 on 2026-11-19 by olivia]', '2026-10-17 05:50:56', '2026-11-19 11:39:39'),
(2, 'biogesic', 'fever reducer', 9, 'box', 8, '2027-05-17', 'mercury drug', 'box2', '[Stock In +1 on 2026-11-19 by olivia] | [Stock In +1 on 2026-11-19 by olivia] | [Stock In +1 on 2026-11-19 by olivia] | [Stock In +1 on 2026-11-19 by olivia]', '2026-10-17 05:54:53', '2026-11-19 11:39:48');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_inventory_transactions`
--

CREATE TABLE `medicine_inventory_transactions` (
  `id` int NOT NULL,
  `medicine_id` int NOT NULL,
  `movement` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `quantity_before` int NOT NULL,
  `quantity_after` int NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recorded_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_inventory_transactions`
--

INSERT INTO `medicine_inventory_transactions` (`id`, `medicine_id`, `movement`, `quantity`, `quantity_before`, `quantity_after`, `note`, `recorded_by`, `recorded_at`) VALUES
(1, 2, 'IN', 1, 5, 6, NULL, 'olivia', '2026-11-19 11:39:35'),
(2, 1, 'IN', 1, 100, 101, NULL, 'olivia', '2026-11-19 11:39:39'),
(3, 2, 'IN', 1, 6, 7, NULL, 'olivia', '2026-11-19 11:39:41'),
(4, 2, 'IN', 1, 7, 8, NULL, 'olivia', '2026-11-19 11:39:45'),
(5, 2, 'IN', 1, 8, 9, NULL, 'olivia', '2026-11-19 11:39:48');

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
(2, 'STAFF-0002', '$2y$12$w06HW0BU.RAXVM3kvDn9TukLv3CEn2HtdKid/rqVr3MqIC0xoi9mi', 'James oliver Bastismo', 'Staff', 'jamesoliverbastismo188@gmail.com', NULL, '2026-10-13 05:47:41'),
(4, 'STAFF-0004', NULL, 'dutctmilk', 'Staff', 'dutchmilk@gmail.com', NULL, '2026-10-31 01:24:02'),
(15, 'STAFF-0003', '$2y$12$sHCeW7l639VL6rfE/VCLheGmmArENwv0cC25B7eKTcrO6j6NC/IDK', 'brigildo', 'Teacher', 'brigildo@gmail.com', NULL, '2026-11-19 10:10:44'),
(16, 'TEACHER-0001', '$2y$12$t8feNy6wGfHzTFos8cQKqOhV.cVi2TtznyHqr3wYJMHxljZe6JjNm', 'Alipuyo, Jinks, LPT.', 'Teacher', '', NULL, '2026-11-19 10:16:38'),
(17, 'TEACHER-0002', '$2y$12$j0rh4jRQuG6yFYl/Bsffwucnjc0rm/m06rjpBx8qzVwN2n2sMuFFm', 'mayshane paran', 'Teacher', 'may@gmail.com', NULL, '2026-11-19 10:16:38');

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
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medical_history` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `current_medications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `immunization_information` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `emergency_contact_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `emergency_contact_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id_no`, `fullname`, `age`, `gender`, `course_year`, `contact`, `allergies`, `known_conditions`, `created_at`, `user_id`, `email`, `id_document`, `password`, `medical_history`, `current_medications`, `immunization_information`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_number`) VALUES
(10, 'SCC-22-00022156', 'Kisha Nicole Paran', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-17 08:27:10', 27, NULL, NULL, '111', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'SCC-22-0006978', 'James oliver Bastismo', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-19 11:24:42', 31, NULL, NULL, '000', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'SCC-22-00016716', 'Bastismo', 20, 'Male', 'BSIT-3RD YR', '09817405587', 'nn', 'nn', '2026-11-19 11:29:28', NULL, 'bas@gmail.com', NULL, '$2y$12$VJl5mEaDs3j6bocpKTUYvep8LibexWYEokoQ.QGyHM2sALhf1SPMy', 'nn', 'nn', 'nn', 'ruben', 'parent', '09553588755'),
(13, 'scc-000', 'riji', 21, 'Male', 'BSIT-3RD YR', '098', 'nn', 'nn', '2026-11-19 11:45:07', NULL, 'riji@gmail.com', NULL, '$2y$12$BMWXh62WYwTOqsfMGaP63esa.phM9nZfCk5kXfAGdpz83K3XURmBq', 'nn', 'nn', 'nn', 'nn', 'nn', 'nn');

-- --------------------------------------------------------

--
-- Table structure for table `student_profile_documents`
--

CREATE TABLE `student_profile_documents` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profile_history`
--

CREATE TABLE `student_profile_history` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `student_id_no` varchar(50) NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `change_note` varchar(255) DEFAULT NULL,
  `action_type` varchar(20) NOT NULL DEFAULT 'updated',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_profile_history`
--

INSERT INTO `student_profile_history` (`id`, `student_id`, `student_id_no`, `snapshot_json`, `changed_by`, `change_note`, `action_type`, `created_at`) VALUES
(1, 12, 'SCC-22-00016716', '[]', 'olivia', 'nn', 'created', '2026-11-19 11:29:28'),
(2, 13, 'scc-000', '[]', 'olivia', '', 'created', '2026-11-19 11:45:07');

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
(1, 'TEACHER69', 'Alipuyo, Jinks, LPT.', 'jingk@gmail.com', '$2y$12$t8feNy6wGfHzTFos8cQKqOhV.cVi2TtznyHqr3wYJMHxljZe6JjNm', NULL, 'Active', '2026-11-19 01:48:47'),
(2, 'TEACHER-0002', 'mayshane paran', 'may@gmail.com', '$2y$12$j0rh4jRQuG6yFYl/Bsffwucnjc0rm/m06rjpBx8qzVwN2n2sMuFFm', NULL, 'Active', '2026-11-19 09:07:36'),
(3, 'TEACHER-003', 'brigildo', 'brigildo@gmail.com', '$2y$12$sHCeW7l639VL6rfE/VCLheGmmArENwv0cC25B7eKTcrO6j6NC/IDK', NULL, 'Active', '2026-11-19 10:07:19');

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
  `can_confirm_returns` tinyint(1) NOT NULL DEFAULT '0',
  `can_review` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_course_assignments`
--

INSERT INTO `teacher_course_assignments` (`id`, `teacher_id`, `course_year`, `can_view_passes`, `can_view_authorization`, `can_view_return_records`, `can_confirm_returns`, `can_review`, `created_at`) VALUES
(1, 15, 'BSIT-3RD YR', 1, 1, 1, 1, 1, '2026-11-19 11:32:24'),
(2, 16, 'BSIT-3RD YR', 1, 1, 1, 1, 1, '2026-11-19 11:41:05'),
(3, 17, 'BSIT-3RD YR', 1, 1, 1, 1, 1, '2026-11-19 11:51:28');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_pass_reviews`
--

CREATE TABLE `teacher_pass_reviews` (
  `id` int NOT NULL,
  `pass_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_pass_reviews`
--

INSERT INTO `teacher_pass_reviews` (`id`, `pass_id`, `teacher_id`, `reviewed_at`) VALUES
(1, 1, 15, '2026-11-19 11:34:33'),
(2, 1, 16, '2026-11-19 11:42:41'),
(3, 3, 16, '2026-11-19 11:52:57');

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
(28, 'Alipuyo, Jinks, LPT.', 'jingk@gmail.com', 'Teacher', '$2y$12$B2ExIqz2Fjq/bN23TpC2H.C.FoRQkL0rHegDeWtO1txNbPlTwA17C', 'Active', '2026-11-19 01:45:08', NULL),
(29, 'mayshane paran', 'may@gmail.com', 'Teacher', '$2y$12$j0rh4jRQuG6yFYl/Bsffwucnjc0rm/m06rjpBx8qzVwN2n2sMuFFm', 'Active', '2026-11-19 09:07:36', NULL),
(30, 'brigildo', 'brigildo@gmail.com', 'Teacher', '$2y$12$sHCeW7l639VL6rfE/VCLheGmmArENwv0cC25B7eKTcrO6j6NC/IDK', 'Active', '2026-11-19 10:07:19', NULL),
(31, 'James oliver Bastismo', NULL, 'Student', '$2y$12$9PRHeN/LLbKKJ5HRe4FaNODvZ.cUH9qrBZCxjliuj1EGPUH/26QeK', 'Active', '2026-11-19 11:24:42', 'SCC-22-0006978');

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
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_created` (`created_at`),
  ADD KEY `idx_audit_actor_role` (`actor_role`),
  ADD KEY `idx_audit_module` (`module`);

--
-- Indexes for table `clinic_passes`
--
ALTER TABLE `clinic_passes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pass_student` (`student_id_no`),
  ADD KEY `idx_pass_date` (`pass_date`),
  ADD KEY `idx_pass_return_confirmed_by_teacher` (`return_confirmed_by_teacher_id`);

--
-- Indexes for table `clinic_referrals`
--
ALTER TABLE `clinic_referrals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ref_student` (`student_id_no`),
  ADD KEY `idx_ref_date` (`referral_date`);

--
-- Indexes for table `clinic_referral_followups`
--
ALTER TABLE `clinic_referral_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_referral_followups_referral` (`referral_id`),
  ADD KEY `idx_referral_followups_date` (`followed_up_at`);

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
-- Indexes for table `medicine_inventory_transactions`
--
ALTER TABLE `medicine_inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_transactions_medicine` (`medicine_id`),
  ADD KEY `idx_inventory_transactions_date` (`recorded_at`);

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
-- Indexes for table `student_profile_documents`
--
ALTER TABLE `student_profile_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_profile_documents_student` (`student_id`);

--
-- Indexes for table `student_profile_history`
--
ALTER TABLE `student_profile_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_profile_history_student` (`student_id`),
  ADD KEY `idx_profile_history_date` (`created_at`);

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
-- Indexes for table `teacher_pass_reviews`
--
ALTER TABLE `teacher_pass_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_teacher_pass_review` (`pass_id`,`teacher_id`),
  ADD KEY `idx_teacher_pass_review_teacher` (`teacher_id`);

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
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT for table `clinic_passes`
--
ALTER TABLE `clinic_passes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clinic_referrals`
--
ALTER TABLE `clinic_referrals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_referral_followups`
--
ALTER TABLE `clinic_referral_followups`
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
-- AUTO_INCREMENT for table `medicine_inventory_transactions`
--
ALTER TABLE `medicine_inventory_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `med_cert_requests`
--
ALTER TABLE `med_cert_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `student_profile_documents`
--
ALTER TABLE `student_profile_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_profile_history`
--
ALTER TABLE `student_profile_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teacher_pass_reviews`
--
ALTER TABLE `teacher_pass_reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clinic_passes`
--
ALTER TABLE `clinic_passes`
  ADD CONSTRAINT `fk_pass_return_confirmed_by_teacher` FOREIGN KEY (`return_confirmed_by_teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `medicine_inventory_transactions`
--
ALTER TABLE `medicine_inventory_transactions`
  ADD CONSTRAINT `fk_inventory_transactions_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicine_inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  ADD CONSTRAINT `fk_tca_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_pass_reviews`
--
ALTER TABLE `teacher_pass_reviews`
  ADD CONSTRAINT `fk_tpr_pass` FOREIGN KEY (`pass_id`) REFERENCES `clinic_passes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tpr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
