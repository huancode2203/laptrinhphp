-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: badminton_hub_20261008
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `details` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `booking_services`
--

DROP TABLE IF EXISTS `booking_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_price` int unsigned NOT NULL,
  `amount` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_services_booking_id_service_id_unique` (`booking_id`,`service_id`),
  KEY `booking_services_service_id_foreign` (`service_id`),
  CONSTRAINT `booking_services_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_services_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `court_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `court_amount` int unsigned NOT NULL,
  `service_amount` int unsigned NOT NULL DEFAULT '0',
  `total_amount` int unsigned NOT NULL,
  `paid_amount` int unsigned NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `checked_in_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_reference_unique` (`reference`),
  UNIQUE KEY `bookings_request_key_unique` (`request_key`),
  KEY `bookings_court_id_status_starts_at_ends_at_index` (`court_id`,`status`,`starts_at`,`ends_at`),
  KEY `bookings_user_id_starts_at_index` (`user_id`,`starts_at`),
  CONSTRAINT `bookings_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amenities` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `courts`
--

DROP TABLE IF EXISTS `courts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `hourly_rate` int unsigned NOT NULL,
  `peak_rate` int unsigned NOT NULL,
  `weekend_rate` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courts_branch_id_name_unique` (`branch_id`,`name`),
  CONSTRAINT `courts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `maintenances`
--

DROP TABLE IF EXISTS `maintenances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `court_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maintenances_court_id_status_starts_at_ends_at_index` (`court_id`,`status`,`starts_at`,`ends_at`),
  CONSTRAINT `maintenances_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `received_by` bigint unsigned NOT NULL,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment',
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_request_key_unique` (`request_key`),
  KEY `payments_booking_id_foreign` (`booking_id`),
  KEY `payments_received_by_foreign` (`received_by`),
  CONSTRAINT `payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_branch_id_name_unique` (`branch_id`,`name`),
  CONSTRAINT `services_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_branch_id_foreign` (`branch_id`),
  KEY `users_role_index` (`role`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-08 17:42:52

-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: badminton_hub_20261008
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_08_080648_create_badminton_tables',1),(5,'2026_10_08_173715_create_notifications_table',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Badminton Hub – Quận 1','Quận 1','18 Nguyễn Bỉnh Khiêm, Q.1',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(2,'Badminton Hub – Quận 3','Quận 3','210 Võ Văn Tần, Q.3',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(3,'Badminton Hub – Bình Thạnh','Bình Thạnh','72 Điện Biên Phủ, Bình Thạnh',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(4,'Badminton Hub – Phú Nhuận','Phú Nhuận','93 Phan Xích Long, Phú Nhuận',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(5,'Badminton Hub – Gò Vấp','Gò Vấp','45 Quang Trung, Gò Vấp',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(6,'Badminton Hub – Tân Bình','Tân Bình','110 Cộng Hòa, Tân Bình',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(7,'Badminton Hub – Thủ Đức','Thủ Đức','52 Võ Văn Ngân, Thủ Đức',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(8,'Badminton Hub – Quận 7','Quận 7','88 Nguyễn Hữu Thọ, Q.7',NULL,'[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-08 08:14:59','2026-10-08 08:14:59');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Quản trị viên','admin@badminton.test',NULL,'$2y$12$6335hNLevZtd4KoowiTnsujKmRHHbIYD1RiJbRnqKdD4uXL0asysS',NULL,'2026-10-08 08:14:59','2026-10-08 08:14:59','admin',NULL,NULL),(2,'Quản lý Bình Thạnh','manager@badminton.test',NULL,'$2y$12$q/SmHkdiNgGMv.AGBDgUQuzyFNFipdlNOYrMnSv3541/EbnFwa9NG',NULL,'2026-10-08 08:14:59','2026-10-08 08:14:59','manager',NULL,3),(3,'Khách hàng mẫu','customer@badminton.test',NULL,'$2y$12$43eWwjnvLIbub1CMiX4Il.1yGwHxLfDtEAJMUS3X8ZDbV/LkaKJcO',NULL,'2026-10-08 08:14:59','2026-10-08 08:14:59','customer',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `courts`
--

LOCK TABLES `courts` WRITE;
/*!40000 ALTER TABLE `courts` DISABLE KEYS */;
INSERT INTO `courts` VALUES (1,1,'Sân 01','active',120000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(2,1,'Sân 02','active',120000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(3,1,'Sân 03','active',120000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(4,1,'Sân 04','active',120000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(5,1,'Sân 05','active',120000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(6,1,'Sân 06','active',120000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(7,2,'Sân 01','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(8,2,'Sân 02','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(9,2,'Sân 03','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(10,2,'Sân 04','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(11,2,'Sân 05','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(12,2,'Sân 06','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(13,2,'Sân 07','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(14,2,'Sân 08','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(15,3,'Sân 01','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(16,3,'Sân 02','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(17,3,'Sân 03','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(18,3,'Sân 04','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(19,3,'Sân 05','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(20,3,'Sân 06','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(21,3,'Sân 07','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(22,3,'Sân 08','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(23,3,'Sân 09','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(24,3,'Sân 10','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(25,3,'Sân 11','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(26,3,'Sân 12','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(27,4,'Sân 01','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(28,4,'Sân 02','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(29,4,'Sân 03','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(30,4,'Sân 04','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(31,4,'Sân 05','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(32,4,'Sân 06','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(33,4,'Sân 07','active',110000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(34,5,'Sân 01','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(35,5,'Sân 02','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(36,5,'Sân 03','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(37,5,'Sân 04','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(38,5,'Sân 05','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(39,5,'Sân 06','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(40,5,'Sân 07','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(41,5,'Sân 08','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(42,5,'Sân 09','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(43,5,'Sân 10','active',95000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(44,6,'Sân 01','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(45,6,'Sân 02','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(46,6,'Sân 03','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(47,6,'Sân 04','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(48,6,'Sân 05','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(49,6,'Sân 06','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(50,6,'Sân 07','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(51,6,'Sân 08','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(52,6,'Sân 09','active',100000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(53,7,'Sân 01','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(54,7,'Sân 02','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(55,7,'Sân 03','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(56,7,'Sân 04','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(57,7,'Sân 05','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(58,7,'Sân 06','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(59,7,'Sân 07','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(60,7,'Sân 08','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(61,7,'Sân 09','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(62,7,'Sân 10','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(63,7,'Sân 11','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(64,7,'Sân 12','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(65,7,'Sân 13','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(66,7,'Sân 14','active',90000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(67,8,'Sân 01','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(68,8,'Sân 02','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(69,8,'Sân 03','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(70,8,'Sân 04','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(71,8,'Sân 05','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(72,8,'Sân 06','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(73,8,'Sân 07','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(74,8,'Sân 08','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(75,8,'Sân 09','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(76,8,'Sân 10','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(77,8,'Sân 11','active',105000,180000,200000,'2026-10-08 08:14:59','2026-10-08 08:14:59');
/*!40000 ALTER TABLE `courts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,1,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(2,1,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(3,1,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(4,1,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(5,1,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(6,1,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(7,1,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(8,1,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(9,2,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(10,2,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(11,2,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(12,2,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(13,2,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(14,2,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(15,2,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(16,2,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(17,3,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(18,3,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(19,3,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(20,3,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(21,3,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(22,3,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(23,3,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(24,3,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(25,4,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(26,4,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(27,4,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(28,4,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(29,4,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(30,4,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(31,4,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(32,4,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(33,5,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(34,5,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(35,5,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(36,5,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(37,5,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(38,5,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(39,5,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(40,5,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(41,6,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(42,6,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(43,6,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(44,6,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(45,6,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(46,6,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(47,6,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(48,6,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(49,7,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(50,7,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(51,7,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(52,7,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(53,7,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(54,7,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(55,7,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(56,7,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(57,8,'Thuê vợt','buổi',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(58,8,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(59,8,'Nước suối','chai',10000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(60,8,'Pocari Sweat','chai',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(61,8,'Thuê giày','buổi',25000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(62,8,'Locker','buổi',15000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(63,8,'Shower','lần',30000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59'),(64,8,'Huấn luyện viên','buổi',200000,1,'2026-10-08 08:14:59','2026-10-08 08:14:59');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-08 17:42:52
