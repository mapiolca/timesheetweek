-- EN: Add the PDF model column to existing tables / FR: Ajoute la colonne de modèle PDF aux tables existantes.
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS model_pdf VARCHAR(255) DEFAULT NULL AFTER status;
