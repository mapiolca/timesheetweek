-- EN: Add the PDF model column to existing tables when missing.
-- FR: Ajouter la colonne du modèle PDF aux tables existantes lorsqu'elle est absente.
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS model_pdf VARCHAR(255) DEFAULT NULL AFTER status;
