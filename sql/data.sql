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
