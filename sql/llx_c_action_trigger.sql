-- Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
--
-- Populate business events for timesheetweek module.

UPDATE llx_c_action_trigger
SET elementtype = 'timesheetweek@timesheetweek'
WHERE code IN ('TIMESHEETWEEK_CREATE', 'TIMESHEETWEEK_SAVE', 'TIMESHEETWEEK_SUBMIT', 'TIMESHEETWEEK_APPROVE', 'TIMESHEETWEEK_REFUSE', 'TIMESHEETWEEK_DELETE', 'TIMESHEETWEEK_SEAL', 'TIMESHEETWEEK_BACKTODRAFT');

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
SELECT 'TIMESHEETWEEK_BACKTODRAFT', 'Réouverture feuille de temps', 'Déclenché quand une feuille de temps repasse en brouillon.', 'timesheetweek@timesheetweek', 2105
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_action_trigger WHERE code = 'TIMESHEETWEEK_BACKTODRAFT');
