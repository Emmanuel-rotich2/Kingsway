-- Add required payroll payment identity fields to staff profiles
-- Staff cannot be payroll-eligible without complete statutory and payment details.

ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER last_name,
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) NULL AFTER nhif_no;
