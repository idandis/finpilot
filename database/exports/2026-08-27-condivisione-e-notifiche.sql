-- ---------------------------------------------------------------------------
-- ManageMe - aggiornamento database del 27/08/2026
-- Condivisione (liste della spesa, board dei task, pianificazione pasti),
-- assegnatari e notifiche.
--
-- Contiene SOLO struttura: nessuna riga esistente viene toccata o cancellata.
-- Da incollare in phpMyAdmin > database > SQL, oppure da importare come file.
--
-- Le istruzioni sono in ordine di dipendenza: eseguile tutte insieme, dall'alto
-- verso il basso. Se una tabella o una colonna esiste gia', MySQL segnala
-- l'errore e basta saltare quel blocco (nulla viene perso).
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- 1. Board dei task e le persone che ci lavorano
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `task_boards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_boards_user_id_position_index` (`user_id`,`position`),
  CONSTRAINT `task_boards_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_board_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_board_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_board_members_task_board_id_user_id_unique` (`task_board_id`,`user_id`),
  KEY `task_board_members_user_id_foreign` (`user_id`),
  CONSTRAINT `task_board_members_task_board_id_foreign` FOREIGN KEY (`task_board_id`) REFERENCES `task_boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_board_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. I task: possono stare su una board (e quindi senza data) ed essere
--    assegnati a una persona della board
-- ---------------------------------------------------------------------------

-- I task di una board non hanno un giorno: la colonna deve poter essere NULL.
ALTER TABLE `tasks`
  MODIFY `task_date` date NULL DEFAULT NULL;

ALTER TABLE `tasks`
  ADD COLUMN `task_board_id` bigint unsigned NULL DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `assigned_to_user_id` bigint unsigned NULL DEFAULT NULL AFTER `task_board_id`,
  ADD KEY `tasks_user_id_task_board_id_status_index` (`user_id`,`task_board_id`,`status`),
  ADD KEY `tasks_task_board_id_foreign` (`task_board_id`),
  ADD KEY `tasks_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  ADD CONSTRAINT `tasks_task_board_id_foreign` FOREIGN KEY (`task_board_id`) REFERENCES `task_boards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 3. Liste della spesa condivise
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `shopping_list_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shopping_list_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shopping_list_members_shopping_list_id_user_id_unique` (`shopping_list_id`,`user_id`),
  KEY `shopping_list_members_user_id_foreign` (`user_id`),
  CONSTRAINT `shopping_list_members_shopping_list_id_foreign` FOREIGN KEY (`shopping_list_id`) REFERENCES `shopping_lists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shopping_list_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Pianificazione pasti condivisa + chi cucina
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `meal_plan_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint unsigned NOT NULL,
  `member_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meal_plan_members_owner_user_id_member_user_id_unique` (`owner_user_id`,`member_user_id`),
  KEY `meal_plan_members_member_user_id_foreign` (`member_user_id`),
  CONSTRAINT `meal_plan_members_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meal_plan_members_member_user_id_foreign` FOREIGN KEY (`member_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `meals`
  ADD COLUMN `assigned_to_user_id` bigint unsigned NULL DEFAULT NULL AFTER `user_id`,
  ADD KEY `meals_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  ADD CONSTRAINT `meals_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 5. Notifiche (la campanella in sidebar e le mail di invito)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_notifiable_type_notifiable_id_read_at_index` (`notifiable_type`,`notifiable_id`,`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. Registro delle migrazioni
--
-- Segna le migrazioni come gia' eseguite, cosi' un eventuale `php artisan
-- migrate` non prova a rifare quello che hai appena applicato a mano.
-- ---------------------------------------------------------------------------

SET @batch := (SELECT IFNULL(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_08_26_090000_create_task_boards_table', @batch),
  ('2026_08_26_090100_add_task_board_to_tasks_table', @batch),
  ('2026_08_26_100000_create_task_board_members_table', @batch),
  ('2026_08_26_100100_add_assignee_to_tasks_table', @batch),
  ('2026_08_27_090000_create_shopping_list_members_table', @batch),
  ('2026_08_27_100000_create_meal_plan_members_table', @batch),
  ('2026_08_27_100100_add_cook_to_meals_table', @batch),
  ('2026_08_27_120000_create_notifications_table', @batch);
