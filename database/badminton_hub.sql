
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
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
INSERT INTO `branches` VALUES (1,'Badminton Hub – Quận 1','Quận 1','18 Nguyễn Bỉnh Khiêm, Q.1','028 3822 1108','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:33','2026-10-09 02:25:33',180,1380,960,90,50,30,15,NULL),(2,'Badminton Hub – Quận 3','Quận 3','210 Võ Văn Tần, Q.3','028 3930 8847','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL),(3,'Badminton Hub – Bình Thạnh','Bình Thạnh','72 Điện Biên Phủ, Bình Thạnh','028 3514 2233','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL),(4,'Badminton Hub – Phú Nhuận','Phú Nhuận','93 Phan Xích Long, Phú Nhuận','028 3845 7762','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL),(5,'Badminton Hub – Gò Vấp','Gò Vấp','45 Quang Trung, Gò Vấp','028 3894 5521','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL),(6,'Badminton Hub – Tân Bình','Tân Bình','110 Cộng Hòa, Tân Bình','028 3812 3340','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL),(7,'Badminton Hub – Thủ Đức','Thủ Đức','52 Võ Văn Ngân, Thủ Đức','028 3720 8811','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL),(8,'Badminton Hub – Quận 7','Quận 7','88 Nguyễn Hữu Thọ, Q.7','028 5416 9203','[\"Parking\", \"Shower\", \"Cafe\", \"WiFi\"]',1,'2026-10-09 02:25:34','2026-10-09 02:25:34',180,1380,960,90,50,30,15,NULL);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coaches` WRITE;
/*!40000 ALTER TABLE `coaches` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `commerce_payments` WRITE;
/*!40000 ALTER TABLE `commerce_payments` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `community_posts` WRITE;
/*!40000 ALTER TABLE `community_posts` DISABLE KEYS */;
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
INSERT INTO `content_pages` VALUES (1,'dat-san','Đặt sân và giữ chỗ','faq','Chọn chi nhánh, ngày, khung giờ và sân trống. Kiểm tra tổng tiền trước khi xác nhận. Lịch đặt trực tuyến cần thanh toán phần đặt cọc trong thời hạn hiển thị. Lịch chưa đủ đặt cọc sẽ được tự giải phóng khi hết hạn.',1,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(2,'huy-lich','Hủy lịch và hoàn tiền','faq','Bạn có thể tự hủy lịch chưa thanh toán trước giờ chơi. Lịch thanh toán hoàn toàn bằng ví có thể tự hủy và nhận lại tiền vào ví trước giờ chơi ít nhất 4 tiếng. Với các phương thức khác, gửi yêu cầu hỗ trợ hoặc liên hệ chi nhánh để đối soát và hoàn tiền.',1,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(3,'diem-thuong','Tích điểm và quyền lợi hội viên','faq','Mỗi 10.000đ thanh toán cho buổi chơi đã hoàn tất hoặc mua hàng được tích 1 điểm. Đổi điểm thành tiền trong ví tại mục Quyền lợi. Hạng hội viên được xét theo tổng điểm đã tích; ưu đãi phù hợp được áp dụng khi tính giá sân.',1,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(4,'cong-dong','Chơi cùng nhau, tôn trọng nhau','faq','Đăng bài tìm bạn chơi, tham gia nhóm và trò chuyện. Bài có ngôn từ cần kiểm tra sẽ chờ người quản trị duyệt. Bạn có thể báo cáo nội dung, chặn tài khoản và gỡ bài của mình. Không chia sẻ thông tin riêng của người khác.',1,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(5,'ve-chung-toi','Kết nối sân chơi cầu lông','page','Badminton Hub giúp bạn tìm khung giờ phù hợp, đặt sân và kết nối người chơi. Xem thông tin từng chi nhánh trước khi lựa chọn; quản lý lịch, thanh toán và yêu cầu hỗ trợ trong tài khoản.',1,'2026-10-09 02:25:33','2026-10-09 02:25:33');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `court_blocks` WRITE;
/*!40000 ALTER TABLE `court_blocks` DISABLE KEYS */;
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
INSERT INTO `courts` VALUES (1,1,'Sân 01','active',120000,180000,200000,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(2,1,'Sân 02','active',120000,180000,200000,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(3,1,'Sân 03','active',120000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(4,1,'Sân 04','active',120000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(5,1,'Sân 05','active',120000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(6,1,'Sân 06','active',120000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(7,2,'Sân 01','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(8,2,'Sân 02','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(9,2,'Sân 03','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(10,2,'Sân 04','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(11,2,'Sân 05','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(12,2,'Sân 06','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(13,2,'Sân 07','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(14,2,'Sân 08','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(15,3,'Sân 01','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(16,3,'Sân 02','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(17,3,'Sân 03','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(18,3,'Sân 04','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(19,3,'Sân 05','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(20,3,'Sân 06','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(21,3,'Sân 07','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(22,3,'Sân 08','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(23,3,'Sân 09','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(24,3,'Sân 10','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(25,3,'Sân 11','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(26,3,'Sân 12','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(27,4,'Sân 01','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(28,4,'Sân 02','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(29,4,'Sân 03','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(30,4,'Sân 04','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(31,4,'Sân 05','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(32,4,'Sân 06','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(33,4,'Sân 07','active',110000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(34,5,'Sân 01','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(35,5,'Sân 02','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(36,5,'Sân 03','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(37,5,'Sân 04','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(38,5,'Sân 05','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(39,5,'Sân 06','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(40,5,'Sân 07','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(41,5,'Sân 08','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(42,5,'Sân 09','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(43,5,'Sân 10','active',95000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(44,6,'Sân 01','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(45,6,'Sân 02','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(46,6,'Sân 03','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(47,6,'Sân 04','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(48,6,'Sân 05','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(49,6,'Sân 06','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(50,6,'Sân 07','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(51,6,'Sân 08','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(52,6,'Sân 09','active',100000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(53,7,'Sân 01','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(54,7,'Sân 02','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(55,7,'Sân 03','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(56,7,'Sân 04','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(57,7,'Sân 05','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(58,7,'Sân 06','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(59,7,'Sân 07','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(60,7,'Sân 08','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(61,7,'Sân 09','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(62,7,'Sân 10','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(63,7,'Sân 11','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(64,7,'Sân 12','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(65,7,'Sân 13','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(66,7,'Sân 14','active',90000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(67,8,'Sân 01','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(68,8,'Sân 02','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(69,8,'Sân 03','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(70,8,'Sân 04','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(71,8,'Sân 05','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(72,8,'Sân 06','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(73,8,'Sân 07','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(74,8,'Sân 08','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(75,8,'Sân 09','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(76,8,'Sân 10','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(77,8,'Sân 11','active',105000,180000,200000,'2026-10-09 02:25:34','2026-10-09 02:25:34');
/*!40000 ALTER TABLE `courts` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `event_registrations` WRITE;
/*!40000 ALTER TABLE `event_registrations` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `game_events` WRITE;
/*!40000 ALTER TABLE `game_events` DISABLE KEYS */;
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
INSERT INTO `loyalty_rewards` VALUES (1,'Đổi 100 điểm lấy 10.000đ vào ví',100,10000,1,'2026-10-09 02:25:33','2026-10-09 02:25:33'),(2,'Đổi 500 điểm lấy 60.000đ vào ví',500,60000,1,'2026-10-09 02:25:33','2026-10-09 02:25:33');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `maintenances` WRITE;
/*!40000 ALTER TABLE `maintenances` DISABLE KEYS */;
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
INSERT INTO `membership_plans` VALUES (1,'Hạng Đồng',0,3,365,1,'2026-10-09 02:25:33','2026-10-09 02:25:33',3,100),(2,'Hạng Bạc',0,5,365,1,'2026-10-09 02:25:33','2026-10-09 02:25:33',7,500),(3,'Hạng Vàng',0,8,365,1,'2026-10-09 02:25:33','2026-10-09 02:25:33',14,1500),(4,'Silver',199000,5,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34',0,0),(5,'Gold',399000,10,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34',0,0),(6,'VIP',799000,20,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34',0,0);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `memberships` WRITE;
/*!40000 ALTER TABLE `memberships` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_08_080648_create_badminton_tables',1),(5,'2026_10_08_173715_create_notifications_table',1),(6,'2026_10_08_174754_extend_hub_business_modules',1),(7,'2026_10_08_175737_finish_hub_integrations',1),(8,'2026_10_09_033306_complete_hub_features_and_ai',1),(9,'2026_10_09_090952_add_bank_reconciliation_details',1);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_comments` WRITE;
/*!40000 ALTER TABLE `post_comments` DISABLE KEYS */;
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
INSERT INTO `products` VALUES (1,1,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(2,1,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(3,1,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(4,1,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(5,1,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(6,1,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(7,2,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(8,2,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(9,2,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(10,2,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(11,2,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(12,2,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(13,3,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(14,3,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(15,3,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(16,3,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(17,3,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(18,3,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(19,4,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(20,4,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(21,4,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(22,4,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(23,4,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(24,4,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(25,5,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(26,5,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(27,5,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(28,5,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(29,5,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(30,5,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(31,6,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(32,6,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(33,6,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(34,6,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(35,6,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(36,6,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(37,7,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(38,7,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(39,7,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(40,7,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(41,7,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(42,7,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(43,8,'Aquafina 500ml','WTR-001','Nước uống','💧',10000,48,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(44,8,'Pocari Sweat 500ml','WTR-002','Nước uống','🥤',15000,24,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(45,8,'Yonex AS-30 (12 quả)','SHT-001','Cầu lông','🏸',120000,15,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(46,8,'RSL Tourney (6 quả)','SHT-003','Cầu lông','🏸',55000,30,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(47,8,'Vợt Yonex Astrox 88S','RAC-001','Vợt','🎾',2800000,3,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(48,8,'Grip cuộn Yonex AC102','ACC-001','Phụ kiện','🎀',35000,42,1,'2026-10-09 02:25:34','2026-10-09 02:25:34');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_request_key_unique` (`request_key`),
  UNIQUE KEY `sales_reference_unique` (`reference`),
  KEY `sales_branch_id_foreign` (`branch_id`),
  KEY `sales_clerk_id_foreign` (`clerk_id`),
  KEY `sales_user_id_foreign` (`user_id`),
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
INSERT INTO `services` VALUES (1,1,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(2,1,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(3,1,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(4,1,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(5,1,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(6,1,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(7,1,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(8,1,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(9,2,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(10,2,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(11,2,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(12,2,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(13,2,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(14,2,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(15,2,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(16,2,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(17,3,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(18,3,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(19,3,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(20,3,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(21,3,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(22,3,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(23,3,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(24,3,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(25,4,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(26,4,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(27,4,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(28,4,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(29,4,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(30,4,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(31,4,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(32,4,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(33,5,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(34,5,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(35,5,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(36,5,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(37,5,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(38,5,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(39,5,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(40,5,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(41,6,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(42,6,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(43,6,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(44,6,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(45,6,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(46,6,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(47,6,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(48,6,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(49,7,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(50,7,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(51,7,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(52,7,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(53,7,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(54,7,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(55,7,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(56,7,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(57,8,'Thuê vợt','buổi',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(58,8,'Cầu Yonex AS-30','hộp',120000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(59,8,'Nước suối','chai',10000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(60,8,'Pocari Sweat','chai',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(61,8,'Thuê giày','buổi',25000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(62,8,'Locker','buổi',15000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(63,8,'Shower','lần',30000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34'),(64,8,'Huấn luyện viên','buổi',200000,1,'2026-10-09 02:25:34','2026-10-09 02:25:34');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Quản trị viên','admin@badminton.test',NULL,'$2y$12$fzBoC74a/NqmsaSRRZgH8uaqhzqOcClRNLLU8Fy1UdpSDiNBFJDtO',NULL,'2026-10-09 02:25:34','2026-10-09 02:25:34','admin',NULL,NULL,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(2,'Quản lý Bình Thạnh','manager@badminton.test',NULL,'$2y$12$STwVnWaH9Ct00WOa7zF2LepBWeRJDWVaxV3ZUHA27RqE75QlEE8tu',NULL,'2026-10-09 02:25:34','2026-10-09 02:25:34','manager',NULL,3,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(3,'Nhân viên Bình Thạnh','staff@badminton.test',NULL,'$2y$12$tAJJsKuvk65gE7c.5yMEbuoicIRqmtNKMhLoD.RfikowrcVE2Hvr6',NULL,'2026-10-09 02:25:34','2026-10-09 02:25:34','staff',NULL,3,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(4,'Thu ngân Bình Thạnh','cashier@badminton.test',NULL,'$2y$12$MDqBIemcYEfD0UyPbMZeY.Jl1PpiSFA4Gx7FCTINi5wZPNVZnRDjG',NULL,'2026-10-09 02:25:34','2026-10-09 02:25:34','cashier',NULL,3,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL),(5,'Khách hàng mẫu','customer@badminton.test',NULL,'$2y$12$NS6rPvZkF0czz82wKSJEM.MIeSJbGtQxpXAX.eox3j3rsj4OgvANW',NULL,'2026-10-09 02:25:35','2026-10-09 02:25:35','customer',NULL,NULL,1,0,'Sơ cấp',0,0,0,NULL,NULL,NULL,NULL);
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
