-- =====================================================================
-- Dolibarr module: timesheetweek
-- SQL install file: create tables + constraints
-- =====================================================================

-- ---------------------------------------------------------------------
-- Table: llx_timesheet_week  (feuille hebdo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `llx_timesheet_week` (
	`rowid`           INT AUTO_INCREMENT PRIMARY KEY,
	`ref`             VARCHAR(50) NOT NULL UNIQUE,
	`fk_user`         INT NOT NULL,
	`year`            SMALLINT NOT NULL,
	`week`            SMALLINT NOT NULL,
	`status`          SMALLINT NOT NULL DEFAULT 0,
	`note`            TEXT,
	`date_creation`   DATETIME DEFAULT CURRENT_TIMESTAMP,
	`date_validation` DATETIME DEFAULT NULL,
	`fk_user_valid`   INT DEFAULT NULL,
	`total_hours`     DOUBLE(24,8) NOT NULL DEFAULT 0,
	`overtime_hours`  DOUBLE(24,8) NOT NULL DEFAULT 0,
	`tms`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

	-- Un salarié ne peut avoir qu'une feuille par année/semaine
	UNIQUE KEY `uk_timesheet_week_user_year_week` (`fk_user`,`year`,`week`),

	-- Index de confort
	KEY `idx_timesheet_week_user` (`fk_user`),
	KEY `idx_timesheet_week_valid` (`fk_user_valid`),
	KEY `idx_timesheet_week_year`  (`year`),
	KEY `idx_timesheet_week_week`  (`week`),
	KEY `idx_timesheet_week_status` (`status`),

	-- Contraintes
	CONSTRAINT `fk_timesheet_week_user`
		FOREIGN KEY (`fk_user`) REFERENCES `llx_user` (`rowid`)
		ON UPDATE CASCADE ON DELETE RESTRICT,

	CONSTRAINT `fk_timesheet_week_user_valid`
		FOREIGN KEY (`fk_user_valid`) REFERENCES `llx_user` (`rowid`)
		ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=innodb;

-- ---------------------------------------------------------------------
-- Table: llx_timesheet_week_line  (détail par jour & tâche)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `llx_timesheet_week_line` (
	`rowid`              INT AUTO_INCREMENT PRIMARY KEY,
	`fk_timesheet_week`  INT NOT NULL,
	`fk_task`            INT NOT NULL,
	`day_date`           DATE NOT NULL,
	`hours`              DOUBLE(24,8) NOT NULL DEFAULT 0,
	`zone`               SMALLINT NOT NULL DEFAULT 1,   -- 1..5
	`meal`               SMALLINT NOT NULL DEFAULT 0,   -- 0/1
	`tms`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

	-- Empêcher doublons de saisie sur même (feuille, tâche, jour)
	UNIQUE KEY `uk_timesheet_week_line_unique` (`fk_timesheet_week`,`fk_task`,`day_date`),

	-- Index de confort
	KEY `idx_timesheet_week_line_week` (`fk_timesheet_week`),
	KEY `idx_timesheet_week_line_task` (`fk_task`),
	KEY `idx_timesheet_week_line_day`  (`day_date`),

	-- Contraintes
	CONSTRAINT `fk_timesheet_week_line_week`
		FOREIGN KEY (`fk_timesheet_week`) REFERENCES `llx_timesheet_week` (`rowid`)
		ON UPDATE CASCADE ON DELETE CASCADE,

	CONSTRAINT `fk_timesheet_week_line_task`
		FOREIGN KEY (`fk_task`) REFERENCES `llx_projet_task` (`rowid`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb;

-- =====================================================================
-- UPGRADE SECTION (si vous partez d'un schéma plus ancien)
-- Exécutez ces ALTER uniquement si les colonnes n'existent pas déjà.
-- =====================================================================

-- 1) Colonnes total_hours et overtime_hours sur la table de feuille
-- (Ajoute les colonnes si elles n'existent pas)
ALTER TABLE `llx_timesheet_week`
	ADD COLUMN IF NOT EXISTS `total_hours`    DOUBLE(24,8) NOT NULL DEFAULT 0,
	ADD COLUMN IF NOT EXISTS `overtime_hours` DOUBLE(24,8) NOT NULL DEFAULT 0;

-- 2) Contrainte d'unicité feuille par salarié/semaine (si manquante)
ALTER TABLE `llx_timesheet_week`
	ADD CONSTRAINT `uk_timesheet_week_user_year_week`
	UNIQUE (`fk_user`,`year`,`week`);

-- 3) Table des lignes si elle n'existait pas (anciennes versions)
CREATE TABLE IF NOT EXISTS `llx_timesheet_week_line` (
	`rowid`              INT AUTO_INCREMENT PRIMARY KEY,
	`fk_timesheet_week`  INT NOT NULL,
	`fk_task`            INT NOT NULL,
	`day_date`           DATE NOT NULL,
	`hours`              DOUBLE(24,8) NOT NULL DEFAULT 0,
	`zone`               SMALLINT NOT NULL DEFAULT 1,
	`meal`               SMALLINT NOT NULL DEFAULT 0,
	`tms`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY `uk_timesheet_week_line_unique` (`fk_timesheet_week`,`fk_task`,`day_date`),
	KEY `idx_timesheet_week_line_week` (`fk_timesheet_week`),
	KEY `idx_timesheet_week_line_task` (`fk_task`),
	KEY `idx_timesheet_week_line_day`  (`day_date`),
	CONSTRAINT `fk_timesheet_week_line_week`
		FOREIGN KEY (`fk_timesheet_week`) REFERENCES `llx_timesheet_week` (`rowid`)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `fk_timesheet_week_line_task`
		FOREIGN KEY (`fk_task`) REFERENCES `llx_projet_task` (`rowid`)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=innodb;
