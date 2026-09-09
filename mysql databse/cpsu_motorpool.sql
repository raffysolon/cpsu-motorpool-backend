-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 09, 2026 at 08:40 AM
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
-- Database: `cpsu_motorpool`
--

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
-- Table structure for table `campus_coordinator_assignments`
--

CREATE TABLE `campus_coordinator_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campus_name` varchar(255) NOT NULL,
  `coordinator_name` varchar(255) NOT NULL,
  `driver_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vehicle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campus_coordinator_assignments`
--

INSERT INTO `campus_coordinator_assignments` (`id`, `campus_name`, `coordinator_name`, `driver_id`, `vehicle_id`, `created_at`, `updated_at`) VALUES
(4, 'San Carlos', 'Ken M Balogo', 3, 2, '2026-09-05 06:33:46', '2026-09-05 06:33:46'),
(6, 'Hinigaran', 'Richard Deut', 4, 3, '2026-09-08 01:49:56', '2026-09-08 01:49:56');

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
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_08_26_084802_create_personal_access_tokens_table', 1),
(5, '2026_08_27_003616_create_vehicles_table', 1),
(6, '2026_08_27_003628_create_campus_coordinator_assignments_table', 1),
(7, '2026_08_27_120100_add_contact_and_license_to_users_table', 1),
(8, '2026_08_27_120325_create_trips_table', 1),
(9, '2026_08_27_120333_create_trip_passengers_table', 1),
(10, '2026_09_01_112710_create_notifications_table', 1),
(11, '2026_09_05_000001_create_trip_movements_table', 2),
(12, '2026_09_05_000002_add_return_schedule_to_trips_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `trip_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `trip_id`, `message`, `is_read`, `created_at`, `updated_at`) VALUES
(9, 1, 7, 'New trip request from Raphael SOlon to Example', 0, '2026-09-05 19:36:18', '2026-09-05 19:36:18'),
(10, 3, 7, 'Your trip request to Example has been approved', 1, '2026-09-05 19:37:15', '2026-09-07 01:09:54'),
(11, 1, 8, 'New trip request from Raphael SOlon to Bacolod', 0, '2026-09-06 10:53:26', '2026-09-06 10:53:26'),
(12, 3, 8, 'Your trip request to Bacolod has been approved', 1, '2026-09-06 10:55:07', '2026-09-06 22:05:53'),
(13, 6, 9, 'A new trip to kabankalan has been created and assigned to you', 1, '2026-09-06 13:28:00', '2026-09-08 01:54:17'),
(14, 5, 10, 'A new trip to Dumaguete has been created and assigned to you', 1, '2026-09-07 01:36:42', '2026-09-07 01:37:13'),
(15, 1, 11, 'New trip request from Raphael SOlon to Hinigaran', 0, '2026-09-07 02:54:43', '2026-09-07 02:54:43'),
(16, 3, 11, 'Your trip request to Hinigaran has been approved', 1, '2026-09-07 02:56:11', '2026-09-07 03:31:34'),
(17, 1, 11, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 02:56:25', '2026-09-07 02:56:25'),
(18, 1, 12, 'New trip request from Raphael SOlon to Bacolod', 0, '2026-09-07 03:27:43', '2026-09-07 03:27:43'),
(19, 1, 13, 'New trip request from Raphael SOlon to Bacolod', 0, '2026-09-07 03:29:28', '2026-09-07 03:29:28'),
(20, 3, 13, 'Your trip request to Bacolod has been approved', 1, '2026-09-07 03:31:19', '2026-09-07 03:31:40'),
(21, 1, 13, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 03:32:12', '2026-09-07 03:32:12'),
(22, 1, 14, 'New trip request from Raphael SOlon to Kabangkalan', 0, '2026-09-07 05:16:56', '2026-09-07 05:16:56'),
(23, 3, 14, 'Your trip request to Kabangkalan has been approved', 1, '2026-09-07 05:18:46', '2026-09-07 06:06:58'),
(24, 1, 14, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 05:19:24', '2026-09-07 05:19:24'),
(25, 1, 15, 'New trip request from Raphael SOlon to eeee', 0, '2026-09-07 05:36:01', '2026-09-07 05:36:01'),
(26, 3, 15, 'Your trip request to eeee has been approved', 1, '2026-09-07 05:36:14', '2026-09-07 05:36:25'),
(27, 1, 15, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 05:36:27', '2026-09-07 05:36:27'),
(28, 1, 16, 'New trip request from Raphael SOlon to raphael', 0, '2026-09-07 05:47:54', '2026-09-07 05:47:54'),
(29, 3, 16, 'Your trip request to raphael has been approved', 1, '2026-09-07 05:48:22', '2026-09-07 06:02:25'),
(30, 1, 16, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 06:01:15', '2026-09-07 06:01:15'),
(31, 1, 17, 'New trip request from Raphael SOlon to Kabangkalan', 0, '2026-09-07 06:11:51', '2026-09-07 06:11:51'),
(32, 3, 17, 'Your trip request to Kabangkalan has been approved', 1, '2026-09-07 06:13:35', '2026-09-07 06:21:32'),
(33, 1, 17, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 06:14:06', '2026-09-07 06:14:06'),
(34, 1, 18, 'New trip request from Raphael SOlon to San Carlos', 0, '2026-09-07 06:21:17', '2026-09-07 06:21:17'),
(35, 3, 18, 'Your trip request to San Carlos has been approved', 1, '2026-09-07 06:21:52', '2026-09-07 06:24:22'),
(36, 1, 18, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 06:22:14', '2026-09-07 06:22:14'),
(37, 1, 19, 'New trip request from Raphael SOlon to fmdm', 0, '2026-09-07 06:25:13', '2026-09-07 06:25:13'),
(38, 3, 19, 'Your trip request to fmdm has been approved', 1, '2026-09-07 06:25:29', '2026-09-08 00:47:37'),
(39, 1, 19, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-07 06:25:31', '2026-09-07 06:25:31'),
(40, 1, 20, 'New trip request from Raphael SOlon to djdjj', 0, '2026-09-07 06:53:54', '2026-09-07 06:53:54'),
(41, 3, 20, 'Your trip request to djdjj has been approved', 1, '2026-09-07 07:08:25', '2026-09-08 00:47:32'),
(42, 1, 20, 'Raphael SOlon started the trip late after the scheduled departure.', 1, '2026-09-07 07:08:40', '2026-09-08 00:58:25'),
(43, 1, 21, 'New trip request from Raphael SOlon to Kabangkalan', 1, '2026-09-07 23:41:08', '2026-09-08 00:58:24'),
(44, 3, 21, 'Your trip request to Kabangkalan has been approved', 1, '2026-09-07 23:42:42', '2026-09-07 23:43:18'),
(45, 1, 21, 'Raphael SOlon started the trip early, 10 minutes before the scheduled departure.', 1, '2026-09-07 23:44:21', '2026-09-08 00:58:22'),
(46, 1, 22, 'New trip request from Raphael SOlon to Kabangkalan', 1, '2026-09-08 00:54:17', '2026-09-08 00:58:21'),
(47, 3, 22, 'Your trip request to Kabangkalan has been approved', 1, '2026-09-08 00:55:45', '2026-09-08 01:05:05'),
(48, 1, 22, 'Raphael SOlon started the trip early, 10 minutes before the scheduled departure.', 0, '2026-09-08 00:56:07', '2026-09-08 00:56:07'),
(49, 1, 9, 'example started the trip late after the scheduled departure.', 0, '2026-09-08 01:52:22', '2026-09-08 01:52:22'),
(50, 1, 23, 'New trip request from Raphael SOlon to LA suerte', 0, '2026-09-08 02:16:12', '2026-09-08 02:16:12'),
(51, 3, 23, 'Your trip request to LA suerte has been approved', 1, '2026-09-08 02:17:30', '2026-09-08 02:17:52'),
(52, 3, 23, 'Your trip request to LA suerte has been approved', 1, '2026-09-08 02:17:31', '2026-09-08 02:17:52'),
(53, 1, 23, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-08 02:18:03', '2026-09-08 02:18:03'),
(54, 1, 24, 'New trip request from Raphael SOlon to Himamaylan', 0, '2026-09-08 08:14:47', '2026-09-08 08:14:47'),
(55, 3, 24, 'Your trip request to Himamaylan has been approved', 1, '2026-09-08 08:16:20', '2026-09-08 08:16:52'),
(56, 1, 24, 'Raphael SOlon started the trip early, 10 minutes before the scheduled departure.', 0, '2026-09-08 08:21:52', '2026-09-08 08:21:52'),
(57, 1, 25, 'New trip request from Raphael SOlon to 2222', 0, '2026-09-08 12:15:26', '2026-09-08 12:15:26'),
(58, 3, 25, 'Your trip request to 2222 has been approved', 0, '2026-09-08 12:16:59', '2026-09-08 12:16:59'),
(59, 1, 25, 'Raphael SOlon started the trip late after the scheduled departure.', 0, '2026-09-08 12:18:02', '2026-09-08 12:18:02');

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
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'auth-token', 'ee9a5230935d7dd2b01b9b9d8554d87cc30af4dea1e67c5356e866154e29cb38', '[\"*\"]', '2026-09-02 04:50:19', NULL, '2026-09-02 04:46:10', '2026-09-02 04:50:19'),
(2, 'App\\Models\\User', 3, 'auth-token', 'ef7b958419fda5426273c4df941b9f7c2e733ec025bd94f7bd3d9886dc0ddd9e', '[\"*\"]', '2026-09-02 04:50:50', NULL, '2026-09-02 04:50:38', '2026-09-02 04:50:50'),
(3, 'App\\Models\\User', 1, 'auth-token', '673a8a85ab55c50c42e2b678d062117cb1fc8d0fe29c2422c9722540d02a671b', '[\"*\"]', '2026-09-02 17:22:03', NULL, '2026-09-02 17:16:37', '2026-09-02 17:22:03'),
(4, 'App\\Models\\User', 3, 'auth-token', 'dd68b5bbb6ea7191b61d3629caaafb8fc94d1b433a99c015b44ac33114e2fd5c', '[\"*\"]', '2026-09-02 17:23:58', NULL, '2026-09-02 17:23:42', '2026-09-02 17:23:58'),
(5, 'App\\Models\\User', 3, 'auth-token', '0222f67753d6879099365e794b425e208ed1a0fc55a4435fc8ac2c50f89d3f39', '[\"*\"]', '2026-09-02 17:29:05', NULL, '2026-09-02 17:24:45', '2026-09-02 17:29:05'),
(6, 'App\\Models\\User', 3, 'auth-token', 'cdfb657d4a814cb341a1b005391fd2c42c60a844f4092a5fe6a6fa30f4a1111c', '[\"*\"]', '2026-09-02 17:53:25', NULL, '2026-09-02 17:49:20', '2026-09-02 17:53:25'),
(7, 'App\\Models\\User', 1, 'auth-token', '86c3875ca45ed99cb7e6a789fd4f04b2dca01827b9f3e303b362baa15bed051e', '[\"*\"]', '2026-09-02 17:56:36', NULL, '2026-09-02 17:53:49', '2026-09-02 17:56:36'),
(8, 'App\\Models\\User', 3, 'auth-token', '062e9fdfaf1eb383ca20bb04da91cd74b53e510c12c0c52c48b4921b9a3318e6', '[\"*\"]', '2026-09-02 18:08:51', NULL, '2026-09-02 17:58:39', '2026-09-02 18:08:51'),
(9, 'App\\Models\\User', 3, 'auth-token', 'd8ba5356ae50bdc753c42d6d0035f8303a37a9c0c2a2761f6908675de0b652e9', '[\"*\"]', '2026-09-02 18:29:11', NULL, '2026-09-02 18:17:57', '2026-09-02 18:29:11'),
(10, 'App\\Models\\User', 3, 'auth-token', '03fff95460bbff4f1739540014c6527e1e44eb3cdea4f3a8c00373b3ceeed88d', '[\"*\"]', '2026-09-02 19:03:18', NULL, '2026-09-02 18:51:08', '2026-09-02 19:03:18'),
(11, 'App\\Models\\User', 3, 'auth-token', 'fb0e6eef708879ba07b3d88de692e9f4d8c987246dc57d08caac6da60a744d19', '[\"*\"]', '2026-09-02 19:05:58', NULL, '2026-09-02 19:05:52', '2026-09-02 19:05:58'),
(12, 'App\\Models\\User', 1, 'auth-token', '1d3cb3f212ed30e699f8a92d2af0c3c0dd7917b2845b350ee9a2ea924a5b3ba3', '[\"*\"]', '2026-09-02 19:08:31', NULL, '2026-09-02 19:06:28', '2026-09-02 19:08:31'),
(13, 'App\\Models\\User', 5, 'auth-token', '2dc2bf8bca26475f1367aa4aaa8e46296f1f6c15149de97763d78df3ecd527ef', '[\"*\"]', '2026-09-02 19:09:05', NULL, '2026-09-02 19:08:47', '2026-09-02 19:09:05'),
(14, 'App\\Models\\User', 3, 'auth-token', '949073ac7960952c9a036b08591225df1a86428200d6c4a38f7f62c92de5f871', '[\"*\"]', '2026-09-02 19:56:51', NULL, '2026-09-02 19:47:10', '2026-09-02 19:56:51'),
(15, 'App\\Models\\User', 5, 'auth-token', 'd404556e863f9fc2cafc09ad95f28d909d689989f4e23b3efa8fe3bb26b9b93b', '[\"*\"]', '2026-09-02 20:12:17', NULL, '2026-09-02 20:07:33', '2026-09-02 20:12:17'),
(16, 'App\\Models\\User', 3, 'auth-token', '819797190bb0309f008b58bc759bf61fc2f931fff764de6b968de5d083236ce5', '[\"*\"]', '2026-09-02 20:12:43', NULL, '2026-09-02 20:12:38', '2026-09-02 20:12:43'),
(17, 'App\\Models\\User', 1, 'auth-token', '5cf19f11f89055cfeec47a9fade13c82ff825e1ac3779531edd45db26ef3a1ee', '[\"*\"]', '2026-09-02 20:17:45', NULL, '2026-09-02 20:13:12', '2026-09-02 20:17:45'),
(18, 'App\\Models\\User', 3, 'auth-token', '3e70e8a9663186b3c586cfc272d562e5d02b1673892c4055d53e87f274610b78', '[\"*\"]', '2026-09-02 23:15:14', NULL, '2026-09-02 20:18:06', '2026-09-02 23:15:14'),
(19, 'App\\Models\\User', 3, 'auth-token', 'b412734f3c04e6b4dfd6df24d95c5e609cee27731bb830c254d9b010c392b6ca', '[\"*\"]', '2026-09-02 23:16:30', NULL, '2026-09-02 23:15:27', '2026-09-02 23:16:30'),
(20, 'App\\Models\\User', 3, 'auth-token', '1efed0fccf428fec0872a53e60dafe033ffe1cd3b5bcf27997bc8ee5bde7212b', '[\"*\"]', '2026-09-03 00:34:57', NULL, '2026-09-03 00:32:45', '2026-09-03 00:34:57'),
(21, 'App\\Models\\User', 1, 'auth-token', 'ff6a037bd40e98335258f5900e292580cf302b37ab59cf529ddf82e9cfb8f0fa', '[\"*\"]', '2026-09-03 00:35:46', NULL, '2026-09-03 00:35:11', '2026-09-03 00:35:46'),
(22, 'App\\Models\\User', 3, 'auth-token', '58cdcce86d491701be453407b6973360ad4296110828f099d8b4d1982e87d744', '[\"*\"]', '2026-09-03 03:06:34', NULL, '2026-09-03 03:06:13', '2026-09-03 03:06:34'),
(23, 'App\\Models\\User', 1, 'auth-token', '10f72aece4da8f98763c12270e826205737125ce8adedfa9bb4cddf69cf1c026', '[\"*\"]', '2026-09-03 03:27:26', NULL, '2026-09-03 03:08:29', '2026-09-03 03:27:26'),
(24, 'App\\Models\\User', 1, 'auth-token', '356acfd072653dc81a1eba4da60dcbc97703333e67bd8b43667523f523a58a49', '[\"*\"]', '2026-09-03 04:37:04', NULL, '2026-09-03 04:36:54', '2026-09-03 04:37:04'),
(25, 'App\\Models\\User', 3, 'auth-token', '45dfa7d494c3ed51a1de19896f881f9053918e486d4656abaa4846d51d12fbc2', '[\"*\"]', '2026-09-03 05:20:14', NULL, '2026-09-03 04:43:25', '2026-09-03 05:20:14'),
(26, 'App\\Models\\User', 3, 'auth-token', 'e67d507565e8800a691b9f1fd49bf915ee5f266c51012e0ffa9c47e5ae502195', '[\"*\"]', '2026-09-03 04:53:42', NULL, '2026-09-03 04:53:34', '2026-09-03 04:53:42'),
(27, 'App\\Models\\User', 3, 'auth-token', 'c9bf87f13bb30e290d841af7216c608fdbdbbf665bf32ca1fda078438f19d8d9', '[\"*\"]', '2026-09-03 06:06:39', NULL, '2026-09-03 05:36:40', '2026-09-03 06:06:39'),
(28, 'App\\Models\\User', 1, 'auth-token', '93d99a0065baad4f5f27e803c0b2812ecb95b647353c6a9575fa255427b5b3d7', '[\"*\"]', NULL, NULL, '2026-09-03 06:07:05', '2026-09-03 06:07:05'),
(29, 'App\\Models\\User', 3, 'auth-token', '6f58bb4536c72b0e9733c89327111fed56192f3dc42f64b290b48560419ef49b', '[\"*\"]', '2026-09-03 06:07:46', NULL, '2026-09-03 06:07:40', '2026-09-03 06:07:46'),
(30, 'App\\Models\\User', 1, 'auth-token', 'bf17d44f511f165b93ee5d0b03789776ae4c18434427cf22a6bb7c477b6e13fc', '[\"*\"]', '2026-09-04 03:51:06', NULL, '2026-09-04 02:05:07', '2026-09-04 03:51:06'),
(31, 'App\\Models\\User', 3, 'auth-token', 'aaf198acd35926fd51f5b9b9f3fb621a20b052c901c83eab651e26173dd3012d', '[\"*\"]', NULL, NULL, '2026-09-04 03:34:31', '2026-09-04 03:34:31'),
(32, 'App\\Models\\User', 3, 'auth-token', 'e6a35abde26a996c70d480543d939a506904b9dcc594deac2dcd1e5ba38589f5', '[\"*\"]', '2026-09-04 05:11:04', NULL, '2026-09-04 03:44:22', '2026-09-04 05:11:04'),
(33, 'App\\Models\\User', 1, 'auth-token', 'be3b29b3480e7b7732eaa9a302c654e4efde5521454a2946277d497e3b561195', '[\"*\"]', '2026-09-04 05:24:46', NULL, '2026-09-04 04:08:45', '2026-09-04 05:24:46'),
(34, 'App\\Models\\User', 3, 'auth-token', '6c512756994e1e1f768476f819dbf13c1513ff677cd45ddea05d19304355c005', '[\"*\"]', '2026-09-05 06:28:22', NULL, '2026-09-05 06:28:15', '2026-09-05 06:28:22'),
(35, 'App\\Models\\User', 1, 'auth-token', '5a18beeff3753d311b358ea145158445e923b51f9f095fcd98f9ac8f2c09660a', '[\"*\"]', '2026-09-05 06:33:57', NULL, '2026-09-05 06:28:45', '2026-09-05 06:33:57'),
(36, 'App\\Models\\User', 3, 'auth-token', '1babeed213092286cf92555e7ae60533561bae1ceae6296cad8fc4711b09854c', '[\"*\"]', '2026-09-05 06:35:23', NULL, '2026-09-05 06:34:28', '2026-09-05 06:35:23'),
(37, 'App\\Models\\User', 3, 'auth-token', '609ec20184913fc34fa3fbb62dcc6df7473650467969e14415edfbbc9bd2e5c6', '[\"*\"]', '2026-09-05 06:49:42', NULL, '2026-09-05 06:48:07', '2026-09-05 06:49:42'),
(38, 'App\\Models\\User', 1, 'auth-token', '634b8ee20cf23a03866d6dbd4a2d8df00a4367ae0465713da37282e2e96b7a9b', '[\"*\"]', '2026-09-05 07:02:43', NULL, '2026-09-05 06:49:59', '2026-09-05 07:02:43'),
(39, 'App\\Models\\User', 3, 'auth-token', '1740e4c17718fdbbd01c4eafaf60b039a4aec517fd3332e453bb6c77833cabb3', '[\"*\"]', '2026-09-05 07:17:13', NULL, '2026-09-05 07:05:10', '2026-09-05 07:17:13'),
(40, 'App\\Models\\User', 1, 'auth-token', '60e6cb6432b0e524d715ef4419f42d58204674758d8cf912a606b0d638109080', '[\"*\"]', '2026-09-05 07:18:00', NULL, '2026-09-05 07:17:39', '2026-09-05 07:18:00'),
(41, 'App\\Models\\User', 1, 'auth-token', 'dfebf2f30bcd79727fc9c6fe9ef6f3746d0bd5ec160de2c82eaaf2e83de9a63d', '[\"*\"]', '2026-09-05 15:05:38', NULL, '2026-09-05 15:05:06', '2026-09-05 15:05:38'),
(42, 'App\\Models\\User', 1, 'auth-token', 'cf90f637fb58116bcb1c8f041514e95675076f02d8405404ce13cc0fefb7617d', '[\"*\"]', '2026-09-05 15:06:45', NULL, '2026-09-05 15:06:26', '2026-09-05 15:06:45'),
(43, 'App\\Models\\User', 1, 'auth-token', 'b7d79cdf390ca55b94a16fd23016d1493fba3fc59146aef7cf829d3ac02604a8', '[\"*\"]', NULL, NULL, '2026-09-05 15:24:29', '2026-09-05 15:24:29'),
(44, 'App\\Models\\User', 3, 'auth-token', 'd3dd94f1729da7756a496813b815b05cd273ffce8add50d6d879fdc9510a9437', '[\"*\"]', '2026-09-05 15:25:09', NULL, '2026-09-05 15:24:55', '2026-09-05 15:25:09'),
(45, 'App\\Models\\User', 3, 'auth-token', '73c61791b2113678ba28c0b6d7ab513133ee50171f4f3b841b65fc237169a4d6', '[\"*\"]', '2026-09-05 15:37:02', NULL, '2026-09-05 15:29:25', '2026-09-05 15:37:02'),
(46, 'App\\Models\\User', 3, 'auth-token', '6df005786c50ebe3531b9675ec191c9f39f1c252fe5b7ed1ffc8ddd6ea7e1226', '[\"*\"]', '2026-09-05 16:08:40', NULL, '2026-09-05 15:39:37', '2026-09-05 16:08:40'),
(47, 'App\\Models\\User', 3, 'auth-token', '1e5940fdd181bee8768bfad1cd444f360363db13eecb254149b4a3bae2fa7a39', '[\"*\"]', '2026-09-05 16:19:06', NULL, '2026-09-05 16:12:33', '2026-09-05 16:19:06'),
(48, 'App\\Models\\User', 1, 'auth-token', '7b9b1650291d350ca231233f317fadc370fc6fe435d3de014d5d5713082aaf48', '[\"*\"]', '2026-09-05 16:37:40', NULL, '2026-09-05 16:19:14', '2026-09-05 16:37:40'),
(49, 'App\\Models\\User', 3, 'auth-token', 'dc1b0eb34e868680c4884d0a337a6c82fcfd439f56259f1c0ca8d160559eb279', '[\"*\"]', '2026-09-05 17:05:31', NULL, '2026-09-05 16:38:20', '2026-09-05 17:05:31'),
(50, 'App\\Models\\User', 3, 'auth-token', '64aef3cf3939375cb6cf950b3e3e54b478e96845a081daed5cfe6f8f8540be08', '[\"*\"]', '2026-09-05 17:10:00', NULL, '2026-09-05 17:09:39', '2026-09-05 17:10:00'),
(51, 'App\\Models\\User', 3, 'auth-token', '47d4c99bb2b73d9e40e7d75100a3b3f2669df73f3e4fb11eb067b9a00991ae20', '[\"*\"]', '2026-09-05 17:18:17', NULL, '2026-09-05 17:17:35', '2026-09-05 17:18:17'),
(52, 'App\\Models\\User', 3, 'auth-token', '6b8dafa97a865450aeb663ec99ecdc961930a5efdf641b0f2cba327c7ec67014', '[\"*\"]', '2026-09-05 19:23:07', NULL, '2026-09-05 19:04:00', '2026-09-05 19:23:07'),
(53, 'App\\Models\\User', 1, 'auth-token', '4d0a168c20c0e2cac6b104182743062625a3eca7ec3f23625d615dce0f85a244', '[\"*\"]', '2026-09-05 19:35:02', NULL, '2026-09-05 19:23:35', '2026-09-05 19:35:02'),
(54, 'App\\Models\\User', 3, 'auth-token', '803290feaae89fc9ce8947b34f06233cb72c9c715e0d3da857709920c8a4a57a', '[\"*\"]', '2026-09-05 19:36:49', NULL, '2026-09-05 19:35:30', '2026-09-05 19:36:49'),
(55, 'App\\Models\\User', 1, 'auth-token', 'b2fa8d9314ab24a4c44f8f5d682297f871f83a80e7b9c07862f3a62bb49e4606', '[\"*\"]', '2026-09-05 19:49:10', NULL, '2026-09-05 19:36:59', '2026-09-05 19:49:10'),
(56, 'App\\Models\\User', 3, 'auth-token', '900740ac61a7ff0b65483ab702525764700a6051ac2bf7c1ebd35ceeb9deee42', '[\"*\"]', '2026-09-05 19:51:56', NULL, '2026-09-05 19:51:28', '2026-09-05 19:51:56'),
(57, 'App\\Models\\User', 3, 'auth-token', '1c3b693da57142f2e0ee693cf48be16ebeff43013f1945f43765ccd109ff9413', '[\"*\"]', '2026-09-06 04:15:28', NULL, '2026-09-05 20:04:48', '2026-09-06 04:15:28'),
(58, 'App\\Models\\User', 1, 'auth-token', '398765da0ba38ae053e15c19a9030f34f99a33eff27ee63593d817031e7b5cba', '[\"*\"]', '2026-09-06 10:29:37', NULL, '2026-09-06 10:28:29', '2026-09-06 10:29:37'),
(59, 'App\\Models\\User', 3, 'auth-token', '5711975fdd6ad265c54a9ddab2710f89bb88e7b08006c6d84ff96e3e30ca482e', '[\"*\"]', '2026-09-06 10:52:04', NULL, '2026-09-06 10:30:01', '2026-09-06 10:52:04'),
(60, 'App\\Models\\User', 3, 'auth-token', '177342a4bb5c6ca9632434bddbd18bcaec90021a36aad004e91b120b1577a345', '[\"*\"]', '2026-09-06 10:53:32', NULL, '2026-09-06 10:52:25', '2026-09-06 10:53:32'),
(61, 'App\\Models\\User', 1, 'auth-token', 'd2085391202b7c01f811b380e97e031616d2265ad47a0d851c7e4b0dbac506c8', '[\"*\"]', '2026-09-06 10:56:17', NULL, '2026-09-06 10:53:52', '2026-09-06 10:56:17'),
(62, 'App\\Models\\User', 3, 'auth-token', '6b4b6f04b7e5d28e6f9f22f3ee1583e0244f3463d413c7c8bea412746314031e', '[\"*\"]', '2026-09-06 10:58:42', NULL, '2026-09-06 10:57:36', '2026-09-06 10:58:42'),
(63, 'App\\Models\\User', 1, 'auth-token', '43b67d6ad73d48e2fe251a5a81706f71ec63a9e11ec4e8fbc126d976c39d9fed', '[\"*\"]', '2026-09-06 11:09:23', NULL, '2026-09-06 10:59:15', '2026-09-06 11:09:23'),
(64, 'App\\Models\\User', 1, 'auth-token', 'e3d18bb0afcc313822db76d2425809078fe0209d7bd5e4da122eb1c68aab6a9b', '[\"*\"]', '2026-09-06 12:30:37', NULL, '2026-09-06 12:11:09', '2026-09-06 12:30:37'),
(65, 'App\\Models\\User', 1, 'auth-token', '5a081030b995836205e7c7324ced1e4611088b4f4729b9882975480c7645e4b0', '[\"*\"]', '2026-09-06 13:09:13', NULL, '2026-09-06 12:51:02', '2026-09-06 13:09:13'),
(66, 'App\\Models\\User', 3, 'auth-token', '77d7674de90bd2c116b95af7defafff13f15a3bb115eebac5abd9c7879c760c3', '[\"*\"]', '2026-09-06 13:20:34', NULL, '2026-09-06 13:09:56', '2026-09-06 13:20:34'),
(67, 'App\\Models\\User', 1, 'auth-token', 'd75d3aa33f864007ff9e7925904cb43dd2b187433829874bb4cec9ecbc33e2b1', '[\"*\"]', '2026-09-06 13:21:41', NULL, '2026-09-06 13:21:08', '2026-09-06 13:21:41'),
(68, 'App\\Models\\User', 1, 'auth-token', 'cc1aab9b4273a06fb92fa6b6f2904e26013f2f6e103aadec1291185ccaf8a63d', '[\"*\"]', '2026-09-06 13:29:07', NULL, '2026-09-06 13:25:20', '2026-09-06 13:29:07'),
(69, 'App\\Models\\User', 6, 'auth-token', '00ed3d0269961239c599fb20d97b6f6a0cc22e78385cb37a094ff5d21b3d7d4c', '[\"*\"]', '2026-09-06 13:30:28', NULL, '2026-09-06 13:29:28', '2026-09-06 13:30:28'),
(70, 'App\\Models\\User', 3, 'auth-token', '720ed477f71f1a16d609535b1e14734e11d068212c97c435524dd1dfa4f6f484', '[\"*\"]', '2026-09-06 22:08:49', NULL, '2026-09-06 22:05:38', '2026-09-06 22:08:49'),
(71, 'App\\Models\\User', 3, 'auth-token', '4f210f6f889883996f1d877ca7907ae6015689361fc56895267f2afd1734be2c', '[\"*\"]', '2026-09-07 01:03:26', NULL, '2026-09-07 01:02:46', '2026-09-07 01:03:26'),
(72, 'App\\Models\\User', 3, 'auth-token', 'a47693ef879e51ef0235c1eb6d5d19abb2c85749c4e0598e461087dd23a20531', '[\"*\"]', '2026-09-07 01:30:00', NULL, '2026-09-07 01:06:28', '2026-09-07 01:30:00'),
(73, 'App\\Models\\User', 1, 'auth-token', '82b6921aa66d34cb8512daaf83f8e73a2e9ee8571d620cdeb4243f12f51ee5b9', '[\"*\"]', '2026-09-07 03:11:48', NULL, '2026-09-07 01:33:37', '2026-09-07 03:11:48'),
(74, 'App\\Models\\User', 5, 'auth-token', '3ce62cd5cc9b5246d5175740ccd12d05ff24887544140ad3210adb3a51e14b23', '[\"*\"]', '2026-09-07 01:38:33', NULL, '2026-09-07 01:34:31', '2026-09-07 01:38:33'),
(75, 'App\\Models\\User', 3, 'auth-token', '8953548be6a7894992d70d49a5993cd1c938ab278d0d9a97f24228a069d523f1', '[\"*\"]', '2026-09-07 03:03:44', NULL, '2026-09-07 02:34:30', '2026-09-07 03:03:44'),
(76, 'App\\Models\\User', 3, 'auth-token', '50f6027cab4bff910ca7f8ba28a323b2447b250857d600c71abe4796d1826114', '[\"*\"]', NULL, NULL, '2026-09-07 03:10:35', '2026-09-07 03:10:35'),
(77, 'App\\Models\\User', 3, 'auth-token', 'b15ec2da459758e9c587de3a4195392133f8c2213c15dcbd1732f9f5615d1209', '[\"*\"]', '2026-09-07 03:12:26', NULL, '2026-09-07 03:11:05', '2026-09-07 03:12:26'),
(78, 'App\\Models\\User', 3, 'auth-token', '995e10e29bc6aecf2e80b99322dbf07fcb2508e8d2d9cd5d03a6960ac287d088', '[\"*\"]', '2026-09-07 04:01:32', NULL, '2026-09-07 03:25:24', '2026-09-07 04:01:32'),
(79, 'App\\Models\\User', 1, 'auth-token', '8fbe9535b44ea8c51185c2441e4fdf967f5cb721721bd4b2cfe4f3d20086515b', '[\"*\"]', '2026-09-07 03:31:26', NULL, '2026-09-07 03:26:46', '2026-09-07 03:31:26'),
(80, 'App\\Models\\User', 3, 'auth-token', '611e85ae1f800bbbac1b5a3b141019e5c43e7eeb477d7169e8db0f9ed08f41b3', '[\"*\"]', '2026-09-07 05:17:03', NULL, '2026-09-07 05:09:03', '2026-09-07 05:17:03'),
(81, 'App\\Models\\User', 3, 'auth-token', 'd83f5711f1c8a3a5c01190d812af2958af56c293809d08c605df59f6ad0894ad', '[\"*\"]', '2026-09-07 05:34:04', NULL, '2026-09-07 05:15:38', '2026-09-07 05:34:04'),
(82, 'App\\Models\\User', 1, 'auth-token', 'eff9f425a9b819893853c30f264245bea4cb22bbd8359500970961077fdf9807', '[\"*\"]', '2026-09-07 06:12:43', NULL, '2026-09-07 05:17:30', '2026-09-07 06:12:43'),
(83, 'App\\Models\\User', 3, 'auth-token', '0f918986fba370cdc8cde65fc239582c69d4c97fb5a9796ca073a30fdae92432', '[\"*\"]', '2026-09-07 07:13:46', NULL, '2026-09-07 05:35:34', '2026-09-07 07:13:46'),
(84, 'App\\Models\\User', 3, 'auth-token', 'bce696c9f3a55a30e9edc0dd0a0403f17516f4c557f21e338d93be71088ecdf8', '[\"*\"]', '2026-09-07 06:58:54', NULL, '2026-09-07 06:12:55', '2026-09-07 06:58:54'),
(85, 'App\\Models\\User', 1, 'auth-token', '7ba83344e88fc7eb5ebe4a4578c10626a649f230308fbe9616d0ef03a50206df', '[\"*\"]', '2026-09-07 07:12:39', NULL, '2026-09-07 07:05:25', '2026-09-07 07:12:39'),
(86, 'App\\Models\\User', 3, 'auth-token', '802c9c6f6c6f78ff7e04ccd4958cd95f695b9330c662f5928f9c298e6e6b87ec', '[\"*\"]', '2026-09-07 23:37:16', NULL, '2026-09-07 07:18:10', '2026-09-07 23:37:16'),
(87, 'App\\Models\\User', 1, 'auth-token', '090825b36204e72267524ed31e796990111af03d94b1f52f650ec6be86e931b2', '[\"*\"]', '2026-09-07 23:57:51', NULL, '2026-09-07 23:37:41', '2026-09-07 23:57:51'),
(88, 'App\\Models\\User', 3, 'auth-token', '8a441d9e095a3bd29d1ac83a8b6aaf290cd7f4c84caa22bc7e0ec7ed3a6d83bb', '[\"*\"]', '2026-09-07 23:47:23', NULL, '2026-09-07 23:38:29', '2026-09-07 23:47:23'),
(89, 'App\\Models\\User', 1, 'auth-token', '3de50d3e9fd9d3c82eac2ed441dd00cffd16ca89fc86b61cb113ffb754229115', '[\"*\"]', '2026-09-08 01:09:04', NULL, '2026-09-08 00:45:25', '2026-09-08 01:09:04'),
(90, 'App\\Models\\User', 3, 'auth-token', '203a840aa8533afa2e261ee1ec8ac33387d557f11e9f626816ed1ce41b7ad63a', '[\"*\"]', '2026-09-08 00:50:04', NULL, '2026-09-08 00:47:11', '2026-09-08 00:50:04'),
(91, 'App\\Models\\User', 3, 'auth-token', 'f8446ab70ba67a299970c25459f914a5fb3d1a91c29538d178cfd604115bce1f', '[\"*\"]', NULL, NULL, '2026-09-08 00:47:24', '2026-09-08 00:47:24'),
(92, 'App\\Models\\User', 3, 'auth-token', '2b50f954974362ebef7f2e265043bc2fd1661e339aa1a016ec68d8f034404278', '[\"*\"]', '2026-09-08 00:57:12', NULL, '2026-09-08 00:53:22', '2026-09-08 00:57:12'),
(93, 'App\\Models\\User', 3, 'auth-token', 'dfe4b633f7ce69d9e3f8ede7e3b738543f3a5129e276828fa29c39e7de871e05', '[\"*\"]', '2026-09-08 01:51:48', NULL, '2026-09-08 01:04:20', '2026-09-08 01:51:48'),
(94, 'App\\Models\\User', 1, 'auth-token', '70faea0b35feadf0afc2910fd205362ff8fd49122641a96f5be491278a522488', '[\"*\"]', '2026-09-08 01:36:53', NULL, '2026-09-08 01:36:19', '2026-09-08 01:36:53'),
(95, 'App\\Models\\User', 1, 'auth-token', '1aceda6240b28ebcf033c371cdeb8e1272d19e345fdfe5f21b498326c6a9b5b0', '[\"*\"]', '2026-09-08 02:08:19', NULL, '2026-09-08 01:48:40', '2026-09-08 02:08:19'),
(96, 'App\\Models\\User', 6, 'auth-token', '70d7833915029b2f88ff7d212fe8e494637d02dbbf6291c333374616304084cf', '[\"*\"]', '2026-09-08 01:58:47', NULL, '2026-09-08 01:52:18', '2026-09-08 01:58:47'),
(97, 'App\\Models\\User', 3, 'auth-token', 'cc47586d3a745d7f18901a935525765ba8f008fbe06e7f2bce9c36d7fe6353b4', '[\"*\"]', '2026-09-08 02:00:11', NULL, '2026-09-08 02:00:08', '2026-09-08 02:00:11'),
(98, 'App\\Models\\User', 1, 'auth-token', 'dcee5bef8306d17f300a1a513e5fb444945ca5134cf15968bc04fbb12ccf127e', '[\"*\"]', '2026-09-08 02:17:38', NULL, '2026-09-08 02:13:48', '2026-09-08 02:17:38'),
(99, 'App\\Models\\User', 3, 'auth-token', '095944c85df53b12130fbeedc24eda991c85fce5a2ff7c91305362ebcbea9072', '[\"*\"]', '2026-09-08 12:19:14', NULL, '2026-09-08 02:14:59', '2026-09-08 12:19:14'),
(100, 'App\\Models\\User', 1, 'auth-token', 'a50a550d54f42839245cf5f8bd274eedcebc18850ebba7fafe91ca6cd4bd60c7', '[\"*\"]', '2026-09-08 08:23:05', NULL, '2026-09-08 08:11:02', '2026-09-08 08:23:05'),
(101, 'App\\Models\\User', 1, 'auth-token', '69efd11f0b6525bb49e09c9e6eda56b147c15b2c46728db0eace659ebe31e963', '[\"*\"]', '2026-09-08 12:18:39', NULL, '2026-09-08 12:15:48', '2026-09-08 12:18:39');

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

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('LaQJh6gAibn1bCClGldKZ0XkSUaSKSIbUi1wGLqF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQVJKSFp3MDQzR0JzcUFzUTlKd2FQbzdCbTNQZEM3dVY0YTFjZ0wxaiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788731817),
('OavTW7dqaOMSCflGOlNylnSNj71J94BToFGEvLGE', NULL, '10.150.250.243', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY0p5SnRpUm1WeHR6bXR0QmhOSzFOSnd1U0RLWDNmaFh0Y0d1SzYzNiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHA6Ly8xMC4xNTAuMjUwLjI0Mzo4MDAwIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788732111);

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `driver_id` bigint(20) UNSIGNED NOT NULL,
  `vehicle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `scheduled_departure` datetime NOT NULL,
  `return_scheduled_departure` datetime DEFAULT NULL,
  `status` enum('pending','approved','active','completed','denied') NOT NULL DEFAULT 'pending',
  `total_distance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `driver_id`, `vehicle_id`, `origin`, `destination`, `purpose`, `scheduled_departure`, `return_scheduled_departure`, `status`, `total_distance`, `created_at`, `updated_at`) VALUES
(7, 3, 2, 'CPSU SAN CARLOS', 'Example', 'Example', '2026-09-06 11:40:00', NULL, 'completed', 0.00, '2026-09-05 19:36:18', '2026-09-06 04:13:15'),
(8, 3, 2, 'San Carlos', 'Bacolod', 'Getting Passengers Ched', '2026-09-07 06:00:00', NULL, 'completed', 0.00, '2026-09-06 10:53:26', '2026-09-06 22:07:16'),
(9, 6, 4, 'San CARLOS', 'kabankalan', 'meeting', '2026-09-08 00:00:00', NULL, 'completed', 0.00, '2026-09-06 13:28:00', '2026-09-08 01:52:30'),
(10, 5, 3, 'San Carlos', 'Dumaguete', 'Meeting', '2026-09-07 01:00:00', NULL, 'completed', 0.00, '2026-09-07 01:36:41', '2026-09-07 01:38:25'),
(11, 3, 2, 'San Carlos', 'Hinigaran', 'Getting Ched Officers', '2026-09-07 10:53:00', NULL, 'completed', 0.00, '2026-09-07 02:54:43', '2026-09-07 02:57:36'),
(12, 3, 2, 'San Carlos', 'Bacolod', 'Transferring files', '2026-09-07 11:26:00', NULL, 'denied', 0.00, '2026-09-07 03:27:42', '2026-09-07 03:28:06'),
(13, 3, 2, 'San Carlos', 'Bacolod', 'Meeting', '2026-09-07 11:28:00', NULL, 'completed', 0.00, '2026-09-07 03:29:28', '2026-09-07 03:33:52'),
(14, 3, 2, 'San Carlos', 'Kabangkalan', 'Sending files', '2026-09-07 13:16:00', NULL, 'completed', 0.00, '2026-09-07 05:16:56', '2026-09-07 05:19:53'),
(15, 3, 2, 'qqqqq', 'eeee', 'deddd', '2026-09-07 13:35:00', NULL, 'completed', 0.00, '2026-09-07 05:36:01', '2026-09-07 05:36:43'),
(16, 3, 2, 'Solon', 'raphael', 'meeting', '2026-09-07 13:47:00', NULL, 'completed', 0.00, '2026-09-07 05:47:54', '2026-09-07 06:01:29'),
(17, 3, 2, 'San Carlos', 'Kabangkalan', 'Meeting', '2026-09-07 14:11:00', NULL, 'completed', 0.00, '2026-09-07 06:11:51', '2026-09-07 06:14:54'),
(18, 3, 2, 'Kabangkalan', 'San Carlos', 'Getting Passengers', '2026-09-07 14:20:00', NULL, 'completed', 0.00, '2026-09-07 06:21:16', '2026-09-07 06:22:25'),
(19, 3, 2, 'san c', 'fmdm', 'fmfm', '2026-09-07 14:25:00', NULL, 'completed', 0.00, '2026-09-07 06:25:13', '2026-09-07 06:25:44'),
(20, 3, 2, 'sjsjsj', 'djdjj', 'ekek', '2026-09-07 14:53:00', NULL, 'completed', 0.00, '2026-09-07 06:53:54', '2026-09-07 07:09:03'),
(21, 3, 2, 'San Carlos', 'Kabangkalan', 'Meeting', '2026-09-08 07:45:00', NULL, 'completed', 0.00, '2026-09-07 23:41:07', '2026-09-07 23:46:05'),
(22, 3, 2, 'San Carlos', 'Kabangkalan', 'Meeting', '2026-09-08 09:00:00', NULL, 'completed', 0.00, '2026-09-08 00:54:17', '2026-09-08 00:56:52'),
(23, 3, 2, 'San Carlos', 'LA suerte', 'Getting Passengers', '2026-09-08 10:15:00', NULL, 'completed', 0.00, '2026-09-08 02:16:11', '2026-09-08 02:18:21'),
(24, 3, 2, 'San Carlos', 'Himamaylan', 'Important Meeting', '2026-09-08 16:30:00', NULL, 'completed', 0.00, '2026-09-08 08:14:47', '2026-09-08 08:22:08'),
(25, 3, 2, '11111', '2222', '2222', '2026-09-08 20:15:00', NULL, 'completed', 0.00, '2026-09-08 12:15:26', '2026-09-08 12:18:21');

-- --------------------------------------------------------

--
-- Table structure for table `trip_movements`
--

CREATE TABLE `trip_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `trip_id` bigint(20) UNSIGNED NOT NULL,
  `movement_no` tinyint(3) UNSIGNED NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `scheduled_departure` datetime DEFAULT NULL,
  `actual_departure_at` datetime DEFAULT NULL,
  `actual_arrival_at` datetime DEFAULT NULL,
  `departure_latitude` decimal(10,7) DEFAULT NULL,
  `departure_longitude` decimal(10,7) DEFAULT NULL,
  `arrival_latitude` decimal(10,7) DEFAULT NULL,
  `arrival_longitude` decimal(10,7) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip_movements`
--

INSERT INTO `trip_movements` (`id`, `trip_id`, `movement_no`, `origin`, `destination`, `scheduled_departure`, `actual_departure_at`, `actual_arrival_at`, `departure_latitude`, `departure_longitude`, `arrival_latitude`, `arrival_longitude`, `status`, `created_at`, `updated_at`) VALUES
(15, 7, 1, 'CPSU SAN CARLOS', 'Example', '2026-09-06 11:40:00', '2026-09-06 12:08:54', '2026-09-06 12:13:05', NULL, NULL, NULL, NULL, 'completed', '2026-09-05 19:36:18', '2026-09-06 04:13:05'),
(16, 7, 2, 'Example', 'CPSU SAN CARLOS', NULL, '2026-09-06 12:13:11', '2026-09-06 12:13:15', NULL, NULL, NULL, NULL, 'completed', '2026-09-05 19:36:18', '2026-09-06 04:13:15'),
(17, 8, 1, 'San Carlos', 'Bacolod', '2026-09-07 06:00:00', '2026-09-07 06:06:46', '2026-09-07 06:07:02', NULL, NULL, NULL, NULL, 'completed', '2026-09-06 10:53:26', '2026-09-06 22:07:02'),
(18, 8, 2, 'Bacolod', 'San Carlos', NULL, '2026-09-07 06:07:10', '2026-09-07 06:07:15', NULL, NULL, NULL, NULL, 'completed', '2026-09-06 10:53:26', '2026-09-06 22:07:15'),
(19, 9, 1, 'San CARLOS', 'kabankalan', '2026-09-08 00:00:00', '2026-09-08 09:52:22', '2026-09-08 09:52:26', NULL, NULL, NULL, NULL, 'completed', '2026-09-06 13:28:00', '2026-09-08 01:52:26'),
(20, 9, 2, 'kabankalan', 'San CARLOS', NULL, '2026-09-08 09:52:28', '2026-09-08 09:52:30', NULL, NULL, NULL, NULL, 'completed', '2026-09-06 13:28:00', '2026-09-08 01:52:30'),
(21, 10, 1, 'San Carlos', 'Dumaguete', '2026-09-07 01:00:00', '2026-09-07 09:37:56', '2026-09-07 09:38:14', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 01:36:41', '2026-09-07 01:38:14'),
(22, 10, 2, 'Dumaguete', 'San Carlos', NULL, '2026-09-07 09:38:18', '2026-09-07 09:38:25', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 01:36:41', '2026-09-07 01:38:25'),
(23, 11, 1, 'San Carlos', 'Hinigaran', '2026-09-07 10:53:00', '2026-09-07 10:56:25', '2026-09-07 10:57:26', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 02:54:43', '2026-09-07 02:57:26'),
(24, 11, 2, 'Hinigaran', 'San Carlos', NULL, '2026-09-07 10:57:31', '2026-09-07 10:57:36', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 02:54:43', '2026-09-07 02:57:36'),
(25, 12, 1, 'San Carlos', 'Bacolod', '2026-09-07 11:26:00', NULL, NULL, NULL, NULL, NULL, NULL, 'scheduled', '2026-09-07 03:27:43', '2026-09-07 03:27:43'),
(26, 12, 2, 'Bacolod', 'San Carlos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'scheduled', '2026-09-07 03:27:43', '2026-09-07 03:27:43'),
(27, 13, 1, 'San Carlos', 'Bacolod', '2026-09-07 11:28:00', '2026-09-07 11:32:12', '2026-09-07 11:33:37', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 03:29:28', '2026-09-07 03:33:37'),
(28, 13, 2, 'Bacolod', 'San Carlos', NULL, '2026-09-07 11:33:46', '2026-09-07 11:33:52', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 03:29:28', '2026-09-07 03:33:52'),
(29, 14, 1, 'San Carlos', 'Kabangkalan', '2026-09-07 13:16:00', '2026-09-07 13:19:24', '2026-09-07 13:19:46', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 05:16:56', '2026-09-07 05:19:46'),
(30, 14, 2, 'Kabangkalan', 'San Carlos', NULL, '2026-09-07 13:19:49', '2026-09-07 13:19:53', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 05:16:56', '2026-09-07 05:19:53'),
(31, 15, 1, 'qqqqq', 'eeee', '2026-09-07 13:35:00', '2026-09-07 13:36:27', '2026-09-07 13:36:35', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 05:36:01', '2026-09-07 05:36:35'),
(32, 15, 2, 'eeee', 'qqqqq', NULL, '2026-09-07 13:36:38', '2026-09-07 13:36:43', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 05:36:01', '2026-09-07 05:36:43'),
(33, 16, 1, 'Solon', 'raphael', '2026-09-07 13:47:00', '2026-09-07 14:01:14', '2026-09-07 14:01:21', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 05:47:54', '2026-09-07 06:01:21'),
(34, 16, 2, 'raphael', 'Solon', NULL, '2026-09-07 14:01:26', '2026-09-07 14:01:29', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 05:47:54', '2026-09-07 06:01:29'),
(35, 17, 1, 'San Carlos', 'Kabangkalan', '2026-09-07 14:11:00', '2026-09-07 14:14:05', '2026-09-07 14:14:46', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:11:51', '2026-09-07 06:14:46'),
(36, 17, 2, 'Kabangkalan', 'San Carlos', NULL, '2026-09-07 14:14:50', '2026-09-07 14:14:53', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:11:51', '2026-09-07 06:14:53'),
(37, 18, 1, 'Kabangkalan', 'San Carlos', '2026-09-07 14:20:00', '2026-09-07 14:22:14', '2026-09-07 14:22:19', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:21:16', '2026-09-07 06:22:19'),
(38, 18, 2, 'San Carlos', 'Kabangkalan', NULL, '2026-09-07 14:22:23', '2026-09-07 14:22:25', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:21:16', '2026-09-07 06:22:25'),
(39, 19, 1, 'san c', 'fmdm', '2026-09-07 14:25:00', '2026-09-07 14:25:31', '2026-09-07 14:25:38', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:25:13', '2026-09-07 06:25:38'),
(40, 19, 2, 'fmdm', 'san c', NULL, '2026-09-07 14:25:42', '2026-09-07 14:25:44', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:25:13', '2026-09-07 06:25:44'),
(41, 20, 1, 'sjsjsj', 'djdjj', '2026-09-07 14:53:00', '2026-09-07 15:08:40', '2026-09-07 15:08:57', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:53:54', '2026-09-07 07:08:57'),
(42, 20, 2, 'djdjj', 'sjsjsj', NULL, '2026-09-07 15:09:00', '2026-09-07 15:09:03', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 06:53:54', '2026-09-07 07:09:03'),
(43, 21, 1, 'San Carlos', 'Kabangkalan', '2026-09-08 07:45:00', '2026-09-08 07:44:21', '2026-09-08 07:45:58', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 23:41:08', '2026-09-07 23:45:58'),
(44, 21, 2, 'Kabangkalan', 'San Carlos', NULL, '2026-09-08 07:46:01', '2026-09-08 07:46:05', NULL, NULL, NULL, NULL, 'completed', '2026-09-07 23:41:08', '2026-09-07 23:46:05'),
(45, 22, 1, 'San Carlos', 'Kabangkalan', '2026-09-08 09:00:00', '2026-09-08 08:56:07', '2026-09-08 08:56:42', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 00:54:17', '2026-09-08 00:56:42'),
(46, 22, 2, 'Kabangkalan', 'San Carlos', NULL, '2026-09-08 08:56:50', '2026-09-08 08:56:52', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 00:54:17', '2026-09-08 00:56:52'),
(47, 23, 1, 'San Carlos', 'LA suerte', '2026-09-08 10:15:00', '2026-09-08 10:18:03', '2026-09-08 10:18:15', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 02:16:11', '2026-09-08 02:18:15'),
(48, 23, 2, 'LA suerte', 'San Carlos', NULL, '2026-09-08 10:18:18', '2026-09-08 10:18:21', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 02:16:11', '2026-09-08 02:18:21'),
(49, 24, 1, 'San Carlos', 'Himamaylan', '2026-09-08 16:30:00', '2026-09-08 16:21:52', '2026-09-08 16:22:02', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 08:14:47', '2026-09-08 08:22:02'),
(50, 24, 2, 'Himamaylan', 'San Carlos', NULL, '2026-09-08 16:22:04', '2026-09-08 16:22:08', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 08:14:47', '2026-09-08 08:22:08'),
(51, 25, 1, '11111', '2222', '2026-09-08 20:15:00', '2026-09-08 20:18:02', '2026-09-08 20:18:15', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 12:15:26', '2026-09-08 12:18:15'),
(52, 25, 2, '2222', '11111', NULL, '2026-09-08 20:18:19', '2026-09-08 20:18:21', NULL, NULL, NULL, NULL, 'completed', '2026-09-08 12:15:26', '2026-09-08 12:18:21');

-- --------------------------------------------------------

--
-- Table structure for table `trip_passengers`
--

CREATE TABLE `trip_passengers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `trip_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip_passengers`
--

INSERT INTO `trip_passengers` (`id`, `trip_id`, `name`, `designation`, `created_at`, `updated_at`) VALUES
(4, 7, 'Raffy', 'Admn', '2026-09-05 19:36:18', '2026-09-05 19:36:18'),
(5, 10, 'Raphael Solon', 'Dean Of Information Technology', '2026-09-07 01:36:41', '2026-09-07 01:36:41'),
(6, 11, 'Ken M Balogo', 'Campus Admin', '2026-09-07 02:54:43', '2026-09-07 02:54:43'),
(7, 13, 'Ken M Balogo', 'Campus Coordinator', '2026-09-07 03:29:28', '2026-09-07 03:29:28'),
(8, 15, 'enen', 'rjrj', '2026-09-07 05:36:01', '2026-09-07 05:36:01'),
(9, 16, 'raphael', 'raphael', '2026-09-07 05:47:54', '2026-09-07 05:47:54'),
(10, 17, 'Ken M Balogo', 'Campus Coordinator', '2026-09-07 06:11:51', '2026-09-07 06:11:51'),
(11, 18, 'Example', 'xe', '2026-09-07 06:21:16', '2026-09-07 06:21:16'),
(12, 20, 'fjfj', 'sken', '2026-09-07 06:53:54', '2026-09-07 06:53:54'),
(13, 21, 'Ken M Balogo', 'Campus Administrator', '2026-09-07 23:41:08', '2026-09-07 23:41:08'),
(14, 22, 'Ken M Balogo', 'Campus Administrator', '2026-09-08 00:54:17', '2026-09-08 00:54:17'),
(15, 24, 'Ricard swerte', 'Administrator', '2026-09-08 08:14:47', '2026-09-08 08:14:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','driver') NOT NULL DEFAULT 'driver',
  `contact_number` varchar(255) DEFAULT NULL,
  `license_number` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `contact_number`, `license_number`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@cpsu.edu.ph', NULL, '$2y$12$Iw287Xqt/IDJ9RCao.szMehqgHKjUhbUdoxcLRGMLNMQcRL1dMs/6', 'admin', NULL, NULL, NULL, '2026-09-02 00:57:09', '2026-09-03 00:35:41'),
(3, 'Raphael SOlon', 'raphaelsolon65@gmail.com', NULL, '$2y$12$KDfquMxnyq7cyKkYyGRWDOp0lS3zkduHJIFtOreftoaU1rdOH30/m', 'driver', '09756970684', '4455', NULL, '2026-09-02 04:47:01', '2026-09-02 23:16:31'),
(4, 'Bryan CAballero', 'bryan@gmail.com', NULL, '$2y$12$Wk8l8sCe2oKQJ/QDTU3o0eE4OkNpLYTOn6WnpU4KyRORPNp9cVICy', 'driver', '09634343434', '4453', NULL, '2026-09-02 04:47:44', '2026-09-02 04:47:44'),
(5, 'Joana Undang', 'joanaundang@gmail.com', NULL, '$2y$12$RppySfV5HJT34zFFxkn4XOkLVmiKhcCuMDtERflT.96rcfBTNY7gq', 'driver', '09756970684', '33566', NULL, '2026-09-02 17:56:28', '2026-09-07 01:34:14'),
(6, 'example', 'example@gmail.com', NULL, '$2y$12$61QC5arDymJA748puK.IguDFu6zIuRZH5MZRCoh90ZqQQsjT4B.Bq', 'driver', '0944554596', '11223344', NULL, '2026-09-06 13:26:48', '2026-09-06 13:26:48'),
(7, 'Raffy Solon', 'raffysolon@gmail.com', NULL, '$2y$12$c2GnhF4lc1sxHizzYK6fOO2NCNk6dFVZ4NS2p7rft0uEpqX9axJ/G', 'driver', '09438333737', '225677', NULL, '2026-09-07 07:12:21', '2026-09-07 07:12:21');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `plate_no` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `name`, `plate_no`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Mitsubishi', '1234', 'active', '2026-09-02 04:48:34', '2026-09-02 04:48:34'),
(2, 'Toyota', '9899', 'active', '2026-09-02 04:48:50', '2026-09-02 04:48:50'),
(3, 'Hilux', '22442', 'active', '2026-09-02 17:55:43', '2026-09-02 17:55:43'),
(4, 'Example Vehicle', '0233', 'active', '2026-09-06 13:26:11', '2026-09-06 13:26:11'),
(5, 'Honda Civic', '3445', 'active', '2026-09-07 07:11:43', '2026-09-07 07:11:43');

--
-- Indexes for dumped tables
--

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
-- Indexes for table `campus_coordinator_assignments`
--
ALTER TABLE `campus_coordinator_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campus_coordinator_assignments_driver_id_foreign` (`driver_id`),
  ADD KEY `campus_coordinator_assignments_vehicle_id_foreign` (`vehicle_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

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
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_id_foreign` (`user_id`),
  ADD KEY `notifications_trip_id_foreign` (`trip_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trips_driver_id_foreign` (`driver_id`),
  ADD KEY `trips_vehicle_id_foreign` (`vehicle_id`);

--
-- Indexes for table `trip_movements`
--
ALTER TABLE `trip_movements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trip_movements_trip_id_movement_no_unique` (`trip_id`,`movement_no`);

--
-- Indexes for table `trip_passengers`
--
ALTER TABLE `trip_passengers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_passengers_trip_id_foreign` (`trip_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicles_plate_no_unique` (`plate_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `campus_coordinator_assignments`
--
ALTER TABLE `campus_coordinator_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `trip_movements`
--
ALTER TABLE `trip_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `trip_passengers`
--
ALTER TABLE `trip_passengers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `campus_coordinator_assignments`
--
ALTER TABLE `campus_coordinator_assignments`
  ADD CONSTRAINT `campus_coordinator_assignments_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `campus_coordinator_assignments_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_trip_id_foreign` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `trips_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`);

--
-- Constraints for table `trip_movements`
--
ALTER TABLE `trip_movements`
  ADD CONSTRAINT `trip_movements_trip_id_foreign` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trip_passengers`
--
ALTER TABLE `trip_passengers`
  ADD CONSTRAINT `trip_passengers_trip_id_foreign` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
