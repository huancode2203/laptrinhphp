
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
DROP TABLE IF EXISTS `ai_search_turns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_search_turns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_search_turns_request_key_unique` (`request_key`),
  KEY `ai_search_turns_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_search_turns_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_search_turns` WRITE;
/*!40000 ALTER TABLE `ai_search_turns` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_search_turns` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reset_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_codes_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `auth_codes` WRITE;
/*!40000 ALTER TABLE `auth_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `auth_codes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `blocked_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blocked_users` (
  `user_id` bigint unsigned NOT NULL,
  `blocked_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`blocked_id`),
  KEY `blocked_users_blocked_id_foreign` (`blocked_id`),
  CONSTRAINT `blocked_users_blocked_id_foreign` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blocked_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `blocked_users` WRITE;
/*!40000 ALTER TABLE `blocked_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `blocked_users` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `booking_services` WRITE;
/*!40000 ALTER TABLE `booking_services` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_services` ENABLE KEYS */;
UNLOCK TABLES;
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
  `coupon_id` bigint unsigned DEFAULT NULL,
  `discount_amount` int unsigned NOT NULL DEFAULT '0',
  `booking_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `series_key` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `deposit_amount` int NOT NULL DEFAULT '0',
  `payment_expires_at` timestamp NULL DEFAULT NULL,
  `reminded_at` timestamp NULL DEFAULT NULL,
  `product_amount` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_reference_unique` (`reference`),
  UNIQUE KEY `bookings_request_key_unique` (`request_key`),
  KEY `bookings_court_id_status_starts_at_ends_at_index` (`court_id`,`status`,`starts_at`,`ends_at`),
  KEY `bookings_user_id_starts_at_index` (`user_id`,`starts_at`),
  KEY `bookings_coupon_id_foreign` (`coupon_id`),
  KEY `bookings_series_key_index` (`series_key`),
  KEY `bookings_payment_expires_at_index` (`payment_expires_at`),
  CONSTRAINT `bookings_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bookings_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,'DEMO-BH-001','a4d98a7b-c620-5f38-a06e-9d6e34dabbd6',5,1,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(2,'DEMO-BH-002','4f13ec90-30d3-550b-add5-59f5675a3821',7,8,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(3,'DEMO-BH-003','26212560-08bc-559e-819d-10ce8e21f5e5',8,17,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(4,'DEMO-BH-004','117392e6-0663-5325-9a94-f6fee07893dc',9,27,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(5,'DEMO-BH-005','fc8c0f59-4fc9-5d4b-8be4-a523bb191665',10,35,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(6,'DEMO-BH-006','0bdb4557-74f2-5d28-8783-666790aace9e',5,46,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(7,'DEMO-BH-007','bf6795bc-c65a-5216-91a8-cbc82257b67e',12,53,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(8,'DEMO-BH-008','2f15e991-474d-5261-9801-822a3090a108',13,68,'2026-10-08 17:00:00','2026-10-08 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-08 17:00:00','2026-10-07 10:00:00','2026-10-07 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(9,'DEMO-BH-009','e7b0ef06-9366-5483-b238-cde70647d28d',14,3,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(10,'DEMO-BH-010','52dc8813-a3ce-5da1-a8d0-8aea226e64db',15,7,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(11,'DEMO-BH-011','150ab9e4-2bf0-5f73-a00a-b1c621820935',5,16,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(12,'DEMO-BH-012','2eafa53a-127b-58de-8de2-7022d8151268',17,29,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(13,'DEMO-BH-013','0751deec-07f1-5b74-a65a-d1848ecf02d6',18,34,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(14,'DEMO-BH-014','021e04ac-8c35-516a-98ff-89d72efe4840',19,45,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(15,'DEMO-BH-015','775def4c-5f84-59ba-a50a-9e4e073f1053',20,55,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(16,'DEMO-BH-016','8f9b063b-a6f0-5746-8c90-d3f8736d8f5e',5,67,'2026-10-07 17:00:00','2026-10-07 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-07 17:00:00','2026-10-06 10:00:00','2026-10-06 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(17,'DEMO-BH-017','2eb1faef-4061-5823-b7e9-4aa6c652ab4a',22,2,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(18,'DEMO-BH-018','2634be3f-c72d-5bec-9e43-6744846383a3',23,9,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(19,'DEMO-BH-019','382a76cf-5acc-5c9b-a292-563b8e686246',24,15,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(20,'DEMO-BH-020','d5d16d2a-83eb-584c-8fcf-a5e678bbe992',25,28,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(21,'DEMO-BH-021','3867f6aa-0242-5720-be4c-d9cdb5c0d5ba',5,36,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(22,'DEMO-BH-022','78141b42-1548-5e3e-9a70-1004726af3fe',7,44,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(23,'DEMO-BH-023','cf6ea862-c54d-5e34-8ba5-7d0ec8a7877a',8,54,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(24,'DEMO-BH-024','e5abc04d-ada8-5e64-9520-727edace86e1',9,69,'2026-10-06 17:00:00','2026-10-06 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-06 17:00:00','2026-10-05 10:00:00','2026-10-05 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(25,'DEMO-BH-025','58f117af-984c-589c-aa73-545783229003',10,1,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(26,'DEMO-BH-026','076caba7-0f85-5815-9dd2-82b77129eed0',5,8,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(27,'DEMO-BH-027','4054d743-eff7-51c8-9d19-ebb6e26bac0d',12,17,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(28,'DEMO-BH-028','b8202828-520d-54c5-8c36-1ead52c568bc',13,27,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(29,'DEMO-BH-029','bccec485-b264-523f-8328-7676913a97ee',14,35,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(30,'DEMO-BH-030','0f2767c8-927e-5a33-96ea-f8dcbd646d56',15,46,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(31,'DEMO-BH-031','24a0e06a-7f6e-5837-a763-54a65185d488',5,53,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(32,'DEMO-BH-032','fec45013-2f43-52f4-aec2-cf2c48e40e2e',17,68,'2026-10-05 17:00:00','2026-10-05 19:00:00','completed',360000,0,360000,360000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-05 17:00:00','2026-10-04 10:00:00','2026-10-04 10:00:00',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(33,'DEMO-BH-033','9753a428-ec3c-5638-9fba-c50372c99e70',18,3,'2026-10-04 17:00:00','2026-10-04 19:00:00','completed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-04 17:00:00','2026-10-03 10:00:00','2026-10-03 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(34,'DEMO-BH-034','db1904df-6e68-5fd0-bc74-17d94eda88cf',19,7,'2026-10-04 17:00:00','2026-10-04 19:00:00','completed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-04 17:00:00','2026-10-03 10:00:00','2026-10-03 10:00:00',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(35,'DEMO-BH-035','f837d32b-268b-575b-91cc-0688f97e6e5d',20,16,'2026-10-04 17:00:00','2026-10-04 19:00:00','completed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-04 17:00:00','2026-10-03 10:00:00','2026-10-03 10:00:00',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(36,'DEMO-BH-036','64cd571a-1886-548c-9ce9-6d63e4063316',5,29,'2026-10-04 17:00:00','2026-10-04 19:00:00','completed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống','2026-10-04 17:00:00','2026-10-03 10:00:00','2026-10-03 10:00:00',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(37,'DEMO-BH-037','ea471b30-8986-565f-9637-bc6b62088c53',22,34,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(38,'DEMO-BH-038','a0b83da1-d832-54fa-a597-d3dabc5f6999',23,45,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(39,'DEMO-BH-039','4572f136-8dd7-581e-a527-c60ca50bcd5a',24,55,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(40,'DEMO-BH-040','70bbd42a-53b3-5e71-a871-44121e02ba65',25,67,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(41,'DEMO-BH-041','491b86ab-c29c-5ca6-a877-cff547725038',5,2,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(42,'DEMO-BH-042','323fd2d9-26e3-5454-907e-b25a0056cf9b',7,9,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(43,'DEMO-BH-043','05c37c17-f154-566b-ae25-f7f388bb4efc',8,15,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(44,'DEMO-BH-044','b4855cb5-813f-5af2-ba3b-f0bd29b2bff6',9,28,'2026-10-10 18:00:00','2026-10-10 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(45,'DEMO-BH-045','5aad4d4d-d96f-576d-8c91-2429802ea00f',10,36,'2026-10-11 18:00:00','2026-10-11 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(46,'DEMO-BH-046','9359f921-e0de-5131-b54f-e895c54d0b94',5,44,'2026-10-11 18:00:00','2026-10-11 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(47,'DEMO-BH-047','99b08fc3-1bf5-5281-b2ce-f5b6f0b6d67a',12,54,'2026-10-11 18:00:00','2026-10-11 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(48,'DEMO-BH-048','4dc791e0-a1cc-5c1f-acf4-be286a2b0844',13,69,'2026-10-11 18:00:00','2026-10-11 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(49,'DEMO-BH-049','2f2bd1da-b8a8-591f-93f5-7cb423e7b9ae',14,1,'2026-10-11 18:00:00','2026-10-11 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(50,'DEMO-BH-050','b79555a3-fa60-5005-be94-446204462269',15,8,'2026-10-11 18:00:00','2026-10-11 20:00:00','confirmed',400000,0,400000,400000,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(51,'DEMO-BH-051','d84965f7-067d-5438-8198-28b71cc518dc',5,17,'2026-10-11 18:00:00','2026-10-11 20:00:00','cancelled',400000,0,400000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(52,'DEMO-BH-052','c92be493-6287-595d-a629-c2bc14dfafad',17,27,'2026-10-11 18:00:00','2026-10-11 20:00:00','cancelled',400000,0,400000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',200000,NULL,NULL,0),(53,'DEMO-BH-053','4a6c718a-3da3-5711-9495-c6aef6098bd3',18,35,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(54,'DEMO-BH-054','88f3c1e3-b87b-5b5f-ba3e-c19ec75fb096',19,46,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(55,'DEMO-BH-055','89c68bab-6222-5fc0-a508-bacc47f65bd0',20,53,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(56,'DEMO-BH-056','857bd456-a2e8-5249-bea1-75328a6a5c33',5,68,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(57,'DEMO-BH-057','059db70e-ebe8-5734-bb16-d210e3ca911e',22,3,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(58,'DEMO-BH-058','b02a0096-93f6-541e-8947-c4689f5f1b9c',23,7,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0),(59,'DEMO-BH-059','517f9120-0994-537b-b8c0-9457fd395e58',24,16,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'counter',0,NULL,NULL,0),(60,'DEMO-BH-060','0638b209-04da-5e79-bdb1-cb4214b46d99',25,29,'2026-10-12 18:00:00','2026-10-12 20:00:00','cancelled',360000,0,360000,0,'Dữ liệu mẫu để trải nghiệm hệ thống',NULL,'2026-10-09 12:31:56','2026-10-09 12:31:56',NULL,0,'single',NULL,NULL,'online',180000,NULL,NULL,0);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;
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
  `opens_minute` smallint unsigned NOT NULL DEFAULT '180',
  `closes_minute` smallint unsigned NOT NULL DEFAULT '1380',
  `peak_minute` smallint unsigned NOT NULL DEFAULT '960',
  `booking_days` smallint unsigned NOT NULL DEFAULT '90',
  `deposit_percent` tinyint unsigned NOT NULL DEFAULT '50',
  `payment_minutes` smallint unsigned NOT NULL DEFAULT '30',
  `fixed_discount` tinyint unsigned NOT NULL DEFAULT '15',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Badminton Hub – Quận 1','Quận 1','18 Nguyễn Bỉnh Khiêm, Q.1','028 3822 1108','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(2,'Badminton Hub – Quận 3','Quận 3','210 Võ Văn Tần, Q.3','028 3930 8847','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(3,'Badminton Hub – Bình Thạnh','Bình Thạnh','72 Điện Biên Phủ, Bình Thạnh','028 3514 2233','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(4,'Badminton Hub – Phú Nhuận','Phú Nhuận','93 Phan Xích Long, Phú Nhuận','028 3845 7762','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(5,'Badminton Hub – Gò Vấp','Gò Vấp','45 Quang Trung, Gò Vấp','028 3894 5521','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(6,'Badminton Hub – Tân Bình','Tân Bình','110 Cộng Hòa, Tân Bình','028 3812 3340','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(7,'Badminton Hub – Thủ Đức','Thủ Đức','52 Võ Văn Ngân, Thủ Đức','028 3720 8811','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL),(8,'Badminton Hub – Quận 7','Quận 7','88 Nguyễn Hữu Thọ, Q.7','028 5416 9203','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 13:31:54','2026-10-09 13:31:54',180,1380,960,90,50,30,15,NULL);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `attachments` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_messages_request_key_unique` (`request_key`),
  KEY `chat_messages_user_id_foreign` (`user_id`),
  KEY `chat_messages_conversation_id_id_index` (`conversation_id`,`id`),
  CONSTRAINT `chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `coach_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coach_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coach_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `amount` int NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coach_sessions_booking_id_unique` (`booking_id`),
  KEY `coach_sessions_coach_id_foreign` (`coach_id`),
  KEY `coach_sessions_user_id_foreign` (`user_id`),
  CONSTRAINT `coach_sessions_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `coach_sessions_coach_id_foreign` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`),
  CONSTRAINT `coach_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coach_sessions` WRITE;
/*!40000 ALTER TABLE `coach_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `coach_sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `coaches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coaches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `hourly_rate` int unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coaches_branch_id_foreign` (`branch_id`),
  CONSTRAINT `coaches_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coaches` WRITE;
/*!40000 ALTER TABLE `coaches` DISABLE KEYS */;
INSERT INTO `coaches` VALUES (1,1,'Nguyễn Thành Long','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',200000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,2,'Trần Hoàng Phúc','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',210000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,3,'Lê Minh Huy','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',220000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,4,'Phạm Ngọc Mai','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',230000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,5,'Võ Quốc Đạt','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',240000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,6,'Huỳnh Bảo Trân','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',250000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,7,'Đặng Anh Tuấn','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',260000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,8,'Bùi Minh Tâm','Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.',270000,1,'2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `coaches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `commerce_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commerce_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `subject_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commerce_payments_branch_id_foreign` (`branch_id`),
  KEY `commerce_payments_user_id_foreign` (`user_id`),
  KEY `commerce_payments_actor_id_foreign` (`actor_id`),
  KEY `commerce_payments_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  CONSTRAINT `commerce_payments_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commerce_payments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `commerce_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `commerce_payments` WRITE;
/*!40000 ALTER TABLE `commerce_payments` DISABLE KEYS */;
INSERT INTO `commerce_payments` VALUES (1,1,5,1,'event_registration',1,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,1,6,1,'event_registration',2,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,2,5,1,'event_registration',3,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,2,7,1,'event_registration',4,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,3,5,1,'event_registration',5,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,3,8,1,'event_registration',6,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,4,5,1,'event_registration',7,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,4,9,1,'event_registration',8,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(9,5,5,1,'event_registration',9,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(10,5,10,1,'event_registration',10,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(11,6,5,1,'event_registration',11,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(12,6,11,1,'event_registration',12,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(13,7,5,1,'event_registration',13,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(14,7,12,1,'event_registration',14,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(15,8,5,1,'event_registration',15,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(16,8,13,1,'event_registration',16,'payment','cash',50000,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(17,1,5,1,'membership',1,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(18,2,6,1,'membership',2,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(19,3,7,1,'membership',3,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(20,4,8,1,'membership',4,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(21,5,9,1,'membership',5,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(22,6,10,1,'membership',6,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(23,7,11,1,'membership',7,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(24,8,12,1,'membership',8,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(25,1,13,1,'membership',9,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56'),(26,2,14,1,'membership',10,'payment','cash',199000,'2026-10-07 13:31:56','2026-10-07 13:31:56');
/*!40000 ALTER TABLE `commerce_payments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `community_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skill_level` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` datetime NOT NULL,
  `capacity` smallint unsigned NOT NULL DEFAULT '4',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `moderation_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `moderation_result` json DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `moderated_by` bigint unsigned DEFAULT NULL,
  `images` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `community_posts_user_id_foreign` (`user_id`),
  KEY `community_posts_branch_id_foreign` (`branch_id`),
  KEY `community_posts_status_starts_at_index` (`status`,`starts_at`),
  KEY `community_posts_moderated_by_foreign` (`moderated_by`),
  KEY `community_posts_moderation_status_index` (`moderation_status`),
  CONSTRAINT `community_posts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `community_posts_moderated_by_foreign` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `community_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `community_posts` WRITE;
/*!40000 ALTER TABLE `community_posts` DISABLE KEYS */;
INSERT INTO `community_posts` VALUES (1,5,1,'Tìm thêm bạn đánh đôi · Quận 1','Nhóm mình cần thêm hai bạn, trình độ sơ cấp đến trung cấp. Chơi vui, cùng chia tiền sân nhé!','Tìm người đánh','Sơ cấp','2026-10-11 18:00:00',6,'open','2026-10-09 13:31:56','2026-10-09 13:31:56','approved',NULL,NULL,NULL,NULL),(2,7,2,'Giao lưu cầu lông cuối tuần · Quận 3','Mời mọi người tham gia giao lưu. Mang theo vợt và nước uống, đến sớm 10 phút để khởi động.','Giao lưu','Trung cấp','2026-10-12 18:00:00',6,'open','2026-10-09 12:31:56','2026-10-09 12:31:56','approved',NULL,NULL,NULL,NULL),(3,8,3,'Nhóm cầu lông sau giờ làm · Bình Thạnh','Đội chơi đều mỗi tuần, chào đón bạn mới và ưu tiên tinh thần thể thao.','Tuyển thành viên','Trung cấp','2026-10-13 18:00:00',6,'open','2026-10-09 11:31:56','2026-10-09 11:31:56','approved',NULL,NULL,NULL,NULL),(4,9,4,'Chia sẻ cách khởi động trước khi lên sân · Phú Nhuận','Mình dành 10 phút xoay khớp, chạy nhẹ và tập bước di chuyển trước buổi chơi. Mọi người có mẹo nào khác không?','Chia sẻ','Sơ cấp','2026-10-14 18:00:00',6,'open','2026-10-09 10:31:56','2026-10-09 10:31:56','approved',NULL,NULL,NULL,NULL),(5,10,5,'Tìm thêm bạn đánh đôi · Gò Vấp','Nhóm mình cần thêm hai bạn, trình độ sơ cấp đến trung cấp. Chơi vui, cùng chia tiền sân nhé!','Tìm người đánh','Trung cấp','2026-10-15 18:00:00',6,'open','2026-10-09 09:31:56','2026-10-09 09:31:56','approved',NULL,NULL,NULL,NULL),(6,5,6,'Giao lưu cầu lông cuối tuần · Tân Bình','Mời mọi người tham gia giao lưu. Mang theo vợt và nước uống, đến sớm 10 phút để khởi động.','Giao lưu','Trung cấp','2026-10-16 18:00:00',6,'open','2026-10-09 08:31:56','2026-10-09 08:31:56','approved',NULL,NULL,NULL,NULL),(7,12,7,'Nhóm cầu lông sau giờ làm · Thủ Đức','Đội chơi đều mỗi tuần, chào đón bạn mới và ưu tiên tinh thần thể thao.','Tuyển thành viên','Sơ cấp','2026-10-17 18:00:00',6,'open','2026-10-09 07:31:56','2026-10-09 07:31:56','approved',NULL,NULL,NULL,NULL),(8,13,8,'Chia sẻ cách khởi động trước khi lên sân · Quận 7','Mình dành 10 phút xoay khớp, chạy nhẹ và tập bước di chuyển trước buổi chơi. Mọi người có mẹo nào khác không?','Chia sẻ','Trung cấp','2026-10-11 18:00:00',6,'open','2026-10-09 06:31:56','2026-10-09 06:31:56','approved',NULL,NULL,NULL,NULL),(9,14,1,'Tìm thêm bạn đánh đôi · Quận 1','Nhóm mình cần thêm hai bạn, trình độ sơ cấp đến trung cấp. Chơi vui, cùng chia tiền sân nhé!','Tìm người đánh','Trung cấp','2026-10-12 18:00:00',6,'open','2026-10-09 05:31:56','2026-10-09 05:31:56','approved',NULL,NULL,NULL,NULL),(10,15,2,'Giao lưu cầu lông cuối tuần · Quận 3','Mời mọi người tham gia giao lưu. Mang theo vợt và nước uống, đến sớm 10 phút để khởi động.','Giao lưu','Sơ cấp','2026-10-13 18:00:00',6,'open','2026-10-09 04:31:56','2026-10-09 04:31:56','approved',NULL,NULL,NULL,NULL),(11,5,3,'Nhóm cầu lông sau giờ làm · Bình Thạnh','Đội chơi đều mỗi tuần, chào đón bạn mới và ưu tiên tinh thần thể thao.','Tuyển thành viên','Trung cấp','2026-10-14 18:00:00',6,'open','2026-10-09 03:31:56','2026-10-09 03:31:56','approved',NULL,NULL,NULL,NULL),(12,17,4,'Chia sẻ cách khởi động trước khi lên sân · Phú Nhuận','Mình dành 10 phút xoay khớp, chạy nhẹ và tập bước di chuyển trước buổi chơi. Mọi người có mẹo nào khác không?','Chia sẻ','Trung cấp','2026-10-15 18:00:00',6,'open','2026-10-09 02:31:56','2026-10-09 02:31:56','approved',NULL,NULL,NULL,NULL),(13,18,5,'Tìm thêm bạn đánh đôi · Gò Vấp','Nhóm mình cần thêm hai bạn, trình độ sơ cấp đến trung cấp. Chơi vui, cùng chia tiền sân nhé!','Tìm người đánh','Sơ cấp','2026-10-16 18:00:00',6,'open','2026-10-09 01:31:56','2026-10-09 01:31:56','approved',NULL,NULL,NULL,NULL),(14,19,6,'Giao lưu cầu lông cuối tuần · Tân Bình','Mời mọi người tham gia giao lưu. Mang theo vợt và nước uống, đến sớm 10 phút để khởi động.','Giao lưu','Trung cấp','2026-10-17 18:00:00',6,'open','2026-10-09 00:31:56','2026-10-09 00:31:56','approved',NULL,NULL,NULL,NULL),(15,20,7,'Nhóm cầu lông sau giờ làm · Thủ Đức','Đội chơi đều mỗi tuần, chào đón bạn mới và ưu tiên tinh thần thể thao.','Tuyển thành viên','Trung cấp','2026-10-11 18:00:00',6,'open','2026-10-08 23:31:56','2026-10-08 23:31:56','approved',NULL,NULL,NULL,NULL),(16,5,8,'Chia sẻ cách khởi động trước khi lên sân · Quận 7','Mình dành 10 phút xoay khớp, chạy nhẹ và tập bước di chuyển trước buổi chơi. Mọi người có mẹo nào khác không?','Chia sẻ','Sơ cấp','2026-10-12 18:00:00',6,'open','2026-10-08 22:31:56','2026-10-08 22:31:56','approved',NULL,NULL,NULL,NULL),(17,22,1,'Tìm thêm bạn đánh đôi · Quận 1','Nhóm mình cần thêm hai bạn, trình độ sơ cấp đến trung cấp. Chơi vui, cùng chia tiền sân nhé!','Tìm người đánh','Trung cấp','2026-10-13 18:00:00',6,'open','2026-10-08 21:31:56','2026-10-08 21:31:56','approved',NULL,NULL,NULL,NULL),(18,23,2,'Giao lưu cầu lông cuối tuần · Quận 3','Mời mọi người tham gia giao lưu. Mang theo vợt và nước uống, đến sớm 10 phút để khởi động.','Giao lưu','Trung cấp','2026-10-14 18:00:00',6,'open','2026-10-08 20:31:56','2026-10-08 20:31:56','approved',NULL,NULL,NULL,NULL),(19,24,3,'Nhóm cầu lông sau giờ làm · Bình Thạnh','Đội chơi đều mỗi tuần, chào đón bạn mới và ưu tiên tinh thần thể thao.','Tuyển thành viên','Sơ cấp','2026-10-15 18:00:00',6,'open','2026-10-08 19:31:56','2026-10-08 19:31:56','approved',NULL,NULL,NULL,NULL),(20,25,4,'Chia sẻ cách khởi động trước khi lên sân · Phú Nhuận','Mình dành 10 phút xoay khớp, chạy nhẹ và tập bước di chuyển trước buổi chơi. Mọi người có mẹo nào khác không?','Chia sẻ','Trung cấp','2026-10-16 18:00:00',6,'open','2026-10-08 18:31:56','2026-10-08 18:31:56','pending',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `community_posts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `content_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'page',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_pages_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `content_pages` WRITE;
/*!40000 ALTER TABLE `content_pages` DISABLE KEYS */;
INSERT INTO `content_pages` VALUES (1,'dat-san','Đặt sân và giữ chỗ','faq','Chọn chi nhánh, ngày, khung giờ và sân trống. Kiểm tra tổng tiền trước khi xác nhận. Lịch đặt trực tuyến cần thanh toán phần đặt cọc trong thời hạn hiển thị. Lịch chưa đủ đặt cọc sẽ được tự giải phóng khi hết hạn.',1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(2,'huy-lich','Hủy lịch và hoàn tiền','faq','Bạn có thể tự hủy lịch chưa thanh toán trước giờ chơi. Lịch thanh toán hoàn toàn bằng ví có thể tự hủy và nhận lại tiền vào ví trước giờ chơi ít nhất 4 tiếng. Với các phương thức khác, gửi yêu cầu hỗ trợ hoặc liên hệ chi nhánh để đối soát và hoàn tiền.',1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(3,'diem-thuong','Tích điểm và quyền lợi hội viên','faq','Mỗi 10.000đ thanh toán cho buổi chơi đã hoàn tất hoặc mua hàng được tích 1 điểm. Đổi điểm thành tiền trong ví tại mục Quyền lợi. Hạng hội viên được xét theo tổng điểm đã tích; ưu đãi phù hợp được áp dụng khi tính giá sân.',1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(4,'cong-dong','Chơi cùng nhau, tôn trọng nhau','faq','Đăng bài tìm bạn chơi, tham gia nhóm và trò chuyện. Bài có ngôn từ cần kiểm tra sẽ chờ người quản trị duyệt. Bạn có thể báo cáo nội dung, chặn tài khoản và gỡ bài của mình. Không chia sẻ thông tin riêng của người khác.',1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(5,'ve-chung-toi','Kết nối sân chơi cầu lông','page','Badminton Hub giúp bạn tìm khung giờ phù hợp, đặt sân và kết nối người chơi. Xem thông tin từng chi nhánh trước khi lựa chọn; quản lý lịch, thanh toán và yêu cầu hỗ trợ trong tài khoản.',1,'2026-10-09 13:31:54','2026-10-09 13:31:54');
/*!40000 ALTER TABLE `content_pages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `content_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reason` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `content_reports_community_post_id_foreign` (`community_post_id`),
  KEY `content_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `content_reports_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `content_reports` WRITE;
/*!40000 ALTER TABLE `content_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_reports` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `conversation_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_members` (
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `typing_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`conversation_id`,`user_id`),
  KEY `conversation_members_user_id_foreign` (`user_id`),
  CONSTRAINT `conversation_members_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `conversation_members` WRITE;
/*!40000 ALTER TABLE `conversation_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_members` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direct_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_post_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_direct_key_unique` (`direct_key`),
  UNIQUE KEY `conversations_community_post_id_unique` (`community_post_id`),
  UNIQUE KEY `conversations_booking_id_unique` (`booking_id`),
  CONSTRAINT `conversations_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `conversations_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` int unsigned NOT NULL,
  `minimum_amount` int unsigned NOT NULL DEFAULT '0',
  `max_discount` int unsigned DEFAULT NULL,
  `usage_limit` int unsigned NOT NULL DEFAULT '100',
  `starts_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`),
  KEY `coupons_branch_id_foreign` (`branch_id`),
  CONSTRAINT `coupons_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,'HUBDEMO1',1,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,'HUBDEMO2',2,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,'HUBDEMO3',3,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,'HUBDEMO4',4,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,'HUBDEMO5',5,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,'HUBDEMO6',6,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,'HUBDEMO7',7,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,'HUBDEMO8',8,'percent',10,150000,50000,100,'2026-10-08 20:31:56','2026-12-08 20:31:56',1,'2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `court_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `court_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `court_id` bigint unsigned NOT NULL,
  `game_event_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `court_blocks_court_id_foreign` (`court_id`),
  KEY `court_blocks_game_event_id_foreign` (`game_event_id`),
  CONSTRAINT `court_blocks_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `court_blocks_game_event_id_foreign` FOREIGN KEY (`game_event_id`) REFERENCES `game_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `court_blocks` WRITE;
/*!40000 ALTER TABLE `court_blocks` DISABLE KEYS */;
INSERT INTO `court_blocks` VALUES (1,6,1,'2026-10-16 09:00:00','2026-10-16 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,14,2,'2026-10-17 09:00:00','2026-10-17 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,26,3,'2026-10-18 09:00:00','2026-10-18 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,33,4,'2026-10-19 09:00:00','2026-10-19 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,43,5,'2026-10-20 09:00:00','2026-10-20 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,52,6,'2026-10-21 09:00:00','2026-10-21 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,66,7,'2026-10-22 09:00:00','2026-10-22 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,77,8,'2026-10-23 09:00:00','2026-10-23 12:00:00',1,'2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `court_blocks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `court_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `court_holds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `court_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `released` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `court_holds_request_key_unique` (`request_key`),
  KEY `court_holds_user_id_foreign` (`user_id`),
  KEY `court_holds_court_id_expires_at_index` (`court_id`,`expires_at`),
  CONSTRAINT `court_holds_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `court_holds_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `court_holds` WRITE;
/*!40000 ALTER TABLE `court_holds` DISABLE KEYS */;
/*!40000 ALTER TABLE `court_holds` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `courts` WRITE;
/*!40000 ALTER TABLE `courts` DISABLE KEYS */;
INSERT INTO `courts` VALUES (1,1,'Sân 01','active',120000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(2,1,'Sân 02','active',120000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(3,1,'Sân 03','active',120000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(4,1,'Sân 04','active',120000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(5,1,'Sân 05','active',120000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(6,1,'Sân 06','active',120000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(7,2,'Sân 01','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(8,2,'Sân 02','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(9,2,'Sân 03','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(10,2,'Sân 04','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(11,2,'Sân 05','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(12,2,'Sân 06','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(13,2,'Sân 07','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(14,2,'Sân 08','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(15,3,'Sân 01','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(16,3,'Sân 02','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(17,3,'Sân 03','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(18,3,'Sân 04','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(19,3,'Sân 05','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(20,3,'Sân 06','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(21,3,'Sân 07','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(22,3,'Sân 08','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(23,3,'Sân 09','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(24,3,'Sân 10','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(25,3,'Sân 11','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(26,3,'Sân 12','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(27,4,'Sân 01','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(28,4,'Sân 02','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(29,4,'Sân 03','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(30,4,'Sân 04','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(31,4,'Sân 05','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(32,4,'Sân 06','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(33,4,'Sân 07','active',110000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(34,5,'Sân 01','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(35,5,'Sân 02','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(36,5,'Sân 03','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(37,5,'Sân 04','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(38,5,'Sân 05','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(39,5,'Sân 06','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(40,5,'Sân 07','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(41,5,'Sân 08','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(42,5,'Sân 09','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(43,5,'Sân 10','active',95000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(44,6,'Sân 01','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(45,6,'Sân 02','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(46,6,'Sân 03','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(47,6,'Sân 04','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(48,6,'Sân 05','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(49,6,'Sân 06','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(50,6,'Sân 07','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(51,6,'Sân 08','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(52,6,'Sân 09','active',100000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(53,7,'Sân 01','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(54,7,'Sân 02','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(55,7,'Sân 03','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(56,7,'Sân 04','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(57,7,'Sân 05','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(58,7,'Sân 06','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(59,7,'Sân 07','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(60,7,'Sân 08','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(61,7,'Sân 09','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(62,7,'Sân 10','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(63,7,'Sân 11','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(64,7,'Sân 12','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(65,7,'Sân 13','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(66,7,'Sân 14','active',90000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(67,8,'Sân 01','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(68,8,'Sân 02','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(69,8,'Sân 03','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(70,8,'Sân 04','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(71,8,'Sân 05','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(72,8,'Sân 06','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(73,8,'Sân 07','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(74,8,'Sân 08','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(75,8,'Sân 09','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(76,8,'Sân 10','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(77,8,'Sân 11','active',105000,180000,200000,'2026-10-09 13:31:54','2026-10-09 13:31:54');
/*!40000 ALTER TABLE `courts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `demo_data_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demo_data_batches` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `counts` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `demo_data_batches` WRITE;
/*!40000 ALTER TABLE `demo_data_batches` DISABLE KEYS */;
INSERT INTO `demo_data_batches` VALUES ('realistic-demo-v1','{\"users\": 20, \"coaches\": 8, \"coupons\": 8, \"reviews\": 20, \"bookings\": 60, \"payments\": 50, \"game_events\": 8, \"memberships\": 10, \"court_blocks\": 8, \"maintenances\": 6, \"post_comments\": 20, \"community_posts\": 20, \"commerce_payments\": 26, \"event_registrations\": 16}','2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `demo_data_batches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `event_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_event_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount` int unsigned NOT NULL,
  `paid_amount` int unsigned NOT NULL DEFAULT '0',
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_registrations_game_event_id_user_id_unique` (`game_event_id`,`user_id`),
  KEY `event_registrations_user_id_foreign` (`user_id`),
  CONSTRAINT `event_registrations_game_event_id_foreign` FOREIGN KEY (`game_event_id`) REFERENCES `game_events` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `event_registrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_registrations` WRITE;
/*!40000 ALTER TABLE `event_registrations` DISABLE KEYS */;
INSERT INTO `event_registrations` VALUES (1,1,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,1,6,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,2,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,2,7,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,3,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,3,8,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,4,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,4,9,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(9,5,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(10,5,10,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(11,6,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(12,6,11,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(13,7,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(14,7,12,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(15,8,5,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56'),(16,8,13,50000,50000,'cash','confirmed','2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `event_registrations` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `favorite_courts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorite_courts` (
  `user_id` bigint unsigned NOT NULL,
  `court_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`court_id`),
  KEY `favorite_courts_court_id_foreign` (`court_id`),
  CONSTRAINT `favorite_courts_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorite_courts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `favorite_courts` WRITE;
/*!40000 ALTER TABLE `favorite_courts` DISABLE KEYS */;
/*!40000 ALTER TABLE `favorite_courts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `game_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `game_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Đơn · Loại trực tiếp',
  `skill_level` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Mọi trình độ',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'photo-1626224583764-f87db24ac4ea',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `capacity` smallint unsigned NOT NULL,
  `fee` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_events_branch_id_foreign` (`branch_id`),
  KEY `game_events_created_by_foreign` (`created_by`),
  CONSTRAINT `game_events_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `game_events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `game_events` WRITE;
/*!40000 ALTER TABLE `game_events` DISABLE KEYS */;
INSERT INTO `game_events` VALUES (1,1,1,'Giao lưu cuối tuần · Quận 1','friendly','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','Giao lưu','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-16 09:00:00','2026-10-16 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,2,1,'Giải đôi phong trào · Quận 3','league','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','round_robin','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-17 09:00:00','2026-10-17 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,3,1,'Lớp nền tảng cầu lông · Bình Thạnh','training','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','Giao lưu','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-18 09:00:00','2026-10-18 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,4,1,'Workshop di chuyển · Phú Nhuận','workshop','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','Giao lưu','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-19 09:00:00','2026-10-19 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,5,1,'Giao lưu cuối tuần · Gò Vấp','friendly','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','Giao lưu','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-20 09:00:00','2026-10-20 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,6,1,'Giải đôi phong trào · Tân Bình','league','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','round_robin','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-21 09:00:00','2026-10-21 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,7,1,'Lớp nền tảng cầu lông · Thủ Đức','training','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','Giao lưu','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-22 09:00:00','2026-10-22 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,8,1,'Workshop di chuyển · Quận 7','workshop','Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.','Giao lưu','Mọi trình độ','photo-1626224583764-f87db24ac4ea','2026-10-23 09:00:00','2026-10-23 12:00:00',16,50000,'published','2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `game_events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `integration_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `integration_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `amount` int NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `integration_transactions_provider_transaction_id_unique` (`provider`,`transaction_id`),
  KEY `integration_transactions_booking_id_foreign` (`booking_id`),
  CONSTRAINT `integration_transactions_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `integration_transactions` WRITE;
/*!40000 ALTER TABLE `integration_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `integration_transactions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `inventory_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `delta` int NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_adjustments_request_key_unique` (`request_key`),
  KEY `inventory_adjustments_product_id_foreign` (`product_id`),
  KEY `inventory_adjustments_user_id_foreign` (`user_id`),
  CONSTRAINT `inventory_adjustments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `inventory_adjustments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `inventory_adjustments` WRITE;
/*!40000 ALTER TABLE `inventory_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_adjustments` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `loyalty_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `request_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int NOT NULL,
  `balance` int NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loyalty_entries_request_key_unique` (`request_key`),
  KEY `loyalty_entries_user_id_foreign` (`user_id`),
  CONSTRAINT `loyalty_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `loyalty_entries` WRITE;
/*!40000 ALTER TABLE `loyalty_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_entries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `loyalty_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int unsigned NOT NULL,
  `wallet_amount` int unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `loyalty_rewards` WRITE;
/*!40000 ALTER TABLE `loyalty_rewards` DISABLE KEYS */;
INSERT INTO `loyalty_rewards` VALUES (1,'Đổi 100 điểm lấy 10.000đ vào ví',100,10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(2,'Đổi 500 điểm lấy 60.000đ vào ví',500,60000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54');
/*!40000 ALTER TABLE `loyalty_rewards` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `maintenances` WRITE;
/*!40000 ALTER TABLE `maintenances` DISABLE KEYS */;
INSERT INTO `maintenances` VALUES (1,4,'2026-10-12 10:00:00','2026-10-12 12:00:00','Kiểm tra mặt sân và thay đèn định kỳ','scheduled','2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,10,'2026-10-12 10:00:00','2026-10-12 12:00:00','Kiểm tra mặt sân và thay đèn định kỳ','scheduled','2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,18,'2026-10-12 10:00:00','2026-10-12 12:00:00','Kiểm tra mặt sân và thay đèn định kỳ','scheduled','2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,30,'2026-10-12 10:00:00','2026-10-12 12:00:00','Kiểm tra mặt sân và thay đèn định kỳ','scheduled','2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,37,'2026-10-12 10:00:00','2026-10-12 12:00:00','Kiểm tra mặt sân và thay đèn định kỳ','scheduled','2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,47,'2026-10-12 10:00:00','2026-10-12 12:00:00','Kiểm tra mặt sân và thay đèn định kỳ','scheduled','2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `maintenances` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `media_uploads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_uploads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uploads_path_unique` (`path`),
  KEY `media_uploads_user_id_foreign` (`user_id`),
  CONSTRAINT `media_uploads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `media_uploads` WRITE;
/*!40000 ALTER TABLE `media_uploads` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_uploads` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `membership_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` int unsigned NOT NULL,
  `discount_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `duration_days` smallint unsigned NOT NULL DEFAULT '30',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `early_days` smallint unsigned NOT NULL DEFAULT '0',
  `points_threshold` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `membership_plans_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `membership_plans` WRITE;
/*!40000 ALTER TABLE `membership_plans` DISABLE KEYS */;
INSERT INTO `membership_plans` VALUES (1,'Hạng Đồng',0,3,365,1,'2026-10-09 13:31:54','2026-10-09 13:31:54',3,100),(2,'Hạng Bạc',0,5,365,1,'2026-10-09 13:31:54','2026-10-09 13:31:54',7,500),(3,'Hạng Vàng',0,8,365,1,'2026-10-09 13:31:54','2026-10-09 13:31:54',14,1500),(4,'Silver',199000,5,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54',0,0),(5,'Gold',399000,10,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54',0,0),(6,'VIP',799000,20,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54',0,0);
/*!40000 ALTER TABLE `membership_plans` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `memberships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `membership_plan_id` bigint unsigned NOT NULL,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int unsigned NOT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `memberships_request_key_unique` (`request_key`),
  KEY `memberships_membership_plan_id_foreign` (`membership_plan_id`),
  KEY `memberships_user_id_expires_at_index` (`user_id`,`expires_at`),
  CONSTRAINT `memberships_membership_plan_id_foreign` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `memberships_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `memberships` WRITE;
/*!40000 ALTER TABLE `memberships` DISABLE KEYS */;
INSERT INTO `memberships` VALUES (1,5,4,'2fefa7b5-fd62-5636-991f-a0d090998a4b',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(2,6,4,'81c987a6-a8fb-52e3-8263-a3dacd945ec8',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(3,7,4,'fb583fbd-91eb-55d8-b917-f6d49305a7de',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(4,8,4,'0dc60979-1112-59f7-a355-a4b7bc44f705',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(5,9,4,'dfd16d26-8539-54e1-a705-ad797d28fbba',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(6,10,4,'2496a360-053c-5583-b9a7-250a9c015d7c',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(7,11,4,'251d38e5-255d-59f6-ac29-ac028d3f4b7f',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(8,12,4,'35682bf4-3fc4-5922-8a6b-023246cab85a',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(9,13,4,'352684eb-2fce-542c-980e-378a96a38dd8',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56'),(10,14,4,'30fdd72f-8248-58b8-b58d-281b752d43c4',199000,'cash','2026-10-07 20:31:56','2026-11-06 20:31:56','2026-10-09 13:31:56','2026-10-09 13:31:56');
/*!40000 ALTER TABLE `memberships` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_08_080648_create_badminton_tables',1),(5,'2026_10_08_173715_create_notifications_table',1),(6,'2026_10_08_174754_extend_hub_business_modules',1),(7,'2026_10_08_175737_finish_hub_integrations',1),(8,'2026_10_09_033306_complete_hub_features_and_ai',1),(9,'2026_10_09_090952_add_bank_reconciliation_details',1),(10,'2026_10_09_200716_align_booking_pos_ai_and_demo_data',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `payment_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `purpose` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int unsigned NOT NULL,
  `order_info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extra_data` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pay_url` text COLLATE utf8mb4_unicode_ci,
  `trans_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `result_code` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_attempts_request_key_unique` (`request_key`),
  UNIQUE KEY `payment_attempts_order_id_unique` (`order_id`),
  UNIQUE KEY `payment_attempts_trans_id_unique` (`trans_id`),
  KEY `payment_attempts_user_id_foreign` (`user_id`),
  KEY `payment_attempts_booking_id_foreign` (`booking_id`),
  CONSTRAINT `payment_attempts_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payment_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payment_attempts` WRITE;
/*!40000 ALTER TABLE `payment_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_attempts` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,1,'a5766e96-90c4-564a-8a7f-877f404e111d','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(2,2,1,'bf4d7e4e-5442-55d0-aa11-54ba4909ecd9','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(3,3,1,'3109ce02-239b-5603-99ad-f39564338b6e','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(4,4,1,'117edd2d-8ad8-5cc5-a6f7-875c380c767d','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(5,5,1,'862d73f5-4626-5a8e-9447-a6ff8881d526','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(6,6,1,'bc3136cd-7a95-5cfc-8c3c-5f3bb4ab73b7','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(7,7,1,'702efa6d-b96c-5161-8e7b-c11b74605aa1','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(8,8,1,'e5faa73a-73a7-5c20-ac94-b1f88e360607','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-07 10:00:00','2026-10-07 10:00:00'),(9,9,1,'e5685ead-b5a1-5631-b6e1-cc7a160b6383','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(10,10,1,'86811508-f2af-5055-a8a2-c1a57400022b','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(11,11,1,'09af0f54-d0b8-566a-a163-b8fe1d6aa335','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(12,12,1,'f36f25d3-3550-546a-9cdf-bd4ee7253d17','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(13,13,1,'d8922d9c-ad42-5e17-af0c-9e5e784862dd','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(14,14,1,'8c343b56-5548-5789-88a0-ddfe1fc38e68','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(15,15,1,'d6ba0e5c-b8a9-5146-9f32-5a164a6b46a2','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(16,16,1,'b45c3812-ed30-5881-81e3-24ddfe2c9ee5','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-06 10:00:00','2026-10-06 10:00:00'),(17,17,1,'8f577768-9676-5f76-8782-1c95075eec1a','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(18,18,1,'8dbd1b75-5f43-5d23-885d-db612aa62f18','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(19,19,1,'69d1266c-4840-59f7-bae6-b3292b001bc8','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(20,20,1,'5a7f998b-45ea-5df8-accb-368b754c1c3b','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(21,21,1,'bf0da6f4-bd5d-5485-9642-8e9ed662de22','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(22,22,1,'d29fbece-721b-5870-9d7d-343c549c2c5e','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(23,23,1,'5092a220-5b80-5318-b964-4d7b7984f9c8','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(24,24,1,'4febe23c-c18e-5bf8-8388-90c0016f5ab4','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-05 10:00:00','2026-10-05 10:00:00'),(25,25,1,'3310a3e2-2463-57df-894e-7327ade472a5','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(26,26,1,'71f4d1ad-f07c-537f-b122-ac24db5bd906','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(27,27,1,'d3f382dc-b51c-576c-9afb-45bf6e676add','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(28,28,1,'0af67fe6-d0d6-5709-8569-b0473294616f','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(29,29,1,'c995b690-fb92-5422-b3af-7b50d3359fa3','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(30,30,1,'c84a24f8-bef2-56d2-872d-3bbe01899ea6','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(31,31,1,'57144b96-ae34-534f-8d3d-831faf415262','payment','cash',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(32,32,1,'6037bbc8-d058-5b9f-8694-a8ae4612a52b','payment','bank',360000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-04 10:00:00','2026-10-04 10:00:00'),(33,33,1,'8caff064-66cf-5e59-a1f8-85f94f31b07b','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-03 10:00:00','2026-10-03 10:00:00'),(34,34,1,'5693cbe1-ff13-579e-a2d1-f9c3f3581a78','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-03 10:00:00','2026-10-03 10:00:00'),(35,35,1,'9d0af59b-d38b-5615-8b82-935e161c0b42','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-03 10:00:00','2026-10-03 10:00:00'),(36,36,1,'fc296b85-d7c4-56a8-a0f2-7d1e32627b72','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-03 10:00:00','2026-10-03 10:00:00'),(37,37,1,'85c3b0e9-8507-57ec-b754-0a7612450059','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(38,38,1,'603d9b12-6011-5718-ad8e-ac7a1f2cdfeb','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(39,39,1,'30511616-c7e6-544f-8401-922166d2b180','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(40,40,1,'3a53126a-cf88-5789-8c7d-190ada445057','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(41,41,1,'1e590e81-3067-5631-af51-ca7619d915d8','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(42,42,1,'45f646af-2c8d-5803-b891-427d07a7eaf4','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(43,43,1,'c8982b07-7711-58cd-bad2-b994e6bdb17a','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(44,44,1,'a80ef84a-0d12-5689-b8cd-215a2a84adb3','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(45,45,1,'5d88a674-c13e-5f85-91a2-a80edee9b140','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(46,46,1,'5942cb6b-eb35-58f8-abee-8c32ba8c97e7','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(47,47,1,'1cc32635-713f-5dac-a82c-8ef6264b4190','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(48,48,1,'8d006543-bf93-538e-a3f3-e4cbcc9319aa','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(49,49,1,'65186dcf-bee1-5272-a542-a00b5fc38466','payment','cash',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56'),(50,50,1,'4c8b26e4-5808-5bae-8dd7-15ac1f9f6b4e','payment','bank',400000,'Giao dịch mẫu, không qua cổng thanh toán','2026-10-09 12:31:56','2026-10-09 12:31:56');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `phone_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phone_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_codes_user_id_foreign` (`user_id`),
  CONSTRAINT `phone_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `phone_codes` WRITE;
/*!40000 ALTER TABLE `phone_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `phone_codes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `post_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_comments_community_post_id_foreign` (`community_post_id`),
  KEY `post_comments_user_id_foreign` (`user_id`),
  KEY `post_comments_parent_id_foreign` (`parent_id`),
  CONSTRAINT `post_comments_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `post_comments` (`id`),
  CONSTRAINT `post_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_comments` WRITE;
/*!40000 ALTER TABLE `post_comments` DISABLE KEYS */;
INSERT INTO `post_comments` VALUES (1,1,8,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(2,1,5,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',1),(3,2,9,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(4,2,7,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',3),(5,3,10,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(6,3,8,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',5),(7,4,11,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(8,4,9,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',7),(9,5,12,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(10,5,10,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',9),(11,6,13,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(12,6,5,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',11),(13,7,14,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(14,7,12,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',13),(15,8,15,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(16,8,13,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',15),(17,9,16,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(18,9,14,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',17),(19,10,17,'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?','2026-10-09 13:31:56','2026-10-09 13:31:56',NULL),(20,10,15,'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.','2026-10-09 13:31:56','2026-10-09 13:31:56',19);
/*!40000 ALTER TABLE `post_comments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `post_joins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_joins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_joins_community_post_id_user_id_unique` (`community_post_id`,`user_id`),
  KEY `post_joins_user_id_foreign` (`user_id`),
  CONSTRAINT `post_joins_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_joins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_joins` WRITE;
/*!40000 ALTER TABLE `post_joins` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_joins` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `post_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_likes` (
  `community_post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`community_post_id`,`user_id`),
  KEY `post_likes_user_id_foreign` (`user_id`),
  CONSTRAINT `post_likes_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_likes` WRITE;
/*!40000 ALTER TABLE `post_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_likes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pricing_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pricing_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `court_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `weekday` tinyint unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `from_minute` smallint unsigned NOT NULL,
  `to_minute` smallint unsigned NOT NULL,
  `hourly_rate` int unsigned NOT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pricing_rules_branch_id_foreign` (`branch_id`),
  KEY `pricing_rules_court_id_foreign` (`court_id`),
  CONSTRAINT `pricing_rules_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `pricing_rules_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pricing_rules` WRITE;
/*!40000 ALTER TABLE `pricing_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `pricing_rules` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 0xF09F8FB8,
  `price` int unsigned NOT NULL,
  `stock` int unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_branch_id_sku_unique` (`branch_id`,`sku`),
  CONSTRAINT `products_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(2,1,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(3,1,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(4,1,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(5,1,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(6,1,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(7,2,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(8,2,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(9,2,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(10,2,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(11,2,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(12,2,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(13,3,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(14,3,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(15,3,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(16,3,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(17,3,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(18,3,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(19,4,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(20,4,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(21,4,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(22,4,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(23,4,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(24,4,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(25,5,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(26,5,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(27,5,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(28,5,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(29,5,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(30,5,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(31,6,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(32,6,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(33,6,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(34,6,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(35,6,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(36,6,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(37,7,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(38,7,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(39,7,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(40,7,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(41,7,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(42,7,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(43,8,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(44,8,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(45,8,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(46,8,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(47,8,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(48,8,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 13:31:54','2026-10-09 13:31:54');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `refund_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refund_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `payment_attempt_id` bigint unsigned DEFAULT NULL,
  `request_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refund_requests_request_key_unique` (`request_key`),
  UNIQUE KEY `refund_requests_order_id_unique` (`order_id`),
  KEY `refund_requests_booking_id_foreign` (`booking_id`),
  KEY `refund_requests_payment_attempt_id_foreign` (`payment_attempt_id`),
  CONSTRAINT `refund_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `refund_requests_payment_attempt_id_foreign` FOREIGN KEY (`payment_attempt_id`) REFERENCES `payment_attempts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `refund_requests` WRITE;
/*!40000 ALTER TABLE `refund_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `refund_requests` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `images` json DEFAULT NULL,
  `reply` text COLLATE utf8mb4_unicode_ci,
  `replied_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_booking_id_unique` (`booking_id`),
  KEY `reviews_branch_id_foreign` (`branch_id`),
  KEY `reviews_user_id_foreign` (`user_id`),
  KEY `reviews_replied_by_foreign` (`replied_by`),
  CONSTRAINT `reviews_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviews_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviews_replied_by_foreign` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`),
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES (1,1,1,5,5,'Sân sáng, mặt sân êm và có chỗ gửi xe.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(2,2,2,7,4,'Nhân viên hỗ trợ nhanh, nhóm mình sẽ quay lại.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(3,3,3,8,5,'Đặt giờ tiện, sân sạch và thoáng.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(4,4,4,9,5,'Buổi chơi vui, dễ tìm bạn cùng trình độ.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(5,5,5,10,4,'Sân sáng, mặt sân êm và có chỗ gửi xe.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(6,6,6,5,5,'Nhân viên hỗ trợ nhanh, nhóm mình sẽ quay lại.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(7,7,7,12,4,'Đặt giờ tiện, sân sạch và thoáng.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(8,8,8,13,5,'Buổi chơi vui, dễ tìm bạn cùng trình độ.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(9,9,1,14,5,'Sân sáng, mặt sân êm và có chỗ gửi xe.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(10,10,2,15,4,'Nhân viên hỗ trợ nhanh, nhóm mình sẽ quay lại.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(11,11,3,5,5,'Đặt giờ tiện, sân sạch và thoáng.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(12,12,4,17,4,'Buổi chơi vui, dễ tìm bạn cùng trình độ.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(13,13,5,18,5,'Sân sáng, mặt sân êm và có chỗ gửi xe.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(14,14,6,19,5,'Nhân viên hỗ trợ nhanh, nhóm mình sẽ quay lại.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(15,15,7,20,4,'Đặt giờ tiện, sân sạch và thoáng.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(16,16,8,5,5,'Buổi chơi vui, dễ tìm bạn cùng trình độ.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(17,17,1,22,4,'Sân sáng, mặt sân êm và có chỗ gửi xe.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(18,18,2,23,5,'Nhân viên hỗ trợ nhanh, nhóm mình sẽ quay lại.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(19,19,3,24,5,'Đặt giờ tiện, sân sạch và thoáng.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL),(20,20,4,25,4,'Buổi chơi vui, dễ tìm bạn cùng trình độ.',1,'2026-10-09 13:31:56','2026-10-09 13:31:56',NULL,NULL,NULL);
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `allowed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_role_permission_unique` (`role`,`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_price` int unsigned NOT NULL,
  `amount` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_id_foreign` (`sale_id`),
  KEY `sale_items_product_id_foreign` (`product_id`),
  CONSTRAINT `sale_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sale_items_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `clerk_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `total_amount` int unsigned NOT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_request_key_unique` (`request_key`),
  UNIQUE KEY `sales_reference_unique` (`reference`),
  KEY `sales_branch_id_foreign` (`branch_id`),
  KEY `sales_clerk_id_foreign` (`clerk_id`),
  KEY `sales_user_id_foreign` (`user_id`),
  KEY `sales_booking_id_foreign` (`booking_id`),
  CONSTRAINT `sales_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_clerk_id_foreign` FOREIGN KEY (`clerk_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `saved_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_posts` (
  `user_id` bigint unsigned NOT NULL,
  `community_post_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`community_post_id`),
  KEY `saved_posts_community_post_id_foreign` (`community_post_id`),
  CONSTRAINT `saved_posts_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `saved_posts` WRITE;
/*!40000 ALTER TABLE `saved_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `saved_posts` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,1,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(2,1,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(3,1,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(4,1,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(5,1,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(6,1,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(7,1,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(8,1,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(9,2,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(10,2,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(11,2,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(12,2,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(13,2,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(14,2,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(15,2,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(16,2,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(17,3,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(18,3,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(19,3,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(20,3,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(21,3,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(22,3,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(23,3,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(24,3,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(25,4,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(26,4,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(27,4,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(28,4,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(29,4,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(30,4,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(31,4,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(32,4,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(33,5,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(34,5,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(35,5,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(36,5,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(37,5,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(38,5,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(39,5,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(40,5,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(41,6,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(42,6,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(43,6,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(44,6,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(45,6,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(46,6,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(47,6,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(48,6,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(49,7,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(50,7,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(51,7,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(52,7,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(53,7,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(54,7,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(55,7,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(56,7,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(57,8,'Thuê vợt','buổi',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(58,8,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(59,8,'Nước suối','chai',10000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(60,8,'Pocari Sweat','chai',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(61,8,'Thuê giày','buổi',25000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(62,8,'Locker','buổi',15000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(63,8,'Shower','lần',30000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54'),(64,8,'Huấn luyện viên','buổi',200000,1,'2026-10-09 13:31:54','2026-10-09 13:31:54');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `reply` text COLLATE utf8mb4_unicode_ci,
  `replied_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_tickets_user_id_foreign` (`user_id`),
  KEY `support_tickets_branch_id_foreign` (`branch_id`),
  KEY `support_tickets_replied_by_foreign` (`replied_by`),
  CONSTRAINT `support_tickets_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `support_tickets_replied_by_foreign` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`),
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `tournament_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tournament_matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_event_id` bigint unsigned NOT NULL,
  `round` tinyint unsigned NOT NULL,
  `position` smallint unsigned NOT NULL,
  `player_a` bigint unsigned DEFAULT NULL,
  `player_b` bigint unsigned DEFAULT NULL,
  `score_a` smallint unsigned DEFAULT NULL,
  `score_b` smallint unsigned DEFAULT NULL,
  `winner_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tournament_matches_game_event_id_round_position_unique` (`game_event_id`,`round`,`position`),
  KEY `tournament_matches_player_a_foreign` (`player_a`),
  KEY `tournament_matches_player_b_foreign` (`player_b`),
  KEY `tournament_matches_winner_id_foreign` (`winner_id`),
  CONSTRAINT `tournament_matches_game_event_id_foreign` FOREIGN KEY (`game_event_id`) REFERENCES `game_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournament_matches_player_a_foreign` FOREIGN KEY (`player_a`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tournament_matches_player_b_foreign` FOREIGN KEY (`player_b`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tournament_matches_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tournament_matches` WRITE;
/*!40000 ALTER TABLE `tournament_matches` DISABLE KEYS */;
/*!40000 ALTER TABLE `tournament_matches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_branches` (
  `user_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`branch_id`),
  KEY `user_branches_branch_id_foreign` (`branch_id`),
  CONSTRAINT `user_branches_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_branches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_branches` WRITE;
/*!40000 ALTER TABLE `user_branches` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_branches` ENABLE KEYS */;
UNLOCK TABLES;
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
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `wallet_balance` bigint unsigned NOT NULL DEFAULT '0',
  `skill_level` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sơ cấp',
  `public_profile` tinyint(1) NOT NULL DEFAULT '0',
  `loyalty_points` int NOT NULL DEFAULT '0',
  `earned_points` int NOT NULL DEFAULT '0',
  `google_sub` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_google_sub_unique` (`google_sub`),
  KEY `users_branch_id_foreign` (`branch_id`),
  KEY `users_role_index` (`role`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Quản trị viên','admin@badminton.test',NULL,'$2y$12$BByFrX123uhuUyvTqGgCSeEGX6YCQ3Gfx9eyLZt5is2mBquy6X0HW',NULL,'2026-10-09 13:31:54','2026-10-09 13:31:54','admin',NULL,NULL,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(2,'Quản lý Bình Thạnh','manager@badminton.test',NULL,'$2y$12$.0WRF.kCB7dRwtp0jP7gb.fH/i7xky8gHHdznli.d1f2lkCcpHlDa',NULL,'2026-10-09 13:31:55','2026-10-09 13:31:55','manager',NULL,3,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(3,'Nhân viên Bình Thạnh','staff@badminton.test',NULL,'$2y$12$NquPePzjLuEl20eiuP2JiuUTFtyTx9UBGkASGRx0RncHTvBnCIT4S',NULL,'2026-10-09 13:31:55','2026-10-09 13:31:55','staff',NULL,3,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(4,'Thu ngân Bình Thạnh','cashier@badminton.test',NULL,'$2y$12$vnNIgwmvYOELCQTTmN1zNezNaINWluG470N2sKkxatxhOKIXfgjB.',NULL,'2026-10-09 13:31:55','2026-10-09 13:31:55','cashier',NULL,3,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(5,'Khách hàng mẫu','customer@badminton.test',NULL,'$2y$12$oPNU3KUUi1AD5SqeXh.9K.aqV0GYeANpzjMaSZM.C7mLjSgKGsP62',NULL,'2026-10-09 13:31:55','2026-10-09 13:31:55','customer',NULL,NULL,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(6,'Nguyễn Hoàng Anh','player01@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000001',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(7,'Trần Minh Khoa','player02@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000002',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL),(8,'Lê Bảo Ngọc','player03@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000003',NULL,1,0,'Nâng cao',1,0,0,NULL,NULL,NULL,NULL),(9,'Phạm Thu Hà','player04@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000004',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(10,'Võ Đức Huy','player05@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000005',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL),(11,'Đặng Phương Linh','player06@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000006',NULL,1,0,'Nâng cao',1,0,0,NULL,NULL,NULL,NULL),(12,'Bùi Gia Bảo','player07@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000007',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(13,'Huỳnh Thanh Tâm','player08@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000008',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL),(14,'Đỗ Nhật Minh','player09@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000009',NULL,1,0,'Nâng cao',1,0,0,NULL,NULL,NULL,NULL),(15,'Ngô Khánh Vy','player10@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000010',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(16,'Dương Quốc Hưng','player11@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000011',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL),(17,'Mai Tú Anh','player12@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000012',NULL,1,0,'Nâng cao',1,0,0,NULL,NULL,NULL,NULL),(18,'Lý Hải Nam','player13@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000013',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(19,'Phan Ngọc Hân','player14@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000014',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL),(20,'Trương Minh Quân','player15@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000015',NULL,1,0,'Nâng cao',1,0,0,NULL,NULL,NULL,NULL),(21,'Hồ Quỳnh Như','player16@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000016',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(22,'Lâm Tuấn Kiệt','player17@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000017',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL),(23,'Cao Thảo My','player18@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000018',NULL,1,0,'Nâng cao',1,0,0,NULL,NULL,NULL,NULL),(24,'Đinh Việt Anh','player19@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000019',NULL,1,0,'Sơ cấp',1,0,0,NULL,NULL,NULL,NULL),(25,'Tạ Minh Thư','player20@badminton.test','2026-10-09 13:31:56','$2y$12$gXXtWSuQxXyuHXNUeqidO.LceSdpR4FCDevGE0gs1UxBxbMrutXBy',NULL,'2026-10-09 13:31:56','2026-10-09 13:31:56','customer','0802000020',NULL,1,0,'Trung cấp',1,0,0,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `waitlist_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `waitlist_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `court_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `waitlist_entries_court_id_user_id_starts_at_ends_at_unique` (`court_id`,`user_id`,`starts_at`,`ends_at`),
  KEY `waitlist_entries_user_id_foreign` (`user_id`),
  CONSTRAINT `waitlist_entries_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `waitlist_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `waitlist_entries` WRITE;
/*!40000 ALTER TABLE `waitlist_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `waitlist_entries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `wallet_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `balance_after` bigint unsigned NOT NULL,
  `kind` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_entries_request_key_unique` (`request_key`),
  KEY `wallet_entries_user_id_foreign` (`user_id`),
  KEY `wallet_entries_actor_id_foreign` (`actor_id`),
  KEY `wallet_entries_branch_id_foreign` (`branch_id`),
  CONSTRAINT `wallet_entries_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `wallet_entries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `wallet_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `wallet_entries` WRITE;
/*!40000 ALTER TABLE `wallet_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `wallet_entries` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
