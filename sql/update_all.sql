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

-- EN: Add the creation user column to existing tables when missing.
SET @tsw_has_fk_user_creat := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'fk_user_creat'
);
SET @tsw_add_fk_user_creat := IF(
	@tsw_has_fk_user_creat = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN fk_user_creat INT DEFAULT NULL AFTER fk_user',
	'SELECT 1'
);
PREPARE tsw_fk_user_creat_stmt FROM @tsw_add_fk_user_creat;
EXECUTE tsw_fk_user_creat_stmt;
DEALLOCATE PREPARE tsw_fk_user_creat_stmt;

-- EN: Add the last modification user column to existing tables when missing.
SET @tsw_has_fk_user_modif := (
	SELECT COUNT(*)
	FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND COLUMN_NAME = 'fk_user_modif'
);
SET @tsw_add_fk_user_modif := IF(
	@tsw_has_fk_user_modif = 0,
	'ALTER TABLE llx_timesheet_week ADD COLUMN fk_user_modif INT DEFAULT NULL AFTER fk_user_creat',
	'SELECT 1'
);
PREPARE tsw_fk_user_modif_stmt FROM @tsw_add_fk_user_modif;
EXECUTE tsw_fk_user_modif_stmt;
DEALLOCATE PREPARE tsw_fk_user_modif_stmt;

UPDATE llx_timesheet_week
SET fk_user_creat = fk_user
WHERE fk_user_creat IS NULL
AND fk_user IS NOT NULL;

UPDATE llx_timesheet_week
SET fk_user_modif = COALESCE(fk_user_valid, fk_user_creat, fk_user)
WHERE fk_user_modif IS NULL
AND COALESCE(fk_user_valid, fk_user_creat, fk_user) IS NOT NULL;

-- EN: Add index on creation user when missing.
SET @tsw_has_idx_fk_user_creat := (
	SELECT COUNT(*)
	FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND INDEX_NAME = 'idx_timesheet_week_user_creat'
);
SET @tsw_add_idx_fk_user_creat := IF(
	@tsw_has_idx_fk_user_creat = 0,
	'ALTER TABLE llx_timesheet_week ADD INDEX idx_timesheet_week_user_creat (fk_user_creat)',
	'SELECT 1'
);
PREPARE tsw_idx_fk_user_creat_stmt FROM @tsw_add_idx_fk_user_creat;
EXECUTE tsw_idx_fk_user_creat_stmt;
DEALLOCATE PREPARE tsw_idx_fk_user_creat_stmt;

-- EN: Add index on last modification user when missing.
SET @tsw_has_idx_fk_user_modif := (
	SELECT COUNT(*)
	FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'llx_timesheet_week'
		AND INDEX_NAME = 'idx_timesheet_week_user_modif'
);
SET @tsw_add_idx_fk_user_modif := IF(
	@tsw_has_idx_fk_user_modif = 0,
	'ALTER TABLE llx_timesheet_week ADD INDEX idx_timesheet_week_user_modif (fk_user_modif)',
	'SELECT 1'
);
PREPARE tsw_idx_fk_user_modif_stmt FROM @tsw_add_idx_fk_user_modif;
EXECUTE tsw_idx_fk_user_modif_stmt;
DEALLOCATE PREPARE tsw_idx_fk_user_modif_stmt;

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

-- EN: Add the native Notification router template selected by the TimesheetWeek notification events when unset by activation.
UPDATE llx_c_email_templates
SET label = 'Notification TimesheetWeek'
WHERE module = 'timesheetweek'
AND type_template IN ('timesheetweek', 'timesheetweek@timesheetweek')
AND lang = 'fr_FR'
AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER'
AND NOT EXISTS (
	SELECT 1
	FROM (
		SELECT rowid
		FROM llx_c_email_templates
		WHERE entity = 0
		AND lang = 'fr_FR'
		AND label = 'Notification TimesheetWeek'
	) AS tsw_existing_router_template_label
);

UPDATE llx_c_email_templates
SET label = 'Notification TimesheetWeek'
WHERE module = 'timesheetweek'
AND type_template IN ('timesheetweek', 'timesheetweek@timesheetweek')
AND lang = 'en_US'
AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER'
AND NOT EXISTS (
	SELECT 1
	FROM (
		SELECT rowid
		FROM llx_c_email_templates
		WHERE entity = 0
		AND lang = 'en_US'
		AND label = 'Notification TimesheetWeek'
	) AS tsw_existing_router_template_label
);

UPDATE llx_c_email_templates
SET type_template = 'timesheetweek@timesheetweek'
WHERE module = 'timesheetweek'
AND type_template = 'timesheetweek'
AND lang = 'fr_FR'
AND label = 'Notification TimesheetWeek'
AND NOT EXISTS (
	SELECT 1
	FROM (
		SELECT rowid
		FROM llx_c_email_templates
		WHERE entity = 0
		AND lang = 'fr_FR'
		AND label = 'Notification TimesheetWeek'
	) AS tsw_existing_router_template
);

UPDATE llx_c_email_templates
SET type_template = 'timesheetweek@timesheetweek'
WHERE module = 'timesheetweek'
AND type_template = 'timesheetweek'
AND lang = 'en_US'
AND label = 'Notification TimesheetWeek'
AND NOT EXISTS (
	SELECT 1
	FROM (
		SELECT rowid
		FROM llx_c_email_templates
		WHERE entity = 0
		AND lang = 'en_US'
		AND label = 'Notification TimesheetWeek'
	) AS tsw_existing_router_template
);

UPDATE llx_c_email_templates
SET module = 'timesheetweek', type_template = 'timesheetweek@timesheetweek', position = 200, active = 1, enabled = 'isModEnabled(\"timesheetweek\")', joinfiles = 0
WHERE entity = 0
AND lang = 'fr_FR'
AND label = 'Notification TimesheetWeek';

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'Notification TimesheetWeek',200,1,'isModEnabled(\"timesheetweek\")',0,'__TIMESHEETWEEK_NOTIFICATION_SUBJECT__','__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'Notification TimesheetWeek');

UPDATE llx_c_email_templates
SET module = 'timesheetweek', type_template = 'timesheetweek@timesheetweek', position = 200, active = 1, enabled = 'isModEnabled(\"timesheetweek\")', joinfiles = 0
WHERE entity = 0
AND lang = 'en_US'
AND label = 'Notification TimesheetWeek';

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'Notification TimesheetWeek',200,1,'isModEnabled(\"timesheetweek\")',0,'__TIMESHEETWEEK_NOTIFICATION_SUBJECT__','__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'Notification TimesheetWeek');

-- EN: Add workflow email templates used by configurable TimesheetWeek step notifications.
INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SUBMIT',210,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ soumise par __TIMESHEETWEEK_EMPLOYEE_FULLNAME__','Bonjour __RECIPIENT_FULLNAME__,\n\n__TIMESHEETWEEK_EMPLOYEE_FULLNAME__ a soumis la feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__.\n\nValideur : __TIMESHEETWEEK_VALIDATOR_FULLNAME__\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SUBMIT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_APPROVE',220,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ approuvée','Bonjour __RECIPIENT_FULLNAME__,\n\nVotre feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été approuvée par __ACTION_USER_FULLNAME__.\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_APPROVE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_REFUSE',230,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ refusée','Bonjour __RECIPIENT_FULLNAME__,\n\nVotre feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été refusée par __ACTION_USER_FULLNAME__.\nMotif : __TIMESHEETWEEK_MOTIF__\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_REFUSE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SETDRAFT',240,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ repassée en brouillon','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été repassée en brouillon par __ACTION_USER_FULLNAME__.\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SETDRAFT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SEAL',250,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ scellée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été scellée par __ACTION_USER_FULLNAME__.\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_UNSEAL',260,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ descellée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été descellée par __ACTION_USER_FULLNAME__.\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_UNSEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SUBMIT',210,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ submitted by __TIMESHEETWEEK_EMPLOYEE_FULLNAME__','Hello __RECIPIENT_FULLNAME__,\n\n__TIMESHEETWEEK_EMPLOYEE_FULLNAME__ submitted timesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__.\n\nValidator: __TIMESHEETWEEK_VALIDATOR_FULLNAME__\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SUBMIT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_APPROVE',220,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ approved','Hello __RECIPIENT_FULLNAME__,\n\nYour timesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was approved by __ACTION_USER_FULLNAME__.\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_APPROVE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_REFUSE',230,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ refused','Hello __RECIPIENT_FULLNAME__,\n\nYour timesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was refused by __ACTION_USER_FULLNAME__.\nReason: __TIMESHEETWEEK_MOTIF__\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_REFUSE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SETDRAFT',240,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ reverted to draft','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was reverted to draft by __ACTION_USER_FULLNAME__.\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SETDRAFT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SEAL',250,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ sealed','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was sealed by __ACTION_USER_FULLNAME__.\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_UNSEAL',260,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ unsealed','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was unsealed by __ACTION_USER_FULLNAME__.\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_UNSEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_CREATE',205,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ créée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été créée pour __TIMESHEETWEEK_EMPLOYEE_FULLNAME__.\nStatut actuel : __TIMESHEETWEEK_STATUS__\nAccès direct : __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_CREATE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_DELETE',270,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Feuille de temps __REF__ supprimée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ a été supprimée par __ACTION_USER_FULLNAME__.\nSalarié : __TIMESHEETWEEK_EMPLOYEE_FULLNAME__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_DELETE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_CREATE',205,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ created','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was created for __TIMESHEETWEEK_EMPLOYEE_FULLNAME__.\nCurrent status: __TIMESHEETWEEK_STATUS__\nDirect access: __TIMESHEETWEEK_URL_RAW__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_CREATE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_DELETE',270,1,'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',0,'Timesheet __REF__ deleted','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was deleted by __ACTION_USER_FULLNAME__.\nEmployee: __TIMESHEETWEEK_EMPLOYEE_FULLNAME__\n\n__SENDEREMAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_DELETE');

UPDATE llx_c_email_templates
SET module = 'timesheetweek',
type_template = 'timesheetweek@timesheetweek',
active = 1,
enabled = 'isModEnabled(\"timesheetweek\") && isModEnabled(\"notification\")',
joinfiles = 0
WHERE entity = 0
AND lang IN ('fr_FR', 'en_US')
AND label IN (
	'TIMESHEETWEEK_NOTIFY_CREATE',
	'TIMESHEETWEEK_NOTIFY_SUBMIT',
	'TIMESHEETWEEK_NOTIFY_APPROVE',
	'TIMESHEETWEEK_NOTIFY_REFUSE',
	'TIMESHEETWEEK_NOTIFY_SETDRAFT',
	'TIMESHEETWEEK_NOTIFY_SEAL',
	'TIMESHEETWEEK_NOTIFY_UNSEAL',
	'TIMESHEETWEEK_NOTIFY_DELETE'
);

UPDATE llx_const
SET type = 'emailtemplate:timesheetweek@timesheetweek'
WHERE name IN (
	'TIMESHEETWEEK_CREATE_TEMPLATE',
	'TIMESHEETWEEK_MODIFY_TEMPLATE',
	'TIMESHEETWEEK_DELETE_TEMPLATE',
	'TIMESHEETWEEK_SUBMIT_TEMPLATE',
	'TIMESHEETWEEK_APPROVE_TEMPLATE',
	'TIMESHEETWEEK_REFUSE_TEMPLATE',
	'TIMESHEETWEEK_SETDRAFT_TEMPLATE',
	'TIMESHEETWEEK_SEAL_TEMPLATE',
	'TIMESHEETWEEK_UNSEAL_TEMPLATE'
);

-- EN: Keep only TimesheetWeek business triggers configurable through native Agenda/Notification pages.
DELETE FROM llx_c_action_trigger
WHERE code IN (
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

DELETE FROM llx_c_action_trigger
WHERE code IN (
	'TIMESHEETWEEK_CREATE',
	'TIMESHEETWEEK_SUBMIT',
	'TIMESHEETWEEK_APPROVE',
	'TIMESHEETWEEK_REFUSE',
	'TIMESHEETWEEK_SETDRAFT',
	'TIMESHEETWEEK_SEAL',
	'TIMESHEETWEEK_UNSEAL',
	'TIMESHEETWEEK_DELETE',
	'TIMESHEETWEEK_MODIFY'
)
AND elementtype = 'timesheetweek';

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_CREATE', 'agenda:notification', 'Create weekly timesheet', 'Executed when a weekly timesheet is created.', 45000301);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_SUBMIT', 'agenda:notification', 'Submit weekly timesheet', 'Executed when a weekly timesheet is submitted for approval.', 45000302);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_APPROVE', 'agenda:notification', 'Approve weekly timesheet', 'Executed when a weekly timesheet is approved.', 45000303);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_REFUSE', 'agenda:notification', 'Refuse weekly timesheet', 'Executed when a weekly timesheet is refused.', 45000304);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_SETDRAFT', 'agenda:notification', 'Revert weekly timesheet to draft', 'Executed when a weekly timesheet is reverted to draft.', 45000305);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_SEAL', 'agenda:notification', 'Seal weekly timesheet', 'Executed when a weekly timesheet is sealed.', 45000306);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_UNSEAL', 'agenda:notification', 'Unseal weekly timesheet', 'Executed when a weekly timesheet is unsealed.', 45000307);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_DELETE', 'agenda:notification', 'Delete weekly timesheet', 'Executed when a weekly timesheet is deleted.', 45000308);

INSERT IGNORE INTO llx_c_action_trigger (elementtype, code, contexts, label, description, rang)
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_MODIFY', 'agenda', 'Modify weekly timesheet', 'Executed when a weekly timesheet is modified without a dedicated workflow transition.', 45000309);

-- EN: Repair historical Agenda links that used the short element type.
UPDATE llx_actioncomm
SET elementtype = 'timesheetweek@timesheetweek'
WHERE elementtype = 'timesheetweek'
	AND fk_element IN (SELECT rowid FROM llx_timesheet_week);

-- EN: Remove duplicate Agenda entries created by the historical manual ActionComm path.
-- EN: Keep the event whose title contains the timesheet reference.
DELETE a
FROM llx_actioncomm AS a
INNER JOIN llx_timesheet_week AS t ON t.rowid = a.fk_element
INNER JOIN llx_actioncomm AS keepa ON keepa.elementtype = 'timesheetweek@timesheetweek'
	AND keepa.fk_element = a.fk_element
	AND keepa.entity = a.entity
	AND (COALESCE(keepa.code, '') = COALESCE(a.code, '') OR keepa.code = CONCAT('AC_', a.code) OR a.code = CONCAT('AC_', keepa.code) OR COALESCE(keepa.code, '') = '' OR COALESCE(a.code, '') = '')
	AND DATE_FORMAT(keepa.datep, '%Y-%m-%d %H:%i') = DATE_FORMAT(a.datep, '%Y-%m-%d %H:%i')
	AND keepa.id <> a.id
	AND keepa.label LIKE CONCAT('%', t.ref, '%')
WHERE a.elementtype = 'timesheetweek@timesheetweek'
	AND a.label NOT LIKE CONCAT('%', t.ref, '%')
	AND TRIM(REPLACE(REPLACE(keepa.label, t.ref, ''), '  ', ' ')) = TRIM(a.label);

-- EN: If the same referenced event was inserted twice, keep the oldest entry.
DELETE a
FROM llx_actioncomm AS a
INNER JOIN llx_timesheet_week AS t ON t.rowid = a.fk_element
INNER JOIN llx_actioncomm AS keepa ON keepa.elementtype = 'timesheetweek@timesheetweek'
	AND keepa.fk_element = a.fk_element
	AND keepa.entity = a.entity
	AND (COALESCE(keepa.code, '') = COALESCE(a.code, '') OR keepa.code = CONCAT('AC_', a.code) OR a.code = CONCAT('AC_', keepa.code) OR COALESCE(keepa.code, '') = '' OR COALESCE(a.code, '') = '')
	AND DATE_FORMAT(keepa.datep, '%Y-%m-%d %H:%i') = DATE_FORMAT(a.datep, '%Y-%m-%d %H:%i')
	AND keepa.label = a.label
	AND keepa.id < a.id
WHERE a.elementtype = 'timesheetweek@timesheetweek'
	AND a.label LIKE CONCAT('%', t.ref, '%');
