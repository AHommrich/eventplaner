/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drink_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `drink_catalog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `display_name_en` varchar(255) DEFAULT NULL,
  `alcohol_percent` double NOT NULL,
  `is_alcoholic` tinyint(1) NOT NULL,
  `negative_points` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT NULL,
  `search_terms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`search_terms`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drink_catalog_type_unique` (`type`),
  KEY `drink_catalog_category_index` (`category`),
  KEY `drink_catalog_type_index` (`type`),
  KEY `drink_catalog_is_alcoholic_index` (`is_alcoholic`),
  KEY `drink_catalog_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drink_catalog_sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `drink_catalog_sizes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `catalog_id` bigint(20) unsigned NOT NULL,
  `amount_liter` double NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drink_catalog_sizes_catalog_id_amount_liter_unique` (`catalog_id`,`amount_liter`),
  KEY `drink_catalog_sizes_catalog_id_index` (`catalog_id`),
  CONSTRAINT `drink_catalog_sizes_catalog_id_foreign` FOREIGN KEY (`catalog_id`) REFERENCES `drink_catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drink_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `drink_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guest_id` bigint(20) unsigned NOT NULL,
  `drink_id` bigint(20) unsigned NOT NULL,
  `size_id` bigint(20) unsigned DEFAULT NULL,
  `amount_liter` double DEFAULT NULL,
  `base_points` int(11) NOT NULL DEFAULT 0,
  `final_points` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `drink_logs_guest_id_foreign` (`guest_id`),
  KEY `drink_logs_drink_id_foreign` (`drink_id`),
  KEY `drink_logs_size_id_index` (`size_id`),
  CONSTRAINT `drink_logs_drink_id_foreign` FOREIGN KEY (`drink_id`) REFERENCES `drinks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `drink_logs_guest_id_foreign` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `drink_logs_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `drink_catalog_sizes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drinks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `drinks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `drink_catalog_id` bigint(20) unsigned NOT NULL,
  `size_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drinks_event_id_size_id_unique` (`event_id`,`size_id`),
  KEY `drinks_drink_catalog_id_foreign` (`drink_catalog_id`),
  KEY `drinks_size_id_foreign` (`size_id`),
  CONSTRAINT `drinks_drink_catalog_id_foreign` FOREIGN KEY (`drink_catalog_id`) REFERENCES `drink_catalog` (`id`) ON DELETE CASCADE,
  CONSTRAINT `drinks_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `drinks_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `drink_catalog_sizes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_photo_games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_photo_games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `status` enum('draft','active','ended') NOT NULL DEFAULT 'draft',
  `catalog_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_photo_games_event_id_unique` (`event_id`),
  KEY `event_photo_games_catalog_id_foreign` (`catalog_id`),
  CONSTRAINT `event_photo_games_catalog_id_foreign` FOREIGN KEY (`catalog_id`) REFERENCES `photo_game_task_catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_photo_games_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `status` enum('pending','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_requests_user_id_foreign` (`user_id`),
  CONSTRAINT `event_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_style_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_style_presets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `color_primary` varchar(7) DEFAULT NULL,
  `color_secondary` varchar(7) DEFAULT NULL,
  `color_tertiary` varchar(7) DEFAULT NULL,
  `color_home_text` varchar(7) DEFAULT NULL,
  `color_home_shadow` varchar(7) DEFAULT NULL,
  `home_shadow_opacity` tinyint(3) unsigned DEFAULT NULL,
  `role_screen_bg` varchar(20) DEFAULT NULL,
  `role_card_bg` varchar(20) DEFAULT NULL,
  `role_card_text` varchar(20) DEFAULT NULL,
  `role_card_button` varchar(20) DEFAULT NULL,
  `role_card_button_text` varchar(20) DEFAULT NULL,
  `role_tab_tint` varchar(20) DEFAULT NULL,
  `role_border` varchar(20) DEFAULT NULL,
  `role_fab` varchar(20) DEFAULT NULL,
  `role_fab_icon` varchar(20) DEFAULT NULL,
  `font_heading` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_style_presets_event_id_foreign` (`event_id`),
  CONSTRAINT `event_style_presets_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_task_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_task_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `action` enum('hidden','modified','added') NOT NULL,
  `custom_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_task_overrides_event_id_foreign` (`event_id`),
  KEY `event_task_overrides_task_id_foreign` (`task_id`),
  CONSTRAINT `event_task_overrides_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_task_overrides_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `photo_game_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_user` (
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`event_id`,`user_id`),
  KEY `event_user_user_id_foreign` (`user_id`),
  CONSTRAINT `event_user_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `date` datetime DEFAULT NULL,
  `cover_image_url` varchar(255) DEFAULT NULL,
  `cover_image_r2_key` varchar(255) DEFAULT NULL,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_address` varchar(255) DEFAULT NULL,
  `venue_street` varchar(255) DEFAULT NULL,
  `venue_house_number` varchar(20) DEFAULT NULL,
  `venue_postal_code` varchar(20) DEFAULT NULL,
  `venue_city` varchar(255) DEFAULT NULL,
  `venue_state` varchar(255) DEFAULT NULL,
  `venue_country` varchar(100) DEFAULT 'Deutschland',
  `venue_display_mode` varchar(20) NOT NULL DEFAULT 'both',
  `venue_lat` decimal(10,7) DEFAULT NULL,
  `venue_lng` decimal(10,7) DEFAULT NULL,
  `dresscode` text DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `color_primary` varchar(7) DEFAULT NULL,
  `color_secondary` varchar(7) DEFAULT NULL,
  `color_tertiary` varchar(7) DEFAULT NULL,
  `role_screen_bg` varchar(10) DEFAULT NULL,
  `role_card_bg` varchar(10) DEFAULT NULL,
  `role_card_text` varchar(10) DEFAULT NULL,
  `role_card_button` varchar(10) DEFAULT NULL,
  `role_card_button_text` varchar(10) DEFAULT NULL,
  `role_tab_tint` varchar(10) DEFAULT NULL,
  `role_border` varchar(10) DEFAULT NULL,
  `role_fab` varchar(10) DEFAULT NULL,
  `role_fab_icon` varchar(10) DEFAULT NULL,
  `font_heading` varchar(255) DEFAULT NULL,
  `color_home_text` varchar(7) DEFAULT NULL,
  `color_home_shadow` varchar(7) NOT NULL DEFAULT '#000000',
  `home_shadow_opacity` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `drink_game_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `drink_game_end_time` timestamp NULL DEFAULT NULL,
  `photo_game_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `projector_token` varchar(64) DEFAULT NULL,
  `projector_album_id` bigint(20) unsigned DEFAULT NULL,
  `projector_name_mode` varchar(20) NOT NULL DEFAULT 'first',
  `rsvp_deadline` datetime DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_slug_unique` (`slug`),
  UNIQUE KEY `events_projector_token_unique` (`projector_token`),
  KEY `events_user_id_foreign` (`user_id`),
  KEY `events_projector_album_id_foreign` (`projector_album_id`),
  CONSTRAINT `events_projector_album_id_foreign` FOREIGN KEY (`projector_album_id`) REFERENCES `photo_albums` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `food_specials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `food_specials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `translation_key` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `groups_event_id_foreign` (`event_id`),
  CONSTRAINT `groups_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guest_food_special`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `guest_food_special` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guest_id` bigint(20) unsigned NOT NULL,
  `food_special_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guest_food_special_guest_id_foreign` (`guest_id`),
  KEY `guest_food_special_food_special_id_foreign` (`food_special_id`),
  CONSTRAINT `guest_food_special_food_special_id_foreign` FOREIGN KEY (`food_special_id`) REFERENCES `food_specials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guest_food_special_guest_id_foreign` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `guests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `rsvp_status` enum('accepted_pending','accepted','declined_pending','declined','revocation_requested') DEFAULT NULL,
  `rsvp_set_by_guest_id` bigint(20) unsigned DEFAULT NULL,
  `rsvp_set_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `rsvp_set_at` timestamp NULL DEFAULT NULL,
  `app_access` tinyint(1) NOT NULL DEFAULT 1,
  `drinks_access` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guests_family_id_foreign` (`group_id`),
  KEY `guests_event_id_foreign` (`event_id`),
  KEY `guests_rsvp_set_by_guest_id_foreign` (`rsvp_set_by_guest_id`),
  KEY `guests_rsvp_set_by_user_id_foreign` (`rsvp_set_by_user_id`),
  CONSTRAINT `guests_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `guests_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `guests_rsvp_set_by_guest_id_foreign` FOREIGN KEY (`rsvp_set_by_guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `guests_rsvp_set_by_user_id_foreign` FOREIGN KEY (`rsvp_set_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invitation_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invitation_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `guest_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `group_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitation_tokens_token_unique` (`token`),
  KEY `invitation_tokens_guest_id_foreign` (`guest_id`),
  KEY `invitation_tokens_group_id_foreign` (`group_id`),
  CONSTRAINT `invitation_tokens_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invitation_tokens_guest_id_foreign` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photo_albums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photo_albums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `slug` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `photo_albums_event_id_slug_unique` (`event_id`,`slug`),
  CONSTRAINT `photo_albums_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photo_game_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photo_game_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `guest_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `override_id` bigint(20) unsigned DEFAULT NULL,
  `photo_id` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `photo_game_assignments_game_id_guest_id_unique` (`game_id`,`guest_id`),
  KEY `photo_game_assignments_guest_id_foreign` (`guest_id`),
  KEY `photo_game_assignments_task_id_foreign` (`task_id`),
  KEY `photo_game_assignments_photo_id_foreign` (`photo_id`),
  KEY `photo_game_assignments_override_id_foreign` (`override_id`),
  CONSTRAINT `photo_game_assignments_game_id_foreign` FOREIGN KEY (`game_id`) REFERENCES `event_photo_games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `photo_game_assignments_guest_id_foreign` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `photo_game_assignments_override_id_foreign` FOREIGN KEY (`override_id`) REFERENCES `event_task_overrides` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photo_game_assignments_photo_id_foreign` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photo_game_assignments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `photo_game_tasks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photo_game_task_catalogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photo_game_task_catalogs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `event_type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photo_game_task_catalogs_event_id_foreign` (`event_id`),
  CONSTRAINT `photo_game_task_catalogs_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photo_game_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photo_game_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `catalog_id` bigint(20) unsigned NOT NULL,
  `description` text NOT NULL,
  `description_en` varchar(255) DEFAULT NULL,
  `translation_key` varchar(255) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photo_game_tasks_catalog_id_foreign` (`catalog_id`),
  CONSTRAINT `photo_game_tasks_catalog_id_foreign` FOREIGN KEY (`catalog_id`) REFERENCES `photo_game_task_catalogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `guest_id` bigint(20) unsigned DEFAULT NULL,
  `uploaded_by` varchar(255) DEFAULT NULL,
  `uploader_user_id` bigint(20) unsigned DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `r2_key` varchar(500) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `album_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photos_guest_id_foreign` (`guest_id`),
  KEY `photos_event_id_foreign` (`event_id`),
  KEY `photos_album_id_foreign` (`album_id`),
  KEY `photos_uploader_user_id_foreign` (`uploader_user_id`),
  CONSTRAINT `photos_album_id_foreign` FOREIGN KEY (`album_id`) REFERENCES `photo_albums` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_guest_id_foreign` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_uploader_user_id_foreign` FOREIGN KEY (`uploader_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `privacy_accepted_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'user',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_07_22_211822_create_badges_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_07_22_211831_create_guests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2025_07_22_220238_create_families_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2025_07_25_184053_create_guest_drinks_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_07_25_195642_add_likelihood_to_guests_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_07_29_194945_create_food_specials_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_07_29_195024_create_guest_food_special_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_07_29_195054_add_invite_to_guests_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_15_173056_create_personal_access_tokens_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_15_173100_create_invitation_tokens_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_15_200000_create_photos_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_16_091601_add_role_to_users_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_16_091602_create_events_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_16_091603_create_event_user_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_16_091604_rename_badges_to_categories',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_03_16_091605_rename_families_to_groups_and_add_event_id',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_03_16_091606_update_guests_table_for_event_groups_categories',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_03_16_091607_add_event_id_to_photos_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_03_16_091608_update_invitation_tokens_rename_family_to_group',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_03_16_120000_seed_admin_user',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_03_16_130000_seed_default_event_and_backfill',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_03_16_140000_create_drinks_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_03_16_150000_cleanup_guest_drinks',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_03_16_160000_add_rsvp_deadline_to_events_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_03_16_160001_add_rsvp_fields_to_guests_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_03_16_170000_update_rsvp_status_enum_on_guests_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_03_16_180000_add_revocation_requested_to_rsvp_status',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_03_16_190000_add_settings_to_events_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_03_16_200000_add_color_home_text_to_events_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_03_16_210000_add_app_access_to_guests_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_03_16_220000_create_drink_logs_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_03_16_230000_add_drinks_access_to_guests_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_03_18_000001_add_points_size_ml_to_drinks_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_03_18_000002_add_game_enabled_to_events_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_03_18_000003_create_drink_catalog_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_03_18_000004_update_drinks_table_for_catalog',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_18_000005_add_game_columns_to_drink_logs_and_events',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_18_000006_add_theme_colors_to_events_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_19_000001_add_color_card_text_and_tab_tint_to_events_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_03_19_000002_add_color_card_button_to_events_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_19_000003_add_color_card_button_text_to_events_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_03_19_000005_add_font_heading_to_events_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_03_19_000010_refactor_color_system_to_palette_and_roles',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_03_19_000011_add_role_fab_icon_to_events_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_03_20_000001_add_venue_coordinates_to_events',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_03_20_000002_add_structured_address_to_events',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_03_20_000001_add_venue_display_mode_to_events',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_03_20_000002_add_home_shadow_to_events',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_03_23_000001_add_is_approved_to_users',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_03_23_000002_create_event_requests_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_03_23_000003_remove_legacy_guest_fields',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_03_24_000001_create_photo_albums_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_03_24_000002_add_album_and_r2key_to_photos_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_03_24_000003_add_projector_fields_to_events_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_03_24_000004_seed_photo_albums_and_backfill',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_03_24_000005_rename_party_album_to_presentation',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_03_26_000001_add_photo_game_enabled_to_events',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_03_26_000002_create_photo_game_task_catalogs_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_03_26_000003_create_photo_game_tasks_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_03_26_000004_create_event_photo_games_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_03_26_000005_create_photo_game_assignments_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_03_26_000006_seed_wedding_task_catalog',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_03_26_000007_seed_additional_task_catalogs',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_03_27_000001_delta_model_catalog_schema',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_03_27_000002_create_event_task_overrides_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_03_27_000003_update_photo_game_assignments_for_delta',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_03_27_000004_reseed_global_task_catalogs',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_03_27_000005_add_description_to_photos',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_03_27_000006_add_projector_name_mode_to_events',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_03_27_000007_add_uploader_user_id_to_photos',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_03_29_000001_refactor_drink_catalog_to_type_only',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_03_29_000002_add_size_id_to_drink_logs',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_03_29_000003_add_size_id_to_drinks',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_03_30_000001_add_calculator_settings_to_events',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_03_30_000002_add_calculator_enabled_to_events',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_03_31_000001_add_translation_key_to_photo_game_tasks',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_03_31_000002_add_translation_key_to_food_specials_and_seed_catalog',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_03_31_000003_create_missing_default_albums_for_events',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_01_000001_create_event_style_presets_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_01_000001_add_display_name_en_to_drink_catalog',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_01_000002_add_description_en_to_photo_game_tasks',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_04_02_000001_backfill_projector_token_for_existing_events',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_06_28_201013_drop_calculator_fields_from_events',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_07_01_000001_add_privacy_accepted_at_to_users',46);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
