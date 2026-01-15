-- TimesheetWeek - main table
CREATE TABLE IF NOT EXISTS llx_timesheet_week (
	rowid INT AUTO_INCREMENT PRIMARY KEY,
	entity INT NOT NULL DEFAULT 1,
	ref VARCHAR(50) NOT NULL,
	fk_user INT NOT NULL,
	year SMALLINT NOT NULL,
	week SMALLINT NOT NULL,
	status SMALLINT NOT NULL DEFAULT 0,
	model_pdf VARCHAR(255) DEFAULT NULL,
	-- EN: Stores the preferred PDF model per sheet / FR: Stocke le modèle PDF préféré par feuille
	note TEXT,
	datec DATETIME DEFAULT CURRENT_TIMESTAMP,
	date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
	datev DATETIME DEFAULT NULL,
	date_validation DATETIME DEFAULT NULL,
	fk_user_author INT DEFAULT NULL,
	fk_user_valid INT DEFAULT NULL,
	total_hours DOUBLE(24,8) NOT NULL DEFAULT 0,
	overtime_hours DOUBLE(24,8) NOT NULL DEFAULT 0,
	contract DOUBLE(24,8) DEFAULT NULL,
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

	CONSTRAINT fk_timesheet_week_user_author
		FOREIGN KEY (fk_user_author) REFERENCES llx_user (rowid),
	CONSTRAINT fk_timesheet_week_user_valid
		FOREIGN KEY (fk_user_valid) REFERENCES llx_user (rowid)
) ENGINE=innodb;


-- EN: Insert default weekly reminder email template when full email columns exist with code
INSERT INTO llx_c_email_templates (
entity,
private,
module,
type_template,
label,
lang,
position,
active,
enabled,
joinfiles,
email_from,
email_to,
email_tocc,
email_tobcc,
topic,
content
)
SELECT
0,
0,
'timesheetweek',
'timesheetweek',
'TIMESHEETWEEK_REMINDER',
'fr_FR',
0,
1,
1,
0,
'',
'',
'',
'',
'Rappel d''envoi des feuilles d''heures',
'Bonjour __TSW_USER_FIRSTNAME__,\\nMerci de soumettre votre feuille d''heures de la semaine pour lundi 8h.\\n__TSW_TIMESHEET_NEW_URL__\\nBon weekend, __TSW_DOLIBARR_TITLE__'
WHERE EXISTS (
SELECT 1
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'llx_c_email_templates'
AND COLUMN_NAME IN ('email_tocc', 'email_tobcc', 'email_from', 'email_to', 'joinfiles')
GROUP BY TABLE_NAME
HAVING COUNT(DISTINCT COLUMN_NAME) = 6
)
AND NOT EXISTS (
SELECT 1
FROM llx_c_email_templates
WHERE module = 'timesheetweek'
AND entity IN (0, 1)
AND label = 'TIMESHEETWEEK_REMINDER'
);

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
