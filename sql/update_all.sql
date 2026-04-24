-- EN: Add the PDF model column to existing tables when missing.
SET @tsw_has_model_pdf := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'model_pdf'
);
SET @tsw_add_model_pdf := IF(
	@tsw_has_model_pdf = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN model_pdf VARCHAR(255) DEFAULT NULL AFTER status',
	'SELECT 1'
);
PREPARE tsw_stmt FROM @tsw_add_model_pdf;
EXECUTE tsw_stmt;
DEALLOCATE PREPARE tsw_stmt;

-- EN: Add the contract hours column to existing tables when missing.
SET @tsw_has_contract := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = 'llx_timesheet_week'
			AND COLUMN_NAME = 'contract'
);
SET @tsw_add_contract := IF(
	@tsw_has_contract = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN contract DOUBLE(24,8) DEFAULT NULL AFTER overtime_hours',
	'SELECT 1'
);
PREPARE tsw_contract_stmt FROM @tsw_add_contract;
EXECUTE tsw_contract_stmt;
DEALLOCATE PREPARE tsw_contract_stmt;

-- EN: Add the seal user column to existing tables when missing.
SET @tsw_has_fk_user_seal := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'fk_user_seal'
);
SET @tsw_add_fk_user_seal := IF(
	@tsw_has_fk_user_seal = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN fk_user_seal INT DEFAULT NULL AFTER fk_user_valid',
	'SELECT 1'
);
PREPARE tsw_fk_user_seal_stmt FROM @tsw_add_fk_user_seal;
EXECUTE tsw_fk_user_seal_stmt;
DEALLOCATE PREPARE tsw_fk_user_seal_stmt;

-- EN: Add the seal date column to existing tables when missing.
SET @tsw_has_date_seal := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'date_seal'
);
SET @tsw_add_date_seal := IF(
	@tsw_has_date_seal = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN date_seal DATETIME DEFAULT NULL AFTER fk_user_seal',
	'SELECT 1'
);
PREPARE tsw_date_seal_stmt FROM @tsw_add_date_seal;
EXECUTE tsw_date_seal_stmt;
DEALLOCATE PREPARE tsw_date_seal_stmt;

-- EN: Add index on seal user when missing.
SET @tsw_has_idx_fk_user_seal := (
	SELECT COUNT(*)
	FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND INDEX_NAME = 'idx_timesheet_week_fk_user_seal'
);
SET @tsw_add_idx_fk_user_seal := IF(
	@tsw_has_idx_fk_user_seal = 0,
	'ALTER TABLE llx_timesheet_week ADD INDEX idx_timesheet_week_fk_user_seal (fk_user_seal)',
	'SELECT 1'
);
PREPARE tsw_idx_fk_user_seal_stmt FROM @tsw_add_idx_fk_user_seal;
EXECUTE tsw_idx_fk_user_seal_stmt;
DEALLOCATE PREPARE tsw_idx_fk_user_seal_stmt;

-- EN: Add index on seal date when missing.
SET @tsw_has_idx_date_seal := (
	SELECT COUNT(*)
	FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND INDEX_NAME = 'idx_timesheet_week_date_seal'
);
SET @tsw_add_idx_date_seal := IF(
	@tsw_has_idx_date_seal = 0,
	'ALTER TABLE llx_timesheet_week ADD INDEX idx_timesheet_week_date_seal (date_seal)',
	'SELECT 1'
);
PREPARE tsw_idx_date_seal_stmt FROM @tsw_add_idx_date_seal;
EXECUTE tsw_idx_date_seal_stmt;
DEALLOCATE PREPARE tsw_idx_date_seal_stmt;

-- EN: Ensure TimesheetWeek trigger definitions exist in the action trigger dictionary.
-- FR: Garantit la présence des triggers TimesheetWeek dans le dictionnaire des actions.
UPDATE llx_c_action_trigger
SET elementtype = 'timesheetweek@timesheetweek'
WHERE code IN ('TIMESHEETWEEK_CREATE', 'TIMESHEETWEEK_SAVE', 'TIMESHEETWEEK_SUBMIT', 'TIMESHEETWEEK_APPROVE', 'TIMESHEETWEEK_REFUSE', 'TIMESHEETWEEK_DELETE', 'TIMESHEETWEEK_SEAL', 'TIMESHEETWEEK_REOPEN');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_CREATE', 'Création feuille de temps', 'Déclenché quand une feuille de temps est créée.', 'timesheetweek@timesheetweek', 2098
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_CREATE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_SAVE', 'Sauvegarde feuille de temps', 'Déclenché quand une feuille de temps est sauvegardée.', 'timesheetweek@timesheetweek', 2099
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_SAVE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_SUBMIT', 'Soumission feuille de temps', 'Déclenché quand une feuille de temps est soumise.', 'timesheetweek@timesheetweek', 2100
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_SUBMIT');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_APPROVE', 'Approbation feuille de temps', 'Déclenché quand une feuille de temps est approuvée.', 'timesheetweek@timesheetweek', 2101
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_APPROVE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_REFUSE', 'Refus feuille de temps', 'Déclenché quand une feuille de temps est refusée.', 'timesheetweek@timesheetweek', 2102
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_REFUSE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_DELETE', 'Suppression feuille de temps', 'Déclenché quand une feuille de temps est supprimée.', 'timesheetweek@timesheetweek', 2103
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_DELETE');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_SEAL', 'Scellement feuille de temps', 'Déclenché quand une feuille de temps est scellée.', 'timesheetweek@timesheetweek', 2104
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_SEAL');

INSERT INTO llx_c_action_trigger (code, label, description, elementtype, rang)
SELECT 'TIMESHEETWEEK_REOPEN', 'Réouverture feuille de temps', 'Déclenché quand une feuille de temps repasse en brouillon.', 'timesheetweek@timesheetweek', 2105
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_REOPEN');
