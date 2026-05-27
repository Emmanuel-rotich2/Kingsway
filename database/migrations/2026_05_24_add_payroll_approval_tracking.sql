-- Add payroll approval tracking before payment release

ALTER TABLE staff_payroll
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER approved_at;
