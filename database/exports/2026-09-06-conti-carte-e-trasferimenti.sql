-- ---------------------------------------------------------------------------
-- ManageMe - aggiornamento database del 06/09/2026
-- Conti e carte collegati al budget mensile, trasferimenti tra conti e
-- condivisione del budget.
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
-- 1. Budget condiviso: chi altro vede e modifica il budget di una persona
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `budget_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint unsigned NOT NULL,
  `member_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `budget_members_owner_user_id_member_user_id_unique` (`owner_user_id`,`member_user_id`),
  KEY `budget_members_member_user_id_foreign` (`member_user_id`),
  CONSTRAINT `budget_members_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_members_member_user_id_foreign` FOREIGN KEY (`member_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. I conti: IBAN e intestatario
-- ---------------------------------------------------------------------------

ALTER TABLE `financial_accounts`
  ADD COLUMN `iban` varchar(34) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `bank_name`,
  ADD COLUMN `holder_name` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `iban`;

-- ---------------------------------------------------------------------------
-- 3. Da quale conto e' passato un movimento del budget
--
-- Facoltativo: nullo vuol dire "non indicato". I movimenti registrati prima di
-- questo aggiornamento restano com'erano.
-- ---------------------------------------------------------------------------

ALTER TABLE `budget_expenses`
  ADD COLUMN `financial_account_id` bigint unsigned NULL DEFAULT NULL AFTER `budget_subcategory_id`,
  ADD KEY `budget_expenses_financial_account_id_foreign` (`financial_account_id`),
  ADD CONSTRAINT `budget_expenses_financial_account_id_foreign` FOREIGN KEY (`financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 4. Trasferimenti tra conti
--
-- ATTENZIONE: senza questa tabella la pagina del budget mensile va in errore,
-- perche' e' l'unica che la interroga a ogni caricamento.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `account_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `from_financial_account_id` bigint unsigned NULL DEFAULT NULL,
  `to_financial_account_id` bigint unsigned NULL DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `transferred_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_transfers_user_id_transferred_at_index` (`user_id`,`transferred_at`),
  KEY `account_transfers_from_financial_account_id_foreign` (`from_financial_account_id`),
  KEY `account_transfers_to_financial_account_id_foreign` (`to_financial_account_id`),
  CONSTRAINT `account_transfers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_transfers_from_financial_account_id_foreign` FOREIGN KEY (`from_financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_transfers_to_financial_account_id_foreign` FOREIGN KEY (`to_financial_account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Il conto sta fuori dalle statistiche
-- ---------------------------------------------------------------------------

ALTER TABLE `financial_accounts`
  ADD COLUMN `hidden_from_stats` tinyint(1) NOT NULL DEFAULT '0' AFTER `is_active`;

-- ---------------------------------------------------------------------------
-- 6. Registro delle migrazioni
-- ---------------------------------------------------------------------------

SET @batch := (SELECT IFNULL(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT * FROM (
  SELECT '2026_09_01_090000_create_budget_members_table' AS `migration`, @batch AS `batch`
  UNION ALL SELECT '2026_09_04_090000_add_iban_and_holder_to_financial_accounts_table', @batch
  UNION ALL SELECT '2026_09_04_090100_add_financial_account_to_budget_expenses_table', @batch
  UNION ALL SELECT '2026_09_04_100000_create_account_transfers_table', @batch
  UNION ALL SELECT '2026_09_06_090000_add_hidden_from_stats_to_financial_accounts_table', @batch
) AS `nuove`
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` AS `m` WHERE `m`.`migration` = `nuove`.`migration`
);
