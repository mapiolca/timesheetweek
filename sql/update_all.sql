-- EN: Add the PDF model column to existing tables.
ALTER TABLE llx_timesheet_week ADD COLUMN model_pdf VARCHAR(255) DEFAULT NULL AFTER status;
