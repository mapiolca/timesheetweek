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

-- EN: Add the standard creation date column when missing.
SET @tsw_has_datec := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'datec'
);
SET @tsw_add_datec := IF(
	@tsw_has_datec = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN datec DATETIME DEFAULT CURRENT_TIMESTAMP AFTER note',
	'SELECT 1'
);
PREPARE tsw_datec_stmt FROM @tsw_add_datec;
EXECUTE tsw_datec_stmt;
DEALLOCATE PREPARE tsw_datec_stmt;

-- EN: Add the author column when missing.
SET @tsw_has_author := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'fk_user_author'
);
SET @tsw_add_author := IF(
	@tsw_has_author = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN fk_user_author INT DEFAULT NULL AFTER datev',
	'SELECT 1'
);
PREPARE tsw_author_stmt FROM @tsw_add_author;
EXECUTE tsw_author_stmt;
DEALLOCATE PREPARE tsw_author_stmt;

-- EN: Add the standard validation date column when missing.
SET @tsw_has_datev := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'datev'
);
SET @tsw_add_datev := IF(
	@tsw_has_datev = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN datev DATETIME DEFAULT NULL AFTER date_creation',
	'SELECT 1'
);
PREPARE tsw_datev_stmt FROM @tsw_add_datev;
EXECUTE tsw_datev_stmt;
DEALLOCATE PREPARE tsw_datev_stmt;

-- EN: Backfill new columns with existing data when possible.
UPDATE llx_timesheet_week
SET datec = COALESCE(datec, date_creation)
WHERE datec IS NULL;
UPDATE llx_timesheet_week
SET fk_user_author = COALESCE(fk_user_author, fk_user)
WHERE fk_user_author IS NULL;
UPDATE llx_timesheet_week
SET datev = COALESCE(datev, date_validation)
WHERE datev IS NULL;
