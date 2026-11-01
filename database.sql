-- ====================================================================
-- Academics Student Management System — Production Database Dump
-- 100% Verified for InfinityFree, cPanel, phpMyAdmin & Localhost
-- Generated: 2026-10-28 20:11:58
-- Note for InfinityFree: Do NOT run CREATE DATABASE.
-- First create the database in vPanel, open it in phpMyAdmin, and Import this file.
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `activity_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_logs_school` (`school_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `activity_logs`
INSERT INTO `activity_logs` (`id`, `school_id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('1', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 18:53:06'),
('2', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 18:54:07'),
('3', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 18:55:08'),
('4', '1', '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-26 18:55:08'),
('5', '1', '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-26 18:55:09'),
('6', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 18:55:56'),
('11', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 20:22:13'),
('12', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 21:05:38'),
('13', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-26 21:05:38'),
('14', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-26 23:20:39'),
('15', NULL, '1', 'toggle_alert', 'emergency_alert', '1', 'Deactivated emergency closure notice', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-26 23:20:46'),
('16', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 09:15:35'),
('17', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 09:16:22'),
('18', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 09:16:40'),
('19', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:19:25'),
('20', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:19:58'),
('21', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:21:24'),
('22', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:46:35'),
('23', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:47:17'),
('24', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:47:24'),
('25', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:47:30'),
('26', '1', '3', 'login', 'user', '3', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 11:47:37'),
('27', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-27 15:47:02'),
('28', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 16:04:56'),
('29', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 16:09:16'),
('30', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 16:12:57'),
('31', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-27 20:24:33'),
('32', NULL, '1', 'update_alert', 'emergency_alert', '1', 'Updated emergency alert: my nahi batao ga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-27 20:25:10'),
('33', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-27 23:20:30'),
('34', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-27 23:51:00'),
('35', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 06:42:52'),
('36', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.1.4 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', '2026-10-28 06:43:57'),
('37', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:08:54'),
('38', NULL, '1', 'update_status', 'task', '5', 'Status changed to Completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.1.4 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', '2026-10-28 07:10:21'),
('39', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:27:43'),
('40', '1', '2', 'login', 'user', '2', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:27:46');
INSERT INTO `activity_logs` (`id`, `school_id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('41', '1', '3', 'login', 'user', '3', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:27:46'),
('42', '1', '2', 'login', 'user', '2', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:28:39'),
('43', '1', '3', 'login', 'user', '3', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:28:40'),
('44', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:28:40'),
('46', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 07:31:53'),
('47', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:09:37'),
('48', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:09:38'),
('49', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:09:54'),
('50', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:10:14'),
('51', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:10:45'),
('52', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:11:02'),
('53', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-10-28 08:11:23'),
('54', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:04:37'),
('55', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:04:37'),
('56', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:04:37'),
('57', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:04:38'),
('58', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:04:38'),
('59', '1', '8', 'auto_login', 'user', '8', 'Signed in with a single-use auto-login token', '127.0.0.1', 'unknown', '2026-10-28 09:04:38'),
('60', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:11'),
('61', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:12'),
('62', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:13'),
('63', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:13'),
('64', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:14'),
('65', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:34'),
('66', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:36'),
('67', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:39'),
('68', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:40'),
('69', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:05:40'),
('70', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:06:16'),
('71', NULL, '5', 'create_event', 'school_events', '26', 'Added event: Verify Event 1793160376', '127.0.0.1', 'unknown', '2026-10-28 09:06:16'),
('72', NULL, '5', 'create', 'task', '25', 'Created task: Verify Task 1793160376', '127.0.0.1', 'unknown', '2026-10-28 09:06:16'),
('73', NULL, '5', 'create', 'student', '35', 'Created student: Verify Student 1793160376', '127.0.0.1', 'unknown', '2026-10-28 09:06:17'),
('74', NULL, '5', 'create', 'user', '14', 'Created user: Verify Teacher 1793160376', '127.0.0.1', 'unknown', '2026-10-28 09:06:17'),
('75', '1', '5', 'school_settings', 'school', '1', 'Updated school branding and settings', '127.0.0.1', 'unknown', '2026-10-28 09:06:17'),
('76', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:06:17'),
('77', NULL, '1', 'school_create', 'school', '3', 'Created school VER160376', '127.0.0.1', 'unknown', '2026-10-28 09:06:17'),
('78', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:06:17'),
('79', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:06:18'),
('80', '2', '9', 'login', 'user', '9', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:06:18');
INSERT INTO `activity_logs` (`id`, `school_id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('81', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:19'),
('82', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:19'),
('83', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:19'),
('84', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:20'),
('85', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:20'),
('86', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:20'),
('87', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:21'),
('88', '1', '8', 'auto_login', 'user', '8', 'Signed in with a single-use auto-login token', '127.0.0.1', 'unknown', '2026-10-28 09:11:21'),
('89', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:22'),
('90', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:23'),
('91', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:23'),
('92', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:30'),
('93', NULL, '5', 'create_event', 'school_events', '28', 'Added event: Verify Event 1793160688', '127.0.0.1', 'unknown', '2026-10-28 09:11:31'),
('94', NULL, '5', 'create', 'task', '27', 'Created task: Verify Task 1793160688', '127.0.0.1', 'unknown', '2026-10-28 09:11:31'),
('95', NULL, '5', 'create', 'student', '38', 'Created student: Verify Student 1793160688', '127.0.0.1', 'unknown', '2026-10-28 09:11:31'),
('96', NULL, '5', 'create', 'user', '16', 'Created user: Verify Teacher 1793160688', '127.0.0.1', 'unknown', '2026-10-28 09:11:32'),
('97', '1', '5', 'school_settings', 'school', '1', 'Updated school branding and settings', '127.0.0.1', 'unknown', '2026-10-28 09:11:32'),
('98', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:32'),
('99', NULL, '1', 'school_create', 'school', '4', 'Created school VER160688', '127.0.0.1', 'unknown', '2026-10-28 09:11:33'),
('100', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:33'),
('101', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:35'),
('102', '2', '9', 'login', 'user', '9', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:11:36'),
('103', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:12:55'),
('104', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:12:55'),
('105', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:12:55'),
('106', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:12:56'),
('107', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:12:56'),
('108', '1', '8', 'auto_login', 'user', '8', 'Signed in with a single-use auto-login token', '127.0.0.1', 'unknown', '2026-10-28 09:12:56'),
('109', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:37'),
('110', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:38'),
('111', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:38'),
('112', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:38'),
('113', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:39'),
('114', '1', '8', 'auto_login', 'user', '8', 'Signed in with a single-use auto-login token', '127.0.0.1', 'unknown', '2026-10-28 09:24:39'),
('115', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:51'),
('116', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:53'),
('117', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:53'),
('118', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:54'),
('119', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:24:56'),
('120', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:25:05');
INSERT INTO `activity_logs` (`id`, `school_id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('121', NULL, '5', 'create_event', 'school_events', '30', 'Added event: Verify Event 1793161505', '127.0.0.1', 'unknown', '2026-10-28 09:25:05'),
('122', NULL, '5', 'create', 'task', '29', 'Created task: Verify Task 1793161505', '127.0.0.1', 'unknown', '2026-10-28 09:25:06'),
('123', NULL, '5', 'create', 'student', '41', 'Created student: Verify Student 1793161505', '127.0.0.1', 'unknown', '2026-10-28 09:25:07'),
('124', NULL, '5', 'create', 'user', '18', 'Created user: Verify Teacher 1793161505', '127.0.0.1', 'unknown', '2026-10-28 09:25:07'),
('125', '1', '5', 'school_settings', 'school', '1', 'Updated school branding and settings', '127.0.0.1', 'unknown', '2026-10-28 09:25:07'),
('126', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:25:07'),
('127', NULL, '1', 'school_create', 'school', '5', 'Created school VER161505', '127.0.0.1', 'unknown', '2026-10-28 09:25:07'),
('128', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:25:08'),
('129', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:25:09'),
('130', '2', '9', 'login', 'user', '9', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 09:25:09'),
('131', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.1.4 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', '2026-10-28 09:43:05'),
('132', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 10:46:42'),
('133', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.1.4 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', '2026-10-28 10:57:33'),
('134', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:40'),
('135', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:40'),
('136', '1', '6', 'login', 'user', '6', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:41'),
('137', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:42'),
('138', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:43'),
('139', '1', '8', 'auto_login', 'user', '8', 'Signed in with a single-use auto-login token', '127.0.0.1', 'unknown', '2026-10-28 11:44:43'),
('140', '1', '5', 'login', 'user', '5', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:44'),
('141', NULL, '5', 'create_event', 'school_events', '32', 'Added event: Verify Event 1793169884', '127.0.0.1', 'unknown', '2026-10-28 11:44:44'),
('142', NULL, '5', 'create', 'task', '31', 'Created task: Verify Task 1793169884', '127.0.0.1', 'unknown', '2026-10-28 11:44:44'),
('143', NULL, '5', 'create', 'student', '44', 'Created student: Verify Student 1793169884', '127.0.0.1', 'unknown', '2026-10-28 11:44:45'),
('144', NULL, '5', 'create', 'user', '20', 'Created user: Verify Teacher 1793169884', '127.0.0.1', 'unknown', '2026-10-28 11:44:46'),
('145', '1', '5', 'school_settings', 'school', '1', 'Updated school branding and settings', '127.0.0.1', 'unknown', '2026-10-28 11:44:46'),
('146', NULL, '1', 'login', 'user', '1', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:47'),
('147', NULL, '1', 'school_create', 'school', '6', 'Created school VER169884', '127.0.0.1', 'unknown', '2026-10-28 11:44:47'),
('148', '1', '8', 'login', 'user', '8', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:48'),
('149', '1', '7', 'login', 'user', '7', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:48'),
('150', '2', '9', 'login', 'user', '9', 'User logged in', '127.0.0.1', 'unknown', '2026-10-28 11:44:49'),
('151', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 17:47:22'),
('152', NULL, '1', 'update_alert', 'emergency_alert', '1', 'Updated emergency alert: my nahi batao ga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 17:48:22'),
('153', NULL, '1', 'logout', 'user', '1', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 17:48:48'),
('154', '1', '8', 'login', 'user', '8', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 17:50:38'),
('155', NULL, '8', 'logout', 'user', '8', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 17:56:02'),
('156', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-28 17:56:08'),
('157', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 18:54:31'),
('158', '1', '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-28 18:54:33'),
('159', '1', '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-28 18:54:35'),
('160', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 18:59:00');
INSERT INTO `activity_logs` (`id`, `school_id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
('161', NULL, '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-28 18:59:05'),
('162', NULL, '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-28 18:59:06'),
('163', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 19:00:21'),
('164', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 19:00:47'),
('165', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 19:01:29'),
('166', NULL, '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-28 19:01:30'),
('167', NULL, '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-28 19:01:31'),
('168', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 19:03:02'),
('169', NULL, '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-28 19:03:03'),
('170', NULL, '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-28 19:03:03'),
('171', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 20:05:51'),
('172', NULL, '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-28 20:05:54'),
('173', NULL, '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-28 20:05:55'),
('174', NULL, '1', 'login', 'user', '1', 'User logged in', '::1', 'unknown', '2026-10-28 20:10:47'),
('175', NULL, '2', 'login', 'user', '2', 'User logged in', '::1', 'unknown', '2026-10-28 20:10:48'),
('176', NULL, '3', 'login', 'user', '3', 'User logged in', '::1', 'unknown', '2026-10-28 20:10:49');

-- --------------------------------------------------------
-- Table structure for table `announcements`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `target_roles` varchar(50) DEFAULT 'all',
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` datetime DEFAULT current_timestamp(),
  `end_date` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_active` (`is_active`),
  KEY `idx_start` (`start_date`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `application_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `application_settings`;
CREATE TABLE `application_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `application_settings`
INSERT INTO `application_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('1', 'app_name', 'Academics', 'Application name', '2026-10-26 18:49:45'),
('2', 'app_version', '1.0.0', 'Application version', '2026-10-26 18:49:45'),
('3', 'timezone', 'Asia/Karachi', 'Default timezone', '2026-10-26 18:49:45'),
('4', 'records_per_page', '10', 'Default pagination limit', '2026-10-26 18:49:45');

-- --------------------------------------------------------
-- Table structure for table `courses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `credits` int(11) DEFAULT 3,
  `instructor_id` int(11) DEFAULT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `status` enum('Active','Inactive','Archived') DEFAULT 'Active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `created_by` (`created_by`),
  KEY `idx_course_code` (`course_code`),
  KEY `idx_status` (`status`),
  KEY `idx_instructor` (`instructor_id`),
  KEY `idx_courses_school` (`school_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `courses`
INSERT INTO `courses` (`id`, `school_id`, `course_code`, `course_name`, `description`, `credits`, `instructor_id`, `schedule`, `start_date`, `end_date`, `capacity`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
('1', '1', 'CS101', 'Introduction to Computer Science', 'Fundamentals of computer science and programming in C and PHP', '3', '2', 'Mon/Wed 10:00 - 11:30 AM', '2026-10-26', '2027-04-26', '30', 'Active', '1', '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('2', '1', 'MATH201', 'Calculus II', 'Advanced calculus topics including integration, multivariable calculus, and infinite series', '4', '2', 'Tue/Thu 09:00 - 10:30 AM', '2026-10-26', '2027-04-26', '25', 'Active', '1', '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('3', '1', 'ENG101', 'English Composition', 'Academic writing, reading comprehension, and business communications', '3', '2', 'Fri 02:00 - 04:00 PM', '2026-10-26', '2027-04-26', '35', 'Active', '1', '2026-10-26 18:49:45', '2026-10-28 08:45:58');

-- --------------------------------------------------------
-- Table structure for table `emergency_alerts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `emergency_alerts`;
CREATE TABLE `emergency_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `alert_type` enum('Emergency Closure','Severe Weather','Administrative Off','General Alert') DEFAULT 'Emergency Closure',
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `emergency_alerts_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `emergency_alerts`
INSERT INTO `emergency_alerts` (`id`, `school_id`, `title`, `message`, `alert_type`, `effective_date`, `end_date`, `is_active`, `updated_by`, `updated_at`) VALUES
('1', '1', 'URGENT: Rain Emergency Closure Notice', 'As per local government directives and weather advisory regarding torrential rains, the school campus will remain closed on the specified date. All scheduled classes will be conducted online via student portal.', 'Emergency Closure', '2026-10-28', '2026-10-30', '1', '1', '2026-10-28 20:05:44'),
('2', '2', 'School Closure Notice', 'School will remain closed until further notice.', 'Emergency Closure', '2026-10-28', '2026-10-29', '0', '1', '2026-10-28 09:05:12');

-- --------------------------------------------------------
-- Table structure for table `enrollments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('Enrolled','Completed','Dropped','Withdrawn') DEFAULT 'Enrolled',
  `grade` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_status` (`status`),
  KEY `idx_enroll_school` (`school_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `enrollments`
INSERT INTO `enrollments` (`id`, `school_id`, `student_id`, `course_id`, `enrollment_date`, `status`, `grade`, `notes`, `created_at`, `updated_at`) VALUES
('1', '1', '1', '1', '2026-10-26', 'Enrolled', NULL, NULL, '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('2', '1', '1', '2', '2026-10-26', 'Enrolled', NULL, NULL, '2026-10-26 18:49:45', '2026-10-28 08:45:58');

-- --------------------------------------------------------
-- Table structure for table `login_attempts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `icon` varchar(60) DEFAULT 'fa-bell',
  `color` varchar(30) DEFAULT '#e0f2fe',
  `icon_color` varchar(30) DEFAULT '#0284c7',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `notifications`
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `icon`, `color`, `icon_color`, `is_read`, `link`, `created_at`) VALUES
('1', '1', 'New student enrolled', 'A new student has been added to CS101.', 'fa-user-plus', '#dcfce7', '#16a34a', '0', NULL, '2026-10-27 11:46:05'),
('2', '1', 'Task overdue', 'Grade Assignment 1 is past due.', 'fa-exclamation-triangle', '#fee2e2', '#dc2626', '0', NULL, '2026-10-27 11:46:05'),
('3', '1', 'System update', 'Academics updated to v1.1.', 'fa-rocket', '#e0f2fe', '#0284c7', '0', NULL, '2026-10-27 11:46:05'),
('4', '2', 'New assignment', 'You have a new task to grade.', 'fa-clipboard-check', '#fef3c7', '#d97706', '0', NULL, '2026-10-27 11:46:05'),
('5', '3', 'Welcome!', 'Welcome to Academics, Demo Student.', 'fa-graduation-cap', '#ede9fe', '#7c3aed', '0', NULL, '2026-10-27 11:46:05');

-- --------------------------------------------------------
-- Table structure for table `parent_student`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `parent_student`;
CREATE TABLE `parent_student` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `relationship` enum('father','mother','guardian') NOT NULL DEFAULT 'guardian',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parent_student` (`parent_id`,`student_id`),
  KEY `idx_ps_school` (`school_id`),
  KEY `fk_ps_student` (`student_id`),
  CONSTRAINT `fk_ps_parent` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `parent_student`
INSERT INTO `parent_student` (`id`, `parent_id`, `student_id`, `school_id`, `relationship`, `created_at`) VALUES
('1', '8', '31', '1', 'father', '2026-10-28 08:46:00'),
('2', '12', '32', '2', 'guardian', '2026-10-28 08:46:00');

-- --------------------------------------------------------
-- Table structure for table `school_events`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `school_events`;
CREATE TABLE `school_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `event_type` enum('Function','Holiday','Exam','Meeting','Other') DEFAULT 'Function',
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_holiday` tinyint(1) DEFAULT 0,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_is_holiday` (`is_holiday`),
  KEY `idx_events_school` (`school_id`),
  CONSTRAINT `school_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `school_events`
INSERT INTO `school_events` (`id`, `school_id`, `title`, `event_type`, `description`, `event_date`, `end_date`, `is_holiday`, `status`, `created_by`, `created_at`) VALUES
('1', '1', 'Annual Sports Gala 2026', 'Function', 'Grand sports competitions, track events, and prize distribution ceremony.', '2026-11-07', '2026-11-08', '0', 'Upcoming', '1', '2026-10-26 18:49:45'),
('2', '1', 'Science & Robotics Exhibition', 'Function', 'Students showcase STEM, AI, and science projects in the school auditorium.', '2026-11-20', NULL, '0', 'Upcoming', '1', '2026-10-26 18:49:45'),
('3', '1', 'Parent-Teacher Conference (PTM)', 'Meeting', 'Individual progress discussions between teachers and parents for semester review.', '2026-11-01', NULL, '0', 'Upcoming', '1', '2026-10-26 18:49:45'),
('4', '1', 'Mid-Term Examination Commencement', 'Exam', 'Formal examination period for all grades. Date sheet published on notice board.', '2026-11-13', '2026-11-23', '0', 'Upcoming', '1', '2026-10-26 18:49:45'),
('5', '1', 'Kashmir Solidarity Day (Gazetted Holiday)', 'Holiday', 'School will remain closed in observance of National Kashmir Day.', '2026-11-04', NULL, '1', 'Upcoming', '1', '2026-10-26 18:49:45'),
('6', '1', 'Winter Break Holidays', 'Holiday', 'Annual winter vacation for all classes. Administrative offices will remain open.', '2026-11-30', '2026-12-10', '1', 'Upcoming', '1', '2026-10-26 18:49:45'),
('22', '1', 'Annual Science Exhibition', 'Function', 'Students showcase science projects, experiments and innovations.', '2026-11-09', NULL, '0', 'Upcoming', '5', '2026-10-28 08:46:00'),
('23', '1', 'Founder\'s Day Holiday', 'Holiday', 'Campus closed in honour of the school founder.', '2026-11-17', NULL, '1', 'Upcoming', '5', '2026-10-28 08:46:00'),
('24', '2', 'Inter-School Debate', 'Function', 'Debate teams from neighbouring schools compete on current affairs.', '2026-11-12', NULL, '0', 'Upcoming', '9', '2026-10-28 08:46:00');

-- --------------------------------------------------------
-- Table structure for table `school_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `school_settings`;
CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `public_page_title` varchar(255) DEFAULT NULL,
  `public_page_description` text DEFAULT NULL,
  `allow_parent_auto_login` tinyint(1) NOT NULL DEFAULT 1,
  `session_timeout_minutes` int(11) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_id` (`school_id`),
  CONSTRAINT `fk_school_settings_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `school_settings`
INSERT INTO `school_settings` (`id`, `school_id`, `public_page_title`, `public_page_description`, `allow_parent_auto_login`, `session_timeout_minutes`, `created_at`, `updated_at`) VALUES
('1', '1', 'Academics International School — Official Portal', 'Welcome to Academics International School.', '1', '30', '2026-10-28 08:45:58', '2026-10-28 09:06:17'),
('2', '2', 'Beaconhouse Model School — Official Portal', 'Welcome to Beaconhouse Model School. Explore our events, achievements and academic programs.', '1', '30', '2026-10-28 08:45:58', '2026-10-28 08:45:58');

-- --------------------------------------------------------
-- Table structure for table `schools`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `schools`;
CREATE TABLE `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) NOT NULL,
  `school_code` varchar(50) NOT NULL,
  `subdomain` varchar(50) DEFAULT NULL,
  `school_email` varchar(255) DEFAULT NULL,
  `school_phone` varchar(30) DEFAULT NULL,
  `school_address` text DEFAULT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `school_logo` varchar(255) DEFAULT NULL,
  `primary_color` varchar(20) NOT NULL DEFAULT '#4f46e5',
  `secondary_color` varchar(20) NOT NULL DEFAULT '#06b6d4',
  `motto` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `status` enum('active','suspended','trial') NOT NULL DEFAULT 'active',
  `plan` enum('basic','pro','enterprise') NOT NULL DEFAULT 'basic',
  `trial_ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `subscription_plan` enum('free','basic','premium') NOT NULL DEFAULT 'free',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_code` (`school_code`),
  UNIQUE KEY `uniq_school_subdomain` (`subdomain`),
  KEY `idx_school_code` (`school_code`),
  KEY `idx_school_active` (`is_active`),
  KEY `idx_school_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `schools`
INSERT INTO `schools` (`id`, `school_name`, `school_code`, `school_email`, `school_phone`, `school_address`, `school_logo`, `primary_color`, `secondary_color`, `motto`, `website`, `is_active`, `subscription_plan`, `created_at`, `updated_at`) VALUES
('1', 'Academics International School', 'SCH001', 'info@sch001.edu', '+92 300 111 2233', '12-A, Knowledge Avenue, Lahore', NULL, '#4f46e5', '#06b6d4', 'Learn. Lead. Inspire.', 'https://sch001.example.edu', '1', 'premium', '2026-10-28 08:45:58', '2026-10-28 08:45:58'),
('2', 'Beaconhouse Model School', 'SCH002', 'info@sch002.edu', '+92 300 444 5566', '88-B, Garden Town, Karachi', NULL, '#0f766e', '#f59e0b', 'Excellence in every step', 'https://sch002.example.edu', '1', 'basic', '2026-10-28 08:45:58', '2026-10-28 11:44:49');

-- SaaS subdomain / status / plan backfill for the demo schools
UPDATE `schools` SET `subdomain` = 'edusphere-international-school', `status` = 'active', `plan` = 'enterprise' WHERE `school_code` = 'SCH001';
UPDATE `schools` SET `subdomain` = 'beaconhouse-model-school', `status` = 'active', `plan` = 'basic' WHERE `school_code` = 'SCH002';

-- --------------------------------------------------------
-- Table structure for table `students`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('Active','Inactive','Graduated','Suspended') DEFAULT 'Active',
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_student_number` (`student_number`),
  KEY `idx_status` (`status`),
  KEY `idx_enrollment_date` (`enrollment_date`),
  KEY `idx_students_school` (`school_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `students`
INSERT INTO `students` (`id`, `school_id`, `user_id`, `student_number`, `full_name`, `email`, `phone`, `date_of_birth`, `gender`, `address`, `enrollment_date`, `status`, `profile_image`, `created_at`, `updated_at`) VALUES
('1', '1', '3', 'STU-2024-001', 'Demo Student', 'student@edusphere.com', NULL, NULL, NULL, NULL, '2026-10-26', 'Active', NULL, '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('31', '1', '7', 'SCH001-STU-1366', 'Ayesha Malik', 'student@sch001.com', NULL, NULL, NULL, NULL, '2026-10-28', 'Active', NULL, '2026-10-28 08:46:00', '2026-10-28 08:46:00'),
('32', '2', '11', 'SCH002-STU-5009', 'Hamza Sheikh', 'student@sch002.com', NULL, NULL, NULL, NULL, '2026-10-28', 'Active', NULL, '2026-10-28 08:46:00', '2026-10-28 08:46:00');

-- --------------------------------------------------------
-- Table structure for table `tasks`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `visibility` enum('school','class','individual') NOT NULL DEFAULT 'school',
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_tasks_school` (`school_id`),
  KEY `idx_tasks_assigned` (`assigned_to_user_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `tasks`
INSERT INTO `tasks` (`id`, `school_id`, `user_id`, `student_id`, `created_by_user_id`, `assigned_to_user_id`, `visibility`, `course_id`, `title`, `description`, `priority`, `status`, `due_date`, `completed_at`, `created_at`, `updated_at`) VALUES
('1', '1', '1', '1', NULL, NULL, 'school', '1', 'Complete Assignment 1', 'Finish the programming assignment for CS101 covering functions and arrays.', 'High', 'Pending', '2026-11-02', NULL, '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('2', '1', '1', '1', NULL, NULL, 'school', '2', 'Study for Midterm', 'Review calculus chapters 1 through 5 including integration techniques and limits.', 'Medium', 'In Progress', '2026-11-09', NULL, '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('3', '1', '2', '1', NULL, NULL, 'school', '1', 'Grade Assignment 1', 'Grade and provide feedback on student submissions for CS101.', 'Urgent', 'Pending', '2026-10-29', NULL, '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('4', '1', '3', '1', NULL, NULL, 'school', '1', 'Read Chapter 3', 'Read and summarize chapter 3 on Object-Oriented Programming and PDO security.', 'Low', 'Completed', '2026-10-24', '2026-10-26 18:49:45', '2026-10-26 18:49:45', '2026-10-28 08:45:58'),
('21', '1', '6', '31', '6', '31', 'school', NULL, 'Algebra Worksheet Chapter 4', 'Complete exercises 4.1 to 4.5 and upload scans.', 'High', 'Pending', '2026-11-02', NULL, '2026-10-28 08:46:00', '2026-10-28 08:46:00'),
('22', '1', '6', '31', '6', '31', 'school', NULL, 'Science Lab Report', 'Write a report on the photosynthesis experiment.', 'Medium', 'In Progress', '2026-11-06', NULL, '2026-10-28 08:46:00', '2026-10-28 08:46:00'),
('23', '2', '10', '32', '10', '32', 'school', NULL, 'English Essay — My City', '300 words descriptive essay.', 'Medium', 'Pending', '2026-11-04', NULL, '2026-10-28 08:46:00', '2026-10-28 08:46:00');

-- --------------------------------------------------------
-- Table structure for table `user_redis_cache`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_redis_cache`;
CREATE TABLE `user_redis_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL,
  `cache_key` varchar(191) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_urc_user` (`user_id`),
  KEY `idx_urc_school` (`school_id`),
  CONSTRAINT `fk_urc_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_urc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `user_redis_cache`
INSERT INTO `user_redis_cache` (`id`, `user_id`, `school_id`, `email`, `role`, `cache_key`, `token_hash`, `token_expires_at`, `last_login`, `created_at`) VALUES
('1', '1', NULL, 'admin@edusphere.com', 'super_admin', 'user:1', NULL, NULL, '2026-10-28 18:54:31', '2026-10-28 09:04:37'),
('2', '5', '1', 'schooladmin@sch001.com', 'school_admin', 'user:5', NULL, NULL, '2026-10-28 11:44:44', '2026-10-28 09:04:37'),
('3', '6', '1', 'teacher@sch001.com', 'teacher', 'user:6', NULL, NULL, '2026-10-28 11:44:41', '2026-10-28 09:04:37'),
('4', '7', '1', 'student@sch001.com', 'student', 'user:7', NULL, NULL, '2026-10-28 11:44:48', '2026-10-28 09:04:38'),
('5', '8', '1', 'parent@sch001.com', 'parent', 'user:8', NULL, NULL, '2026-10-28 17:50:38', '2026-10-28 09:04:38'),
('20', '9', '2', 'schooladmin@sch002.com', 'school_admin', 'user:9', NULL, NULL, '2026-10-28 11:44:49', '2026-10-28 09:06:18'),
('73', '2', '1', 'teacher@edusphere.com', 'teacher', 'user:2', NULL, NULL, '2026-10-28 18:54:33', '2026-10-28 18:54:33'),
('74', '3', '1', 'student@edusphere.com', 'student', 'user:3', NULL, NULL, '2026-10-28 18:54:35', '2026-10-28 18:54:35');

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin','school_admin','teacher','student','parent') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_users_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `school_id`, `full_name`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
('1', NULL, 'System Administrator', 'admin@edusphere.com', '$2y$10$Ro246yHuqUPebBbJbl.9MeB9nETD76fg14wh2/VkT3XSxMY13eZn.', 'admin', '1', '2026-10-28 20:10:47', '2026-10-26 18:49:45', '2026-10-28 20:10:47'),
('2', '1', 'Demo Teacher', 'teacher@edusphere.com', '$2y$10$fdLHo1oH5S87q/wOHKEvG.1GMKqWP36codnsePNtzqkBMN.o9OaTy', 'teacher', '1', '2026-10-28 20:10:48', '2026-10-26 18:49:45', '2026-10-28 20:10:48'),
('3', '1', 'Demo Student', 'student@edusphere.com', '$2y$10$YVCQz.bWZCEQa0EzR59AjeAmntdQB//ttnGIcWZUuqn5sTEvLuu/.', 'student', '1', '2026-10-28 20:10:49', '2026-10-26 18:49:45', '2026-10-28 20:10:49'),
('5', '1', 'Sarah Khan (School Admin)', 'schooladmin@sch001.com', '$2y$10$YwLek1mTDV08fvgv5LlAsesu4g/UtRNf74ro0zmBY374gu3oX1tdW', 'school_admin', '1', '2026-10-28 11:44:44', '2026-10-28 08:45:59', '2026-10-28 11:44:44'),
('6', '1', 'Imran Ali (Teacher)', 'teacher@sch001.com', '$2y$10$V1WJEsXhjrSCBXzUJOknZewGNMg28t6BO6PpAZtPPHeWTD10NBzoS', 'teacher', '1', '2026-10-28 11:44:41', '2026-10-28 08:45:59', '2026-10-28 11:44:41'),
('7', '1', 'Ayesha Malik (Student)', 'student@sch001.com', '$2y$10$1Ct1YACJbKErOf2ooF4r6OuiT.gY/T46Xegwc8.xC.PvOjwKgoduG', 'student', '1', '2026-10-28 11:44:48', '2026-10-28 08:45:59', '2026-10-28 11:44:48'),
('8', '1', 'Tariq Malik (Parent)', 'parent@sch001.com', '$2y$10$LJsB/T6KUYua2iZ7O0XbUOBPqp423nKHsl5YWqv4IrjvKXuZJz3Hm', 'parent', '1', '2026-10-28 17:50:38', '2026-10-28 08:45:59', '2026-10-28 17:50:38'),
('9', '2', 'Bilal Ahmed (School Admin)', 'schooladmin@sch002.com', '$2y$10$nRYMjRfSR6dATyrJhArkLeH.VPZjGCWSmtdU2juQ0kDJAYL/1C7xa', 'school_admin', '1', '2026-10-28 11:44:49', '2026-10-28 08:45:59', '2026-10-28 11:44:49'),
('10', '2', 'Nadia Hussain (Teacher)', 'teacher@sch002.com', '$2y$10$tXZWiGkn8cFHF3cY8VWSC.AbAI3i3nkdBsWAVuzAFVEeYgAK8xU4W', 'teacher', '1', NULL, '2026-10-28 08:45:59', '2026-10-28 08:45:59'),
('11', '2', 'Hamza Sheikh (Student)', 'student@sch002.com', '$2y$10$SI.TLxFn8j.qWtiFOU0aL.TngTHDAubk7fHmDaMJdiGgEcd/DQpYK', 'student', '1', NULL, '2026-10-28 08:45:59', '2026-10-28 08:45:59'),
('12', '2', 'Fatima Sheikh (Parent)', 'parent@sch002.com', '$2y$10$MVA8HsHUct3EkI9ABDX9oOAk0v/eDvO1zEtZqO1RYlUZLwebje3R6', 'parent', '1', NULL, '2026-10-28 08:46:00', '2026-10-28 08:46:00');

-- --------------------------------------------------------
-- Table structure for table `school_subscriptions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `school_subscriptions`;
CREATE TABLE `school_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `plan` enum('basic','pro','enterprise') NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','cancelled','expired') NOT NULL DEFAULT 'active',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sub_school` (`school_id`),
  KEY `idx_sub_status` (`status`),
  KEY `idx_sub_end` (`end_date`),
  CONSTRAINT `fk_sub_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a subscription record for every existing school
INSERT INTO `school_subscriptions`
  (`school_id`, `plan`, `price`, `billing_cycle`, `start_date`, `end_date`, `status`, `payment_method`, `transaction_id`)
SELECT
  `id`,
  `plan`,
  CASE `plan` WHEN 'enterprise' THEN 500.00 WHEN 'pro' THEN 150.00 ELSE 50.00 END,
  'yearly',
  DATE(`created_at`),
  DATE_ADD(DATE(`created_at`), INTERVAL 1 YEAR),
  'active',
  'migration',
  CONCAT('MIGRATED-', `school_code`)
FROM `schools`;

-- --------------------------------------------------------
-- Table structure for table `contact_messages`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(150) NOT NULL DEFAULT 'General enquiry',
  `message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` enum('new','read','archived') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_school` (`school_id`),
  KEY `idx_contact_status` (`status`),
  KEY `idx_contact_created` (`created_at`),
  CONSTRAINT `fk_contact_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
