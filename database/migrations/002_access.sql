-- =============================================================================
-- Migration 002 : accès et visites guidées
-- Tables : guided_links, login_attempts, plus la clé étrangère visits.guided_id
--
-- Mêmes règles que la migration 001 (SQL simple, importable par phpMyAdmin 4.9).
-- Les jetons ne sont jamais stockés en clair : seul leur SHA-256 (64 caractères hexa) l'est.
-- =============================================================================

-- Visites guidées : mode « device » (cet appareil est étiqueté jusqu'à expiration)
-- ou « link » (lien personnel à jeton envoyé à un invité). Révocable depuis l'admin.
CREATE TABLE IF NOT EXISTS guided_links (
  guided_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mode ENUM('device','link') NOT NULL,
  label VARCHAR(100) NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL DEFAULT NULL,
  first_used_at DATETIME NULL DEFAULT NULL,
  last_used_at DATETIME NULL DEFAULT NULL,
  uses INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (guided_id),
  UNIQUE KEY uq_guided_token (token_hash),
  KEY idx_guided_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une visite guidée supprimée ne supprime pas ses visites : elles perdent seulement leur libellé
ALTER TABLE visits ADD CONSTRAINT fk_visits_guided FOREIGN KEY (guided_id) REFERENCES guided_links (guided_id) ON DELETE SET NULL;

-- Tentatives de connexion à l'espace admin. Jamais d'adresse IP : visit_key est la clé de visite du jour,
-- remise à NULL à minuit comme celle de la table visits (les lignes restent 30 jours pour les statistiques d'échecs).
CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempted_at DATETIME NOT NULL,
  visit_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  succeeded TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (attempt_id),
  KEY idx_login_key (visit_key, attempted_at),
  KEY idx_login_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doit rester la dernière instruction du fichier
INSERT INTO schema_migrations (version, description, applied_at) VALUES ('002', 'Acces : guided_links, login_attempts, cle etrangere visits.guided_id', UTC_TIMESTAMP());
