INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT
    0,
    'timesheetweek',
    'actioncomm_send',
    'fr_FR',
    0,
    NULL,
    NOW(),
    'Rappel du vendredi soir',
    100,
    1,
    'isModEnabled(\"timesheetweek\")',
    NULL,
    "Rappel Feuilles d\'heures hebdomadaires",
    "Bonjour,<div style=\"margin-left:40px\"><br>Merci de soumettre vos feuilles d\'heures de la semaine pour lundi matin 8h.</div><div style=\"margin-left:80px\"><br>Bon week-end.</div>"
WHERE NOT EXISTS (
    SELECT 1
    FROM llx_c_email_templates
    WHERE module = 'timesheetweek'
    AND type_template = 'actioncomm_send'
    AND lang = 'fr_FR'
    AND label = 'Rappel du vendredi soir'
);

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
SET topic = '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'fr_FR' AND label = 'Notification TimesheetWeek' AND (topic IS NULL OR topic = '');

UPDATE llx_c_email_templates
SET content = '__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'fr_FR' AND label = 'Notification TimesheetWeek' AND (content IS NULL OR content = '');

UPDATE llx_c_email_templates
SET module = 'timesheetweek', type_template = 'timesheetweek@timesheetweek', position = 200, active = 1, enabled = 'isModEnabled(\"timesheetweek\")', joinfiles = 0
WHERE entity = 0
AND lang = 'en_US'
AND label = 'Notification TimesheetWeek';

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'Notification TimesheetWeek',200,1,'isModEnabled(\"timesheetweek\")',0,'__TIMESHEETWEEK_NOTIFICATION_SUBJECT__','__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'Notification TimesheetWeek');

UPDATE llx_c_email_templates
SET topic = '__TIMESHEETWEEK_NOTIFICATION_SUBJECT__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'en_US' AND label = 'Notification TimesheetWeek' AND (topic IS NULL OR topic = '');

UPDATE llx_c_email_templates
SET content = '__TIMESHEETWEEK_NOTIFICATION_BODY__'
WHERE module = 'timesheetweek' AND type_template = 'timesheetweek@timesheetweek' AND lang = 'en_US' AND label = 'Notification TimesheetWeek' AND (content IS NULL OR content = '');

UPDATE llx_c_email_templates
SET type_template = 'timesheetweek@timesheetweek'
WHERE module = 'timesheetweek'
AND type_template = 'timesheetweek_notification';

UPDATE llx_const
SET value = 'Notification TimesheetWeek'
WHERE name = 'TIMESHEETWEEK_MODIFY_TEMPLATE'
AND value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER';

INSERT INTO llx_const (name, type, value, note, visible, entity)
SELECT 'TIMESHEETWEEK_CREATE_TEMPLATE', oldc.type, oldc.value, oldc.note, oldc.visible, oldc.entity
FROM llx_const AS oldc
WHERE oldc.name = 'TIMESHEETWEEK_TIMESHEETWEEK_CREATE_TEMPLATE'
AND oldc.value <> ''
AND NOT EXISTS (SELECT 1 FROM llx_const AS newc WHERE newc.name = 'TIMESHEETWEEK_CREATE_TEMPLATE' AND newc.entity = oldc.entity);

INSERT INTO llx_const (name, type, value, note, visible, entity)
SELECT 'TIMESHEETWEEK_MODIFY_TEMPLATE', oldc.type, oldc.value, oldc.note, oldc.visible, oldc.entity
FROM llx_const AS oldc
WHERE oldc.name = 'TIMESHEETWEEK_TIMESHEETWEEK_UPDATE_TEMPLATE'
AND oldc.value <> ''
AND NOT EXISTS (SELECT 1 FROM llx_const AS newc WHERE newc.name = 'TIMESHEETWEEK_MODIFY_TEMPLATE' AND newc.entity = oldc.entity);

INSERT INTO llx_const (name, type, value, note, visible, entity)
SELECT 'TIMESHEETWEEK_DELETE_TEMPLATE', oldc.type, oldc.value, oldc.note, oldc.visible, oldc.entity
FROM llx_const AS oldc
WHERE oldc.name = 'TIMESHEETWEEK_TIMESHEETWEEK_DELETE_TEMPLATE'
AND oldc.value <> ''
AND NOT EXISTS (SELECT 1 FROM llx_const AS newc WHERE newc.name = 'TIMESHEETWEEK_DELETE_TEMPLATE' AND newc.entity = oldc.entity);

INSERT INTO llx_const (name, type, value, note, visible, entity)
SELECT 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_CREATE', oldc.type, oldc.value, oldc.note, oldc.visible, oldc.entity
FROM llx_const AS oldc
WHERE oldc.name = 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_TIMESHEETWEEK_CREATE'
AND oldc.value <> ''
AND NOT EXISTS (SELECT 1 FROM llx_const AS newc WHERE newc.name = 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_CREATE' AND newc.entity = oldc.entity);

INSERT INTO llx_const (name, type, value, note, visible, entity)
SELECT 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_MODIFY', oldc.type, oldc.value, oldc.note, oldc.visible, oldc.entity
FROM llx_const AS oldc
WHERE oldc.name = 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_TIMESHEETWEEK_UPDATE'
AND oldc.value <> ''
AND NOT EXISTS (SELECT 1 FROM llx_const AS newc WHERE newc.name = 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_MODIFY' AND newc.entity = oldc.entity);

INSERT INTO llx_const (name, type, value, note, visible, entity)
SELECT 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_DELETE', oldc.type, oldc.value, oldc.note, oldc.visible, oldc.entity
FROM llx_const AS oldc
WHERE oldc.name = 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_TIMESHEETWEEK_DELETE'
AND oldc.value <> ''
AND NOT EXISTS (SELECT 1 FROM llx_const AS newc WHERE newc.name = 'MAIN_AGENDA_ACTIONAUTO_TIMESHEETWEEK_DELETE' AND newc.entity = oldc.entity);

UPDATE llx_const
SET value = 'Notification TimesheetWeek'
WHERE name = 'TIMESHEETWEEK_MODIFY_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SUBMIT',210,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ soumise','Bonjour __RECIPIENT_FULLNAME__,\n\nLe salarié __TIMESHEETWEEK_EMPLOYEE_FULLNAME__ a soumis la feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SUBMIT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_APPROVE',220,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ approuvée','Bonjour __RECIPIENT_FULLNAME__,\n\nVotre feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est approuvée par __ACTION_USER_FULLNAME__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_APPROVE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_REFUSE',230,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ refusée','Bonjour __RECIPIENT_FULLNAME__,\n\nVotre feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est refusée par __ACTION_USER_FULLNAME__.\nMotif : __TIMESHEETWEEK_MOTIF__\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_REFUSE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SETDRAFT',240,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ remise en brouillon','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est remise en brouillon par __ACTION_USER_FULLNAME__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SETDRAFT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SEAL',250,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ scellée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est scellée.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_SEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','fr_FR',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_UNSEAL',260,1,'isModEnabled(\"timesheetweek\")',0,'Feuille de temps __TIMESHEETWEEK_REF__ descellée','Bonjour __RECIPIENT_FULLNAME__,\n\nLa feuille de temps __TIMESHEETWEEK_REF__ pour la semaine __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ est descellée par __ACTION_USER_FULLNAME__.\nVous pouvez la consulter ici : __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'fr_FR' AND label = 'TIMESHEETWEEK_NOTIFY_UNSEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SUBMIT',210,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ submitted','Hello __RECIPIENT_FULLNAME__,\n\nThe employee __TIMESHEETWEEK_EMPLOYEE_FULLNAME__ submitted timesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SUBMIT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_APPROVE',220,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ approved','Hello __RECIPIENT_FULLNAME__,\n\nYour timesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was approved by __ACTION_USER_FULLNAME__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_APPROVE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_REFUSE',230,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ refused','Hello __RECIPIENT_FULLNAME__,\n\nYour timesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was refused by __ACTION_USER_FULLNAME__.\nReason: __TIMESHEETWEEK_MOTIF__\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_REFUSE');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SETDRAFT',240,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ reverted to draft','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was reverted to draft by __ACTION_USER_FULLNAME__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SETDRAFT');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_SEAL',250,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ sealed','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was sealed.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_SEAL');

INSERT INTO llx_c_email_templates (entity,module,type_template,lang,private,fk_user,datec,label,position,active,enabled,joinfiles,topic,content)
SELECT 0,'timesheetweek','timesheetweek@timesheetweek','en_US',0,NULL,NOW(),'TIMESHEETWEEK_NOTIFY_UNSEAL',260,1,'isModEnabled(\"timesheetweek\")',0,'Timesheet __TIMESHEETWEEK_REF__ unsealed','Hello __RECIPIENT_FULLNAME__,\n\nTimesheet __TIMESHEETWEEK_REF__ for week __TIMESHEETWEEK_WEEK__/__TIMESHEETWEEK_YEAR__ was unsealed by __ACTION_USER_FULLNAME__.\nYou can review it here: __TIMESHEETWEEK_URL__\n\n__TIMESHEETWEEK_MAIL_SIGNATURE__'
WHERE NOT EXISTS (SELECT 1 FROM llx_c_email_templates WHERE entity = 0 AND lang = 'en_US' AND label = 'TIMESHEETWEEK_NOTIFY_UNSEAL');

UPDATE llx_const
SET value = 'Notification TimesheetWeek'
WHERE name = 'TIMESHEETWEEK_MODIFY_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER');

UPDATE llx_const
SET value = 'TIMESHEETWEEK_NOTIFY_SUBMIT'
WHERE name = 'TIMESHEETWEEK_SUBMIT_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' OR value = 'Notification TimesheetWeek');

UPDATE llx_const
SET value = 'TIMESHEETWEEK_NOTIFY_APPROVE'
WHERE name = 'TIMESHEETWEEK_APPROVE_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' OR value = 'Notification TimesheetWeek');

UPDATE llx_const
SET value = 'TIMESHEETWEEK_NOTIFY_REFUSE'
WHERE name = 'TIMESHEETWEEK_REFUSE_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' OR value = 'Notification TimesheetWeek');

UPDATE llx_const
SET value = 'TIMESHEETWEEK_NOTIFY_SETDRAFT'
WHERE name = 'TIMESHEETWEEK_SETDRAFT_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' OR value = 'Notification TimesheetWeek');

UPDATE llx_const
SET value = 'TIMESHEETWEEK_NOTIFY_SEAL'
WHERE name = 'TIMESHEETWEEK_SEAL_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' OR value = 'Notification TimesheetWeek');

UPDATE llx_const
SET value = 'TIMESHEETWEEK_NOTIFY_UNSEAL'
WHERE name = 'TIMESHEETWEEK_UNSEAL_TEMPLATE'
AND (value = '' OR value = 'TIMESHEETWEEK_NOTIFY_WORKFLOW_ROUTER' OR value = 'Notification TimesheetWeek');

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
VALUES ('timesheetweek@timesheetweek', 'TIMESHEETWEEK_MODIFY', 'agenda:notification', 'Modify weekly timesheet', 'Executed when a weekly timesheet is modified without a dedicated workflow transition.', 45000309);
