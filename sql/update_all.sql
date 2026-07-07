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

-- EN: Add the native Notification router template selected by TIMESHEETWEEK_TIMESHEETWEEK_UPDATE_TEMPLATE when unset by activation.
UPDATE llx_c_email_templates
SET type_template = 'timesheetweek@timesheetweek'
WHERE module = 'timesheetweek'
AND type_template = 'timesheetweek'
AND lang = 'fr_FR'
AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER'
AND NOT EXISTS (
	SELECT 1
	FROM (
		SELECT rowid
		FROM llx_c_email_templates
		WHERE module = 'timesheetweek'
		AND type_template = 'timesheetweek@timesheetweek'
		AND lang = 'fr_FR'
		AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER'
	) AS tsw_existing_router_template
);

UPDATE llx_c_email_templates
SET type_template = 'timesheetweek@timesheetweek'
WHERE module = 'timesheetweek'
AND type_template = 'timesheetweek'
AND lang = 'en_US'
AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER'
AND NOT EXISTS (
	SELECT 1
	FROM (
		SELECT rowid
		FROM llx_c_email_templates
		WHERE module = 'timesheetweek'
		AND type_template = 'timesheetweek@timesheetweek'
		AND lang = 'en_US'
		AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER'
	) AS tsw_existing_router_template
);

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER',200,1,'isModEnabled(\"timesheetweek\")',0,'__TIMESHEETWEEK_NOTIFICATION_SUBJECT__','__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER');

UPDATE llx_c_email_templates
SET topic = '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' AND (topic IS NULL OR topic = '');

UPDATE llx_c_email_templates
SET content = '__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' AND (content IS NULL OR content = '');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER',200,1,'isModEnabled(\"timesheetweek\")',0,'__TIMESHEETWEEK_NOTIFICATION_SUBJECT__','__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER');

UPDATE llx_c_email_templates
SET topic = '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' AND (topic IS NULL OR topic = '');

UPDATE llx_c_email_templates
SET content = '__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' AND (content IS NULL OR content = '');

-- EN: Add workflow email templates used by configurable TimesheetWeek step notifications.
INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SUBMIT',210,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ soumise','Bonjour __RECIPIENT_FULLNAME__,\n\nLe salarié __TIMESHEETWEEK_EMPLOYEE_FULLNAME__ a soumis la feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SUBMIT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_APPROVE',220,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ approuvée','Bonjour __RECIPIENT_FULLNAME__,\n\nVotre feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est approuvée par __ACTION_USER_FULLNAME__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_APPROVE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_REFUSE',230,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ refusée','Bonjour __RECIPIENT_FULLNAME__,\n\nVotre feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est refusée par __ACTION_USER_FULLNAME__.\nMotif : __TIMESHEETWEEK_MOTIF__\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_REFUSE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SETDRAFT',240,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ remise en brouillon','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est remise en brouillon par __ACTION_USER_FULLNAME__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SETDRAFT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SEAL',250,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ scellée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est scellée.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_UNSEAL',260,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ descellée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est descellée par __ACTION_USER_FULLNAME__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_UNSEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SUBMIT',210,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ submitted','Hello __RECIPIENT_FULLNAME__,\n\nThe employee __TIMESHEETWEEK_EMPLOYEE_FULLNAME__ submitted timesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SUBMIT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_APPROVE',220,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ approved','Hello __RECIPIENT_FULLNAME__,\n\nYour timesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was approved by __ACTION_USER_FULLNAME__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_APPROVE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_REFUSE',230,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ refused','Hello __RECIPIENT_FULLNAME__,\n\nYour timesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was refused by __ACTION_USER_FULLNAME__.\nReason: __TIMESHEETWEEK_MOTIF__\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_REFUSE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SETDRAFT',240,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ reverted to draft','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was reverted to draft by __ACTION_USER_FULLNAME__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SETDRAFT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SEAL',250,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ sealed','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was sealed.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek_notification','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_UNSEAL',260,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ unsealed','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was unsealed by __ACTION_USER_FULLNAME__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE module = 'timesheetweek' AND type_template = 'timesheetweek_notification' AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_UNSEAL');

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
