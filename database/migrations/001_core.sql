-- =============================================================================
-- Migration 001 : socle de la collecte du dashboard
-- Tables : schema_migrations, settings, visits, hits, daily_stats, errors
--
-- Cible : MariaDB 11.8 (IONOS) et MySQL 8 (Laragon). SQL volontairement simple :
-- pas de DELIMITER, pas de procédure stockée, pas de commentaire contenant un
-- point-virgule, pour rester importable tel quel par phpMyAdmin 4.9 (jeu de
-- caractères du fichier : utf8).
--
-- Règles de vie privée (docs/dashboard-vie-privee.md) :
--  * aucune colonne ne reçoit d'adresse IP, jamais
--  * visits.visit_key est le seul pseudonyme : elle est remise à NULL à minuit
--  * les dates et heures sont en UTC, sauf visit_day, stat_date et error_day (jour Europe/Paris)
-- =============================================================================

-- Versions de migration déjà appliquées (renseignée par la dernière instruction de chaque fichier)
CREATE TABLE IF NOT EXISTS schema_migrations (
  version CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  description VARCHAR(200) NOT NULL,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages et état interne (dernier jour de rotation, dernière purge, options de rapport...)
CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  value VARCHAR(1000) NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par visite : pages successives d'un même visiteur le même jour, moins de 30 minutes entre deux pages.
-- visit_key reste vide (NULL) pour les visiteurs qui envoient DNT ou Sec-GPC : leurs pages ne sont comptées que dans daily_stats.
CREATE TABLE IF NOT EXISTS visits (
  visit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  visit_day DATE NOT NULL,
  profile ENUM('human','bot','test','developer','guided') NOT NULL,
  guided_id INT UNSIGNED NULL DEFAULT NULL,
  started_at DATETIME NOT NULL,
  last_hit_at DATETIME NOT NULL,
  hit_count INT UNSIGNED NOT NULL DEFAULT 1,
  device_type ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  browser VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  os VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  bot_agent VARCHAR(255) NULL DEFAULT NULL,
  referrer_domain VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  utm_source VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  utm_medium VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  utm_campaign VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  PRIMARY KEY (visit_id),
  KEY idx_visits_key (visit_key, last_hit_at),
  KEY idx_visits_day (visit_day, profile),
  KEY idx_visits_guided (guided_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcours détaillé : une ligne par page vue rattachée à une visite (supprimée avec sa visite)
CREATE TABLE IF NOT EXISTS hits (
  hit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_id BIGINT UNSIGNED NOT NULL,
  hit_at DATETIME NOT NULL,
  page VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  lang CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status SMALLINT UNSIGNED NOT NULL,
  creation_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  PRIMARY KEY (hit_id),
  KEY idx_hits_visit (visit_id, hit_at),
  KEY idx_hits_at (hit_at),
  KEY idx_hits_page (page, hit_at),
  CONSTRAINT fk_hits_visit FOREIGN KEY (visit_id) REFERENCES visits (visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compteur de TOUTES les pages vues, par jour (Europe/Paris), y compris celles sans visite (DNT)
-- et celles dont le détail a été purgé : c'est la source des compteurs, hits ne sert qu'aux parcours.
CREATE TABLE IF NOT EXISTS daily_stats (
  stat_date DATE NOT NULL,
  profile ENUM('human','bot','test','developer','guided') NOT NULL,
  page VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  lang CHAR(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status SMALLINT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (stat_date, profile, page, lang, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Erreurs regroupées : une seule ligne par jour, type et sujet (chemin d'une 404, fichier:ligne d'une erreur PHP...)
-- Un scanner qui demande mille fois la même adresse ne produit donc qu'une ligne.
CREATE TABLE IF NOT EXISTS errors (
  error_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  error_day DATE NOT NULL,
  kind ENUM('not_found','server_error','php','database','security') NOT NULL,
  subject VARCHAR(255) NOT NULL DEFAULT '',
  message VARCHAR(500) NOT NULL DEFAULT '',
  occurrences INT UNSIGNED NOT NULL DEFAULT 1,
  first_at DATETIME NOT NULL,
  last_at DATETIME NOT NULL,
  PRIMARY KEY (error_id),
  UNIQUE KEY uq_errors_day_kind_subject (error_day, kind, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doit rester la dernière instruction du fichier
INSERT INTO schema_migrations (version, description, applied_at) VALUES ('001', 'Socle de la collecte : visits, hits, daily_stats, errors, settings', UTC_TIMESTAMP());
