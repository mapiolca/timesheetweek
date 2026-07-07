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


-- EN: Add the justification column to existing tables when missing.
SET @tsw_has_motif := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'motif'
);
SET @tsw_add_motif := IF(
	@tsw_has_motif = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN motif TEXT AFTER note',
	'SELECT 1'
);
PREPARE tsw_motif_stmt FROM @tsw_add_motif;
EXECUTE tsw_motif_stmt;
DEALLOCATE PREPARE tsw_motif_stmt;

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

-- EN: Add the last generated main document column to existing tables when missing.
SET @tsw_has_last_main_doc := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'last_main_doc'
);
SET @tsw_add_last_main_doc := IF(
	@tsw_has_last_main_doc = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN last_main_doc VARCHAR(255) DEFAULT NULL AFTER model_pdf',
	'SELECT 1'
);
PREPARE tsw_last_main_doc_stmt FROM @tsw_add_last_main_doc;
EXECUTE tsw_last_main_doc_stmt;
DEALLOCATE PREPARE tsw_last_main_doc_stmt;

-- EN: Keep only CRUD TimesheetWeek triggers configurable through native Agenda/Notification pages.
DELETE FROM llx_c_action_trigger
WHERE code IN (
	'TIMESHEETWEEK_SUBMIT',
	'TIMESHEETWEEK_APPROVE',
	'TIMESHEETWEEK_REFUSE',
	'TIMESHEETWEEK_SUBMITTED',
	'TIMESHEETWEEK_APPROVED',
	'TIMESHEETWEEK_REFUSED',
	'TSWK_CREATE',
	'TSWK_SUBMIT',
	'TSWK_REOPEN',
	'TSWK_APPROVE',
	'TSWK_SEAL',
	'TSWK_UNSEAL',
	'TSWK_REFUSE',
	'TSWK_DELETE'
)
AND elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek');

DELETE FROM llx_c_action_trigger
WHERE code IN (
	'TIMESHEETWEEK_TIMESHEETWEEK_CREATE',
	'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE',
	'TIMESHEETWEEK_TIMESHEETWEEK_DELETE'
)
AND elementtype IN ('timesheetweek', 'timesheetweek@timesheetweek');

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_TIMESHEETWEEK_CREATE', 'agenda:notification', 'Create weekly timesheet', 'Executed when a weekly timesheet is created; the precise business context is carried by the object context', 45000301);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE', 'agenda:notification', 'Update weekly timesheet', 'Executed when a weekly timesheet is updated; status, seal and refusal details are carried by the object context', 45000302);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_TIMESHEETWEEK_DELETE', 'agenda:notification', 'Delete weekly timesheet', 'Executed when a weekly timesheet is deleted; the object context identifies the deleted sheet', 45000303);

-- EN: Repair historical Agenda links that used the short element type.
UPDATE llx_actioncomm
SET elementtype = 'timesheetweek@timesheetweek'
WHERE elementtype = 'timesheetweek'
	AND fk_element IN (SELECT rowid FROM llx_timesheet_week);
