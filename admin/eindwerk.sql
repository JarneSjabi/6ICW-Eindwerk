-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Gegenereerd op: 10 jun 2026 om 13:10
-- Serverversie: 10.4.32-MariaDB
-- PHP-versie: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eindwerk`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(255) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `parameters`
--

CREATE TABLE `parameters` (
  `id` int(11) NOT NULL,
  `digi_builtin` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `data_type` varchar(50) DEFAULT 'string',
  `default_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `parameters`
--

INSERT INTO `parameters` (`id`, `digi_builtin`, `name`, `description`, `label`, `data_type`, `default_value`, `created_at`, `updated_at`) VALUES
(1, 1, 'TasteDateAmount', 'The amount of days before the item reaches the expire date', 'THT of TGT (in dagen)', 'integer', '9', '2025-10-03 19:20:25', '2025-11-08 18:15:07'),
(10, 1, 'LossPercentage', 'The percent of loss expected when preparing / cutting the item for the customer', 'Verliespercentage', 'integer', '0', '2025-11-08 18:05:36', '2025-11-08 18:05:36'),
(11, 1, 'MaxStorageTemp', 'The maximum temperature allowed to store this item', 'Maximale Bewaartemperatuur', 'integer', '18', '2025-11-08 18:06:34', '2025-11-08 18:14:53'),
(12, 1, 'MinStorageTemp', 'The minimum temperature allowed to store this item', 'Minimale Bewaartemperatuur', 'integer', '8', '2025-11-08 18:07:00', '2025-11-08 18:14:50'),
(13, 1, 'SellByDateAmount', 'The amount of days before the item must be sold', 'Uiterste verkoopdatum (in dagen)', 'integer', '7', '2025-11-08 18:09:32', '2025-11-08 18:14:25'),
(14, 1, 'FreezeDateAmount', 'The amount of days the item can remain frozen after the freeze date', 'Invriesduur (in dagen)', 'integer', '7', '2025-11-08 18:11:32', '2025-11-08 18:14:19'),
(15, 1, 'GasFillTimeMap', 'The total time that the item will be filled with gas when using MAP (Modified Atmosphere Packaging)', 'Gasvultijd MAP-verpakking (in seconden)', 'integer', '8', '2025-11-08 18:14:12', '2025-11-08 19:13:19');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `category` varchar(255) NOT NULL,
  `risk_grade` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `risk_grade`, `created_at`, `updated_at`) VALUES
(4, 'view_users', 'Bekijk gebruikers', 'users', 3, '2025-10-28 09:27:00', '2026-02-26 10:42:57'),
(5, 'manage_users', 'Beheer gebruikers', 'users', 5, '2025-10-28 09:27:00', '2026-02-13 16:23:38'),
(6, 'manage_roles', 'Beheer rollen en permissies', 'administration', 5, '2025-10-28 09:27:00', '2026-02-13 16:23:38'),
(7, 'system_settings', 'Beheer systeeminstellingen', 'administration', 5, '2025-10-28 09:27:00', '2025-10-28 15:30:02'),
(8, 'developer_tools', 'Toegang tot Developer Tools', 'development', 5, '2025-10-28 09:27:00', '2025-10-28 15:30:04'),
(9, 'view_reports', 'Bekijk rapporten', 'administration', 1, '2025-10-28 09:27:00', '2025-10-28 15:30:05'),
(10, 'manage_auditlog', 'Beheer wijzigingen en logboeken', 'administration', 3, '2025-10-28 09:27:00', '2025-10-28 15:39:54'),
(11, 'manage_parameters', 'Beheer parameters', 'parameters', 3, '2025-10-28 09:27:00', '2025-10-28 15:30:08'),
(14, 'view_vehicles', 'Bekijk voertuigen', 'vehicles', 1, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(15, 'manage_vehicles', 'Beheer voertuigen', 'vehicles', 3, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(16, 'view_vehicle_templates', 'Bekijk voertuigtemplates', 'vehicles', 1, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(17, 'manage_vehicle_templates', 'Beheer voertuigtemplates', 'vehicles', 3, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(18, 'view_rides', 'Bekijk ritten', 'rides', 1, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(19, 'manage_rides', 'Beheer ritten', 'rides', 3, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(20, 'view_routes', 'Bekijk routes', 'routes', 1, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(21, 'manage_routes', 'Beheer routes', 'routes', 3, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(22, 'view_vehicle_location_reports', 'Bekijk locatierapporten', 'monitoring', 1, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(23, 'view_ride_interrupts', 'Bekijk ritonderbrekingen', 'monitoring', 1, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(24, 'manage_integrations', 'Beheer integraties', 'integrations', 3, '2026-02-13 16:23:38', '2026-02-13 16:23:38'),
(25, 'organization_settings', 'Organisatie-instellingen', 'administration', 3, '2026-02-13 16:23:38', '2026-02-13 16:23:38');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ride_interrupts`
--

CREATE TABLE `ride_interrupts` (
  `id` int(11) NOT NULL,
  `ride_request_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `route_id` int(11) DEFAULT NULL,
  `interrupt_type` enum('traffic_delay','vehicle_issue','passenger_request','emergency','other') NOT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL COMMENT 'How long the interrupt lasted',
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ride_requests`
--

CREATE TABLE `ride_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Customer user ID if registered',
  `customer_name` varchar(255) DEFAULT NULL COMMENT 'For non-registered customers',
  `customer_email` varchar(255) DEFAULT NULL COMMENT 'For non-registered customers',
  `customer_phone` varchar(50) DEFAULT NULL COMMENT 'For non-registered customers',
  `pickup_latitude` decimal(10,8) NOT NULL,
  `pickup_longitude` decimal(11,8) NOT NULL,
  `pickup_address` varchar(500) DEFAULT NULL,
  `dropoff_latitude` decimal(10,8) NOT NULL,
  `dropoff_longitude` decimal(11,8) NOT NULL,
  `dropoff_address` varchar(500) DEFAULT NULL,
  `requested_pickup_time` datetime NOT NULL,
  `passenger_count` int(11) NOT NULL DEFAULT 1,
  `comfort_level` enum('basic','standard','premium') DEFAULT 'standard',
  `shared_ride` tinyint(1) DEFAULT 0 COMMENT 'Whether customer accepts shared ride',
  `estimated_distance_km` decimal(10,2) DEFAULT NULL,
  `estimated_duration_minutes` int(11) DEFAULT NULL,
  `estimated_price_cents` int(11) DEFAULT NULL,
  `status` enum('pending','assigned','in_progress','completed','cancelled') DEFAULT 'pending',
  `vehicle_id` int(11) DEFAULT NULL COMMENT 'Assigned vehicle',
  `route_id` int(11) DEFAULT NULL COMMENT 'Assigned route',
  `actual_pickup_time` datetime DEFAULT NULL,
  `actual_dropoff_time` datetime DEFAULT NULL,
  `actual_distance_km` decimal(10,2) DEFAULT NULL,
  `actual_duration_minutes` int(11) DEFAULT NULL,
  `actual_price_cents` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL COMMENT 'Optional route name',
  `start_latitude` decimal(10,8) NOT NULL,
  `start_longitude` decimal(11,8) NOT NULL,
  `end_latitude` decimal(10,8) NOT NULL,
  `end_longitude` decimal(11,8) NOT NULL,
  `waypoints` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of waypoints' CHECK (json_valid(`waypoints`)),
  `distance_km` decimal(10,2) DEFAULT NULL,
  `estimated_duration_minutes` int(11) DEFAULT NULL,
  `traffic_factor` decimal(3,2) DEFAULT 1.00 COMMENT 'Traffic multiplier (1.0 = normal, 1.5 = 50% slower)',
  `status` enum('planned','active','completed','cancelled') DEFAULT 'planned',
  `ride_id` int(11) NOT NULL,
  `scheduled_start_time` datetime NOT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','float','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value`, `type`, `description`, `category`, `updated_at`) VALUES
(1, 'digisync_enabled', '1', 'boolean', NULL, 'general', '2025-12-26 13:37:02'),
(2, 'digisync_respect_business_hours', '0', 'boolean', NULL, 'general', '2025-12-26 13:37:02'),
(3, 'digisync_business_hour_start', '8', 'string', NULL, 'general', '2025-12-26 13:37:02'),
(4, 'digisync_business_hour_end', '18', 'string', NULL, 'general', '2025-12-26 13:37:02'),
(5, 'digisync_max_jobs_per_run', '50', 'string', NULL, 'general', '2025-12-26 13:37:02'),
(6, 'digisync_stuck_job_timeout_minutes', '5', 'string', NULL, 'general', '2025-12-26 13:37:02'),
(7, 'digisync_api_debug', '1', 'boolean', NULL, 'general', '2025-12-26 13:37:02'),
(8, 'backend_service_url', 'http://10.10.1.38:5000', 'string', NULL, 'general', '2026-03-02 13:04:15');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `remember_token` varchar(255) NOT NULL,
  `user_group_id` int(11) NOT NULL DEFAULT 1,
  `is_root_user` int(1) NOT NULL DEFAULT 0,
  `last_login` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `password_hash`, `remember_token`, `user_group_id`, `is_root_user`, `last_login`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Admin', 'admin@admin.net', '$2a$12$ASQ.C.XpN0p/p8StiaxFSORRf8.OmLeaoAahvkbFLpiBDpBUsBl8e', '984f13f4564e55862a37c32199ac4df95c8ac481d9868520300febebb87255b6', 5, 1, '2026-06-09 12:36:25', 1, '2025-10-27 12:16:44', '2026-06-10 11:09:53');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `user_groups`
--

CREATE TABLE `user_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(2500) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `user_groups`
--

INSERT INTO `user_groups` (`id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '', 'Geen toegang tot beheerdershulpmiddelen.', 1, '2025-10-27 12:16:44', '2026-04-04 12:55:11'),
(2, 'Medewerker', 'Gebruikersgroep voor medewerkers', 1, '2025-10-27 12:16:44', '2026-02-23 12:57:05'),
(4, 'Manager', 'Volledige beheerderstoegang: rechten om alle zakelijke acties te verrichten', 1, '2025-10-27 12:16:44', '2025-11-08 17:58:09'),
(5, 'Systeembeheerder', 'Gebruikersgroep waarbij rechten verleend worden met betrekking tot het onderhoud, opvolging en ontwikkelen van interne tools.', 1, '2025-10-27 12:16:44', '2025-10-28 16:37:30');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `user_group_permissions`
--

CREATE TABLE `user_group_permissions` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `user_group_permissions`
--

INSERT INTO `user_group_permissions` (`id`, `group_id`, `permission_id`, `value`, `created_at`, `updated_at`) VALUES
(421, 5, 14, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(422, 5, 15, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(423, 5, 16, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(424, 5, 17, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(425, 5, 18, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(426, 5, 19, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(427, 5, 20, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(428, 5, 21, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(429, 5, 22, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(430, 5, 23, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(431, 5, 24, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(432, 5, 25, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(433, 5, 4, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(434, 5, 5, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(435, 5, 6, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(436, 5, 7, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(437, 5, 8, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(438, 5, 9, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(439, 5, 10, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(440, 5, 11, 1, '2026-02-26 10:45:49', '2026-02-26 10:45:49'),
(441, 4, 14, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(442, 4, 15, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(443, 4, 16, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(444, 4, 17, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(445, 4, 18, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(446, 4, 19, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(447, 4, 20, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(448, 4, 21, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(449, 4, 22, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(450, 4, 23, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(451, 4, 24, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(452, 4, 25, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(453, 4, 4, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(454, 4, 5, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(455, 4, 6, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(456, 4, 7, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(457, 4, 9, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(458, 4, 10, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(459, 4, 11, 1, '2026-02-26 10:45:58', '2026-02-26 10:45:58'),
(460, 2, 14, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(461, 2, 15, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(462, 2, 16, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(463, 2, 17, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(464, 2, 18, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(465, 2, 19, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(466, 2, 20, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(467, 2, 21, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(468, 2, 22, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(469, 2, 23, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(470, 2, 4, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(471, 2, 9, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26'),
(472, 2, 11, 1, '2026-02-26 10:46:26', '2026-02-26 10:46:26');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `vehicle_template_id` int(11) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `vin` varchar(50) DEFAULT NULL,
  `status` enum('available','in_use','maintenance','out_of_service') DEFAULT 'available',
  `battery_level` int(11) DEFAULT 100 COMMENT 'Percentage 0-100',
  `odometer_km` int(11) DEFAULT 0,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_km` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_template_id`, `license_plate`, `vin`, `status`, `battery_level`, `odometer_km`, `last_maintenance_date`, `next_maintenance_km`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'ABC-123', 'VIN001', 'available', 41, 15000, NULL, NULL, 1, '2026-02-13 16:27:10', '2026-06-09 19:08:53'),
(2, 1, 'ABC-124', 'VIN002', 'available', 100, 12000, NULL, NULL, 1, '2026-02-13 16:27:10', '2026-06-09 16:54:37'),
(3, 2, 'XYZ-789', 'VIN003', 'available', 100, 20000, NULL, NULL, 1, '2026-02-13 16:27:10', '2026-06-09 16:48:13'),
(4, 3, 'DEF-456', 'VIN004', 'available', 100, 8000, NULL, NULL, 1, '2026-02-13 16:27:10', '2026-06-09 16:48:14'),
(5, 4, 'GHI-789', 'VIN005', 'available', 100, 25000, NULL, NULL, 1, '2026-02-13 16:27:10', '2026-06-09 16:48:15');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vehicle_location_reports`
--

CREATE TABLE `vehicle_location_reports` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed_kmh` decimal(5,2) DEFAULT NULL,
  `heading` decimal(5,2) DEFAULT NULL COMMENT 'Direction in degrees',
  `route_id` int(11) DEFAULT NULL COMMENT 'Current route if any',
  `ride_request_id` int(11) DEFAULT NULL COMMENT 'Current ride request if any',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `vehicle_location_reports`
--

INSERT INTO `vehicle_location_reports` (`id`, `vehicle_id`, `latitude`, `longitude`, `speed_kmh`, `heading`, `route_id`, `ride_request_id`, `reported_at`) VALUES
(1, 1, 51.07561030, 4.27416360, NULL, NULL, NULL, NULL, '2026-06-10 11:10:24');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vehicle_templates`
--

CREATE TABLE `vehicle_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand` text DEFAULT NULL,
  `model` text NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 4 COMMENT 'Number of passengers',
  `max_range_km` int(11) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of features' CHECK (json_valid(`features`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `description` text DEFAULT NULL,
  `battery_capacity_kwh` decimal(10,2) DEFAULT 100.00,
  `consumption_kwh_per_km` decimal(5,3) DEFAULT 0.200,
  `charging_time_0_to_100_min` int(11) DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Gegevens worden geëxporteerd voor tabel `vehicle_templates`
--

INSERT INTO `vehicle_templates` (`id`, `name`, `brand`, `model`, `capacity`, `max_range_km`, `features`, `is_active`, `created_at`, `updated_at`, `description`, `battery_capacity_kwh`, `consumption_kwh_per_km`, `charging_time_0_to_100_min`) VALUES
(1, 'Standard Sedan', 'Audi', 'A6 e-tron', 5, 756, '[\"climate_control\", \"wifi\", \"usb_charging\"]', 1, '2026-02-13 16:27:10', '2026-05-04 11:42:37', NULL, 75.00, 0.180, 45),
(2, 'Premium SUV', 'BYD', 'BYD Seal U DM-i', 5, 671, '[\"climate_control\", \"wifi\", \"usb_charging\", \"leather_seats\", \"premium_sound\"]', 1, '2026-02-13 16:27:10', '2026-05-04 11:46:07', NULL, 100.00, 0.210, 30),
(3, 'Compacte Hatchback', 'BYD', 'Dolphin', 4, 427, '[\"basic_comfort\"]', 1, '2026-02-13 16:27:10', '2026-05-04 11:46:27', NULL, 100.00, 0.175, 30),
(4, 'Luxe SUV', 'Audi', 'Q8 e-tron', 5, 582, '[\"climate_control\", \"wifi\", \"usb_charging\", \"leather_seats\", \"premium_sound\", \"extra_legroom\"]', 1, '2026-02-13 16:27:10', '2026-05-04 11:46:50', NULL, 100.00, 0.280, 30);

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `parameters`
--
ALTER TABLE `parameters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexen voor tabel `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexen voor tabel `ride_interrupts`
--
ALTER TABLE `ride_interrupts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ride_request_id` (`ride_request_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `resolved` (`resolved`);

--
-- Indexen voor tabel `ride_requests`
--
ALTER TABLE `ride_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `status` (`status`),
  ADD KEY `requested_pickup_time` (`requested_pickup_time`);

--
-- Indexen voor tabel `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `status` (`status`),
  ADD KEY `scheduled_start_time` (`scheduled_start_time`);

--
-- Indexen voor tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key_name`),
  ADD KEY `category` (`category`);

--
-- Indexen voor tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`);

--
-- Indexen voor tabel `user_groups`
--
ALTER TABLE `user_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `user_group_permissions`
--
ALTER TABLE `user_group_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexen voor tabel `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `vehicle_template_id` (`vehicle_template_id`),
  ADD KEY `status` (`status`);

--
-- Indexen voor tabel `vehicle_location_reports`
--
ALTER TABLE `vehicle_location_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `route_id` (`route_id`),
  ADD KEY `ride_request_id` (`ride_request_id`),
  ADD KEY `reported_at` (`reported_at`);

--
-- Indexen voor tabel `vehicle_templates`
--
ALTER TABLE `vehicle_templates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `parameters`
--
ALTER TABLE `parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT voor een tabel `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT voor een tabel `ride_interrupts`
--
ALTER TABLE `ride_interrupts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `ride_requests`
--
ALTER TABLE `ride_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT voor een tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT voor een tabel `user_groups`
--
ALTER TABLE `user_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT voor een tabel `user_group_permissions`
--
ALTER TABLE `user_group_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=473;

--
-- AUTO_INCREMENT voor een tabel `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT voor een tabel `vehicle_location_reports`
--
ALTER TABLE `vehicle_location_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `vehicle_templates`
--
ALTER TABLE `vehicle_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `ride_interrupts`
--
ALTER TABLE `ride_interrupts`
  ADD CONSTRAINT `ride_interrupts_ibfk_1` FOREIGN KEY (`ride_request_id`) REFERENCES `ride_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ride_interrupts_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ride_interrupts_ibfk_3` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE SET NULL;

--
-- Beperkingen voor tabel `ride_requests`
--
ALTER TABLE `ride_requests`
  ADD CONSTRAINT `ride_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ride_requests_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ride_requests_ibfk_3` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE SET NULL;

--
-- Beperkingen voor tabel `routes`
--
ALTER TABLE `routes`
  ADD CONSTRAINT `routes_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`);

--
-- Beperkingen voor tabel `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`vehicle_template_id`) REFERENCES `vehicle_templates` (`id`);

--
-- Beperkingen voor tabel `vehicle_location_reports`
--
ALTER TABLE `vehicle_location_reports`
  ADD CONSTRAINT `vehicle_location_reports_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicle_location_reports_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vehicle_location_reports_ibfk_3` FOREIGN KEY (`ride_request_id`) REFERENCES `ride_requests` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
