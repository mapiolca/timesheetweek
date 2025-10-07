-- EN: Add weekly zone and meal counters on existing timesheets.
-- FR: Ajoute les compteurs hebdomadaires de zones et de paniers sur les feuilles existantes.
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS zone1_count SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS zone2_count SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS zone3_count SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS zone4_count SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS zone5_count SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE llx_timesheet_week ADD COLUMN IF NOT EXISTS meal_count SMALLINT NOT NULL DEFAULT 0;
