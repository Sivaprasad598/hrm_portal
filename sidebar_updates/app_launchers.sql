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
-- Table structure for table `app_launchers`
--

CREATE TABLE `app_launchers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `icon` varchar(191) NOT NULL,
  `color` varchar(50) NOT NULL DEFAULT '#4f46e5',
  `route` varchar(191) DEFAULT NULL,
  `url` varchar(191) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `target` varchar(20) NOT NULL DEFAULT '_self',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_launchers`
--

INSERT INTO `app_launchers` (`id`, `name`, `slug`, `icon`, `color`, `route`, `url`, `sort_order`, `is_active`, `target`, `created_at`, `updated_at`) VALUES
(1, 'Dashboard', 'dashboard', 'fa fa-home', '#0f766e', 'dashboard', NULL, 1, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(2, 'My Calendar', 'my-calendar', 'fa fa-calendar', '#9333ea', 'my-calendar.index', NULL, 2, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(3, 'Leads', 'leads', 'fa fa-bullseye', '#dc2626', 'lead-contact.index', NULL, 3, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(4, 'Clients', 'clients', 'fa fa-users', '#059669', 'clients.index', NULL, 4, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(5, 'HR', 'hr', 'fa fa-user-tie', '#2563eb', 'employees.index', NULL, 5, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(6, 'Employees', 'employees', 'fa fa-user', '#ea580c', 'employees.index', NULL, 6, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(7, 'Leaves', 'leaves', 'fa fa-plane', '#e11d48', 'leaves.index', NULL, 7, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(8, 'Leave Management', 'leave-mgmt', 'fa fa-clipboard', '#0d9488', 'leave-management.index', NULL, 8, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(9, 'Identity Card', 'identity', 'fa fa-id-card', '#65a30d', 'identity.index', NULL, 9, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(10, 'Payslips', 'payslips', 'fa fa-file-invoice', '#7c3aed', 'payslips.index', NULL, 10, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(11, 'Shift Roster', 'shift-roster', 'fa fa-clock', '#d97706', 'shifts.index', NULL, 11, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(12, 'Attendance', 'attendance', 'fa fa-check-square', '#059669', 'attendances.index', NULL, 12, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(13, 'Holiday', 'holiday', 'fa fa-umbrella', '#7c3aed', 'holidays.index', NULL, 13, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(14, 'Designation', 'designation', 'fa fa-briefcase', '#b91c1c', 'designations.index', NULL, 14, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(15, 'Department', 'department', 'fa fa-sitemap', '#0d9488', 'departments.index', NULL, 15, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(16, 'Work', 'work', 'fa fa-briefcase', '#7c3aed', 'projects.index', NULL, 16, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(17, 'Finance', 'finance', 'fa fa-coins', '#b91c1c', 'invoices.index', NULL, 17, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(18, 'Products', 'products', 'fa fa-cube', '#059669', 'products.index', NULL, 18, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(19, 'Orders', 'orders', 'fa fa-shopping-cart', '#2563eb', 'orders.index', NULL, 19, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(20, 'Tickets', 'tickets', 'fa fa-headset', '#ea580c', 'tickets.index', NULL, 20, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(21, 'Events', 'events', 'fa fa-calendar-alt', '#e11d48', 'events.index', NULL, 21, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(22, 'Messages', 'messages', 'fa fa-comments', '#0891b2', 'messages.index', NULL, 22, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(23, 'Notice Board', 'notice-board', 'fa fa-thumbtack', '#16a34a', 'notices.index', NULL, 23, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(24, 'Knowledge Base', 'knowledgebase', 'fa fa-book', '#7c3aed', 'knowledgebase.index', NULL, 24, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(25, 'Reports', 'reports', 'fa fa-chart-pie', '#ea580c', 'task-report.index', NULL, 25, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(26, 'Settings', 'settings', 'fa fa-cog', '#0d9488', 'company-settings.index', NULL, 26, 1, '_self', '2026-10-06 05:16:07', '2026-10-06 07:02:43'),
(27, 'Projects', 'projects', 'fa fa-briefcase', '#f90606', 'public/account/projects', 'public/account/projects', 27, 1, '_self', '2026-10-06 00:27:29', '2026-10-06 07:02:43'),
(28, 'TEST', 'test', 'fas fa-clipboard-check', '#4f46e5', NULL, NULL, 28, 1, '_self', '2026-10-06 07:17:22', '2026-10-06 07:20:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_launchers`
--
ALTER TABLE `app_launchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `app_launchers_slug_unique` (`slug`),
  ADD KEY `app_launchers_sort_index` (`sort_order`),
  ADD KEY `app_launchers_active_index` (`is_active`,`sort_order`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_launchers`
--
ALTER TABLE `app_launchers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
