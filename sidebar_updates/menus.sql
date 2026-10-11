-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2026 at 03:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrm_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `route` varchar(191) DEFAULT NULL,
  `icon` varchar(191) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `parent_id`, `name`, `slug`, `route`, `icon`, `level`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Dashboard', 'dashboard', NULL, NULL, 0, 2, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(2, 1, 'Private Dashboard', 'private-dashboard', 'dashboard', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-06 00:58:38'),
(3, 1, 'Advanced Dashboard', 'advanced-dashboard', 'dashboard.advanced', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-06 00:58:15'),
(4, NULL, 'My Calendar', 'my-calendar', 'my-calendar.index', NULL, 0, 3, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(5, NULL, 'Leads', 'leads', NULL, NULL, 0, 6, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(6, 5, 'Lead Contact', 'lead-contact', 'lead-contact.index', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-05 07:28:24'),
(7, 5, 'Deals', 'deals', 'deals.index', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 07:28:11'),
(8, NULL, 'Clients', 'clients', 'clients.index', NULL, 0, 7, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(9, NULL, 'HR', 'hr', NULL, NULL, 0, 8, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(10, 9, 'Employees', 'employees', 'employees.index', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(11, 9, 'Employee Exit', 'employee-exit', 'employee-exit.index', NULL, 1, 6, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(12, 9, 'Employee Termination', 'employee-termination', 'employee-termination.index', NULL, 1, 3, 1, '2026-10-05 06:00:29', '2026-10-06 04:09:51'),
(13, 9, 'Employee Resignation', 'employee-resignation', 'resignation-management.index', NULL, 1, 4, 1, '2026-10-05 06:00:29', '2026-10-06 04:10:28'),
(14, 9, 'Probation Period Requests', 'probation-period-requests', 'probation-requests.index', NULL, 1, 7, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(15, 9, 'Resignation', 'resignation', 'resignations.index', NULL, 1, 8, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(16, 9, 'Leaves', 'leaves', 'leaves.index', NULL, 1, 9, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(17, 9, 'Leave Management', 'leave-management', 'leave-management.index', NULL, 1, 5, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(18, 9, 'Leave Year End', 'leave-year-end', 'leave-year-end.index', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-06 03:22:56'),
(19, 9, 'Identity Card', 'identity-card', 'identity.index', NULL, 1, 10, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(20, 9, 'Offer Letter', 'offer-letter', 'offer-letter.index', NULL, 1, 11, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(21, 9, 'Payslips', 'payslips', 'payslips.index', NULL, 1, 12, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(22, 9, 'Shift Roster', 'shift-roster', 'shifts.index', NULL, 1, 13, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(23, 9, 'Attendance', 'attendance', 'attendances.index', NULL, 1, 14, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(24, 9, 'Holiday', 'holiday', 'holidays.index', NULL, 1, 15, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(25, 9, 'Designation', 'designation', 'designations.index', NULL, 1, 16, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(26, 9, 'Department', 'department', 'departments.index', NULL, 1, 17, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(27, 9, 'Appreciation', 'appreciation', 'appreciations.index', NULL, 1, 18, 1, '2026-10-05 06:00:29', '2026-10-06 04:15:08'),
(28, NULL, 'Biometric', 'biometric', NULL, NULL, 0, 9, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(29, 28, 'Sync Calendar', 'sync-calendar', 'biometric.calendar', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(30, 28, 'Device Attendance', 'device-attendance', 'biometric.attendance', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(31, 28, 'Attendance Sync', 'attendance-sync', 'biometric.attendance-sync', NULL, 1, 3, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(32, 28, 'Punch Log', 'punch-log', 'biometric.punch-log', NULL, 1, 4, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(33, 28, 'Employee 360', 'employee-360', 'biometric.profile', NULL, 1, 5, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(34, 28, 'Exceptions', 'exceptions', 'biometric.exceptions', NULL, 1, 6, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(35, 28, 'Shifts', 'shifts', 'biometric.shifts', NULL, 1, 7, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(36, 28, 'Employee Mapping', 'employee-mapping', 'biometric.employees', NULL, 1, 8, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(37, 28, 'Employee Comparison', 'employee-comparison', 'biometric.comparison', NULL, 1, 9, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(38, 28, 'Devices', 'devices', 'biometric.devices', NULL, 1, 10, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(39, 28, 'Sync Status', 'sync-status', 'biometric.sync-logs', NULL, 1, 11, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(40, NULL, 'Work', 'work', NULL, NULL, 0, 10, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(41, 40, 'Contracts', 'contracts', 'contracts.index', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(42, 40, 'Projects', 'projects', 'projects.index', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(43, 40, 'Tasks', 'tasks', 'tasks.index', NULL, 1, 3, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(44, 40, 'Timesheet', 'timesheet', 'timelogs.index', NULL, 1, 4, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(45, NULL, 'Finance', 'finance', NULL, NULL, 0, 11, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(46, 45, 'Proposal', 'proposal', 'proposals.index', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(47, 45, 'Estimates', 'estimates', 'estimates.index', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(48, 45, 'Invoices', 'invoices', 'invoices.index', NULL, 1, 3, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(49, 45, 'Payments', 'payments', 'payments.index', NULL, 1, 4, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(50, 45, 'Credit Note', 'credit-note', 'creditnotes.index', NULL, 1, 5, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(51, 45, 'Expenses', 'expenses', 'expenses.index', NULL, 1, 6, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(52, 45, 'Bank Account', 'bank-account', 'bankaccounts.index', NULL, 1, 7, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(53, NULL, 'Tickets', 'tickets', 'tickets.index', NULL, 0, 12, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(54, NULL, 'Events', 'events', 'events.index', NULL, 0, 13, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(55, NULL, 'Messages', 'messages', 'messages.index', NULL, 0, 14, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(56, NULL, 'Notice Board', 'notice-board', 'notices.index', NULL, 0, 4, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(57, NULL, 'Reports', 'reports', NULL, NULL, 0, 16, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(58, 57, 'Task Report', 'task-report', 'task-report.index', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(59, 57, 'Time Log Report', 'time-log-report', 'time-log-report.index', NULL, 1, 2, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(60, 57, 'Weekly Timesheet', 'weekly-timesheet', 'time-log-weekly-report.index', NULL, 1, 3, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(61, 57, 'Finance Report', 'finance-report', 'finance-report.index', NULL, 1, 4, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(62, 57, 'Income Vs Expense', 'income-vs-expense', 'income-expense-report.index', NULL, 1, 5, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(63, 57, 'Leave Report', 'leave-report', 'leave-report.leave_quota', NULL, 1, 6, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(64, 57, 'Attendance Report', 'attendance-report', 'attendance-report.index', NULL, 1, 7, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(65, 57, 'Expense Report', 'expense-report', 'expense-report.index', NULL, 1, 8, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(66, 57, 'Deal Report', 'deal-report', 'lead-report.index', NULL, 1, 9, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(67, NULL, 'Settings', 'settings', NULL, NULL, 0, 17, 1, '2026-10-05 06:00:29', '2026-10-06 07:45:53'),
(68, 67, 'App Settings', 'app-settings', 'app-settings.index', NULL, 1, 1, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(69, 67, 'Attendance Settings', 'attendance-settings', 'attendance-settings.index', NULL, 1, 3, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(70, 67, 'Biometric Settings', 'biometric-settings', 'biometric-settings.index', NULL, 1, 4, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(71, 67, 'Business Address', 'business-address', 'business-address.index', NULL, 1, 5, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(72, 67, 'Company Settings', 'company-settings', 'company-settings.index', NULL, 1, 6, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(73, 67, 'Contract Settings', 'contract-settings', 'contract-settings.index', NULL, 1, 7, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(74, 67, 'Currency Settings', 'currency-settings', 'currency-settings.index', NULL, 1, 8, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(75, 67, 'Custom Fields', 'custom-fields', 'custom-fields.index', NULL, 1, 9, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(76, 67, 'Custom Link Settings', 'custom-link-settings', 'custom-link-settings.index', NULL, 1, 10, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(77, 67, 'Database Backup Settings', 'database-backup-settings', 'database-backup-settings.index', NULL, 1, 11, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(78, 67, 'Downloads', 'downloads', 'downloads.index', NULL, 1, 12, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(79, 67, 'Finance Settings', 'finance-settings', 'invoice-settings.index', NULL, 1, 13, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(80, 67, 'GDPR Settings', 'gdpr-settings', 'gdpr-settings.index', NULL, 1, 14, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(81, 67, 'Google Calendar Settings', 'google-calendar-settings', 'google-calendar-settings.index', NULL, 1, 15, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(82, 67, 'Language Settings', 'language-settings', 'language-settings.index', NULL, 1, 16, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(83, 67, 'Lead Settings', 'lead-settings', 'lead-settings.index', NULL, 1, 17, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(84, 67, 'Leaves Settings', 'leaves-settings', 'leaves-settings.index', NULL, 1, 18, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(85, 67, 'Message Settings', 'message-settings', 'message-settings.index', NULL, 1, 19, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(86, 67, 'Module Settings', 'module-settings', 'module-settings.index', NULL, 1, 20, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(87, 67, 'Notice Period Settings', 'notice-period-settings', 'notice-period-settings.index', NULL, 1, 21, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(88, 67, 'Notification Settings', 'notification-settings', 'notifications.index', NULL, 1, 22, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(89, 67, 'Payment Credentials', 'payment-credentials', 'payment-gateway-settings.index', NULL, 1, 23, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(90, 67, 'Payslip Settings', 'payslip-settings', 'payslip-settings.index', NULL, 1, 24, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(91, 67, 'Profile Settings', 'profile-settings', 'profile-settings.index', NULL, 1, 25, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(92, 67, 'Project Settings', 'project-settings', 'project-settings.index', NULL, 1, 26, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(93, 67, 'Roles & Permissions', 'roles-permissions', 'role-permissions.index', NULL, 1, 27, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(94, 67, 'Security Settings', 'security-settings', 'security-settings.index', NULL, 1, 30, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(95, 67, 'Sign Up Settings', 'sign-up-settings', 'sign-up-settings.index', NULL, 1, 31, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(96, 67, 'Social Login Settings', 'social-login-settings', 'social-auth-settings.index', NULL, 1, 32, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(97, 67, 'Storage Settings', 'storage-settings', 'storage-settings.index', NULL, 1, 33, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(98, 67, 'Task Settings', 'task-settings', 'task-settings.index', NULL, 1, 34, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(99, 67, 'Tax Settings', 'tax-settings', 'taxes.index', NULL, 1, 35, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(100, 67, 'Theme Settings', 'theme-settings', 'theme-settings.index', NULL, 1, 36, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(101, 67, 'Ticket Settings', 'ticket-settings', 'ticket-settings.index', NULL, 1, 37, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(102, 67, 'Time Log Settings', 'time-log-settings', 'timelog-settings.index', NULL, 1, 38, 1, '2026-10-05 06:00:29', '2026-10-05 06:00:29'),
(103, NULL, 'Test', 'test', 'public/account/menu-management', 'fa fa-briefcase', 0, 5, 1, '2026-10-05 06:21:36', '2026-10-06 07:45:53'),
(108, 103, 'goo', 'goo', NULL, NULL, 1, 1, 1, '2026-10-06 04:09:09', '2026-10-06 07:37:19'),
(110, 9, 'testing', 'testing', NULL, NULL, 1, 19, 1, '2026-10-06 04:51:06', '2026-10-06 04:51:06'),
(112, 67, 'App Launcher Settings', 'app-launcher-settings', 'app-launcher-settings.index', NULL, 1, 2, 1, '2026-10-06 06:30:43', '2026-10-06 06:30:43'),
(113, 67, 'Assign Menus', 'assign-menus', 'assign-menus.index', NULL, 1, 28, 1, '2026-10-06 06:30:43', '2026-10-06 06:30:43'),
(114, 67, 'Add Menu', 'add-menu', 'menu-management.index', NULL, 1, 29, 1, '2026-10-06 06:30:43', '2026-10-06 06:30:43'),
(116, 83, 'row', 'row', NULL, NULL, 2, 1, 1, '2026-10-06 06:48:44', '2026-10-06 06:57:26'),
(117, 63, 'DEEE', 'deee', NULL, NULL, 2, 1, 1, '2026-10-06 07:01:20', '2026-10-06 07:01:20'),
(118, NULL, 'New Test', 'new-test', NULL, 'fas fa-vial', 0, 1, 1, '2026-10-06 07:28:08', '2026-10-06 07:45:53'),
(119, 118, 'sub test', 'sub-test', NULL, 'fas fa-chart-line', 1, 1, 1, '2026-10-06 07:28:45', '2026-10-06 07:34:17'),
(120, 83, 'child test', 'child-test', NULL, 'fas fa-clipboard-check', 2, 2, 1, '2026-10-06 07:29:13', '2026-10-06 07:44:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menus_slug_unique` (`slug`),
  ADD KEY `menus_parent_id_foreign` (`parent_id`),
  ADD KEY `menus_parent_sort_index` (`parent_id`,`sort_order`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `menus`
--
ALTER TABLE `menus`
  ADD CONSTRAINT `menus_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
