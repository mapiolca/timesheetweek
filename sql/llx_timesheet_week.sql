-- TimesheetWeek - main table
CREATE TABLE IF NOT EXISTS llx_timesheet_week (
	rowid INT AUTO_INCREMENT PRIMARY KEY,
	entity INT NOT NULL DEFAULT 1,
	ref VARCHAR(50) NOT NULL,
	fk_user INT NOT NULL,
	year SMALLINT NOT NULL,
	week SMALLINT NOT NULL,
	status SMALLINT NOT NULL DEFAULT 0,
	note TEXT,
	date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
	date_validation DATETIME DEFAULT NULL,
	fk_user_valid INT DEFAULT NULL,
	total_hours DOUBLE(24,8) NOT NULL DEFAULT 0,
	overtime_hours DOUBLE(24,8) NOT NULL DEFAULT 0,
	zone1_count SMALLINT NOT NULL DEFAULT 0,
	zone2_count SMALLINT NOT NULL DEFAULT 0,
	zone3_count SMALLINT NOT NULL DEFAULT 0,
	zone4_count SMALLINT NOT NULL DEFAULT 0,
	zone5_count SMALLINT NOT NULL DEFAULT 0,
	meal_count SMALLINT NOT NULL DEFAULT 0,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

	-- Unicité par entité
	UNIQUE KEY uk_timesheet_week_ref (entity, ref),
	-- 1 feuille par utilisateur et semaine (par entité)
	UNIQUE KEY uk_timesheet_week_user_week (entity, fk_user, year, week),

	KEY idx_timesheet_week_entity (entity),
	KEY idx_timesheet_week_user (fk_user),
	KEY idx_timesheet_week_user_valid (fk_user_valid),
	KEY idx_timesheet_week_yearweek (year, week),

	CONSTRAINT fk_timesheet_week_user
		FOREIGN KEY (fk_user) REFERENCES llx_user (rowid),

	CONSTRAINT fk_timesheet_week_user_valid
		FOREIGN KEY (fk_user_valid) REFERENCES llx_user (rowid)
) ENGINE=innodb;

-- TimesheetWeek - lines
CREATE TABLE IF NOT EXISTS llx_timesheet_week_line (
	rowid INT AUTO_INCREMENT PRIMARY KEY,
	entity INT NOT NULL DEFAULT 1,
	fk_timesheet_week INT NOT NULL,
	fk_task INT NOT NULL,
day_date DATE NOT NULL,
hours DOUBLE(24,8) NOT NULL DEFAULT 0,
daily_rate INT NOT NULL DEFAULT 0,
zone SMALLINT NOT NULL DEFAULT 0,
meal TINYINT NOT NULL DEFAULT 0,
	tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

	-- Empêche les doublons de saisie pour la même tâche et le même jour dans une même feuille
	UNIQUE KEY uk_timesheet_week_line (fk_timesheet_week, fk_task, day_date),

	KEY idx_timesheet_week_line_entity (entity),
	KEY idx_timesheet_week_line_task (fk_task),
	KEY idx_timesheet_week_line_day (day_date),

	CONSTRAINT fk_timesheet_week_line_timesheet
		FOREIGN KEY (fk_timesheet_week) REFERENCES llx_timesheet_week (rowid) ON DELETE CASCADE,

	CONSTRAINT fk_timesheet_week_line_task
		FOREIGN KEY (fk_task) REFERENCES llx_projet_task (rowid) ON DELETE CASCADE
) ENGINE=innodb;
