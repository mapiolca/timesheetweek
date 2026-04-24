-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- Populate business events for timesheetweek module.

UPDATE llx_c_action_trigger
SET elementtype = 'timesheetweek@timesheetweek'
WHERE code IN ('TIMESHEETWEEK_SUBMIT', 'TIMESHEETWEEK_APPROVE', 'TIMESHEETWEEK_REFUSE');

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
